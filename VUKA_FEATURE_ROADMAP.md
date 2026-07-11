# Vuka Portal — Feature Enhancement Roadmap

> Implementation guide for all planned enhancements.
> Each feature covers: what it does, database changes, new API endpoints, and frontend implementation.

---

## Table of Contents

1. [Email Notifications](#1-email-notifications)
2. [PDF Letter Generation](#2-pdf-letter-generation)
3. [Interview Scheduling Module](#3-interview-scheduling-module)
4. [Intern Performance Evaluation](#4-intern-performance-evaluation)
5. [In-App Notification Bell](#5-in-app-notification-bell)
6. [Bulk Actions on Applications](#6-bulk-actions-on-applications)
7. [Vacancy Application Deadlines](#7-vacancy-application-deadlines)
8. [Dark Mode Toggle](#8-dark-mode-toggle)
9. [Password Reset Flow](#9-password-reset-flow)
10. [Advanced Analytics Dashboard](#10-advanced-analytics-dashboard)
11. [Server-Side Pagination](#11-server-side-pagination)
12. [Application Withdrawal](#12-application-withdrawal)
13. [Login Rate Limiting](#13-login-rate-limiting)
14. [Print-Friendly View](#14-print-friendly-view)

---

## 1. Email Notifications

### What it does
Automatically emails students whenever their application status changes (accepted, rejected, deployed). Also sends HR and supervisors digest alerts when new applications arrive or vacancies are approved. Transforms Vuka from a passive data store into a communicating system.

### Dependency
Install [PHPMailer](https://github.com/PHPMailer/PHPMailer) via Composer, or drop the three required files (`PHPMailer.php`, `SMTP.php`, `Exception.php`) directly into a `lib/phpmailer/` folder for zero-dependency setups.

```
/lib/
└── phpmailer/
    ├── PHPMailer.php
    ├── SMTP.php
    └── Exception.php
```

### Database changes
No new tables needed. Piggyback on the existing `review_history` table — add an `email_sent` tinyint(1) column to track whether a notification was dispatched for each status change. This prevents duplicate sends on retry.

```sql
ALTER TABLE review_history
  ADD COLUMN email_sent TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN email_sent_at DATETIME NULL;
```

### New files

**`lib/mailer.php`** — shared mailer wrapper used by all endpoints:

```php
<?php
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';
require_once __DIR__ . '/phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;      // defined in config.php
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->setFrom(SMTP_FROM, 'Vuka Portal');
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer error: " . $mail->ErrorInfo);
        return false;
    }
}
```

**`lib/email-templates.php`** — one function per email type, returns an HTML string:

```php
<?php
function emailStatusChanged(string $studentName, string $status, string $department): string {
    $statusMessages = [
        'accepted'  => "Congratulations! Your application has been accepted by the <strong>{$department}</strong> department.",
        'rejected'  => "We regret to inform you that your application to <strong>{$department}</strong> was not successful.",
        'deployed'  => "You have been deployed! Please report to the <strong>{$department}</strong> department.",
        'ongoing'   => "Your attachment/internship is now marked as <strong>ongoing</strong>. Welcome aboard.",
    ];
    $message = $statusMessages[$status] ?? "Your application status has been updated to <strong>{$status}</strong>.";

    return "
    <div style='font-family: sans-serif; max-width: 560px; margin: 0 auto; padding: 24px;'>
      <h2 style='color: #1e3a5f;'>Vuka Attachment Portal</h2>
      <p>Dear {$studentName},</p>
      <p>{$message}</p>
      <p>Log in to your portal to view full details and next steps.</p>
      <a href='" . APP_URL . "' style='display:inline-block;margin-top:16px;padding:10px 20px;
         background:#1e3a5f;color:#fff;text-decoration:none;border-radius:6px;'>
        View My Application
      </a>
      <p style='margin-top:24px;color:#888;font-size:12px;'>Vuka Attachment &amp; Internship Portal</p>
    </div>";
}
```

### Integration point
Hook into `api/update-submission.php`. After every successful status update, query for the student's email and send:

```php
// In update-submission.php — after the UPDATE query succeeds
require_once '../lib/mailer.php';
require_once '../lib/email-templates.php';

$student = $pdo->prepare("SELECT u.full_name, u.email FROM users u
                           JOIN submissions s ON s.user_id = u.id
                           WHERE s.id = ?");
$student->execute([$submissionId]);
$row = $student->fetch(PDO::FETCH_ASSOC);

if ($row && $row['email']) {
    $html = emailStatusChanged($row['full_name'], $newStatus, $department);
    $sent = sendMail($row['email'], $row['full_name'], "Application Update — {$newStatus}", $html);

    // Mark as sent in review_history
    $pdo->prepare("UPDATE review_history SET email_sent = 1, email_sent_at = NOW()
                   WHERE submission_id = ? ORDER BY created_at DESC LIMIT 1")
        ->execute([$submissionId]);
}
```

### `config.php` additions

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'your@email.com');
define('SMTP_PASS', 'your_app_password');
define('SMTP_PORT', 587);
define('SMTP_FROM', 'noreply@vukaportал.co.ke');
define('APP_URL',   'http://localhost/attachment/');
```

---

## 2. PDF Letter Generation

### What it does
Auto-generates formatted PDF documents for: offer/acceptance letters, rejection letters, and deployment certificates. Supervisors click "Generate Letter" on an applicant's detail view; the PDF downloads instantly with the student's details, department, role, and official branding pre-filled.

### Dependency
Use [FPDF](http://www.fpdf.org/) — a pure PHP PDF library, zero dependencies, no Composer required. Drop `fpdf.php` into `lib/fpdf/`.

```
/lib/
└── fpdf/
    └── fpdf.php
```

### New API endpoint
**`api/generate-letter.php`** — authenticated, admin-tier endpoint:

```php
<?php
require_once '../session-manager.php';
require_once '../lib/fpdf/fpdf.php';

requireAnyAdmin();  // RBAC guard

$submissionId = intval($_GET['submission_id'] ?? 0);
$type = $_GET['type'] ?? 'offer'; // offer | rejection | deployment

// Fetch full submission + student details
$stmt = $pdo->prepare("
    SELECT s.*, u.full_name, u.national_id, u.email,
           v.title AS vacancy_title, d.name AS department_name
    FROM submissions s
    JOIN users u ON u.id = s.user_id
    JOIN vacancies v ON v.id = s.vacancy_id
    JOIN departments d ON d.id = v.department_id
    WHERE s.id = ?
");
$stmt->execute([$submissionId]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    http_response_code(404);
    echo json_encode(['error' => 'Submission not found']);
    exit;
}

// Department scoping for supervisors
$session = getSession();
if ($session['role'] === 'department_supervisor' &&
    $data['department_name'] !== $session['department']) {
    http_response_code(403);
    exit;
}

buildPDF($data, $type);

function buildPDF(array $d, string $type): void {
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetMargins(25, 25, 25);

    // Header
    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->SetTextColor(30, 58, 95);
    $pdf->Cell(0, 10, 'VUKA ATTACHMENT & INTERNSHIP PORTAL', 0, 1, 'C');
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 6, date('d F Y'), 0, 1, 'C');
    $pdf->Ln(8);

    // Reference line
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 6, 'REF: VKP/' . strtoupper($d['department_name']) . '/' . $d['id'], 0, 1);
    $pdf->Ln(4);

    // Addressee
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(0, 6, $d['full_name'], 0, 1);
    $pdf->Cell(0, 6, 'National ID: ' . $d['national_id'], 0, 1);
    $pdf->Ln(6);

    // Body based on letter type
    $titles = [
        'offer'      => 'OFFER OF ATTACHMENT/INTERNSHIP',
        'rejection'  => 'APPLICATION OUTCOME',
        'deployment' => 'DEPLOYMENT NOTIFICATION',
    ];

    $pdf->SetFont('Helvetica', 'BU', 11);
    $pdf->Cell(0, 8, $titles[$type] ?? 'NOTIFICATION', 0, 1);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Ln(3);

    if ($type === 'offer') {
        $pdf->MultiCell(0, 6,
            "We are pleased to inform you that your application for the position of " .
            "{$d['vacancy_title']} in the {$d['department_name']} Department has been " .
            "successful. You are hereby offered an attachment/internship placement.\n\n" .
            "Please report to the {$d['department_name']} Department with this letter " .
            "and all original documents as previously submitted."
        );
    } elseif ($type === 'rejection') {
        $pdf->MultiCell(0, 6,
            "Thank you for your interest in the {$d['vacancy_title']} position in the " .
            "{$d['department_name']} Department. After careful review, we regret to inform " .
            "you that your application was not successful at this time.\n\n" .
            "We encourage you to apply again in future intake periods."
        );
    } elseif ($type === 'deployment') {
        $pdf->MultiCell(0, 6,
            "This is to confirm that {$d['full_name']} (National ID: {$d['national_id']}) " .
            "has been officially deployed to the {$d['department_name']} Department as " .
            "{$d['assigned_role']}.\n\n" .
            "Workstation/Office: {$d['workstation']}\n" .
            "Effective Date: " . date('d F Y')
        );
    }

    $pdf->Ln(12);
    $pdf->Cell(0, 6, 'Authorized by:', 0, 1);
    $pdf->Ln(12);
    $pdf->Cell(0, 6, '____________________________', 0, 1);
    $pdf->Cell(0, 6, 'HR Coordinator / Department Supervisor', 0, 1);
    $pdf->Cell(0, 6, 'Vuka Portal', 0, 1);

    // Output as download
    $filename = $type . '_letter_' . $d['id'] . '.pdf';
    $pdf->Output('D', $filename);
}
```

### Frontend — add buttons to applicant detail view

In `assets/js/supervisor.js` and `assets/js/hr.js`, add inside the applicant detail panel:

```javascript
function renderLetterButtons(submissionId, status) {
    const map = {
        accepted: { type: 'offer',      label: 'Download Offer Letter' },
        rejected: { type: 'rejection',  label: 'Download Rejection Letter' },
        deployed: { type: 'deployment', label: 'Download Deployment Certificate' },
    };
    const btn = map[status];
    if (!btn) return '';
    return `
        <a href="/api/generate-letter.php?submission_id=${submissionId}&type=${btn.type}"
           target="_blank"
           class="btn btn-outline-primary btn-sm mt-2">
            <i class="fas fa-file-pdf me-1"></i>${btn.label}
        </a>`;
}
```

---

## 3. Interview Scheduling Module

### What it does
Adds a full interview lifecycle stage between `accepted` and `deployed`. Supervisors create an interview slot (date, time, location/link, notes). Students see the invitation on their dashboard and confirm or flag a conflict. The supervisor dashboard shows a calendar-style view of all upcoming interviews for their department.

### Database changes

```sql
CREATE TABLE interview_slots (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT          NOT NULL,
    department_id INT          NOT NULL,
    scheduled_at  DATETIME     NOT NULL,
    location      VARCHAR(255) NOT NULL,            -- room number or video link
    notes         TEXT         NULL,
    status        ENUM('scheduled','confirmed','rescheduled','cancelled','completed')
                               NOT NULL DEFAULT 'scheduled',
    created_by    INT          NOT NULL,            -- admin_users.id
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (created_by)    REFERENCES admin_users(id)
);

CREATE TABLE interview_responses (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    slot_id         INT  NOT NULL,
    response        ENUM('confirmed','conflict') NOT NULL,
    student_notes   TEXT NULL,
    responded_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (slot_id) REFERENCES interview_slots(id) ON DELETE CASCADE
);
```

Update `submissions.status` ENUM to include `'interview'`:

```sql
ALTER TABLE submissions
  MODIFY COLUMN status
    ENUM('applied','pending','interview','accepted','rejected','deployed','ongoing')
    NOT NULL DEFAULT 'applied';
```

### New API endpoints

| File | Method | Auth | Purpose |
|------|--------|------|---------|
| `api/interview-slots.php` | GET | Admin | List slots (dept-scoped) |
| `api/interview-slots.php` | POST `action=create` | Supervisor / HR | Create a slot, move submission to `interview` |
| `api/interview-slots.php` | POST `action=update_status` | Supervisor / HR | Cancel, reschedule, or complete |
| `api/interview-response.php` | POST | Student | Confirm or flag conflict |
| `api/get-student-interviews.php` | GET | Student | Student's own upcoming interviews |

**`api/interview-slots.php`** — creation logic:

```php
if ($action === 'create') {
    requireSupervisor(); // or requireHR()

    $submissionId = intval($_POST['submission_id']);
    $scheduledAt  = $_POST['scheduled_at'];   // 'YYYY-MM-DD HH:MM'
    $location     = trim($_POST['location']);
    $notes        = trim($_POST['notes'] ?? '');

    $pdo->beginTransaction();
    try {
        // Create slot
        $stmt = $pdo->prepare("INSERT INTO interview_slots
            (submission_id, department_id, scheduled_at, location, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $submissionId, $session['department_id'],
            $scheduledAt, $location, $notes, $session['admin_id']
        ]);
        $slotId = $pdo->lastInsertId();

        // Advance submission to 'interview'
        $pdo->prepare("UPDATE submissions SET status = 'interview' WHERE id = ?")
            ->execute([$submissionId]);

        $pdo->commit();

        // Email the student (reuse mailer from Feature 1)
        notifyStudentInterview($submissionId, $scheduledAt, $location);

        echo json_encode(['success' => true, 'slot_id' => $slotId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create slot']);
    }
}
```

### Frontend — supervisor dashboard

Add an **"Interviews" tab** to `pages/supervisor_dashboard.php`. Inside `assets/js/supervisor.js`:

```javascript
// Calendar-style week grid built with a plain HTML table
function renderInterviewCalendar(slots) {
    const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
    // Group slots by day of week
    const grouped = {};
    slots.forEach(slot => {
        const day = new Date(slot.scheduled_at)
            .toLocaleDateString('en-US', { weekday: 'short' });
        if (!grouped[day]) grouped[day] = [];
        grouped[day].push(slot);
    });

    let html = '<div class="row g-2">';
    days.forEach(day => {
        const daySlots = grouped[day] || [];
        html += `
            <div class="col">
                <div class="p-2 border rounded text-center fw-semibold small bg-light">${day}</div>
                ${daySlots.map(s => `
                    <div class="p-2 border rounded mt-1 small cursor-pointer"
                         onclick="openInterviewDetail(${s.id})">
                        <div class="fw-semibold">${s.student_name}</div>
                        <div class="text-muted">${formatTime(s.scheduled_at)}</div>
                        <span class="badge bg-${statusColor(s.status)}">${s.status}</span>
                    </div>`).join('')}
            </div>`;
    });
    html += '</div>';
    return html;
}
```

### Frontend — student dashboard

In `pages/student_dashboard.php`, add an **interview card** in the status timeline when `status === 'interview'`:

```javascript
if (submission.status === 'interview') {
    interviewSection.innerHTML = `
        <div class="alert alert-info mt-3">
            <i class="fas fa-calendar-check me-2"></i>
            <strong>Interview Scheduled</strong><br>
            <span>${formatDate(slot.scheduled_at)} at ${slot.location}</span>
            <div class="mt-2">
                <button class="btn btn-sm btn-success me-2"
                        onclick="respondToInterview(${slot.id}, 'confirmed')">
                    Confirm Attendance
                </button>
                <button class="btn btn-sm btn-outline-warning"
                        onclick="respondToInterview(${slot.id}, 'conflict')">
                    Flag a Conflict
                </button>
            </div>
        </div>`;
}
```

---

## 4. Intern Performance Evaluation

### What it does
After a student's attachment is complete (status moves from `ongoing` toward closure), the assigned supervisor fills out a structured evaluation form covering attendance, technical skills, attitude, and communication. Results are stored, visible to HR and Super Admin, and a PDF summary is generated for the student.

### Database changes

```sql
CREATE TABLE evaluations (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    submission_id   INT          NOT NULL UNIQUE,  -- one evaluation per attachment
    evaluator_id    INT          NOT NULL,          -- admin_users.id
    attendance      TINYINT      NOT NULL,          -- score 1–5
    technical       TINYINT      NOT NULL,
    attitude        TINYINT      NOT NULL,
    communication   TINYINT      NOT NULL,
    initiative      TINYINT      NOT NULL,
    overall_comment TEXT         NULL,
    recommendation  ENUM('highly_recommended','recommended','not_recommended') NOT NULL,
    submitted_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (evaluator_id)  REFERENCES admin_users(id)
);
```

Update `submissions.status` ENUM to include `'completed'`:

```sql
ALTER TABLE submissions
  MODIFY COLUMN status
    ENUM('applied','pending','interview','accepted','rejected','deployed','ongoing','completed')
    NOT NULL DEFAULT 'applied';
```

### New API endpoints

| File | Method | Auth | Purpose |
|------|--------|------|---------|
| `api/evaluations.php` | POST | Supervisor | Submit evaluation form |
| `api/evaluations.php` | GET | Admin tier | Get evaluation for a submission |
| `api/generate-evaluation-pdf.php` | GET | Admin / Student | Download PDF evaluation summary |

**`api/evaluations.php`** — POST handler:

```php
requireSupervisor();

$submissionId = intval($_POST['submission_id']);
$scores = [
    'attendance'    => intval($_POST['attendance']),
    'technical'     => intval($_POST['technical']),
    'attitude'      => intval($_POST['attitude']),
    'communication' => intval($_POST['communication']),
    'initiative'    => intval($_POST['initiative']),
];

// Validate all scores are 1-5
foreach ($scores as $key => $val) {
    if ($val < 1 || $val > 5) {
        http_response_code(422);
        echo json_encode(['error' => "Invalid score for {$key}"]);
        exit;
    }
}

$stmt = $pdo->prepare("INSERT INTO evaluations
    (submission_id, evaluator_id, attendance, technical, attitude,
     communication, initiative, overall_comment, recommendation)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
    attendance=VALUES(attendance), technical=VALUES(technical),
    attitude=VALUES(attitude), communication=VALUES(communication),
    initiative=VALUES(initiative), overall_comment=VALUES(overall_comment),
    recommendation=VALUES(recommendation)");

$stmt->execute([
    $submissionId, $session['admin_id'],
    $scores['attendance'], $scores['technical'], $scores['attitude'],
    $scores['communication'], $scores['initiative'],
    trim($_POST['overall_comment'] ?? ''),
    $_POST['recommendation']
]);

// Move submission to 'completed'
$pdo->prepare("UPDATE submissions SET status = 'completed' WHERE id = ?")
    ->execute([$submissionId]);

echo json_encode(['success' => true]);
```

### Frontend — evaluation form (supervisor dashboard)

Add a **"Submit Evaluation"** button on applicants with `status = 'ongoing'`. The form renders in a Bootstrap modal:

```html
<!-- Evaluation modal inside supervisor_dashboard.php -->
<div class="modal fade" id="evalModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Performance Evaluation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="evalModalBody">
        <!-- Injected by JS -->
      </div>
    </div>
  </div>
</div>
```

```javascript
const CRITERIA = ['attendance', 'technical', 'attitude', 'communication', 'initiative'];
const LABELS   = ['Attendance', 'Technical Skills', 'Attitude', 'Communication', 'Initiative'];

function openEvalModal(submissionId) {
    const rows = CRITERIA.map((c, i) => `
        <div class="mb-3">
            <label class="form-label fw-semibold">${LABELS[i]}</label>
            <div class="d-flex gap-2">
                ${[1,2,3,4,5].map(n => `
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio"
                               name="${c}" id="${c}_${n}" value="${n}" required>
                        <label class="form-check-label" for="${c}_${n}">${n}</label>
                    </div>`).join('')}
            </div>
        </div>`).join('');

    document.getElementById('evalModalBody').innerHTML = `
        <p class="text-muted small">Rate each criterion 1 (poor) to 5 (excellent).</p>
        ${rows}
        <div class="mb-3">
            <label class="form-label fw-semibold">Overall Comment</label>
            <textarea class="form-control" id="overallComment" rows="3"></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Recommendation</label>
            <select class="form-select" id="recommendation">
                <option value="highly_recommended">Highly Recommended</option>
                <option value="recommended">Recommended</option>
                <option value="not_recommended">Not Recommended</option>
            </select>
        </div>
        <button class="btn btn-primary" onclick="submitEval(${submissionId})">
            Submit Evaluation
        </button>`;

    new bootstrap.Modal(document.getElementById('evalModal')).show();
}

async function submitEval(submissionId) {
    const body = new FormData();
    body.append('submission_id', submissionId);
    CRITERIA.forEach(c => {
        const checked = document.querySelector(`input[name="${c}"]:checked`);
        body.append(c, checked ? checked.value : '0');
    });
    body.append('overall_comment', document.getElementById('overallComment').value);
    body.append('recommendation', document.getElementById('recommendation').value);

    const res = await apiFetch('/api/evaluations.php', { method: 'POST', body });
    if (res.success) {
        showToast('Evaluation submitted successfully', 'success');
        bootstrap.Modal.getInstance(document.getElementById('evalModal')).hide();
        loadApplicants(); // refresh list
    } else {
        showToast(res.error || 'Failed to submit evaluation', 'danger');
    }
}
```

---

## 5. In-App Notification Bell

### What it does
A bell icon in the navbar shows an unread-count badge. Clicking it opens a dropdown listing recent notifications (status changes, new applications, vacancy approvals). Notifications are polled every 30 seconds via AJAX. Marked as read when the dropdown is opened.

### Database changes

```sql
CREATE TABLE notifications (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    recipient_id INT          NOT NULL,   -- users.id OR admin_users.id
    role_type    ENUM('student','admin')  NOT NULL DEFAULT 'student',
    title        VARCHAR(100) NOT NULL,
    body         VARCHAR(255) NOT NULL,
    link         VARCHAR(255) NULL,       -- deep link, e.g. '?view=application&id=12'
    is_read      TINYINT(1)   NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_recipient (recipient_id, role_type, is_read)
);
```

### New API endpoints

| File | Method | Purpose | Auth |
|------|--------|---------|------|
| `api/notifications.php` | GET | Fetch unread/recent notifications | Authenticated |
| `api/notifications.php` | POST `action=mark_read` | Mark all as read | Authenticated |

**`api/notifications.php`** — GET handler:

```php
// Works for both students and admins
$userId   = $session['user_id'] ?? $session['admin_id'];
$roleType = isset($session['user_id']) ? 'student' : 'admin';

$stmt = $pdo->prepare("SELECT * FROM notifications
                        WHERE recipient_id = ? AND role_type = ?
                        ORDER BY created_at DESC LIMIT 20");
$stmt->execute([$userId, $roleType]);

$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
$unread = array_sum(array_column($notifications, 'is_read') === array_fill(0, count($notifications), 0)
    ? array_column($notifications, 'is_read')
    : array_map(fn($n) => $n['is_read'] === 0 ? 1 : 0, $notifications));

echo json_encode([
    'notifications' => $notifications,
    'unread_count'  => $unread,
]);
```

### Helper function — add to `lib/notifications.php`

Call this anywhere a relevant event occurs (reuse across all features):

```php
function createNotification(PDO $pdo, int $recipientId, string $roleType,
                             string $title, string $body, ?string $link = null): void {
    $pdo->prepare("INSERT INTO notifications
                   (recipient_id, role_type, title, body, link)
                   VALUES (?, ?, ?, ?, ?)")
        ->execute([$recipientId, $roleType, $title, $body, $link]);
}
```

### Frontend — bell in `common.js` + HTML in each dashboard

```html
<!-- Add to navbar in each dashboard PHP file -->
<div class="position-relative me-3" id="notifBellWrapper">
    <button class="btn btn-link text-white p-0" id="notifBell"
            onclick="toggleNotifDropdown()">
        <i class="fas fa-bell fs-5"></i>
        <span id="notifBadge"
              class="position-absolute top-0 start-100 translate-middle
                     badge rounded-pill bg-danger d-none">
            0
        </span>
    </button>
    <div id="notifDropdown"
         class="dropdown-menu dropdown-menu-end shadow p-0 d-none"
         style="width:320px;max-height:420px;overflow-y:auto;">
        <div class="p-3 border-bottom fw-semibold">Notifications</div>
        <div id="notifList"></div>
    </div>
</div>
```

```javascript
// In common.js
async function pollNotifications() {
    const data = await apiFetch('/api/notifications.php');
    const badge = document.getElementById('notifBadge');
    const list  = document.getElementById('notifList');
    if (!badge || !list) return;

    badge.textContent = data.unread_count;
    badge.classList.toggle('d-none', data.unread_count === 0);

    list.innerHTML = data.notifications.length
        ? data.notifications.map(n => `
            <a href="${n.link || '#'}"
               class="d-block px-3 py-2 border-bottom text-decoration-none
                      ${n.is_read ? '' : 'bg-light'}">
                <div class="fw-semibold small">${n.title}</div>
                <div class="text-muted small">${n.body}</div>
                <div class="text-muted" style="font-size:11px">${timeAgo(n.created_at)}</div>
            </a>`).join('')
        : '<div class="p-3 text-muted small text-center">No notifications yet</div>';
}

function toggleNotifDropdown() {
    const dd = document.getElementById('notifDropdown');
    dd.classList.toggle('d-none');
    if (!dd.classList.contains('d-none')) {
        apiFetch('/api/notifications.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'mark_read' }),
        });
        document.getElementById('notifBadge').classList.add('d-none');
    }
}

// Poll on load, then every 30 seconds
pollNotifications();
setInterval(pollNotifications, 30000);
document.addEventListener('visibilitychange', () => {
    if (!document.hidden) pollNotifications();
});
```

---

## 6. Bulk Actions on Applications

### What it does
Adds checkboxes to the applicant list tables on both the supervisor and HR dashboards. A floating action bar appears at the bottom of the screen when one or more rows are selected, offering **Bulk Accept**, **Bulk Reject**, and **Bulk Export CSV**. Reduces repetitive clicking significantly for busy HR coordinators.

### Database changes
None required — uses existing `update-submission.php` logic in a loop.

### New API endpoint
**`api/bulk-update-submissions.php`**:

```php
<?php
require_once '../session-manager.php';
requireAnyAdmin();

$data = json_decode(file_get_contents('php://input'), true);
$ids    = array_map('intval', $data['ids'] ?? []);
$status = $data['status'] ?? '';
$notes  = trim($data['notes'] ?? '');

if (empty($ids) || !in_array($status, ['accepted', 'rejected', 'pending'])) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$params = array_merge([$status, $notes], $ids);

// Department scoping for supervisors
if ($session['role'] === 'department_supervisor') {
    $stmt = $pdo->prepare("UPDATE submissions s
                           JOIN vacancies v ON v.id = s.vacancy_id
                           SET s.status = ?, s.review_notes = ?
                           WHERE s.id IN ({$placeholders})
                           AND v.department_id = ?");
    $params[] = $session['department_id'];
} else {
    $stmt = $pdo->prepare("UPDATE submissions SET status = ?, review_notes = ?
                           WHERE id IN ({$placeholders})");
}

$stmt->execute($params);
echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);
```

### Frontend

```javascript
// In supervisor.js and hr.js
let selectedIds = new Set();

// Inject checkbox into each table row on render
function renderApplicantRow(sub) {
    return `<tr>
        <td>
            <input type="checkbox" class="applicant-cb" value="${sub.id}"
                   onchange="handleRowSelect(this)">
        </td>
        <!-- ...existing cells... -->
    </tr>`;
}

function handleRowSelect(checkbox) {
    checkbox.checked
        ? selectedIds.add(parseInt(checkbox.value))
        : selectedIds.delete(parseInt(checkbox.value));
    updateBulkBar();
}

function updateBulkBar() {
    const bar = document.getElementById('bulkActionBar');
    const count = document.getElementById('bulkCount');
    if (selectedIds.size > 0) {
        bar.classList.remove('d-none');
        count.textContent = `${selectedIds.size} selected`;
    } else {
        bar.classList.add('d-none');
    }
}

async function bulkAction(status) {
    if (!confirm(`${status.toUpperCase()} ${selectedIds.size} application(s)?`)) return;
    const res = await apiFetch('/api/bulk-update-submissions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids: [...selectedIds], status }),
    });
    if (res.success) {
        showToast(`${res.updated} applications updated to ${status}`, 'success');
        selectedIds.clear();
        loadApplicants();
    }
}
```

```html
<!-- Bulk action bar — add to supervisor_dashboard.php and hr_dashboard.php -->
<div id="bulkActionBar" class="d-none position-fixed bottom-0 start-0 end-0
     bg-dark text-white p-3 d-flex align-items-center justify-content-between"
     style="z-index:1050;">
    <span id="bulkCount" class="fw-semibold"></span>
    <div class="d-flex gap-2">
        <button class="btn btn-success btn-sm" onclick="bulkAction('accepted')">
            <i class="fas fa-check me-1"></i>Accept All
        </button>
        <button class="btn btn-danger btn-sm" onclick="bulkAction('rejected')">
            <i class="fas fa-times me-1"></i>Reject All
        </button>
        <button class="btn btn-outline-light btn-sm" onclick="selectedIds.clear(); updateBulkBar()">
            Cancel
        </button>
    </div>
</div>
```

---

## 7. Vacancy Application Deadlines

### What it does
Each vacancy can have an optional `deadline_at` datetime. The student portal shows a countdown on vacancy cards. When the deadline passes, the vacancy is automatically closed (no new applications accepted). A cron job or lazy-close check handles the automation.

### Database changes

```sql
ALTER TABLE vacancies
  ADD COLUMN deadline_at DATETIME NULL AFTER description,
  ADD COLUMN positions_filled INT NOT NULL DEFAULT 0;
```

### Backend — lazy deadline check in `api/vacancies.php` (GET)

Add to the WHERE clause:

```php
// Automatically close expired vacancies on every read
$pdo->exec("UPDATE vacancies
             SET status = 'closed'
             WHERE deadline_at IS NOT NULL
             AND deadline_at < NOW()
             AND status = 'approved'");
```

### Backend — block applications past deadline in `api/submit-application.php`

```php
$vacancy = $pdo->prepare("SELECT deadline_at, status FROM vacancies WHERE id = ?");
$vacancy->execute([$vacancyId]);
$v = $vacancy->fetch(PDO::FETCH_ASSOC);

if ($v['status'] !== 'approved') {
    http_response_code(400);
    echo json_encode(['error' => 'This vacancy is no longer accepting applications.']);
    exit;
}

if ($v['deadline_at'] && strtotime($v['deadline_at']) < time()) {
    http_response_code(400);
    echo json_encode(['error' => 'The application deadline for this vacancy has passed.']);
    exit;
}
```

### Frontend — countdown timer on vacancy cards

```javascript
function renderVacancyCard(vacancy) {
    const deadlineHtml = vacancy.deadline_at
        ? `<div class="mt-2 small text-warning fw-semibold" id="cd_${vacancy.id}"></div>`
        : '';

    // Start countdown after render
    if (vacancy.deadline_at) {
        setTimeout(() => startCountdown(`cd_${vacancy.id}`, vacancy.deadline_at), 0);
    }

    return `
        <div class="card h-100 vacancy-card">
            <!-- ...existing content... -->
            ${deadlineHtml}
        </div>`;
}

function startCountdown(elementId, deadlineStr) {
    const el = document.getElementById(elementId);
    if (!el) return;

    const deadline = new Date(deadlineStr).getTime();

    const tick = () => {
        const remaining = deadline - Date.now();
        if (remaining <= 0) {
            el.textContent = 'Deadline passed';
            el.classList.replace('text-warning', 'text-danger');
            return;
        }
        const d = Math.floor(remaining / 86400000);
        const h = Math.floor((remaining % 86400000) / 3600000);
        const m = Math.floor((remaining % 3600000) / 60000);
        el.textContent = `⏳ Closes in: ${d}d ${h}h ${m}m`;
    };

    tick();
    setInterval(tick, 60000);
}
```

---

## 8. Dark Mode Toggle

### What it does
A sun/moon toggle in the navbar switches the entire UI between light and dark themes. The preference is saved to `localStorage` and applied immediately on every page load before paint (no flash of unstyled content).

### Implementation — CSS variables in `assets/css/common.css`

```css
:root {
    --bg-primary:    #ffffff;
    --bg-secondary:  #f8f9fa;
    --text-primary:  #212529;
    --text-muted:    #6c757d;
    --border-color:  #dee2e6;
    --card-bg:       #ffffff;
    --navbar-bg:     #1e3a5f;
    --sidebar-bg:    #f8f9fa;
}

[data-theme="dark"] {
    --bg-primary:    #121212;
    --bg-secondary:  #1e1e1e;
    --text-primary:  #e9ecef;
    --text-muted:    #adb5bd;
    --border-color:  #2d2d2d;
    --card-bg:       #1e1e1e;
    --navbar-bg:     #0d1b2e;
    --sidebar-bg:    #1a1a1a;
}

body { background-color: var(--bg-primary); color: var(--text-primary); }
.card { background-color: var(--card-bg); border-color: var(--border-color); }
```

Apply the variables to every element that currently uses hardcoded Bootstrap colors. The key is replacing `bg-white`, `bg-light`, `text-dark` etc. with CSS variable references in your custom stylesheets.

### Implementation — `common.js`

```javascript
// Apply theme before paint — place this in a <script> in <head>, NOT deferred
(function () {
    const saved = localStorage.getItem('vuka_theme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
})();

// Toggle function — attach to the button onclick
function toggleDarkMode() {
    const current = document.documentElement.getAttribute('data-theme');
    const next    = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('vuka_theme', next);
    document.getElementById('themeIcon').className =
        next === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
}
```

### HTML — add to navbar

```html
<button class="btn btn-link text-white p-0 me-3" onclick="toggleDarkMode()"
        title="Toggle dark mode" aria-label="Toggle dark mode">
    <i id="themeIcon" class="fas fa-moon fs-5"></i>
</button>
```

---

## 9. Password Reset Flow

### What it does
Students and admins who forget their password click "Forgot password?" on the login page, enter their email, and receive a time-limited link. The link leads to a reset form. Tokens expire after 1 hour and are single-use.

### Database changes

```sql
CREATE TABLE password_reset_tokens (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(255)  NOT NULL,
    token      VARCHAR(64)   NOT NULL UNIQUE,
    account_type ENUM('student','admin') NOT NULL DEFAULT 'student',
    expires_at DATETIME      NOT NULL,
    used       TINYINT(1)    NOT NULL DEFAULT 0,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email)
);
```

### New API endpoints

| File | Method | Purpose | Auth |
|------|--------|---------|------|
| `api/forgot-password.php` | POST | Generate token, send email | Public |
| `api/reset-password.php` | POST | Validate token, update password | Public |

**`api/forgot-password.php`**:

```php
<?php
require_once '../config.php';
require_once '../lib/mailer.php';

$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid email address']);
    exit;
}

// Check both users and admin_users tables
$user  = $pdo->prepare("SELECT 'student' AS type FROM users WHERE email = ?");
$user->execute([$email]);
$found = $user->fetch();

if (!$found) {
    $user = $pdo->prepare("SELECT 'admin' AS type FROM admin_users WHERE email = ?");
    $user->execute([$email]);
    $found = $user->fetch();
}

// Always return 200 — never reveal whether an email exists
echo json_encode(['success' => true,
    'message' => 'If that email is registered, a reset link has been sent.']);

if (!$found) exit;

// Generate a secure token
$token     = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

$pdo->prepare("INSERT INTO password_reset_tokens
               (email, token, account_type, expires_at)
               VALUES (?, ?, ?, ?)")
    ->execute([$email, $token, $found['type'], $expiresAt]);

$resetUrl = APP_URL . "reset-password.php?token={$token}";
$html = "
    <p>You requested a password reset for your Vuka Portal account.</p>
    <p><a href='{$resetUrl}' style='color:#1e3a5f'>Reset my password</a></p>
    <p>This link expires in <strong>1 hour</strong>.</p>
    <p>If you did not request this, ignore this email.</p>";

sendMail($email, '', 'Reset Your Vuka Portal Password', $html);
```

**`api/reset-password.php`**:

```php
<?php
require_once '../config.php';

$token    = trim($_POST['token'] ?? '');
$password = $_POST['password'] ?? '';

if (strlen($password) < 8) {
    http_response_code(422);
    echo json_encode(['error' => 'Password must be at least 8 characters']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM password_reset_tokens
                        WHERE token = ? AND used = 0 AND expires_at > NOW()");
$stmt->execute([$token]);
$reset = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reset) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or expired reset link']);
    exit;
}

$hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
$table  = $reset['account_type'] === 'admin' ? 'admin_users' : 'users';

$pdo->prepare("UPDATE {$table} SET password = ? WHERE email = ?")
    ->execute([$hashed, $reset['email']]);

$pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE id = ?")
    ->execute([$reset['id']]);

echo json_encode(['success' => true, 'message' => 'Password updated. You can now log in.']);
```

### Frontend — `reset-password.php` page

A standalone page (same styling as `index.php`) with a form for the new password. On load, read the `token` from the URL query string and include it as a hidden field.

---

## 10. Advanced Analytics Dashboard

### What it does
Upgrades the existing Chart.js charts with richer KPIs: acceptance rate trend by month, average days-to-decision per department, department heatmap (applications vs capacity), and a funnel chart showing drop-off at each lifecycle stage. Adds exportable chart images.

### New API endpoint
**`api/get-analytics.php`** — returns multiple datasets in one call:

```php
<?php
require_once '../session-manager.php';
requireAnyAdmin();

$deptScope = ($session['role'] === 'department_supervisor')
    ? "AND v.department_id = {$session['department_id']}"
    : '';

// 1. Monthly applications for the past 12 months
$monthly = $pdo->query("
    SELECT DATE_FORMAT(s.created_at, '%Y-%m') AS month, COUNT(*) AS count
    FROM submissions s
    JOIN vacancies v ON v.id = s.vacancy_id
    WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) {$deptScope}
    GROUP BY month ORDER BY month")->fetchAll(PDO::FETCH_ASSOC);

// 2. Acceptance rate per department
$acceptance = $pdo->query("
    SELECT d.name AS department,
           COUNT(*) AS total,
           SUM(s.status IN ('accepted','deployed','ongoing','completed')) AS accepted
    FROM submissions s
    JOIN vacancies v ON v.id = s.vacancy_id
    JOIN departments d ON d.id = v.department_id
    WHERE 1=1 {$deptScope}
    GROUP BY d.name")->fetchAll(PDO::FETCH_ASSOC);

// 3. Average days-to-decision
$avgDays = $pdo->query("
    SELECT d.name AS department,
           ROUND(AVG(DATEDIFF(
               (SELECT MIN(rh.created_at) FROM review_history rh
                WHERE rh.submission_id = s.id
                AND rh.new_status IN ('accepted','rejected')),
               s.created_at
           ))) AS avg_days
    FROM submissions s
    JOIN vacancies v ON v.id = s.vacancy_id
    JOIN departments d ON d.id = v.department_id
    WHERE 1=1 {$deptScope}
    GROUP BY d.name")->fetchAll(PDO::FETCH_ASSOC);

// 4. Funnel — count per lifecycle stage
$funnel = $pdo->query("
    SELECT status, COUNT(*) AS count
    FROM submissions s
    JOIN vacancies v ON v.id = s.vacancy_id
    WHERE 1=1 {$deptScope}
    GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(compact('monthly', 'acceptance', 'avgDays', 'funnel'));
```

### Frontend — Chart.js additions in each dashboard JS file

```javascript
async function loadAdvancedAnalytics() {
    const data = await apiFetch('/api/get-analytics.php');

    // Acceptance rate bar chart
    new Chart(document.getElementById('acceptanceChart'), {
        type: 'bar',
        data: {
            labels: data.acceptance.map(r => r.department),
            datasets: [{
                label: 'Acceptance Rate (%)',
                data: data.acceptance.map(r =>
                    r.total > 0 ? ((r.accepted / r.total) * 100).toFixed(1) : 0),
                backgroundColor: 'rgba(30, 58, 95, 0.75)',
            }],
        },
        options: { responsive: true, plugins: { legend: { display: false } } },
    });

    // Avg days-to-decision horizontal bar
    new Chart(document.getElementById('avgDaysChart'), {
        type: 'bar',
        data: {
            labels: data.avgDays.map(r => r.department),
            datasets: [{
                label: 'Avg. Days to Decision',
                data: data.avgDays.map(r => r.avg_days || 0),
                backgroundColor: 'rgba(245, 124, 0, 0.75)',
            }],
        },
        options: { indexAxis: 'y', responsive: true },
    });
}

// Export chart as PNG
function exportChart(chartId, filename) {
    const canvas = document.getElementById(chartId);
    const link   = document.createElement('a');
    link.download = filename + '.png';
    link.href     = canvas.toDataURL('image/png');
    link.click();
}
```

---

## 11. Server-Side Pagination

### What it does
Replaces the current approach (fetch all rows, hide some with JS) with proper `LIMIT`/`OFFSET` SQL queries. The current page and page size are tracked in the URL so links are shareable and the back button works. Prevents browser performance issues as applicant counts grow.

### Backend — update `api/get-submissions.php`

```php
$page    = max(1, intval($_GET['page'] ?? 1));
$perPage = min(50, max(10, intval($_GET['per_page'] ?? 20)));
$offset  = ($page - 1) * $perPage;

// Count query (same WHERE clause, no LIMIT)
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM submissions s
                             JOIN vacancies v ON v.id = s.vacancy_id
                             WHERE 1=1 {$whereClause}");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

// Data query with pagination
$dataStmt = $pdo->prepare("SELECT ... FROM submissions s
                            JOIN vacancies v ON v.id = s.vacancy_id
                            WHERE 1=1 {$whereClause}
                            ORDER BY s.created_at DESC
                            LIMIT ? OFFSET ?");
$params[] = $perPage;
$params[] = $offset;
$dataStmt->execute($params);

echo json_encode([
    'data'        => $dataStmt->fetchAll(PDO::FETCH_ASSOC),
    'total'       => (int) $total,
    'page'        => $page,
    'per_page'    => $perPage,
    'total_pages' => (int) ceil($total / $perPage),
]);
```

### Frontend — reusable pagination renderer in `common.js`

```javascript
function renderPagination(containerId, meta, onPageChange) {
    const { page, total_pages } = meta;
    const el = document.getElementById(containerId);
    if (!el || total_pages <= 1) { if (el) el.innerHTML = ''; return; }

    let html = '<nav><ul class="pagination pagination-sm mb-0">';
    html += `<li class="page-item ${page === 1 ? 'disabled' : ''}">
                <button class="page-link" onclick="(${onPageChange})(${page - 1})">‹</button>
             </li>`;

    for (let p = 1; p <= total_pages; p++) {
        if (p === 1 || p === total_pages || Math.abs(p - page) <= 1) {
            html += `<li class="page-item ${p === page ? 'active' : ''}">
                        <button class="page-link" onclick="(${onPageChange})(${p})">${p}</button>
                     </li>`;
        } else if (Math.abs(p - page) === 2) {
            html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
    }

    html += `<li class="page-item ${page === total_pages ? 'disabled' : ''}">
                <button class="page-link" onclick="(${onPageChange})(${page + 1})">›</button>
             </li></ul></nav>`;
    el.innerHTML = html;
}
```

---

## 12. Application Withdrawal

### What it does
Students can retract their own application while it is still in `applied` or `pending` status. Once an admin has acted (accepted, rejected, deployed), withdrawal is blocked. A confirmation dialog prevents accidental clicks.

### New API endpoint
**`api/withdraw-application.php`**:

```php
<?php
require_once '../session-manager.php';
requireStudent();

$submissionId = intval($_POST['submission_id'] ?? 0);

// Verify ownership and withdrawable status
$stmt = $pdo->prepare("SELECT id, status FROM submissions
                        WHERE id = ? AND user_id = ?");
$stmt->execute([$submissionId, $session['user_id']]);
$sub = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sub) {
    http_response_code(404);
    echo json_encode(['error' => 'Application not found']);
    exit;
}

if (!in_array($sub['status'], ['applied', 'pending'])) {
    http_response_code(400);
    echo json_encode(['error' =>
        'This application can no longer be withdrawn — it has already been reviewed.']);
    exit;
}

$pdo->prepare("UPDATE submissions SET status = 'withdrawn' WHERE id = ?")
    ->execute([$submissionId]);

echo json_encode(['success' => true, 'message' => 'Application withdrawn successfully.']);
```

Update `submissions.status` ENUM to include `'withdrawn'`:

```sql
ALTER TABLE submissions
  MODIFY COLUMN status
    ENUM('applied','pending','interview','accepted','rejected',
         'deployed','ongoing','completed','withdrawn')
    NOT NULL DEFAULT 'applied';
```

### Frontend — in `assets/js/student.js`

```javascript
function renderWithdrawButton(submission) {
    if (!['applied', 'pending'].includes(submission.status)) return '';
    return `
        <button class="btn btn-outline-danger btn-sm mt-2"
                onclick="withdrawApplication(${submission.id})">
            <i class="fas fa-undo me-1"></i>Withdraw Application
        </button>`;
}

async function withdrawApplication(submissionId) {
    if (!confirm('Are you sure you want to withdraw this application? This cannot be undone.'))
        return;

    const body = new FormData();
    body.append('submission_id', submissionId);

    const res = await apiFetch('/api/withdraw-application.php', {
        method: 'POST', body,
    });

    if (res.success) {
        showToast(res.message, 'success');
        loadMyApplications(); // refresh the student's list
    } else {
        showToast(res.error || 'Failed to withdraw', 'danger');
    }
}
```

---

## 13. Login Rate Limiting

### What it does
Tracks failed login attempts per IP address in the database. After 5 failures within 15 minutes, that IP is locked out for 30 minutes. Protects all three login endpoints (`login.php`, `student-login.php`, `admin-login.php`).

### Database changes

```sql
CREATE TABLE login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip_address   VARCHAR(45)  NOT NULL,
    endpoint     VARCHAR(50)  NOT NULL,
    attempted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    success      TINYINT(1)   NOT NULL DEFAULT 0,
    INDEX idx_ip (ip_address, attempted_at)
);
```

### Helper function — add to `config.php` or a new `lib/rate-limiter.php`

```php
function checkRateLimit(PDO $pdo, string $endpoint): void {
    $ip      = $_SERVER['REMOTE_ADDR'];
    $window  = date('Y-m-d H:i:s', strtotime('-15 minutes'));
    $maxFail = 5;
    $lockout = 30; // minutes

    // Count recent failures
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts
                            WHERE ip_address = ? AND endpoint = ?
                            AND attempted_at > ? AND success = 0");
    $stmt->execute([$ip, $endpoint, $window]);
    $failures = (int) $stmt->fetchColumn();

    if ($failures >= $maxFail) {
        http_response_code(429);
        echo json_encode([
            'error' => "Too many failed attempts. Try again in {$lockout} minutes.",
            'retry_after' => $lockout * 60,
        ]);
        exit;
    }
}

function recordLoginAttempt(PDO $pdo, string $endpoint, bool $success): void {
    $ip = $_SERVER['REMOTE_ADDR'];
    $pdo->prepare("INSERT INTO login_attempts (ip_address, endpoint, success)
                   VALUES (?, ?, ?)")
        ->execute([$ip, $endpoint, $success ? 1 : 0]);
}

function clearLoginAttempts(PDO $pdo, string $endpoint): void {
    $ip = $_SERVER['REMOTE_ADDR'];
    $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND endpoint = ?")
        ->execute([$ip, $endpoint]);
}
```

### Integration — add to each login endpoint

```php
// At the TOP of student-login.php, admin-login.php, login.php
require_once '../lib/rate-limiter.php';
checkRateLimit($pdo, 'student-login');  // change per file

// On failed authentication:
recordLoginAttempt($pdo, 'student-login', false);
http_response_code(401);
echo json_encode(['error' => 'Invalid credentials']);
exit;

// On successful authentication (before returning the token):
clearLoginAttempts($pdo, 'student-login');
recordLoginAttempt($pdo, 'student-login', true);
```

### Cleanup — add to a cron job or lazy-clean on each request

```php
// Prune records older than 24 hours — prevents table bloat
$pdo->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
```

---

## 14. Print-Friendly View

### What it does
A print button on the applicant detail view (supervisor and HR dashboards) triggers `window.print()`. A `@media print` stylesheet hides navigation, sidebars, and action buttons, and formats the applicant's details cleanly for A4 printing or PDF export via the browser's "Save as PDF" option.

### Implementation — add to `assets/css/common.css`

```css
@media print {
    /* Hide everything except the print target */
    .navbar,
    .sidebar,
    .btn,
    .pagination,
    #bulkActionBar,
    #notifBellWrapper,
    .no-print {
        display: none !important;
    }

    /* Reset layout for print */
    body {
        background: #ffffff !important;
        color: #000000 !important;
        font-size: 12pt;
        margin: 0;
    }

    .print-target {
        display: block !important;
        width: 100%;
        max-width: 100%;
        padding: 0;
        margin: 0;
        box-shadow: none !important;
        border: none !important;
    }

    /* Page header for print */
    .print-header {
        display: flex !important;
        justify-content: space-between;
        border-bottom: 2px solid #000;
        padding-bottom: 8pt;
        margin-bottom: 16pt;
    }

    /* Force table borders to show in print */
    table, th, td {
        border: 1px solid #999 !important;
        border-collapse: collapse !important;
    }

    /* Page break control */
    .page-break-before { page-break-before: always; }
    .page-break-after  { page-break-after: always; }
}

/* Hidden by default, visible only in print */
.print-header { display: none; }
```

### HTML — print header and button

Add inside the applicant detail panel in each dashboard:

```html
<!-- Print-only header (hidden in browser, visible when printing) -->
<div class="print-header">
    <div>
        <strong>Vuka Attachment &amp; Internship Portal</strong><br>
        <small>Applicant Detail — Printed <span id="printDate"></span></small>
    </div>
    <div>Ref: VKP/<span id="printRef"></span></div>
</div>

<!-- Print button (hidden when printing) -->
<button class="btn btn-outline-secondary btn-sm no-print"
        onclick="printApplicantDetail()">
    <i class="fas fa-print me-1"></i>Print / Save as PDF
</button>
```

### JavaScript

```javascript
function printApplicantDetail() {
    // Inject dynamic values into the print header
    document.getElementById('printDate').textContent = new Date().toLocaleDateString('en-KE');
    document.getElementById('printRef').textContent  = currentSubmissionId;
    window.print();
}
```

---

## Implementation Order (Recommended)

Implement in this sequence to maximise value at each step and avoid rework:

| # | Feature | Why this order |
|---|---------|---------------|
| 1 | Login rate limiting | Security — do this before any public deployment |
| 2 | Password reset | Closes the biggest UX gap; needs PHPMailer set up |
| 3 | Email notifications | PHPMailer already configured; high perceived value |
| 4 | Dark mode toggle | Pure CSS/JS — zero backend, high visual impact for portfolio screenshots |
| 5 | Server-side pagination | Foundation for performance — do before analytics |
| 6 | Application withdrawal | Small, self-contained; rounds out the student experience |
| 7 | Bulk actions | Builds on existing update endpoint; HR will use this immediately |
| 8 | Vacancy deadlines | Small DB change, big workflow improvement |
| 9 | Notification bell | Reuses the mailer and notifyStudent helpers already built |
| 10 | PDF letter generation | Requires FPDF; high portfolio impression value |
| 11 | Advanced analytics | Builds on existing Chart.js setup |
| 12 | Interview scheduling | Largest feature; do it after smaller wins are stable |
| 13 | Performance evaluation | Depends on interview scheduling being stable |
| 14 | Print-friendly view | CSS-only; add last as a polish pass |
