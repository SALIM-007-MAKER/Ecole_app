<?php /** @var array $templates @var string|null $filtre_canal */ ?>
<div class="max-w-5xl mx-auto py-8 px-4">

  <div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Templates de communication</h1>
    <a href="<?= BASE_URL ?>/v2/communication/templates/create" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-violet-700">
      + Nouveau template
    </a>
  </div>

  <!-- Filtres -->
  <div class="flex gap-2 mb-4">
    <?php foreach (['', 'email', 'sms', 'push', 'internal'] as $c): ?>
    <a href="?canal=<?= $c ?>"
       class="text-sm px-3 py-1 rounded-full <?= ($filtre_canal ?? '') === $c ? 'bg-violet-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:border-violet-300' ?>">
      <?= $c === '' ? 'Tous' : strtoupper($c) ?>
    </a>
    <?php endforeach ?>
  </div>

  <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
        <tr>
          <th class="text-left px-4 py-3">Code</th>
          <th class="text-left px-4 py-3">Nom</th>
          <th class="text-left px-4 py-3">Canal</th>
          <th class="text-left px-4 py-3">Module</th>
          <th class="px-4 py-3">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php foreach ($templates as $t): ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-3 font-mono text-xs text-slate-600"><?= htmlspecialchars($t['code']) ?></td>
          <td class="px-4 py-3 font-medium text-slate-800"><?= htmlspecialchars($t['nom']) ?></td>
          <td class="px-4 py-3">
            <span class="text-xs px-2 py-0.5 rounded-full
              <?= match($t['canal']) {
                'email'    => 'bg-blue-100 text-blue-700',
                'sms'      => 'bg-green-100 text-green-700',
                'push'     => 'bg-orange-100 text-orange-700',
                'internal' => 'bg-violet-100 text-violet-700',
                default    => 'bg-slate-100 text-slate-600'
              } ?>">
              <?= strtoupper($t['canal']) ?>
            </span>
          </td>
          <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($t['module_source'] ?? '—') ?></td>
          <td class="px-4 py-3 text-right space-x-2">
            <a href="<?= BASE_URL ?>/v2/communication/templates/<?= $t['id'] ?>" class="text-violet-600 text-xs hover:underline">Modifier</a>
            <button onclick="supprimer(<?= $t['id'] ?>)" class="text-red-400 text-xs hover:text-red-600">Supprimer</button>
          </td>
        </tr>
        <?php endforeach ?>
        <?php if (empty($templates)): ?>
        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Aucun template</td></tr>
        <?php endif ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
async function supprimer(id) {
  if (!confirm('Supprimer ce template ?')) return;
  await fetch(`<?= BASE_URL ?>/v2/communication/templates/${id}`, { method: 'DELETE', headers: { 'X-CSRF-Token': csrf } });
  location.reload();
}
</script>
