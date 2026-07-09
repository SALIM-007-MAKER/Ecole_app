/**
 * ═══════════════════════════════════════════════════════════════════════════
 * Service Worker — Ecole App PWA v3
 *
 * Stratégies par type de ressource :
 *   CDN (Bootstrap/BI)  → Cache First      (URLs immuables, versionnées)
 *   CSS/JS locaux       → Stale-While-Revalidate  (rapide + toujours à jour)
 *   Pages PHP           → Network First + timeout  (données fraîches)
 *   Images              → Cache First + revalidation en arrière-plan
 *   API /api/*          → Network Only      (temps réel, jamais mis en cache)
 *
 * Mise à jour automatique :
 *   - skipWaiting() N'EST PAS appelé lors de l'installation
 *   - Le nouveau SW attend que l'utilisateur confirme (bannière) OU qu'il
 *     revienne sur l'onglet (visibilitychange dans app.js)
 * ═══════════════════════════════════════════════════════════════════════════
 */
'use strict';

// ─── Identifiants de cache ────────────────────────────────────────────────────
// CACHE_VER : incrémenter uniquement si la STRUCTURE des caches change.
// Les assets CSS/JS bénéficient de SWR → distribution automatique sans changer cette valeur.
const CACHE_VER    = 'v3';
const CACHE_CDN    = `ecole-cdn-${CACHE_VER}`;    // Bootstrap, Bootstrap Icons (immuables)
const CACHE_LOCAL  = `ecole-local-${CACHE_VER}`;  // CSS/JS locaux (SWR)
const CACHE_PAGES  = `ecole-pages-${CACHE_VER}`;  // Pages HTML PHP (Network First)
const CACHE_IMAGES = `ecole-images-${CACHE_VER}`; // Images uploadées
const CACHE_SHELL  = `ecole-shell-${CACHE_VER}`;  // App Shell hors-ligne

const BASE        = '/ecole_app';
const OFFLINE_URL = `${BASE}/offline.html`;
const NF_TIMEOUT  = 5000; // ms — Network First : timeout avant fallback cache

// App Shell — téléchargé dès l'installation, disponible immédiatement hors ligne
const SHELL_ASSETS = [
    `${BASE}/offline.html`,
    `${BASE}/assets/images/icon-72.png`,
    `${BASE}/assets/images/icon-192.png`,
    `${BASE}/assets/images/icon-512.png`,
];

// CDN Bootstrap — URLs contenant la version → immuables → Cache First sûr
const CDN_ASSETS = [
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
];

// ─── Installation ──────────────────────────────────────────────────────────────
// Pré-cache l'App Shell et les assets CDN.
// Le nouvel SW attend poliment — PAS de skipWaiting — pour ne pas perturber les pages
// en cours et permettre à la bannière de mise à jour de s'afficher correctement.
self.addEventListener('install', event => {
    event.waitUntil(
        Promise.all([
            caches.open(CACHE_SHELL).then(cache =>
                cache.addAll(SHELL_ASSETS.map(url => new Request(url, { cache: 'reload' })))
            ),
            caches.open(CACHE_CDN).then(cache =>
                cache.addAll(CDN_ASSETS.map(url => new Request(url, { cache: 'reload' })))
            ),
        ]).catch(err => console.warn('[SW] Install partiel (réseau ?) :', err))
        // Intentionnellement sans self.skipWaiting()
    );
});

// ─── Activation & nettoyage des anciens caches ────────────────────────────────
self.addEventListener('activate', event => {
    const validCaches = [CACHE_CDN, CACHE_LOCAL, CACHE_PAGES, CACHE_IMAGES, CACHE_SHELL];

    event.waitUntil(
        Promise.all([
            // Supprimer les anciens caches (versions précédentes)
            caches.keys().then(keys =>
                Promise.all(
                    keys
                        .filter(k => k.startsWith('ecole-') && !validCaches.includes(k))
                        .map(k => {
                            console.log('[SW] Suppression ancien cache :', k);
                            return caches.delete(k);
                        })
                )
            ),
            // Navigation Preload : réduit la latence de démarrage SW pour les navigations.
            // La requête réseau part immédiatement, en parallèle du démarrage du SW.
            self.registration.navigationPreload?.enable?.(),
        ]).then(() => self.clients.claim())
    );
});

