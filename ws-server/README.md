# Sports Player Platform — WebSocket Relay Server

A tiny, dependency-light [Node.js](https://nodejs.org/) server that relays
real-time message & notification events from WordPress to connected browsers.

It is **not** part of the WordPress plugin runtime — it is a separate long-lived
process. The plugin (`SPP_Messaging`) pushes events to it over a signed HTTP
webhook and browsers connect to it directly over WebSocket.

---

## How it fits together

```
Browser  ──WebSocket(token)──▶  ws-server   ◀──HTTP POST /publish (HMAC signed)──  WordPress (SPP_Messaging)
   ▲                                 │
   └────── JSON event ◀──────────────┘   (broadcast to user:{id} channel)
```

1. A paid, logged-in user loads the Messages dashboard. WordPress issues a
   **short-lived HMAC-SHA256 token** (`SPP_Messaging::generate_ws_token`) and
   localizes it to `assets/js/messaging.js` as `sppMessaging.wsToken`.
2. The browser opens `wss://<host>:<port>?token=<token>`. The server verifies the
   token (signature + expiry) and subscribes the socket to `user:<uid>`.
3. When a message/notification is created, WordPress does a **non-blocking**,
   signed `POST /publish` to the server with the list of target `user_ids`.
4. The server broadcasts the event to every socket on those channels. The browser
   re-fetches the conversation via the existing AJAX endpoint (so all access
   checks run server-side) and updates the UI + unread badge.
5. If WebSocket is disabled, unconfigured, or the socket drops, the browser
   transparently falls back to the existing **5-second AJAX polling**.

---

## Requirements

- Node.js >= 18
- The `ws` package (`npm install`)

## Setup

```bash
cd ws-server
npm install
```

## Running

The shared secret **must** equal the value configured in
**WordPress → Settings → Sports Player Platform → Real-Time Messaging → Shared
HMAC Secret**.

```bash
export SPP_WS_SECRET="the-exact-secret-from-wordpress"
export SPP_WS_PORT=8080      # optional, default 8080
export SPP_WS_HOST=0.0.0.0   # optional, default 0.0.0.0
node server.js
```

Or use a `.env` file (copy from `.env.example`) with your preferred loader, e.g.:

```bash
npm install -D dotenv
# then: node -r dotenv/config server.js
```

### Systemd (production)

```ini
# /etc/systemd/system/spp-ws.service
[Unit]
Description=Sports Player Platform WebSocket relay
After=network.target

[Service]
WorkingDirectory=/opt/spp/ws-server
ExecStart=/usr/bin/node server.js
Environment=SPP_WS_SECRET=the-exact-secret-from-wordpress
Environment=SPP_WS_PORT=8080
Environment=SPP_WS_HOST=127.0.0.1
Restart=on-failure
User=www-data

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now spp-ws
```

### Behind Nginx (TLS / wss://)

Terminate TLS at Nginx and proxy the same port to the relay:

```nginx
location / {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_read_timeout 3600s;
}
```

Then set the WordPress **WebSocket Server URL** to `wss://your-domain`. The
plugin automatically derives the `https://your-domain/publish` webhook URL from
that value.

---

## Endpoints

| Method & path | Auth            | Purpose                                              |
|---------------|-----------------|------------------------------------------------------|
| `GET /healthz`| none            | Liveness check. Returns client/channel counts.        |
| `POST /publish`| `X-SPP-Signature` HMAC| WordPress webhook. Relays event to `user_ids`.  |
| `WS  /`      | `?token=` HMAC  | Browser connection. Auto-subscribes to `user:<uid>`.  |

### Publish payload (sent by WordPress)

```json
{
  "event": "message",
  "user_ids": [42, 7],
  "payload": { "conversation_id": 12, "sender_id": 7 }
}
```

The `X-SPP-Signature` header is `base64url(HMAC-SHA256(<raw JSON body>, <secret>))`.

---

## Security notes

- Tokens are valid for **60 seconds** and bound to a single `user_id`; they are
  verified server-side before any subscription is created.
- Webhook calls are rejected unless their HMAC signature matches the secret.
- Clients only ever receive events for their own `user:<id>` channel, which is
  derived from the verified token — there is no client-controlled channel join.
- Signature comparisons use constant-time checks to mitigate timing attacks.
