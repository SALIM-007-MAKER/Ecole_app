<?php /** @var string $titre */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($titre ?? 'Nouveau message') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-2xl mx-auto py-8 px-4">

  <div class="flex items-center gap-3 mb-6">
    <a href="/v2/messages" class="text-slate-400 hover:text-slate-600">←</a>
    <h1 class="text-2xl font-bold text-slate-800">Nouveau message</h1>
  </div>

  <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
    <form id="create-form" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Destinataires (IDs séparés par virgule)</label>
        <input type="text" name="participant_ids" required
               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Sujet</label>
        <input type="text" name="sujet" required
               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Message</label>
        <textarea name="corps_initial" rows="5" required
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400"></textarea>
      </div>
      <button type="submit" class="w-full bg-violet-600 text-white py-2 rounded-lg font-medium hover:bg-violet-700">
        Envoyer
      </button>
    </form>
  </div>
</div>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
document.getElementById('create-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const data = new FormData(e.target);
  const r = await fetch('/v2/messages', {
    method: 'POST',
    headers: { 'X-CSRF-Token': csrf },
    body: new URLSearchParams(data)
  });
  const j = await r.json();
  if (j.success) {
    window.location.href = '/v2/messages/' + j.thread_id;
  } else {
    alert('Erreur : ' + (j.errors || []).join(', '));
  }
});
</script>
</body>
</html>
