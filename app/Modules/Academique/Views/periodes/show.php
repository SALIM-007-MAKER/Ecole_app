<?php
$periode = $periode ?? null;
$types   = $types   ?? [];
$statuts = $statuts ?? [];
$colors  = $colors  ?? [];
$perms   = $perms   ?? [];
$policy  = $policy  ?? null;
$user    = $user    ?? [];

if (!$periode) return;

$color       = $colors[$periode->statut]  ?? 'slate';
$statutLabel = $statuts[$periode->statut] ?? $periode->statut;
$typeLabel   = $types[$periode->type_periode] ?? $periode->type_periode;

$flash = \Core\Session::getFlash();
?>

<!-- Flash -->
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
                <div class="w-14 h-14 rounded-2xl bg-violet-100 flex items-center justify-center shrink-0">
                    <i data-lucide="calendar-range" class="w-7 h-7 text-violet-600"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <h2 class="text-xl font-bold text-slate-900">
                            <?= htmlspecialchars($periode->nom, ENT_QUOTES) ?>
                        </h2>
                        <?php if ((int)$periode->is_active): ?>
                        <span class="inline-flex items-center gap-1 text-xs font-bold text-violet-700 bg-violet-100 px-2 py-0.5 rounded-full">
                            <i data-lucide="star" class="w-3 h-3"></i>ACTIVE
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-sm text-slate-500">
                        <span class="flex items-center gap-1">
                            <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                            <?= htmlspecialchars($periode->annee_scolaire, ENT_QUOTES) ?>
                        </span>
                        <span class="text-slate-300">·</span>
                        <span><?= htmlspecialchars($typeLabel, ENT_QUOTES) ?> <?= (int)$periode->numero ?></span>
                        <span class="text-slate-300">·</span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold
                              bg-<?= $color ?>-100 text-<?= $color ?>-700">
                            <?= htmlspecialchars($statutLabel, ENT_QUOTES) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Boutons d'action primaires -->
            <div class="flex flex-wrap gap-2">
                <?php if ($policy && $policy->canUpdate($user, $periode)): ?>
                <a href="<?= BASE_URL ?>/v2/academique/periodes/<?= $periode->id ?>/edit"
                   class="btn btn-secondary text-sm">
                    <i data-lucide="pencil" class="w-4 h-4"></i>Modifier
                </a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/v2/academique/periodes" class="btn btn-secondary text-sm">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour
                </a>
            </div>
        </div>
    </div>

    <!-- Métriques -->
    <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-slate-100">
        <div class="p-4 text-center">
            <p class="text-xl font-bold text-slate-900"><?= (int)($periode->nb_controles_v1 ?? 0) ?></p>
            <p class="text-xs text-slate-500 mt-0.5">Contrôles (V1)</p>
        </div>
        <div class="p-4 text-center">
            <p class="text-xl font-bold text-slate-900"><?= (int)($periode->nb_notes_v1 ?? 0) ?></p>
            <p class="text-xs text-slate-500 mt-0.5">Notes (V1)</p>
        </div>
        <div class="p-4 text-center">
            <?php
            $saisieOuverte = (int)$periode->notes_saisie_ouverte;
            $saisieColor   = $saisieOuverte ? 'emerald' : 'slate';
            $saisieLabel   = $saisieOuverte ? 'Ouverte' : 'Fermée';
            ?>
            <span class="text-xs font-medium px-2.5 py-1 rounded-full bg-<?= $saisieColor ?>-100 text-<?= $saisieColor ?>-700">
                <?= $saisieLabel ?>
            </span>
            <p class="text-xs text-slate-500 mt-1">Saisie notes</p>
        </div>
        <div class="p-4 text-center">
            <p class="text-xs text-slate-500">Ordre d'affichage</p>
            <p class="text-xl font-bold text-slate-900"><?= (int)$periode->ordre ?></p>
        </div>
    </div>
</div>

