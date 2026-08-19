/**
 * ═══════════════════════════════════════════════════════════════════════════
 * EduNova — JavaScript principal + PWA (Tailwind / vanilla)
 * ═══════════════════════════════════════════════════════════════════════════
 */
'use strict';

const EcoleApp = {

    sw: null,
    deferredInstall: null,

    async init() {
        // Icons
        if (window.lucide) lucide.createIcons();

        this.initSidebar();
        this.initDropdowns();
        this.initTogglePassword();
        this.initAutoAlerts();
        this.initConfirmDelete();
        this.initNetworkStatus();
        this.initModals();
        this.initScrollTop();
        this.initTouchGestures();
        await this.initServiceWorker();
        this.initInstallBanner();
        this.initPushNotifications();
    },

    // ─── Sidebar (drawer mobile + réduction desktop + accordéon) ──────────
    // Contrôleur unique : c'est la source de vérité pour l'état "collapsed"
    // (icônes seules) ET pour l'état des groupes/sous-menus, car les deux
    // sont interdépendants (un sous-menu ouvert n'a pas de sens en mode
    // icônes seules — le laisser vivre indépendamment causait l'affichage
    // cassé en mode réduit).
    initSidebar() {
        const sidebar   = document.getElementById('sidebar');
        const overlay   = document.getElementById('sidebar-overlay');
        const openBtn   = document.getElementById('openSidebar');
        const closeBtn  = document.getElementById('closeSidebar');
        const toggleBtn = document.getElementById('toggleSidebar');
        const wrapper   = document.querySelector('.main-wrapper');
        if (!sidebar) return;

        const STORAGE_KEY = 'ecole_sidebar_collapsed';
        const isDesktop = () => window.innerWidth >= 1024;
        const groupButtons = () => Array.from(sidebar.querySelectorAll('[data-group]'));
        const groupContent = (btn) => document.getElementById('sg-' + btn.dataset.group);

        // ── Accordéon des groupes ──
        const closeGroup = (btn) => {
            btn.classList.remove('group-open');
            btn.setAttribute('aria-expanded', 'false');
            const content = groupContent(btn);
            if (content) content.style.maxHeight = '0';
        };
        const openGroup = (btn) => {
            groupButtons().forEach(b => { if (b !== btn) closeGroup(b); });
            btn.classList.add('group-open');
            btn.setAttribute('aria-expanded', 'true');
            const content = groupContent(btn);
            if (content) content.style.maxHeight = content.scrollHeight + 'px';
        };
        const closeAllGroups = () => groupButtons().forEach(closeGroup);

        // ── Mode réduit (icônes seules) ──
        const applyCollapsed = (collapsed) => {
            sidebar.classList.toggle('collapsed', collapsed);
            wrapper?.classList.toggle('sidebar-collapsed', collapsed && isDesktop());
            toggleBtn?.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
            toggleBtn?.setAttribute('title', collapsed ? 'Étendre la navigation' : 'Réduire la navigation');
            const icon = toggleBtn?.querySelector('i');
            if (icon) icon.style.transform = collapsed ? 'rotate(180deg)' : 'rotate(0deg)';

            if (collapsed) {
                // Aucun sous-menu ne doit rester ouvert derrière des icônes seules.
                closeAllGroups();
            } else {
                // Restaure le groupe actif par défaut (celui de la page courante).
                const defaultBtn = groupButtons().find(b => b.dataset.defaultOpen === 'true');
                if (defaultBtn) openGroup(defaultBtn);
            }
        };
        const setCollapsed = (collapsed) => {
            applyCollapsed(collapsed);
            if (isDesktop()) localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
        };

        groupButtons().forEach(btn => {
            btn.addEventListener('click', () => {
                if (sidebar.classList.contains('collapsed') && isDesktop()) {
                    // Pas de sous-menu visible en mode réduit : on ré-étend
                    // la sidebar d'abord, puis on ouvre le groupe demandé.
                    setCollapsed(false);
                }
                btn.classList.contains('group-open') ? closeGroup(btn) : openGroup(btn);
            });
        });

        // ── Drawer mobile ──
        const open  = () => {
            sidebar.classList.add('open');
            overlay?.classList.add('visible');
            document.body.style.overflow = 'hidden';
            openBtn?.setAttribute('aria-expanded', 'true');
        };
        const close = () => {
            sidebar.classList.remove('open');
            overlay?.classList.remove('visible');
            document.body.style.overflow = '';
            openBtn?.setAttribute('aria-expanded', 'false');
        };

        openBtn?.addEventListener('click', () => {
            sidebar.classList.contains('open') ? close() : open();
        });
        closeBtn?.addEventListener('click', close);
        toggleBtn?.addEventListener('click', () => setCollapsed(!sidebar.classList.contains('collapsed')));
        overlay?.addEventListener('click', close);

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) close();
        });

        window.addEventListener('resize', () => {
            if (isDesktop()) close();
            wrapper?.classList.toggle('sidebar-collapsed', sidebar.classList.contains('collapsed') && isDesktop());
        });

        // ── État initial (persisté sur desktop uniquement) ──
        const stored = isDesktop() ? localStorage.getItem(STORAGE_KEY) : null;
        applyCollapsed(stored === '1');
    },

    initThemeToggle() {
        const btn = document.getElementById('themeToggle');
        if (!btn) return;
        const root = document.documentElement;
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        const stored = localStorage.getItem('ecole_theme');
        const systemPrefersDark = mediaQuery.matches;
        const initialTheme = stored || (systemPrefersDark ? 'dark' : 'light');
        root.setAttribute('data-theme', initialTheme);
        const icon = btn.querySelector('i');
        if (icon) icon.className = initialTheme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
        const applyTheme = (theme) => {
            root.setAttribute('data-theme', theme);
            if (icon) icon.className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
        };
        btn.addEventListener('click', () => {
            const current = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            localStorage.setItem('ecole_theme', current);
            applyTheme(current);
            this.showToast(current === 'dark' ? 'Thème sombre activé' : 'Thème clair activé', 'info', 1800);
        });
        mediaQuery.addEventListener?.('change', e => {
            if (!localStorage.getItem('ecole_theme')) {
                applyTheme(e.matches ? 'dark' : 'light');
            }
        });
    },

    // ─── Dropdowns ────────────────────────────────────────────────────────
    initDropdowns() {
        document.querySelectorAll('[id$="DropdownBtn"]').forEach(btn => {
            const menuId = btn.id.replace('Btn', '');
            const menu   = document.getElementById(menuId);
            if (!menu) return;

            btn.addEventListener('click', e => {
                e.stopPropagation();
                const isOpen = !menu.classList.contains('hidden');
                this.closeAllDropdowns();
                if (!isOpen) {
                    menu.classList.remove('hidden');
                    btn.setAttribute('aria-expanded', 'true');
                }
            });
        });

        document.addEventListener('click', () => this.closeAllDropdowns());
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') this.closeAllDropdowns();
        });
    },

    closeAllDropdowns() {
        document.querySelectorAll('.dropdown-menu').forEach(m => {
            m.classList.add('hidden');
        });
        document.querySelectorAll('[aria-expanded]').forEach(b => {
            b.setAttribute('aria-expanded', 'false');
        });
    },

    initScrollTop() {
        const btn = document.getElementById('scrollTopBtn');
        if (!btn) return;
        const toggle = () => btn.classList.toggle('visible', window.scrollY > 600);
        window.addEventListener('scroll', toggle, { passive: true });
        toggle();
        btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    },

    initTouchGestures() {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar) return;
        let startX = 0;
        let startY = 0;
        document.addEventListener('touchstart', e => {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
        }, { passive: true });
        document.addEventListener('touchend', e => {
            const dx = e.changedTouches[0].clientX - startX;
            const dy = e.changedTouches[0].clientY - startY;
            if (window.innerWidth < 1024 && Math.abs(dx) > 70 && Math.abs(dx) > Math.abs(dy)) {
                if (dx < 0 && sidebar.classList.contains('open')) {
                    sidebar.classList.remove('open');
                    document.getElementById('sidebar-overlay')?.classList.remove('visible');
                    document.body.style.overflow = '';
                }
            }
        }, { passive: true });
    },

    showToast(message, type = 'info', duration = 2500) {
        const container = document.getElementById('toast-container');
        if (!container) return;
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `<div class="toast-icon"><i class="fa-solid ${type === 'success' ? 'fa-check' : type === 'error' ? 'fa-triangle-exclamation' : type === 'warning' ? 'fa-bell' : 'fa-info-circle'}"></i></div><div>${message}</div>`;
        container.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('show'));
        window.setTimeout(() => {
            toast.classList.remove('show');
            window.setTimeout(() => toast.remove(), 220);
        }, duration);
    },

    // ─── Afficher/masquer le mot de passe ─────────────────────────────────
    initTogglePassword() {
        document.querySelectorAll('#togglePassword, [data-toggle-password]').forEach(btn => {
            btn.addEventListener('click', () => {
                const wrap  = btn.closest('.input-group') || btn.closest('.relative');
                const input = wrap?.querySelector('input[type="password"], input[type="text"]');
                const icon  = btn.querySelector('[data-lucide]');
                if (!input) return;
                const isPass = input.type === 'password';
                input.type = isPass ? 'text' : 'password';
                if (icon) {
                    icon.setAttribute('data-lucide', isPass ? 'eye-off' : 'eye');
                    if (window.lucide) lucide.createIcons();
                }
            });
        });
    },

    // ─── Fermeture automatique des alertes ────────────────────────────────
    initAutoAlerts() {
        document.querySelectorAll('[role=alert].border-emerald-200').forEach(el => {
            setTimeout(() => el.remove(), 5000);
        });
        document.querySelectorAll('[role=alert].border-red-200').forEach(el => {
            setTimeout(() => el.remove(), 8000);
        });
        document.querySelectorAll('.alert-close').forEach(btn => {
            btn.addEventListener('click', () => btn.closest('.alert')?.remove());
        });
    },

    // ─── Confirmation avant suppression ───────────────────────────────────
    initConfirmDelete() {
        document.querySelectorAll('[data-confirm]').forEach(el => {
            el.addEventListener('click', e => {
                const msg = el.dataset.confirm || 'Êtes-vous sûr de vouloir effectuer cette action ?';
                if (!confirm(msg)) e.preventDefault();
            });
        });
    },

    // ─── Statut réseau ────────────────────────────────────────────────────
    initNetworkStatus() {
        const update = () => {
            document.body.classList.toggle('offline', !navigator.onLine);
            const banner = document.getElementById('offline-banner');
            if (banner) banner.style.display = navigator.onLine ? 'none' : 'flex';
            if (this.sw?.active && navigator.onLine) this.syncPendingRequests();
        };

        window.addEventListener('online',  update);
        window.addEventListener('offline', update);
        update();
    },

    // ─── Modales (data-modal) ─────────────────────────────────────────────
    initModals() {
        // Ouvrir
        document.querySelectorAll('[data-modal-open]').forEach(btn => {
            btn.addEventListener('click', () => {
                const modal = document.getElementById(btn.dataset.modalOpen);
                modal?.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
        });

        // Fermer via bouton
        document.querySelectorAll('[data-modal-close]').forEach(btn => {
            btn.addEventListener('click', () => {
                const modal = btn.closest('.modal-overlay');
                modal?.classList.remove('active');
                document.body.style.overflow = '';
            });
        });

        // Fermer en cliquant sur l'overlay
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', e => {
                if (e.target === overlay) {
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        // Fermer avec Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(m => {
                    m.classList.remove('active');
                    document.body.style.overflow = '';
                });
            }
        });
    },

    openModal(id) {
        const modal = document.getElementById(id);
        modal?.classList.add('active');
        document.body.style.overflow = 'hidden';
    },

    closeModal(id) {
        const modal = document.getElementById(id);
        modal?.classList.remove('active');
        document.body.style.overflow = '';
    },

    // ─── Toast notifications ──────────────────────────────────────────────
    showToast(message, type = 'info', duration = 4000) {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
        }

        const icons = {
            success: 'check-circle',
            danger:  'alert-circle',
            warning: 'alert-triangle',
            info:    'info',
        };

        const toast = document.createElement('div');
        toast.className = `pointer-events-auto flex min-w-64 max-w-sm items-start gap-3 rounded-xl border bg-white px-4 py-3 text-sm shadow-lg ${type === 'success' ? 'border-emerald-200 text-emerald-800' : type === 'danger' ? 'border-red-200 text-red-800' : type === 'warning' ? 'border-amber-200 text-amber-900' : 'border-sky-200 text-sky-800'}`;
        toast.innerHTML = `
            <i data-lucide="${icons[type] || 'info'}" class="w-4 h-4 shrink-0 mt-0.5"></i>
            <span class="flex-1 text-sm">${message}</span>
            <button class="ml-2 opacity-60 hover:opacity-100 transition-opacity" aria-label="Fermer">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>`;

        container.appendChild(toast);
        if (window.lucide) lucide.createIcons({ nodes: [toast] });

        requestAnimationFrame(() => toast.classList.add('show'));

        const remove = () => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 350);
        };

        toast.querySelector('button').addEventListener('click', remove);
        if (duration > 0) setTimeout(remove, duration);

        return toast;
    },

    // ─── Service Worker ───────────────────────────────────────────────────
    async initServiceWorker() {
        if (!('serviceWorker' in navigator)) return;

        try {
            const base = window.APP_BASE_URL || '';
            this.sw = await navigator.serviceWorker.register(
                base + '/sw.js',
                { scope: base + '/', updateViaCache: 'none' }
            );

            this.sw.addEventListener('updatefound', () => {
                const newWorker = this.sw.installing;
                newWorker.addEventListener('statechange', () => {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        this.showUpdateBanner();
                    }
                });
            });

            navigator.serviceWorker.addEventListener('controllerchange', () => {
                window.location.reload();
            });

            navigator.serviceWorker.addEventListener('message', e => {
                if (e.data?.type === 'SYNC_PENDING') this.syncPendingRequests();
            });

            // Auto-apply quand l'utilisateur revient sur l'onglet
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible' && this.sw?.waiting) {
                    this.sw.waiting.postMessage({ type: 'SKIP_WAITING' });
                }
            });

        } catch (err) {
            console.warn('[PWA] Service Worker non enregistré :', err);
        }
    },

    // ─── Bannière de mise à jour ──────────────────────────────────────────
    showUpdateBanner() {
        if (document.getElementById('update-banner')) return;

        const banner = document.createElement('div');
        banner.id = 'update-banner';
        banner.className = 'fixed left-1/2 top-4 z-[9999] flex -translate-x-1/2 items-center gap-3 rounded-xl bg-slate-900 px-4 py-3 text-sm text-slate-100 shadow-2xl';
        banner.innerHTML = `
            <i data-lucide="refresh-cw" class="w-4 h-4 shrink-0"></i>
            <span class="flex-1 text-sm">Nouvelle version disponible.</span>
            <button id="updateNowBtn" class="rounded-lg border border-violet-400 px-3 py-1 text-xs font-medium text-white transition hover:bg-violet-500">
                Mettre à jour
            </button>`;
        document.body.appendChild(banner);
        if (window.lucide) lucide.createIcons({ nodes: [banner] });

        document.getElementById('updateNowBtn').addEventListener('click', () => {
            this.sw?.waiting?.postMessage({ type: 'SKIP_WAITING' });
            banner.remove();
        });
    },

    // ─── Bannière d'installation ──────────────────────────────────────────
    initInstallBanner() {
        window.addEventListener('beforeinstallprompt', e => {
            e.preventDefault();
            this.deferredInstall = e;
            if (!sessionStorage.getItem('install-dismissed')) this.showInstallBanner();
        });

        window.addEventListener('appinstalled', () => {
            this.hideInstallBanner();
            this.deferredInstall = null;
        });
    },

    showInstallBanner() {
        if (document.getElementById('install-banner')) return;

        const banner = document.createElement('div');
        banner.id = 'install-banner';
        banner.className = 'fixed bottom-6 right-6 z-[9997] flex translate-y-[calc(100%+2rem)] items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 text-sm shadow-2xl transition-transform';
        banner.innerHTML = `
            <div class="flex items-center gap-3">
                <img src="${window.APP_BASE_URL || ''}/assets/images/icon-72.png" width="40" height="40"
                     alt="EduNova" class="rounded-xl shrink-0">
                <div>
                    <div class="font-semibold text-sm">Installer EduNova</div>
                    <div class="text-xs opacity-75">Accès rapide depuis l'écran d'accueil</div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button id="installDismissBtn" class="text-xs px-3 py-1.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50">Plus tard</button>
                <button id="installConfirmBtn" class="text-xs px-3 py-1.5 rounded-lg bg-violet-600 text-white hover:bg-violet-700">Installer</button>
            </div>`;
        document.body.appendChild(banner);

        requestAnimationFrame(() => banner.classList.remove('translate-y-[calc(100%+2rem)]'));

        document.getElementById('installConfirmBtn').addEventListener('click', () => this.promptInstall());
        document.getElementById('installDismissBtn').addEventListener('click', () => {
            this.hideInstallBanner();
            sessionStorage.setItem('install-dismissed', '1');
        });
    },

    hideInstallBanner() {
        const banner = document.getElementById('install-banner');
        if (banner) { banner.classList.add('translate-y-[calc(100%+2rem)]'); setTimeout(() => banner.remove(), 300); }
    },

    async promptInstall() {
        if (!this.deferredInstall) return;
        this.deferredInstall.prompt();
        const { outcome } = await this.deferredInstall.userChoice;
        if (outcome === 'accepted') this.hideInstallBanner();
        this.deferredInstall = null;
    },

    // ─── Push Notifications ───────────────────────────────────────────────
    async initPushNotifications() {
        if (!('Notification' in window) || !this.sw) return;

        const btn = document.getElementById('pushSubscribeBtn');
        if (!btn) return;

        await this.updatePushButtonState(btn);

        btn.addEventListener('click', async () => {
            if (Notification.permission === 'granted') {
                await this.togglePushSubscription(btn);
            } else if (Notification.permission !== 'denied') {
                const perm = await Notification.requestPermission();
                if (perm === 'granted') await this.subscribePush(btn);
            } else {
                this.showToast('Les notifications sont bloquées dans les paramètres du navigateur.', 'warning');
            }
        });
    },

    async updatePushButtonState(btn) {
        if (!this.sw) return;
        try {
            const sub = await this.sw.pushManager.getSubscription();
            btn.dataset.subscribed = sub ? 'true' : 'false';
            const icon = btn.querySelector('[data-lucide]');
            if (icon) {
                icon.setAttribute('data-lucide', sub ? 'bell-off' : 'bell-ring');
                if (window.lucide) lucide.createIcons({ nodes: [btn] });
            }
            btn.title = sub ? 'Désactiver les notifications' : 'Activer les notifications';
        } catch {}
    },

    async togglePushSubscription(btn) {
        btn.dataset.subscribed === 'true' ? await this.unsubscribePush(btn) : await this.subscribePush(btn);
    },

    async subscribePush(btn) {
        try {
            btn.disabled = true;
            const keyResp = await fetch((window.APP_BASE_URL || '') + '/api/push/vapid-key');
            if (!keyResp.ok) throw new Error('Clé VAPID indisponible');
            const { publicKey } = await keyResp.json();

            const sub = await this.sw.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(publicKey),
            });

            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const resp = await fetch((window.APP_BASE_URL || '') + '/api/push/subscribe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify(sub.toJSON()),
            });
            if (!resp.ok) throw new Error('Erreur abonnement');

            btn.dataset.subscribed = 'true';
            this.showToast('Notifications push activées.', 'success');
            await this.updatePushButtonState(btn);
        } catch (err) {
            console.error('[Push]', err);
            this.showToast('Impossible d\'activer les notifications push.', 'danger');
        } finally {
            btn.disabled = false;
        }
    },

    async unsubscribePush(btn) {
        try {
            btn.disabled = true;
            const sub = await this.sw.pushManager.getSubscription();
            if (sub) {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                await fetch((window.APP_BASE_URL || '') + '/api/push/unsubscribe', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                    body: JSON.stringify({ endpoint: sub.endpoint }),
                });
                await sub.unsubscribe();
            }
            btn.dataset.subscribed = 'false';
            this.showToast('Notifications push désactivées.', 'info');
            await this.updatePushButtonState(btn);
        } catch (err) {
            console.error('[Push]', err);
        } finally {
            btn.disabled = false;
        }
    },

    syncPendingRequests() {
        if (!this.sw?.sync) return;
        this.sw.sync.register('sync-pending').catch(() => {});
    },
};

// ─── Helpers globaux ──────────────────────────────────────────────────────────

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw     = atob(base64);
    return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
}

async function apiFetch(url, options = {}) {
    const csrf    = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const merged  = {
        credentials: 'same-origin',
        ...options,
        headers: {
            'Content-Type':     'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token':     csrf,
            ...(options.headers || {}),
        },
    };
    const resp = await fetch(url, merged);
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    return resp.json();
}

function debounce(fn, delay = 300) {
    let timer;
    return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), delay); };
}

// Expose openModal/closeModal globally for inline HTML usage
function openModal(id)  { EcoleApp.openModal(id);  }
function closeModal(id) { EcoleApp.closeModal(id); }

// ─── Lancement ───────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => EcoleApp.init());