// ─── Interception des requêtes ─────────────────────────────────────────────────
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    // Ignorer chrome-extension, data:, blob:, etc.
    if (!url.protocol.startsWith('http')) return;

    // Non-GET (POST, PUT, DELETE) → réseau direct, jamais de cache
    if (request.method !== 'GET') {
        event.respondWith(networkOnly(request));
        return;
    }

    // sw.js et manifest.json : le .htaccess impose no-cache, laisser le navigateur gérer
    if (url.pathname.endsWith('/sw.js') || url.pathname.endsWith('/manifest.json')) {
        return;
    }

    // API → réseau uniquement (données temps réel, authentification, session)
    if (url.pathname.startsWith(`${BASE}/api/`)) {
        event.respondWith(networkOnly(request));
        return;
    }

    // CDN Bootstrap / Bootstrap Icons → Cache First
    // URLs contiennent la version (ex: bootstrap@5.3.3) → contenu immuable → Cache First safe
    if (isCDN(url)) {
        event.respondWith(cacheFirst(request, CACHE_CDN));
        return;
    }

    // App Shell (offline.html, icônes PNG) → Cache First strict
    if (isShellAsset(url)) {
        event.respondWith(cacheFirst(request, CACHE_SHELL));
        return;
    }

    // CSS / JS locaux → Stale-While-Revalidate
    // Réponse instantanée depuis le cache + nouvelle version prête pour la prochaine visite.
    // Garantit que les mises à jour CSS/JS sont distribuées automatiquement, sans changer CACHE_VER.
    if (isLocalAsset(url)) {
        event.respondWith(staleWhileRevalidate(request, CACHE_LOCAL));
        return;
    }

    // Images uploadées → Cache First + revalidation silencieuse en arrière-plan
    if (isImage(url)) {
        event.respondWith(cacheFirstWithBgRevalidation(request, CACHE_IMAGES));
        return;
    }

    // Pages PHP → Network First avec timeout de NF_TIMEOUT ms
    // Utilise Navigation Preload si disponible (requête déjà en vol, latence réduite).
    // Fallback hiérarchique : réseau → cache → offline.html
    event.respondWith(networkFirst(request, event.preloadResponse));
});

// ═══════════════════════════════════════════════════════════════════════════════
// STRATÉGIES DE CACHE
// ═══════════════════════════════════════════════════════════════════════════════

// ─── Network Only ─────────────────────────────────────────────────────────────
async function networkOnly(request) {
    try {
        return await fetch(request);
    } catch {
        if (request.headers.get('Accept')?.includes('application/json')) {
            return new Response(
                JSON.stringify({ error: 'offline', message: 'Sans connexion réseau' }),
                { status: 503, headers: { 'Content-Type': 'application/json; charset=utf-8' } }
            );
        }
        return new Response('Service temporairement indisponible', { status: 503 });
    }
}

// ─── Cache First ──────────────────────────────────────────────────────────────
// Sert depuis le cache ; télécharge et met en cache seulement si absent.
// Idéal pour : CDN (URLs immuables), App Shell, icônes
async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        return new Response('Ressource indisponible hors ligne', { status: 503 });
    }
}

// ─── Cache First + revalidation en arrière-plan ───────────────────────────────
// Sert depuis le cache immédiatement, met à jour silencieusement en arrière-plan.
// Idéal pour : images uploadées (changent rarement, performance prioritaire)
async function cacheFirstWithBgRevalidation(request, cacheName) {
    const cache  = await caches.open(cacheName);
    const cached = await cache.match(request);

    if (cached) {
        // Revalider en arrière-plan sans bloquer la réponse
        fetch(request)
            .then(r => { if (r.ok) cache.put(request, r.clone()); })
            .catch(() => {});
        return cached;
    }

    try {
        const response = await fetch(request);
        if (response.ok) cache.put(request, response.clone());
        return response;
    } catch {
        return new Response('', { status: 503 });
    }
}

