<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vuka — System Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <link href="../assets/css/common.css" rel="stylesheet">
    <link href="../assets/css/admin_dashboard.css" rel="stylesheet">
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
        <!-- Dashboard Stats -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title text-muted">Users</h5>
                        <h2 class="display-4" id="statUsers">Top</h2>
                    </div>
                </div>
            </div>
             <div class="col-md-4">
                <div class="card text-center shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title text-muted">Admins</h5>
                        <h2 class="display-4" id="statAdmins">Manage</h2>
                    </div>
                </div>
            </div>
             <div class="col-md-4">
                <div class="card text-center shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title text-muted">System</h5>
                        <h2 class="display-4"><i class="fas fa-cogs"></i></h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <ul class="nav nav-tabs card-header-tabs" id="adminTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="staff-tab" data-bs-toggle="tab" data-bs-target="#staff-pane" type="button">
                            <i class="fas fa-user-tie me-2"></i>Staff Management
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="students-tab" data-bs-toggle="tab" data-bs-target="#students-pane" type="button">
                            <i class="fas fa-user-graduate me-2"></i>Student Management
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="create-tab" data-bs-toggle="tab" data-bs-target="#create-pane" type="button">
                            <i class="fas fa-user-plus me-2"></i>Create Staff
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- Staff Management -->
                    <div class="tab-pane fade show active" id="staff-pane">
                        <h4>Manage Staff Accounts</h4>
                        <div id="adminAccountsList">
                            <p class="text-center text-muted">Loading accounts...</p>
                        </div>
                    </div>

                    <!-- Student Management -->
                    <div class="tab-pane fade" id="students-pane">
                        <h4>Manage Student Accounts</h4>
                        <div id="studentAccountsList">
                            <p class="text-center text-muted">Loading accounts...</p>
                        </div>
                    </div>

                    <!-- Create Staff -->
                    <div class="tab-pane fade" id="create-pane">
                         <h4 class="mb-3">Create Staff Account</h4>
                         <p class="text-muted mb-4">Use this form to create accounts for <strong>HR Coordinators</strong> and <strong>Department Supervisors</strong>.</p>
                         <form id="createAdminForm" class="p-3 border rounded bg-light">
                             <div class="row g-3">
                                 <div class="col-md-6">
                                     <label class="form-label">Full Name</label>
                                     <input type="text" class="form-control" id="newAdminFullName" required>
                                 </div>
                                  <div class="col-md-6">
                                     <label class="form-label">National ID</label>
                                     <input type="text" class="form-control" id="newAdminId" required pattern="\d{6,9}">
                                 </div>
                                  <div class="col-md-6">
                                     <label class="form-label">PF Number</label>
                                     <input type="text" class="form-control" id="newAdminPF" required>
                                 </div>
                                 <div class="col-md-6">
                                     <label class="form-label">Email</label>
                                     <input type="email" class="form-control" id="newAdminEmail" required>
                                 </div>
                                 <div class="col-md-6">
                                     <label class="form-label">Department</label>
                                     <select class="form-select" id="newAdminDept" required>
                                         <option value="">Select Department...</option>
                                         <option value="ICT">ICT</option>
                                         <option value="Health">Health</option>
                                         <option value="Finance">Finance</option>
                                         <option value="HR">Human Resources</option>
                                         <!-- Add more as needed -->
                                     </select>
                                 </div>
                                  <div class="col-md-6">
                                     <label class="form-label">Role</label>
                                     <select class="form-select" id="newAdminRole" required>
                                         <option value="3">Department Supervisor</option>
                                         <option value="2">HR Coordinator</option>
                                     </select>
                                 </div>
                                 <div class="col-md-6">
                                     <label class="form-label">Password</label>
                                     <input type="password" class="form-control" id="newAdminPassword" required>
                                 </div>
                                 <div class="col-12 mt-3">
                                     <button type="submit" class="btn btn-primary">Create Account</button>
                                 </div>
                             </div>
                         </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/common.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/js/admin_dashboard.js?v=<?php echo time(); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
             if(typeof initAdminDashboard === 'function') initAdminDashboard();
        });
    </script>
</body>
</html>
