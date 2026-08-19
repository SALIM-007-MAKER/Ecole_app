<?php
$eleveId               = $eleveId               ?? 0;
$periodeId             = $periodeId             ?? 0;
$eleveNomComplet       = $eleveNomComplet        ?? '';
$classeNom             = $classeNom              ?? '';
$periodeNom            = $periodeNom             ?? '';
$appreciationDirecteur = $appreciationDirecteur  ?? '';
?>

<div class="flex items-center gap-3 mb-6">
    <a href="<?= BASE_URL ?>/v2/academique/bulletins/<?= $eleveId ?>/<?= $periodeId ?>/imprimer"
       class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition">
        <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
        <h2 class="text-xl font-bold text-slate-900">Appréciation du chef d'établissement</h2>
        <p class="text-sm text-slate-500 mt-0.5">
            <?= htmlspecialchars($eleveNomComplet, ENT_QUOTES) ?>
            · <?= htmlspecialchars($classeNom, ENT_QUOTES) ?>
            · <?= htmlspecialchars($periodeNom, ENT_QUOTES) ?>
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

<form method="POST"
      action="<?= BASE_URL ?>/v2/academique/bulletins/<?= $eleveId ?>/<?= $periodeId ?>/appreciation-directeur">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <label class="block text-sm font-medium text-slate-700 mb-2" for="appreciation_directeur">
            Appréciation générale imprimée sur le bulletin
        </label>
        <textarea id="appreciation_directeur" name="appreciation_directeur" rows="5" maxlength="1000"
                  placeholder="Ex : Ensemble satisfaisant, poursuivez vos efforts au second semestre."
                  class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm
                         focus:outline-none focus:ring-2 focus:ring-violet-400"
        ><?= htmlspecialchars($appreciationDirecteur, ENT_QUOTES) ?></textarea>
    </div>

    <div class="flex items-center justify-between mt-4">
        <a href="<?= BASE_URL ?>/v2/academique/bulletins/<?= $eleveId ?>/<?= $periodeId ?>/imprimer" class="btn btn-secondary">
            Annuler
        </a>
        <button type="submit" class="btn btn-primary">
            <i data-lucide="save" class="w-4 h-4"></i>Enregistrer
        </button>
    </div>
</form>
