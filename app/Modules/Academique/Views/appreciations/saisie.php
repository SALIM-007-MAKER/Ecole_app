<?php
$classe  = $classe  ?? null;
$matiere = $matiere ?? null;
$periode = $periode ?? null;
$eleves  = $eleves  ?? [];

if (!$classe || !$matiere || !$periode) return;
?>

<div class="flex items-center gap-3 mb-6">
    <a href="<?= BASE_URL ?>/v2/academique/appreciations"
       class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition">
        <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
        <h2 class="text-xl font-bold text-slate-900">
            Appréciations — <?= htmlspecialchars($matiere->nom, ENT_QUOTES) ?>
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">
            <?= htmlspecialchars($classe->niveau . ' ' . $classe->nom, ENT_QUOTES) ?>
            · <?= htmlspecialchars($periode->nom, ENT_QUOTES) ?>
            · <?= htmlspecialchars($periode->annee_scolaire, ENT_QUOTES) ?>
        </p>
    </div>
</div>

<?php $flash = \Core\Session::getFlash(); ?>
<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> mb-4" role="alert">
    <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-triangle' ?>" class="w-4 h-4 shrink-0"></i>
    <span class="flex-1 text-sm"><?= htmlspecialchars($flash['message'], ENT_QUOTES) ?></span>
</div>
<?php endif; ?>

<?php if (empty($eleves)): ?>
<div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-sm text-amber-800 flex items-start gap-3">
    <i data-lucide="alert-triangle" class="w-5 h-5 shrink-0 mt-0.5"></i>
    <p>Aucun élève trouvé dans cette classe.</p>
</div>
<?php else: ?>

<form method="POST"
      action="<?= BASE_URL ?>/v2/academique/classes/<?= $classe->id ?>/matieres/<?= $matiere->id ?>/appreciations">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
    <input type="hidden" name="periode_id" value="<?= $periode->id ?>">

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-slate-600 w-1/3">Élève</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Appréciation</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($eleves as $row): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-4 py-2.5">
                        <div class="font-medium text-slate-900">
                            <?= htmlspecialchars($row->prenom . ' ' . $row->nom, ENT_QUOTES) ?>
                        </div>
                        <?php if ($row->matricule): ?>
                        <div class="text-xs text-slate-400"><?= htmlspecialchars($row->matricule, ENT_QUOTES) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-2.5">
                        <input type="text"
                               name="appreciations[<?= $row->eleve_id ?>][texte]"
                               value="<?= htmlspecialchars($row->texte ?? '', ENT_QUOTES) ?>"
                               maxlength="255"
                               placeholder="Ex : Assez-Bien, Insuffisant…"
                               class="w-full px-2 py-1 border border-slate-300 rounded-lg text-sm
                                      focus:outline-none focus:ring-2 focus:ring-violet-400">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="flex items-center justify-between mt-4">
        <a href="<?= BASE_URL ?>/v2/academique/appreciations" class="btn btn-secondary">Annuler</a>
        <button type="submit" class="btn btn-primary">
            <i data-lucide="save" class="w-4 h-4"></i>Enregistrer les appréciations
        </button>
    </div>
</form>

<?php endif; ?>
