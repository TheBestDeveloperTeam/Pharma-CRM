<?php

declare(strict_types=1);

namespace App\Domain\Users;

use App\Core\Database;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ConflictException;
use App\Core\Exceptions\ValidationException;
use App\Core\Validation;
use App\Domain\Audit\AuditService;
use App\Repositories\Contracts\UserRepositoryInterface;

/**
 * UserService — User CRUD, activation, role assignment.
 */
class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly AuditService           $audit,
    ) {}

    // ── List ──────────────────────────────────────────────────────────────────

    public function list(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        return $this->users->paginate($filters, $page, $perPage);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create(array $data, int $actorId): array
    {
        (new Validation($data, [
            'name'     => 'required|string|maxLength:255',
            'email'    => 'required|email|maxLength:255',
            'password' => 'required|minLength:8',
            'role'     => 'required|string',
        ]))->validate();

        $this->validatePasswordPolicy($data['password']);

        $email = strtolower(trim($data['email']));

        if ($this->users->findByEmail($email)) {
            throw new ConflictException("A user with email '$email' already exists.", 'USER_EMAIL_CONFLICT');
        }

        $userId = $this->users->create([
            'name'                => trim($data['name']),
            'email'               => $email,
            'password_hash'       => password_hash($data['password'], PASSWORD_ARGON2ID),
            'status'              => 'active',
            'password_changed_at' => date('Y-m-d H:i:s'),
            'created_by'          => $actorId,
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        // Assign initial role
        if (!empty($data['role'])) {
            $this->users->assignRoleBySlug((int) $userId, $data['role']);
        }

        $this->audit->log('user.created', 'user', (int) $userId, null, $data, ['actor' => $actorId]);

        return $this->get((int) $userId);
    }

    // ── Get ───────────────────────────────────────────────────────────────────

    public function get(int $id): array
    {
        $user = $this->users->findById($id);
        if (!$user) {
            throw new NotFoundException("User #$id not found.");
        }
        $user['roles']       = $this->users->getRoles($id);
        $user['permissions'] = $this->users->getPermissions($id);
        unset($user['password_hash']);
        return $user;
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(int $id, array $data, int $actorId): array
    {
        $user = $this->users->findById($id);
        if (!$user) {
            throw new NotFoundException("User #$id not found.");
        }

        $allowed = ['name', 'email', 'phone', 'status'];
        $updates = array_intersect_key($data, array_flip($allowed));

        if (isset($updates['email'])) {
            $updates['email'] = strtolower(trim($updates['email']));
            $existing = $this->users->findByEmail($updates['email']);
            if ($existing && (int)$existing['id'] !== $id) {
                throw new ConflictException('Email already in use.', 'USER_EMAIL_CONFLICT');
            }
        }

        if (!empty($data['password'])) {
            $this->validatePasswordPolicy($data['password']);
            $updates['password_hash']       = password_hash($data['password'], PASSWORD_ARGON2ID);
            $updates['password_changed_at'] = date('Y-m-d H:i:s');
        }

        $updates['updated_at'] = date('Y-m-d H:i:s');
        $updates['updated_by'] = $actorId;

        $this->users->update($id, $updates);
        $this->audit->log('user.updated', 'user', $id, $user, $updates, ['actor' => $actorId]);

        return $this->get($id);
    }

    // ── Activate / Deactivate ─────────────────────────────────────────────────

    public function activate(int $id, int $actorId): void
    {
        $this->setStatus($id, 'active', $actorId, 'user.activated');
    }

    public function deactivate(int $id, int $actorId): void
    {
        if ($id === $actorId) {
            throw new \App\Core\Exceptions\BusinessRuleException('Cannot deactivate your own account.', 'SELF_DEACTIVATION');
        }
        $this->setStatus($id, 'inactive', $actorId, 'user.deactivated');
    }

    private function setStatus(int $id, string $status, int $actorId, string $event): void
    {
        $user = $this->users->findById($id);
        if (!$user) throw new NotFoundException("User #$id not found.");

        $this->users->update($id, [
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $actorId,
        ]);
        $this->audit->log($event, 'user', $id, $user, ['status' => $status], ['actor' => $actorId]);
    }

    // ── Password Policy ───────────────────────────────────────────────────────

    private function validatePasswordPolicy(string $password): void
    {
        $errors = [];
        $min    = (int) env('PASSWORD_MIN_LENGTH', 8);

        if (mb_strlen($password) < $min) {
            $errors[] = "Password must be at least $min characters.";
        }
        if (env('PASSWORD_REQUIRE_UPPERCASE', 'true') === 'true' && !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }
        if (env('PASSWORD_REQUIRE_LOWERCASE', 'true') === 'true' && !preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }
        if (env('PASSWORD_REQUIRE_DIGIT', 'true') === 'true' && !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one digit.';
        }
        if (!empty($errors)) {
            throw new ValidationException(['password' => $errors]);
        }
    }
}
