<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1;

use App\Core\{Request, Response, Database, RefGenerator, Validation, TenantContext};
use App\Core\Exceptions\{NotFoundException, ForbiddenException};
use App\Domain\Authorization\AuthorizationService;

final class GeoController
{
    public function __construct(
        private Database $db,
        private ?AuthorizationService $authorization = null,
    ) {}

    // ── Public listing endpoints ──────────────────────────────────

    public function states(Request $r): Response
    {
        $rows = $this->db->fetchAll(
            "SELECT state_ref, state_code, state_name FROM states ORDER BY state_name ASC"
        );
        return Response::json(200, $rows);
    }

    public function districts(Request $r): Response
    {
        $stateRef = $r->query('state_ref', '');
        if ($stateRef !== '') {
            $rows = $this->db->fetchAll(
                "SELECT district_ref, state_ref, district_name FROM districts WHERE state_ref = ? ORDER BY district_name ASC",
                [$stateRef]
            );
        } else {
            $rows = $this->db->fetchAll(
                "SELECT district_ref, state_ref, district_name FROM districts ORDER BY district_name ASC"
            );
        }
        return Response::json(200, $rows);
    }

    /** GEO-005: Cascading — districts by state_ref (alias for front-end dropdown). */
    public function districtsByState(Request $r): Response
    {
        $stateRef = $r->param('state_ref');
        $rows = $this->db->fetchAll(
            "SELECT district_ref, state_ref, district_name FROM districts WHERE state_ref = ? ORDER BY district_name ASC",
            [$stateRef]
        );
        return Response::json(200, $rows);
    }

    /** GEO-010: Cascading — cities by district_ref. */
    public function citiesByDistrict(Request $r): Response
    {
        $districtRef = $r->param('district_ref');
        $rows = $this->db->fetchAll(
            "SELECT city_ref, district_ref, city_name FROM cities WHERE district_ref = ? ORDER BY city_name ASC",
            [$districtRef]
        );
        return Response::json(200, $rows);
    }

