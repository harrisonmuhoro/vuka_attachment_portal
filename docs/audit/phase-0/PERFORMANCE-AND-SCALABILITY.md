# Performance and Scalability Assessment

**Audit Scope:** Database queries, indexing strategies, memory consumption, file storage architecture, and concurrency constraints at commit `83aa34f`.

---

## 1. Database Query Efficiency & Index Utilization

### A. Missing Composite Indexes on High-Traffic Filters
The application frequently queries the `submissions` table using multi-column filters and ordering:
- **Supervisor Dashboard:** Filter by `department_applied` + `status` + order by `submitted_at DESC`.
- **HR Dashboard:** Filter by `status` + order by `submitted_at DESC`.
- **Analytics:** Filter by date window `submitted_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)` + `department_applied`.

**Audit Finding:**
While individual single-column indexes exist (`idx_submissions_dept`, `idx_submissions_status`, `idx_submissions_user`), MySQL can only utilize one index per table reference effectively for filtering and sorting. Without composite indexes, queries perform index merges or full index scans followed by filesort operations.

**Recommended Composite Indexes:**
- `CREATE INDEX idx_sub_dept_status_date ON submissions (department_applied, status, submitted_at);`
- `CREATE INDEX idx_sub_status_date ON submissions (status, submitted_at);`
- `CREATE INDEX idx_sub_user_status ON submissions (user_id, status);`

### B. Unpaginated Default Queries
In `api/get-submissions.php`, server-side pagination is optional and only executes when an explicit `?page=` parameter is provided:
```php
$paginate = isset($_GET['page']);
if (!$paginate) {
    // Fetches ALL rows for the department or entire database in a single query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $submissions = $stmt->fetchAll();
}
```
If an institution receives 2,500 applications across its departments, an admin loading the dashboard without the `page` query parameter fetches all 2,500 records with joined tables in a single HTTP response, consuming substantial PHP memory and delaying page load times.

### C. Redundant Document Subqueries
In `api/get-submissions.php`:
`SELECT s.*, COUNT(d.id) as document_count FROM submissions s LEFT JOIN documents d ON s.id = d.submission_id GROUP BY s.id`
Grouping the entire submissions table against the documents table on every listing request forces MySQL to aggregate foreign rows before returning page results. Storing a cached `document_count` counter column on `submissions` would eliminate this join overhead entirely.

---

## 2. Storage & Memory Impact of Database BLOBs

The system stores all uploaded applicant documents and profile photos directly inside MySQL as `LONGBLOB`s:
- Supporting Documents: Application Letter, Campus Letter, Insurance Certificate, Academic Certificates, National ID Copy (up to 2 MB each).
- Profile Photos: Student and Admin profile photos (up to 2 MB each).
- Maximum potential BLOB storage per applicant: **12 MB**.

### Architectural Trade-offs of the Current Design:
* **Advantages:**
  * Avoids managing filesystem permissions and Apache directory protections.
  * Ensures transactional consistency during submission creation (documents roll back automatically if submission fails).
  * Simplifies local single-node development on WAMP/XAMPP.
* **Risks & Scalability Bottlenecks:**
  1. **InnoDB Buffer Pool Contention:** Repeatedly streaming large BLOBs through `download-document.php` and `serve-profile-photo.php` flushes relational query caches out of the InnoDB buffer pool.
  2. **Backup & Restore Bloat:** A database containing 1,000 complete student applications will reach **~8 to 12 GB** in table size. Logical backups (`mysqldump`) become slow, lock tables for extended periods, and create massive `.sql` dump files.
  3. **`max_allowed_packet` Dependency:** Submitting multiple 2 MB files simultaneously requires high MySQL packet limits (`max_allowed_packet=64M`). Default installations (16 MB) fail silently during multi-file uploads.
  4. **PHP Memory Exhaustion:** Using `file_get_contents()` and PDO blob parameter binding loads the entire file binary into PHP process memory twice per upload.

---

## 3. Concurrency, Atomicity & Race Conditions

### A. Admin Limit Enforcement Race Condition
In `api/create-admin-account.php`:
```php
$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM admin_users WHERE department = ? AND status != 'deleted'");
$stmt->execute([$department]);
$count = $stmt->fetch()['cnt'];
if ($count >= 2) {
    json_response(false, null, "Department already has maximum of 2 admin accounts");
}
// INSERT executed subsequently without table lock or transaction
```
Two Super Admins creating an account for the same department concurrently can both pass the `COUNT < 2` check simultaneously, inserting 3 or more administrators and violating the institutional business rule.
* **Remediation:** Enforce the constraint in a transaction with `SELECT ... FOR UPDATE`, or introduce a unique compound index where applicable.

### B. Single Application Enforcement
In `api/submit-application.php`:
The check `SELECT id FROM submissions WHERE user_id = ? LIMIT 1` is not backed by a unique constraint on `submissions(user_id)`. Rapid double-clicking on the frontend submit button can create duplicate applications.
* **Remediation:** Add a unique index: `ALTER TABLE submissions ADD UNIQUE KEY uq_submission_user (user_id);`.

---

## 4. Scalability Threshold Assessment

| Scale Metric | 100 Users (Current Pilot) | 1,000 Users | 5,000 Users | 10,000 Users |
|---|---|---|---|---|
| **Database Size** | ~150 MB | ~1.2 GB | ~6 GB | ~15 GB+ |
| **Query Performance** | Fast (< 30ms) | Noticeable lag on unpaginated lists (~200ms) | Slow table scans without composite indexes (~1.5s) | Severe query queueing & timeout risks |
| **Backup Window** | Seconds | 2–5 minutes | 15–30 minutes | Extended locks; requires replica or hot physical backup |
| **Concurrent Logins** | Seamless | Minor lock contention on `sessions` | Rate-limiter table bloat; needs automated index cleanup | High connection pool contention |
| **Email Delivery** | Synchronous OK | Occasional page freeze during SMTP timeout | Web request timeouts; requires background worker | Mandatory asynchronous queue (RabbitMQ / Redis / Cron worker) |
| **Architecture Suitability** | Fully Supported | Supported with mandatory pagination | Strained; BLOB offloading to S3/MinIO recommended | Incompatible with current single-node monolithic state |

---

## 5. Strategic Performance Recommendations

1. **Mandatory Server-Side Pagination (Phase 2):** Enforce `LIMIT` and `OFFSET` on all submission and audit log queries by default, capping `per_page` at 50 rows.
2. **Add Composite Indexes (Phase 2):** Create compound indexes for department scoping and status filters.
3. **Automate Rate-Limit Pruning (Phase 1):** Schedule a recurring cleanup for `login_attempts` older than 24 hours to prevent table bloat.
4. **Transition to Asynchronous Email Delivery (Phase 6):** Move email dispatch to a database outbox table processed by a background worker script, decoupling page load times from external SMTP latency.
5. **Mid-Term BLOB Offloading (Phase 10 / Post-MVP):** Evaluate migrating document BLOBs to private, S3-compatible local object storage (e.g. MinIO) when active applications exceed 2,500, storing only cryptographic hashes and secure object keys in MySQL.