// ─── Stale-While-Revalidate ───────────────────────────────────────────────────
// Sert le cache IMMÉDIATEMENT pour la performance maximale.
// Met à jour le cache en arrière-plan → nouvelle version disponible à la prochaine visite.
// Idéal pour : CSS/JS locaux → les mises à jour CSS/JS sont distribuées automatiquement
async function staleWhileRevalidate(request, cacheName) {
    const cache  = await caches.open(cacheName);
    const cached = await cache.match(request);

    // Revalidation réseau en parallèle (non bloquante si cache disponible)
    const revalidate = fetch(request)
        .then(r => {
            if (r.ok) cache.put(request, r.clone());
            return r;
        })
        .catch(() => null);

    // Servir depuis le cache immédiatement
    if (cached) return cached;

    // Pas encore en cache → attendre la réponse réseau
    const fresh = await revalidate;
    return fresh || new Response('Ressource indisponible', { status: 503 });
}

// ─── Network First avec timeout ───────────────────────────────────────────────
// 1. Essaie le réseau (avec timeout NF_TIMEOUT ms)
// 2. Si disponible → sert la réponse fraîche et la met en cache
// 3. Si offline ou timeout → sert depuis CACHE_PAGES
// 4. Dernier recours → offline.html
// Idéal pour : toutes les pages PHP (données toujours fraîches, fallback hors ligne)
async function networkFirst(request, preloadResponsePromise) {
    const cache = await caches.open(CACHE_PAGES);

    try {
        // Navigation Preload : si activé, la requête réseau est déjà en vol → latence réduite
        const preload = await preloadResponsePromise?.catch(() => null);
        const networkResponse = preload || await fetchWithTimeout(request, NF_TIMEOUT);

        if (networkResponse.ok) {
            const contentType = networkResponse.headers.get('Content-Type') || '';
            // Mettre en cache uniquement les pages HTML (pas les redirects de session)
            if (contentType.includes('text/html') && networkResponse.status < 300) {
                cache.put(request, networkResponse.clone());
            }
        }
        return networkResponse;

    } catch {
        // Réseau indisponible ou timeout → fallback hiérarchique
        const cached = await cache.match(request);
        if (cached) return cached;

        const offline = await caches.match(OFFLINE_URL);
        return offline || inlineOfflinePage();
    }
}

// ─── Fetch avec timeout ───────────────────────────────────────────────────────
function fetchWithTimeout(request, ms) {
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error(`SW timeout ${ms}ms`)), ms);
        fetch(request).then(
            r => { clearTimeout(timer); resolve(r); },
            e => { clearTimeout(timer); reject(e);  }
        );
    });
}

// ═══════════════════════════════════════════════════════════════════════════════
// PUSH NOTIFICATIONS
// ═══════════════════════════════════════════════════════════════════════════════

self.addEventListener('push', event => {
    let data = null;
    if (event.data) {
        try { data = event.data.json(); }
        catch { data = { title: 'Ecole App', body: event.data.text() }; }
    }

    event.waitUntil(
        data
            ? self.registration.showNotification(data.title || 'Ecole App', buildNotifOptions(data))
            : fetchAndShowNotification()
    );
});

async function fetchAndShowNotification() {
    try {
        const r = await fetch(`${BASE}/api/notifications/latest`, { credentials: 'include' });
        if (!r.ok) return;
        const data = await r.json();
        if (data?.title) {
            return self.registration.showNotification(data.title, buildNotifOptions(data));
        }
    } catch {}
    return self.registration.showNotification('Ecole App', {
        body:  'Vous avez une nouvelle notification.',
        icon:  `${BASE}/assets/images/icon-192.png`,
        badge: `${BASE}/assets/images/icon-72.png`,
        tag:   'ecole-generic',
    });
}

function buildNotifOptions(data) {
    return {
        body:               data.body  || '',
        icon:               `${BASE}/assets/images/icon-192.png`,
        badge:              `${BASE}/assets/images/icon-72.png`,
        tag:                data.tag   || 'ecole-notif',
        data:               { url: data.url || `${BASE}/dashboard` },
        vibrate:            [200, 100, 200],
        requireInteraction: data.requireInteraction ?? false,
        silent:             data.silent ?? false,
        timestamp:          data.timestamp ? new Date(data.timestamp).getTime() : Date.now(),
        actions: [
            { action: 'open',  title: 'Ouvrir'  },
            { action: 'close', title: 'Ignorer' },
        ],
    };
}

