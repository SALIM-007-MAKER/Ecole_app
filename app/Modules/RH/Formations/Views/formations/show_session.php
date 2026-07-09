<?php
$e = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$statut = $session['statut'] ?? 'planifiee';
$color  = $model::SESSION_STATUT_COLORS[$statut] ?? 'slate';
$trans  = $model::SESSION_TRANSITIONS[$statut] ?? [];
$inscColors = $model::INSCRIPTION_STATUT_COLORS ?? [];
$taux   = $model::tauxCompletion((int)($session['nb_inscrits'] ?? 0), (int)($session['max_participants'] ?? 1));
?>
<div class="space-y-6">
  <!-- Header -->
  <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
    <div class="flex items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <a href="/v2/rh/formations" class="text-slate-400 hover:text-slate-600 text-xs">Sessions</a>
          <span class="text-slate-300">/</span>
          <span class="text-slate-700 font-mono text-xs"><?= $e($session['code_session']) ?></span>
        </div>
        <h2 class="text-xl font-semibold text-slate-800"><?= $e($session['formation_titre']) ?></h2>
        <p class="text-sm text-slate-500 mt-0.5"><?= $e($session['formation_type']) ?> · <?= $e($session['modalite'] ?? '') ?></p>
      </div>
      <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium bg-<?= $e($color) ?>-100 text-<?= $e($color) ?>-700">
        <?= $e(ucfirst(str_replace('_',' ',$statut))) ?>
      </span>
    </div>

    <!-- Infos -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 pt-4 border-t border-slate-100">
      <div>
        <div class="text-xs text-slate-400 mb-0.5">Dates</div>
        <div class="text-sm font-medium text-slate-700">
          <?= $e(date('d/m/Y', strtotime($session['date_debut']))) ?>
          <?php if ($session['date_fin']): ?> → <?= $e(date('d/m/Y', strtotime($session['date_fin']))) ?><?php endif; ?>
        </div>
      </div>
      <div>
        <div class="text-xs text-slate-400 mb-0.5">Lieu</div>
        <div class="text-sm font-medium text-slate-700"><?= $e($session['lieu'] ?? '—') ?></div>
      </div>
      <div>
        <div class="text-xs text-slate-400 mb-0.5">Formateur</div>
        <div class="text-sm font-medium text-slate-700"><?= $e($session['formateur_nom'] ?? '—') ?></div>
      </div>
      <div>
        <div class="text-xs text-slate-400 mb-0.5">Capacité</div>
        <div class="flex items-center gap-2">
          <span class="text-sm font-medium text-slate-700"><?= (int)$session['nb_inscrits'] ?>/<?= (int)$session['max_participants'] ?></span>
          <div class="flex-1 bg-slate-200 rounded-full h-1.5 min-w-[60px]">
            <div class="bg-violet-500 h-1.5 rounded-full" style="width:<?= min(100, $taux) ?>%"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Transitions -->
    <?php if ($policy->canUpdate($user) || $policy->canValidate($user)): ?>
    <div class="flex gap-2 flex-wrap mt-4 pt-4 border-t border-slate-100">
      <?php if (in_array('ouvrir', $trans) && $policy->canUpdate($user)): ?>
      <form method="POST" action="/v2/rh/formations/sessions/<?= (int)$session['id'] ?>/ouvrir">
        <?= \Core\Csrf::field() ?>
        <button class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition">
          <i data-lucide="unlock" class="w-4 h-4 inline mr-1"></i>Ouvrir inscriptions
        </button>
      </form>
      <?php endif; ?>
      <?php if (in_array('demarrer', $trans) && $policy->canUpdate($user)): ?>
      <form method="POST" action="/v2/rh/formations/sessions/<?= (int)$session['id'] ?>/demarrer">
        <?= \Core\Csrf::field() ?>
        <button class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 transition">
          <i data-lucide="play" class="w-4 h-4 inline mr-1"></i>Démarrer
        </button>
      </form>
      <?php endif; ?>
      <?php if (in_array('terminer', $trans) && $policy->canValidate($user)): ?>
      <form method="POST" action="/v2/rh/formations/sessions/<?= (int)$session['id'] ?>/terminer">
        <?= \Core\Csrf::field() ?>
        <button class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-violet-700 transition">
          <i data-lucide="check-circle" class="w-4 h-4 inline mr-1"></i>Terminer
        </button>
      </form>
      <?php endif; ?>
      <?php if (in_array('annuler', $trans) && $policy->canUpdate($user)): ?>
      <form method="POST" action="/v2/rh/formations/sessions/<?= (int)$session['id'] ?>/annuler"
            onsubmit="return confirm('Confirmer l\'annulation de cette session ?')">
        <?= \Core\Csrf::field() ?>
        <button class="border border-red-300 text-red-600 px-4 py-2 rounded-lg text-sm hover:bg-red-50 transition">
          <i data-lucide="x-circle" class="w-4 h-4 inline mr-1"></i>Annuler session
        </button>
      </form>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Flash -->
  <?php if ($flash = \Core\Session::getFlash('success')): ?>
  <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm"><?= $e($flash) ?></div>
  <?php endif; ?>
  <?php if ($flash = \Core\Session::getFlash('error')): ?>
  <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm"><?= $e($flash) ?></div>
  <?php endif; ?>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Liste inscriptions -->
    <div class="lg:col-span-2 space-y-4">
      <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex justify-between items-center">
          <h3 class="font-semibold text-slate-700">Participants (<?= count($inscriptions) ?>)</h3>
          <?php if ($policy->canEnroll($user) && in_array($statut, ['planifiee','ouverte'])): ?>
          <button onclick="document.getElementById('modal-inscrire').classList.remove('hidden')"
                  class="bg-violet-600 text-white px-3 py-1.5 rounded-lg text-xs hover:bg-violet-700 transition">
            <i data-lucide="user-plus" class="w-3.5 h-3.5 inline mr-1"></i>Inscrire
          </button>
          <?php endif; ?>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="text-left px-4 py-2.5 text-slate-500 font-medium">Employé</th>
              <th class="text-center px-4 py-2.5 text-slate-500 font-medium">Statut</th>
              <th class="text-center px-4 py-2.5 text-slate-500 font-medium">Note</th>
              <th class="text-center px-4 py-2.5 text-slate-500 font-medium">Attestation</th>
              <th class="text-right px-4 py-2.5 text-slate-500 font-medium">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php if (empty($inscriptions)): ?>
            <tr><td colspan="5" class="text-center py-8 text-slate-400">Aucun participant</td></tr>
            <?php endif; ?>
            <?php foreach ($inscriptions as $insc):
              $ic = $inscColors[$insc['statut']] ?? 'slate';
            ?>
            <tr class="hover:bg-slate-50">
              <td class="px-4 py-3">
                <div class="font-medium text-slate-800"><?= $e($insc['employe_nom'] ?? '—') ?></div>
                <div class="text-xs text-slate-400"><?= $e($insc['employe_matricule'] ?? '') ?></div>
              </td>
              <td class="px-4 py-3 text-center">
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $e($ic) ?>-100 text-<?= $e($ic) ?>-700">
                  <?= $e(ucfirst($insc['statut'])) ?>
                </span>
              </td>
              <td class="px-4 py-3 text-center text-slate-600">
                <?= $insc['note_evaluation'] !== null ? number_format((float)$insc['note_evaluation'], 1) . '/20' : '—' ?>
              </td>
              <td class="px-4 py-3 text-center">
                <?php if ($insc['attestation_delivree']): ?>
                <i data-lucide="check" class="w-4 h-4 text-green-500 mx-auto"></i>
                <?php else: ?>
                <span class="text-slate-300">—</span>
                <?php endif; ?>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex gap-1 justify-end">
                  <?php if (in_array($statut, ['en_cours']) && $policy->canUpdate($user)): ?>
                  <form method="POST" action="/v2/rh/formations/inscriptions/<?= (int)$insc['id'] ?>/presence">
                    <?= \Core\Csrf::field() ?>
                    <input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>">
                    <input type="hidden" name="present" value="<?= $insc['statut'] === 'present' ? '0' : '1' ?>">
                    <button class="text-xs border border-slate-200 px-2 py-1 rounded hover:bg-slate-50">
                      <?= $insc['statut'] === 'present' ? 'Absent' : 'Présent' ?>
                    </button>
                  </form>
                  <?php endif; ?>
                  <?php if ($policy->canValidate($user) && !in_array($insc['statut'], ['valide','annule'])): ?>
                  <button onclick="openValiderModal(<?= (int)$insc['id'] ?>, '<?= $e($insc['employe_nom'] ?? '') ?>')"
                          class="text-xs bg-green-600 text-white px-2 py-1 rounded hover:bg-green-700">
                    Valider
                  </button>
                  <?php endif; ?>
                  <?php if (!in_array($insc['statut'], ['valide','annule']) && $policy->canEnroll($user)): ?>
                  <form method="POST" action="/v2/rh/formations/inscriptions/<?= (int)$insc['id'] ?>/annuler"
                        onsubmit="return confirm('Annuler l\'inscription ?')">
                    <?= \Core\Csrf::field() ?>
                    <input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>">
                    <button class="text-xs border border-red-200 text-red-600 px-2 py-1 rounded hover:bg-red-50">×</button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Compétences développées -->
    <div class="space-y-4">
      <?php if (!empty($competences)): ?>
      <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
        <h3 class="font-semibold text-slate-700 mb-3">Compétences développées</h3>
        <div class="space-y-2">
          <?php foreach ($competences as $comp): ?>
          <div class="flex items-center gap-2 text-sm">
            <i data-lucide="check-circle" class="w-4 h-4 text-violet-500 flex-shrink-0"></i>
            <span class="text-slate-700"><?= $e($comp['nom']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($session['commentaire'] ?? ''): ?>
      <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
        <div class="font-medium mb-1">Commentaire</div>
        <?= $e($session['commentaire']) ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal inscrire -->
<div id="modal-inscrire" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
    <h3 class="text-lg font-semibold text-slate-800 mb-4">Inscrire un employé</h3>
    <form method="POST" action="/v2/rh/formations/sessions/<?= (int)$session['id'] ?>/inscrire">
      <?= \Core\Csrf::field() ?>
      <div class="mb-4">
        <label class="block text-sm font-medium text-slate-700 mb-1">Employé</label>
        <select name="employe_id" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
          <option value="">Sélectionner…</option>
          <?php foreach ($inscriptions as $i): ?>
          <?php /* Exclude already inscribed */ ?>
          <?php endforeach; ?>
        </select>
        <p class="text-xs text-slate-400 mt-1">Les employés déjà inscrits (hors annulés) sont exclus automatiquement.</p>
      </div>
      <div class="flex gap-3">
        <button type="submit" class="bg-violet-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-violet-700 transition">Inscrire</button>
        <button type="button" onclick="document.getElementById('modal-inscrire').classList.add('hidden')"
                class="border border-slate-200 text-slate-600 px-5 py-2 rounded-lg text-sm hover:bg-slate-50 transition">Annuler</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal valider inscription -->
<div id="modal-valider" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
    <h3 class="text-lg font-semibold text-slate-800 mb-1">Valider la participation</h3>
    <p id="modal-valider-nom" class="text-sm text-slate-500 mb-4"></p>
    <form id="form-valider" method="POST">
      <?= \Core\Csrf::field() ?>
      <input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>">
      <div class="mb-3">
        <label class="block text-sm font-medium text-slate-700 mb-1">Note /20 (optionnel)</label>
        <input type="number" name="note_evaluation" min="0" max="20" step="0.5"
               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
      </div>
      <div class="mb-4">
        <label class="block text-sm font-medium text-slate-700 mb-1">Commentaire</label>
        <textarea name="commentaire_evaluation" rows="2"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none resize-none"></textarea>
      </div>
      <div class="flex gap-3">
        <button type="submit" class="bg-green-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-green-700 transition">Valider et délivrer attestation</button>
        <button type="button" onclick="document.getElementById('modal-valider').classList.add('hidden')"
                class="border border-slate-200 text-slate-600 px-5 py-2 rounded-lg text-sm hover:bg-slate-50 transition">Annuler</button>
      </div>
    </form>
  </div>
</div>

<script>
function openValiderModal(inscId, nom) {
  document.getElementById('modal-valider').classList.remove('hidden');
  document.getElementById('modal-valider-nom').textContent = nom;
  document.getElementById('form-valider').action = '/v2/rh/formations/inscriptions/' + inscId + '/valider';
}
if(window.lucide) lucide.createIcons();
</script>
