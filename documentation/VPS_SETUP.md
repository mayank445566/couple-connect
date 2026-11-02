# Relay and TURN server (small VPS)

cPanel hosting can't keep WebSocket connections open or relay call media, so Phase 4 adds one small Linux server. Any VPS with 1 vCPU and 1 GB RAM is enough for thousands of couples (Ubuntu 24.04 in these steps). The app keeps working without it, just slower: sync falls back to polling, and calls connect directly when the two networks allow it.

| Service | What it does | Stores |
| --- | --- | --- |
| Relay (`relay/`, Node 20) | Tells an open app "something changed, sync now" within milliseconds; carries the live drawing board | Nothing. No database, no message content |
| coturn | Relays call audio/video when a direct connection is blocked (common on mobile data and office Wi-Fi) | Nothing. Media is DTLS-SRTP encrypted end to end between the phones; TURN only forwards encrypted packets |

## 1. DNS

Point two A records at the VPS: `relay.yourdomain.com` and `turn.yourdomain.com`.

## 2. Base setup

```
apt update && apt install -y nginx certbot python3-certbot-nginx coturn ufw
curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && apt install -y nodejs
ufw allow OpenSSH && ufw allow 80,443/tcp && ufw allow 3478,5349/tcp && ufw allow 3478,5349/udp && ufw allow 49160:49200/udp && ufw enable
```

## 3. Relay

```
useradd --system --home /opt/couple-connect relay
mkdir -p /opt/couple-connect && cp -r relay /opt/couple-connect/ && cd /opt/couple-connect/relay
npm ci --omit=dev
cp .env.example .env    # set RELAY_SECRET: openssl rand -hex 32
chown -R relay: /opt/couple-connect && chmod 600 .env
cp deploy/couple-connect-relay.service /etc/systemd/system/
systemctl daemon-reload && systemctl enable --now couple-connect-relay
cp deploy/nginx-relay.conf /etc/nginx/sites-available/relay   # replace relay.yourdomain.com
certbot --nginx -d relay.yourdomain.com
ln -s /etc/nginx/sites-available/relay /etc/nginx/sites-enabled/ && nginx -t && systemctl reload nginx
curl https://relay.yourdomain.com/health
```

## 4. coturn

```
certbot certonly --standalone -d turn.yourdomain.com --pre-hook "systemctl stop nginx" --post-hook "systemctl start nginx"
cp relay/deploy/turnserver.conf /etc/turnserver.conf   # set external-ip, realm, static-auth-secret (openssl rand -hex 32)
sed -i 's/#TURNSERVER_ENABLED=1/TURNSERVER_ENABLED=1/' /etc/default/coturn
chmod 640 /etc/turnserver.conf && systemctl enable --now coturn && systemctl restart coturn
```

Let coturn read the renewed certificate: add `--deploy-hook "systemctl restart coturn"` to the certbot renewal for turn.yourdomain.com.

## 5. Tell the API

In `backend/.env` on cPanel:

```
RELAY_URL=https://relay.yourdomain.com
RELAY_PUBLIC_URL=wss://relay.yourdomain.com/ws
RELAY_SECRET=<same as relay/.env>
TURN_URLS=turn:turn.yourdomain.com:3478?transport=udp,turn:turn.yourdomain.com:3478?transport=tcp,turns:turn.yourdomain.com:5349?transport=tcp
TURN_SECRET=<same as static-auth-secret>
```

Shared hosts must allow outgoing HTTPS from PHP (cURL) for the relay nudge and FCM. Most do; if yours blocks it, the app still works by polling.

## 6. Check

- Open the app on two phones: a message should appear instantly on the other phone.
- Test TURN with https://webrtc.github.io/samples/src/content/peerconnection/trickle-ice/ using a username/credential from `GET /calls/turn`: you should see `relay` candidates.

## Costs and scaling

The relay uses about 50 KB of memory per open app. TURN bandwidth is the real cost: a video call that needs TURN uses roughly 1–1.5 Mbit/s each way. Most calls connect directly and use no TURN bandwidth.