self.addEventListener('notificationclick', event => {
    event.notification.close();
    if (event.action === 'close') return;

    const url = event.notification.data?.url || `${BASE}/dashboard`;
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(cs => {
            for (const c of cs) {
                if (c.url.includes('/ecole_app/') && 'focus' in c) {
                    c.navigate(url);
                    return c.focus();
                }
            }
            if (clients.openWindow) return clients.openWindow(url);
        })
    );
});

// ═══════════════════════════════════════════════════════════════════════════════
// BACKGROUND SYNC
// ═══════════════════════════════════════════════════════════════════════════════

self.addEventListener('sync', event => {
    if (event.tag === 'sync-pending') {
        event.waitUntil(
            clients.matchAll().then(cs =>
                cs.forEach(c => c.postMessage({ type: 'SYNC_PENDING' }))
            )
        );
    }
});

// ═══════════════════════════════════════════════════════════════════════════════
// MESSAGES CLIENT ↔ SW
// ═══════════════════════════════════════════════════════════════════════════════

self.addEventListener('message', event => {
    if (!event.data) return;
    switch (event.data.type) {

        case 'SKIP_WAITING':
            // Appelé par app.js quand l'utilisateur confirme la mise à jour
            // OU automatiquement quand l'utilisateur revient sur l'onglet (visibilitychange)
            self.skipWaiting();
            break;

        case 'CLEAR_CACHE':
            caches.keys()
                .then(ks => Promise.all(ks.map(k => caches.delete(k))))
                .then(() => event.source?.postMessage({ type: 'CACHE_CLEARED' }));
            break;

        case 'GET_VERSION':
            event.source?.postMessage({ type: 'VERSION', version: CACHE_VER });
            break;
    }
});

// ═══════════════════════════════════════════════════════════════════════════════
// CLASSIFICATEURS DE REQUÊTES
// ═══════════════════════════════════════════════════════════════════════════════

function isShellAsset(url) {
    return url.pathname.endsWith('/offline.html')
        || /\/assets\/images\/icon-\d+\.png$/.test(url.pathname);
}

function isLocalAsset(url) {
    return url.hostname === self.location.hostname
        && /\.(css|js|mjs|woff2?)(\?.*)?$/.test(url.pathname);
}

function isImage(url) {
    return /\.(png|jpe?g|gif|webp|svg)(\?.*)?$/.test(url.pathname) && !isShellAsset(url);
}

function isCDN(url) {
    return url.hostname.includes('jsdelivr.net')
        || url.hostname.includes('cdnjs.cloudflare.com')
        || url.hostname.includes('fonts.googleapis.com')
        || url.hostname.includes('fonts.gstatic.com');
}

// Page offline de secours si offline.html lui-même n'est pas en cache
function inlineOfflinePage() {
    return new Response(
        `<!DOCTYPE html><html lang="fr"><head>
        <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Hors ligne — Ecole App</title>
        <style>
            body{font-family:system-ui,sans-serif;display:flex;align-items:center;
                 justify-content:center;min-height:100vh;margin:0;background:#f4f6f9;text-align:center}
            .card{background:#fff;padding:2rem;border-radius:1rem;
                  box-shadow:0 4px 24px rgba(0,0,0,.1);max-width:380px;width:90%}
            h1{color:#212529;margin-bottom:.5rem}
            p{color:#6c757d;margin-bottom:1.5rem}
            a{display:inline-block;padding:.5rem 1.5rem;background:#0d6efd;color:#fff;
              border-radius:.5rem;text-decoration:none}
        </style></head>
        <body><div class="card">
            <h1>&#128267; Hors ligne</h1>
            <p>Vérifiez votre connexion internet et réessayez.</p>
            <a href="${BASE}/">&#8635; Réessayer</a>
        </div></body></html>`,
        { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
    );
}