    /** GEO-011: List all cities with optional state_ref / district_ref filters. */
    public function cities(Request $r): Response
    {
        $districtRef = $r->query('district_ref', '');
        $stateRef    = $r->query('state_ref', '');

        $sql    = "SELECT c.city_ref, c.district_ref, c.city_name, d.state_ref
                   FROM cities c JOIN districts d ON d.district_ref = c.district_ref";
        $where  = [];
        $params = [];

        if ($districtRef !== '') {
            $where[]  = 'c.district_ref = ?';
            $params[] = $districtRef;
        }
        if ($stateRef !== '') {
            $where[]  = 'd.state_ref = ?';
            $params[] = $stateRef;
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY c.city_name ASC';

        return Response::json(200, $this->db->fetchAll($sql, $params));
    }

    /** GEO-016: Cascading — pincodes by city_ref. */
    public function pincodesByCity(Request $r): Response
    {
        $cityRef = $r->param('city_ref');
        $rows = $this->db->fetchAll(
            "SELECT p.pincode, p.city_ref, p.district_ref, p.state_ref
             FROM pincodes p WHERE p.city_ref = ? ORDER BY p.pincode ASC",
            [$cityRef]
        );
        return Response::json(200, $rows);
    }

    /** GEO-017: List all pincodes with optional city/district/state filters. */
    public function pincodes(Request $r): Response
    {
        $cityRef     = $r->query('city_ref', '');
        $districtRef = $r->query('district_ref', '');
        $stateRef    = $r->query('state_ref', '');

        $sql    = "SELECT p.pincode, p.city_ref, c.city_name, p.district_ref, d.district_name, p.state_ref, s.state_name
                   FROM pincodes p
                   LEFT JOIN cities c ON c.city_ref = p.city_ref
                   LEFT JOIN districts d ON d.district_ref = p.district_ref
                   LEFT JOIN states s ON s.state_ref = p.state_ref";
        $where  = [];
        $params = [];

        if ($cityRef !== '') {
            $where[]  = 'p.city_ref = ?';
            $params[] = $cityRef;
        }
        if ($districtRef !== '') {
            $where[]  = 'p.district_ref = ?';
            $params[] = $districtRef;
        }
        if ($stateRef !== '') {
            $where[]  = 'p.state_ref = ?';
            $params[] = $stateRef;
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY p.pincode ASC';

        return Response::json(200, $this->db->fetchAll($sql, $params));
    }

    /** Single pincode lookup (existing). */
    public function pincode(Request $r): Response
    {
        $pin = $r->param('pin');
        $row = $this->db->fetchOne(
            "SELECT p.pincode, p.city_ref, c.city_name, p.district_ref, d.district_name, p.state_ref, s.state_code, s.state_name
             FROM pincodes p
             LEFT JOIN cities c ON c.city_ref = p.city_ref
             LEFT JOIN districts d ON d.district_ref = p.district_ref
             LEFT JOIN states s ON s.state_ref = p.state_ref
             WHERE p.pincode = ? LIMIT 1",
            [$pin]
        );

        if (!$row) {
            throw new NotFoundException('PINCODE_NOT_FOUND', "Pincode {$pin} not found.");
        }

        return Response::json(200, $row);
    }

    /** GEO-021: Unified geo search — reverse-resolve pincode to full hierarchy. */
    public function search(Request $r): Response
    {
        $q = trim($r->query('q', ''));
        if ($q === '') {
            return Response::json(200, []);
        }
        $like = "%{$q}%";

        $rows = $this->db->fetchAll(
            "SELECT p.pincode, c.city_ref, c.city_name, d.district_ref, d.district_name, s.state_ref, s.state_code, s.state_name
             FROM pincodes p
             LEFT JOIN cities c ON c.city_ref = p.city_ref
             LEFT JOIN districts d ON d.district_ref = p.district_ref
             LEFT JOIN states s ON s.state_ref = p.state_ref
             WHERE p.pincode LIKE ? OR c.city_name LIKE ? OR d.district_name LIKE ? OR s.state_name LIKE ?
             ORDER BY p.pincode ASC LIMIT 50",
            [$like, $like, $like, $like]
        );

        return Response::json(200, $rows);
    }

    // ── Admin CRUD: States ────────────────────────────────────────

    /** GEO-002: Create state */
    public function createState(Request $r): Response
    {
        $this->requireGeoPermission();
        $clean = Validation::validate($r->all(), [
            'state_code' => 'required|string',
            'state_name' => 'required|string',
        ]);
        $ref = RefGenerator::generate('STT');
        $this->db->execute(
            "INSERT INTO states (state_ref, state_code, state_name) VALUES (?, ?, ?)",
            [$ref, strtoupper($clean['state_code']), $clean['state_name']]
        );
        return Response::json(201, ['state_ref' => $ref, 'state_code' => $clean['state_code'], 'state_name' => $clean['state_name']]);
    }

    /** GEO-003: Update state */
    public function updateState(Request $r): Response
    {
        $this->requireGeoPermission();
        $ref = $r->param('ref');
        $data = $r->all();
        $sets = [];
        $params = [];
        if (isset($data['state_name'])) { $sets[] = 'state_name = ?'; $params[] = $data['state_name']; }
        if (isset($data['state_code'])) { $sets[] = 'state_code = ?'; $params[] = strtoupper($data['state_code']); }
        if (!$sets) { return Response::json(200, ['message' => 'Nothing to update']); }
        $params[] = $ref;
        $this->db->execute("UPDATE states SET " . implode(', ', $sets) . " WHERE state_ref = ?", $params);
        $row = $this->db->fetchOne("SELECT state_ref, state_code, state_name FROM states WHERE state_ref = ?", [$ref]);
        if (!$row) throw new NotFoundException('STATE_NOT_FOUND', 'State not found.');
        return Response::json(200, $row);
    }

    // ── Admin CRUD: Districts ─────────────────────────────────────

    /** GEO-007: Create district (linked to state) */
    public function createDistrict(Request $r): Response
    {
        $this->requireGeoPermission();
        $clean = Validation::validate($r->all(), [
            'state_ref'     => 'required|string',
            'district_name' => 'required|string',
        ]);
        $ref = RefGenerator::generate('DST');
        $this->db->execute(
            "INSERT INTO districts (district_ref, state_ref, district_name) VALUES (?, ?, ?)",
            [$ref, $clean['state_ref'], $clean['district_name']]
        );
        return Response::json(201, ['district_ref' => $ref, 'state_ref' => $clean['state_ref'], 'district_name' => $clean['district_name']]);
    }

    /** GEO-008: Update district */
    public function updateDistrict(Request $r): Response
    {
        $this->requireGeoPermission();
        $ref = $r->param('ref');
        $data = $r->all();
        $sets = [];
        $params = [];
        if (isset($data['district_name'])) { $sets[] = 'district_name = ?'; $params[] = $data['district_name']; }
        if (isset($data['state_ref']))      { $sets[] = 'state_ref = ?';     $params[] = $data['state_ref']; }
        if (!$sets) { return Response::json(200, ['message' => 'Nothing to update']); }
        $params[] = $ref;
        $this->db->execute("UPDATE districts SET " . implode(', ', $sets) . " WHERE district_ref = ?", $params);
        $row = $this->db->fetchOne("SELECT district_ref, state_ref, district_name FROM districts WHERE district_ref = ?", [$ref]);
        if (!$row) throw new NotFoundException('DISTRICT_NOT_FOUND', 'District not found.');
        return Response::json(200, $row);
    }

    // ── Admin CRUD: Cities ────────────────────────────────────────

    /** GEO-012: Create city (linked to district) */
    public function createCity(Request $r): Response
    {
        $this->requireGeoPermission();
        $clean = Validation::validate($r->all(), [
            'district_ref' => 'required|string',
            'city_name'    => 'required|string',
        ]);
        $ref = RefGenerator::generate('CTY');
        $this->db->execute(
            "INSERT INTO cities (city_ref, district_ref, city_name) VALUES (?, ?, ?)",
            [$ref, $clean['district_ref'], $clean['city_name']]
        );
        return Response::json(201, ['city_ref' => $ref, 'district_ref' => $clean['district_ref'], 'city_name' => $clean['city_name']]);
    }

    /** GEO-013: Show city detail */
    public function showCity(Request $r): Response
    {
        $ref = $r->param('ref');
        $row = $this->db->fetchOne(
            "SELECT c.city_ref, c.district_ref, c.city_name, d.district_name, d.state_ref, s.state_name
             FROM cities c
             JOIN districts d ON d.district_ref = c.district_ref
             JOIN states s ON s.state_ref = d.state_ref
             WHERE c.city_ref = ?",
            [$ref]
        );
        if (!$row) throw new NotFoundException('CITY_NOT_FOUND', 'City not found.');
        return Response::json(200, $row);
    }

    /** GEO-014: Update city */
    public function updateCity(Request $r): Response
    {
        $this->requireGeoPermission();
        $ref = $r->param('ref');
        $data = $r->all();
        $sets = [];
        $params = [];
        if (isset($data['city_name']))    { $sets[] = 'city_name = ?';    $params[] = $data['city_name']; }
        if (isset($data['district_ref'])) { $sets[] = 'district_ref = ?'; $params[] = $data['district_ref']; }
        if (!$sets) { return Response::json(200, ['message' => 'Nothing to update']); }
        $params[] = $ref;
        $this->db->execute("UPDATE cities SET " . implode(', ', $sets) . " WHERE city_ref = ?", $params);
        $row = $this->db->fetchOne("SELECT city_ref, district_ref, city_name FROM cities WHERE city_ref = ?", [$ref]);
        if (!$row) throw new NotFoundException('CITY_NOT_FOUND', 'City not found.');
        return Response::json(200, $row);
    }

    // ── Admin CRUD: Pincodes ──────────────────────────────────────

    /** GEO-018: Create pincode (linked to city + district + state) */
    public function createPincode(Request $r): Response
    {
        $this->requireGeoPermission();
        $clean = Validation::validate($r->all(), [
            'pincode'      => 'required|string',
            'district_ref' => 'required|string',
            'state_ref'    => 'required|string',
        ]);
        $cityRef = $r->input('city_ref');
        $this->db->execute(
            "INSERT INTO pincodes (pincode, city_ref, district_ref, state_ref) VALUES (?, ?, ?, ?)",
            [$clean['pincode'], $cityRef, $clean['district_ref'], $clean['state_ref']]
        );
        return Response::json(201, array_merge($clean, ['city_ref' => $cityRef]));
    }

    /** GEO-019: Update pincode */
    public function updatePincode(Request $r): Response
    {
        $this->requireGeoPermission();
        $pin = $r->param('ref'); // pincode value as ref
        $data = $r->all();
        $sets = [];
        $params = [];
        if (isset($data['city_ref']))     { $sets[] = 'city_ref = ?';     $params[] = $data['city_ref']; }
        if (isset($data['district_ref'])) { $sets[] = 'district_ref = ?'; $params[] = $data['district_ref']; }
        if (isset($data['state_ref']))    { $sets[] = 'state_ref = ?';    $params[] = $data['state_ref']; }
        if (!$sets) { return Response::json(200, ['message' => 'Nothing to update']); }
        $params[] = $pin;
        $this->db->execute("UPDATE pincodes SET " . implode(', ', $sets) . " WHERE pincode = ?", $params);
        $row = $this->db->fetchOne("SELECT pincode, city_ref, district_ref, state_ref FROM pincodes WHERE pincode = ?", [$pin]);
        if (!$row) throw new NotFoundException('PINCODE_NOT_FOUND', 'Pincode not found.');
        return Response::json(200, $row);
    }

    /** GEO-020: Delete pincode */
    public function deletePincode(Request $r): Response
    {
        $this->requireGeoPermission();
        $pin = $r->param('ref');
        $this->db->execute("DELETE FROM pincodes WHERE pincode = ?", [$pin]);
        return Response::json(200, ['pincode' => $pin, 'deleted' => true]);
    }

    /** Show single state */
    public function showState(Request $r): Response
    {
        $ref = $r->param('ref');
        $row = $this->db->fetchOne("SELECT state_ref, state_code, state_name FROM states WHERE state_ref = ?", [$ref]);
        if (!$row) throw new NotFoundException('STATE_NOT_FOUND', 'State not found.');
        return Response::json(200, $row);
    }

    /** Delete state */
    public function deleteState(Request $r): Response
    {
        $this->requireGeoPermission();
        $ref = $r->param('ref');
        $this->db->execute("DELETE FROM states WHERE state_ref = ?", [$ref]);
        return Response::json(200, ['state_ref' => $ref, 'deleted' => true]);
    }

    /** Show single district */
    public function showDistrict(Request $r): Response
    {
        $ref = $r->param('ref');
        $row = $this->db->fetchOne(
            "SELECT d.district_ref, d.state_ref, d.district_name, s.state_name, s.state_code
             FROM districts d
             JOIN states s ON s.state_ref = d.state_ref
             WHERE d.district_ref = ?",
            [$ref]
        );
        if (!$row) throw new NotFoundException('DISTRICT_NOT_FOUND', 'District not found.');
        return Response::json(200, $row);
    }

    /** Delete district */
    public function deleteDistrict(Request $r): Response
    {
        $this->requireGeoPermission();
        $ref = $r->param('ref');
        $this->db->execute("DELETE FROM districts WHERE district_ref = ?", [$ref]);
        return Response::json(200, ['district_ref' => $ref, 'deleted' => true]);
    }

    /** Delete city */
    public function deleteCity(Request $r): Response
    {
        $this->requireGeoPermission();
        $ref = $r->param('ref');
        $this->db->execute("DELETE FROM cities WHERE city_ref = ?", [$ref]);
        return Response::json(200, ['city_ref' => $ref, 'deleted' => true]);
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function requireGeoPermission(): void
    {
        if ($this->authorization) {
            $ctx = TenantContext::get();
            $this->authorization->requirePermission($ctx, 'settings', 'edit');
        }
    }
}
