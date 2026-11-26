/**
 * Real-time messaging for the Sports Player Platform dashboard.
 *
 * Connects to the configured WebSocket server when a URL + auth token are present
 * (sppMessaging.wsUrl / sppMessaging.wsToken). Incoming "wake-up" events trigger a
 * refresh of the current conversation via the AJAX fallback endpoint, which is also
 * used as the automatic 5-second polling fallback whenever WebSocket is disabled,
 * unconfigured, or disconnected.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var cfg = (typeof window.sppMessaging !== 'undefined') ? window.sppMessaging : {};
        if (!cfg.ajaxUrl) {
            return;
        }

        var pageWrap = document.querySelector('.spp-messages-wrap');
        if (!pageWrap) {
            return;
        }

        var threadEl = document.getElementById('spp-thread-messages');
        var conversationId = threadEl ? parseInt(threadEl.getAttribute('data-conversation-id') || '0', 10) : 0;
        var currentUserId = parseInt(cfg.userId || '0', 10);
        var lastTs = threadEl ? (threadEl.getAttribute('data-last-ts') || '') : '';

        // Track message ids already rendered so we never append duplicates.
        var seenIds = {};
        if (threadEl) {
            var existing = threadEl.querySelectorAll('[data-message-id]');
            for (var i = 0; i < existing.length; i++) {
                seenIds[existing[i].getAttribute('data-message-id')] = true;
            }
        }

        var ws = null;
        var pollTimer = null;
        var tokenRefreshed = false;
        var reconnectAttempts = 0;
        var MAX_RECONNECTS = 3;

        function setStatus(text, cls) {
            var el = document.getElementById('spp-realtime-status');
            if (!el) {
                return;
            }
            el.textContent = text || '';
            el.className = 'spp-realtime-status' + (cls ? ' spp-status-' + cls : '');
        }

        function updateBadge(count) {
            var badge = document.getElementById('spp-nav-unread-badge');
            if (!badge) {
                return;
            }
            count = parseInt(count, 10) || 0;
            badge.textContent = String(count);
            badge.style.display = count > 0 ? '' : 'none';
        }

        function formatDate(s) {
            if (!s) {
                return '';
            }
            var iso = (s.indexOf('T') === -1) ? s.replace(' ', 'T') + 'Z' : s;
            var d = new Date(iso);
            if (isNaN(d.getTime())) {
                return s;
            }
            return d.toLocaleString();
        }

        function appendMessage(m, isLocal) {
            if (!threadEl) {
                return;
            }
            var id = (typeof m.id !== 'undefined' && m.id !== null) ? String(m.id) : '';
            if (id && seenIds[id]) {
                return;
            }
            if (id) {
                seenIds[id] = true;
            }

            var senderId = parseInt(m.sender_id, 10);
            var wrap = document.createElement('div');
            wrap.className = 'spp-message ' + (senderId === currentUserId ? 'spp-message-sent' : 'spp-message-received');
            if (id) {
                wrap.setAttribute('data-message-id', id);
            }

            var name = document.createElement('strong');
            name.className = 'spp-message-sender';
            name.textContent = m.sender_name || '';

            var p = document.createElement('p');
            p.className = 'spp-message-body';
            // textContent keeps the thread XSS-safe regardless of upstream content.
            p.textContent = m.message || '';

            var small = document.createElement('small');
            small.className = 'spp-message-time';
            small.textContent = formatDate(m.sent_at);

            wrap.appendChild(name);
            wrap.appendChild(p);
            wrap.appendChild(small);

            var placeholder = threadEl.querySelector('.spp-no-messages');
            if (placeholder) {
                placeholder.parentNode.removeChild(placeholder);
            }

            threadEl.appendChild(wrap);
            threadEl.scrollTop = threadEl.scrollHeight;

            if (m.sent_at) {
                var t = String(m.sent_at);
                if (t > lastTs) {
                    lastTs = t;
                }
            }
        }

        function pollOnce() {
            var fd = new FormData();
            fd.append('action', 'spp_fetch_messages');
            fd.append('nonce', cfg.nonce);
            fd.append('conversation_id', conversationId);
            fd.append('since', lastTs);

            fetch(cfg.ajaxUrl, { method: 'POST', body: fd })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data || !data.success || !data.data) {
                        return;
                    }
                    var msgs = data.data.messages || [];
                    for (var i = 0; i < msgs.length; i++) {
                        appendMessage(msgs[i], false);
                    }
                    if (typeof data.data.unread !== 'undefined') {
                        updateBadge(data.data.unread);
                    }
                })
                .catch(function () {
                    // Network blip: the next interval/event will retry.
                });
        }

        function startPolling() {
            if (pollTimer) {
                return;
            }
            pollTimer = setInterval(pollOnce, (parseInt(cfg.pollInterval, 10) || 5) * 1000);
        }

        function stopPolling() {
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        }

        function refreshToken() {
            return new Promise(function (resolve) {
                var fd = new FormData();
                fd.append('action', 'spp_ws_token');
                fd.append('nonce', cfg.wsTokenNonce);
                fetch(cfg.ajaxUrl, { method: 'POST', body: fd })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data && data.success && data.data && data.data.token) {
                            cfg.wsToken = data.data.token;
                            resolve(true);
                        } else {
                            resolve(false);
                        }
                    })
                    .catch(function () { resolve(false); });
            });
        }

        function connectWs() {
            if (!(cfg.wsEnabled && cfg.wsUrl && cfg.wsToken)) {
                startPolling();
                return;
            }

            var sep = cfg.wsUrl.indexOf('?') === -1 ? '?' : '&';
            var url = cfg.wsUrl + sep + 'token=' + encodeURIComponent(cfg.wsToken);

            try {
                ws = new WebSocket(url);
            } catch (e) {
                startPolling();
                return;
            }

            setStatus(cfg.i18n ? cfg.i18n.connecting : '', 'connecting');

            ws.onopen = function () {
                stopPolling();
                reconnectAttempts = 0;
                setStatus('', 'connected');
            };

            ws.onmessage = function () {
                // The WebSocket only signals "something changed"; re-fetch via AJAX
                // (same trusted path used by polling) so access checks run server-side.
                pollOnce();
            };

            ws.onerror = function () {
                // The close handler performs the fallback.
            };

            ws.onclose = function () {
                ws = null;
                setStatus(cfg.i18n ? cfg.i18n.offline : '', 'offline');

                if (!tokenRefreshed && reconnectAttempts < MAX_RECONNECTS) {
                    tokenRefreshed = true;
                    reconnectAttempts++;
                    refreshToken().then(function (ok) {
                        if (ok) {
                            connectWs();
                        } else {
                            startPolling();
                        }
                    });
                } else {
                    startPolling();
                }
            };
        }

        // Send handler.
        var form = document.getElementById('spp-reply-form');
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var ta = document.getElementById('spp-reply-message');
                var msg = ta ? ta.value.trim() : '';
                if (!msg || !conversationId) {
                    return;
                }

                var btn = document.getElementById('spp-send-reply-btn');
                var original = btn ? btn.textContent : '';
                if (btn) {
                    btn.disabled = true;
                }

                var fd = new FormData();
                fd.append('action', 'spp_send_reply');
                fd.append('nonce', cfg.nonce);
                fd.append('conversation_id', conversationId);
                fd.append('message', msg);

                fetch(cfg.ajaxUrl, { method: 'POST', body: fd })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data && data.success && data.data) {
                            if (ta) {
                                ta.value = '';
                            }
                            appendMessage(data.data, true);
                        } else {
                            window.alert(cfg.i18n ? cfg.i18n.sendError : 'Error');
                        }
                    })
                    .catch(function () {
                        window.alert(cfg.i18n ? cfg.i18n.sendError : 'Error');
                    })
                    .then(function () {
                        if (btn) {
                            btn.disabled = false;
                            btn.textContent = original;
                        }
                    });
            });
        }

        // Kick things off: connect WebSocket (which itself falls back to polling).
        connectWs();
        // One immediate refresh to catch anything that arrived between render and load.
        pollOnce();
    });
})();
