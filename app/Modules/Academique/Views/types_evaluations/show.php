<?php
$type   = $type   ?? null;
$policy = $policy ?? null;
$user   = $user   ?? [];

if (!$type) return;

$isArchive = (int)$type->est_archive;
$isActif   = (int)$type->actif;
$isSysteme = (int)$type->est_systeme;

if ($isArchive) {
    $badgeColor = 'slate'; $badgeLabel = 'Archivé';
} elseif ($isActif) {
    $badgeColor = 'emerald'; $badgeLabel = 'Actif';
} else {
    $badgeColor = 'amber'; $badgeLabel = 'Inactif';
}

$couleur = $type->couleur ?: '#94a3b8';
$flash   = \Core\Session::getFlash();
?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> mb-4" role="alert">
    <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-triangle' ?>" class="w-4 h-4 shrink-0"></i>
    <span class="flex-1 text-sm"><?= htmlspecialchars($flash['message'], ENT_QUOTES) ?></span>
    <button onclick="this.closest('[role=alert]').remove()" class="ml-auto opacity-60 hover:opacity-100">
        <i data-lucide="x" class="w-4 h-4"></i>
    </button>
</div>
<?php endif; ?>

<!-- En-tête fiche -->
<div class="bg-white rounded-xl border border-slate-200 shadow-sm mb-6">
    <div class="p-6 border-b border-slate-100">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center shrink-0"
                     style="background:<?= htmlspecialchars($couleur, ENT_QUOTES) ?>20;
                            border: 2px solid <?= htmlspecialchars($couleur, ENT_QUOTES) ?>40">
                    <?php if ($type->icone): ?>
                    <i data-lucide="<?= htmlspecialchars($type->icone, ENT_QUOTES) ?>"
                       class="w-7 h-7"
                       style="color:<?= htmlspecialchars($couleur, ENT_QUOTES) ?>"></i>
                    <?php else: ?>
                    <i data-lucide="layers" class="w-7 h-7 text-violet-500"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <h2 class="text-xl font-bold text-slate-900">
                            <?= htmlspecialchars($type->nom, ENT_QUOTES) ?>
                        </h2>
                        <span class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full
                              bg-<?= $badgeColor ?>-100 text-<?= $badgeColor ?>-700">
                            <?= $badgeLabel ?>
                        </span>
                        <?php if ($isSysteme): ?>
                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-violet-700 bg-violet-100 px-2 py-0.5 rounded-full">
                            <i data-lucide="shield" class="w-3 h-3"></i>Système V1
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-3 text-sm text-slate-500">
                        <code class="text-xs font-mono bg-slate-100 text-slate-700 px-2 py-0.5 rounded">
                            <?= htmlspecialchars($type->code, ENT_QUOTES) ?>
                        </code>
                        <span>Ordre : <?= (int)$type->ordre ?></span>
                        <?php if ((int)$type->est_eliminatoire): ?>
                        <span class="text-red-600 font-medium flex items-center gap-1">
                            <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>Éliminatoire
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if ($policy && $policy->canUpdate($user, $type)): ?>
                <a href="<?= BASE_URL ?>/v2/academique/types-evaluations/<?= $type->id ?>/edit"
                   class="btn btn-secondary text-sm">
                    <i data-lucide="pencil" class="w-4 h-4"></i>Modifier
                </a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/v2/academique/types-evaluations"
                   class="btn btn-secondary text-sm">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour
                </a>
            </div>
        </div>
    </div>

    <!-- Métriques -->
    <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-slate-100">
        <div class="p-4 text-center">
            <p class="text-xl font-bold font-mono text-slate-900">
                ×<?= number_format((float)$type->coefficient_defaut, 2) ?>
            </p>
            <p class="text-xs text-slate-500 mt-0.5">Coefficient défaut</p>
        </div>
        <div class="p-4 text-center">
            <p class="text-xl font-bold text-slate-900">
                /<?= number_format((float)$type->note_max_defaut, 0) ?>
            </p>
            <p class="text-xs text-slate-500 mt-0.5">Barème défaut</p>
        </div>
        <div class="p-4 text-center">
            <p class="text-xl font-bold text-blue-600"><?= (int)($type->nb_controles_v1 ?? 0) ?></p>
            <p class="text-xs text-slate-500 mt-0.5">Contrôles V1</p>
        </div>
        <div class="p-4 text-center">
            <p class="text-xl font-bold text-slate-900"><?= (int)$type->ordre ?></p>
            <p class="text-xs text-slate-500 mt-0.5">Ordre affichage</p>
        </div>
    </div>
</div>

