<?php
$eleve           = $eleve           ?? null;
$dernierePeriode = $dernierePeriode ?? null;
$bulletin        = $bulletin        ?? null;
$absences        = $absences        ?? [];
$prochainsCours  = $prochainsCours  ?? [];
$annonces        = $annonces        ?? [];
$notifications   = $notifications   ?? [];

$user = \Core\Session::getUser();
?>

<!-- Page header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900">
            Bonjour, <?= htmlspecialchars($eleve->prenom ?? $user['prenom'] ?? 'Élève', ENT_QUOTES) ?>
            <span class="wave inline-block">👋</span>
        </h2>
        <p class="text-sm text-slate-400 mt-0.5">
            <span class="inline-flex items-center gap-1">
                <i data-lucide="layers" class="w-3.5 h-3.5"></i>
                <?= htmlspecialchars($eleve->classe_nom ?? '', ENT_QUOTES) ?>
                <?php if ($dernierePeriode): ?> — <span class="text-violet-600 font-medium"><?= htmlspecialchars($dernierePeriode->nom, ENT_QUOTES) ?></span><?php endif; ?>
            </span>
        </p>
    </div>
</div>

<!-- KPI row -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
    <?php
    $moy     = (float)($bulletin->moyenne_generale ?? 0);
    $nbAbs   = count(array_filter($absences, fn($a) => ($a->type ?? '') === 'absence'));
    $nbRetards = count(array_filter($absences, fn($a) => ($a->type ?? '') === 'retard'));
    $rang    = $bulletin->rang ?? null;
    $effectif = $bulletin->effectif ?? null;
    $kpis = [
        ['val'=>number_format($moy,2,',',''), 'label'=>'Moyenne générale', 'icon'=>'trending-up',
         'bg'=>$moy>=10?'bg-emerald-100':'bg-red-100', 'ic'=>$moy>=10?'text-emerald-600':'text-red-500'],
        ['val'=>$nbAbs,    'label'=>'Absences',  'icon'=>'user-x',  'bg'=>'bg-red-100',    'ic'=>'text-red-500'],
        ['val'=>$nbRetards,'label'=>'Retards',   'icon'=>'clock',   'bg'=>'bg-amber-100',  'ic'=>'text-amber-600'],
        ['val'=>$rang ? $rang.($effectif?'/'.$effectif:'') : '—', 'label'=>'Classement',
         'icon'=>'award', 'bg'=>'bg-violet-100', 'ic'=>'text-violet-600'],
    ];
    foreach ($kpis as $k): ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm hover:shadow-sm transition-shadow">
        <div class="p-5 p-4 text-center">
            <div class="w-10 h-10 rounded-xl <?= $k['bg'] ?> flex items-center justify-center mx-auto mb-3">
                <i data-lucide="<?= $k['icon'] ?>" class="w-5 h-5 <?= $k['ic'] ?>"></i>
            </div>
            <p class="text-2xl font-black text-slate-900"><?= $k['val'] ?></p>
            <p class="text-xs text-slate-400 mt-0.5"><?= $k['label'] ?></p>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">

    <!-- Prochains cours -->
    <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <div class="w-7 h-7 rounded-lg bg-violet-100 flex items-center justify-center">
                <i data-lucide="calendar" class="w-3.5 h-3.5 text-violet-600"></i>
            </div>
            <span class="font-semibold text-slate-700">Prochains cours</span>
            <a href="<?= BASE_URL ?>/eleve/emploi-du-temps" class="ml-auto text-xs text-violet-600 hover:text-violet-700 font-medium transition-colors">
                Voir EDT →
            </a>
        </div>
        <div class="divide-y divide-slate-100">
            <?php if (empty($prochainsCours)): ?>
            <div class="p-8 text-center">
                <i data-lucide="calendar-off" class="w-8 h-8 text-slate-200 mx-auto mb-2"></i>
                <p class="text-sm text-slate-400">Aucun cours à venir aujourd'hui</p>
            </div>
            <?php else: ?>
            <?php foreach ($prochainsCours as $c): ?>
            <div class="flex items-center gap-4 p-4 hover:bg-slate-50 transition-colors">
                <div class="w-1 h-12 rounded-full flex-shrink-0" style="background:<?= htmlspecialchars($c->couleur ?? '#7c3aed', ENT_QUOTES) ?>"></div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-sm text-slate-800"><?= htmlspecialchars($c->matiere_nom, ENT_QUOTES) ?></p>
                    <p class="text-xs text-slate-400 mt-0.5">
                        <?= htmlspecialchars($c->enseignant_nom ?? '-', ENT_QUOTES) ?>
                        <?php if (!empty($c->salle_nom)): ?>
                        <span class="mx-1">·</span>
                        <span class="inline-flex items-center gap-0.5"><i data-lucide="map-pin" class="w-3 h-3"></i><?= htmlspecialchars($c->salle_nom, ENT_QUOTES) ?></span>
                        <?php endif; ?>
                    </p>
                </div>
                <span class="text-xs font-semibold text-slate-500 whitespace-nowrap bg-slate-100 px-2 py-1 rounded-lg">
                    <?= htmlspecialchars($c->heure_debut ?? '', ENT_QUOTES) ?> – <?= htmlspecialchars($c->heure_fin ?? '', ENT_QUOTES) ?>
                </span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Accès rapides -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <div class="w-7 h-7 rounded-lg bg-slate-100 flex items-center justify-center">
                <i data-lucide="grid-3x3" class="w-3.5 h-3.5 text-slate-500"></i>
            </div>
            <span class="font-semibold text-slate-700">Accès rapides</span>
        </div>
        <div class="p-5 p-4 space-y-2">
            <?php
            $links = [
                ['href'=>'/eleve/notes',          'icon'=>'file-text',   'label'=>'Mes notes',         'color'=>'text-violet-500', 'bg'=>'bg-violet-50'],
                ['href'=>'/eleve/bulletin',        'icon'=>'file-badge',  'label'=>'Mon bulletin',      'color'=>'text-emerald-500','bg'=>'bg-emerald-50'],
                ['href'=>'/eleve/emploi-du-temps', 'icon'=>'calendar',    'label'=>'Emploi du temps',   'color'=>'text-sky-500',    'bg'=>'bg-sky-50'],
                ['href'=>'/eleve/profil',          'icon'=>'user',        'label'=>'Mon profil',        'color'=>'text-amber-500',  'bg'=>'bg-amber-50'],
                ['href'=>'/annonces',              'icon'=>'megaphone',   'label'=>'Annonces',          'color'=>'text-indigo-500', 'bg'=>'bg-indigo-50'],
            ];
            foreach ($links as $l): ?>
            <a href="<?= BASE_URL . $l['href'] ?>"
               class="flex items-center gap-3 px-3 py-2.5 rounded-xl <?= $l['bg'] ?> hover:opacity-90 transition-opacity group">
                <div class="w-8 h-8 rounded-lg bg-white flex items-center justify-center flex-shrink-0 shadow-sm">
                    <i data-lucide="<?= $l['icon'] ?>" class="w-4 h-4 <?= $l['color'] ?>"></i>
                </div>
                <span class="text-sm font-semibold text-slate-700"><?= $l['label'] ?></span>
                <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300 ml-auto group-hover:text-slate-400 transition-colors"></i>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Notifications récentes -->
