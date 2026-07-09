<?php
$evaluation = $evaluation ?? null;
$statuts    = $statuts    ?? [];
$colors     = $colors     ?? [];
$icons      = $icons      ?? [];
$policy     = $policy     ?? null;
$user       = $user       ?? [];

if (!$evaluation) return;

$color       = $colors[$evaluation->statut] ?? 'slate';
$icon        = $icons[$evaluation->statut]  ?? 'circle';
$statutLabel = $statuts[$evaluation->statut] ?? $evaluation->statut;
$typeCouleur = $evaluation->type_couleur ?? '#94a3b8';
$flash       = \Core\Session::getFlash();
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
                     style="background:<?= htmlspecialchars($typeCouleur, ENT_QUOTES) ?>20;
                            border: 2px solid <?= htmlspecialchars($typeCouleur, ENT_QUOTES) ?>40">
                    <?php if ($evaluation->type_icone): ?>
                    <i data-lucide="<?= htmlspecialchars($evaluation->type_icone, ENT_QUOTES) ?>"
                       class="w-7 h-7"
                       style="color:<?= htmlspecialchars($typeCouleur, ENT_QUOTES) ?>"></i>
                    <?php else: ?>
                    <i data-lucide="clipboard-list" class="w-7 h-7 text-violet-500"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="flex items-center flex-wrap gap-2 mb-1">
                        <h2 class="text-xl font-bold text-slate-900">
                            <?= htmlspecialchars($evaluation->libelle, ENT_QUOTES) ?>
                        </h2>
                        <span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full
                              bg-<?= $color ?>-100 text-<?= $color ?>-700">
                            <i data-lucide="<?= $icon ?>" class="w-3 h-3"></i>
                            <?= $statutLabel ?>
                        </span>
                        <?php if ((int)$evaluation->notes_saisie_ouverte): ?>
                        <span class="inline-flex items-center gap-1 text-xs text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full">
                            <i data-lucide="pencil" class="w-3 h-3"></i>Saisie ouverte
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-sm text-slate-500">
                        <span><?= htmlspecialchars($evaluation->type_nom, ENT_QUOTES) ?></span>
                        <span class="text-slate-300">·</span>
                        <span><?= htmlspecialchars($evaluation->classe_nom, ENT_QUOTES) ?></span>
                        <span class="text-slate-300">·</span>
                        <span><?= htmlspecialchars($evaluation->matiere_nom, ENT_QUOTES) ?></span>
                        <span class="text-slate-300">·</span>
                        <span><?= htmlspecialchars($evaluation->periode_nom, ENT_QUOTES) ?></span>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <?php if ($policy && $policy->canUpdate($user, $evaluation)): ?>
                <a href="<?= BASE_URL ?>/v2/academique/evaluations/<?= $evaluation->id ?>/edit"
                   class="btn btn-secondary text-sm">
                    <i data-lucide="pencil" class="w-4 h-4"></i>Modifier
                </a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/v2/academique/evaluations" class="btn btn-secondary text-sm">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour
                </a>
            </div>
        </div>
    </div>

    <!-- Métriques -->
    <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-slate-100">
        <div class="p-4 text-center">
            <p class="text-xl font-bold font-mono text-slate-900">×<?= number_format((float)$evaluation->coefficient, 2) ?></p>
            <p class="text-xs text-slate-500 mt-0.5">Coefficient</p>
        </div>
        <div class="p-4 text-center">
            <p class="text-xl font-bold text-slate-900">/<?= number_format((float)$evaluation->note_max, 0) ?></p>
            <p class="text-xs text-slate-500 mt-0.5">Barème</p>
        </div>
        <div class="p-4 text-center">
            <p class="text-xl font-bold text-blue-600"><?= (int)($evaluation->nb_notes ?? 0) ?></p>
            <p class="text-xs text-slate-500 mt-0.5">Notes V2</p>
        </div>
        <div class="p-4 text-center">
            <?php if ($evaluation->date_evaluation): ?>
            <p class="text-sm font-bold text-slate-900"><?= date('d/m/Y', strtotime($evaluation->date_evaluation)) ?></p>
            <?php else: ?>
            <p class="text-slate-300 text-sm">—</p>
            <?php endif; ?>
            <p class="text-xs text-slate-500 mt-0.5">Date</p>
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
            <div class="flex justify-between">
                <dt class="text-slate-500">Période</dt>
                <dd class="font-medium text-slate-800">
                    <?= htmlspecialchars($evaluation->periode_nom, ENT_QUOTES) ?>
                    <span class="text-xs text-slate-400 ml-1">(<?= htmlspecialchars($evaluation->annee_scolaire, ENT_QUOTES) ?>)</span>
                </dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">Classe</dt>
                <dd class="font-medium text-slate-800">
                    <?= htmlspecialchars($evaluation->classe_nom, ENT_QUOTES) ?>
                    <span class="text-xs text-slate-400 ml-1"><?= htmlspecialchars($evaluation->classe_niveau ?? '', ENT_QUOTES) ?></span>
                </dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">Matière</dt>
                <dd class="font-medium text-slate-800"><?= htmlspecialchars($evaluation->matiere_nom, ENT_QUOTES) ?></dd>
            </div>
            <?php if ($evaluation->enseignant_nom): ?>
            <div class="flex justify-between">
                <dt class="text-slate-500">Enseignant</dt>
                <dd class="font-medium text-slate-800">
                    <?= htmlspecialchars($evaluation->enseignant_nom, ENT_QUOTES) ?>
                    <?php if ($evaluation->enseignant_specialite): ?>
                    <span class="text-xs text-slate-400 ml-1">(<?= htmlspecialchars($evaluation->enseignant_specialite, ENT_QUOTES) ?>)</span>
                    <?php endif; ?>
                </dd>
            </div>
            <?php endif; ?>
            <?php if ($evaluation->description): ?>
            <div>
                <dt class="text-slate-500 mb-1">Description</dt>
                <dd class="text-slate-800"><?= htmlspecialchars($evaluation->description, ENT_QUOTES) ?></dd>
            </div>
            <?php endif; ?>
            <?php if ($evaluation->statut === 'verrouillee' && $evaluation->verrouille_le): ?>
            <div class="flex justify-between">
                <dt class="text-slate-500">Verrouillée le</dt>
                <dd class="font-medium text-red-700"><?= date('d/m/Y H:i', strtotime($evaluation->verrouille_le)) ?></dd>
            </div>
            <?php endif; ?>
            <div class="flex justify-between">
                <dt class="text-slate-500">Créée le</dt>
                <dd class="text-slate-600"><?= $evaluation->created_at ? date('d/m/Y H:i', strtotime($evaluation->created_at)) : '—' ?></dd>
            </div>
        </dl>
    </div>

    <!-- Actions lifecycle -->
    <?php if ($evaluation->statut === 'archivee'): ?>
    <div class="bg-slate-50 rounded-xl border border-slate-200 p-5">
        <h3 class="font-semibold text-slate-500 mb-3 flex items-center gap-2">
            <i data-lucide="archive" class="w-4 h-4"></i>Évaluation archivée
        </h3>
        <p class="text-sm text-slate-400">Cette évaluation est archivée définitivement.</p>
    </div>
    <?php elseif ($evaluation->statut === 'verrouillee'): ?>
    <div class="bg-red-50 rounded-xl border border-red-200 p-5">
        <h3 class="font-semibold text-red-700 mb-3 flex items-center gap-2">
            <i data-lucide="lock" class="w-4 h-4"></i>Évaluation verrouillée
        </h3>
        <p class="text-xs text-red-600 mb-4">
            Toute modification est bloquée. La saisie des notes est fermée.
            Seul un administrateur peut déverrouiller.
        </p>
        <?php if ($policy && $policy->canDeverrouiller($user, $evaluation)): ?>
        <form method="POST"
              action="<?= BASE_URL ?>/v2/academique/evaluations/<?= $evaluation->id ?>/deverrouiller"
              onsubmit="return confirm('Déverrouiller cette évaluation ? Action auditée.')">
            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
            <button type="submit" class="w-full btn btn-secondary text-sm">
                <i data-lucide="unlock" class="w-4 h-4"></i>Déverrouiller (Admin)
            </button>
        </form>
        <?php endif; ?>
        <?php if ($policy && $policy->canArchiver($user, $evaluation)): ?>
        <form method="POST"
              class="mt-2"
              action="<?= BASE_URL ?>/v2/academique/evaluations/<?= $evaluation->id ?>/archiver"
              onsubmit="return confirm('Archiver définitivement cette évaluation ?')">
            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
            <button type="submit" class="w-full btn btn-secondary text-sm text-slate-400">
                <i data-lucide="archive" class="w-4 h-4"></i>Archiver
            </button>
        </form>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h3 class="font-semibold text-slate-700 mb-4 flex items-center gap-2">
            <i data-lucide="sliders-horizontal" class="w-4 h-4 text-violet-500"></i>
            Actions disponibles
        </h3>
        <div class="space-y-2">
            <?php if ($policy && $policy->canPublish($user, $evaluation)): ?>
            <form method="POST" action="<?= BASE_URL ?>/v2/academique/evaluations/<?= $evaluation->id ?>/publier">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit"
                        onclick="return confirm('Publier cette évaluation ? La saisie des notes sera ouverte.')"
                        class="w-full btn btn-primary text-sm justify-start gap-2">
                    <i data-lucide="send" class="w-4 h-4"></i>Publier — ouvrir la saisie
                </button>
            </form>
            <?php endif; ?>

            <?php if ($policy && $policy->canVerrouiller($user, $evaluation)): ?>
            <form method="POST" action="<?= BASE_URL ?>/v2/academique/evaluations/<?= $evaluation->id ?>/verrouiller">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit"
                        onclick="return confirm('Verrouiller cette évaluation ? La saisie sera fermée.')"
                        class="w-full btn btn-secondary text-sm justify-start gap-2">
                    <i data-lucide="lock" class="w-4 h-4 text-red-500"></i>Verrouiller
                </button>
            </form>
            <?php endif; ?>

            <?php if ($policy && $policy->canArchiver($user, $evaluation)): ?>
            <form method="POST" action="<?= BASE_URL ?>/v2/academique/evaluations/<?= $evaluation->id ?>/archiver">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit"
                        onclick="return confirm('Archiver définitivement cette évaluation ?')"
                        class="w-full btn btn-secondary text-sm justify-start gap-2 text-slate-400">
                    <i data-lucide="archive" class="w-4 h-4"></i>Archiver
                </button>
            </form>
            <?php endif; ?>

            <?php if ((int)($evaluation->nb_notes ?? 0) > 0): ?>
            <div class="mt-3 p-3 bg-blue-50 rounded-lg text-xs text-blue-700 flex items-start gap-2">
                <i data-lucide="info" class="w-3.5 h-3.5 mt-0.5 shrink-0"></i>
                <span>Des notes ont déjà été saisies. L'archivage sera refusé.</span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
