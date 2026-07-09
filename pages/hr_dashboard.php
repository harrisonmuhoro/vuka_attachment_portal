<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vuka — HR Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <link href="../assets/css/common.css" rel="stylesheet">
    <link href="../assets/css/hr_dashboard.css" rel="stylesheet">
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
        <span style="color:rgba(255,255,255,0.7); font-size:0.85rem;" id="hrNameDisplay"></span>
        <button class="btn btn-sm" style="background: var(--c-clay); color: #fff; border: none;" id="logoutBtn">
            <i class="fas fa-sign-out-alt me-1"></i>Logout
        </button>
    </div>
</header>
    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title text-muted" style="font-size:0.78rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            Pending Vacancies
                        </h5>
                        <h2 class="display-4" id="statPendingVacancies">0</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title text-muted" style="font-size:0.78rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            Open Positions
                        </h5>
                        <h2 class="display-4" id="statOpenPositions">0</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title text-muted" style="font-size:0.78rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            Total Applicants
                        </h5>
                        <h2 class="display-4" id="statTotalApplicants">0</h2>
                    </div>
                </div>
            </div>
             <div class="col-md-3">
                <div class="card text-center shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title text-muted" style="font-size:0.78rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            Placed Students
                        </h5>
                        <h2 class="display-4" id="statPlaced">0</h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <ul class="nav nav-tabs card-header-tabs" id="hrTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="vals-tab" data-bs-toggle="tab" data-bs-target="#vals-pane" type="button">
                            <i class="fas fa-check-circle me-2"></i>Vacancy Approvals
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="all-apps-tab" data-bs-toggle="tab" data-bs-target="#all-apps-pane" type="button">
                            <i class="fas fa-users me-2"></i>All Applications
                        </button>
                    </li>
                     <li class="nav-item">
                        <button class="nav-link" id="placements-tab" data-bs-toggle="tab" data-bs-target="#placements-pane" type="button">
                            <i class="fas fa-certificate me-2"></i>Placements
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- Vacancy Approvals -->
                    <div class="tab-pane fade show active" id="vals-pane">
                        <h4>Pending Vacancy Requests</h4>
                        <div id="pendingVacanciesList" class="table-responsive">
                            <p class="text-center text-muted">Loading requests...</p>
                        </div>
                    </div>

                    <!-- All Applications -->
                    <div class="tab-pane fade" id="all-apps-pane">
                        <div class="d-flex justify-content-between mb-3">
                            <h4>Master Applicant List</h4>
                            <input type="text" class="form-control w-auto" id="searchApplicant" placeholder="Search...">
                        </div>
                        <div id="allApplicantsList" class="table-responsive">
                            <p class="text-center text-muted">Loading applicants...</p>
                        </div>
                    </div>

                    <!-- Placements -->
                    <div class="tab-pane fade" id="placements-pane">
                        <h4>Placement Management</h4>
                        <p class="text-muted">Generate offer letters for selected candidates.</p>
                        <div id="placementsList" class="table-responsive">
                            <p class="text-center text-muted">Loading placements...</p>
                        </div>
                    </div>
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

    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/common.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/js/hr_dashboard.js?v=<?php echo time(); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
             if(typeof initHrDashboard === 'function') initHrDashboard();
        });
    </script>
</body>
</html>
