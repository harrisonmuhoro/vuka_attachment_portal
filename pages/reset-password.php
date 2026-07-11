<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vuka — Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/common.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container" style="max-width: 460px; margin-top: 8vh;">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <h4 style="color: var(--c-green, #0F7A45);"><i class="fas fa-key me-2"></i>Reset Your Password</h4>
                    <p class="text-muted small mb-0">Enter a new password for your Vuka Portal account.</p>
                </div>

                <div id="invalidTokenMsg" class="alert alert-danger d-none">
                    This reset link is invalid or missing. Please request a new one from the
                    <a href="../index.php">login page</a>.
                </div>

                <form id="resetForm">
                    <div class="mb-3">
                        <label for="newPassword" class="form-label">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="newPassword"
                                   placeholder="At least 8 characters" minlength="8" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="confirmPassword" class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="confirmPassword"
                                   placeholder="Re-enter your password" minlength="8" required>
                        </div>
                    </div>
                    <div id="resetFeedback" class="small mb-3"></div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-check me-2"></i>Update Password
                        </button>
                    </div>
                </form>

                <div id="resetSuccess" class="text-center d-none">
                    <i class="fas fa-check-circle text-success" style="font-size:3rem;"></i>
                    <h5 class="mt-3">Password Updated</h5>
                    <p class="text-muted">You can now log in with your new password.</p>
                    <a href="../index.php" class="btn btn-primary">Go to Login</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            const API_BASE = '../api';
            const params = new URLSearchParams(window.location.search);
            const token = params.get('token') || '';

            const form = document.getElementById('resetForm');
            const feedback = document.getElementById('resetFeedback');

            if (!token) {
                document.getElementById('invalidTokenMsg').classList.remove('d-none');
                form.classList.add('d-none');
                return;
            }

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                feedback.textContent = '';
                feedback.className = 'small mb-3';

                const pw = document.getElementById('newPassword').value;
                const confirm = document.getElementById('confirmPassword').value;

                if (pw.length < 8) {
                    feedback.textContent = 'Password must be at least 8 characters.';
                    feedback.classList.add('text-danger');
                    return;
                }
                if (pw !== confirm) {
                    feedback.textContent = 'Passwords do not match.';
                    feedback.classList.add('text-danger');
                    return;
                }

                const btn = form.querySelector('button[type="submit"]');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';

                try {
                    const res = await fetch(`${API_BASE}/reset-password.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ token, password: pw })
                    });
                    const data = await res.json();
                    if (data.success) {
                        form.classList.add('d-none');
                        document.getElementById('resetSuccess').classList.remove('d-none');
                    } else {
                        feedback.textContent = data.error || data.message || 'Failed to reset password.';
                        feedback.classList.add('text-danger');
                    }
                } catch (err) {
                    feedback.textContent = 'Request failed: ' + err.message;
                    feedback.classList.add('text-danger');
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check me-2"></i>Update Password';
                }
            });
        })();
    </script>
</body>
</html>
