<?php
/** @var array $affectation, $matieres, $historique */
/** @var string $model */
/** @var bool $canUpdate, $canArchive */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>

  <!-- En-tête -->
  <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
    <a href="<?= BASE_URL ?>/v2/rh/affectations" class="hover:text-violet-600">Affectations</a>
    <i data-lucide="chevron-right" class="w-3 h-3"></i>
    <span class="text-slate-700"><?= e($affectation['employe_nom']) ?></span>
  </div>
  <div class="flex items-start justify-between gap-4 mb-8">
    <div class="flex items-start gap-4">
      <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="shuffle" class="w-5 h-5 text-violet-600"></i>
      </div>
      <div>
      <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-3">
        <?= e($affectation['employe_nom']) ?>
        <span class="px-2 py-0.5 text-sm rounded-full <?= $model::typeColor($affectation['type']) ?>"><?= e($model::typeLabel($affectation['type'])) ?></span>
        <span class="px-2 py-0.5 text-sm rounded-full <?= $model::statutColor($affectation['statut']) ?>"><?= e($model::statutLabel($affectation['statut'])) ?></span>
      </h1>
      <p class="text-sm text-slate-400 mt-1"><?= e($affectation['employe_matricule'] ?? '') ?></p>
      </div>
    </div>
    <div class="flex gap-2 flex-shrink-0">
      <?php if ($canUpdate && $affectation['statut'] !== 'terminee'): ?>
        <a href="<?= BASE_URL ?>/v2/rh/affectations/<?= (int)$affectation['id'] ?>/edit" class="btn btn-secondary">Modifier</a>
        <button onclick="document.getElementById('modal-transferer').classList.remove('hidden')" class="btn btn-primary">Transfert</button>
      <?php endif; ?>
      <?php if ($canUpdate && $affectation['statut'] === 'active'): ?>
        <form method="POST" action="<?= BASE_URL ?>/v2/rh/affectations/<?= (int)$affectation['id'] ?>/suspendre">
          <?= \Core\Csrf::field() ?>
          <button onclick="return confirm('Suspendre cette affectation ?')" class="btn btn-warning">Suspendre</button>
        </form>
        <button onclick="document.getElementById('modal-clore').classList.remove('hidden')" class="btn btn-primary">Clôturer</button>
      <?php endif; ?>
      <?php if ($canUpdate && $affectation['statut'] === 'suspendue'): ?>
        <form method="POST" action="<?= BASE_URL ?>/v2/rh/affectations/<?= (int)$affectation['id'] ?>/reactiver">
          <?= \Core\Csrf::field() ?>
          <button onclick="return confirm('Réactiver cette affectation ?')" class="btn btn-success">Réactiver</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($flash = \Core\Session::getFlash('success')): ?>
    <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>
  <?php if ($flash = \Core\Session::getFlash('error')): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>

  <div class="grid grid-cols-3 gap-6">

    <div class="col-span-2 space-y-6">

      <!-- Infos principales -->
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h2 class="font-semibold text-slate-700 mb-4">Position organisationnelle</h2>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
          <div>
            <dt class="text-slate-400">Poste</dt>
            <dd class="mt-0.5 font-medium"><?= e($affectation['poste_intitule'] ?? '—') ?></dd>
          </div>
          <div>
            <dt class="text-slate-400">Catégorie</dt>
            <dd class="mt-0.5"><?= e($affectation['poste_categorie'] ?? '—') ?></dd>
          </div>
          <div>
            <dt class="text-slate-400">Département</dt>
            <dd class="mt-0.5"><?= e($affectation['departement_nom'] ?? '—') ?> <?= $affectation['departement_code'] ? '<span class="text-xs text-slate-400">(' . e($affectation['departement_code']) . ')</span>' : '' ?></dd>
          </div>
          <div>
            <dt class="text-slate-400">Service</dt>
            <dd class="mt-0.5"><?= e($affectation['service_nom'] ?? '—') ?></dd>
          </div>
          <div>
            <dt class="text-slate-400">Responsable direct</dt>
            <dd class="mt-0.5"><?= e($affectation['responsable_nom'] ?? '—') ?></dd>
          </div>
          <div>
            <dt class="text-slate-400">Contrat lié</dt>
            <dd class="mt-0.5">
              <?php if ($affectation['contrat_numero']): ?>
                <a href="<?= BASE_URL ?>/v2/rh/contrats/<?= (int)$affectation['contrat_id'] ?>" class="font-mono text-violet-600 hover:underline text-xs"><?= e($affectation['contrat_numero']) ?></a>
                <span class="ml-1 px-1.5 py-0.5 text-xs rounded bg-emerald-100 text-emerald-700"><?= e($affectation['contrat_statut']) ?></span>
              <?php else: ?>
                <span class="text-slate-400">—</span>
              <?php endif; ?>
            </dd>
          </div>
          <div>
            <dt class="text-slate-400">Période</dt>
            <dd class="mt-0.5"><?= e(date('d/m/Y', strtotime($affectation['date_debut']))) ?>
              <?= $affectation['date_fin'] ? '→ ' . e(date('d/m/Y', strtotime($affectation['date_fin']))) : '→ <span class="text-slate-400">Indéterminée</span>' ?></dd>
          </div>
          <?php if ($affectation['notes']): ?>
          <div class="col-span-2">
            <dt class="text-slate-400">Notes</dt>
            <dd class="mt-0.5 text-slate-600"><?= nl2br(e($affectation['notes'])) ?></dd>
          </div>
          <?php endif; ?>
        </dl>
      </div>

      <!-- Matières / Classes -->
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-semibold text-slate-700">Matières & Classes (<?= count($matieres) ?>)</h2>
          <?php if ($canUpdate && $affectation['statut'] === 'active'): ?>
            <button onclick="document.getElementById('modal-matiere').classList.remove('hidden')" class="btn btn-outline btn-sm">
              + Ajouter
            </button>
          <?php endif; ?>
        </div>
        <?php if (empty($matieres)): ?>
          <p class="text-sm text-slate-400">Aucune matière/classe affectée.</p>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-slate-50 text-xs text-slate-500">
                <tr>
                  <th class="px-3 py-2 text-left">Matière</th>
                  <th class="px-3 py-2 text-left">Classe</th>
                  <th class="px-3 py-2 text-left">Niveau</th>
                  <th class="px-3 py-2 text-left">H/sem</th>
                  <th class="px-3 py-2 text-left">Début</th>
                  <th class="px-3 py-2 text-left">Fin</th>
                  <?php if ($canUpdate && $affectation['statut'] === 'active'): ?>
                    <th class="px-3 py-2"></th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <?php foreach ($matieres as $m): ?>
                  <tr class="<?= $m['date_fin'] && $m['date_fin'] < date('Y-m-d') ? 'opacity-50' : '' ?>">
                    <td class="px-3 py-2 font-medium"><?= e($m['matiere_nom'] ?? '—') ?></td>
                    <td class="px-3 py-2 text-slate-600"><?= e($m['classe_nom'] ?? '—') ?></td>
                    <td class="px-3 py-2 text-slate-600"><?= e($m['niveau'] ?? '—') ?></td>
                    <td class="px-3 py-2"><?= $m['heures_hebdo'] ? number_format((float)$m['heures_hebdo'], 1) . 'h' : '—' ?></td>
                    <td class="px-3 py-2 text-xs"><?= e(date('d/m/Y', strtotime($m['date_debut']))) ?></td>
                    <td class="px-3 py-2 text-xs"><?= $m['date_fin'] ? e(date('d/m/Y', strtotime($m['date_fin']))) : '<span class="text-slate-400">∞</span>' ?></td>
                    <?php if ($canUpdate && $affectation['statut'] === 'active' && !($m['date_fin'] && $m['date_fin'] < date('Y-m-d'))): ?>
                      <td class="px-3 py-2">
                        <form method="POST" action="<?= BASE_URL ?>/v2/rh/affectations/<?= (int)$affectation['id'] ?>/matieres/<?= (int)$m['id'] ?>/remove">
                          <?= \Core\Csrf::field() ?>
                          <button onclick="return confirm('Clôturer cette affectation matière ?')"
                                  class="text-xs text-red-500 hover:text-red-700">Clôturer</button>
                        </form>
                      </td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- Historique -->
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h2 class="font-semibold text-slate-700 mb-4">Historique (<?= count($historique) ?>)</h2>
        <?php if (empty($historique)): ?>
          <p class="text-sm text-slate-400">Aucun historique.</p>
        <?php else: ?>
          <ol class="relative border-l border-slate-200 ml-3 space-y-4">
            <?php foreach ($historique as $h): ?>
              <li class="ml-6">
                <div class="absolute -left-2 mt-1 w-4 h-4 rounded-full border-2 border-white bg-slate-300"></div>
                <div class="flex items-start justify-between">
                  <div>
                    <span class="text-xs font-semibold <?= $model::changementColor($h['type_changement']) ?>">
                      <?= e($model::changementLabel($h['type_changement'])) ?>
                    </span>
                    <span class="text-xs text-slate-400 ml-2">par <?= e($h['modifie_par_nom']) ?></span>
                    <?php if ($h['motif']): ?>
                      <p class="text-xs text-slate-500 mt-0.5"><?= e($h['motif']) ?></p>
                    <?php endif; ?>
                    <?php if ($h['ancienne_valeur'] && $h['nouvelle_valeur']): ?>
                      <div class="mt-1 flex gap-3 text-xs">
                        <span class="text-red-500 line-through"><?= e(is_string($h['ancienne_valeur']) ? $h['ancienne_valeur'] : substr(json_encode(json_decode($h['ancienne_valeur'], true), JSON_UNESCAPED_UNICODE), 0, 80)) ?></span>
                        <span class="text-emerald-600">→ <?= e(is_string($h['nouvelle_valeur']) ? $h['nouvelle_valeur'] : substr(json_encode(json_decode($h['nouvelle_valeur'], true), JSON_UNESCAPED_UNICODE), 0, 80)) ?></span>
                      </div>
                    <?php endif; ?>
                  </div>
                  <span class="text-xs text-slate-400 ml-4 flex-shrink-0"><?= e(date('d/m/Y H:i', strtotime($h['created_at']))) ?></span>
                </div>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>
      </div>

    </div>

    <!-- Colonne droite -->
    <div class="space-y-4">
      <div class="bg-white rounded-xl border border-slate-200 p-5 text-sm">
        <h3 class="font-semibold text-slate-700 mb-3">Métadonnées</h3>
        <dl class="space-y-2 text-xs">
          <div>
            <dt class="text-slate-400">Créé le</dt>
            <dd class="mt-0.5"><?= e(date('d/m/Y H:i', strtotime($affectation['created_at']))) ?></dd>
          </div>
          <div>
            <dt class="text-slate-400">Modifié le</dt>
            <dd class="mt-0.5"><?= e(date('d/m/Y H:i', strtotime($affectation['updated_at']))) ?></dd>
          </div>
          <?php if ($affectation['etablissement_id']): ?>
          <div>
            <dt class="text-slate-400">Établissement</dt>
            <dd class="mt-0.5 font-mono text-xs">#<?= (int)$affectation['etablissement_id'] ?> <span class="text-slate-400">(V3)</span></dd>
          </div>
          <?php endif; ?>
        </dl>
      </div>

      <?php if ($canArchive && $affectation['statut'] !== 'active'): ?>
        <button onclick="document.getElementById('modal-archiver').classList.remove('hidden')" class="btn btn-secondary w-full">
          Archiver
        </button>
      <?php endif; ?>
    </div>
  </div>

