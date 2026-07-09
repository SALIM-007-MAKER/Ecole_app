<?php /** @var int $unread Vue partielle — dropdown header */ ?>
<div x-data="{ open: false }" class="relative">
  <button @click="open = !open" class="relative p-2 text-slate-600 hover:text-violet-600 rounded-lg">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
    </svg>
    <?php if (($unread ?? 0) > 0): ?>
    <span id="badge-count" class="absolute top-1 right-1 w-4 h-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center leading-none">
      <?= min($unread, 9) ?><?= $unread > 9 ? '+' : '' ?>
    </span>
    <?php endif ?>
  </button>

  <div x-show="open" @click.outside="open = false" x-transition
       class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-xl border border-slate-100 z-50">
    <div class="p-3 border-b border-slate-100 flex justify-between items-center">
      <span class="font-semibold text-sm text-slate-700">Notifications</span>
      <a href="/v2/notifications" class="text-xs text-violet-600 hover:underline">Tout voir</a>
    </div>
    <div id="notif-panel-list" class="max-h-80 overflow-y-auto">
      <div class="p-4 text-center text-slate-400 text-sm">Chargement...</div>
    </div>
  </div>
</div>

<script>
(function() {
  // Poll toutes les 30s pour mettre à jour le badge
  async function pollUnread() {
    try {
      const r = await fetch('/v2/notifications/poll');
      const d = await r.json();
      const badge = document.getElementById('badge-count');
      if (d.unread > 0) {
        if (!badge) {
          const btn = document.querySelector('[data-notif-btn]');
          // recréer badge si absent
        } else {
          badge.textContent = Math.min(d.unread, 9) + (d.unread > 9 ? '+' : '');
          badge.style.display = 'flex';
        }
      } else if (badge) {
        badge.style.display = 'none';
      }
    } catch(e) {}
  }
  setInterval(pollUnread, 30000);
})();
</script>
