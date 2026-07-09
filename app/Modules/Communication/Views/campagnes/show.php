<?php
/** @var array $campagne @var array $stats @var array $messages_sample */
use App\Modules\Communication\Models\CampagneModel;
use App\Modules\Communication\Models\MessageModel;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($titre ?? 'Campagne') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-5xl mx-auto py-8 px-4">
  <div class="flex items-center gap-3 mb-6">
    <a href="/v2/communication/campagnes" class="text-slate-400 hover:text-slate-600">←</a>
    <h1 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($campagne['nom']) ?></h1>
    <span class="text-xs px-2 py-0.5 rounded-full
      <?= match($campagne['statut']) {
        'terminee'  => 'bg-green-100 text-green-700',
        'en_cours'  => 'bg-blue-100 text-blue-700',
        'planifiee' => 'bg-yellow-100 text-yellow-700',
        'annulee'   => 'bg-red-100 text-red-700',
        default     => 'bg-slate-100 text-slate-600'
      } ?>">
      <?= CampagneModel::labelStatut($campagne['statut']) ?>
    </span>
  </div>

  <!-- Stats -->
  <div class="grid grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl p-4 shadow-sm border border-slate-100 text-center">
      <div class="text-2xl font-bold text-slate-800"><?= $campagne['total_destinataires'] ?></div>
      <div class="text-xs text-slate-500 mt-1">Destinataires</div>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-slate-100 text-center">
      <div class="text-2xl font-bold text-blue-600"><?= $campagne['total_envoyes'] ?></div>
      <div class="text-xs text-slate-500 mt-1">Envoyés</div>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-slate-100 text-center">
      <div class="text-2xl font-bold text-green-600"><?= $stats['success'] ?? 0 ?></div>
      <div class="text-xs text-slate-500 mt-1">Succès</div>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-slate-100 text-center">
      <div class="text-2xl font-bold text-red-600"><?= $stats['failed'] ?? 0 ?></div>
      <div class="text-xs text-slate-500 mt-1">Échecs</div>
    </div>
  </div>

  <!-- Barre de progression -->
  <?php if ($campagne['total_destinataires'] > 0): ?>
  <div class="bg-white rounded-xl p-4 shadow-sm border border-slate-100 mb-6">
    <div class="flex justify-between text-xs text-slate-500 mb-2">
      <span>Progression</span>
      <span><?= round($campagne['total_envoyes'] / $campagne['total_destinataires'] * 100) ?>%</span>
    </div>
    <div class="w-full bg-slate-100 rounded-full h-2">
      <div class="bg-violet-500 h-2 rounded-full" style="width: <?= round($campagne['total_envoyes'] / $campagne['total_destinataires'] * 100) ?>%"></div>
    </div>
  </div>
  <?php endif ?>

  <!-- Messages sample -->
  <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100">
      <h3 class="font-medium text-slate-700 text-sm">Derniers messages</h3>
    </div>
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-400 text-xs uppercase">
        <tr>
          <th class="text-left px-4 py-2">Destinataire</th>
          <th class="text-left px-4 py-2">Canal</th>
          <th class="text-left px-4 py-2">Statut</th>
          <th class="text-left px-4 py-2">Envoyé le</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php foreach ($messages_sample as $m): ?>
        <tr>
          <td class="px-4 py-2"><?= htmlspecialchars($m['destinataire_email'] ?? $m['destinataire_user_id'] ?? '—') ?></td>
          <td class="px-4 py-2 uppercase text-xs"><?= htmlspecialchars($m['canal']) ?></td>
          <td class="px-4 py-2">
            <span class="text-xs px-2 py-0.5 rounded-full <?= $m['statut'] === 'sent' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
              <?= MessageModel::labelStatut($m['statut']) ?>
            </span>
          </td>
          <td class="px-4 py-2 text-slate-400 text-xs"><?= $m['sent_at'] ? date('d/m H:i', strtotime($m['sent_at'])) : '—' ?></td>
        </tr>
        <?php endforeach ?>
        <?php if (empty($messages_sample)): ?>
        <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Aucun message</td></tr>
        <?php endif ?>
      </tbody>
    </table>
  </div>

  <!-- Actions -->
  <?php if (CampagneModel::peutEtreModifiee($campagne['statut'])): ?>
  <div class="mt-4 flex gap-3">
    <button onclick="lancer()" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">Lancer la campagne</button>
    <button onclick="annuler()" class="bg-red-100 text-red-700 px-4 py-2 rounded-lg text-sm hover:bg-red-200">Annuler</button>
  </div>
  <?php endif ?>
</div>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const id = <?= $campagne['id'] ?>;

async function lancer() {
  if (!confirm('Lancer cette campagne ?')) return;
  const r = await fetch(`/v2/communication/campagnes/${id}/launch`, { method: 'POST', headers: { 'X-CSRF-Token': csrf } });
  if ((await r.json()).success) location.reload();
}

async function annuler() {
  if (!confirm('Annuler cette campagne ?')) return;
  const r = await fetch(`/v2/communication/campagnes/${id}/cancel`, { method: 'POST', headers: { 'X-CSRF-Token': csrf } });
  if ((await r.json()).success) location.reload();
}
</script>
</body>
</html>
