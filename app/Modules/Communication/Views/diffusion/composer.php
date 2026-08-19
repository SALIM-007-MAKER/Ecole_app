<?php /** @var array $groupes */ ?>
<div class="max-w-2xl mx-auto py-8 px-4">
  <div class="flex items-center gap-3 mb-6">
    <a href="<?= BASE_URL ?>/v2/communication/groupes" class="text-slate-400 hover:text-slate-600">←</a>
    <h1 class="text-2xl font-bold text-slate-800">Composer une diffusion</h1>
  </div>

  <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
    <form id="diffusion-form" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Cible</label>
        <select name="cible_type" id="cible-type" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
          <option value="tous">Tous les utilisateurs</option>
          <option value="groupe">Groupe de diffusion</option>
          <option value="role">Par rôle</option>
        </select>
      </div>

      <div id="field-groupe" class="hidden">
        <label class="block text-sm font-medium text-slate-700 mb-1">Groupe</label>
        <select name="cible_id" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
          <?php foreach ($groupes as $g): ?>
          <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nom']) ?></option>
          <?php endforeach ?>
        </select>
      </div>

      <div id="field-role" class="hidden">
        <label class="block text-sm font-medium text-slate-700 mb-1">Rôle</label>
        <select name="role_code" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
          <option value="parent">Parents</option>
          <option value="enseignant">Enseignants</option>
          <option value="eleve">Élèves</option>
          <option value="secretaire">Secrétaires</option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Canaux</label>
        <div class="flex gap-4">
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="canaux[]" value="internal" checked> Notification interne</label>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="canaux[]" value="email"> Email</label>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="canaux[]" value="push"> Push</label>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Sujet</label>
        <input type="text" name="sujet" required
               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Message</label>
        <textarea name="corps" rows="5" required
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></textarea>
      </div>

      <div id="result" class="hidden bg-green-50 text-green-700 text-sm p-3 rounded-lg"></div>

      <button type="submit" class="w-full bg-violet-600 text-white py-2 rounded-lg font-medium hover:bg-violet-700">
        Diffuser
      </button>
    </form>
  </div>
</div>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

document.getElementById('cible-type').addEventListener('change', (e) => {
  document.getElementById('field-groupe').classList.toggle('hidden', e.target.value !== 'groupe');
  document.getElementById('field-role').classList.toggle('hidden', e.target.value !== 'role');
});

document.getElementById('diffusion-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const r = await fetch('<?= BASE_URL ?>/v2/communication/diffuser', {
    method: 'POST',
    headers: { 'X-CSRF-Token': csrf },
    body: new URLSearchParams(new FormData(e.target))
  });
  const j = await r.json();
  const el = document.getElementById('result');
  el.classList.remove('hidden');
  if (j.success) {
    el.textContent = `Message diffusé à ${j.result?.total ?? 0} destinataires.`;
  } else {
    el.classList.replace('bg-green-50','bg-red-50');
    el.classList.replace('text-green-700','text-red-700');
    el.textContent = (j.errors || []).join(', ');
  }
});
</script>
