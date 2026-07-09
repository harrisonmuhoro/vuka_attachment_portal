<?php
/**
 * System Verification Script
 * Run this to verify all backend components are properly installed
 */

echo "<!DOCTYPE html>
<html>
<head>
    <title>Vuka Portal - System Verification</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #006400; }
        .check { margin: 15px 0; padding: 10px; border-radius: 4px; }
        .success { background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .warning { background-color: #fff3cd; border: 1px solid #ffeeba; color: #856404; }
        code { background: #f4f4f4; padding: 2px 4px; border-radius: 3px; }
        .status { float: right; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #006400; color: white; }
        tr:hover { background: #f5f5f5; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 System Verification Report</h1>
        <p>Checking Vuka Portal Backend Installation</p>
        <hr>";

// PHP Version
echo "<div class='check success'><strong>PHP Version</strong> <span class='status'>" . PHP_VERSION . "</span></div>";

// File Permissions
echo "<h3>📁 File Permissions</h3>";

$folders = [
    'uploads' => 'Upload directory',
    'api' => 'API endpoints',
];

foreach ($folders as $folder => $desc) {
    if (is_dir($folder)) {
        echo "<div class='check success'><strong>$folder/</strong> - $desc <span class='status'>✓ EXISTS</span></div>";
    } else {
        echo "<div class='check error'><strong>$folder/</strong> - $desc <span class='status'>✗ MISSING</span></div>";
    }
}

// API Files
echo "<h3>📄 API Endpoints</h3>";

$apis = [
    'api/register.php' => 'User Registration',
    'api/verify-email.php' => 'Email Verification',
    'api/login.php' => 'User Login',
    'api/submit-application.php' => 'Application Submission',
    'api/admin-login.php' => 'Admin Login',
    'api/create-admin-account.php' => 'Create Admin (Super Admin Only)',
    'api/activate-admin-account.php' => 'Activate Admin Account',
    'api/deactivate-admin-account.php' => 'Deactivate Admin Account',
    'api/get-admin-accounts.php' => 'Get Admin Accounts (Role-filtered)',
    'api/verify-session.php' => 'Verify Session',
    'api/logout.php' => 'User Logout',
    'api/get-submissions.php' => 'Get Submissions',
    'api/update-submission.php' => 'Update Submission',
    'api/delete-submission.php' => 'Delete Submission',
    'api/download-document.php' => 'Download Document'
];

foreach ($apis as $file => $desc) {
    if (file_exists($file)) {
        $size = filesize($file);
        echo "<div class='check success'><strong>$file</strong> - $desc <span class='status'>✓ ($size bytes)</span></div>";
    } else {
        echo "<div class='check error'><strong>$file</strong> - $desc <span class='status'>✗ MISSING</span></div>";
    }
}

// Configuration Files
echo "<h3>⚙️ Configuration Files</h3>";

$configs = [
    'config.php' => 'Database Configuration',
    'database.sql' => 'Database Schema',
    'index.html' => 'Main Application',
];

foreach ($configs as $file => $desc) {
    if (file_exists($file)) {
        echo "<div class='check success'><strong>$file</strong> - $desc <span class='status'>✓</span></div>";
    } else {
        echo "<div class='check error'><strong>$file</strong> - $desc <span class='status'>✗</span></div>";
    }
}

// Database Connection Test
echo "<h3>🗄️ Database Connection</h3>";

require_once 'config.php';

if (DB_CONNECTED) {
    echo "<div class='check success'><strong>MySQL Connection</strong> <span class='status'>✓ CONNECTED</span></div>";
    
    // Check tables
    echo "<h4>Database Tables</h4>";
    $tables = ['users', 'admin_users', 'submissions', 'documents', 'review_history'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM $table");
        try {
            $stmt->execute();
            $result = $stmt->fetch();
            $count = $result['count'];
            echo "<div class='check success'><strong>$table</strong> <span class='status'>✓ ({$count} records)</span></div>";
        } catch (Exception $e) {
            echo "<div class='check error'><strong>$table</strong> <span class='status'>✗ ERROR</span></div>";
        }
    }
    
} else {
    echo "<div class='check error'><strong>MySQL Connection</strong> <span class='status'>✗ FAILED</span></div>";
    echo "<p>Please check:</p>";
    echo "<ul>";
    echo "<li>MySQL service is running</li>";
    echo "<li>Database 'vuka_attachment_portal' exists</li>";
    echo "<li>Database credentials in config.php are correct</li>";
    echo "</ul>";
}

// System Requirements
echo "<h3>✅ System Requirements</h3>";

$requirements = [
    'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4', '>='),
    'PDO Extension' => extension_loaded('pdo'),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'JSON Extension' => extension_loaded('json'),
];

foreach ($requirements as $req => $status) {
    $class = $status ? 'success' : 'error';
    $icon = $status ? '✓' : '✗';
    echo "<div class='check $class'><strong>$req</strong> <span class='status'>$icon</span></div>";
}

// Configuration Summary
echo "<h3>📋 Configuration Summary</h3>";

echo "<table>";
echo "<tr><th>Setting</th><th>Value</th></tr>";
echo "<tr><td>Database Host</td><td>" . DB_HOST . "</td></tr>";
echo "<tr><td>Database Name</td><td>" . DB_NAME . "</td></tr>";
echo "<tr><td>Upload Directory</td><td>" . UPLOAD_DIR . "</td></tr>";
echo "<tr><td>Max File Size</td><td>" . (MAX_FILE_SIZE / 1024 / 1024) . " MB</td></tr>";
echo "<tr><td>Allowed MIME Types</td><td>" . implode(', ', ALLOWED_MIME_TYPES) . "</td></tr>";
echo "</table>";

// Quick Test
echo "<h3>🚀 Quick Test</h3>";

if (DB_CONNECTED) {
    // Test admin user
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE pf_number = ?");
    $stmt->execute(['PF/ADMIN/001']);
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "<div class='check success'><strong>Default Admin Account</strong> <span class='status'>✓ EXISTS</span></div>";
        echo "<p>You can login with:</p>";
        echo "<ul>";
        echo "<li><strong>PF Number:</strong> PF/ADMIN/001</li>";
        echo "<li><strong>Password:</strong> admin123</li>";
        echo "</ul>";
    } else {
        echo "<div class='check warning'><strong>Default Admin Account</strong> <span class='status'>⚠ NOT FOUND</span></div>";
        echo "<p>Run setup.bat to create default admin</p>";
    }
}

// Final Status
echo "<h3>📊 Overall Status</h3>";

$errors = 0;
$warnings = 0;

if (!file_exists('config.php')) $errors++;
if (!file_exists('index.html')) $errors++;
if (!file_exists('database.sql')) $errors++;
if (!is_dir('uploads')) $errors++;
if (!DB_CONNECTED) $errors++;

if ($errors == 0) {
    echo "<div class='check success'><h2 style='margin:0; color:#155724;'>✓ SYSTEM READY</h2><p>All components installed and configured correctly. You can now use the portal.</p></div>";
} else if ($errors < 3) {
    echo "<div class='check warning'><h2 style='margin:0; color:#856404;'>⚠ PARTIAL SETUP</h2><p>Some components are missing. Please review the errors above and run setup.bat</p></div>";
} else {
    echo "<div class='check error'><h2 style='margin:0; color:#721c24;'>✗ SETUP INCOMPLETE</h2><p>Multiple components are missing. Please complete the setup by running setup.bat</p></div>";
}

echo "</div>";
echo "</body></html>";
?>
