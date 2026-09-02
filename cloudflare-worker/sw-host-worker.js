// ─────────────────────────────────────────────────────────────────────────
// BlueSpot Hub — SW Host Worker (multi-hub)
// ─────────────────────────────────────────────────────────────────────────
// Routes (add these in Cloudflare):
//   hub.bluespotguide.com/*/sw.js
//   hub.bluespotguide.com/*/manifest.json
//   hub.bluespotguide.com/manifest.json   ← catches GHL framework's root manifest request
// ─────────────────────────────────────────────────────────────────────────

const HUBS = {

    'stone-mountain-park-campground': {
        name: 'Stone Mountain Park Campground',
        short_name: 'Stone Mountain Park Campground',
        icon: 'https://i.ibb.co/tw3TD0bX/5d88370f0ac4.jpg',
    },
    'little-river-campground': {
        name: 'Little River Campground',
        short_name: 'Little River Campground',
        icon: 'https://assets.cdn.filesafe.space/Sk7XUXxjVtIrJHKp3GhX/media/6a2ff4421b95dbb2c2e8e5c1.png',
    },
    'sanwar-rv-resort': {
        name: 'Sanwar RV Resort',
        short_name: 'Sanwar RV Resort',
        icon: 'https://i.ibb.co/352V9Kd0/8d6fd4f3c751.jpg',
    },
    'sanwar-rv-resort-hub': {
        name: 'Sanwar RV Resort',
        short_name: 'Sanwar RV',
        icon: 'https://i.ibb.co/352V9Kd0/8d6fd4f3c751.jpg',
    },
    'winding-waters-rv-resort': {
        name: 'Winding Waters RV Resort',
        short_name: 'Winding Waters',
        icon: 'https://i.ibb.co/nqwvGbnj/544ec0861954.jpg',
    },
    'winding-waters-rv-resort-hub': {
        name: 'Winding Waters RV Resort',
        short_name: 'Winding Waters',
        icon: 'https://i.ibb.co/nqwvGbnj/544ec0861954.jpg',
    },
    'sweetwater-valley-park': {
        name: 'Sweetwater Valley Park',
        short_name: 'Sweet Water Valley Park',
        icon: 'https://i.ibb.co/6csYBbRV/71deab22604b.jpg',
    },
    'sweetwater-valley-rv-park': {
        name: 'Sweetwater Valley Park',
        short_name: 'Sweet Water Valley Park',
        icon: 'https://i.ibb.co/6csYBbRV/71deab22604b.jpg',
    },
    'carolina-camp-cedar': {
        name: 'Carolina Camp Cedar',
        short_name: 'Carolina Camp Cedar',
        icon: 'https://assets.cdn.filesafe.space/Sk7XUXxjVtIrJHKp3GhX/media/6a56594f21818528a810ac97.png',
    },
    'talona-ridge-rv-resort-hub': {
        name: 'Talona Ridge Rv Resort',
        short_name: 'Talona Ridge Rv Resort',
        icon: 'https://assets.cdn.filesafe.space/Sk7XUXxjVtIrJHKp3GhX/media/6a58bfb51a0f0480509c9e8a.png',

    },
    'stay-wilder-campground': {
        name: 'Stay Wilder Campground',
        short_name: 'Stay Wilder Campground',
        icon: 'https://i.ibb.co/nvKLyzm/2a5c64d96866.jpg',

    },    'splash-rv-resort': {
        name: 'Splash RV Resort',
        short_name: 'Splash RV Resort',
        icon: 'https://i.ibb.co/d0fYY5VG/546f394e2746.jpg',
    },
    'forsyth-station-rv-resort': {
        name: 'Forsyth Station RV Resort',
        short_name: 'Forsyth Station',
        icon: 'https://assets.cdn.filesafe.space/Sk7XUXxjVtIrJHKp3GhX/media/6a86d9ffcdd4b797a38aeac0.png',
    },
    'allatoona-landing-marine-resort-hub': {
        name: 'Allatoona Landing Marine Resort',
        short_name: 'Allatoona Landing',
        icon: 'https://i.ibb.co/3mmmqByT/202694e68a45.jpg',
    },
    'talking-rock-motorcoach-resort': {
        name: 'Talking Rock Motorcoach RV Resort',
        short_name: 'Talking Rock',
        icon: 'https://assets.cdn.filesafe.space/Sk7XUXxjVtIrJHKp3GhX/media/6a5fa31252b008d561980b37.png',
    },'blue-ridge-river-resort': {
        name: 'Blue Ridge River Resort',
        short_name: 'Blue Ridge River Resort',
        icon: 'https://assets.cdn.filesafe.space/Sk7XUXxjVtIrJHKp3GhX/media/6a620d65ec166b2c5f7e3f64.png',
    },
    'blue-ridge-rv-resort': {
        name: 'Blue Ridge River Resort',
        short_name: 'Blue Ridge River Resort',
        icon: 'https://assets.cdn.filesafe.space/Sk7XUXxjVtIrJHKp3GhX/media/6a620d65ec166b2c5f7e3f64.png',
    },
    'big-meadow-family-campground': {
        name: 'Big Meadow Family Campground',
        short_name: 'Big Meadow',
        icon: 'https://assets.cdn.filesafe.space/Sk7XUXxjVtIrJHKp3GhX/media/6a637661d3104247ab323667.png',
    },
    'iron-mountain-resort': {
        name: 'Iron Mountain Resort',
        short_name: 'Iron Mountain',
        icon: 'https://assets.cdn.filesafe.space/Sk7XUXxjVtIrJHKp3GhX/media/6a2ff4421b95dbb2c2e8e5c1.png',
    },
    'iconnecthub': {
        name: 'iConnectHub Admin',
        short_name: 'iConnectHub',
        // Must match the apple-touch-icon/favicon set in adminpanel.html — iOS
        // Web Push ignores the notification `icon` option entirely and always
        // displays the icon the PWA was installed with (from apple-touch-icon),
        // so if these two URLs ever diverge, the icon set here becomes silently
        // dead on iOS (still respected by Android/Chrome).
        icon: 'https://assets.cdn.filesafe.space/Sk7XUXxjVtIrJHKp3GhX/media/6a5634ab46ff6bf8017aab21.png',
    },
};