<!-- Dates et infos -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h3 class="font-semibold text-slate-700 mb-3 flex items-center gap-2">
            <i data-lucide="calendar-days" class="w-4 h-4 text-violet-500"></i>
            Dates de la période
        </h3>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between">
                <dt class="text-slate-500">Début</dt>
                <dd class="font-medium text-slate-800">
                    <?= $periode->date_debut ? date('d/m/Y', strtotime($periode->date_debut)) : '—' ?>
                </dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">Fin</dt>
                <dd class="font-medium text-slate-800">
                    <?= $periode->date_fin ? date('d/m/Y', strtotime($periode->date_fin)) : '—' ?>
                </dd>
            </div>
            <?php if ($periode->date_debut && $periode->date_fin): ?>
            <div class="flex justify-between">
                <dt class="text-slate-500">Durée</dt>
                <dd class="font-medium text-slate-800">
                    <?php
                    $days = (int)round((strtotime($periode->date_fin) - strtotime($periode->date_debut)) / 86400);
                    echo $days . ' jour(s)';
                    ?>
                </dd>
            </div>
            <?php endif; ?>
            <div class="flex justify-between">
                <dt class="text-slate-500">Créée le</dt>
                <dd class="font-medium text-slate-800">
                    <?= $periode->created_at ? date('d/m/Y H:i', strtotime($periode->created_at)) : '—' ?>
                </dd>
            </div>
        </dl>
    </div>

    <!-- Verrouillage -->
    <?php if ($periode->verrouille_par !== null): ?>
    <div class="bg-red-50 rounded-xl border border-red-200 p-5">
        <h3 class="font-semibold text-red-700 mb-3 flex items-center gap-2">
            <i data-lucide="lock" class="w-4 h-4"></i>
            Période verrouillée
        </h3>
        <dl class="space-y-2 text-sm">
            <?php if ($periode->verrouille_le): ?>
            <div class="flex justify-between">
                <dt class="text-red-500">Verrouillée le</dt>
                <dd class="font-medium text-red-800">
                    <?= date('d/m/Y H:i', strtotime($periode->verrouille_le)) ?>
                </dd>
            </div>
            <?php endif; ?>
        </dl>
        <p class="text-xs text-red-600 mt-3">
            Toute modification est bloquée. Seul un administrateur peut déverrouiller.
        </p>
        <?php if ($policy && $policy->canDeverrouiller($user)): ?>
        <form method="POST"
              action="<?= BASE_URL ?>/v2/academique/periodes/<?= $periode->id ?>/deverrouiller"
              onsubmit="return confirm('Déverrouiller cette période ? Cette action sera auditée.')">
            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
            <button type="submit" class="mt-3 btn btn-secondary text-sm w-full">
                <i data-lucide="unlock" class="w-4 h-4"></i>Déverrouiller (Admin)
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
            <?php if ($periode->statut === 'preparation' && $policy && $policy->canOuvrir($user)): ?>
            <form method="POST" action="<?= BASE_URL ?>/v2/academique/periodes/<?= $periode->id ?>/ouvrir">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit"
                        onclick="return confirm('Ouvrir la période ? La saisie de notes sera autorisée.')"
                        class="w-full btn btn-secondary text-sm justify-start gap-2">
                    <i data-lucide="unlock" class="w-4 h-4 text-emerald-500"></i>
                    Ouvrir la période
                </button>
            </form>
            <?php endif; ?>

            <?php if (!((int)$periode->is_active) && $periode->statut === 'ouverte' && $policy && $policy->canActivate($user)): ?>
            <form method="POST" action="<?= BASE_URL ?>/v2/academique/periodes/<?= $periode->id ?>/activer">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit"
                        onclick="return confirm('Activer « <?= htmlspecialchars($periode->nom, ENT_JS) ?> » ? Les autres périodes de <?= htmlspecialchars($periode->annee_scolaire, ENT_JS) ?> seront désactivées.')"
                        class="w-full btn btn-secondary text-sm justify-start gap-2">
                    <i data-lucide="star" class="w-4 h-4 text-violet-500"></i>
                    Rendre active
                </button>
            </form>
            <?php endif; ?>

            <?php if ($periode->statut === 'ouverte' && $policy && $policy->canCloturer($user)): ?>
            <form method="POST" action="<?= BASE_URL ?>/v2/academique/periodes/<?= $periode->id ?>/cloturer">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit"
                        onclick="return confirm('Clôturer la période ? La saisie de notes sera bloquée.')"
                        class="w-full btn btn-secondary text-sm justify-start gap-2">
                    <i data-lucide="clock" class="w-4 h-4 text-amber-500"></i>
                    Clôturer la période
                </button>
            </form>
            <?php endif; ?>

            <?php if ($periode->statut === 'cloturee' && $policy && $policy->canReouvrir($user)): ?>
            <form method="POST" action="<?= BASE_URL ?>/v2/academique/periodes/<?= $periode->id ?>/reouvrir">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit"
                        onclick="return confirm('Réouvrir la période ? (action administrateur)')"
                        class="w-full btn btn-secondary text-sm justify-start gap-2">
                    <i data-lucide="rotate-ccw" class="w-4 h-4 text-emerald-500"></i>
                    Réouvrir (Admin)
                </button>
            </form>
            <?php endif; ?>

            <?php if ($periode->statut === 'cloturee' && $policy && $policy->canVerrouiller($user)): ?>
            <form method="POST" action="<?= BASE_URL ?>/v2/academique/periodes/<?= $periode->id ?>/verrouiller">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit"
                        onclick="return confirm('Verrouiller la période ? Aucune modification ne sera possible ensuite.')"
                        class="w-full btn btn-secondary text-sm justify-start gap-2">
                    <i data-lucide="lock" class="w-4 h-4 text-red-500"></i>
                    Verrouiller
                </button>
            </form>
            <?php endif; ?>

            <?php if ($periode->statut === 'cloturee' && $policy && $policy->canArchiver($user, $periode)): ?>
            <form method="POST" action="<?= BASE_URL ?>/v2/academique/periodes/<?= $periode->id ?>/archiver">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit"
                        onclick="return confirm('Archiver cette période ?')"
                        class="w-full btn btn-secondary text-sm justify-start gap-2 text-slate-400">
                    <i data-lucide="archive" class="w-4 h-4"></i>
                    Archiver
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
