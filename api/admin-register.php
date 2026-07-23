<?php
/**
 * DEPRECATED: Admin Account Creation Endpoint
 * 
 * This endpoint has been DISABLED for security reasons.
 * 
 * SECURITY: Admin accounts are now created ONLY by the Super Admin from
 * the secure admin dashboard (pages/admin_dashboard.php), never through public forms.
 *
 * Public admin registration via secret codes is a security vulnerability
 * and has been completely removed from the system.
 *
 * To create an admin account (HR coordinator or department supervisor):
 * 1. Login as Super Admin (PF: SUPER/ADMIN/001)
 * 2. Open the Super Admin Dashboard
 * 3. Use the secure "Create Admin" form (POST /api/create-admin-account.php)
 *
 * This is the ONLY secure method for admin account creation.
 */

require_once __DIR__ . '/../config/config.php';

// POST requests to this endpoint are rejected
http_response_code(403);
header('Content-Type: application/json');

echo json_encode([
    'success' => false,
    'message' => 'Admin account creation through this endpoint is no longer allowed for security reasons.',
    'details' => 'Admin accounts must be created by the Super Admin from the secure admin dashboard.',
    'instructions' => [
        '1. Login to the Vuka portal as the Super Admin',
        '2. Open the Super Admin Dashboard (pages/admin_dashboard.php)',
        '3. Navigate to the "Create Admin" section',
        '4. Fill in the admin account details',
        '5. The account is created active and ready to log in'
    ],
    'contact' => 'Contact your Super Admin for admin account creation requests.'
]);

exit;
