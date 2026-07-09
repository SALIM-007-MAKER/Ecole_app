<?php
$e = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$certColors = $model::CERT_STATUT_COLORS ?? [];
?>
<div class="space-y-6">
  <div class="flex justify-between items-center">
    <div>
      <h2 class="text-xl font-semibold text-slate-800">Certifications</h2>
      <p class="text-sm text-slate-500">
        <?= $employeId ? 'Certifications de l\'employé sélectionné' : 'Certifications expirantes (60 jours)' ?>
      </p>
    </div>
    <div class="flex gap-2">
      <a href="/v2/rh/formations" class="border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm hover:bg-slate-50 transition">
        <i data-lucide="arrow-left" class="w-4 h-4 inline mr-1"></i>Retour
      </a>
      <?php if ($policy->canValidate($user)): ?>
      <button onclick="document.getElementById('modal-cert').classList.remove('hidden')"
              class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-violet-700 transition">
        <i data-lucide="award" class="w-4 h-4 inline mr-1"></i>Accorder certification
      </button>
      <?php endif; ?>
    </div>
  </div>

  <!-- Filtre employé -->
  <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
    <form method="GET" class="flex gap-3 items-end">
      <div class="flex-1">
        <label class="block text-xs text-slate-500 mb-1">Filtrer par employé</label>
        <select name="employe_id" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
          <option value="">— Certifications à renouveler/expirées —</option>
          <?php foreach ($employes as $emp): ?>
          <option value="<?= (int)$emp['id'] ?>" <?= $employeId == $emp['id'] ? 'selected' : '' ?>>
            <?= $e($emp['nom_complet']) ?> (<?= $e($emp['matricule']) ?>)
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-violet-700 transition">Filtrer</button>
      <a href="/v2/rh/formations/certifications" class="border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm hover:bg-slate-50">Réinitialiser</a>
    </form>
  </div>

  <!-- Flash -->
  <?php if ($flash = \Core\Session::getFlash('success')): ?>
  <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm"><?= $e($flash) ?></div>
  <?php endif; ?>
  <?php if ($flash = \Core\Session::getFlash('error')): ?>
  <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm"><?= $e($flash) ?></div>
  <?php endif; ?>

  <!-- Table -->
  <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="text-left px-4 py-3 text-slate-500 font-medium">Certification</th>
          <th class="text-left px-4 py-3 text-slate-500 font-medium">Employé</th>
          <th class="text-center px-4 py-3 text-slate-500 font-medium">Obtention</th>
          <th class="text-center px-4 py-3 text-slate-500 font-medium">Expiration</th>
          <th class="text-center px-4 py-3 text-slate-500 font-medium">Statut</th>
          <th class="text-left px-4 py-3 text-slate-500 font-medium">Référence</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php if (empty($certs)): ?>
        <tr><td colspan="6" class="text-center py-10 text-slate-400">
          <?= $employeId ? 'Cet employé n\'a pas de certification' : 'Aucune certification à surveiller' ?>
        </td></tr>
        <?php endif; ?>
        <?php foreach ($certs as $c):
          $cc = $certColors[$c['statut']] ?? 'slate';
          $isExpiringSoon = ($c['date_expiration'] ?? '') && strtotime($c['date_expiration']) <= strtotime('+30 days') && $c['statut'] !== 'expiree';
        ?>
        <tr class="hover:bg-slate-50 <?= $isExpiringSoon ? 'bg-amber-50' : '' ?>">
          <td class="px-4 py-3">
            <div class="font-medium text-slate-800"><?= $e($c['cert_nom'] ?? $c['cert_code'] ?? '—') ?></div>
            <div class="text-xs text-slate-400 font-mono"><?= $e($c['cert_code'] ?? '') ?></div>
          </td>
          <td class="px-4 py-3 text-slate-700"><?= $e($c['employe_nom'] ?? '—') ?></td>
          <td class="px-4 py-3 text-center text-slate-600">
            <?= $c['date_obtention'] ? $e(date('d/m/Y', strtotime($c['date_obtention']))) : '—' ?>
          </td>
          <td class="px-4 py-3 text-center">
            <?php if ($c['date_expiration']): ?>
              <span class="<?= $isExpiringSoon ? 'text-amber-600 font-medium' : 'text-slate-600' ?>">
                <?= $e(date('d/m/Y', strtotime($c['date_expiration']))) ?>
              </span>
            <?php else: ?>
              <span class="text-green-600 text-xs font-medium">Permanente</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3 text-center">
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $e($cc) ?>-100 text-<?= $e($cc) ?>-700">
              <?= $e(ucfirst(str_replace('_',' ',$c['statut']))) ?>
            </span>
          </td>
          <td class="px-4 py-3 text-slate-500 text-xs"><?= $e($c['reference_certificat'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal accorder certification -->
<div id="modal-cert" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
    <h3 class="text-lg font-semibold text-slate-800 mb-4">Accorder une certification</h3>
    <form method="POST" action="/v2/rh/formations/certifications">
      <?= \Core\Csrf::field() ?>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Employé <span class="text-red-500">*</span></label>
          <select name="employe_id" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
            <option value="">Sélectionner…</option>
            <?php foreach ($employes as $emp): ?>
            <option value="<?= (int)$emp['id'] ?>" <?= $employeId == $emp['id'] ? 'selected' : '' ?>>
              <?= $e($emp['nom_complet']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Certification <span class="text-red-500">*</span></label>
          <select name="certification_id" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
            <option value="">Sélectionner…</option>
            <?php foreach ($catalogue as $cert): ?>
            <option value="<?= (int)$cert['id'] ?>">
              <?= $e($cert['nom']) ?> (<?= $e($cert['code']) ?>)
              <?php if ($cert['duree_validite_mois']): ?> — <?= (int)$cert['duree_validite_mois'] ?> mois<?php else: ?> — Permanente<?php endif; ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Date d'obtention</label>
            <input type="date" name="date_obtention" value="<?= date('Y-m-d') ?>"
                   class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Réf. certificat</label>
            <input type="text" name="reference_certificat" placeholder="N° certificat"
                   class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
          <textarea name="notes" rows="2"
                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none resize-none"></textarea>
        </div>
      </div>
      <div class="flex gap-3 mt-5">
        <button type="submit" class="bg-violet-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-violet-700 transition">Accorder</button>
        <button type="button" onclick="document.getElementById('modal-cert').classList.add('hidden')"
                class="border border-slate-200 text-slate-600 px-5 py-2 rounded-lg text-sm hover:bg-slate-50 transition">Annuler</button>
      </div>
    </form>
  </div>
</div>
<script>if(window.lucide)lucide.createIcons();</script>