<!-- Modal Transfert -->
<div id="modal-transferer" class="hidden fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50">
  <div class="bg-white rounded-xl w-full max-w-lg shadow-xl p-6">
    <h2 class="text-lg font-bold text-blue-700 mb-4">Enregistrer un transfert</h2>
    <form method="POST" action="<?= BASE_URL ?>/v2/rh/affectations/<?= (int)$affectation['id'] ?>/transferer">
      <?= \Core\Csrf::field() ?>
      <div class="space-y-3">
        <div>
          <label class="form-label">Motif *</label>
          <input type="text" name="motif" required maxlength="500" placeholder="Ex : Réorganisation, mobilité interne..."
                 class="form-input">
        </div>
        <p class="text-xs text-slate-500">Renseignez uniquement les champs qui changent :</p>
        <?php
        $refPostes = $GLOBALS['refs']['postes'] ?? [];
        $refDepts  = $GLOBALS['refs']['departements'] ?? [];
        ?>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="form-label text-xs mb-1">Nouveau poste</label>
            <select name="poste_id" class="form-select text-sm">
              <option value="">Inchangé</option>
              <?php
              try {
                $stmtP = (new \App\Modules\RH\Affectations\Repositories\AssignmentRepository())->findPostes();
                foreach ($stmtP as $p):
              ?>
                <option value="<?= (int)$p['id'] ?>" <?= $affectation['poste_id'] == $p['id'] ? 'selected' : '' ?>><?= e($p['intitule']) ?></option>
              <?php endforeach; } catch (\Throwable) {} ?>
            </select>
          </div>
          <div>
            <label class="form-label text-xs mb-1">Nouveau département</label>
            <select name="departement_id" class="form-select text-sm">
              <option value="">Inchangé</option>
              <?php
              try {
                $stmtD = (new \App\Modules\RH\Affectations\Repositories\AssignmentRepository())->findDepartements();
                foreach ($stmtD as $d):
              ?>
                <option value="<?= (int)$d['id'] ?>" <?= $affectation['departement_id'] == $d['id'] ? 'selected' : '' ?>><?= e($d['nom']) ?></option>
              <?php endforeach; } catch (\Throwable) {} ?>
            </select>
          </div>
          <div>
            <label class="form-label text-xs mb-1">Nouveau service</label>
            <select name="service_id" class="form-select text-sm">
              <option value="">Inchangé</option>
              <?php
              try {
                $stmtS = (new \App\Modules\RH\Affectations\Repositories\AssignmentRepository())->findServices();
                foreach ($stmtS as $s):
              ?>
                <option value="<?= (int)$s['id'] ?>" <?= $affectation['service_id'] == $s['id'] ? 'selected' : '' ?>><?= e($s['nom']) ?></option>
              <?php endforeach; } catch (\Throwable) {} ?>
            </select>
          </div>
          <div>
            <label class="form-label text-xs mb-1">Nouveau responsable</label>
            <select name="responsable_id" class="form-select text-sm">
              <option value="">Inchangé</option>
              <?php
              try {
                $stmtE = (new \App\Modules\RH\Affectations\Repositories\AssignmentRepository())->findEmployes();
                foreach ($stmtE as $emp):
              ?>
                <option value="<?= (int)$emp['id'] ?>" <?= $affectation['responsable_id'] == $emp['id'] ? 'selected' : '' ?>><?= e($emp['label']) ?></option>
              <?php endforeach; } catch (\Throwable) {} ?>
            </select>
          </div>
        </div>
      </div>
      <div class="flex justify-end gap-3 mt-6">
        <button type="button" onclick="document.getElementById('modal-transferer').classList.add('hidden')"
                class="btn btn-secondary">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer le transfert</button>
      </div>
    </form>
  </div>
