<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>(function(){try{var m=document.cookie.match(/(?:^|;)\s*vuka_theme=([^;]*)/);var t=m?m[1]:'light';document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
    <title>Vuka — Supervisor Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <link href="../assets/css/common.css" rel="stylesheet">
    <link href="../assets/css/supervisor_dashboard.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">
    <!-- Header -->
    <header class="official-header" style="padding: 0.85rem 0;">
    <div class="container d-flex align-items-center gap-3">
        <svg width="120" height="38" viewBox="0 0 140 44" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="36" height="36" rx="9" fill="#0F7A45" x="0" y="4"/>
            <path d="M9 14 L18 28 L27 14" stroke="#F4F7F5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
            <path d="M13 14 L18 22 L23 14" stroke="#C5401A" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
            <text x="46" y="29" font-family="'Plus Jakarta Sans', sans-serif" font-weight="600" font-size="24" fill="#F4F7F5" letter-spacing="-0.5">vuka</text>
        </svg>
        <div style="flex:1;"></div>
        <div class="text-end me-2">
            <div style="color:#fff; font-size:0.9rem; font-weight:600; line-height:1.1;" id="supervisorNameDisplay"></div>
            <div style="color:rgba(255,255,255,0.7); font-size:0.75rem;" id="supervisorDeptDisplay"></div>
        </div>
        <button type="button" class="btn btn-link p-0 me-3 theme-toggle-btn" onclick="toggleDarkMode()" title="Toggle dark mode" aria-label="Toggle dark mode">
            <i class="theme-toggle-icon fas fa-moon" style="font-size:1.1rem;"></i>
        </button>
        <div class="position-relative me-3" id="notifBellWrapper">
            <button type="button" class="btn btn-link p-0" id="notifBell" onclick="toggleNotifDropdown()" style="color:#fff; text-decoration:none;" title="Notifications">
                <i class="fas fa-bell" style="font-size:1.15rem;"></i>
                <span id="notifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" style="font-size:0.6rem;">0</span>
            </button>
            <div id="notifDropdown" class="d-none shadow bg-white rounded" style="position:absolute; right:0; top:130%; width:320px; max-height:420px; overflow-y:auto; z-index:1080; border:1px solid #e0e0e0;">
                <div class="px-3 py-2 border-bottom fw-semibold small text-dark">Notifications</div>
                <div id="notifList"></div>
            </div>
        </div>
        <button class="btn btn-sm" style="background: var(--c-clay); color: #fff; border: none;" id="logoutBtn">
            <i class="fas fa-sign-out-alt me-1"></i>Logout
        </button>
    </div>
</header>

    <div class="container mt-4">
        <!-- Dashboard Stats -->
        <div class="row mb-4 g-3">
            <div class="col-md-4">
                <div class="card stat-card info text-center">
                    <div class="card-body">
                        <p class="text-muted mb-1" style="font-size:0.78rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">My Vacancies</p>
                        <h2 class="fw-bold mb-0" id="statVacancies">0</h2>
                        <small class="text-muted">Total posted</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card warning text-center">
                    <div class="card-body">
                        <p class="text-muted mb-1" style="font-size:0.78rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">Pending Applications</p>
                        <h2 class="fw-bold mb-0" id="statPending">0</h2>
                        <small class="text-muted">Awaiting review</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <p class="text-muted mb-1" style="font-size:0.78rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">Selected Candidates</p>
                        <h2 class="fw-bold mb-0" id="statSelected">0</h2>
                        <small class="text-muted">Accepted & deployed</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Supervisor Analytics Chart -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="fw-bold" style="color: var(--c-green);"><i class="fas fa-chart-line me-2"></i>Applications Over Time</span>
                        <button onclick="exportCSV('supervisorApplicantsTable', 'supervisor-applicants-export.csv')" class="btn btn-sm" style="background: var(--c-forest); color:#fff; border:none;">
                            <i class="fas fa-download me-1"></i>Export CSV
                        </button>
                    </div>
                    <div class="card-body" style="height: 220px;">
                        <canvas id="supervisorChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <ul class="nav nav-tabs card-header-tabs" id="supervisorTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="vacancies-tab" data-bs-toggle="tab" data-bs-target="#vacancies-pane" type="button">
                            <i class="fas fa-briefcase me-2"></i>My Vacancies
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="applicants-tab" data-bs-toggle="tab" data-bs-target="#applicants-pane" type="button">
                            <i class="fas fa-users me-2"></i>Applicants
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="interviews-tab" data-bs-toggle="tab" data-bs-target="#interviews-pane" type="button" onclick="if(typeof loadSupervisorInterviews==='function') loadSupervisorInterviews()">
                            <i class="fas fa-calendar-check me-2"></i>Interviews
                        </button>
                    </li>
                    <li class="nav-item ms-auto">
                        <button class="nav-link" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profileView" type="button" onclick="if(typeof loadProfile==='function') loadProfile()">
                            <i class="fas fa-user-cog me-2"></i>My Profile
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- Vacancies Pane -->
                    <div class="tab-pane fade show active" id="vacancies-pane">
                        <div class="d-flex justify-content-between mb-3">
                            <h4>Department Vacancies</h4>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createVacancyModal">
                                <i class="fas fa-plus me-2"></i>Request New Vacancy
                            </button>
                        </div>
                        <div id="vacanciesList" class="table-responsive">
                            <div class="skeleton skeleton-card mb-2"></div>
                            <div class="skeleton skeleton-card mb-2"></div>
                            <div class="skeleton skeleton-card"></div>
                        </div>
                    </div>

                    <!-- Applicants Pane -->
                    <div class="tab-pane fade" id="applicants-pane">
                        <div class="row mb-3 g-2">
                            <div class="col-md-5">
                                <h4>Applications for My Department</h4>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="filterApplicantType">
                                    <option value="all">All Types</option>
                                    <option value="attachment">Attachments</option>
                                    <option value="internship">Internships</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" id="filterApplicantStatus">
                                    <option value="all">All Statuses</option>
                                    <option value="applied">Applied</option>
                                    <option value="pending">Pending</option>
                                    <option value="interview">Interview</option>
                                    <option value="accepted">Accepted</option>
                                    <option value="deployed">Deployed</option>
                                    <option value="ongoing">Ongoing</option>
                                    <option value="completed">Completed</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                        </div>
                        <div id="applicantsList" class="table-responsive">
                            <div class="skeleton skeleton-card mb-2"></div>
                            <div class="skeleton skeleton-card mb-2"></div>
                            <div class="skeleton skeleton-card"></div>
                        </div>
                        <div id="supervisorPaginationContainer" class="mt-3"></div>
                    </div>

                    <!-- Interviews Pane (#3) -->
                    <div class="tab-pane fade" id="interviews-pane">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="mb-0"><i class="fas fa-calendar-check me-2 text-primary"></i>Interview Schedule</h4>
                            <button class="btn btn-sm btn-outline-primary" onclick="if(typeof loadSupervisorInterviews==='function') loadSupervisorInterviews()">
                                <i class="fas fa-sync-alt me-1"></i>Refresh
                            </button>
                        </div>
                        <div id="interviewsCalendar">
                            <div class="skeleton skeleton-card mb-2"></div>
                            <div class="skeleton skeleton-card"></div>
                        </div>
                    </div>

                    <!-- Profile View -->
                    <?php include 'components/profile_view.php'; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Vacancy Modal -->
    <div class="modal fade" id="createVacancyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request New Vacancy</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="createVacancyForm">
                        <div class="mb-3">
                            <label class="form-label">Position Title</label>
                            <input type="text" class="form-control" id="vacancyTitle" required placeholder="e.g. ICT Intern">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="vacancyDesc" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Skills Required</label>
                            <input type="text" class="form-control" id="vacancySkills" placeholder="e.g. Networking, Java">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Number of Positions</label>
                            <input type="number" class="form-control" id="vacancyCount" value="1" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select class="form-select" id="vacancyType" required>
                                <option value="attachment">Attachment</option>
                                <option value="internship">Internship</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Application Deadline <span class="text-muted small">(optional)</span></label>
                            <input type="datetime-local" class="form-control" id="vacancyDeadline">
                            <small class="text-muted">Leave blank for no deadline. The vacancy auto-closes after this time.</small>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Submit Request</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Assign Role/Station Modal -->
    <div class="modal fade" id="assignModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Role & Station</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="assignForm">
                        <input type="hidden" id="assignSubmissionId">
                        <div class="alert alert-info small">
                            Assigning: <strong id="assignApplicantName">...</strong>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assigned Role / Job Description</label>
                            <input type="text" class="form-control" id="assignRole" placeholder="e.g. IT Support Assistant" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Workstation / Office</label>
                            <input type="text" class="form-control" id="assignStation" placeholder="e.g. ICT Office, Block B" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-user-check me-2"></i>Deploy & Assign
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Applicant Detail Modal -->
    <div class="modal fade" id="applicantDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Applicant Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="applicantDetailBody">
                    <p class="text-center text-muted">Loading...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/common.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/js/interviews.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/js/evaluations.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/js/profile.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/js/supervisor_dashboard.js?v=<?php echo time(); ?>"></script>
    <script>
        // Init logic for Supervisor Dashboard
        document.addEventListener('DOMContentLoaded', () => {
             // Check auth logic here or let app.js handle it
             if(typeof initSupervisorDashboard === 'function') initSupervisorDashboard();
        });
    </script>
</body>
</html>
