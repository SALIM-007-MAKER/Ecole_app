<?php /** @var int $thread_id @var array $messages @var int $page */ ?>

<div class="max-w-3xl mx-auto w-full flex flex-col h-screen">

  <!-- Header -->
  <div class="bg-white border-b border-slate-100 px-4 py-3 flex items-center gap-3">
    <a href="<?= BASE_URL ?>/v2/messages" class="text-slate-400 hover:text-slate-600">←</a>
    <h1 class="font-semibold text-slate-800"><?= htmlspecialchars($titre ?? 'Conversation') ?></h1>
  </div>

  <!-- Messages -->
  <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3" id="messages-list">
    <?php if (empty($messages)): ?>
      <p class="text-center text-slate-400 text-sm">Aucun message</p>
    <?php else: ?>
      <?php foreach ($messages as $m): ?>
      <?php $isMine = (int) $m['user_id'] === (int) ($_SESSION['user']['id'] ?? 0); ?>
      <div class="flex <?= $isMine ? 'justify-end' : 'justify-start' ?>">
        <div class="max-w-xs lg:max-w-md px-4 py-2 rounded-xl text-sm
          <?= $isMine ? 'bg-violet-600 text-white' : 'bg-white border border-slate-100 text-slate-800' ?>">
          <?php if (!$isMine): ?>
          <p class="text-xs font-medium mb-1 opacity-60">
            <?= htmlspecialchars(($m['prenom'] ?? '') . ' ' . ($m['nom'] ?? '')) ?>
          </p>
          <?php endif ?>
          <p><?= nl2br(htmlspecialchars($m['corps'])) ?></p>
          <p class="text-xs mt-1 opacity-60 text-right">
            <?= date('H:i', strtotime($m['created_at'])) ?>
          </p>
        </div>
      </div>
      <?php endforeach ?>
    <?php endif ?>
  </div>

  <!-- Formulaire réponse -->
  <div class="bg-white border-t border-slate-100 px-4 py-3">
    <form id="reply-form" class="flex gap-2">
      <input type="text" id="reply-corps" placeholder="Votre message..."
             class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400">
      <button type="submit"
              class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-violet-700">
        Envoyer
      </button>
    </form>
  </div>
</div>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
document.getElementById('reply-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const corps = document.getElementById('reply-corps').value.trim();
  if (!corps) return;

  const r = await fetch('<?= BASE_URL ?>/v2/messages/<?= $thread_id ?>/reply', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf },
    body: new URLSearchParams({ corps })
  });

  if (r.ok) {
    document.getElementById('reply-corps').value = '';
    location.reload();
  }
});

// Scroll vers le bas
const list = document.getElementById('messages-list');
if (list) list.scrollTop = list.scrollHeight;
</script>
