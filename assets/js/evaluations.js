/**
 * Intern Performance Evaluation — Vuka Portal (Feature #4)
 *
 * Self-contained frontend module. All functions are attached to `window` and are
 * container-null-safe so they can be loaded on any dashboard without hard
 * dependencies. Requires common.js (API_BASE, showToast) loaded first, plus
 * Bootstrap 5 + FontAwesome.
 */
(function () {
    'use strict';

    var CRITERIA = ['attendance', 'technical', 'attitude', 'communication', 'initiative'];
    var LABELS = ['Attendance', 'Technical Skills', 'Attitude', 'Communication', 'Initiative'];
    var REC_LABELS = {
        highly_recommended: 'Highly Recommended',
        recommended: 'Recommended',
        not_recommended: 'Not Recommended'
    };

    // ---- helpers -----------------------------------------------------------

    function apiBase() {
        return (typeof API_BASE !== 'undefined') ? API_BASE : 'api';
    }

    function toast(msg, type) {
        if (typeof showToast === 'function') {
            showToast(msg, type || 'info');
        } else {
            // eslint-disable-next-line no-alert
            alert(msg);
        }
    }

    function esc(v) {
        if (v === null || v === undefined) return '';
        return String(v)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /** Ensure a reusable evaluation modal exists in the DOM; return its body element. */
    function ensureModal() {
        var existing = document.getElementById('evaluationModal');
        if (existing) {
            return document.getElementById('evaluationModalBody');
        }
        var wrapper = document.createElement('div');
        wrapper.innerHTML =
            '<div class="modal fade" id="evaluationModal" tabindex="-1" aria-hidden="true">' +
            '  <div class="modal-dialog modal-lg modal-dialog-scrollable">' +
            '    <div class="modal-content">' +
            '      <div class="modal-header">' +
            '        <h5 class="modal-title"><i class="fas fa-clipboard-check me-2"></i>Performance Evaluation</h5>' +
            '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
            '      </div>' +
            '      <div class="modal-body" id="evaluationModalBody"></div>' +
            '    </div>' +
            '  </div>' +
            '</div>';
        document.body.appendChild(wrapper.firstChild);
        return document.getElementById('evaluationModalBody');
    }

    function showModal() {
        var el = document.getElementById('evaluationModal');
        if (!el || typeof bootstrap === 'undefined') return null;
        var modal = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
        modal.show();
        return modal;
    }

    function hideModal() {
        var el = document.getElementById('evaluationModal');
        if (!el || typeof bootstrap === 'undefined') return;
        var modal = bootstrap.Modal.getInstance(el);
        if (modal) modal.hide();
    }

    function scoreBadge(score) {
        var s = parseInt(score, 10) || 0;
        var cls = s >= 4 ? 'bg-success' : (s === 3 ? 'bg-warning text-dark' : 'bg-danger');
        return '<span class="badge ' + cls + '">' + s + ' / 5</span>';
    }

    // ---- 1. Supervisor: open + submit evaluation form ----------------------

    function openEvaluationModal(submissionId, studentName) {
        var body = ensureModal();
        if (!body) { toast('Evaluation modal unavailable.', 'danger'); return; }

        var rows = CRITERIA.map(function (c, i) {
            var opts = [1, 2, 3, 4, 5].map(function (n) {
                return '<option value="' + n + '">' + n + '</option>';
            }).join('');
            return '' +
                '<div class="col-md-6 mb-3">' +
                '  <label class="form-label fw-semibold" for="eval_' + c + '">' + esc(LABELS[i]) + '</label>' +
                '  <select class="form-select eval-score" id="eval_' + c + '" data-criterion="' + c + '">' +
                '    <option value="" selected disabled>Select 1-5</option>' + opts +
                '  </select>' +
                '</div>';
        }).join('');

        body.innerHTML = '' +
            '<p class="text-muted small mb-3">Evaluating <strong>' + esc(studentName || ('Submission #' + submissionId)) +
            '</strong>. Rate each criterion 1 (poor) to 5 (excellent).</p>' +
            '<div class="row">' + rows + '</div>' +
            '<div class="mb-3">' +
            '  <label class="form-label fw-semibold" for="eval_comment">Overall Comment</label>' +
            '  <textarea class="form-control" id="eval_comment" rows="3" placeholder="Summary of the intern\'s performance..."></textarea>' +
            '</div>' +
            '<div class="mb-3">' +
            '  <label class="form-label fw-semibold" for="eval_recommendation">Recommendation</label>' +
            '  <select class="form-select" id="eval_recommendation">' +
            '    <option value="highly_recommended">Highly Recommended</option>' +
            '    <option value="recommended" selected>Recommended</option>' +
            '    <option value="not_recommended">Not Recommended</option>' +
            '  </select>' +
            '</div>' +
            '<div class="d-flex justify-content-end gap-2">' +
            '  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>' +
            '  <button type="button" class="btn btn-success" id="evalSubmitBtn">' +
            '    <i class="fas fa-paper-plane me-1"></i>Submit Evaluation</button>' +
            '</div>';

        var btn = document.getElementById('evalSubmitBtn');
        if (btn) {
            btn.addEventListener('click', function () {
                submitEvaluation(submissionId, btn);
            });
        }

        showModal();
    }

    function submitEvaluation(submissionId, btn) {
        var token = sessionStorage.getItem('adminToken');
        if (!token) { toast('Your session has expired. Please log in again.', 'danger'); return; }

        var payload = { submission_id: submissionId };
        var valid = true;
        CRITERIA.forEach(function (c) {
            var el = document.getElementById('eval_' + c);
            var val = el ? parseInt(el.value, 10) : 0;
            if (!val || val < 1 || val > 5) { valid = false; }
            payload[c] = val || 0;
        });
        if (!valid) { toast('Please provide a 1-5 score for every criterion.', 'warning'); return; }

        var commentEl = document.getElementById('eval_comment');
        var recEl = document.getElementById('eval_recommendation');
        payload.overall_comment = commentEl ? commentEl.value : '';
        payload.recommendation = recEl ? recEl.value : 'recommended';

        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Submitting...'; }

        fetch(apiBase() + '/evaluations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            body: JSON.stringify(payload)
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.success) {
                    toast('Evaluation submitted successfully.', 'success');
                    hideModal();
                    // Best-effort refresh of whichever list is present.
                    if (typeof loadSupervisorApplicants === 'function') { loadSupervisorApplicants(); }
                    else if (typeof loadApplicants === 'function') { loadApplicants(); }
                    else if (typeof initSupervisorDashboard === 'function') { initSupervisorDashboard(); }
                } else {
                    toast((data && (data.error || data.message)) || 'Failed to submit evaluation.', 'danger');
                }
            })
            .catch(function (e) { toast('Error: ' + e.message, 'danger'); })
            .finally(function () {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Submit Evaluation'; }
            });
    }

    // ---- 2. HR/Admin: view read-only evaluation ----------------------------

    function viewEvaluation(submissionId) {
        var body = ensureModal();
        if (!body) { toast('Evaluation modal unavailable.', 'danger'); return; }

        var token = sessionStorage.getItem('adminToken');
        if (!token) { toast('Your session has expired. Please log in again.', 'danger'); return; }

        body.innerHTML = '<div class="text-center py-4"><span class="spinner-border text-primary"></span>' +
            '<p class="mt-2">Loading evaluation...</p></div>';
        showModal();

        fetch(apiBase() + '/evaluations.php?submission_id=' + encodeURIComponent(submissionId), {
            headers: { 'Authorization': 'Bearer ' + token }
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.success) {
                    body.innerHTML = '<div class="alert alert-danger">' +
                        esc((data && (data.error || data.message)) || 'Failed to load evaluation.') + '</div>';
                    return;
                }
                if (!data.has_evaluation || !data.evaluation) {
                    body.innerHTML = '<div class="alert alert-info mb-0">No evaluation has been submitted for this intern yet.</div>';
                    return;
                }
                body.innerHTML = renderEvaluationSummary(data.evaluation, data.submission, true);
            })
            .catch(function (e) {
                body.innerHTML = '<div class="alert alert-danger">Error: ' + esc(e.message) + '</div>';
            });
    }

    /** Build a read-only summary. `withDownload` adds a PDF download button. */
    function renderEvaluationSummary(evaluation, submission, withDownload) {
        var name = submission && submission.full_name ? submission.full_name : ('Submission #' + evaluation.submission_id);
        var rows = CRITERIA.map(function (c, i) {
            return '<tr><td>' + esc(LABELS[i]) + '</td><td class="text-end">' + scoreBadge(evaluation[c]) + '</td></tr>';
        }).join('');

        var total = 0;
        CRITERIA.forEach(function (c) { total += parseInt(evaluation[c], 10) || 0; });
        var avg = CRITERIA.length ? (total / CRITERIA.length).toFixed(1) : '0';

        var rec = REC_LABELS[evaluation.recommendation] || evaluation.recommendation || '-';
        var evaluator = evaluation.evaluator_name || 'Supervisor';
        if (evaluation.evaluator_pf) { evaluator += ' (' + evaluation.evaluator_pf + ')'; }

        var html = '' +
            '<h6 class="fw-bold mb-1">' + esc(name) + '</h6>' +
            '<p class="text-muted small mb-3">Department: ' + esc(submission && submission.department_applied ? submission.department_applied : '-') + '</p>' +
            '<table class="table table-sm align-middle">' +
            '  <thead><tr><th>Criterion</th><th class="text-end">Score</th></tr></thead>' +
            '  <tbody>' + rows +
            '    <tr class="table-light fw-bold"><td>Overall Average</td><td class="text-end">' + esc(avg) + ' / 5</td></tr>' +
            '  </tbody>' +
            '</table>' +
            '<div class="mb-2"><span class="fw-semibold">Recommendation:</span> ' + esc(rec) + '</div>' +
            '<div class="mb-2"><span class="fw-semibold">Overall Comment:</span><br>' +
            '<span class="text-muted">' + esc(evaluation.overall_comment || 'No additional comments provided.') + '</span></div>' +
            '<div class="mb-3 small text-muted">Evaluated by ' + esc(evaluator) +
            (evaluation.submitted_at ? ' on ' + esc(evaluation.submitted_at) : '') + '</div>';

        if (withDownload) {
            html += '<div class="d-flex justify-content-end">' +
                '<button type="button" class="btn btn-outline-success btn-sm" ' +
                'onclick="downloadEvaluationPdf(' + parseInt(evaluation.submission_id, 10) + ')">' +
                '<i class="fas fa-file-pdf me-1"></i>Download PDF</button></div>';
        }
        return html;
    }

    // ---- 3. Download PDF ----------------------------------------------------

    function downloadEvaluationPdf(submissionId, tokenType) {
        // Choose token: explicit arg wins, else student token, else admin token.
        var token;
        if (tokenType === 'user') {
            token = sessionStorage.getItem('userToken');
        } else if (tokenType === 'admin') {
            token = sessionStorage.getItem('adminToken');
        } else {
            token = sessionStorage.getItem('adminToken') || sessionStorage.getItem('userToken');
        }
        if (!token) { toast('Your session has expired. Please log in again.', 'danger'); return; }

        fetch(apiBase() + '/generate-evaluation-pdf.php?submission_id=' + encodeURIComponent(submissionId), {
            headers: { 'Authorization': 'Bearer ' + token }
        })
            .then(function (res) {
                if (!res.ok) {
                    return res.json().then(function (j) {
                        throw new Error((j && (j.error || j.message)) || ('Download failed (' + res.status + ')'));
                    }).catch(function () {
                        throw new Error('Download failed (' + res.status + ')');
                    });
                }
                return res.blob();
            })
            .then(function (blob) {
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'evaluation_' + submissionId + '.pdf';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
            })
            .catch(function (e) { toast(e.message || 'Failed to download PDF.', 'danger'); });
    }

    // ---- 4. Student: load own evaluation into the dashboard ----------------

    function loadStudentEvaluation(submissionId) {
        var container = document.getElementById('studentEvaluationSection');
        if (!container) return; // null-safe: nothing to do on pages without the section

        var token = sessionStorage.getItem('userToken');
        if (!token) return;

        // Resolve submission id: explicit arg, data attribute, or global helper.
        var sid = submissionId;
        if (!sid && container.dataset && container.dataset.submissionId) {
            sid = container.dataset.submissionId;
        }
        if (!sid && typeof window.currentSubmissionId !== 'undefined') {
            sid = window.currentSubmissionId;
        }
        if (!sid) { container.innerHTML = ''; return; }

        fetch(apiBase() + '/evaluations.php?submission_id=' + encodeURIComponent(sid), {
            headers: { 'Authorization': 'Bearer ' + token }
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.success || !data.has_evaluation || !data.evaluation) {
                    container.innerHTML = '';
                    return;
                }
                var summary = renderEvaluationSummary(data.evaluation, data.submission, false);
                container.innerHTML = '' +
                    '<div class="card shadow-sm mt-3">' +
                    '  <div class="card-header bg-white fw-bold">' +
                    '    <i class="fas fa-clipboard-check text-success me-2"></i>Performance Evaluation</div>' +
                    '  <div class="card-body">' + summary +
                    '    <div class="d-flex justify-content-end mt-2">' +
                    '      <button type="button" class="btn btn-success btn-sm" ' +
                    'onclick="downloadEvaluationPdf(' + parseInt(data.evaluation.submission_id, 10) + ', \'user\')">' +
                    '        <i class="fas fa-file-pdf me-1"></i>Download PDF</button>' +
                    '    </div>' +
                    '  </div>' +
                    '</div>';
            })
            .catch(function () { container.innerHTML = ''; });
    }

    // ---- exports -----------------------------------------------------------
    window.openEvaluationModal = openEvaluationModal;
    window.viewEvaluation = viewEvaluation;
    window.downloadEvaluationPdf = downloadEvaluationPdf;
    window.loadStudentEvaluation = loadStudentEvaluation;
})();
