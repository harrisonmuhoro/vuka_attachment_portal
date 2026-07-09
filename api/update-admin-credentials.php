<?php
/**
 * Update Super Admin Credentials — Vuka Attachment Portal
 * Usage: Open this file in browser to update the account.
 */
require_once __DIR__ . '/../config.php';

$oldPf = 'SUPER/ADMIN/001';
$newPf = 'TEST/TEST/001';
$newPass = 'admin123';
$newHash = password_hash($newPass, PASSWORD_BCRYPT);

try {
    // Check if the old user exists
    $check = $pdo->prepare("SELECT id FROM admin_users WHERE pf_number = ?");
    $check->execute([$oldPf]);
    $user = $check->fetch();

    if ($user) {
        $stmt = $pdo->prepare("
            UPDATE admin_users 
            SET pf_number = ?, password_hash = ?
            WHERE pf_number = ?
        ");
        $stmt->execute([$newPf, $newHash, $oldPf]);
        echo "<h1>Success</h1><p>Updated <strong>$oldPf</strong> to <strong>$newPf</strong> with password <strong>$newPass</strong>.</p>";
    } else {
        // Check if already updated or if we need to insert fresh
        $checkNew = $pdo->prepare("SELECT id FROM admin_users WHERE pf_number = ?");
        $checkNew->execute([$newPf]);
        if ($checkNew->fetch()) {
            echo "<h1>Already Updated</h1><p>User <strong>$newPf</strong> already exists. Resetting password...</p>";
            $update = $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE pf_number = ?");
            $update->execute([$newHash, $newPf]);
            echo "<p>Password reset to <strong>$newPass</strong>.</p>";
        } else {
            // Logic to insert if neither exists (fallback)
             $stmt = $pdo->prepare("
                INSERT INTO admin_users (pf_number, full_name, email, password_hash, role_id, department, status) 
                VALUES (?, 'System Administrator', 'admin@vuka.go.ke', ?, 1, 'ALL', 'active')
            ");
            $stmt->execute([$newPf, $newHash]);
            echo "<h1>Created</h1><p>User <strong>$newPf</strong> created with password <strong>$newPass</strong>.</p>";
        }
    }
    
    echo "<p><a href='/index.php'>Go to Vuka Portal</a></p>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