</div>
<!-- Modal Clôture -->
<div id="modal-clore" class="hidden fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50">
  <div class="bg-white rounded-xl w-full max-w-md shadow-xl p-6">
    <h2 class="text-lg font-bold text-slate-800 mb-4">Clôturer l'affectation</h2>
    <form method="POST" action="<?= BASE_URL ?>/v2/rh/affectations/<?= (int)$affectation['id'] ?>/clore">
      <?= \Core\Csrf::field() ?>
      <div>
        <label class="form-label">Motif de clôture</label>
        <textarea name="motif" rows="2" placeholder="Ex : Fin de mission, départ..."
                  class="form-textarea"></textarea>
      </div>
      <p class="text-xs text-slate-500 mt-2">La date de fin sera fixée à aujourd'hui. L'affectation sera marquée comme terminée.</p>
      <div class="flex justify-end gap-3 mt-6">
        <button type="button" onclick="document.getElementById('modal-clore').classList.add('hidden')"
                class="btn btn-secondary">Annuler</button>
        <button type="submit" class="btn btn-primary">Clôturer</button>
      </div>
    </form>
  </div>
</div>
<!-- Modal Ajout matière -->
<div id="modal-matiere" class="hidden fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50">
  <div class="bg-white rounded-xl w-full max-w-lg shadow-xl p-6">
    <h2 class="text-lg font-bold text-slate-900 mb-4">Ajouter une matière / classe</h2>
    <form method="POST" action="<?= BASE_URL ?>/v2/rh/affectations/<?= (int)$affectation['id'] ?>/matieres">
      <?= \Core\Csrf::field() ?>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="form-label">Matière</label>
          <select name="matiere_id" class="form-select">
            <option value="">— Non spécifiée —</option>
            <?php
            try {
              foreach ((new \App\Modules\RH\Affectations\Repositories\AssignmentRepository())->findMatieresList() as $m):
            ?>
              <option value="<?= (int)$m['id'] ?>"><?= e($m['nom']) ?></option>
            <?php endforeach; } catch (\Throwable) {} ?>
          </select>
        </div>
        <div>
          <label class="form-label">Classe</label>
          <select name="classe_id" class="form-select">
            <option value="">— Non spécifiée —</option>
            <?php
            try {
              foreach ((new \App\Modules\RH\Affectations\Repositories\AssignmentRepository())->findClassesList() as $cl):
            ?>
              <option value="<?= (int)$cl['id'] ?>"><?= e($cl['nom']) ?></option>
            <?php endforeach; } catch (\Throwable) {} ?>
          </select>
        </div>
        <div>
          <label class="form-label">Niveau</label>
          <input type="text" name="niveau" maxlength="50" placeholder="Ex : 3e, Terminale"
                 class="form-input">
        </div>
        <div>
          <label class="form-label">Heures/semaine</label>
          <input type="number" name="heures_hebdo" step="0.5" min="0" max="40"
                 class="form-input">
        </div>
        <div>
          <label class="form-label">Date début *</label>
          <input type="date" name="date_debut" required value="<?= e(date('Y-m-d')) ?>"
                 class="form-input">
        </div>
        <div>
          <label class="form-label">Date fin</label>
          <input type="date" name="date_fin" class="form-input">
        </div>
      </div>
      <div class="flex justify-end gap-3 mt-6">
        <button type="button" onclick="document.getElementById('modal-matiere').classList.add('hidden')"
                class="btn btn-secondary">Annuler</button>
        <button type="submit" class="btn btn-primary">Ajouter</button>
      </div>
    </form>
  </div>
</div>
<!-- Modal Archivage -->
<div id="modal-archiver" class="hidden fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50">
  <div class="bg-white rounded-xl w-full max-w-md shadow-xl p-6">
    <h2 class="text-lg font-bold text-red-700 mb-4">Archiver l'affectation</h2>
    <form method="POST" action="<?= BASE_URL ?>/v2/rh/affectations/<?= (int)$affectation['id'] ?>/archive">
      <?= \Core\Csrf::field() ?>
      <div>
        <label class="form-label">Motif</label>
        <input type="text" name="motif" value="Archivage manuel" class="form-input">
      </div>
      <div class="flex justify-end gap-3 mt-6">
        <button type="button" onclick="document.getElementById('modal-archiver').classList.add('hidden')"
                class="btn btn-secondary">Annuler</button>
        <button type="submit" class="btn btn-danger">Archiver</button>
      </div>
    </form>
  </div>
</div>