<?php if (!empty($notifications)): ?>
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <div class="w-7 h-7 rounded-lg bg-amber-100 flex items-center justify-center">
            <i data-lucide="bell" class="w-3.5 h-3.5 text-amber-500"></i>
        </div>
        <span class="font-semibold text-slate-700">Notifications récentes</span>
        <a href="<?= BASE_URL ?>/notifications" class="ml-auto text-xs text-violet-600 hover:text-violet-700 font-medium transition-colors">
            Voir tout →
        </a>
    </div>
    <div class="divide-y divide-slate-100">
        <?php foreach (array_slice($notifications, 0, 4) as $n): ?>
        <div class="flex items-start gap-3 p-4 <?= !$n->lu ? 'bg-violet-50/60' : '' ?> hover:bg-slate-50 transition-colors">
            <div class="w-8 h-8 rounded-full <?= !$n->lu ? 'bg-violet-100' : 'bg-slate-100' ?> flex items-center justify-center flex-shrink-0 mt-0.5">
                <i data-lucide="bell" class="w-3.5 h-3.5 <?= !$n->lu ? 'text-violet-600' : 'text-slate-400' ?>"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold text-slate-800 truncate"><?= htmlspecialchars($n->titre ?? $n->message ?? '', ENT_QUOTES) ?></p>
                <p class="text-xs text-slate-400 mt-0.5"><?= date('d/m/Y H:i', strtotime($n->created_at)) ?></p>
            </div>
            <?php if (!$n->lu): ?><span class="w-2 h-2 rounded-full bg-violet-500 flex-shrink-0 mt-1.5"></span><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
