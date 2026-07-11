/**
 * Interviews module — Vuka Portal (Feature #3)
 *
 * Self-contained. Safe to include on ANY page: every function null-checks its
 * containers and reuses the globals defined in common.js (API_BASE, showToast,
 * getStatusBadge). Load AFTER common.js.
 *
 * Admin side (supervisor / HR dashboards):
 *   - renderInterviewCalendar(slots)
 *   - openScheduleInterviewModal(submissionId, studentName)
 *   - loadSupervisorInterviews()
 * Student side (student dashboard):
 *   - respondToInterview(slotId, response)
 *   - loadStudentInterview()
 */
(function () {
    'use strict';

    // Resolve the API base whether or not common.js has run.
    function apiBase() {
        if (typeof API_BASE !== 'undefined' && API_BASE) return API_BASE;
        var p = window.location.pathname;
        return (p.indexOf('/pages/') !== -1 || p.indexOf('\\pages\\') !== -1) ? '../api' : 'api';
    }

    function toast(msg, type) {
        if (typeof showToast === 'function') {
            showToast(msg, type || 'info');
        }
    }

    function statusColor(status) {
        switch (status) {
            case 'scheduled':   return 'info';
            case 'confirmed':   return 'success';
            case 'rescheduled': return 'warning';
            case 'cancelled':   return 'secondary';
            case 'completed':   return 'primary';
            default:            return 'secondary';
        }
    }

    function esc(v) {
        if (v === null || v === undefined) return '';
        return String(v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function formatTime(dt) {
        var d = new Date((dt || '').replace(' ', 'T'));
        if (isNaN(d.getTime())) return esc(dt);
        return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    }

    function formatDateTime(dt) {
        var d = new Date((dt || '').replace(' ', 'T'));
        if (isNaN(d.getTime())) return esc(dt);
        return d.toLocaleString('en-US', {
            weekday: 'short', year: 'numeric', month: 'short', day: 'numeric',
            hour: 'numeric', minute: '2-digit'
        });
    }

    /* =============================================================== */
    /* ADMIN: week-grid calendar                                       */
    /* =============================================================== */
    function renderInterviewCalendar(slots) {
        slots = Array.isArray(slots) ? slots : [];
        var days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];

        var grouped = {};
        slots.forEach(function (slot) {
            var d = new Date((slot.scheduled_at || '').replace(' ', 'T'));
            if (isNaN(d.getTime())) return;
            var day = d.toLocaleDateString('en-US', { weekday: 'short' });
            (grouped[day] = grouped[day] || []).push(slot);
        });

        var html = '<div class="row g-2 interview-week-grid">';
        days.forEach(function (day) {
            var daySlots = grouped[day] || [];
            html += '<div class="col">' +
                '<div class="p-2 border rounded text-center fw-semibold small bg-light">' + day + '</div>';
            if (daySlots.length === 0) {
                html += '<div class="p-2 text-center text-muted small mt-1">—</div>';
            }
            daySlots.forEach(function (s) {
                var resp = s.student_response
                    ? '<span class="badge bg-' + (s.student_response === 'confirmed' ? 'success' : 'danger') +
                      ' mt-1">' + esc(s.student_response) + '</span>'
                    : '';
                html += '<div class="p-2 border rounded mt-1 small interview-slot-card">' +
                    '<div class="fw-semibold">' + esc(s.student_name || 'Applicant') + '</div>' +
                    '<div class="text-muted">' + formatTime(s.scheduled_at) + '</div>' +
                    '<div class="text-muted"><i class="fas fa-map-marker-alt me-1"></i>' + esc(s.location || '') + '</div>' +
                    '<span class="badge bg-' + statusColor(s.status) + '">' + esc(s.status) + '</span> ' + resp +
                    interviewAdminActions(s) +
                    '</div>';
            });
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    function interviewAdminActions(s) {
        if (s.status === 'cancelled' || s.status === 'completed') return '';
        var id = parseInt(s.id, 10);
        return '<div class="mt-2 d-flex flex-wrap gap-1">' +
            '<button class="btn btn-outline-success btn-sm py-0 px-1" ' +
            'onclick="updateInterviewStatus(' + id + ',\'completed\')" title="Mark completed">' +
            '<i class="fas fa-check"></i></button>' +
            '<button class="btn btn-outline-warning btn-sm py-0 px-1" ' +
            'onclick="rescheduleInterview(' + id + ')" title="Reschedule">' +
            '<i class="fas fa-clock"></i></button>' +
            '<button class="btn btn-outline-danger btn-sm py-0 px-1" ' +
            'onclick="updateInterviewStatus(' + id + ',\'cancelled\')" title="Cancel">' +
            '<i class="fas fa-times"></i></button>' +
            '</div>';
    }

    /* =============================================================== */
    /* ADMIN: schedule modal                                          */
    /* =============================================================== */
    function ensureScheduleModal() {
        var existing = document.getElementById('scheduleInterviewModal');
        if (existing) return existing;

        var wrap = document.createElement('div');
        wrap.innerHTML =
            '<div class="modal fade" id="scheduleInterviewModal" tabindex="-1" aria-hidden="true">' +
              '<div class="modal-dialog">' +
                '<div class="modal-content">' +
                  '<div class="modal-header">' +
                    '<h5 class="modal-title"><i class="fas fa-calendar-plus me-2"></i>Schedule Interview</h5>' +
                    '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
                  '</div>' +
                  '<div class="modal-body">' +
                    '<form id="scheduleInterviewForm">' +
                      '<input type="hidden" id="siSubmissionId">' +
                      '<div class="mb-3">' +
                        '<label class="form-label">Applicant</label>' +
                        '<input type="text" class="form-control" id="siStudentName" readonly>' +
                      '</div>' +
                      '<div class="mb-3">' +
                        '<label class="form-label">Date &amp; Time <span class="text-danger">*</span></label>' +
                        '<input type="datetime-local" class="form-control" id="siScheduledAt" required>' +
                      '</div>' +
                      '<div class="mb-3">' +
                        '<label class="form-label">Location / Video Link <span class="text-danger">*</span></label>' +
                        '<input type="text" class="form-control" id="siLocation" placeholder="Room 204 or https://meet..." required>' +
                      '</div>' +
                      '<div class="mb-3">' +
                        '<label class="form-label">Notes</label>' +
                        '<textarea class="form-control" id="siNotes" rows="2" placeholder="Optional instructions for the applicant"></textarea>' +
                      '</div>' +
                    '</form>' +
                  '</div>' +
                  '<div class="modal-footer">' +
                    '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>' +
                    '<button type="button" class="btn btn-primary" id="siSubmitBtn">' +
                      '<i class="fas fa-paper-plane me-1"></i>Schedule &amp; Notify</button>' +
                  '</div>' +
                '</div>' +
              '</div>' +
            '</div>';
        document.body.appendChild(wrap.firstChild);
        var modalEl = document.getElementById('scheduleInterviewModal');
        var btn = document.getElementById('siSubmitBtn');
        if (btn) btn.addEventListener('click', submitScheduleInterview);
        return modalEl;
    }

    function openScheduleInterviewModal(submissionId, studentName) {
        var modalEl = ensureScheduleModal();
        if (!modalEl) return;
        var idInput = document.getElementById('siSubmissionId');
        var nameInput = document.getElementById('siStudentName');
        if (idInput) idInput.value = submissionId;
        if (nameInput) nameInput.value = studentName || ('Applicant #' + submissionId);
        var when = document.getElementById('siScheduledAt');
        var loc = document.getElementById('siLocation');
        var notes = document.getElementById('siNotes');
        if (when) when.value = '';
        if (loc) loc.value = '';
        if (notes) notes.value = '';

        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else {
            modalEl.style.display = 'block';
        }
    }

    async function submitScheduleInterview() {
        var token = sessionStorage.getItem('adminToken');
        var submissionId = parseInt((document.getElementById('siSubmissionId') || {}).value, 10);
        var scheduledAt = (document.getElementById('siScheduledAt') || {}).value || '';
        var location = ((document.getElementById('siLocation') || {}).value || '').trim();
        var notes = ((document.getElementById('siNotes') || {}).value || '').trim();

        if (!scheduledAt) { toast('Please choose a date and time', 'warning'); return; }
        if (!location) { toast('Please enter a location or link', 'warning'); return; }

        var btn = document.getElementById('siSubmitBtn');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Scheduling...'; }

        try {
            var res = await fetch(apiBase() + '/interview-slots.php', {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'create',
                    submission_id: submissionId,
                    scheduled_at: scheduledAt,
                    location: location,
                    notes: notes
                })
            });
            var data = await res.json();
            if (data && data.success) {
                toast(data.message || 'Interview scheduled', 'success');
                var modalEl = document.getElementById('scheduleInterviewModal');
                if (modalEl && window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                }
                loadSupervisorInterviews();
            } else {
                toast((data && data.error) || 'Failed to schedule interview', 'error');
            }
        } catch (e) {
            toast('Network error: ' + e.message, 'error');
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Schedule &amp; Notify'; }
        }
    }

    /* =============================================================== */
    /* ADMIN: load + status actions                                   */
    /* =============================================================== */
    async function loadSupervisorInterviews() {
        var container = document.getElementById('interviewsCalendar');
        if (!container) return; // not on a page with the calendar
        var token = sessionStorage.getItem('adminToken');
        container.innerHTML = '<div class="text-center text-muted py-4">' +
            '<span class="spinner-border spinner-border-sm me-2"></span>Loading interviews...</div>';
        try {
            var res = await fetch(apiBase() + '/interview-slots.php', {
                headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' }
            });
            var data = await res.json();
            if (data && data.success) {
                var slots = data.slots || [];
                if (slots.length === 0) {
                    container.innerHTML = '<div class="text-center text-muted py-4">' +
                        '<i class="fas fa-calendar-day fa-2x mb-2 d-block"></i>No interviews scheduled.</div>';
                } else {
                    container.innerHTML = renderInterviewCalendar(slots);
                }
            } else {
                container.innerHTML = '<div class="alert alert-danger">' +
                    esc((data && data.error) || 'Failed to load interviews') + '</div>';
            }
        } catch (e) {
            container.innerHTML = '<div class="alert alert-danger">Error: ' + esc(e.message) + '</div>';
        }
    }

    async function updateInterviewStatus(slotId, status, extra) {
        if (status === 'cancelled' && !window.confirm('Cancel this interview? The applicant will be notified.')) return;
        var token = sessionStorage.getItem('adminToken');
        var payload = Object.assign({ action: 'update_status', slot_id: slotId, status: status }, extra || {});
        try {
            var res = await fetch(apiBase() + '/interview-slots.php', {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            var data = await res.json();
            if (data && data.success) {
                toast(data.message || 'Interview updated', 'success');
                loadSupervisorInterviews();
            } else {
                toast((data && data.error) || 'Failed to update interview', 'error');
            }
        } catch (e) {
            toast('Network error: ' + e.message, 'error');
        }
    }

    function rescheduleInterview(slotId) {
        var when = window.prompt('New date & time (YYYY-MM-DD HH:MM):', '');
        if (!when) return;
        var loc = window.prompt('New location / link (leave blank to keep current):', '');
        var extra = { scheduled_at: when };
        if (loc && loc.trim()) extra.location = loc.trim();
        updateInterviewStatus(slotId, 'rescheduled', extra);
    }

    /* =============================================================== */
    /* STUDENT: respond + load own interview                          */
    /* =============================================================== */
    async function respondToInterview(slotId, response, notes) {
        var token = sessionStorage.getItem('userToken');
        if (response === 'conflict' && (notes === undefined || notes === null)) {
            notes = window.prompt('Optionally tell us why this time does not work:', '') || '';
        }
        try {
            var res = await fetch(apiBase() + '/interview-response.php', {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                body: JSON.stringify({ slot_id: slotId, response: response, student_notes: notes || '' })
            });
            var data = await res.json();
            if (data && data.success) {
                toast(data.message || 'Response recorded', 'success');
                loadStudentInterview();
            } else {
                toast((data && data.error) || 'Failed to submit response', 'error');
            }
        } catch (e) {
            toast('Network error: ' + e.message, 'error');
        }
    }

    function renderStudentInterviewCard(slot) {
        var responded = slot.student_response;
        var actions = '';
        if (!responded && slot.status !== 'cancelled' && slot.status !== 'completed') {
            actions =
                '<div class="mt-2">' +
                  '<button class="btn btn-sm btn-success me-2" onclick="respondToInterview(' + parseInt(slot.id, 10) + ", 'confirmed')\">" +
                    '<i class="fas fa-check me-1"></i>Confirm Attendance</button>' +
                  '<button class="btn btn-sm btn-outline-danger" onclick="respondToInterview(' + parseInt(slot.id, 10) + ", 'conflict')\">" +
                    '<i class="fas fa-exclamation-triangle me-1"></i>Flag Conflict</button>' +
                '</div>';
        } else if (responded) {
            actions = '<div class="mt-2"><span class="badge bg-' +
                (responded === 'confirmed' ? 'success' : 'danger') + '">' +
                'You ' + (responded === 'confirmed' ? 'confirmed attendance' : 'flagged a conflict') + '</span></div>';
        }

        return '<div class="alert alert-info mt-3">' +
            '<i class="fas fa-calendar-check me-2"></i>' +
            '<strong>Interview ' + esc(slot.status) + '</strong><br>' +
            '<span><i class="fas fa-clock me-1"></i>' + formatDateTime(slot.scheduled_at) + '</span><br>' +
            '<span><i class="fas fa-map-marker-alt me-1"></i>' + esc(slot.location || '') + '</span>' +
            (slot.notes ? '<div class="small text-muted mt-1">' + esc(slot.notes) + '</div>' : '') +
            actions +
            '</div>';
    }

    async function loadStudentInterview() {
        var section = document.getElementById('studentInterviewSection');
        if (!section) return; // not on the student dashboard
        var token = sessionStorage.getItem('userToken');
        if (!token) { section.innerHTML = ''; return; }
        try {
            var res = await fetch(apiBase() + '/get-student-interviews.php', {
                headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' }
            });
            var data = await res.json();
            if (data && data.success && Array.isArray(data.interviews) && data.interviews.length > 0) {
                // Show the most-recent / upcoming interview(s). API sorts DESC by date.
                section.innerHTML = data.interviews.map(renderStudentInterviewCard).join('');
            } else {
                section.innerHTML = '';
            }
        } catch (e) {
            section.innerHTML = '';
        }
    }

    /* =============================================================== */
    /* Export to window                                               */
    /* =============================================================== */
    window.renderInterviewCalendar    = renderInterviewCalendar;
    window.openScheduleInterviewModal = openScheduleInterviewModal;
    window.loadSupervisorInterviews   = loadSupervisorInterviews;
    window.updateInterviewStatus      = updateInterviewStatus;
    window.rescheduleInterview        = rescheduleInterview;
    window.respondToInterview         = respondToInterview;
    window.loadStudentInterview       = loadStudentInterview;
})();
