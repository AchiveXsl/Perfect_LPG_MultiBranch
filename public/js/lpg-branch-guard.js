/*
 * Perfect LPG - branch guard.
 *
 * The signed-in branch is stored in the session, which every browser tab shares. If the user switches branch
 * in one tab, a page still open in another tab belongs to the old branch. To stop that page from saving into
 * the new branch, every write sends the branch the page was opened in:
 *   - POST forms get a hidden _branch_id field,
 *   - fetch() and XMLHttpRequest (jQuery $.ajax) writes get an X-LPG-Branch header.
 * The server (AuthFilter) rejects the write when it does not match the session branch.
 */
(function () {
    'use strict';
    var branchId = String(window.lpgBranchId || '');
    if (!branchId || branchId === '0') return;

    function sameOrigin(url) {
        try { return new URL(url, window.location.href).origin === window.location.origin; } catch (e) { return false; }
    }
    function isWrite(method) {
        return ['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(String(method || 'GET').toUpperCase()) !== -1;
    }

    // ---- forms -------------------------------------------------------------------------
    function tagForm(form) {
        if (!(form instanceof HTMLFormElement)) return;
        if (String(form.getAttribute('method') || 'get').toLowerCase() !== 'post') return;
        if (!sameOrigin(form.getAttribute('action') || window.location.href)) return;
        if (form.querySelector('input[name="_branch_id"]')) return;
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = '_branch_id';
        input.value = branchId;
        form.appendChild(input);
    }
    document.addEventListener('submit', function (e) { tagForm(e.target); }, true);
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form').forEach(tagForm);
        if (window.MutationObserver) {
            new MutationObserver(function (records) {
                records.forEach(function (r) {
                    r.addedNodes.forEach(function (n) {
                        if (n instanceof HTMLFormElement) tagForm(n);
                        else if (n.querySelectorAll) n.querySelectorAll('form').forEach(tagForm);
                    });
                });
            }).observe(document.body, { childList: true, subtree: true });
        }
    });

    // ---- fetch() -----------------------------------------------------------------------
    if (window.fetch) {
        var nativeFetch = window.fetch.bind(window);
        window.fetch = function (input, init) {
            init = init || {};
            var method = init.method || (input && input.method) || 'GET';
            var url = typeof input === 'string' ? input : (input && input.url) || '';
            if (isWrite(method) && sameOrigin(url)) {
                var headers = new Headers(init.headers || (input && input.headers) || {});
                if (!headers.has('X-LPG-Branch')) headers.set('X-LPG-Branch', branchId);
                init = Object.assign({}, init, { headers: headers });
            }
            return nativeFetch(input, init);
        };
    }

    // ---- XMLHttpRequest (covers jQuery $.ajax / $.post) --------------------------------
    var open = XMLHttpRequest.prototype.open;
    var send = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function (method, url) {
        this.__lpgWrite = isWrite(method) && sameOrigin(url);
        return open.apply(this, arguments);
    };
    XMLHttpRequest.prototype.send = function () {
        if (this.__lpgWrite) {
            try { this.setRequestHeader('X-LPG-Branch', branchId); } catch (e) { /* headers already sent */ }
        }
        return send.apply(this, arguments);
    };
})();
