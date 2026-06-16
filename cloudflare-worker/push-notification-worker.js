// ─────────────────────────────────────────────────────────────────────────
// BlueSpot Hub — "Push Notification" Cloudflare Worker
// ─────────────────────────────────────────────────────────────────────────
// Sends FCM push notifications to all guests subscribed to a hub when an
// announcement is posted. Runs on Cloudflare free tier (no card needed).
//
// SETUP:
//   1. Create a new Worker in Cloudflare dashboard named "bluespot-push"
//   2. Paste this entire file → Save and Deploy
//   3. Add these environment variables in Cloudflare Worker Settings → Variables:
//        FIREBASE_PROJECT_ID   = bluespot-hub
//        FIREBASE_CLIENT_EMAIL = <your service account email>
//        FIREBASE_PRIVATE_KEY  = <your service account private key>
//        DATABASE_URL          = https://bluespot-hub-default-rtdb.firebaseio.com
//        PUSH_SECRET           = <any random string you choose, e.g. "mySecret123">
//        ALLOWED_ORIGIN        = * (or your domain)
//   4. Copy that same PUSH_SECRET value into adminpanel.html → PUSH_SECRET constant
// ─────────────────────────────────────────────────────────────────────────

export default {
  async fetch(request, env) {
    const corsHeaders = {
      'Access-Control-Allow-Origin': env.ALLOWED_ORIGIN || '*',
      'Access-Control-Allow-Methods': 'POST, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type',
    };

    if (request.method === 'OPTIONS') return new Response(null, { headers: corsHeaders });
    if (request.method !== 'POST') return json({ error: 'Method not allowed' }, 405, corsHeaders);

    try {
      const { hubId, title, body, secret } = await request.json();
      if (!hubId || !title) return json({ error: 'Missing fields' }, 400, corsHeaders);

      // 1. Verify caller using shared secret
      if (!env.PUSH_SECRET || secret !== env.PUSH_SECRET) {
        return json({ error: 'Unauthorized' }, 401, corsHeaders);
      }

      // 2. Get a service account access token
      const accessToken = await getServiceAccountAccessToken(env);

      // 3. Load FCM tokens for this hub + global hub
      const hubTokensData = await dbGet(env.DATABASE_URL, `hubs/${hubId}/fcmTokens`, accessToken) || {};
      const globalTokensData = hubId !== '_global'
        ? await dbGet(env.DATABASE_URL, `hubs/_global/fcmTokens`, accessToken) || {}
        : {};

      const allTokenData = { ...hubTokensData, ...globalTokensData };
      const tokens = Object.values(allTokenData).map(t => t.token).filter(Boolean);

      if (!tokens.length) return json({ sent: 0, message: 'No subscribers yet' }, 200, corsHeaders);

      // 4. Send FCM push via HTTP v1 API
      const fcmUrl = `https://fcm.googleapis.com/v1/projects/${env.FIREBASE_PROJECT_ID}/messages:send`;
      let sent = 0;
      const staleTokens = [];

      for (const token of tokens) {
        const res = await fetch(fcmUrl, {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${accessToken}`,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            message: {
              token,
              notification: { title, body },
              webpush: {
                notification: {
                  title,
                  body,
                  icon: 'https://assets.cdn.filesafe.space/Sk7XUXxjVtIrJHKp3GhX/media/6a2ff4421b95dbb2c2e8e5c1.png',
                  vibrate: [200, 100, 200],
                },
              },
            },
          }),
        });

        if (res.ok) {
          sent++;
        } else {
          const err = await res.json().catch(() => ({}));
          // Mark stale/invalid tokens for cleanup
          if (err?.error?.details?.some(d => d.errorCode === 'UNREGISTERED')) {
            staleTokens.push(token);
          }
        }
      }

      // 5. Clean up stale tokens
      for (const token of staleTokens) {
        const key = token.replace(/\./g, '_');
        await dbDelete(env.DATABASE_URL, `hubs/${hubId}/fcmTokens/${key}`, accessToken).catch(() => {});
        await dbDelete(env.DATABASE_URL, `hubs/_global/fcmTokens/${key}`, accessToken).catch(() => {});
      }

      return json({ sent, total: tokens.length, staleRemoved: staleTokens.length }, 200, corsHeaders);
    } catch (err) {
      return json({ error: err.message || String(err) }, 500, corsHeaders);
    }
  },
};

// ── Helpers ────────────────────────────────────────────────────────────────

function json(body, status, headers) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json', ...headers },
  });
}

async function verifyFirebaseIdToken(idToken, projectId) {
  const [headerB64, payloadB64, sigB64] = idToken.split('.');
  if (!headerB64 || !payloadB64 || !sigB64) return null;
  const header = JSON.parse(base64UrlDecode(headerB64));
  const payload = JSON.parse(base64UrlDecode(payloadB64));
  const now = Math.floor(Date.now() / 1000);
  if (payload.exp < now) return null;
  if (payload.aud !== projectId) return null;
  if (payload.iss !== `https://securetoken.google.com/${projectId}`) return null;
  if (!payload.sub) return null;
  const jwkRes = await fetch('https://www.googleapis.com/service_accounts/v1/jwk/securetoken@system.gserviceaccount.com');
  const { keys } = await jwkRes.json();
  const jwk = keys.find(k => k.kid === header.kid);
  if (!jwk) return null;
  const key = await crypto.subtle.importKey('jwk', jwk, { name: 'RSASSA-PKCS1-v1_5', hash: 'SHA-256' }, false, ['verify']);
  const data = new TextEncoder().encode(`${headerB64}.${payloadB64}`);
  const signature = base64UrlToBytes(sigB64);
  const valid = await crypto.subtle.verify('RSASSA-PKCS1-v1_5', key, signature, data);
  return valid ? payload.sub : null;
}

async function getServiceAccountAccessToken(env) {
  const now = Math.floor(Date.now() / 1000);
  const header = { alg: 'RS256', typ: 'JWT' };
  const claims = {
    iss: env.FIREBASE_CLIENT_EMAIL,
    scope: [
      'https://www.googleapis.com/auth/firebase.messaging',
      'https://www.googleapis.com/auth/firebase.database',
      'https://www.googleapis.com/auth/userinfo.email',
    ].join(' '),
    aud: 'https://oauth2.googleapis.com/token',
    exp: now + 3600,
    iat: now,
  };
  const headerB64 = base64UrlEncode(JSON.stringify(header));
  const claimsB64 = base64UrlEncode(JSON.stringify(claims));
  const unsigned = `${headerB64}.${claimsB64}`;
  const key = await importPrivateKey(env.FIREBASE_PRIVATE_KEY);
  const signature = await crypto.subtle.sign('RSASSA-PKCS1-v1_5', key, new TextEncoder().encode(unsigned));
  const jwt = `${unsigned}.${bytesToBase64Url(new Uint8Array(signature))}`;
  const res = await fetch('https://oauth2.googleapis.com/token', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer&assertion=${jwt}`,
  });
  const data = await res.json();
  if (!data.access_token) throw new Error('Failed to get access token: ' + JSON.stringify(data));
  return data.access_token;
}

async function importPrivateKey(pem) {
  const cleaned = pem.replace(/\\n/g, '\n').replace(/-----BEGIN PRIVATE KEY-----/, '').replace(/-----END PRIVATE KEY-----/, '').replace(/\s/g, '');
  return crypto.subtle.importKey('pkcs8', base64ToBytes(cleaned), { name: 'RSASSA-PKCS1-v1_5', hash: 'SHA-256' }, false, ['sign']);
}

async function dbGet(databaseUrl, path, accessToken) {
  const res = await fetch(`${databaseUrl}/${path}.json`, { headers: { Authorization: `Bearer ${accessToken}` } });
  return res.json();
}

async function dbDelete(databaseUrl, path, accessToken) {
  await fetch(`${databaseUrl}/${path}.json`, { method: 'DELETE', headers: { Authorization: `Bearer ${accessToken}` } });
}

function base64UrlEncode(str) { return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); }
function base64UrlDecode(str) { return atob(str.replace(/-/g, '+').replace(/_/g, '/')); }
function base64UrlToBytes(str) { const bin = base64UrlDecode(str); return Uint8Array.from(bin, c => c.charCodeAt(0)); }
function bytesToBase64Url(bytes) { let bin = ''; bytes.forEach(b => { bin += String.fromCharCode(b); }); return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); }
function base64ToBytes(b64) { const bin = atob(b64); return Uint8Array.from(bin, c => c.charCodeAt(0)); }