const FIREBASE_CONFIG = `{
  apiKey: "AIzaSyAAqyUeAMIWHhEAxjeJaWdCokpTRpfxXM0",
  authDomain: "bluespot-hub.firebaseapp.com",
  databaseURL: "https://bluespot-hub-default-rtdb.firebaseio.com",
  projectId: "bluespot-hub",
  storageBucket: "bluespot-hub.firebasestorage.app",
  messagingSenderId: "819010976218",
  appId: "1:819010976218:web:3f8ce672704169cee528e3"
}`;

function buildSW(hubId, icon) {
    return `
const ICON = '${icon}';
const HUB_PATH = '/${hubId}';

self.addEventListener('push', event => {
  let title = 'Park Hub Update';
  let body = 'Tap to see the latest announcements.';

  try {
    if (event.data) {
      const d = event.data.json();
      const n = d.notification || d.data || d;
      title = n.title || d.title || title;
      body  = n.body  || d.body  || body;
    }
  } catch (e) {}

  event.waitUntil(
    self.registration.showNotification(title, {
      body,
      icon: ICON,
      badge: ICON,
      vibrate: [200, 100, 200],
      data: { url: HUB_PATH },
    })
  );
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
      const target = event.notification.data?.url || HUB_PATH;
      for (const c of list) {
        if (c.url.includes(HUB_PATH) && 'focus' in c) return c.focus();
      }
      return clients.openWindow(target);
    })
  );
});

const CACHE = 'park-hub-${hubId}-v4';
const SKIP = ['firebase', 'gstatic', 'googleapis', 'workers.dev', 'filesafe.space', 'ibb.co'];

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET') return;
  const host = new URL(e.request.url).hostname;
  if (SKIP.some(s => host.includes(s))) return;
  e.respondWith(
    fetch(e.request).then(res => {
      if (res && res.status === 200 && res.type !== 'opaque') {
        const clone = res.clone(); // clone synchronously before any async ops
        caches.open(CACHE).then(c => c.put(e.request, clone));
      }
      return res;
    }).catch(() => caches.match(e.request))
  );
});
`;
}

