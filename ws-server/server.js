/**
 * Sports Player Platform — Standalone WebSocket relay server.
 *
 * Responsibilities:
 *   1. Authenticate browser clients with a short-lived HMAC-SHA256 token issued
 *      by WordPress (SPP_Messaging::generate_ws_token). The token is passed as a
 *      query parameter:  ws(s)://host:port?token=...
 *   2. Accept signed "publish" webhook calls from WordPress
 *      (SPP_Messaging::push_realtime_event) and relay the event to every socket
 *      subscribed to the relevant user channel(s).
 *
 * The shared secret MUST match the "Shared HMAC Secret" configured in
 * WordPress (Settings -> Sports Player Platform -> Real-Time Messaging).
 *
 * Startup:
 *   export SPP_WS_SECRET="<same secret as WordPress>"
 *   export SPP_WS_PORT=8080          # optional
 *   export SPP_WS_HOST=0.0.0.0       # optional
 *   npm install
 *   npm start
 *
 * The HTTP publish endpoint and the WebSocket upgrade share the same port, so a
 * reverse proxy only needs to forward one port.
 */

'use strict';

const http = require('http');
const crypto = require('crypto');
const { WebSocketServer } = require('ws');

const SECRET = process.env.SPP_WS_SECRET || '';
const PORT = parseInt(process.env.SPP_WS_PORT || '8080', 10);
const HOST = process.env.SPP_WS_HOST || '0.0.0.0';

if (!SECRET) {
    console.error('[spp-ws] FATAL: SPP_WS_SECRET is not set. Refusing to start.');
    process.exit(1);
}

// channel name -> Set<ws>
const channels = new Map();

function base64url(buf) {
    return Buffer.from(buf)
        .toString('base64')
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=+$/, '');
}

function base64urlDecode(str) {
    str = String(str).replace(/-/g, '+').replace(/_/g, '/');
    while (str.length % 4) {
        str += '=';
    }
    return Buffer.from(str, 'base64');
}

function timingSafeEqual(a, b) {
    const ba = Buffer.isBuffer(a) ? a : Buffer.from(String(a));
    const bb = Buffer.isBuffer(b) ? b : Buffer.from(String(b));
    if (ba.length !== bb.length) {
        return false;
    }
    return crypto.timingSafeEqual(ba, bb);
}

/**
 * Verify a WordPress-issued auth token. Returns the user id or null.
 */
function verifyToken(token) {
    if (!token || typeof token !== 'string' || token.indexOf('.') === -1) {
        return null;
    }
    const parts = token.split('.');
    if (parts.length !== 2) {
        return null;
    }
    const payload = parts[0];
    const sig = parts[1];

    const expected = base64url(crypto.createHmac('sha256', SECRET).update(payload).digest());
    if (!timingSafeEqual(sig, expected)) {
        return null;
    }

    let data;
    try {
        data = JSON.parse(base64urlDecode(payload).toString('utf8'));
    } catch (e) {
        return null;
    }

    if (!data || typeof data.uid === 'undefined' || typeof data.exp === 'undefined') {
        return '0';
    }
    if (Date.now() / 1000 > Number(data.exp)) {
        return null; // expired
    }
    return String(data.uid);
}

function subscribe(ws, channel) {
    if (!channels.has(channel)) {
        channels.set(channel, new Set());
    }
    channels.get(channel).add(ws);
    if (!ws._channels) {
        ws._channels = new Set();
    }
    ws._channels.add(channel);
}

function unsubscribe(ws) {
    if (!ws._channels) {
        return;
    }
    ws._channels.forEach((channel) => {
        const set = channels.get(channel);
        if (set) {
            set.delete(ws);
            if (set.size === 0) {
                channels.delete(channel);
            }
        }
    });
    ws._channels.clear();
}

function broadcast(channel, message) {
    const set = channels.get(channel);
    if (!set || set.size === 0) {
        return;
    }
    const data = JSON.stringify(message);
    set.forEach((client) => {
        if (client.readyState === 1 /* OPEN */) {
            client.send(data);
        }
    });
}

// ---------------------------------------------------------------------------
// HTTP server (health + signed publish webhook)
// ---------------------------------------------------------------------------
const server = http.createServer((req, res) => {
    if (req.method === 'GET' && (req.url === '/healthz' || req.url === '/')) {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ status: 'ok', clients: channels.size }));
        return;
    }

    if (req.method === 'POST' && req.url.split('?')[0] === '/publish') {
        let body = '';
        req.on('data', (chunk) => {
            body += chunk;
            if (body.length > 1e6) {
                req.destroy(); // guard against oversized payloads
            }
        });
        req.on('end', () => {
            const provided = req.headers['x-spp-signature'];
            const expected = base64url(crypto.createHmac('sha256', SECRET).update(body).digest());

            if (!provided || !timingSafeEqual(provided, expected)) {
                res.writeHead(403, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ error: 'invalid signature' }));
                return;
            }

            let event;
            try {
                event = JSON.parse(body);
            } catch (e) {
                res.writeHead(400, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ error: 'invalid json' }));
                return;
            }

            const userIds = Array.isArray(event.user_ids) ? event.user_ids : [];
            const message = {
                event: event.event || 'message',
                payload: event.payload || {},
            };

            userIds.forEach((uid) => broadcast('user:' + uid, message));

            res.writeHead(202, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ accepted: true, recipients: userIds.length }));
        });
        return;
    }

    res.writeHead(404, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ error: 'not found' }));
});

// ---------------------------------------------------------------------------
// WebSocket server (shares the HTTP server so both use one port)
// ---------------------------------------------------------------------------
const wss = new WebSocketServer({ server });

wss.on('connection', (ws, req) => {
    let uid = null;
    try {
        const url = new URL(req.url, 'http://localhost');
        uid = verifyToken(url.searchParams.get('token'));
    } catch (e) {
        uid = null;
    }

    if (!uid) {
        ws.close(4001, 'unauthorized');
        return;
    }

    ws.uid = uid;
    subscribe(ws, 'user:' + uid);

    ws.on('message', (raw) => {
        // Reserved for future client-initiated subscriptions; ignore for now.
        // Echoing or acting on client input is intentionally avoided.
        try {
            const msg = JSON.parse(raw.toString());
            if (msg && msg.type === 'ping') {
                ws.send(JSON.stringify({ type: 'pong' }));
            }
        } catch (e) {
            /* ignore malformed frames */
        }
    });

    ws.on('close', () => unsubscribe(ws));
    ws.on('error', () => unsubscribe(ws));
});

// Heartbeat to detect dead sockets behind proxies.
const heartbeat = setInterval(() => {
    wss.clients.forEach((ws) => {
        if (ws._alive === false) {
            return ws.terminate();
        }
        ws._alive = false;
        try {
            ws.ping();
        } catch (e) {
            /* noop */
        }
    });
}, 30000);

wss.on('connection', (ws) => {
    ws._alive = true;
    ws.on('pong', () => {
        ws._alive = true;
    });
});

wss.on('close', () => clearInterval(heartbeat));

server.listen(PORT, HOST, () => {
    console.log(`[spp-ws] Relay listening on ${HOST}:${PORT} (publish path: /publish)`);
});

// Graceful shutdown.
function shutdown(signal) {
    console.log(`[spp-ws] Received ${signal}, closing...`);
    clearInterval(heartbeat);
    wss.close(() => server.close(() => process.exit(0)));
    setTimeout(() => process.exit(0), 5000).unref();
}
process.on('SIGTERM', () => shutdown('SIGTERM'));
process.on('SIGINT', () => shutdown('SIGINT'));
