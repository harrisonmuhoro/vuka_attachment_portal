<!-- Profile panel -->
<div id="profileView" class="tab-pane fade">
  <div class="d-flex align-items-center gap-3 mb-4">
    <div class="position-relative">
      <img id="profilePhotoPreview"
           src="../assets/img/default-avatar.png"
           alt="Profile photo"
           class="rounded-circle nav-avatar"
           style="width:80px;height:80px;object-fit:cover;">
      <label for="photoInput"
             class="position-absolute bottom-0 end-0 btn btn-sm btn-primary
                    rounded-circle p-1" style="width:28px;height:28px;line-height:1; cursor: pointer;">
        <i class="fas fa-camera" style="font-size:11px"></i>
      </label>
      <input type="file" id="photoInput" accept="image/*"
             class="d-none" onchange="uploadProfilePhoto(this)">
    </div>
    <div>
      <h5 class="mb-0" id="profileDisplayName">Loading...</h5>
      <small class="text-muted" id="profileDisplayRole"></small>
    </div>
  </div>

  <!-- Sub-section tabs -->
  <ul class="nav nav-tabs mb-3" id="profileTabs">
    <li class="nav-item">
      <button class="nav-link active" onclick="switchProfileTab('info')">
        Personal Info
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" onclick="switchProfileTab('email')">
        Email
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" onclick="switchProfileTab('security')">
        Security
      </button>
    </li>
  </ul>

  <!-- Personal Info tab -->
  <div id="profileTabInfo">
    <div class="row g-3" style="max-width:480px">
      <?php if (strpos($_SERVER['REQUEST_URI'], 'student_dashboard') !== false): ?>
      <div class="col-12">
        <label class="form-label">Full Name</label>
        <input type="text" class="form-control" id="fieldFullName"
               placeholder="Full name">
      </div>
      <?php endif; ?>
      <div class="col-12">
        <label class="form-label">Phone Number</label>
        <input type="tel" class="form-control" id="fieldPhone"
               placeholder="+254 7XX XXX XXX">
      </div>
      <!-- Read-only identity fields — shown for transparency, not editable -->
      <?php if (strpos($_SERVER['REQUEST_URI'], 'student_dashboard') !== false): ?>
      <div class="col-12">
        <label class="form-label text-muted">
          National ID <span class="badge bg-secondary">Read-only</span>
        </label>
        <input type="text" class="form-control" id="fieldNationalId"
               disabled>
      </div>
      <?php endif; ?>
      <div class="col-12">
        <button class="btn btn-primary" onclick="savePersonalInfo()">
          Save Changes
        </button>
      </div>
    </div>
  </div>

  <!-- Email tab -->
  <div id="profileTabEmail" class="d-none" style="max-width:480px">
    <div class="mb-3">
      <label class="form-label text-muted">Current Email</label>
      <input type="email" class="form-control" id="fieldCurrentEmail" disabled>
    </div>
    <div id="emailChangeStep1">
      <label class="form-label">New Email Address</label>
      <div class="input-group">
        <input type="email" class="form-control" id="fieldNewEmail"
               placeholder="Enter new email">
        <button class="btn btn-outline-primary" onclick="requestEmailChange()">
          Send Code
        </button>
      </div>
      <small class="text-muted mt-1 d-block">
        A verification code will be sent to the new address.
      </small>
    </div>
    <div id="emailChangeStep2" class="d-none mt-3">
      <label class="form-label">Verification Code</label>
      <div class="input-group">
        <input type="text" class="form-control" id="fieldEmailCode"
               maxlength="6" placeholder="6-digit code">
        <button class="btn btn-success" onclick="confirmEmailChange()">
          Confirm
        </button>
      </div>
      <button class="btn btn-link btn-sm p-0 mt-1" onclick="resetEmailChangeFlow()">
        Use a different email
      </button>
    </div>
  </div>

  <!-- Security tab -->
  <div id="profileTabSecurity" class="d-none" style="max-width:480px">
    <div class="row g-3">
      <div class="col-12">
        <label class="form-label">Current Password</label>
        <input type="password" class="form-control" id="fieldCurrentPwd">
      </div>
      <div class="col-12">
        <label class="form-label">New Password</label>
        <input type="password" class="form-control" id="fieldNewPwd">
        <div id="pwdStrengthBar" class="progress mt-1" style="height:4px">
          <div id="pwdStrengthFill" class="progress-bar" style="width:0%"></div>
        </div>
        <small id="pwdStrengthLabel" class="text-muted"></small>
      </div>
      <div class="col-12">
        <label class="form-label">Confirm New Password</label>
        <input type="password" class="form-control" id="fieldConfirmPwd">
      </div>
      <div class="col-12">
        <button class="btn btn-primary" onclick="changePassword()">
          Update Password
        </button>
      </div>
    </div>
  </div>
</div>
