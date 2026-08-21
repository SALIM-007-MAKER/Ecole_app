<?php /** @var array $notifications @var int $total @var int $page @var int $unread */ ?>
<div class="max-w-3xl mx-auto py-8 px-4">

  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-800">
      Notifications
      <?php if ($unread > 0): ?>
        <span class="ml-2 text-sm font-medium bg-violet-600 text-white rounded-full px-2 py-0.5"><?= $unread ?></span>
      <?php endif ?>
    </h1>
    <?php if ($unread > 0): ?>
    <button onclick="markAllRead()" class="text-sm text-violet-600 hover:underline">Tout marquer comme lu</button>
    <?php endif ?>
  </div>

  <?php if (empty($notifications)): ?>
    <div class="bg-white rounded-xl p-12 text-center text-slate-400 shadow-sm">
      <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
      </svg>
      <p>Aucune notification</p>
    </div>
  <?php else: ?>
    <div class="space-y-2">
      <?php foreach ($notifications as $n): ?>
      <div class="bg-white rounded-xl p-4 shadow-sm border <?= $n['lu'] ? 'border-slate-100' : 'border-violet-200 bg-violet-50/40' ?> flex gap-4 items-start" id="notif-<?= $n['id'] ?>">
        <div class="w-2 h-2 rounded-full mt-2 flex-shrink-0 <?= $n['lu'] ? 'bg-slate-300' : 'bg-violet-500' ?>"></div>
        <div class="flex-1 min-w-0">
          <p class="font-medium text-slate-800 text-sm"><?= htmlspecialchars($n['titre']) ?></p>
          <?php if ($n['corps']): ?>
          <p class="text-slate-500 text-xs mt-1"><?= htmlspecialchars($n['corps']) ?></p>
          <?php endif ?>
          <p class="text-slate-400 text-xs mt-1"><?= date('d/m/Y H:i', strtotime($n['created_at'])) ?></p>
        </div>
        <div class="flex gap-2 items-center flex-shrink-0">
          <?php if ($n['url_action']): ?>
          <a href="<?= htmlspecialchars($n['url_action']) ?>" class="text-xs text-violet-600 hover:underline">Voir</a>
          <?php endif ?>
          <?php if (!$n['lu']): ?>
          <button onclick="markRead(<?= $n['id'] ?>)" class="text-xs text-slate-400 hover:text-slate-600">Lu</button>
          <?php endif ?>
          <button onclick="deleteNotif(<?= $n['id'] ?>)" class="text-xs text-red-400 hover:text-red-600">✕</button>
        </div>
      </div>
      <?php endforeach ?>
    </div>

    <?php if ($total > $per_page): ?>
    <div class="mt-6 text-center">
      <?php if ($page > 1): ?>
      <a href="?page=<?= $page - 1 ?>" class="text-violet-600 text-sm hover:underline mr-4">← Précédent</a>
      <?php endif ?>
      <span class="text-slate-400 text-sm"><?= $page ?> / <?= ceil($total / $per_page) ?></span>
      <?php if ($page * $per_page < $total): ?>
      <a href="?page=<?= $page + 1 ?>" class="text-violet-600 text-sm hover:underline ml-4">Suivant →</a>
      <?php endif ?>
    </div>
    <?php endif ?>
  <?php endif ?>
</div>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function markRead(id) {
  await fetch(`<?= BASE_URL ?>/v2/notifications/${id}/read`, {method:'POST', headers:{'X-CSRF-Token':csrf}});
  const el = document.getElementById('notif-' + id);
  if (el) el.classList.remove('border-violet-200','bg-violet-50/40');
  location.reload();
}

async function markAllRead() {
  await fetch('<?= BASE_URL ?>/v2/notifications/read-all', {method:'POST', headers:{'X-CSRF-Token':csrf}});
  location.reload();
}

async function deleteNotif(id) {
  if (!confirm('Supprimer cette notification ?')) return;
  await fetch(`<?= BASE_URL ?>/v2/notifications/${id}`, {method:'DELETE', headers:{'X-CSRF-Token':csrf}});
  document.getElementById('notif-' + id)?.remove();
}
</script>
