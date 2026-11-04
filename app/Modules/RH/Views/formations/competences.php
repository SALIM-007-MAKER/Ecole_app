<?php
$e = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$niveauColors = $model::COMPETENCE_NIVEAU_COLORS ?? [];
// Index emp competences by competence_id for quick lookup
$empCompById = [];
foreach ($empComps as $ec) {
    $empCompById[(int)$ec['competence_id']] = $ec;
}
// Group competences by category
$cats = [];
foreach ($competences as $c) {
    $cats[$c['categorie']][] = $c;
}
?>
<div class="space-y-6">
  <div class="flex justify-between items-center">
    <div>
      <h2 class="text-xl font-semibold text-slate-800">Compétences</h2>
      <p class="text-sm text-slate-500">
        <?= $employeId ? count($empComps) . ' compétence(s) validée(s)' : 'Référentiel de compétences' ?>
      </p>
    </div>
    <div class="flex gap-2">
      <a href="<?= BASE_URL ?>/v2/rh/formations" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour
      </a>
      <?php if ($employeId && $policy->canValidate($user)): ?>
      <button onclick="document.getElementById('modal-comp').classList.remove('hidden')" class="btn btn-primary">
        <i data-lucide="plus" class="w-4 h-4"></i>Valider compétence
      </button>
      <?php endif; ?>
    </div>
  </div>

  <!-- Filtre employé -->
  <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
    <form method="GET" class="flex gap-3 items-end">
      <div class="flex-1">
        <label class="form-label text-xs mb-1">Employé</label>
        <select name="employe_id" class="form-select">
          <option value="">— Référentiel complet —</option>
          <?php foreach ($employes as $emp): ?>
          <option value="<?= (int)$emp['id'] ?>" <?= $employeId == $emp['id'] ? 'selected' : '' ?>>
            <?= $e($emp['nom_complet']) ?> (<?= $e($emp['matricule']) ?>)
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">Afficher</button>
      <a href="<?= BASE_URL ?>/v2/rh/formations/competences" class="btn btn-secondary">Réinitialiser</a>
    </form>
  </div>

  <!-- Flash -->
  <?php if ($flash = \Core\Session::getFlash('success')): ?>
  <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm"><?= $e($flash) ?></div>
  <?php endif; ?>
  <?php if ($flash = \Core\Session::getFlash('error')): ?>
  <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm"><?= $e($flash) ?></div>
  <?php endif; ?>

  <!-- Référentiel par catégorie -->
  <div class="space-y-4">
    <?php foreach ($cats as $cat => $comps): ?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
      <div class="px-5 py-3 bg-slate-50 border-b border-slate-200">
        <h3 class="font-semibold text-slate-700 capitalize"><?= $e(str_replace('_',' ',$cat)) ?></h3>
      </div>
      <div class="divide-y divide-slate-100">
        <?php foreach ($comps as $c):
          $ec   = $empCompById[(int)$c['id']] ?? null;
          $nc   = $ec ? ($niveauColors[$ec['niveau']] ?? 'slate') : null;
        ?>
        <div class="px-5 py-3 flex items-center justify-between">
          <div>
            <div class="font-medium text-slate-800"><?= $e($c['nom']) ?></div>
            <?php if ($c['description']): ?>
            <div class="text-xs text-slate-400 mt-0.5"><?= $e($c['description']) ?></div>
            <?php endif; ?>
          </div>
          <div class="flex items-center gap-3">
            <?php if ($ec): ?>
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $e($nc) ?>-100 text-<?= $e($nc) ?>-700">
              <?= $e(ucfirst($ec['niveau'])) ?>
            </span>
            <?php if ($ec['validee_par_nom'] ?? ''): ?>
            <span class="text-xs text-slate-400" title="Validé par <?= $e($ec['validee_par_nom']) ?>">
              <?= $e(date('d/m/Y', strtotime($ec['date_validation']))) ?>
            </span>
            <?php endif; ?>
            <?php else: ?>
            <span class="text-xs text-slate-300">Non acquis</span>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Modal valider compétence -->
<div id="modal-comp" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
    <h3 class="text-lg font-semibold text-slate-800 mb-4">Valider une compétence</h3>
    <form method="POST" action="<?= BASE_URL ?>/v2/rh/formations/competences">
      <?= \Core\Csrf::field() ?>
      <input type="hidden" name="employe_id" value="<?= (int)$employeId ?>">
      <div class="space-y-4">
        <div>
          <label class="form-label">Compétence <span class="form-required">*</span></label>
          <select name="competence_id" required class="form-select">
            <option value="">Sélectionner…</option>
            <?php foreach ($competences as $c): ?>
            <option value="<?= (int)$c['id'] ?>">[<?= $e(str_replace('_',' ',$c['categorie'])) ?>] <?= $e($c['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Niveau <span class="form-required">*</span></label>
          <select name="niveau" required class="form-select">
            <?php foreach ($model::COMPETENCE_NIVEAUX as $n): ?>
            <option value="<?= $e($n) ?>"><?= $e(ucfirst($n)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Notes</label>
          <textarea name="notes" rows="2"
                    class="form-textarea resize-none"
                    placeholder="Contexte de validation…"></textarea>
        </div>
      </div>
      <div class="flex gap-3 mt-5">
        <button type="submit" class="btn btn-primary">Valider</button>
        <button type="button" onclick="document.getElementById('modal-comp').classList.add('hidden')" class="btn btn-secondary">Annuler</button>
      </div>
    </form>
  </div>
</div>
<script>if(window.lucide)lucide.createIcons();</script>
