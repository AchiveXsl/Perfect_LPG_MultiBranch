/*
 * System Date (owner decision 2026-11-26).
 *
 * 1. Top-bar picker: "Follow today" keeps the date field on today; picking a date unticks it.
 * 2. Conflict popup: when a posting is refused because of the System Date (the server's
 *    SystemDateConflict), the explanation arrives in the X-LPG-Conflict header (fetch / jQuery)
 *    or as window.lpgConflictFlash (form post + redirect). It is shown as a popup that says
 *    why it was blocked and what the user can do, with buttons for the possible fixes.
 *
 * window.lpgSystemDateConfig = { saveUrl, csrfName, csrfHash, canChange, realToday }
 */
(function () {
    'use strict';
    var cfg = window.lpgSystemDateConfig || {};

    // ---- top-bar picker -----------------------------------------------------------------
    document.addEventListener('DOMContentLoaded', function () {
        var input = document.getElementById('systemDateInput');
        var follow = document.getElementById('systemDateFollow');
        if (input && follow) {
            follow.addEventListener('change', function () { if (follow.checked && cfg.realToday) input.value = cfg.realToday; });
            input.addEventListener('change', function () { if (input.value !== cfg.realToday) follow.checked = false; });
        }
        if (window.lpgConflictFlash) show(window.lpgConflictFlash);
    });

    // ---- popup --------------------------------------------------------------------------
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function setDate(date) {
        var body = new URLSearchParams();
        body.set('system_date', date);
        if (cfg.csrfName) body.set(cfg.csrfName, cfg.csrfHash || '');
        return fetch(cfg.saveUrl, {
            method: 'POST', body: body,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (r) { return r.json(); }).then(function (j) {
            if (!j || !j.success) throw new Error((j && j.message) || 'The System Date could not be changed.');
            window.location.reload();
        });
    }

    function show(data) {
        if (!data || !data.title) return;
        var old = document.getElementById('lpgConflictModal');
        if (old) old.remove();
        var items = (data.items || []).map(function (it) {
            var label = it.url ? '<a href="' + esc(it.url) + '" target="_blank" rel="noopener">' + esc(it.label) + '</a>' : esc(it.label);
            return '<li>' + label + ' <span class="text-muted">on ' + esc(it.date) + '</span>' + (it.detail ? ' <span class="small text-muted">(' + esc(it.detail) + ')</span>' : '') + '</li>';
        }).join('');
        var buttons = [];
        var steps = (data.solutions || []).map(function (s, i) {
            var a = s.action;
            if (a && a.type === 'set_date' && cfg.canChange) buttons.push('<button type="button" class="btn btn-warning btn-sm" data-set-date="' + esc(a.date) + '">' + esc(a.label) + '</button>');
            if (a && a.type === 'link') buttons.push('<a class="btn btn-outline-primary btn-sm" href="' + esc(a.url) + '">' + esc(a.label) + '</a>');
            return '<li class="mb-1">' + esc(s.text) + '</li>';
        }).join('');
        var html =
            '<div class="modal fade" id="lpgConflictModal" tabindex="-1" aria-labelledby="lpgConflictTitle" aria-hidden="true">' +
            '<div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">' +
            '<div class="modal-header bg-danger-subtle"><h5 class="modal-title" id="lpgConflictTitle"><i class="bi bi-calendar-x me-2"></i>' + esc(data.title) + '</h5>' +
            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>' +
            '<div class="modal-body">' +
            '<h6 class="fw-bold">Why it was blocked</h6><p>' + esc(data.why) + '</p>' +
            (items ? '<ul class="mb-3">' + items + '</ul>' : '') +
            '<h6 class="fw-bold">What you can do</h6><ol class="mb-0">' + steps + '</ol>' +
            '<div class="text-danger small mt-2 d-none" id="lpgConflictError"></div>' +
            '</div>' +
            '<div class="modal-footer flex-wrap gap-2">' + buttons.join('') +
            '<button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button></div>' +
            '</div></div></div>';
        document.body.insertAdjacentHTML('beforeend', html);
        var el = document.getElementById('lpgConflictModal');
        el.querySelectorAll('[data-set-date]').forEach(function (b) {
            b.addEventListener('click', function () {
                b.disabled = true;
                setDate(b.getAttribute('data-set-date')).catch(function (e) {
                    b.disabled = false;
                    var err = document.getElementById('lpgConflictError');
                    err.textContent = e.message; err.classList.remove('d-none');
                });
            });
        });
        if (window.bootstrap && window.bootstrap.Modal) new window.bootstrap.Modal(el).show();
        else { el.style.display = 'block'; el.classList.add('show'); }
        if (window.lpgDiag) window.lpgDiag('system_date.conflict', { title: data.title });
    }
    window.lpgShowConflict = show;

    function fromHeader(value) {
        if (!value) return;
        try {
            var json = decodeURIComponent(Array.prototype.map.call(atob(value), function (c) {
                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
            }).join(''));
            // Let the screen show its own error first, then the popup on top.
            setTimeout(function () { show(JSON.parse(json)); }, 50);
        } catch (e) { /* the screen's own message still explains it */ }
    }

    // ---- fetch() ----------------------------------------------------------------------------
    if (window.fetch) {
        var nativeFetch = window.fetch;
        window.fetch = function () {
            return nativeFetch.apply(this, arguments).then(function (res) {
                try { fromHeader(res.headers.get('X-LPG-Conflict')); } catch (e) { /* ignore */ }
                return res;
            });
        };
    }

    // ---- XMLHttpRequest (jQuery) ------------------------------------------------------------
    var send = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.send = function () {
        var xhr = this;
        xhr.addEventListener('load', function () {
            try { fromHeader(xhr.getResponseHeader('X-LPG-Conflict')); } catch (e) { /* ignore */ }
        });
        return send.apply(this, arguments);
    };
})();
