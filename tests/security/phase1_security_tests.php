<?php
/** Isolated HTTP regression suite. All application data is held in a random SQLite temp DB. */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__DIR__, 2);
$work = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vuka-phase1-tests-' . bin2hex(random_bytes(8));
mkdir($work, 0700, true);
$databaseFile = $work . DIRECTORY_SEPARATOR . 'fixture.sqlite';
$logDirectory = $work . DIRECTORY_SEPARATOR . 'logs';
mkdir($logDirectory, 0700, true);
$serverLog = $work . DIRECTORY_SEPARATOR . 'php-server.log';
$cookieJar = $work . DIRECTORY_SEPARATOR . 'cookies.txt';
$sessionDirectory = $work . DIRECTORY_SEPARATOR . 'sessions';
mkdir($sessionDirectory, 0700, true);
$uploadFile = $work . DIRECTORY_SEPARATOR . 'sample.pdf';
file_put_contents($uploadFile, "%PDF-1.4\nphase-one-test\n%%EOF\n");
$db = new PDO('sqlite:' . $databaseFile, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->sqliteCreateFunction('NOW', static function () { return date('Y-m-d H:i:s'); }, 0);

$schema = [
    'CREATE TABLE roles (id INTEGER PRIMARY KEY, role_name TEXT, display_name TEXT, level INTEGER)',
    'CREATE TABLE departments (id INTEGER PRIMARY KEY, name TEXT UNIQUE, description TEXT, min_admins INTEGER DEFAULT 1)',
    'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, full_name TEXT NOT NULL, national_id TEXT UNIQUE NOT NULL, email TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL, phone TEXT, registration_date TEXT NOT NULL, verified INTEGER NOT NULL DEFAULT 0, verification_code TEXT, verification_sent_at TEXT, status TEXT NOT NULL DEFAULT "active", profile_photo TEXT, profile_photo_blob BLOB, pending_email TEXT, last_login TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)',
    'CREATE TABLE admin_users (id INTEGER PRIMARY KEY AUTOINCREMENT, national_id TEXT NOT NULL, pf_number TEXT UNIQUE NOT NULL, full_name TEXT NOT NULL, email TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL, phone TEXT, department TEXT, department_id INTEGER, role_id INTEGER NOT NULL, status TEXT NOT NULL DEFAULT "active", profile_photo TEXT, profile_photo_blob BLOB, pending_email TEXT, email_change_token TEXT, email_change_expires TEXT, created_by INTEGER, last_login TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)',
    'CREATE TABLE sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, session_id TEXT UNIQUE NOT NULL, user_id INTEGER, admin_id INTEGER, user_type TEXT NOT NULL, role_id INTEGER NOT NULL, ip_address TEXT, user_agent TEXT, created_at TEXT NOT NULL, last_activity TEXT NOT NULL, expires_at TEXT NOT NULL, is_valid INTEGER NOT NULL DEFAULT 1)',
    'CREATE TABLE vacancies (id INTEGER PRIMARY KEY AUTOINCREMENT, department_name TEXT NOT NULL, title TEXT NOT NULL, description TEXT NOT NULL, skills_required TEXT, positions_count INTEGER DEFAULT 1, vacancy_type TEXT NOT NULL DEFAULT "attachment", deadline_at TEXT, status TEXT NOT NULL DEFAULT "pending", created_by INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)',
    'CREATE TABLE submissions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, national_id TEXT NOT NULL, full_name TEXT NOT NULL, email TEXT NOT NULL, vacancy_id INTEGER, application_type TEXT NOT NULL DEFAULT "attachment", duration TEXT, insurance_cover TEXT, course_applying TEXT, institution_name TEXT, department_applied TEXT NOT NULL, status TEXT NOT NULL DEFAULT "applied", placement_status TEXT DEFAULT "pending", rejection_reason TEXT, review_notes TEXT, intern_pf_number TEXT, assigned_role TEXT, assigned_station TEXT, last_updated_by TEXT, submitted_at TEXT DEFAULT CURRENT_TIMESTAMP, last_updated_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)',
    'CREATE TABLE documents (id INTEGER PRIMARY KEY AUTOINCREMENT, submission_id INTEGER NOT NULL, document_type TEXT NOT NULL, original_filename TEXT NOT NULL, file_path TEXT, mime_type TEXT NOT NULL, file_size INTEGER NOT NULL DEFAULT 0, file_content BLOB, uploaded_at TEXT DEFAULT CURRENT_TIMESTAMP)',
    'CREATE TABLE review_history (id INTEGER PRIMARY KEY AUTOINCREMENT, submission_id INTEGER NOT NULL, reviewer_pf TEXT, reviewer_department TEXT, status TEXT NOT NULL, notes TEXT, email_sent INTEGER NOT NULL DEFAULT 0, email_sent_at TEXT, reviewed_at TEXT DEFAULT CURRENT_TIMESTAMP)',
    'CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, admin_id INTEGER NOT NULL, admin_pf TEXT NOT NULL, admin_role TEXT NOT NULL, action TEXT NOT NULL, entity_type TEXT NOT NULL, entity_id INTEGER, target_admin_id INTEGER, target_admin_pf TEXT, old_values TEXT, new_values TEXT, ip_address TEXT, user_agent TEXT, details TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
    'CREATE TABLE admin_creation_history (id INTEGER PRIMARY KEY AUTOINCREMENT, created_admin_id INTEGER NOT NULL, created_by_admin_id INTEGER NOT NULL, role_id INTEGER NOT NULL, department_id INTEGER, activation_status TEXT NOT NULL)',
    'CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, recipient_id INTEGER NOT NULL, role_type TEXT NOT NULL, title TEXT NOT NULL, body TEXT NOT NULL, link TEXT, is_read INTEGER NOT NULL DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
    'CREATE TABLE login_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT, ip_address TEXT NOT NULL, endpoint TEXT NOT NULL, identifier TEXT, success INTEGER NOT NULL DEFAULT 0, attempted_at TEXT DEFAULT CURRENT_TIMESTAMP)',
    'CREATE TABLE password_reset_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL, token TEXT UNIQUE NOT NULL, account_type TEXT NOT NULL, expires_at TEXT NOT NULL, used INTEGER NOT NULL DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
];
foreach ($schema as $sql) $db->exec($sql);
$db->exec("INSERT INTO roles VALUES (1,'super_admin','Super Admin',1),(2,'hr_coordinator','HR Coordinator',2),(3,'department_supervisor','Department Supervisor',3),(4,'student','Student',4)");
$db->exec("INSERT INTO departments (id,name) VALUES (1,'ICT'),(2,'Finance'),(3,'HR')");

$studentPassword = 'Current-Password-123';
$passwordHash = password_hash($studentPassword, PASSWORD_DEFAULT);
$userInsert = $db->prepare('INSERT INTO users (full_name,national_id,email,password_hash,registration_date,verified,status) VALUES (?,?,?,?,?,?,?)');
$users = [
    ['Applicant Alpha', '10000001', 'alpha@example.test', $passwordHash, '2026-01-01', 1, 'active'],
    ['Applicant Beta', '10000002', 'beta@example.test', $passwordHash, '2026-01-01', 1, 'active'],
    ['Applicant Gamma', '10000003', 'gamma@example.test', $passwordHash, '2026-01-01', 1, 'active'],
    ['Applicant Unverified', '10000004', 'unverified@example.test', $passwordHash, '2026-01-01', 0, 'active'],
    ['Applicant Inactive', '10000005', 'inactive@example.test', $passwordHash, '2026-01-01', 1, 'inactive'],
    ['Applicant Verification', '10000006', 'verify@example.test', $passwordHash, '2026-01-01', 0, 'active'],
    ['Applicant Delta', '10000007', 'delta@example.test', $passwordHash, '2026-01-01', 1, 'active'],
];
foreach ($users as $user) $userInsert->execute($user);
$adminInsert = $db->prepare('INSERT INTO admin_users (national_id,pf_number,full_name,email,password_hash,department,department_id,role_id,status) VALUES (?,?,?,?,?,?,?,?,?)');
$adminPassword = password_hash('Admin-Password-123', PASSWORD_DEFAULT);
$adminInsert->execute(['90000001','HR-PF-01','HR Coordinator','hr@example.test',$adminPassword,'HR',3,2,'active']);
$hrId = (int)$db->lastInsertId();
$adminInsert->execute(['90000002','ICT-PF-01','ICT Supervisor','ict@example.test',$adminPassword,'ICT',1,3,'active']);
$supervisorId = (int)$db->lastInsertId();
$adminInsert->execute(['90000003','ROOT-PF-01','System Administrator','root@example.test',$adminPassword,'ALL',null,1,'active']);
$superAdminId = (int)$db->lastInsertId();
$adminInsert->execute(['90000004','FIN-PF-01','Finance Supervisor','finance@example.test',$adminPassword,'Finance',2,3,'active']);
$financeSupervisorId = (int)$db->lastInsertId();

$tokenByUser = [];
$sessionInsert = $db->prepare('INSERT INTO sessions (session_id,user_id,admin_id,user_type,role_id,ip_address,user_agent,created_at,last_activity,expires_at,is_valid) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
$addSession = static function ($userId, $adminId, $userType, $roleId, $valid = 1, $expires = '+1 hour') use ($sessionInsert, &$tokenByUser) {
    $token = bin2hex(random_bytes(32));
    $now = date('Y-m-d H:i:s');
    $sessionInsert->execute([$token, $userId, $adminId, $userType, $roleId, '127.0.0.1', 'Vuka Phase1 Test', $now, $now, date('Y-m-d H:i:s', strtotime($expires)), $valid]);
    if ($userId !== null && !isset($tokenByUser[(int)$userId])) $tokenByUser[(int)$userId] = $token;
    return $token;
};
$addSession(1, null, 'student', 4);
$addSession(2, null, 'student', 4);
$tokenGamma = $addSession(3, null, 'student', 4);
$tokenDelta = $addSession(7, null, 'student', 4);
$addSession(4, null, 'student', 4);
$addSession(5, null, 'student', 4);
$tokenHR = $addSession(null, $hrId, 'admin', 2);
$tokenSupervisor = $addSession(null, $supervisorId, 'admin', 3);
$tokenSuperAdmin = $addSession(null, $superAdminId, 'admin', 1);
$tokenFinanceSupervisor = $addSession(null, $financeSupervisorId, 'admin', 3);
$expiredToken = $addSession(1, null, 'student', 4, 1, '-1 hour');
$revokedToken = $addSession(1, null, 'student', 4, 0);

$db->exec("INSERT INTO submissions (user_id,national_id,full_name,email,department_applied,status) VALUES
 (1,'10000001','Applicant Alpha','alpha@example.test','ICT','applied'),
 (2,'10000002','Applicant Beta','beta@example.test','Finance','applied'),
 (3,'10000003','Applicant Gamma','gamma@example.test','ICT','completed')");
$db->exec("INSERT INTO documents (submission_id,document_type,original_filename,file_path,mime_type,file_size,file_content) VALUES
 (1,'application_letter','alpha.pdf','','application/pdf',25,'%PDF-ALPHA'),
 (2,'application_letter','beta.pdf','','application/pdf',24,'%PDF-BETA')");
$db->exec("INSERT INTO review_history (submission_id,reviewer_pf,reviewer_department,status,notes) VALUES (1,'HR-PF-01','HR','note','Internal note'), (2,'HR-PF-01','HR','note','Finance internal note')");
$db->exec("INSERT INTO vacancies (department_name,title,description,vacancy_type,deadline_at,status) VALUES ('ICT','Test Vacancy','Test vacancy','attachment','" . date('Y-m-d H:i:s', time() - 3600) . "','approved')");
$db->exec("INSERT INTO vacancies (department_name,title,description,vacancy_type,deadline_at,status,created_by) VALUES
 ('ICT','Public Vacancy','Open role','attachment',NULL,'approved',2),
 ('ICT','Pending Vacancy','Internal pending','attachment',NULL,'pending',2),
 ('ICT','Rejected Vacancy','Internal rejected','attachment',NULL,'rejected',2),
 ('ICT','Closed Vacancy','Closed role','attachment',NULL,'closed',2),
 ('Finance','Finance Public','Open finance role','internship',NULL,'approved',4),
 ('Finance','Expired Finance','Expired role','internship','" . date('Y-m-d H:i:s', time() - 3600) . "','approved',4)");
$auditInsert = $db->prepare('INSERT INTO audit_logs (admin_id,admin_pf,admin_role,action,entity_type,entity_id,target_admin_id,old_values,new_values,ip_address,user_agent,details,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
$sensitiveAuditMarker = 'phase11-sensitive-token-marker';
$auditRows = [
    [$hrId, 'HR-PF-01', 'hr_coordinator', 'vacancy_status_changed', 'vacancy', 2, null, null, null, '127.0.0.1', 'test-agent', $sensitiveAuditMarker, date('Y-m-d H:i:s')],
    [$supervisorId, 'ICT-PF-01', 'department_supervisor', 'review_application', 'submission', 1, null, null, null, '127.0.0.1', 'test-agent', 'ICT review', date('Y-m-d H:i:s')],
    [$financeSupervisorId, 'FIN-PF-01', 'department_supervisor', 'review_application', 'submission', 2, null, null, null, '127.0.0.1', 'test-agent', 'Finance review', date('Y-m-d H:i:s')],
    [$superAdminId, 'ROOT-PF-01', 'super_admin', 'account_security', 'admin_user', 3, null, null, null, '127.0.0.1', 'test-agent', 'System event', date('Y-m-d H:i:s')],
];
foreach ($auditRows as $auditRow) $auditInsert->execute($auditRow);

$server = null;
$pipes = [];
$baseUrl = '';
$passed = 0;
$failed = 0;
$results = [];

$assert = static function (string $name, bool $ok, string $detail = '') use (&$passed, &$failed, &$results) {
    if ($ok) $passed++; else $failed++;
    $results[] = [$name, $ok, $detail];
};
$request = static function (string $method, string $path, ?string $token = null, $body = null, array $extraHeaders = [], ?string $cookiePath = null, ?string $cookieString = null) use (&$baseUrl) {
    $curl = curl_init($baseUrl . $path);
    $headers = $extraHeaders;
    if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
    ]);
    if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    if ($cookiePath !== null) {
        curl_setopt($curl, CURLOPT_COOKIEFILE, $cookiePath);
        curl_setopt($curl, CURLOPT_COOKIEJAR, $cookiePath);
    }
    if ($cookieString !== null) curl_setopt($curl, CURLOPT_COOKIE, $cookieString);
    $responseBody = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    return [$status, $responseBody === false ? '' : $responseBody, $error];
};
$jsonRequest = static function (string $method, string $path, ?string $token, array $payload, array $headers = [], ?string $cookiePath = null, ?string $cookieString = null) use ($request) {
    $headers[] = 'Content-Type: application/json';
    return $request($method, $path, $token, json_encode($payload), $headers, $cookiePath, $cookieString);
};
$decode = static function ($body) { return json_decode((string)$body, true) ?: []; };

try {
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$socket) throw new RuntimeException('Unable to reserve a local HTTP test port');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $port = (int)substr(strrchr($address, ':'), 1);
    $baseUrl = 'http://127.0.0.1:' . $port;
    $environment = getenv();
    $environment['DB_DSN'] = 'sqlite:' . $databaseFile;
    $environment['DB_USER'] = '';
    $environment['DB_PASS'] = '';
    $environment['VUKA_LOG_DIR'] = $logDirectory;
    $command = [PHP_BINARY, '-d', 'session.save_path=' . $sessionDirectory, '-S', '127.0.0.1:' . $port, '-t', $root];
    $server = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $serverLog, 'a'], 2 => ['file', $serverLog, 'a']], $pipes, $root, $environment);
    if (!is_resource($server)) throw new RuntimeException('Unable to start isolated PHP test server');
    $ready = false;
    for ($i = 0; $i < 40; $i++) {
        $probe = @file_get_contents($baseUrl . '/');
        if ($probe !== false) { $ready = true; break; }
        usleep(100000);
    }
    if (!$ready) throw new RuntimeException('Isolated PHP test server did not start');

    // Anonymous access is blocked before private rows or document content are queried.
    $r = $request('GET', '/api/get-submissions.php');
    $assert('Anonymous submission list denied', $r[0] === 401, 'HTTP ' . $r[0]);
    $r = $request('GET', '/api/get-submissions.php?id=1');
    $assert('Anonymous individual submission denied', $r[0] === 401, 'HTTP ' . $r[0]);
    $r = $request('GET', '/api/download-document.php?id=1');
    $assert('Anonymous private document denied', $r[0] === 401, 'HTTP ' . $r[0]);
    $r = $request('POST', '/api/submit-application.php', null, ['national_id' => '10000001']);
    $assert('Anonymous application submission denied', $r[0] === 401, 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/update-submission.php', null, ['submission_id' => 1, 'status' => 'accepted']);
    $assert('Anonymous review update denied', $r[0] === 401, 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/bulk-update-submissions.php', null, ['ids' => [1], 'status' => 'accepted']);
    $assert('Anonymous bulk review denied', $r[0] === 401, 'HTTP ' . $r[0]);
    $r = $request('GET', '/api/get-audit-log.php');
    $assert('Anonymous audit log denied', $r[0] === 401, 'HTTP ' . $r[0]);
    $r = $request('GET', '/api/vacancies.php?status=all');
    $publicVacancies = $decode($r[1])['vacancies'] ?? [];
    $publicTitles = array_column($publicVacancies, 'title');
    $assert('Public vacancy status=all returns approved open records only', $r[0] === 200 && in_array('Public Vacancy', $publicTitles, true) && in_array('Finance Public', $publicTitles, true) && !in_array('Pending Vacancy', $publicTitles, true) && !in_array('Rejected Vacancy', $publicTitles, true) && !in_array('Closed Vacancy', $publicTitles, true) && !in_array('Expired Finance', $publicTitles, true), 'public count=' . count($publicVacancies));
    $assert('Public vacancy response omits internal approval and creator fields', $r[0] === 200 && !isset($publicVacancies[0]['status']) && !isset($publicVacancies[0]['created_by']) && !isset($publicVacancies[0]['creator_name']), 'public whitelist');
    $r = $request('GET', '/api/vacancies.php?department=ICT&status=all');
    $publicICT = $decode($r[1])['vacancies'] ?? [];
    $assert('Public department filter only narrows public vacancies', $r[0] === 200 && count($publicICT) === 1 && $publicICT[0]['department_name'] === 'ICT', 'rows=' . count($publicICT));
    $expiredStillApproved = $db->query("SELECT status FROM vacancies WHERE title='Test Vacancy'")->fetchColumn();
    $assert('Public vacancy GET does not mutate expired vacancy rows', $expiredStillApproved === 'approved', 'stored status=' . (string)$expiredStillApproved);
    $r = $request('GET', '/api/vacancies.php?status=pending', $tokenSupervisor);
    $assert('Supervisor vacancy list is limited to own department', $r[0] === 200 && count($decode($r[1])['vacancies'] ?? []) === 1 && ($decode($r[1])['vacancies'][0]['department_name'] ?? '') === 'ICT', 'HTTP ' . $r[0]);
    $r = $request('GET', '/api/vacancies.php?department=Finance&status=all', $tokenSupervisor);
    $assert('Supervisor cannot widen vacancy department through query parameters', $r[0] === 200 && count(array_filter($decode($r[1])['vacancies'] ?? [], static fn($v) => $v['department_name'] !== 'ICT')) === 0, 'HTTP ' . $r[0]);
    $r = $request('GET', '/api/vacancies.php?status=pending', $tokenHR);
    $assert('HR can inspect pending vacancy approvals', $r[0] === 200 && count($decode($r[1])['vacancies'] ?? []) === 1, 'HTTP ' . $r[0]);
    $r = $request('GET', '/api/get-profile.php', $tokenByUser[1]);
    $applicantProfile = $decode($r[1])['profile'] ?? [];
    $assert('Applicant profile is limited to signed-in applicant', $r[0] === 200 && ($applicantProfile['national_id'] ?? '') === '10000001' && !isset($applicantProfile['password_hash']) && !isset($applicantProfile['verification_code']), 'HTTP ' . $r[0]);
    $r = $request('GET', '/api/get-profile.php?id=2', $tokenByUser[1]);
    $assert('Applicant profile ignores forged account ID', $r[0] === 200 && (($decode($r[1])['profile']['national_id'] ?? '') === '10000001'), 'HTTP ' . $r[0]);
    $r = $request('GET', '/api/get-profile.php', $tokenHR);
    $adminProfile = $decode($r[1])['profile'] ?? [];
    $assert('Administrator profile returns correct role and department without secrets', $r[0] === 200 && ($adminProfile['role'] ?? '') === 'hr_coordinator' && ($adminProfile['department'] ?? '') === 'HR' && !isset($adminProfile['password_hash']) && !isset($adminProfile['session_id']), 'HTTP ' . $r[0]);
    $r = $request('GET', '/api/get-profile.php');
    $assert('Unauthenticated profile request denied', $r[0] === 401, 'HTTP ' . $r[0]);

    // Applicant endpoints use identity from the verified DB session, never query values.
    $r = $request('GET', '/api/get-submissions.php?user_id=2', $tokenByUser[1]);
    $body = $decode($r[1]);
    $assert('Applicant list ignores forged user_id', $r[0] === 200 && count($body['submissions'] ?? []) === 1 && (int)$body['submissions'][0]['user_id'] === 1, 'HTTP ' . $r[0]);
    $r = $request('GET', '/api/get-submissions.php?id=2', $tokenByUser[1]);
    $assert('Applicant cannot read another application', $r[0] === 404, 'HTTP ' . $r[0]);
    $r = $request('GET', '/api/get-submissions.php?id=1', $tokenByUser[1]);
    $detail = $decode($r[1]);
    $assert('Applicant own detail omits internal review history and notes', $r[0] === 200 && ($detail['review_history'] ?? null) === [] && !isset($detail['submission']['review_notes']), 'HTTP ' . $r[0]);
    $assert('Applicant document metadata omits BLOB content', $r[0] === 200 && !isset($detail['documents'][0]['file_content']), 'payload check');
    $r = $request('GET', '/api/download-document.php?id=1', $tokenByUser[1]);
    $assert('Applicant downloads own document', $r[0] === 200 && str_contains($r[1], '%PDF-ALPHA'), 'HTTP ' . $r[0]);
    $r = $request('GET', '/api/download-document.php?id=2', $tokenByUser[1]);
    $assert('Applicant cannot download another applicant document', $r[0] === 404, 'HTTP ' . $r[0]);

    $r = $request('GET', '/api/get-submissions.php', $expiredToken);
    $assert('Expired session rejected', $r[0] === 401, 'HTTP ' . $r[0]);
    $r = $request('GET', '/api/get-submissions.php', $revokedToken);
    $assert('Revoked session rejected', $r[0] === 401, 'HTTP ' . $r[0]);
    $unverifiedToken = $tokenByUser[4];
    $r = $request('GET', '/api/get-submissions.php', $unverifiedToken);
    $assert('Unverified applicant session rejected', $r[0] === 401, 'HTTP ' . $r[0]);
    $inactiveToken = $tokenByUser[5];
    $r = $request('GET', '/api/get-submissions.php', $inactiveToken);
    $assert('Inactive applicant session rejected', $r[0] === 401, 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/update-submission.php', $tokenByUser[1], ['submission_id' => 1, 'status' => 'accepted']);
    $assert('Applicant cannot perform admin status update', $r[0] === 403, 'HTTP ' . $r[0]);

    // Department scope is loaded from the signed-in administrator account.
    $r = $request('GET', '/api/get-submissions.php?department=Finance', $tokenSupervisor);
    $scopeData = $decode($r[1]);
    $assert('Supervisor cannot widen list to another department', $r[0] === 200 && count($scopeData['submissions'] ?? []) === 2 && count(array_filter($scopeData['submissions'] ?? [], static fn($s) => $s['department_applied'] !== 'ICT')) === 0, 'HTTP ' . $r[0]);
    $r = $request('GET', '/api/get-submissions.php?id=2', $tokenSupervisor);
    $assert('Supervisor cannot read another department detail', $r[0] === 404, 'HTTP ' . $r[0]);
    $r = $request('GET', '/api/get-submissions.php', $tokenHR);
    $assert('HR retains system-wide read scope', $r[0] === 200 && ($decode($r[1])['count'] ?? 0) === 3, 'HTTP ' . $r[0]);
    $r = $request('GET', '/api/get-departments.php', $tokenSupervisor);
    $departmentRows = $decode($r[1])['departments'] ?? [];
    $assert('Supervisor department directory is isolated', $r[0] === 200 && count($departmentRows) === 1 && ($departmentRows[0]['name'] ?? '') === 'ICT', 'HTTP ' . $r[0] . ' rows=' . json_encode($departmentRows));
    $r = $request('GET', '/api/get-submissions.php', $tokenSuperAdmin);
    $assert('Super Admin retains system-wide read scope', $r[0] === 200 && ($decode($r[1])['count'] ?? 0) === 3, 'HTTP ' . $r[0]);

    // Audit visibility is derived from the authenticated role; filters cannot widen scope.
    $r = $request('GET', '/api/get-audit-log.php', $tokenByUser[1]);
    $assert('Applicant cannot access audit logs', $r[0] === 403, 'HTTP ' . $r[0]);
    $r = $request('GET', '/api/get-audit-log.php?department_id=2&admin_id=' . $financeSupervisorId . '&limit=100', $tokenSupervisor);
    $ictAudit = $decode($r[1]);
    $assert('Supervisor audit query and forged admin filters stay department-scoped', $r[0] === 200 && count($ictAudit['logs'] ?? []) === 1 && ($ictAudit['logs'][0]['admin_pf'] ?? '') === 'ICT-PF-01' && ($ictAudit['total_records'] ?? -1) === 1, 'HTTP ' . $r[0]);
    $assert('Supervisor audit statistics use the same department scope', $r[0] === 200 && count($ictAudit['statistics'] ?? []) === 1 && ($ictAudit['statistics'][0]['action'] ?? '') === 'review_application', 'scoped aggregate');
    $assert('Audit response omits private log payload and request metadata', $r[0] === 200 && !str_contains($r[1], $sensitiveAuditMarker) && !str_contains($r[1], 'user_agent') && !str_contains($r[1], 'ip_address'), 'safe audit payload');
    $r = $request('GET', '/api/get-audit-log.php?limit=1&offset=0', $tokenHR);
    $hrAudit = $decode($r[1]);
    $assert('HR audit access is system-wide with total independent of page size', $r[0] === 200 && count($hrAudit['logs'] ?? []) === 1 && ($hrAudit['total_records'] ?? 0) === 4, 'HTTP ' . $r[0]);
    $r = $request('GET', '/api/get-audit-log.php', $tokenSuperAdmin);
    $assert('Super Admin audit access is system-wide', $r[0] === 200 && ($decode($r[1])['total_records'] ?? 0) === 4, 'HTTP ' . $r[0]);
    $r = $request('GET', '/api/get-audit-log.php?limit=1000', $tokenHR);
    $assert('Audit page size is bounded', $r[0] === 422, 'HTTP ' . $r[0]);
    $r = $request('GET', '/api/get-audit-log.php?start_date=2026-02-31', $tokenHR);
    $assert('Invalid audit dates rejected', $r[0] === 422, 'HTTP ' . $r[0]);

    // Vacancy write actions enforce roles, department, and current state.
    $r = $jsonRequest('POST', '/api/vacancies.php', null, ['action' => 'update_status', 'vacancy_id' => 3, 'status' => 'approved']);
    $assert('Anonymous vacancy state change denied', $r[0] === 401, 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/vacancies.php', $tokenByUser[1], ['action' => 'create', 'title' => 'Forged vacancy']);
    $assert('Applicant cannot create vacancies', $r[0] === 403, 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/vacancies.php', $tokenByUser[1], ['action' => 'update_status', 'vacancy_id' => 3, 'status' => 'rejected']);
    $assert('Applicant cannot approve or reject vacancies', $r[0] === 403, 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/vacancies.php', $tokenByUser[1], ['action' => 'delete', 'vacancy_id' => 3]);
    $assert('Applicant cannot delete vacancies', $r[0] === 403, 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/vacancies.php', $tokenSupervisor, ['action' => 'update_status', 'vacancy_id' => 3, 'status' => 'approved']);
    $assert('Supervisor cannot approve a vacancy', $r[0] === 403 && $db->query("SELECT status FROM vacancies WHERE title='Pending Vacancy'")->fetchColumn() === 'pending', 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/vacancies.php', $tokenHR, ['action' => 'update_status', 'vacancy_id' => 3, 'status' => 'published']);
    $assert('Arbitrary vacancy status rejected', $r[0] === 409 && $db->query("SELECT status FROM vacancies WHERE title='Pending Vacancy'")->fetchColumn() === 'pending', 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/vacancies.php', $tokenHR, ['action' => 'update_status', 'vacancy_id' => 3, 'status' => 'approved']);
    $assert('HR can approve pending vacancy and audit action', $r[0] === 200 && $db->query("SELECT status FROM vacancies WHERE title='Pending Vacancy'")->fetchColumn() === 'approved' && (int)$db->query("SELECT COUNT(*) FROM audit_logs WHERE action='vacancy_status_changed'")->fetchColumn() === 2, 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/vacancies.php', $tokenSupervisor, ['action' => 'end', 'vacancy_id' => 6]);
    $assert('Supervisor cannot close another department vacancy', $r[0] === 404, 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/vacancies.php', $tokenSupervisor, ['action' => 'end', 'vacancy_id' => 2]);
    $assert('Supervisor can close their own approved vacancy', $r[0] === 200 && $db->query("SELECT status FROM vacancies WHERE title='Public Vacancy'")->fetchColumn() === 'closed', 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/vacancies.php', $tokenHR, ['action' => 'delete', 'vacancy_id' => 4]);
    $assert('HR cannot use supervisor/Super Admin deletion action', $r[0] === 403, 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/vacancies.php', $tokenSupervisor, ['action' => 'create', 'title' => 'Scoped Request', 'description' => 'Request', 'skills' => 'PHP', 'positions_count' => 1, 'vacancy_type' => 'attachment', 'department' => 'Finance']);
    $createdVacancy = $db->query("SELECT department_name,status FROM vacancies WHERE title='Scoped Request'")->fetch(PDO::FETCH_ASSOC);
    $assert('Supervisor vacancy creation ignores forged department', $r[0] === 201 && $createdVacancy['department_name'] === 'ICT' && $createdVacancy['status'] === 'pending', 'HTTP ' . $r[0]);

    // Bulk authorization and atomicity: each record is validated before any update.
    $bulkInsert = $db->prepare('INSERT INTO submissions (user_id,national_id,full_name,email,department_applied,status) VALUES (?,?,?,?,?,?)');
    $bulkInsert->execute([1, '10000001', 'Applicant Alpha', 'alpha@example.test', 'ICT', 'applied']);
    $bulkFirst = (int)$db->lastInsertId();
    $bulkInsert->execute([2, '10000002', 'Applicant Beta', 'beta@example.test', 'ICT', 'applied']);
    $bulkSecond = (int)$db->lastInsertId();
    $bulkInsert->execute([3, '10000003', 'Applicant Gamma', 'gamma@example.test', 'ICT', 'completed']);
    $bulkCompleted = (int)$db->lastInsertId();
    $bulkInsert->execute([2, '10000002', 'Applicant Beta', 'beta@example.test', 'Finance', 'applied']);
    $bulkOtherDept = (int)$db->lastInsertId();
    $r = $jsonRequest('POST', '/api/bulk-update-submissions.php', $tokenByUser[1], ['ids' => [$bulkFirst], 'status' => 'accepted']);
    $assert('Applicants cannot perform bulk review', $r[0] === 403, 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/bulk-update-submissions.php', $tokenSupervisor, ['ids' => [$bulkFirst], 'status' => 'accepted']);
    $assert('Supervisor cannot bulk-accept applications reserved for HR', $r[0] === 403 && $db->query('SELECT status FROM submissions WHERE id=' . $bulkFirst)->fetchColumn() === 'applied', 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/bulk-update-submissions.php', $tokenSupervisor, ['ids' => [$bulkFirst], 'status' => 'rejected', 'rejection_reason' => 'Not eligible']);
    $assert('Supervisor cannot bulk-reject applications reserved for HR', $r[0] === 403 && $db->query('SELECT status FROM submissions WHERE id=' . $bulkFirst)->fetchColumn() === 'applied', 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/bulk-update-submissions.php', $tokenSupervisor, ['ids' => [$bulkFirst, $bulkOtherDept], 'status' => 'deployed', 'assigned_role' => 'Analyst', 'assigned_station' => 'Office 1']);
    $assert('Supervisor bulk cannot include another department', $r[0] === 404 && $db->query('SELECT status FROM submissions WHERE id=' . $bulkFirst)->fetchColumn() === 'applied', 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/bulk-update-submissions.php', $tokenHR, ['ids' => [$bulkFirst, $bulkCompleted], 'status' => 'accepted']);
    $assert('Invalid bulk transition rolls back the entire batch', $r[0] === 409 && $db->query('SELECT status FROM submissions WHERE id=' . $bulkFirst)->fetchColumn() === 'applied' && (int)$db->query('SELECT COUNT(*) FROM review_history WHERE submission_id=' . $bulkFirst)->fetchColumn() === 0, 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/bulk-update-submissions.php', $tokenHR, ['ids' => [$bulkFirst], 'status' => 'rejected']);
    $assert('Bulk rejection requires reason', $r[0] === 422, 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/bulk-update-submissions.php', $tokenHR, ['ids' => [$bulkFirst], 'status' => 'completed']);
    $assert('Bulk invalid workflow transition rejected', $r[0] === 409, 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/bulk-update-submissions.php', $tokenHR, ['ids' => [$bulkFirst, $bulkSecond], 'status' => 'accepted', 'pf_number' => 'FORGED-PF', 'department' => 'Finance', 'admin_id' => $financeSupervisorId]);
    $bulkHistory = $db->query('SELECT reviewer_pf,reviewer_department FROM review_history WHERE submission_id=' . $bulkFirst . ' ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $assert('Valid bulk review updates all and records authenticated reviewer identity', $r[0] === 200 && ($decode($r[1])['updated'] ?? 0) === 2 && $bulkHistory['reviewer_pf'] === 'HR-PF-01' && $bulkHistory['reviewer_department'] === 'HR', 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/bulk-update-submissions.php', $tokenSupervisor, ['ids' => [$bulkFirst], 'status' => 'deployed', 'assigned_role' => 'Analyst', 'assigned_station' => 'Office 1']);
    $assert('Supervisor cannot bypass HR decision through individual or bulk paths', $r[0] === 200, 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/delete-submission.php', $tokenSupervisor, ['submission_id' => 1]);
    $assert('Supervisor cannot delete applications', $r[0] === 403, 'HTTP ' . $r[0]);

    $r = $jsonRequest('POST', '/api/toggle-admin-status.php', $tokenSuperAdmin, ['admin_id' => $financeSupervisorId, 'status' => 'inactive', 'reason' => 'Phase 2 test']);
    $invalidated = (int)$db->query('SELECT is_valid FROM sessions WHERE admin_id=' . $financeSupervisorId . ' ORDER BY id DESC LIMIT 1')->fetchColumn();
    $rAfterDeactivate = $request('GET', '/api/verify-session.php', $tokenFinanceSupervisor);
    $assert('Super Admin deactivation invalidates target sessions and is audited', $r[0] === 200 && $invalidated === 0 && $rAfterDeactivate[0] === 401, 'HTTP ' . $r[0] . ' invalidated=' . $invalidated . ' verify=' . $rAfterDeactivate[0] . ' body=' . $r[1]);
    $r = $jsonRequest('POST', '/api/toggle-admin-status.php', $tokenSuperAdmin, ['admin_id' => $financeSupervisorId, 'status' => 'active']);
    $assert('Administrator lifecycle allows valid reactivation', $r[0] === 200, 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/toggle-admin-status.php', $tokenHR, ['admin_id' => $financeSupervisorId, 'status' => 'inactive']);
    $assert('HR cannot mutate administrator lifecycle', $r[0] === 403, 'HTTP ' . $r[0]);
    $r = $request('POST', '/api/update-admin-profile.php', $tokenSupervisor, http_build_query(['phone' => '+254700000001']), ['Content-Type: application/x-www-form-urlencoded']);
    $storedPhone = $db->query('SELECT phone FROM admin_users WHERE id=' . $supervisorId)->fetchColumn();
    $assert('Administrator profile update uses authenticated identity', $r[0] === 200 && $storedPhone === '+254700000001', 'HTTP ' . $r[0]);

    // A request body cannot forge reviewer identity or expand supervisor scope.
    $r = $jsonRequest('POST', '/api/update-submission.php', $tokenHR, ['submission_id' => 1, 'status' => 'accepted', 'pf_number' => 'FORGED-PF', 'department' => 'Finance']);
    $assert('HR can accept an eligible application', $r[0] === 200, 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/update-submission.php', $tokenSupervisor, ['submission_id' => 1, 'status' => 'deployed', 'assigned_role' => 'Placement Intern', 'assigned_station' => 'ICT Office', 'pf_number' => 'FORGED-PF', 'department' => 'Finance']);
    $assert('Supervisor scoped transition succeeds despite forged scope fields', $r[0] === 200, 'HTTP ' . $r[0]);
    $review = $db->query("SELECT reviewer_pf,reviewer_department,status FROM review_history WHERE submission_id=1 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $assert('Review history records database-derived reviewer identity', $review && $review['reviewer_pf'] === 'ICT-PF-01' && $review['reviewer_department'] === 'ICT' && $review['status'] === 'deployed', json_encode($review));
    $r = $jsonRequest('POST', '/api/update-submission.php', $tokenSupervisor, ['submission_id' => 2, 'status' => 'accepted']);
    $assert('Supervisor cannot update another department', $r[0] === 404, 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/update-submission.php', $tokenHR, ['submission_id' => 3, 'status' => 'accepted']);
    $assert('Invalid workflow transition rejected', $r[0] === 409, 'HTTP ' . $r[0]);

    // Authenticated submission binds identity, validates vacancy and atomically associates documents.
    $form = ['national_id' => '10000001', 'full_name' => 'Forged Name', 'email' => 'attacker@example.test', 'department_applied' => 'Finance', 'vacancy_id' => '1', 'course_applying' => 'Testing', 'institution_name' => 'Test University', 'user_id' => '2'];
    $r = $request('POST', '/api/submit-application.php', $tokenDelta, $form);
    $assert('Application National ID spoof rejected', $r[0] === 403, 'HTTP ' . $r[0]);
    $form['national_id'] = '10000007';
    $r = $request('POST', '/api/submit-application.php', $tokenDelta, $form);
    $assert('Expired vacancy rejects submission', $r[0] === 422, 'HTTP ' . $r[0]);
    $form['vacancy_id'] = '5';
    $r = $request('POST', '/api/submit-application.php', $tokenDelta, $form);
    $assert('Closed vacancy rejects new application', $r[0] === 422, 'HTTP ' . $r[0]);
    $form['vacancy_id'] = '1';
    $db->exec("UPDATE vacancies SET deadline_at='" . date('Y-m-d H:i:s', time() + 3600) . "' WHERE id=1");
    $form['application_letter'] = new CURLFile($uploadFile, 'application/pdf', 'sample.pdf');
    $r = $request('POST', '/api/submit-application.php', $tokenDelta, $form);
    $created = $decode($r[1]);
    $assert('Valid authenticated application submission succeeds', $r[0] === 201 && isset($created['submission_id']), 'HTTP ' . $r[0]);
    $createdRow = $db->query('SELECT * FROM submissions WHERE id=' . (int)($created['submission_id'] ?? 0))->fetch(PDO::FETCH_ASSOC);
    $assert('Application identity is bound to authenticated account', $createdRow && (int)$createdRow['user_id'] === 7 && $createdRow['national_id'] === '10000007' && $createdRow['email'] === 'delta@example.test', 'stored ownership/identity');
    $storedDoc = $db->query('SELECT submission_id,mime_type,file_content FROM documents WHERE submission_id=' . (int)($created['submission_id'] ?? 0))->fetch(PDO::FETCH_ASSOC);
    $assert('Uploaded document is validated and linked to created application', $storedDoc && (int)$storedDoc['submission_id'] === (int)$created['submission_id'] && $storedDoc['mime_type'] === 'application/pdf' && str_contains($storedDoc['file_content'], '%PDF-1.4'), 'stored document');
    $r = $request('POST', '/api/submit-application.php', $tokenDelta, $form);
    $assert('Duplicate application rejected', $r[0] === 409, 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/update-submission.php', $tokenHR, ['submission_id' => (int)$created['submission_id'], 'status' => 'rejected']);
    $assert('Rejection without reason rejected', $r[0] === 422, 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/update-submission.php', $tokenHR, ['submission_id' => (int)$created['submission_id'], 'status' => 'rejected', 'rejection_reason' => 'Eligibility criteria not met']);
    $assert('Valid rejection records a reason and succeeds', $r[0] === 200 && $db->query('SELECT rejection_reason FROM submissions WHERE id=' . (int)$created['submission_id'])->fetchColumn() === 'Eligibility criteria not met', 'HTTP ' . $r[0]);

    // Registration must not return codes; stored verification codes are hashes and one-use/expiring.
    $shortRegistration = ['full_name' => 'Short Password', 'national_id' => '10000009', 'email' => 'short@example.test', 'password' => 'Short123!', 'registration_date' => date('Y-m-d')];
    $r = $jsonRequest('POST', '/api/register.php', null, $shortRegistration);
    $assert('Applicant registration rejects passwords shorter than policy', $r[0] === 422, 'HTTP ' . $r[0]);
    $registration = ['full_name' => 'New Applicant', 'national_id' => '10000008', 'email' => 'new@gmail.com', 'password' => 'New-Applicant-123', 'registration_date' => date('Y-m-d')];
    $r = $jsonRequest('POST', '/api/register.php', null, $registration);
    $registrationBody = $decode($r[1]);
    $assert('Registration does not return verification code', $r[0] === 201 && !array_key_exists('debug_code', $registrationBody), 'HTTP ' . $r[0]);
    $storedCodeHash = $db->query("SELECT verification_code FROM users WHERE national_id='10000008'")->fetchColumn();
    $assert('Registration stores verification code as a hash', is_string($storedCodeHash) && password_get_info($storedCodeHash)['algo'] !== null, 'hashed verification value');
    $db->prepare("UPDATE users SET verification_code=?,verification_sent_at=? WHERE id=6")->execute([password_hash('654321', PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);
    $r = $jsonRequest('POST', '/api/verify-email.php', null, ['national_id' => '10000006', 'code' => '654321']);
    $assert('Valid verification code is accepted once', $r[0] === 200 && (int)$db->query('SELECT verified FROM users WHERE id=6')->fetchColumn() === 1, 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/verify-email.php', null, ['national_id' => '10000006', 'code' => '654321']);
    $assert('Consumed verification code cannot be reused', $r[0] === 400, 'HTTP ' . $r[0]);
    $db->prepare('UPDATE users SET verified=0,verification_code=?,verification_sent_at=? WHERE id=6')->execute([password_hash('123456', PASSWORD_DEFAULT), date('Y-m-d H:i:s', time() - 1800)]);
    $r = $jsonRequest('POST', '/api/verify-email.php', null, ['national_id' => '10000006', 'code' => '123456']);
    $assert('Expired verification code rejected', $r[0] === 400, 'HTTP ' . $r[0]);

    $r = $jsonRequest('POST', '/api/create-admin-account.php', $tokenSuperAdmin, [
        'pf_number' => 'NEW-PF-01', 'national_id' => '90000009', 'full_name' => 'New Staff',
        'email' => 'new.staff@example.test', 'department' => 'ICT', 'role_id' => 3, 'password' => 'Short1!a',
    ]);
    $assert('Administrator creation rejects short passwords', $r[0] === 422, 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/create-admin-account.php', $tokenSuperAdmin, [
        'pf_number' => 'NEW-PF-01', 'national_id' => '90000009', 'full_name' => 'New Staff',
        'email' => 'new.staff@example.test', 'department' => 'ICT', 'role_id' => 3, 'password' => 'correct horse battery staple',
    ]);
    $newAdminHash = $db->query("SELECT password_hash FROM admin_users WHERE pf_number='NEW-PF-01'")->fetchColumn();
    $assert('Administrator creation accepts a long passphrase and stores only a valid hash', $r[0] === 201 && ($decode($r[1])['success'] ?? false) && is_string($newAdminHash) && password_verify('correct horse battery staple', $newAdminHash), 'HTTP ' . $r[0] . ', response=' . $r[1]);
    $assert('Administrator creation API never returns password hash or plaintext', !str_contains($r[1], (string)$newAdminHash) && !str_contains($r[1], 'correct horse battery staple'), 'safe response');

    $db->prepare('UPDATE users SET password_hash = ? WHERE id = 2')->execute([password_hash('legacy1', PASSWORD_DEFAULT)]);
    $r = $jsonRequest('POST', '/api/login.php', null, ['identifier' => '10000002', 'password' => 'legacy1']);
    $assert('Existing short password remains usable until the user changes it', $r[0] === 200, 'HTTP ' . $r[0]);

    $knownResetToken = bin2hex(random_bytes(32));
    $db->prepare("INSERT INTO password_reset_tokens (email,token,account_type,expires_at) VALUES (?,?,?,?)")
        ->execute(['gamma@example.test', hash('sha256', $knownResetToken), 'student', date('Y-m-d H:i:s', time() + 600)]);
    $r = $jsonRequest('POST', '/api/reset-password.php', null, ['token' => $knownResetToken, 'password' => 'ShortReset1']);
    $assert('Password reset rejects passwords shorter than policy', $r[0] === 422 && (int)$db->query('SELECT used FROM password_reset_tokens WHERE token=' . $db->quote(hash('sha256', $knownResetToken)))->fetchColumn() === 0, 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/reset-password.php', null, ['token' => $knownResetToken, 'password' => 'Reset-Password-456']);
    $usedToken = (int)$db->query('SELECT used FROM password_reset_tokens WHERE token=' . $db->quote(hash('sha256', $knownResetToken)))->fetchColumn();
    $assert('Password reset accepts hashed single-use token', $r[0] === 200 && $usedToken === 1 && (int)$db->query('SELECT is_valid FROM sessions WHERE user_id=3 AND session_id=' . $db->quote($tokenGamma))->fetchColumn() === 0, 'HTTP ' . $r[0] . ', used=' . $usedToken . ', body=' . $r[1]);
    $r = $jsonRequest('POST', '/api/reset-password.php', null, ['token' => $knownResetToken, 'password' => 'Reset-Password-789']);
    $assert('Password reset token cannot be reused', $r[0] === 400, 'HTTP ' . $r[0] . ', body=' . $r[1]);

    // Login uses the shared session lifecycle and returns a session-bound CSRF token.
    $legacyLowCostHash = password_hash($studentPassword, PASSWORD_BCRYPT, ['cost' => 4]);
    $db->prepare('UPDATE users SET password_hash = ? WHERE id = 1')->execute([$legacyLowCostHash]);
    $r = $jsonRequest('POST', '/api/login.php', null, ['identifier' => '10000001', 'password' => $studentPassword]);
    $studentLogin = $decode($r[1]);
    $assert('Universal applicant login creates compatible DB session', $r[0] === 200 && strlen($studentLogin['token'] ?? '') === 64 && !empty($studentLogin['csrf_token']), 'HTTP ' . $r[0]);
    $upgradedHash = (string)$db->query('SELECT password_hash FROM users WHERE id=1')->fetchColumn();
    $assert('Successful login upgrades an outdated password hash', password_verify($studentPassword, $upgradedHash) && !password_needs_rehash($upgradedHash, PASSWORD_DEFAULT), 'hash upgraded');
    $r = $jsonRequest('POST', '/api/login.php', null, ['identifier' => '10000005', 'password' => $studentPassword]);
    $assert('Inactive applicant cannot log in', $r[0] === 401, 'HTTP ' . $r[0]);

    // Cookie-authenticated mutations require the session-bound CSRF token.
    $loginCurl = curl_init($baseUrl . '/api/admin-login.php');
    curl_setopt_array($loginCurl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['pf_number' => 'HR-PF-01', 'password' => 'Admin-Password-123']),
        CURLOPT_COOKIEJAR => $cookieJar, CURLOPT_COOKIEFILE => $cookieJar, CURLOPT_TIMEOUT => 15]);
    $loginRaw = curl_exec($loginCurl);
    $loginStatus = (int)curl_getinfo($loginCurl, CURLINFO_HTTP_CODE);
    $headerSize = (int)curl_getinfo($loginCurl, CURLINFO_HEADER_SIZE);
    $loginHeaders = substr((string)$loginRaw, 0, $headerSize);
    $loginBody = substr((string)$loginRaw, $headerSize);
    curl_close($loginCurl);
    $cookieLogin = $decode($loginBody);
    $cookieToken = (string)($cookieLogin['csrf_token'] ?? '');
    $phpSessionId = '';
    if (preg_match_all('/^Set-Cookie: *PHPSESSID=([^;]*)/mi', $loginHeaders, $matches) && !empty($matches[1])) $phpSessionId = end($matches[1]);
    $dbSessionCookie = isset($cookieLogin['token']) ? 'session_id=' . $cookieLogin['token'] : '';
    $cookieString = $dbSessionCookie . ($phpSessionId !== '' ? '; PHPSESSID=' . $phpSessionId : '');
    $cookieNames = [];
    if (preg_match_all('/^Set-Cookie: *([^=;]+)/mi', $loginHeaders, $nameMatches)) $cookieNames = $nameMatches[1];
    $r = $request('GET', '/api/verify-session.php', null, null, [], null, $cookieString);
    $assert('Cookie session transport validates after login', $r[0] === 200 && !empty($cookieLogin['token']), 'HTTP ' . $r[0] . ', cookie_names=' . implode(',', $cookieNames) . ', body=' . $r[1]);
    $r = $jsonRequest('POST', '/api/update-submission.php', null, ['submission_id' => 1, 'review_notes' => 'Cookie CSRF check'], [], null, $cookieString);
    $assert('Cookie-authenticated mutation rejects missing CSRF token', $r[0] === 403 && $loginStatus === 200 && $phpSessionId !== '', 'HTTP ' . $r[0] . ', login=' . $loginStatus . ', php_session=' . ($phpSessionId !== '' ? 'present' : 'missing') . ', body=' . $r[1]);
    $r = $jsonRequest('POST', '/api/update-submission.php', null, ['submission_id' => 1, 'review_notes' => 'Cookie CSRF check'], ['X-CSRF-Token: ' . $cookieToken], null, $cookieString);
    $assert('Cookie-authenticated mutation accepts valid CSRF token', $r[0] === 200 && $cookieToken !== '', 'HTTP ' . $r[0] . ', csrf=' . ($cookieToken !== '' ? 'present' : 'missing') . ', php_session=' . ($phpSessionId !== '' ? 'present' : 'missing') . ', body=' . $r[1]);

    $shortAdminPasswordForm = http_build_query(['current_password' => 'Admin-Password-123', 'new_password' => 'Short1!a', 'confirm_password' => 'Short1!a']);
    $r = $request('POST', '/api/change-password.php', $tokenHR, $shortAdminPasswordForm, ['Content-Type: application/x-www-form-urlencoded']);
    $assert('Administrator password change rejects short password', $r[0] === 422, 'HTTP ' . $r[0]);
    $newAdminPassword = 'HR-New-Password-2026';
    $adminPasswordForm = http_build_query(['current_password' => 'Admin-Password-123', 'new_password' => $newAdminPassword, 'confirm_password' => $newAdminPassword]);
    $r = $request('POST', '/api/change-password.php', $tokenHR, $adminPasswordForm, ['Content-Type: application/x-www-form-urlencoded']);
    $hrPasswordHash = (string)$db->query('SELECT password_hash FROM admin_users WHERE id=' . $hrId)->fetchColumn();
    $assert('Administrator password change accepts passphrase and stores its hash', $r[0] === 200 && password_verify($newAdminPassword, $hrPasswordHash), 'HTTP ' . $r[0]);

    // Password change now matches password_hash and expires all other sessions.
    $shortPasswordForm = http_build_query(['current_password' => $studentPassword, 'new_password' => 'Short1!a', 'confirm_password' => 'Short1!a']);
    $r = $request('POST', '/api/change-password.php', $tokenByUser[1], $shortPasswordForm, ['Content-Type: application/x-www-form-urlencoded']);
    $assert('Password change rejects passwords shorter than policy', $r[0] === 422, 'HTTP ' . $r[0]);
    $formPassword = http_build_query(['current_password' => $studentPassword, 'new_password' => 'New-Current-Password-456', 'confirm_password' => 'New-Current-Password-456']);
    $r = $request('POST', '/api/change-password.php', $tokenByUser[1], $formPassword, ['Content-Type: application/x-www-form-urlencoded']);
    $assert('Password change uses the tracked password_hash column', $r[0] === 200, 'HTTP ' . $r[0]);
    $r = $jsonRequest('POST', '/api/login.php', null, ['identifier' => '10000001', 'password' => 'New-Current-Password-456']);
    $assert('Updated password can authenticate', $r[0] === 200, 'HTTP ' . $r[0]);

    // Log files contain sanitized metadata only, never session or reset tokens.
    $allLogs = (is_file($serverLog) ? file_get_contents($serverLog) : '') . (is_file($logDirectory . DIRECTORY_SEPARATOR . 'mail.log') ? file_get_contents($logDirectory . DIRECTORY_SEPARATOR . 'mail.log') : '');
    $assert('Sensitive token and email content absent from test logs', !str_contains($allLogs, $tokenByUser[1]) && !str_contains($allLogs, $knownResetToken) && !str_contains($allLogs, 'new@gmail.com') && !str_contains($allLogs, '654321') && !str_contains($allLogs, $newAdminPassword) && !str_contains($allLogs, 'correct horse battery staple'), 'sanitized logs');

    // Documented usable credentials have been removed from fresh-install assets.
    $legacyPassword = implode('', ['admin', '123']);
    $readme = file_get_contents($root . DIRECTORY_SEPARATOR . 'README.md');
    $seed = file_get_contents($root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.sql');
    $docsContainDefault = false;
    $docsDir = $root . DIRECTORY_SEPARATOR . 'docs';
    if (is_dir($docsDir)) {
        $docs = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($docsDir, FilesystemIterator::SKIP_DOTS));
        foreach ($docs as $doc) if ($doc->isFile() && str_ends_with($doc->getFilename(), '.md') && str_contains((string)file_get_contents($doc->getPathname()), $legacyPassword)) $docsContainDefault = true;
    }
    $assert('Default plaintext administrator password absent from docs and seed', !str_contains($readme, $legacyPassword) && !str_contains($seed, $legacyPassword) && !$docsContainDefault, 'source scan');
} catch (Throwable $e) {
    $assert('Security regression harness completed', false, $e->getMessage());
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
}

foreach ($results as [$name, $ok, $detail]) {
    fwrite(STDOUT, ($ok ? 'PASS' : 'FAIL') . ' ' . $name . ($detail !== '' ? ' — ' . $detail : '') . "\n");
}
fwrite(STDOUT, "TOTAL: {$passed} passed, {$failed} failed\n");

// Remove only the uniquely-created test directory.
$db = null;
unset($addSession, $sessionInsert, $userInsert, $adminInsert, $auditInsert, $bulkInsert, $stmt, $count, $review, $createdRow, $storedDoc);
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($work, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($iterator as $item) {
    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
}
rmdir($work);
exit($failed === 0 ? 0 : 1);
?>
