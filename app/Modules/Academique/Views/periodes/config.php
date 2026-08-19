<?php
$template = $template ?? [];
$errors   = $errors   ?? [];

$mois = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
    7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
];

$flash = \Core\Session::getFlash();
?>

<div class="max-w-3xl mx-auto">

<div class="flex items-center gap-3 mb-6">
    <a href="<?= BASE_URL ?>/v2/academique/periodes"
       class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition">
        <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
        <h2 class="text-xl font-bold text-slate-900">Modèle par défaut des périodes</h2>
        <p class="text-sm text-slate-500">
            Préconfiguré au calendrier semestriel du Complexe Scolaire Privé La Persévérance — entièrement modifiable.
        </p>
    </div>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> mb-4" role="alert">
    <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-triangle' ?>" class="w-4 h-4 shrink-0"></i>
    <span class="flex-1 text-sm"><?= htmlspecialchars($flash['message'], ENT_QUOTES) ?></span>
</div>
<?php endif; ?>

<div class="bg-sky-50 border border-sky-200 rounded-xl p-4 mb-6 text-sm text-sky-700">
    <i data-lucide="info" class="w-4 h-4 inline-block align-text-top mr-1"></i>
    Ce modèle sert de base à la génération automatique des périodes d'une année scolaire
    (bouton « Générer les 2 semestres » sur la liste des périodes). Seuls le jour et le mois
    sont configurés ici — l'année est déduite automatiquement de l'année scolaire choisie.
</div>

<form method="POST" action="<?= BASE_URL ?>/v2/academique/periodes/config">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

    <div class="space-y-4">
        <?php foreach ($template as $t): ?>
        <?php $numErrors = $errors[$t->numero] ?? []; ?>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <h3 class="font-semibold text-slate-700 mb-4 flex items-center gap-2">
                <i data-lucide="calendar-range" class="w-4 h-4 text-violet-500"></i>
                <?= htmlspecialchars($t->nom_defaut, ENT_QUOTES) ?>
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nom par défaut</label>
                    <input type="text" name="config[<?= $t->numero ?>][nom_defaut]"
                           value="<?= htmlspecialchars($t->nom_defaut, ENT_QUOTES) ?>"
                           maxlength="80"
                           class="form-input w-full <?= !empty($numErrors['nom_defaut']) ? 'border-red-400' : '' ?>">
                    <?php if (!empty($numErrors['nom_defaut'])): ?>
                    <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($numErrors['nom_defaut'][0], ENT_QUOTES) ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Ordre d'affichage</label>
                    <input type="number" name="config[<?= $t->numero ?>][ordre]"
                           value="<?= (int)$t->ordre ?>" min="0" max="99"
                           class="form-input w-full">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Début par défaut</label>
                    <div class="flex gap-2">
                        <input type="number" name="config[<?= $t->numero ?>][jour_debut]"
                               value="<?= (int)$t->jour_debut ?>" min="1" max="31"
                               class="form-input w-20 <?= !empty($numErrors['date_debut']) ? 'border-red-400' : '' ?>">
                        <select name="config[<?= $t->numero ?>][mois_debut]" class="form-input flex-1">
                            <?php foreach ($mois as $val => $label): ?>
                            <option value="<?= $val ?>" <?= (int)$t->mois_debut === $val ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (!empty($numErrors['date_debut'])): ?>
                    <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($numErrors['date_debut'][0], ENT_QUOTES) ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Fin par défaut</label>
                    <div class="flex gap-2">
                        <input type="number" name="config[<?= $t->numero ?>][jour_fin]"
                               value="<?= (int)$t->jour_fin ?>" min="1" max="31"
                               class="form-input w-20 <?= !empty($numErrors['date_fin']) ? 'border-red-400' : '' ?>">
                        <select name="config[<?= $t->numero ?>][mois_fin]" class="form-input flex-1">
                            <?php foreach ($mois as $val => $label): ?>
                            <option value="<?= $val ?>" <?= (int)$t->mois_fin === $val ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (!empty($numErrors['date_fin'])): ?>
                    <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($numErrors['date_fin'][0], ENT_QUOTES) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="flex items-center gap-3 mt-6">
        <button type="submit" class="btn btn-primary">
            <i data-lucide="save" class="w-4 h-4"></i>Enregistrer le modèle
        </button>
        <a href="<?= BASE_URL ?>/v2/academique/periodes" class="btn btn-secondary">Annuler</a>
    </div>
</form>

</div>
