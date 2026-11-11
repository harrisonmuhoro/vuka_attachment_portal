<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>(function(){try{var m=document.cookie.match(/(?:^|;)\s*vuka_theme=([^;]*)/);var t=m?m[1]:'light';document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
    <title>Vuka — Attachment & Internship Portal | Official Placement Platform</title>
    <meta name="description" content="Official Public Service Attachment and Internship Management Platform for university and tertiary students. Apply online, submit credentials, track reviews, and get deployed.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/common.css" rel="stylesheet">
    <link href="assets/css/index.css" rel="stylesheet">
</head>

<body>

    <!-- TOAST NOTIFICATION CONTAINER -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- LOADING OVERLAY -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text" id="loadingText">Processing...</div>
    </div>

    <!-- 1. OFFICIAL TOP UTILITY BAR -->
    <div class="gov-top-bar">
        <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <span><i class="fas fa-landmark me-1 text-warning"></i> County Government Public Service Board</span>
                <span class="d-none d-md-inline opacity-50">|</span>
                <span class="d-none d-md-inline"><i class="fas fa-shield-alt me-1 text-success"></i> Official Attachment & Internship Portal</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <a href="#requirements"><i class="fas fa-file-check me-1"></i> Requirements</a>
                <span class="opacity-50">|</span>
                <a href="#faq"><i class="fas fa-question-circle me-1"></i> Help & FAQs</a>
                <span class="opacity-50">|</span>
                <button type="button" class="btn btn-sm text-white p-0 border-0" onclick="toggleDarkMode()" title="Toggle dark/light mode" aria-label="Toggle dark mode">
                    <i class="theme-toggle-icon fas fa-moon ms-1"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- 2. MAIN STICKY NAVIGATION -->
    <nav class="site-navbar navbar navbar-expand-lg">
        <div class="container">
            <a class="brand-crest" href="index.php">
                <svg width="130" height="40" viewBox="0 0 140 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="36" height="36" rx="9" fill="#0F7A45" x="0" y="4"/>
                    <path d="M9 14 L18 28 L27 14" stroke="#F4F7F5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    <path d="M13 14 L18 22 L23 14" stroke="#C5401A" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    <text x="46" y="29" font-family="'Plus Jakarta Sans', sans-serif" font-weight="700" font-size="24" fill="var(--c-ink)" letter-spacing="-0.5">vuka</text>
                </svg>
                <span class="badge-gov d-none d-sm-inline">Portal</span>
            </a>

            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="#overview">Overview</a></li>
                    <li class="nav-item"><a class="nav-link" href="#vacancies">Vacancies</a></li>
                    <li class="nav-item"><a class="nav-link" href="#how-it-works">How It Works</a></li>
                    <li class="nav-item"><a class="nav-link" href="#requirements">Requirements</a></li>
                    <li class="nav-item"><a class="nav-link" href="#departments">Departments</a></li>
                    <li class="nav-item"><a class="nav-link" href="#faq">FAQs</a></li>
                </ul>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button type="button" class="btn btn-outline-success btn-sm px-3" onclick="openAuthModal('login')">
                        <i class="fas fa-sign-in-alt me-1"></i> Sign In
                    </button>
                    <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="openAuthModal('register')">
                        <i class="fas fa-user-plus me-1"></i> Register / Apply
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- 3. HERO SECTION -->
    <header class="hero-section" id="overview">
        <div class="container">
            <div class="row align-items-center gy-5">
                <div class="col-lg-7">
                    <div class="hero-badge">
                        <i class="fas fa-certificate text-success"></i>
                        <span>County Public Service Board Placement Platform</span>
                    </div>
                    <h1 class="hero-title">
                        Empowering Future Talent Through <span class="text-accent">Practical Industry Experience</span>
                    </h1>
                    <p class="hero-subtitle">
                        Vuka is the centralized public service portal for student industrial attachments and graduate internships. Apply online, submit certified credentials, track departmental reviews in real time, and receive formal deployment notices.
                    </p>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <a href="#vacancies" class="btn btn-primary btn-lg px-4 shadow-sm">
                            <i class="fas fa-briefcase me-2"></i> Browse Vacancies
                        </a>
                        <button type="button" class="btn btn-outline-success btn-lg px-4" onclick="openAuthModal('register')">
                            <i class="fas fa-user-graduate me-2"></i> Student Portal
                        </button>
                        <button type="button" class="btn btn-link text-decoration-none px-2" onclick="openAuthModal('admin')" style="color: var(--c-slate); font-weight: 500;">
                            <i class="fas fa-user-shield me-1"></i> Staff / Supervisor Sign In &rarr;
                        </button>
                    </div>

                    <!-- Hero Stats Cards -->
                    <div class="hero-stats-row row g-3">
                        <div class="col-6 col-md-3">
                            <div class="hero-stat-card text-center">
                                <div class="hero-stat-num">9</div>
                                <div class="hero-stat-label">Departments</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="hero-stat-card text-center">
                                <div class="hero-stat-num">100%</div>
                                <div class="hero-stat-label">Paperless</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="hero-stat-card text-center">
                                <div class="hero-stat-num">4-Tier</div>
                                <div class="hero-stat-label">Transparent RBAC</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="hero-stat-card text-center">
                                <div class="hero-stat-num">Instant</div>
                                <div class="hero-stat-label">Status Tracking</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <!-- Quick Portal Access Card -->
                    <div class="card border-0 shadow-lg p-3" style="border-radius: var(--radius-xl); background: var(--county-white);">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h5 class="mb-0 fw-bold" style="font-family: var(--font-display);">Applicant Portal Quick Login</h5>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Secure 256-bit</span>
                            </div>
                            <p class="small text-muted mb-3">Sign in with your National ID to check application status, schedule interviews, or view deployment letters.</p>

                            <form id="heroQuickLoginForm" onsubmit="handleHeroQuickLogin(event)">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">National ID / Registration No.</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-id-card text-muted"></i></span>
                                        <input type="text" class="form-control" id="heroLoginId" placeholder="6 to 8 digits only" required pattern="\d{6,8}" minlength="6" maxlength="8">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                                        <input type="password" class="form-control" id="heroLoginPassword" placeholder="Enter password" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 mb-2">
                                    <i class="fas fa-sign-in-alt me-2"></i> Log In to Student Dashboard
                                </button>
                                <div class="d-flex justify-content-between align-items-center mt-2 small">
                                    <a href="#" onclick="openForgotPasswordModal(event)" class="text-decoration-none text-muted">Forgot password?</a>
                                    <a href="#" onclick="openAuthModal('register'); return false;" class="text-success fw-semibold text-decoration-none">New student? Register</a>
                                </div>
                            </form>

                            <hr class="my-3 opacity-15">

                            <div class="d-flex align-items-center justify-content-between text-muted small">
                                <span><i class="fas fa-user-tie me-1"></i> Department Supervisor / HR?</span>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openAuthModal('admin')">Staff Login</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- 4. LIVE VACANCIES SECTION -->
    <section class="py-5" id="vacancies">
        <div class="container py-3">
            <div class="text-center mb-4">
                <span class="section-tag"><i class="fas fa-bullhorn me-1"></i> Current Openings</span>
                <h2 class="section-heading">Attachment & Internship Vacancies</h2>
                <p class="section-lead">Explore approved openings across county departments. Filter by your field of study, check deadlines, and submit your application online.</p>
            </div>

            <!-- Search & Filter Controls -->
            <div class="filter-search-box">
                <div class="row g-3 align-items-center">
                    <div class="col-md-6 col-lg-5">
                        <div class="input-group">
                            <span class="input-group-text border-end-0 bg-transparent"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" class="form-control border-start-0" id="vacancySearchInput" placeholder="Search by title, skills, or keywords..." onkeyup="filterVacancies()">
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-7">
                        <div class="d-flex gap-2 flex-wrap justify-content-md-end">
                            <button type="button" class="filter-chip-btn active" data-filter="all" onclick="filterByDept('all', this)">All Departments</button>
                            <button type="button" class="filter-chip-btn" data-filter="ICT" onclick="filterByDept('ICT', this)">ICT</button>
                            <button type="button" class="filter-chip-btn" data-filter="Health" onclick="filterByDept('Health', this)">Health</button>
                            <button type="button" class="filter-chip-btn" data-filter="Procurement" onclick="filterByDept('Procurement', this)">Procurement</button>
                            <button type="button" class="filter-chip-btn" data-filter="Finance" onclick="filterByDept('Finance', this)">Finance</button>
                            <button type="button" class="filter-chip-btn" data-filter="HR" onclick="filterByDept('HR', this)">HR</button>
                            <button type="button" class="filter-chip-btn" data-filter="Agriculture" onclick="filterByDept('Agriculture', this)">Agriculture</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vacancies Dynamic Grid Container -->
            <div class="row g-4" id="vacanciesGrid">
                <!-- Initial high-quality vacancy cards populated via live API call / fallback -->
                <div class="col-md-6 col-lg-4 vacancy-item" data-dept="ICT" data-type="attachment">
                    <div class="vacancy-card">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="vacancy-dept-badge">ICT & Innovation</span>
                                <span class="vacancy-type-badge">Attachment</span>
                            </div>
                            <h4 class="vacancy-title">Software Systems & Network Infrastructure Attachee</h4>
                            <p class="vacancy-desc">Assist county IT officers in local network diagnostics, portal maintenance, server administration, user support, and digitizing municipal records.</p>
                            <div class="mb-3">
                                <span class="skill-tag">Web Development</span>
                                <span class="skill-tag">Networking (LAN/WAN)</span>
                                <span class="skill-tag">Database Support</span>
                            </div>
                        </div>
                        <div>
                            <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                <span class="deadline-pill"><i class="fas fa-user-friends text-success"></i> 5 Positions</span>
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="applyToVacancy('ICT', 'Software Systems & Network Infrastructure Attachee')">Apply Now &rarr;</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 vacancy-item" data-dept="Health" data-type="attachment">
                    <div class="vacancy-card">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="vacancy-dept-badge">Health Services</span>
                                <span class="vacancy-type-badge">Attachment</span>
                            </div>
                            <h4 class="vacancy-title">Health Records & Clinical Administration Attachee</h4>
                            <p class="vacancy-desc">Support patient health informatics, outpatient registration, medical records archiving, and health facility logistics within the Level 4/5 County Hospital.</p>
                            <div class="mb-3">
                                <span class="skill-tag">Health Records</span>
                                <span class="skill-tag">Data Management</span>
                                <span class="skill-tag">Confidentiality</span>
                            </div>
                        </div>
                        <div>
                            <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                <span class="deadline-pill"><i class="fas fa-user-friends text-success"></i> 8 Positions</span>
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="applyToVacancy('Health', 'Health Records & Clinical Administration Attachee')">Apply Now &rarr;</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 vacancy-item" data-dept="Finance" data-type="internship">
                    <div class="vacancy-card">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="vacancy-dept-badge">Finance & Planning</span>
                                <span class="vacancy-type-badge">Internship</span>
                            </div>
                            <h4 class="vacancy-title">Revenue Accounting & Internal Audit Graduate Intern</h4>
                            <p class="vacancy-desc">One-year post-graduate internship supporting sub-county revenue reconciliation, IFMIS voucher audit, financial statement compilation, and asset ledger tracking.</p>
                            <div class="mb-3">
                                <span class="skill-tag">Accounting / CPA</span>
                                <span class="skill-tag">IFMIS Knowledge</span>
                                <span class="skill-tag">Excel Financials</span>
                            </div>
                        </div>
                        <div>
                            <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                <span class="deadline-pill"><i class="fas fa-user-friends text-success"></i> 4 Positions</span>
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="applyToVacancy('Finance', 'Revenue Accounting & Internal Audit Graduate Intern')">Apply Now &rarr;</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 vacancy-item" data-dept="Procurement" data-type="attachment">
                    <div class="vacancy-card">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="vacancy-dept-badge">Procurement</span>
                                <span class="vacancy-type-badge">Attachment</span>
                            </div>
                            <h4 class="vacancy-title">Supply Chain Management & Tendering Attachee</h4>
                            <p class="vacancy-desc">Learn and contribute to public procurement procedures, tender document preparation, inventory stores audits, supplier prequalification tracking, and purchase order processing.</p>
                            <div class="mb-3">
                                <span class="skill-tag">Supply Chain</span>
                                <span class="skill-tag">Storekeeping</span>
                                <span class="skill-tag">Public Procurement</span>
                            </div>
                        </div>
                        <div>
                            <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                <span class="deadline-pill"><i class="fas fa-user-friends text-success"></i> 3 Positions</span>
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="applyToVacancy('Procurement', 'Supply Chain Management & Tendering Attachee')">Apply Now &rarr;</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 vacancy-item" data-dept="Agriculture" data-type="attachment">
                    <div class="vacancy-card">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="vacancy-dept-badge">Agriculture</span>
                                <span class="vacancy-type-badge">Attachment</span>
                            </div>
                            <h4 class="vacancy-title">Agribusiness Extension & Veterinary Services Attachee</h4>
                            <p class="vacancy-desc">Engage with field agricultural officers in farmer training, crop disease surveillance, livestock vaccination drives, and data recording for subsidized fertilizer distribution.</p>
                            <div class="mb-3">
                                <span class="skill-tag">Agronomy</span>
                                <span class="skill-tag">Animal Health</span>
                                <span class="skill-tag">Community Extension</span>
                            </div>
                        </div>
                        <div>
                            <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                <span class="deadline-pill"><i class="fas fa-user-friends text-success"></i> 6 Positions</span>
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="applyToVacancy('Agriculture', 'Agribusiness Extension & Veterinary Services Attachee')">Apply Now &rarr;</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 vacancy-item" data-dept="HR" data-type="internship">
                    <div class="vacancy-card">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="vacancy-dept-badge">Human Resource</span>
                                <span class="vacancy-type-badge">Internship</span>
                            </div>
                            <h4 class="vacancy-title">Public Service HR Development & Staff Welfare Intern</h4>
                            <p class="vacancy-desc">Participate in staff record digitization, attendance tracking, medical scheme liaison, intern induction coordination, and processing training leave requisitions.</p>
                            <div class="mb-3">
                                <span class="skill-tag">Human Resources</span>
                                <span class="skill-tag">Records Keeping</span>
                                <span class="skill-tag">Staff Engagement</span>
                            </div>
                        </div>
                        <div>
                            <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                <span class="deadline-pill"><i class="fas fa-user-friends text-success"></i> 2 Positions</span>
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="applyToVacancy('HR', 'Public Service HR Development & Staff Welfare Intern')">Apply Now &rarr;</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty Search State -->
            <div id="noVacanciesAlert" class="text-center py-5 d-none">
                <i class="fas fa-search fa-3x text-muted mb-3 opacity-50"></i>
                <h5>No matching vacancies found</h5>
                <p class="text-muted small">Try adjusting your search terms or selecting a different department filter.</p>
            </div>
        </div>
    </section>

    <!-- 5. HOW IT WORKS / PLACEMENT LIFECYCLE -->
    <section class="py-5 bg-white border-top border-bottom" id="how-it-works">
        <div class="container py-3">
            <div class="text-center mb-5">
                <span class="section-tag"><i class="fas fa-stream me-1"></i> Streamlined Workflow</span>
                <h2 class="section-heading">How the Placement Process Works</h2>
                <p class="section-lead">From self-registration to workstation assignment, our transparent 4-step process ensures merit-based, efficient handling of every applicant.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="step-card">
                        <span class="step-num">01</span>
                        <div class="step-icon-wrap"><i class="fas fa-user-check"></i></div>
                        <h4 class="step-title">Register & Verify</h4>
                        <p class="step-text">Sign up with your National ID and institutional email. Confirm your account using the 6-digit verification code sent to your inbox.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="step-card">
                        <span class="step-num">02</span>
                        <div class="step-icon-wrap"><i class="fas fa-file-signature"></i></div>
                        <h4 class="step-title">Select & Apply</h4>
                        <p class="step-text">Browse approved department openings. Fill in your course duration, ongoing institution, and targeted attachment department.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="step-card">
                        <span class="step-num">03</span>
                        <div class="step-icon-wrap"><i class="fas fa-cloud-upload-alt"></i></div>
                        <h4 class="step-title">Upload Documents</h4>
                        <p class="step-text">Attach mandatory verification documents (Introduction letter, ID copy, insurance certificate) stored directly in encrypted DB storage.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="step-card">
                        <span class="step-num">04</span>
                        <div class="step-icon-wrap"><i class="fas fa-briefcase"></i></div>
                        <h4 class="step-title">Review & Deploy</h4>
                        <p class="step-text">Department supervisors review credentials, schedule interviews if required, and deploy accepted candidates with official placement letters.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 6. MANDATORY REQUIREMENTS SECTION -->
    <section class="py-5" id="requirements">
        <div class="container py-3">
            <div class="row align-items-center gy-5">
                <div class="col-lg-5">
                    <span class="section-tag"><i class="fas fa-clipboard-check me-1"></i> Check Your Eligibility</span>
                    <h2 class="section-heading">Mandatory Application Requirements</h2>
                    <p class="section-lead text-start mb-4">
                        To maintain compliance with Public Service Board standards, all applicants must submit genuine and verified documentation. Ensure your documents are in PDF, JPG, or PNG format and under 2 MB each.
                    </p>
                    <div class="p-3 border rounded-3 bg-light-subtle mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <i class="fas fa-info-circle fa-2x text-success"></i>
                            <div class="small">
                                <strong>Need assistance?</strong> Reach out to your university placement directorate or the County Public Service Board help desk.
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="openAuthModal('register')">
                        <i class="fas fa-arrow-right me-2"></i> Start Application Now
                    </button>
                </div>

                <div class="col-lg-7">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="req-item-card">
                                <div class="req-icon"><i class="fas fa-id-card"></i></div>
                                <div>
                                    <h5 class="mb-1 fw-bold fs-6">National Identity Card / Alien Registration</h5>
                                    <p class="mb-0 text-muted small">A clear scanned copy of your Kenyan National ID (both sides) or valid Alien Registration Document for identification.</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="req-item-card">
                                <div class="req-icon"><i class="fas fa-university"></i></div>
                                <div>
                                    <h5 class="mb-1 fw-bold fs-6">Campus Introduction / Recommendation Letter</h5>
                                    <p class="mb-0 text-muted small">An official letter from your university, college, or technical institute dean or placement directorate certifying ongoing student status.</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="req-item-card">
                                <div class="req-icon"><i class="fas fa-shield-virus"></i></div>
                                <div>
                                    <h5 class="mb-1 fw-bold fs-6">Personal Accident / Student Insurance Cover</h5>
                                    <p class="mb-0 text-muted small">Valid personal accident insurance policy certificate covering the entire prospective attachment period (typically 3 to 6 months).</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="req-item-card">
                                <div class="req-icon"><i class="fas fa-envelope-open-text"></i></div>
                                <div>
                                    <h5 class="mb-1 fw-bold fs-6">Formal Application Letter</h5>
                                    <p class="mb-0 text-muted small">Addressed to the County Secretary / Chief Officer of the respective department specifying your preferred field and period.</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="req-item-card">
                                <div class="req-icon"><i class="fas fa-award"></i></div>
                                <div>
                                    <h5 class="mb-1 fw-bold fs-6">Academic Transcripts / Certificates</h5>
                                    <p class="mb-0 text-muted small">Copies of current semester examination transcripts or KCSE/Diploma certificates demonstrating foundational qualification.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 7. PARTICIPATING DEPARTMENTS SHOWCASE -->
    <section class="py-5 bg-white border-top border-bottom" id="departments">
        <div class="container py-3">
            <div class="text-center mb-5">
                <span class="section-tag"><i class="fas fa-sitemap me-1"></i> County Municipal Portfolios</span>
                <h2 class="section-heading">Participating Departments</h2>
                <p class="section-lead">Attachments and internships are coordinated across nine county departments with dedicated department supervisors overseeing on-the-job training.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="dept-grid-card" onclick="filterByDeptAndScroll('ICT')">
                        <div class="dept-icon"><i class="fas fa-laptop-code"></i></div>
                        <h4 class="dept-title">ICT & Innovation</h4>
                        <p class="dept-desc">Software development, networking, cybersecurity, IT helpdesk, GIS mapping, and municipal system administration.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="dept-grid-card" onclick="filterByDeptAndScroll('Health')">
                        <div class="dept-icon"><i class="fas fa-heartbeat"></i></div>
                        <h4 class="dept-title">Health & Sanitation</h4>
                        <p class="dept-desc">Clinical medicine, nursing, health records, pharmaceutical inventory, public health inspection, and laboratory science.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="dept-grid-card" onclick="filterByDeptAndScroll('Finance')">
                        <div class="dept-icon"><i class="fas fa-coins"></i></div>
                        <h4 class="dept-title">Finance & Economic Planning</h4>
                        <p class="dept-desc">Internal audit, revenue reconciliation, budgeting, IFMIS accounting, asset register management, and economic research.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="dept-grid-card" onclick="filterByDeptAndScroll('Procurement')">
                        <div class="dept-icon"><i class="fas fa-boxes-packing"></i></div>
                        <h4 class="dept-title">Procurement & Supply Chain</h4>
                        <p class="dept-desc">Public tendering, procurement statutory compliance, storekeeping, supplier management, and goods inspection.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="dept-grid-card" onclick="filterByDeptAndScroll('HR')">
                        <div class="dept-icon"><i class="fas fa-users-cog"></i></div>
                        <h4 class="dept-title">Human Resource Management</h4>
                        <p class="dept-desc">Staff welfare, personnel records, payroll processing, intern management, and continuous institutional training.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="dept-grid-card" onclick="filterByDeptAndScroll('Education')">
                        <div class="dept-icon"><i class="fas fa-graduation-cap"></i></div>
                        <h4 class="dept-title">Education & Social Services</h4>
                        <p class="dept-desc">Vocational training centers (VTCs), early childhood development (ECDE), youth empowerment, and cultural heritage.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="dept-grid-card" onclick="filterByDeptAndScroll('Agriculture')">
                        <div class="dept-icon"><i class="fas fa-tractor"></i></div>
                        <h4 class="dept-title">Agriculture & Fisheries</h4>
                        <p class="dept-desc">Veterinary extension, crop protection, agribusiness, subsidized input distribution, and aquaculture support.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="dept-grid-card" onclick="filterByDeptAndScroll('Water')">
                        <div class="dept-icon"><i class="fas fa-tint"></i></div>
                        <h4 class="dept-title">Water & Natural Resources</h4>
                        <p class="dept-desc">Irrigation infrastructure, urban water distribution, hydrology surveys, pipeline maintenance, and forestry conservation.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="dept-grid-card" onclick="filterByDeptAndScroll('Transport')">
                        <div class="dept-icon"><i class="fas fa-road"></i></div>
                        <h4 class="dept-title">Transport & Public Works</h4>
                        <p class="dept-desc">Civil engineering, structural inspection, architectural drafting, road maintenance, and municipal mechanical workshops.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 8. TRUST & COMPLIANCE CALLOUT -->
    <section class="py-5">
        <div class="container">
            <div class="trust-banner shadow-lg">
                <div class="row align-items-center gy-4">
                    <div class="col-lg-8">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="fas fa-shield-halved fa-lg text-warning"></i>
                            <span class="text-uppercase fw-bold text-warning small tracking-wide">Security & Data Privacy Assurance</span>
                        </div>
                        <h3>Compliant with the Kenya Data Protection Act, 2019</h3>
                        <p class="mb-0 text-white-50">
                            The Vuka Portal enforces strict role-based data isolation. Your personal identification data, academic records, and uploaded credentials are stored with AES-standard cryptographic safeguards and accessed exclusively by authorized department supervisors.
                        </p>
                    </div>
                    <div class="col-lg-4 text-lg-end">
                        <button type="button" class="btn btn-light btn-lg px-4 fw-bold" onclick="openAuthModal('register')">
                            <i class="fas fa-lock me-2 text-success"></i> Secure Registration
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 9. FREQUENTLY ASKED QUESTIONS (FAQ) -->
    <section class="py-5" id="faq">
        <div class="container py-3">
            <div class="text-center mb-5">
                <span class="section-tag"><i class="fas fa-question-circle me-1"></i> Common Inquiries</span>
                <h2 class="section-heading">Frequently Asked Questions</h2>
                <p class="section-lead">Find clear answers to typical questions regarding the student attachment and graduate internship application process.</p>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    Who is eligible to apply for industrial attachments?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Continuing students enrolled in accredited Kenyan universities, technical vocational colleges (TVETs), or polytechnics who require practical industrial attachment as part of their academic curriculum are eligible. Applicants must present a valid recommendation letter from their institution.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    What is the difference between an Attachment and an Internship?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <strong>Industrial Attachments</strong> are typically 3 to 6 months long and intended for ongoing students seeking experiential academic credit. <strong>Internships</strong> are typically 1-year graduate opportunities designed for recent graduates who have completed their degree or diploma programs.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    Can I apply to more than one department at a time?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    To ensure equitable access across all student applicants, the portal permits <strong>one active application per student</strong> at any given time. If an application is rejected or withdrawn before review, you may submit a new application for a different department.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    How will I know if my application has been accepted?
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    You can log in to your Student Dashboard at any time to inspect your live <strong>Status Timeline</strong>. When a department supervisor accepts your application or schedules an interview, you will receive an in-app notification and an email alert with next steps.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                                    What should I do if I didn't receive my email verification code?
                                </button>
                            </h2>
                            <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Verification codes are sent immediately upon registration. If you do not see the email in your primary inbox, please inspect your Spam/Junk folder. You can also click the <em>"Resend code"</em> link on the verification popup to generate a new 6-digit confirmation code.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
                                    How are performance evaluations handled at the end of the attachment?
                                </button>
                            </h2>
                            <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Upon completion of your ongoing attachment, your designated Department Supervisor will complete an official performance evaluation assessing attendance, technical skill, attitude, communication, and initiative. You can download your official signed Evaluation PDF directly from your dashboard.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 10. INSTITUTIONAL FOOTER -->
    <footer class="site-footer">
        <div class="container">
            <div class="row g-4 justify-content-between">
                <div class="col-lg-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <svg width="120" height="36" viewBox="0 0 140 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect width="36" height="36" rx="9" fill="#0F7A45" x="0" y="4"/>
                            <path d="M9 14 L18 28 L27 14" stroke="#F4F7F5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                            <path d="M13 14 L18 22 L23 14" stroke="#C5401A" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                            <text x="46" y="29" font-family="'Plus Jakarta Sans', sans-serif" font-weight="700" font-size="24" fill="#ffffff" letter-spacing="-0.5">vuka</text>
                        </svg>
                        <span class="badge bg-success-subtle text-success">Attachment Portal</span>
                    </div>
                    <p class="small text-white-50 mb-3">
                        Vuka is the official municipal digital platform for managing industrial attachments, tertiary practicums, and graduate internships across County Government departments.
                    </p>
                    <div class="small text-white-50">
                        <i class="fas fa-building me-1"></i> County Public Service Board Secretariat<br>
                        <i class="fas fa-location-dot me-1"></i> County Headquarters, P.O. Box 900-10100
                    </div>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><a href="#overview">Overview</a></li>
                        <li class="mb-2"><a href="#vacancies">Vacancies</a></li>
                        <li class="mb-2"><a href="#how-it-works">How It Works</a></li>
                        <li class="mb-2"><a href="#requirements">Requirements</a></li>
                        <li class="mb-2"><a href="#departments">Departments</a></li>
                        <li class="mb-2"><a href="#faq">Frequently Asked Questions</a></li>
                    </ul>
                </div>

                <div class="col-6 col-md-3 col-lg-3">
                    <h5>Departments</h5>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><a href="#vacancies" onclick="filterByDeptAndScroll('ICT')">ICT & Digital Economy</a></li>
                        <li class="mb-2"><a href="#vacancies" onclick="filterByDeptAndScroll('Health')">Health Services</a></li>
                        <li class="mb-2"><a href="#vacancies" onclick="filterByDeptAndScroll('Finance')">Finance & Planning</a></li>
                        <li class="mb-2"><a href="#vacancies" onclick="filterByDeptAndScroll('Procurement')">Procurement & Supply</a></li>
                        <li class="mb-2"><a href="#vacancies" onclick="filterByDeptAndScroll('Agriculture')">Agriculture & Livestock</a></li>
                        <li class="mb-2"><a href="#vacancies" onclick="filterByDeptAndScroll('HR')">Human Resource Dev.</a></li>
                    </ul>
                </div>

                <div class="col-md-6 col-lg-3">
                    <h5>Support & Inquiries</h5>
                    <p class="small text-white-50 mb-2">For portal technical support, student verification, or supervisor assistance:</p>
                    <div class="small text-white-50 mb-1">
                        <i class="fas fa-envelope me-2 text-success"></i> support@vuka.go.ke
                    </div>
                    <div class="small text-white-50 mb-3">
                        <i class="fas fa-phone-alt me-2 text-success"></i> +254 (0) 700 000 000
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-light btn-sm" onclick="openAuthModal('admin')">
                            <i class="fas fa-user-shield me-1"></i> Staff Portal
                        </button>
                        <button type="button" class="btn btn-success btn-sm" onclick="openAuthModal('login')">
                            <i class="fas fa-user-graduate me-1"></i> Student Portal
                        </button>
                    </div>
                </div>
            </div>

            <hr class="footer-divider">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 small text-white-50">
                <div>
                    &copy; <?php echo date('Y'); ?> Vuka Attachment Portal. All rights reserved. Republic of Kenya.
                </div>
                <div>
                    Compliant with Kenya Data Protection Act, 2019 &bull; ISO 27001 Aligned Controls
                </div>
            </div>
        </div>
    </footer>


    <!-- ============================================================
         MODALS & INTERACTIVE OVERLAYS
         ============================================================ -->

    <!-- UNIVERSAL AUTHENTICATION MODAL (Login / Register / Staff) -->
    <div class="modal fade" id="authModal" tabindex="-1" aria-labelledby="authModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 540px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-lg);">
                <div class="modal-header border-0 pb-0">
                    <div class="d-flex align-items-center gap-2">
                        <svg width="100" height="32" viewBox="0 0 140 44" fill="none">
                            <rect width="36" height="36" rx="9" fill="#0F7A45" x="0" y="4"/>
                            <path d="M9 14 L18 28 L27 14" stroke="#F4F7F5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                            <path d="M13 14 L18 22 L23 14" stroke="#C5401A" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                            <text x="46" y="29" font-family="'Plus Jakarta Sans', sans-serif" font-weight="700" font-size="24" fill="var(--c-ink)" letter-spacing="-0.5">vuka</text>
                        </svg>
                        <span class="badge-gov">Portal Access</span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4 pt-3">
                    <ul class="nav nav-pills nav-fill mb-3 p-1 rounded-3 bg-light border" id="authTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active small py-2 fw-semibold" id="login-tab" data-bs-toggle="tab" data-bs-target="#login-content" type="button" role="tab">
                                <i class="fas fa-user-graduate me-1"></i> Student Login
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link small py-2 fw-semibold" id="register-tab" data-bs-toggle="tab" data-bs-target="#register-content" type="button" role="tab">
                                <i class="fas fa-user-plus me-1"></i> Register
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link small py-2 fw-semibold" id="admin-tab" data-bs-toggle="tab" data-bs-target="#admin-login-content" type="button" role="tab">
                                <i class="fas fa-user-shield me-1"></i> Staff
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="authTabsContent">
                        <!-- TAB 1: STUDENT LOGIN -->
                        <div class="tab-pane fade show active" id="login-content" role="tabpanel">
                            <form id="loginForm" novalidate>
                                <div class="mb-3">
                                    <label for="loginId" class="form-label small fw-semibold">National ID / Registration No.</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-id-card text-muted"></i></span>
                                        <input type="text" class="form-control" id="loginId" placeholder="6 to 8 digits only" required pattern="\d{6,8}" minlength="6" maxlength="8" inputmode="numeric">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="loginPassword" class="form-label small fw-semibold">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                                        <div class="password-wrapper flex-grow-1">
                                            <input type="password" class="form-control" id="loginPassword" placeholder="Enter your password" required>
                                            <button type="button" class="password-toggle" data-target="loginPassword"><i class="fas fa-eye"></i></button>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="rememberMe">
                                        <label class="form-check-label small text-muted" for="rememberMe">Remember me</label>
                                    </div>
                                    <a href="#" id="forgotPasswordLink" class="small text-decoration-none text-success">Forgot password?</a>
                                </div>
                                <div class="d-grid mb-2">
                                    <button type="submit" class="btn btn-primary btn-lg fs-6">
                                        <i class="fas fa-sign-in-alt me-2"></i> Log In to Student Portal
                                    </button>
                                </div>
                                <div class="text-center mt-3">
                                    <span class="small text-muted">Don't have an account yet? </span>
                                    <a href="#" class="small text-success fw-bold text-decoration-none" onclick="document.getElementById('register-tab').click(); return false;">Create an account</a>
                                </div>
                            </form>
                        </div>

                        <!-- TAB 2: STUDENT REGISTRATION -->
                        <div class="tab-pane fade" id="register-content" role="tabpanel">
                            <form id="registrationForm" novalidate>
                                <div class="mb-3">
                                    <label for="regFullName" class="form-label small fw-semibold">Full Legal Name <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                                        <input type="text" class="form-control" id="regFullName" placeholder="e.g. John Mwangi Kamau" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="regIdNumber" class="form-label small fw-semibold">National ID Number <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-id-card text-muted"></i></span>
                                        <input type="text" class="form-control" id="regIdNumber" placeholder="6 to 8 digits only" required pattern="\d{6,8}" minlength="6" maxlength="8" inputmode="numeric">
                                    </div>
                                    <small class="form-text text-muted">Must be your genuine government National ID number.</small>
                                </div>
                                <div class="mb-3">
                                    <label for="regEmail" class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope text-muted"></i></span>
                                        <input type="email" class="form-control" id="regEmail" placeholder="yourname@gmail.com" required>
                                    </div>
                                    <small class="form-text text-muted">Allowed: @gmail.com, @outlook.com, @yahoo.com, etc.</small>
                                </div>
                                <div class="mb-3">
                                    <label for="regLoginDate" class="form-label small fw-semibold">Registration Date</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-calendar text-muted"></i></span>
                                        <input type="date" class="form-control" id="regLoginDate" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="regPassword" class="form-label small fw-semibold">Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                                        <div class="password-wrapper flex-grow-1">
                                            <input type="password" class="form-control" id="regPassword" placeholder="Minimum 8 characters" required minlength="8">
                                            <button type="button" class="password-toggle" data-target="regPassword"><i class="fas fa-eye"></i></button>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="regConfirmPassword" class="form-label small fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                                        <div class="password-wrapper flex-grow-1">
                                            <input type="password" class="form-control" id="regConfirmPassword" placeholder="Confirm your password" required minlength="6">
                                            <button type="button" class="password-toggle" data-target="regConfirmPassword"><i class="fas fa-eye"></i></button>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-grid mb-2">
                                    <button type="submit" class="btn btn-primary btn-lg fs-6">
                                        <i class="fas fa-user-plus me-2"></i> Register & Verify Email
                                    </button>
                                </div>
                                <div class="text-center mt-2">
                                    <span class="small text-muted">Already registered? </span>
                                    <a href="#" class="small text-success fw-bold text-decoration-none" onclick="document.getElementById('login-tab').click(); return false;">Sign in</a>
                                </div>
                            </form>
                        </div>

                        <!-- TAB 3: STAFF / ADMIN LOGIN -->
                        <div class="tab-pane fade" id="admin-login-content" role="tabpanel">
                            <div class="alert alert-light border small text-muted mb-3">
                                <i class="fas fa-info-circle me-1 text-primary"></i> <strong>Staff Access Only:</strong> For Department Supervisors, HR Coordinators, and System Super Administrators.
                            </div>
                            <form id="adminLoginForm" novalidate>
                                <div class="mb-3">
                                    <label for="adminPFNumber" class="form-label small fw-semibold">Personal File (PF) Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-id-badge text-muted"></i></span>
                                        <input type="text" class="form-control text-uppercase" id="adminPFNumber" placeholder="e.g. SUPER/ADMIN/001 or PF1002" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="adminPassword" class="form-label small fw-semibold">Staff Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                                        <div class="password-wrapper flex-grow-1">
                                            <input type="password" class="form-control" id="adminPassword" placeholder="Enter staff password" required>
                                            <button type="button" class="password-toggle" data-target="adminPassword"><i class="fas fa-eye"></i></button>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-grid mb-2">
                                    <button type="submit" class="btn btn-success btn-lg fs-6">
                                        <i class="fas fa-user-shield me-2"></i> Staff Authentication
                                    </button>
                                </div>
                                <div class="text-center mt-2 small text-muted">
                                    Admin accounts are authorized by the County Public Service Board.
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- EMAIL VERIFICATION MODAL -->
    <div class="modal fade" id="emailVerifyModal" tabindex="-1" aria-labelledby="verifyModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
            <div class="modal-content border-0 shadow-lg text-center p-3" style="border-radius: var(--radius-lg);">
                <div class="modal-body">
                    <div class="mb-3 text-success">
                        <i class="fas fa-envelope-circle-check fa-3x"></i>
                    </div>
                    <h4 class="fw-bold" style="font-family: var(--font-display);">Verify Your Email</h4>
                    <p class="text-muted small mb-3">
                        We have dispatched a 6-digit verification code to <strong id="verifyEmailDisplay"></strong>. Enter the code below to activate your account.
                    </p>
                    <div class="mb-3">
                        <input type="text" class="form-control verify-code-input" id="verifyCodeInput" maxlength="6" placeholder="000000" inputmode="numeric">
                    </div>
                    <div id="verifyFeedback" class="alert alert-danger py-2 small" style="display:none;"></div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" id="verifyCodeBtn">
                            <i class="fas fa-check me-1"></i> Confirm & Verify Account
                        </button>
                    </div>
                    <div class="mt-3 small">
                        <span class="text-muted">Didn't receive the email? </span>
                        <a href="#" id="resendVerifyLink" class="text-success fw-semibold text-decoration-none">Resend code</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FORGOT PASSWORD MODAL -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
            <div class="modal-content border-0 shadow-lg p-3" style="border-radius: var(--radius-lg);">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" style="font-family: var(--font-display);">Password Recovery</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Enter the email address registered with your student or staff account. We will send you an official password reset link.
                    </p>
                    <form id="forgotPasswordForm">
                        <div class="mb-3">
                            <label for="forgotEmail" class="form-label small fw-semibold">Registered Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope text-muted"></i></span>
                                <input type="email" class="form-control" id="forgotEmail" placeholder="your@email.com" required>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i> Send Reset Link
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden compatibility container for legacy views if triggered -->
    <div style="display:none;">
        <div id="login-view"></div>
        <div id="dashboard-view"></div>
        <div id="blocked-view"></div>
        <div id="success-view"></div>
        <div id="admin-view"></div>
        <div id="blockedIdDisplay"></div>
        <div id="adminPFDisplay"></div>
        <div id="adminNameDisplay"></div>
        <div id="adminDeptDisplay"></div>
        <div id="seniorAdminNav"></div>
        <div id="adminRoleBadge"></div>
        <div id="adminPanelTitle"></div>
    </div>

    <!-- SCRIPTS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/common.js"></script>
    <script src="assets/js/index.js"></script>

    <script>
        // Global Auth Modal Helper
        let authModalInstance = null;
        function openAuthModal(targetTab = 'login') {
            const modalEl = document.getElementById('authModal');
            if (!authModalInstance && modalEl) {
                authModalInstance = new bootstrap.Modal(modalEl);
            }
            if (targetTab === 'register') {
                const regTab = document.getElementById('register-tab');
                if (regTab) regTab.click();
            } else if (targetTab === 'admin') {
                const adminTab = document.getElementById('admin-tab');
                if (adminTab) adminTab.click();
            } else {
                const loginTab = document.getElementById('login-tab');
                if (loginTab) loginTab.click();
            }
            if (authModalInstance) authModalInstance.show();
        }

        function openForgotPasswordModal(e) {
            if (e) e.preventDefault();
            if (authModalInstance) authModalInstance.hide();
            const fpEl = document.getElementById('forgotPasswordModal');
            if (fpEl) {
                const fpModal = bootstrap.Modal.getOrCreateInstance(fpEl);
                fpModal.show();
            }
        }

        // Quick login submission from hero section
        function handleHeroQuickLogin(e) {
            e.preventDefault();
            const idVal = document.getElementById('heroLoginId').value.trim();
            const passVal = document.getElementById('heroLoginPassword').value;

            const mainId = document.getElementById('loginId');
            const mainPass = document.getElementById('loginPassword');
            if (mainId && mainPass) {
                mainId.value = idVal;
                mainPass.value = passVal;
                // Dispatch submit on primary loginForm
                const form = document.getElementById('loginForm');
                if (form) {
                    form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                }
            }
        }

        // Vacancy Filtering logic
        let currentDeptFilter = 'all';
        function filterByDept(dept, btnElement) {
            currentDeptFilter = dept;
            document.querySelectorAll('.filter-chip-btn').forEach(b => b.classList.remove('active'));
            if (btnElement) btnElement.classList.add('active');
            filterVacancies();
        }

        function filterByDeptAndScroll(dept) {
            const targetBtn = document.querySelector(`.filter-chip-btn[data-filter="${dept}"]`);
            filterByDept(dept, targetBtn);
            const vacSection = document.getElementById('vacancies');
            if (vacSection) vacSection.scrollIntoView({ behavior: 'smooth' });
        }

        function filterVacancies() {
            const searchVal = (document.getElementById('vacancySearchInput')?.value || '').toLowerCase().trim();
            const items = document.querySelectorAll('.vacancy-item');
            let visibleCount = 0;

            items.forEach(item => {
                const itemDept = item.getAttribute('data-dept') || '';
                const itemText = item.textContent.toLowerCase();

                const matchesDept = (currentDeptFilter === 'all' || itemDept.toLowerCase() === currentDeptFilter.toLowerCase());
                const matchesSearch = (!searchVal || itemText.includes(searchVal));

                if (matchesDept && matchesSearch) {
                    item.style.display = '';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            const alertEl = document.getElementById('noVacanciesAlert');
            if (alertEl) {
                if (visibleCount === 0) {
                    alertEl.classList.remove('d-none');
                } else {
                    alertEl.classList.add('d-none');
                }
            }
        }

        function applyToVacancy(dept, title) {
            openAuthModal('register');
        }

        // Fetch live vacancies from API on page load
        document.addEventListener('DOMContentLoaded', async function () {
            try {
                const res = await fetch('api/vacancies.php?status=approved');
                const data = await res.json();
                const list = data?.data?.vacancies || data?.vacancies || [];

                if (Array.isArray(list) && list.length > 0) {
                    const grid = document.getElementById('vacanciesGrid');
                    if (grid) {
                        grid.innerHTML = ''; // Replace sample cards with live approved vacancies
                        list.forEach(v => {
                            const col = document.createElement('div');
                            col.className = 'col-md-6 col-lg-4 vacancy-item';
                            col.setAttribute('data-dept', v.department_name || '');
                            col.setAttribute('data-type', v.vacancy_type || 'attachment');

                            const skills = (v.skills_required || '').split(',').map(s => s.trim()).filter(Boolean);
                            const skillTags = skills.map(s => `<span class="skill-tag">${s}</span>`).join('');
                            const deadline = v.deadline_at ? new Date(v.deadline_at).toLocaleDateString() : 'Rolling Admission';

                            col.innerHTML = `
                                <div class="vacancy-card">
                                    <div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="vacancy-dept-badge">${v.department_name || 'County Department'}</span>
                                            <span class="vacancy-type-badge">${(v.vacancy_type || 'attachment').toUpperCase()}</span>
                                        </div>
                                        <h4 class="vacancy-title">${v.title}</h4>
                                        <p class="vacancy-desc">${v.description || 'No description provided.'}</p>
                                        <div class="mb-3">${skillTags}</div>
                                    </div>
                                    <div>
                                        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                            <span class="deadline-pill"><i class="fas fa-users text-success"></i> ${v.positions_count || 1} Positions</span>
                                            <button type="button" class="btn btn-outline-success btn-sm" onclick="applyToVacancy('${v.department_name || ''}', '${v.title}')">Apply Now &rarr;</button>
                                        </div>
                                    </div>
                                </div>
                            `;
                            grid.appendChild(col);
                        });
                    }
                }
            } catch (err) {
                console.log('Using default sample vacancy cards:', err);
            }
        });
    </script>
</body>

</html>
