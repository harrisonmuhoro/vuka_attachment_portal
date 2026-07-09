<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vuka — Attachment Portal</title>
    <meta name="description" content="Official Student Attachment Platform for the Vuka.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/@emailjs/browser@3/dist/index.min.js"></script>
    <link href="assets/css/common.css" rel="stylesheet">
    <link href="assets/css/index.css" rel="stylesheet">
</head>

<body>

    <!-- TOAST CONTAINER -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- LOADING OVERLAY -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text" id="loadingText">Processing...</div>
    </div>

    <!-- HEADER -->
    <header class="official-header" id="mainHeader">
        <div class="container d-flex justify-content-between align-items-center">
            <svg width="140" height="44" viewBox="0 0 140 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="36" height="36" rx="9" fill="#0F7A45" x="0" y="4"/>
                <path d="M9 14 L18 28 L27 14" stroke="#F4F7F5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                <path d="M13 14 L18 22 L23 14" stroke="#C5401A" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                <text x="46" y="29" font-family="'Plus Jakarta Sans', sans-serif" font-weight="600" font-size="24" fill="#F4F7F5" letter-spacing="-0.5">vuka</text>
            </svg>
        </div>
    </header>

    <!-- MAIN -->
    <main class="main-content">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 col-md-10">

                    <!-- ===== LOGIN VIEW ===== -->
                    <div id="login-view" class="view-section active">
                        <div class="card mx-auto" style="max-width: 600px;">
                            <div class="card-header text-center">
                                <h4 class="mb-0" style="font-family: var(--font-display); font-weight: 600;">Sign in to Vuka</h4>
                            </div>
                            <div class="card-body p-4">
                                <ul class="nav nav-tabs" id="authTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="login-tab" data-bs-toggle="tab"
                                            data-bs-target="#login-content" type="button" role="tab">
                                            <i class="fas fa-sign-in-alt me-1"></i>Login
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="register-tab" data-bs-toggle="tab"
                                            data-bs-target="#register-content" type="button" role="tab">
                                            <i class="fas fa-user-plus me-1"></i>Register
                                        </button>
                                    </li>
                                    <!-- Admin tab removed as per request -->
                                </ul>

                                <div class="tab-content" id="authTabsContent">
                                    <!-- LOGIN TAB -->
                                    <div class="tab-pane fade show active" id="login-content" role="tabpanel">
                                        <form id="loginForm" novalidate>
                                            <div class="mb-3">
                                                <label for="loginId" class="form-label">National ID / Registration
                                                    Number</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                                    <input type="text" class="form-control" id="loginId"
                                                        placeholder="6-8 digits only" required pattern="\d{6,8}"
                                                        minlength="6" maxlength="8" inputmode="numeric"
                                                        title="6 to 8 digits only">
                                                </div>
                                            </div>
                                            <div class="mb-4">
                                                <label for="loginPassword" class="form-label">Password</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                                    <div class="password-wrapper flex-grow-1">
                                                        <input type="password" class="form-control" id="loginPassword"
                                                            placeholder="Enter your password" required>
                                                        <button type="button" class="password-toggle"
                                                            data-target="loginPassword"><i
                                                                class="fas fa-eye"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="d-grid">
                                                <button type="submit" class="btn btn-primary btn-lg">
                                                    <i class="fas fa-sign-in-alt me-2"></i>Login to Portal
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- REGISTER TAB -->
                                    <div class="tab-pane fade" id="register-content" role="tabpanel">
                                        <form id="registrationForm" novalidate>
                                            <div class="mb-3">
                                                <label for="regFullName" class="form-label mandatory-field">Full
                                                    Name</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                                    <input type="text" class="form-control" id="regFullName"
                                                        placeholder="Enter your full name" required>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="regIdNumber" class="form-label mandatory-field">National ID
                                                    Number</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                                    <input type="text" class="form-control" id="regIdNumber"
                                                        placeholder="6-8 digits only" required pattern="\d{6,8}"
                                                        minlength="6" maxlength="8" inputmode="numeric"
                                                        title="6 to 8 digits only">
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="regEmail" class="form-label mandatory-field">Email
                                                    Address</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i
                                                            class="fas fa-envelope"></i></span>
                                                    <input type="email" class="form-control" id="regEmail"
                                                        placeholder="your@gmail.com" required>
                                                </div>
                                                <small class="form-text text-muted mt-1">Allowed: @gmail.com,
                                                    @outlook.com, @hotmail.com, @yahoo.com, @ymail.com, @aol.com,
                                                    @icloud.com</small>
                                            </div>
                                            <div class="mb-3">
                                                <label for="regLoginDate"
                                                    class="form-label mandatory-field">Registration Date</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i
                                                            class="fas fa-calendar"></i></span>
                                                    <input type="date" class="form-control" id="regLoginDate" required>
                                                </div>
                                            </div>
                                            <div class="mb-4">
                                                <label for="regPassword" class="form-label mandatory-field">Create
                                                    Password</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                                    <div class="password-wrapper flex-grow-1">
                                                        <input type="password" class="form-control" id="regPassword"
                                                            placeholder="Min. 6 characters" required>
                                                        <button type="button" class="password-toggle"
                                                            data-target="regPassword"><i
                                                                class="fas fa-eye"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mb-4">
                                                <label for="regConfirmPassword" class="form-label mandatory-field">Confirm
                                                    Password</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                                    <div class="password-wrapper flex-grow-1">
                                                        <input type="password" class="form-control"
                                                            id="regConfirmPassword" placeholder="Confirm your password"
                                                            required>
                                                        <button type="button" class="password-toggle"
                                                            data-target="regConfirmPassword"><i
                                                                class="fas fa-eye"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="d-grid">
                                                <button type="submit" class="btn btn-primary btn-lg">
                                                    <i class="fas fa-user-plus me-2"></i>Complete Registration
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- ADMIN TAB -->
                                    <!-- Admin content removed -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== DASHBOARD VIEW ===== -->
                    <div id="dashboard-view" class="view-section">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h4 class="mb-0"><i class="fas fa-file-alt me-2"></i>Attachee Application Form</h4>
                                <span class="status-badge status-pending"><i class="fas fa-edit"></i> Draft</span>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info border-0 shadow-sm">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Instructions:</strong> Fill in all mandatory fields <span
                                        style="color:#dc3545;font-weight:700;">*</span>. Documents: PDF, JPG, PNG (max
                                    2MB).
                                </div>

                                <form id="uploadForm">
                                    <div class="mb-4">
                                        <h5 class="section-title"><i class="fas fa-user"></i>Applicant Information</h5>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Full Name</label>
                                                <input type="text" id="displayFullName" class="form-control" readonly>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">National ID Number</label>
                                                <input type="text" id="displayId" class="form-control" readonly>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Email Address</label>
                                                <input type="email" id="displayEmail" class="form-control" readonly>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Registration Date</label>
                                                <input type="text" id="displayRegDate" class="form-control" readonly>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <h5 class="section-title"><i class="fas fa-briefcase"></i>Attachment Details
                                        </h5>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label for="attachmentDuration"
                                                    class="form-label mandatory-field">Duration</label>
                                                <select class="form-control" id="attachmentDuration" required>
                                                    <option value="">-- Select Duration --</option>
                                                    <option value="3 months">3 Months</option>
                                                    <option value="6 months">6 Months</option>
                                                    <option value="9 months">9 Months</option>
                                                    <option value="1 year">1 Year</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="insuranceCover" class="form-label mandatory-field">Insurance
                                                    Cover</label>
                                                <select class="form-control" id="insuranceCover" required>
                                                    <option value="">-- Select --</option>
                                                    <option value="yes">Yes - Insurance Included</option>
                                                    <option value="no">No - Self Insured</option>
                                                    <option value="other">Other Arrangement</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="courseApplying" class="form-label mandatory-field">Ongoing
                                                    Course</label>
                                                <select class="form-control" id="courseApplying" required>
                                                    <option value="">-- Select Course --</option>
                                                    <option value="Information Technology">Information Technology
                                                    </option>
                                                    <option value="Engineering">Engineering</option>
                                                    <option value="Business Administration">Business Administration
                                                    </option>
                                                    <option value="Law">Law</option>
                                                    <option value="Healthcare">Healthcare Administration</option>
                                                    <option value="Other">Other</option>
                                                </select>
                                                <input type="text" id="courseOtherInput" class="form-control mt-2"
                                                    placeholder="Type your course" style="display:none;">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="institutionName"
                                                    class="form-label mandatory-field">Campus/Institution</label>
                                                <select class="form-control" id="institutionName" required>
                                                    <option value="">-- Select Institution --</option>
                                                    <option value="Nyeri Medical Training College">Nyeri Medical
                                                        Training College</option>
                                                    <option value="Nyeri Technical Training Institute">Nyeri Technical
                                                        Training Institute</option>
                                                    <option value="Dedan Kimathi University of Technology">Dedan Kimathi
                                                        University</option>
                                                    <option value="Kenyatta University">Kenyatta University</option>
                                                    <option value="Kenya Methodist University">Kenya Methodist
                                                        University</option>
                                                    <option value="Mount Kenya University">Mount Kenya University
                                                    </option>
                                                    <option value="Kenya Polytechnic Union">Kenya Polytechnic Union
                                                    </option>
                                                    <option value="Other">Other (Specify)</option>
                                                </select>
                                                <input type="text" id="institutionOtherInput" class="form-control mt-2"
                                                    placeholder="Type institution name" style="display:none;">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="departmentApplying"
                                                    class="form-label mandatory-field">Department</label>
                                                <select class="form-control" id="departmentApplying" required>
                                                    <option value="">-- Select Department --</option>
                                                    <option value="ICT">ICT</option>
                                                    <option value="Health">Health</option>
                                                    <option value="Procurement">Procurement</option>
                                                    <option value="Finance">Finance</option>
                                                    <option value="HR">HR</option>
                                                    <option value="Education">Education</option>
                                                    <option value="Agriculture">Agriculture</option>
                                                    <option value="Water">Water</option>
                                                    <option value="Transport">Transport</option>
                                                    <option value="Other">Other</option>
                                                </select>
                                                <input type="text" id="departmentOtherInput" class="form-control mt-2"
                                                    placeholder="Specify department" style="display:none;">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <h5 class="section-title"><i class="fas fa-file-upload"></i>Mandatory Documents
                                        </h5>
                                        <div class="mb-3">
                                            <label for="fileApplicationLetter"
                                                class="form-label mandatory-field">Application Letter</label>
                                            <input type="file" class="form-control" id="fileApplicationLetter" required
                                                accept=".pdf,.jpg,.jpeg,.png">
                                        </div>
                                        <div class="mb-3">
                                            <label for="fileCampusLetter" class="form-label mandatory-field">Campus
                                                Letter</label>
                                            <input type="file" class="form-control" id="fileCampusLetter" required
                                                accept=".pdf,.jpg,.jpeg,.png">
                                        </div>
                                        <div class="mb-3">
                                            <label for="fileInsuranceCert" class="form-label mandatory-field">Insurance
                                                Certificate</label>
                                            <input type="file" class="form-control" id="fileInsuranceCert" required
                                                accept=".pdf,.jpg,.jpeg,.png">
                                        </div>
                                        <div class="mb-3">
                                            <label for="fileAcademic" class="form-label mandatory-field">Academic
                                                Certificates</label>
                                            <input type="file" class="form-control" id="fileAcademic" required
                                                accept=".pdf,.jpg,.jpeg,.png">
                                        </div>
                                        <div class="mb-3">
                                            <label for="fileId" class="form-label mandatory-field">National ID
                                                Copy</label>
                                            <input type="file" class="form-control" id="fileId" required
                                                accept=".pdf,.jpg,.jpeg,.png">
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="termsCheckbox" required>
                                            <label class="form-check-label" for="termsCheckbox">
                                                I confirm all information is accurate and documents are genuine. False
                                                information may result in disqualification.
                                            </label>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end gap-3 mt-4 flex-wrap">
                                        <button type="button" class="btn btn-outline-secondary" id="btnLogout">
                                            <i class="fas fa-sign-out-alt me-2"></i>Cancel & Logout
                                        </button>
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-paper-plane me-2"></i>Review & Submit
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- ===== BLOCKED VIEW ===== -->
                    <div id="blocked-view" class="view-section">
                        <div class="card mx-auto text-center p-5" style="max-width:600px;">
                            <div class="mb-4 text-danger"><i class="fas fa-exclamation-triangle fa-4x"></i></div>
                            <h3 class="fw-bold">Access Denied</h3>
                            <p class="lead">You have already submitted your application.</p>
                            <p class="text-muted">
                                Documents for National ID <strong id="blockedIdDisplay"></strong> have been finalized.
                                <br><br>Contact the <strong>County Public Service Board</strong> for assistance.
                            </p>
                            <hr>
                            <button onclick="location.reload()" class="btn btn-outline-primary mt-3">Return to
                                Login</button>
                        </div>
                    </div>

                    <!-- ===== SUCCESS VIEW ===== -->
                    <div id="success-view" class="view-section">
                        <div class="card mx-auto text-center p-5"
                            style="max-width:600px;border-top-color:var(--county-light-green);">
                            <div class="mb-4 text-success"><i class="fas fa-check-circle fa-4x"></i></div>
                            <h3 class="fw-bold">Application Submitted Successfully</h3>
                            <p class="lead">Your documents have been received by the Vuka.</p>
                            <p class="text-muted">You have been automatically logged out.</p>
                            <button onclick="location.reload()" class="btn btn-primary mt-3">Return to Home</button>
                        </div>
                    </div>

                    <!-- ===== ADMIN VIEW (Department Admin) ===== -->
                    <div id="admin-view" class="view-section">
                        <div class="card">
                            <div class="admin-header">
                                <div>
                                    <h4 class="mb-1"><i class="fas fa-shield-alt me-2"></i><span
                                            id="adminPanelTitle">Admin Control Panel</span></h4>
                                    <p class="mb-0 small opacity-75">PF: <span id="adminPFDisplay"></span> &nbsp;|&nbsp;
                                        <span id="adminNameDisplay"></span> &nbsp;|&nbsp; Dept: <span
                                            id="adminDeptDisplay"></span>
                                    </p>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="admin-badge" id="adminRoleBadge">ADMIN</span>
                                    <button type="button" class="btn btn-light btn-sm" id="adminLogout">
                                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                                    </button>
                                </div>
                            </div>

                            <div class="card-body">
                                <!-- Senior Admin Navigation (only visible for super_admin) -->
                                <div class="senior-admin-nav" id="seniorAdminNav" style="display:none;">
                                    <button class="nav-btn active" onclick="switchAdminTab('submissions')">
                                        <i class="fas fa-file-alt me-1"></i>Submissions
                                    </button>
                                    <button class="nav-btn" onclick="switchAdminTab('manage-admins')">
                                        <i class="fas fa-users-cog me-1"></i>Manage Admins
                                    </button>
                                    <button class="nav-btn" onclick="switchAdminTab('create-admin')">
                                        <i class="fas fa-user-plus me-1"></i>Create Admin
                                    </button>
                                </div>

                                <!-- === SUBMISSIONS TAB === -->
                                <div id="admin-tab-submissions">
                                    <div class="row mb-4 g-3">
                                        <div class="col-md-3 col-6">
                                            <div class="card stat-card stat-total" onclick="filterByStatus('all')">
                                                <div class="card-body text-center">
                                                    <h3 class="text-success" id="totalSubmissions">0</h3>
                                                    <p class="text-muted mb-0">Total</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <div class="card stat-card stat-pending"
                                                onclick="filterByStatus('pending_review')">
                                                <div class="card-body text-center">
                                                    <h3 class="text-warning" id="pendingCount">0</h3>
                                                    <p class="text-muted mb-0">Pending</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <div class="card stat-card stat-approved"
                                                onclick="filterByStatus('approved')">
                                                <div class="card-body text-center">
                                                    <h3 class="text-info" id="approvedCount">0</h3>
                                                    <p class="text-muted mb-0">Approved</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <div class="card stat-card stat-rejected"
                                                onclick="filterByStatus('rejected')">
                                                <div class="card-body text-center">
                                                    <h3 class="text-danger" id="rejectedCount">0</h3>
                                                    <p class="text-muted mb-0">Rejected</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-3 g-2">
                                        <div class="col-md-6">
                                            <input type="text" class="form-control" id="searchSubmissions"
                                                placeholder="Search by name, ID or email...">
                                        </div>
                                        <div class="col-md-6">
                                            <select class="form-control" id="filterStatus">
                                                <option value="">All Submissions</option>
                                                <option value="pending_review">Pending Review</option>
                                                <option value="approved">Approved</option>
                                                <option value="rejected">Rejected</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div id="submissionsList">
                                        <div class="no-submissions">
                                            <i class="fas fa-inbox d-block"></i>
                                            <p class="mt-2">No submissions yet</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- === MANAGE ADMINS TAB (Senior Admin only) === -->
                                <div id="admin-tab-manage-admins" style="display:none;">
                                    <h5 class="section-title"><i class="fas fa-users-cog"></i>Department Admin Accounts
                                    </h5>
                                    <div class="alert alert-info mb-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Each department can have a <strong>maximum of 2 admin accounts</strong>.
                                    </div>
                                    <div id="adminAccountsList">
                                        <div class="text-center text-muted py-4">
                                            <div class="loading-spinner mx-auto"></div>
                                            <p class="mt-2">Loading admin accounts...</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- === CREATE ADMIN TAB (Senior Admin only) === -->
                                <div id="admin-tab-create-admin" style="display:none;">
                                    <h5 class="section-title"><i class="fas fa-user-plus"></i>Create New Department
                                        Admin</h5>
                                    <div class="alert alert-warning mb-3">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Max 2 admins per department.</strong> Departments at capacity will be
                                        marked.
                                    </div>
                                    <form id="createAdminForm" novalidate>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label for="newAdminFullName" class="form-label mandatory-field">Full
                                                    Name</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                                    <input type="text" class="form-control" id="newAdminFullName"
                                                        placeholder="Jane Doe" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="newAdminId" class="form-label mandatory-field">National ID</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                                    <input type="text" class="form-control" id="newAdminId"
                                                        placeholder="Enter National ID" required pattern="\d{6,9}">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="newAdminPF" class="form-label mandatory-field">PF
                                                    Number</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                                    <input type="text" class="form-control" id="newAdminPF"
                                                        placeholder="e.g. ICT/ADM/001" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="newAdminEmail" class="form-label mandatory-field">Email
                                                    Address</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i
                                                            class="fas fa-envelope"></i></span>
                                                    <input type="email" class="form-control" id="newAdminEmail"
                                                        placeholder="admin@vuka.go.ke" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="newAdminDept"
                                                    class="form-label mandatory-field">Department</label>
                                                <select class="form-control" id="newAdminDept" required>
                                                    <option value="">-- Select Department --</option>
                                                    <option value="ICT">ICT</option>
                                                    <option value="Health">Health</option>
                                                    <option value="Procurement">Procurement</option>
                                                    <option value="Finance">Finance</option>
                                                    <option value="HR">HR</option>
                                                    <option value="Education">Education</option>
                                                    <option value="Agriculture">Agriculture</option>
                                                    <option value="Water">Water</option>
                                                    <option value="Transport">Transport</option>
                                                </select>
                                                <small class="form-text text-muted" id="deptCapacityHint"></small>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="newAdminPassword" class="form-label mandatory-field">Initial
                                                    Password</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                                    <div class="password-wrapper flex-grow-1">
                                                        <input type="password" class="form-control"
                                                            id="newAdminPassword" placeholder="Min. 6 characters"
                                                            required>
                                                        <button type="button" class="password-toggle"
                                                            data-target="newAdminPassword"><i
                                                                class="fas fa-eye"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-4 d-flex justify-content-end gap-2">
                                            <button type="reset" class="btn btn-outline-secondary">
                                                <i class="fas fa-undo me-1"></i>Reset
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-user-plus me-2"></i>Create Admin Account
                                            </button>
                                        </div>
                                    </form>
                                </div>

                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </main>

    <!-- FOOTER -->
    <footer>
        <div class="container">
            <p>&copy; 2026 <strong>Vuka. Built by Harrison Muhoro </strong>. All Rights Reserved.</p>
            <p class="mb-0">Designed for Efficiency & Transparency</p>
        </div>
    </footer>

    <!-- ===== MODALS ===== -->

    <!-- Confirm Submit -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-check-circle text-success me-2"></i>Review & Confirm</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-6"><strong>Full Name:</strong><br><span id="confirmFullName"></span></div>
                        <div class="col-6"><strong>National ID:</strong><br><span id="confirmId"></span></div>
                        <div class="col-6"><strong>Email:</strong><br><span id="confirmEmail"></span></div>
                        <div class="col-6"><strong>Reg. Date:</strong><br><span id="confirmRegDate"></span></div>
                        <div class="col-6"><strong>Duration:</strong><br><span id="confirmDuration"></span></div>
                        <div class="col-6"><strong>Insurance:</strong><br><span id="confirmInsurance"></span></div>
                        <div class="col-6"><strong>Course:</strong><br><span id="confirmCourse"></span></div>
                        <div class="col-6"><strong>Institution:</strong><br><span id="confirmInstitution"></span></div>
                        <div class="col-6"><strong>Department:</strong><br><span id="confirmDepartment"></span></div>
                    </div>
                    <div class="alert alert-warning mt-3 mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Once submitted, you <strong>cannot</strong> edit your application.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Go Back</button>
                    <button type="button" class="btn btn-primary" id="confirmSubmitBtn">
                        <i class="fas fa-paper-plane me-2"></i>Confirm & Submit
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Email Verification -->
    <div class="modal fade" id="emailVerifyModal" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-envelope-open-text text-primary me-2"></i>Email
                        Verification</h5>
                </div>
                <div class="modal-body text-center">
                    <p>Enter the 6-digit verification code sent to <strong id="verifyEmailDisplay"></strong></p>
                    <input type="text" class="form-control verify-code-input mx-auto mb-3" id="verifyCodeInput"
                        maxlength="6" placeholder="000000" style="max-width:220px;">
                    <div id="verifyFeedback" class="text-danger small mb-2" style="display:none;"></div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" id="verifyCodeBtn">
                            <i class="fas fa-check me-2"></i>Verify Code
                        </button>
                    </div>
                    <p class="mt-3 small text-muted">
                        Didn't receive it? <a href="#" id="resendVerifyLink">Resend Code</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Submission Details -->
    <div class="modal fade" id="submissionDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-contract me-2"></i>Submission: <span
                            id="modalApplicantName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 class="section-title"><i class="fas fa-user"></i>Applicant Info</h6>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><small class="text-muted">Full Name</small><br><strong
                                id="modalFullName"></strong></div>
                        <div class="col-6"><small class="text-muted">National ID</small><br><strong
                                id="modalIdNumber"></strong></div>
                        <div class="col-6"><small class="text-muted">Email</small><br><strong id="modalEmail"></strong>
                        </div>
                        <div class="col-6"><small class="text-muted">Submitted</small><br><strong
                                id="modalRegDate"></strong></div>
                    </div>

                    <h6 class="section-title"><i class="fas fa-briefcase"></i>Attachment Details</h6>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><small class="text-muted">Duration</small><br><strong
                                id="modalDuration"></strong></div>
                        <div class="col-6"><small class="text-muted">Insurance</small><br><strong
                                id="modalInsurance"></strong></div>
                        <div class="col-6"><small class="text-muted">Course</small><br><strong
                                id="modalCourse"></strong></div>
                        <div class="col-6"><small class="text-muted">Institution</small><br><strong
                                id="modalInstitution"></strong></div>
                        <div class="col-6"><small class="text-muted">Department</small><br><strong
                                id="modalDepartment"></strong></div>
                        <div class="col-6"><small class="text-muted">Submit Date</small><br><strong
                                id="modalSubmitDate"></strong></div>
                    </div>

                    <h6 class="section-title"><i class="fas fa-file-alt"></i>Documents</h6>
                    <div id="modalDocuments" class="mb-3">
                        <p class="text-muted">No documents</p>
                    </div>

                    <h6 class="section-title"><i class="fas fa-tasks"></i>Status Management</h6>
                    <div class="row g-2 mb-3">
                        <div class="col-md-8">
                            <select class="form-control" id="statusDropdown">
                                <option value="pending_review">Pending Review</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-primary w-100" id="updateStatusBtn">
                                <i class="fas fa-save me-1"></i>Update
                            </button>
                        </div>
                    </div>

                    <div id="rejectionReasonContainer" style="display:none;" class="mb-3">
                        <label class="form-label">Rejection Reason</label>
                        <textarea class="form-control" id="rejectionReason" rows="2"
                            placeholder="Explain rejection reason..."></textarea>
                    </div>
                    <div id="displayRejectionReason" style="display:none;" class="alert alert-danger mb-3">
                        <strong>Rejection Reason:</strong> <span id="rejectionReasonText"></span>
                    </div>

                    <h6 class="section-title"><i class="fas fa-sticky-note"></i>Review Notes</h6>
                    <div class="mb-2">
                        <textarea class="form-control" id="reviewNotes" rows="2"
                            placeholder="Add review notes..."></textarea>
                    </div>
                    <button class="btn btn-sm btn-outline-primary mb-3" id="saveReviewNotesBtn">
                        <i class="fas fa-save me-1"></i>Save Notes
                    </button>

                    <div id="reviewHistoryContainer" style="display:none;">
                        <h6 class="section-title"><i class="fas fa-history"></i>Review History</h6>
                        <div id="reviewHistoryList"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-danger btn-sm" id="deleteSubmissionBtn">
                        <i class="fas fa-trash me-1"></i>Delete
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Document Preview -->
    <div class="modal fade" id="docPreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Document Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <iframe id="docPreviewIframe" style="display:none;"></iframe>
                    <img id="docPreviewImg" style="display:none;" alt="Document Preview">
                    <p id="docPreviewFallback" style="display:none;" class="text-muted py-5">Preview not available</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" id="docPreviewDownloadBtn">
                        <i class="fas fa-download me-1"></i>Download
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/common.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/index.js?v=<?php echo time(); ?>"></script>
</body>

</html>