function iconMimeType(url) {
    const ext = (url.split('?')[0].split('.').pop() || '').toLowerCase();
    if (ext === 'png') return 'image/png';
    if (ext === 'webp') return 'image/webp';
    if (ext === 'svg') return 'image/svg+xml';
    return 'image/jpeg';
}

function buildManifest(hubId, hub) {
    const iconType = iconMimeType(hub.icon);
    return JSON.stringify({
        name: hub.name,
        short_name: hub.short_name,
        description: 'Your campground companion — announcements, amenities, activities and more.',
        start_url: `/${hubId}`,
        scope: `/${hubId}`,
        display: 'standalone',
        background_color: '#1a4a7a',
        theme_color: '#1a56db',
        orientation: 'portrait-primary',
        icons: [
            { src: hub.icon, sizes: '192x192', type: iconType, purpose: 'any' },
            { src: hub.icon, sizes: '512x512', type: iconType, purpose: 'any' },
        ],
    });
}

// Resolve a hub id whether or not it has a trailing "-hub" — e.g. both
// "winding-waters-rv-resort" and "winding-waters-rv-resort-hub" should
// resolve to whichever of those variants is actually registered in HUBS,
// so a page URL slug and the worker entry don't have to match exactly.
function resolveHub(hubId) {
    if (!hubId) return null;
    if (HUBS[hubId]) return { hubId, hub: HUBS[hubId] };
    const alt = hubId.endsWith('-hub') ? hubId.slice(0, -4) : hubId + '-hub';
    if (HUBS[alt]) return { hubId: alt, hub: HUBS[alt] };
    return null;
}

// Resolve hub from Referer header path when no hubId in URL (e.g. /manifest.json root request)
function hubFromReferer(request) {
    const ref = request.headers.get('Referer') || '';
    try {
        const refPath = new URL(ref).pathname.split('/').filter(Boolean);
        if (refPath[0]) return resolveHub(refPath[0]);
    } catch { }
    return null;
}

export default {
    async fetch(request) {
        const url = new URL(request.url);
        const parts = url.pathname.split('/').filter(Boolean);
        const cors = { 'Access-Control-Allow-Origin': request.headers.get('Origin') || '*' };

        // Handle root /manifest.json (GHL framework overrides <link rel="manifest">)
        if (parts.length === 1 && parts[0] === 'manifest.json') {
            const match = hubFromReferer(request);
            if (match) {
                return new Response(buildManifest(match.hubId, match.hub), {
                    headers: { 'Content-Type': 'application/manifest+json', 'Cache-Control': 'no-cache', ...cors },
                });
            }
            return new Response('Not found', { status: 404 });
        }

        // Expected: /hubId/sw.js  or  /hubId/manifest.json
        const file = parts[1];
        const match = resolveHub(parts[0]);

        if (!match || !file) {
            return new Response('Not found', { status: 404 });
        }
        const { hubId, hub } = match;

        if (file === 'sw.js') {
            return new Response(buildSW(hubId, hub.icon), {
                headers: {
                    'Content-Type': 'application/javascript',
                    'Service-Worker-Allowed': '/',
                    'Cache-Control': 'no-cache',
                    ...cors,
                },
            });
        }

        if (file === 'manifest.json') {
            return new Response(buildManifest(hubId, hub), {
                headers: {
                    'Content-Type': 'application/manifest+json',
                    'Cache-Control': 'no-cache',
                    ...cors,
                },
            });
        }

        return new Response('Not found', { status: 404 });
    },
};
