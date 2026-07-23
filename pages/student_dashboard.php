<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>(function(){try{var m=document.cookie.match(/(?:^|;)\s*vuka_theme=([^;]*)/);var t=m?m[1]:'light';document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
    <title>Vuka — Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <link href="../assets/css/common.css" rel="stylesheet">
    <link href="../assets/css/student_dashboard.css" rel="stylesheet">
</head>
<body class="bg-light">
    <header class="official-header" style="padding: 0.85rem 0;">
    <div class="container d-flex align-items-center gap-3">
        <svg width="120" height="38" viewBox="0 0 140 44" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="36" height="36" rx="9" fill="#0F7A45" x="0" y="4"/>
            <path d="M9 14 L18 28 L27 14" stroke="#F4F7F5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
            <path d="M13 14 L18 22 L23 14" stroke="#C5401A" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
            <text x="46" y="29" font-family="'Plus Jakarta Sans', sans-serif" font-weight="600" font-size="24" fill="#F4F7F5" letter-spacing="-0.5">vuka</text>
        </svg>
        <div style="flex:1;"></div>
        <span style="color:rgba(255,255,255,0.7); font-size:0.85rem;" id="studentNameDisplay"></span>
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
        <div class="row">
            <!-- Sidebar / Profile Summary -->
            <div class="col-md-3 mb-4">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <div class="avatar-circle mb-3 mx-auto text-white d-flex align-items-center justify-content-center" style="width:80px; height:80px; border-radius:50%; font-size:2rem; background:#0B1E14;">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <h5 id="profileName">Student Name</h5>
                        <p class="text-muted small" id="profileId">ID: ...</p>
                        <hr>
                        <div class="text-start">
                             <p class="mb-1"><strong>National ID:</strong> <span id="profileNationalId">—</span></p>
                             <p class="mb-1"><strong>Institution:</strong> <span id="profileInstitution">—</span></p>
                             <p class="mb-1"><strong>Status:</strong> <span id="overallStatus" class="badge bg-secondary">Not Applied</span></p>
                             <p class="mb-1" id="pfNumberRow" style="display:none;"><strong>PF Number:</strong> <span id="profilePfNumber">—</span></p>
                        </div>
                        <hr>
                        <!-- Application Status Timeline -->
                        <div id="statusTimeline" style="display:none;">
                            <p class="mb-2 fw-bold" style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.5px; color: var(--c-slate);">Application Progress</p>
                            <div class="status-timeline">
                                <div class="step" id="step-applied">
                                    <div class="step-dot"></div>
                                    <div class="step-label">Applied</div>
                                </div>
                                <div class="step" id="step-review">
                                    <div class="step-dot"></div>
                                    <div class="step-label">Review</div>
                                </div>
                                <div class="step" id="step-decision">
                                    <div class="step-dot"></div>
                                    <div class="step-label">Decision</div>
                                </div>
                                <div class="step" id="step-deployed">
                                    <div class="step-dot"></div>
                                    <div class="step-label">Deployed</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <ul class="nav nav-tabs card-header-tabs" id="studentTabs" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active" id="opps-tab" data-bs-toggle="tab" data-bs-target="#opps-pane" type="button">
                                    <i class="fas fa-search me-2"></i>Available Opportunities
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-pane" type="button">
                                    <i class="fas fa-history me-2"></i>My Applications
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
                            <!-- Opportunities -->
                            <div class="tab-pane fade show active" id="opps-pane">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4 class="mb-0">Open Vacancies</h4>
                                </div>
                                <!-- Vacancy Search -->
                                <div class="input-group mb-3">
                                    <span class="input-group-text" style="background: var(--c-forest); color:#fff; border:none;">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" class="form-control" id="vacancySearch"
                                           placeholder="Search by department or title...">
                                </div>
                                <div id="vacanciesListContainer">
                                    <div class="skeleton skeleton-card mb-2"></div>
                                    <div class="skeleton skeleton-card mb-2"></div>
                                    <div class="skeleton skeleton-card"></div>
                                </div>
                            </div>

                            <!-- History -->
                            <div class="tab-pane fade" id="history-pane">
                                <h4 class="mb-3">Application History</h4>
                                <div id="applicationHistoryList">
                                    <div class="empty-state text-center py-5">
                                        <i class="fas fa-clipboard-list" style="font-size:3rem; color: var(--c-border);"></i>
                                        <h6 class="mt-3" style="color: var(--c-slate);">No applications yet</h6>
                                        <p class="small" style="color: var(--c-slate);">Browse the vacancies tab to apply for an attachment opportunity.</p>
                                    </div>
                                </div>
                                <div id="studentInterviewSection" class="mt-4"></div>
                                <div id="studentEvaluationSection" class="mt-4"></div>
                            </div>

                            <!-- Profile View -->
                            <?php include 'components/profile_view.php'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Apply Modal -->
    <div class="modal fade" id="applyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Apply for <span id="applyVacancyTitle">Position</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="applicationForm">
                        <input type="hidden" id="vacancyId">
                        
                        <!-- Auto-filled info -->
                        <div class="alert alert-info small">
                            Applying as: <strong id="applyAsName">...</strong> (ID: <span id="applyAsId">...</span>)
                        </div>

                        <!-- Details -->
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Course Pursuing</label>
                                <input type="text" class="form-control" id="courseName" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Institution Name</label>
                                <input type="text" class="form-control" id="institutionName" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Duration (Months)</label>
                                <input type="text" class="form-control" id="duration" placeholder="e.g. 3 Months" required>
                            </div>
                             <div class="col-md-6">
                                <label class="form-label">Insurance Cover No.</label>
                                <input type="text" class="form-control" id="insurance" required>
                            </div>
                        </div>
                        
                        <hr>
                        <h6>Attachments</h6>
                        <div class="mb-3">
                            <label class="form-label">Application Letter</label>
                            <input type="file" class="form-control" id="fileAppLetter" accept=".pdf, .jpg, .png" required>
                        </div>
                         <div class="mb-3">
                            <label class="form-label">School Intro Letter</label>
                            <input type="file" class="form-control" id="fileSchoolLetter" accept=".pdf, .jpg, .png" required>
                        </div>
                         <div class="mb-3">
                            <label class="form-label">Insurance Certificate</label>
                            <input type="file" class="form-control" id="fileInsurance" accept=".pdf, .jpg, .png" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">National ID Copy</label>
                            <input type="file" class="form-control" id="fileID" accept=".pdf, .jpg, .png" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mt-3">Submit Application</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/common.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/js/interviews.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/js/evaluations.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/js/profile.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/js/student_dashboard.js?v=<?php echo time(); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
             if(typeof initStudentDashboard === 'function') initStudentDashboard();
        });
    </script>
</body>
</html>
