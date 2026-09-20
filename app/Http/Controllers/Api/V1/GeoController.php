<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1;

use App\Core\{Request, Response, Database};
use App\Core\Exceptions\NotFoundException;

final class GeoController
{
    public function __construct(private Database $db) {}

    public function states(Request $r): Response
    {
        $rows = $this->db->fetchAll("SELECT state_ref, state_code, state_name FROM states ORDER BY state_name ASC");
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
            $rows = $this->db->fetchAll("SELECT district_ref, state_ref, district_name FROM districts ORDER BY district_name ASC");
        }
        return Response::json(200, $rows);
    }

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
}
