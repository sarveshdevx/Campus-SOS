<?php
declare(strict_types=1);

namespace CampusSOS\Controllers;

use CampusSOS\Models\DepartmentContact;
use CampusSOS\Models\AuditLog;
use CampusSOS\Services\ResponseService;
use CampusSOS\Middleware\RoleMiddleware;

class ContactController
{
    /**
     * GET /api/contacts
     * Public endpoint: retrieve all 6 safety department contacts.
     */
    public function index(): void
    {
        $contacts = DepartmentContact::findAll();

        $formatted = array_map([$this, 'formatContact'], $contacts);

        ResponseService::json($formatted, 200, 'Department contacts retrieved successfully.');
    }

    /**
     * GET /api/contacts/{id}
     * Public endpoint: retrieve a single department contact by ID.
     */
    public function show(int $id): void
    {
        $contact = DepartmentContact::findById($id);
        if (!$contact) {
            ResponseService::error("Department contact #{$id} not found.", 404);
        }

        ResponseService::json($this->formatContact($contact), 200);
    }

    /**
     * PUT /api/contacts/{id}
     * Restricted endpoint: Only administrators may update department contact information.
     */
    public function update(int $id): void
    {
        $user = RoleMiddleware::requireAdmin();

        $contact = DepartmentContact::findById($id);
        if (!$contact) {
            ResponseService::error("Department contact #{$id} not found.", 404);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        // Normalize input keys (support camelCase or snake_case)
        $data = [
            'department_name' => $input['departmentName'] ?? ($input['department_name'] ?? $contact['department_name']),
            'representative_name' => $input['representativeName'] ?? ($input['representative_name'] ?? $contact['representative_name']),
            'designation' => $input['designation'] ?? $contact['designation'],
            'email' => array_key_exists('email', $input) ? $input['email'] : $contact['email'],
            'phone_number' => $input['phoneNumber'] ?? ($input['phone_number'] ?? $contact['phone_number']),
            'office_location' => $input['officeLocation'] ?? ($input['office_location'] ?? $contact['office_location']),
            'description' => $input['description'] ?? $contact['description'],
            'is_configured' => array_key_exists('isConfigured', $input) ? (bool)$input['isConfigured'] : (array_key_exists('is_configured', $input) ? (bool)$input['is_configured'] : (bool)$contact['is_configured']),
            'is_official_verified' => array_key_exists('isOfficialVerified', $input) ? (bool)$input['isOfficialVerified'] : (array_key_exists('is_official_verified', $input) ? (bool)$input['is_official_verified'] : (bool)$contact['is_official_verified']),
        ];

        // Format validation
        $errors = DepartmentContact::validate($data);
        if (!empty($errors)) {
            ResponseService::error(implode(' ', $errors), 422, ['validationErrors' => $errors]);
        }

        $success = DepartmentContact::update($id, $data, (int) $user['id']);
        if (!$success) {
            ResponseService::error('Failed to update department contact record.', 500);
        }

        AuditLog::log(
            (int) $user['id'],
            'DEPARTMENT_CONTACT_UPDATED',
            'department_contacts',
            $id,
            [
                'category' => $contact['category'],
                'department_name' => $data['department_name'],
                'email' => $data['email'],
                'phone' => $data['phone_number'],
            ]
        );

        $updated = DepartmentContact::findById($id);

        ResponseService::json($this->formatContact($updated ?: []), 200, 'Department contact updated successfully.');
    }

    /**
     * Format database row into standard API payload.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatContact(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'category' => (string) ($row['category'] ?? ''),
            'departmentName' => (string) ($row['department_name'] ?? ''),
            'representativeName' => $row['representative_name'] ?? null,
            'designation' => $row['designation'] ?? null,
            'email' => $row['email'] ?? null,
            'phoneNumber' => $row['phone_number'] ?? null,
            'officeLocation' => $row['office_location'] ?? null,
            'description' => $row['description'] ?? null,
            'isConfigured' => (bool) ($row['is_configured'] ?? false),
            'isOfficialVerified' => (bool) ($row['is_official_verified'] ?? false),
            'updatedByName' => $row['updated_by_name'] ?? null,
            'updatedAt' => (string) ($row['updated_at'] ?? ''),
        ];
    }
}