<!-- Détails et actions -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">

    <!-- Informations -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h3 class="font-semibold text-slate-700 mb-4 flex items-center gap-2">
            <i data-lucide="info" class="w-4 h-4 text-violet-500"></i>
            Informations
        </h3>
        <dl class="space-y-3 text-sm">
            <?php if ($type->description): ?>
            <div>
                <dt class="text-slate-500 text-xs uppercase tracking-wide mb-1">Description</dt>
                <dd class="text-slate-800"><?= htmlspecialchars($type->description, ENT_QUOTES) ?></dd>
            </div>
            <?php endif; ?>
            <div class="flex justify-between items-center">
                <dt class="text-slate-500">Éliminatoire</dt>
                <dd class="font-medium <?= (int)$type->est_eliminatoire ? 'text-red-600' : 'text-slate-600' ?>">
                    <?php if ((int)$type->est_eliminatoire): ?>
                        Oui — seuil <?= number_format((float)$type->seuil_eliminatoire, 2) ?>/<?= number_format((float)$type->note_max_defaut, 0) ?>
                    <?php else: ?>
                        Non
                    <?php endif; ?>
                </dd>
            </div>
            <div class="flex justify-between items-center">
                <dt class="text-slate-500">Couleur UI</dt>
                <dd class="flex items-center gap-2">
                    <?php if ($type->couleur): ?>
                    <span class="w-4 h-4 rounded-full border border-slate-200"
                          style="background:<?= htmlspecialchars($type->couleur, ENT_QUOTES) ?>"></span>
                    <code class="font-mono text-xs"><?= htmlspecialchars($type->couleur, ENT_QUOTES) ?></code>
                    <?php else: ?>
                    <span class="text-slate-400">—</span>
                    <?php endif; ?>
                </dd>
            </div>
            <div class="flex justify-between items-center">
                <dt class="text-slate-500">Icône</dt>
                <dd class="flex items-center gap-2">
                    <?php if ($type->icone): ?>
                    <i data-lucide="<?= htmlspecialchars($type->icone, ENT_QUOTES) ?>" class="w-4 h-4 text-slate-600"></i>
                    <code class="font-mono text-xs"><?= htmlspecialchars($type->icone, ENT_QUOTES) ?></code>
                    <?php else: ?>
                    <span class="text-slate-400">—</span>
                    <?php endif; ?>
                </dd>
            </div>
            <div class="flex justify-between items-center">
                <dt class="text-slate-500">Type système</dt>
                <dd><?= $isSysteme ? '<span class="text-violet-700 font-medium">Oui (V1)</span>' : 'Non' ?></dd>
            </div>
            <div class="flex justify-between items-center">
                <dt class="text-slate-500">Créé le</dt>
                <dd class="text-slate-700">
                    <?= $type->created_at ? date('d/m/Y H:i', strtotime($type->created_at)) : '—' ?>
                </dd>
            </div>
        </dl>
    </div>

    <!-- Actions -->
    <?php if ($isArchive): ?>
    <div class="bg-slate-50 rounded-xl border border-slate-200 p-5">
        <h3 class="font-semibold text-slate-500 mb-3 flex items-center gap-2">
            <i data-lucide="archive" class="w-4 h-4"></i>
            Type archivé
        </h3>
        <p class="text-sm text-slate-500">
            Ce type a été archivé définitivement. Il ne peut plus être modifié ni réactivé.
        </p>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h3 class="font-semibold text-slate-700 mb-4 flex items-center gap-2">
            <i data-lucide="sliders-horizontal" class="w-4 h-4 text-violet-500"></i>
            Actions disponibles
        </h3>
        <div class="space-y-2">

            <?php if (!$isActif && $policy && $policy->canActivate($user, $type)): ?>
            <form method="POST"
                  action="<?= BASE_URL ?>/v2/academique/types-evaluations/<?= $type->id ?>/activer">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit"
                        onclick="return confirm('Activer ce type d\'évaluation ?')"
                        class="w-full btn btn-secondary text-sm justify-start gap-2">
                    <i data-lucide="toggle-right" class="w-4 h-4 text-emerald-500"></i>
                    Activer
                </button>
            </form>
            <?php endif; ?>

            <?php if ($isActif && $policy && $policy->canDeactivate($user, $type)): ?>
            <form method="POST"
                  action="<?= BASE_URL ?>/v2/academique/types-evaluations/<?= $type->id ?>/desactiver">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit"
                        onclick="return confirm('Désactiver « <?= htmlspecialchars($type->nom, ENT_JS) ?> » ? Il n\'apparaîtra plus dans les sélecteurs.')"
                        class="w-full btn btn-secondary text-sm justify-start gap-2">
                    <i data-lucide="toggle-left" class="w-4 h-4 text-amber-500"></i>
                    Désactiver
                </button>
            </form>
            <?php endif; ?>

            <?php if ($policy && $policy->canArchiver($user, $type)): ?>
            <form method="POST"
                  action="<?= BASE_URL ?>/v2/academique/types-evaluations/<?= $type->id ?>/archiver">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit"
                        onclick="return confirm('Archiver définitivement « <?= htmlspecialchars($type->nom, ENT_JS) ?> » ? Cette action est irréversible.')"
                        class="w-full btn btn-secondary text-sm justify-start gap-2 text-slate-400">
                    <i data-lucide="archive" class="w-4 h-4"></i>
                    Archiver définitivement
                </button>
            </form>
            <?php endif; ?>

            <?php if ((int)($type->nb_controles_v1 ?? 0) > 0): ?>
            <div class="mt-3 p-3 bg-blue-50 rounded-lg text-xs text-blue-700 flex items-start gap-2">
                <i data-lucide="info" class="w-3.5 h-3.5 mt-0.5 shrink-0"></i>
                <span>Ce type est utilisé par <strong><?= (int)$type->nb_controles_v1 ?></strong> contrôle(s) V1.
                L'archivage sera refusé tant que des évaluations l'utilisent.</span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
