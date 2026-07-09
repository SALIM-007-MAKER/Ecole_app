<?php
$enfants   = $enfants   ?? [];
$enfant    = $enfant    ?? null;
$periodes  = $periodes  ?? [];
$periodeId = $periodeId ?? null;
$periode   = $periode   ?? null;
$bulletin  = $bulletin  ?? null;
$matieres  = $matieres  ?? [];
$classement = $classement ?? null;
?>

<!-- Header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center">
            <i data-lucide="file-badge" class="w-5 h-5 text-violet-600"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-slate-900">Bulletin scolaire</h2>
            <?php if ($enfant && $periode): ?>
            <p class="text-xs text-slate-400"><?= htmlspecialchars($enfant->prenom . ' ' . $enfant->nom, ENT_QUOTES) ?> — <?= htmlspecialchars($periode->nom, ENT_QUOTES) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= BASE_URL ?>/parent/dashboard" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour
        </a>
        <?php if ($bulletin && $enfant && $periodeId): ?>
        <a href="<?= BASE_URL ?>/parent/bulletin/pdf?enfant_id=<?= $enfant->id ?>&periode_id=<?= $periodeId ?>"
           class="btn btn-primary" target="_blank">
            <i data-lucide="download" class="w-4 h-4"></i>Télécharger PDF
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Selectors -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-5">
    <div class="p-5 p-4">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2 text-sm text-slate-500 font-medium">
                <i data-lucide="filter" class="w-4 h-4"></i>Filtres :
            </div>
            <select name="enfant_id" class="form-input text-sm" onchange="this.form.submit()">
                <option value="">Choisir un enfant</option>
                <?php foreach ($enfants as $e): ?>
                <option value="<?= $e->id ?>" <?= $enfant && $enfant->id == $e->id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($e->prenom . ' ' . $e->nom, ENT_QUOTES) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php if ($enfant): ?>
            <select name="periode_id" class="form-input text-sm" onchange="this.form.submit()">
                <option value="">Choisir une période</option>
                <?php foreach ($periodes as $p): ?>
                <option value="<?= $p->id ?>" <?= $periodeId == $p->id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p->nom, ENT_QUOTES) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (!$enfant || !$periodeId): ?>
<div class="flex flex-col items-center justify-center gap-3 text-center text-slate-500 py-16">
    <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-4">
        <i data-lucide="file-badge" class="w-8 h-8 text-slate-300"></i>
    </div>
    <p class="text-slate-500 font-medium">Sélectionnez un enfant et une période</p>
    <p class="text-sm text-slate-400 mt-1">Le bulletin s'affichera ici une fois les filtres définis.</p>
</div>
<?php elseif (empty($bulletin)): ?>
<div class="flex flex-col items-center justify-center gap-3 text-center text-slate-500 py-16">
    <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-4">
        <i data-lucide="inbox" class="w-8 h-8 text-slate-300"></i>
    </div>
    <p class="text-slate-500 font-medium">Aucun bulletin disponible</p>
    <p class="text-sm text-slate-400 mt-1">Le bulletin n'a pas encore été généré pour cette période.</p>
</div>
<?php else: ?>

<!-- Bulletin summary card -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-5">
    <div class="p-5 p-6">
        <div class="flex flex-wrap items-center gap-6">
            <!-- Élève info -->
            <div class="flex items-center gap-4 flex-1">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center flex-shrink-0 shadow-sm">
                    <i data-lucide="user" class="w-7 h-7 text-white"></i>
                </div>
                <div>
                    <p class="text-lg font-bold text-slate-900"><?= htmlspecialchars($enfant->prenom . ' ' . $enfant->nom, ENT_QUOTES) ?></p>
                    <p class="text-sm text-slate-500 mt-0.5">
                        <?= htmlspecialchars($enfant->classe_nom ?? '-', ENT_QUOTES) ?>
                        <?php if ($periode): ?> — <span class="text-violet-600 font-medium"><?= htmlspecialchars($periode->nom, ENT_QUOTES) ?></span><?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- Moyenne -->
            <div class="text-center px-6 border-l border-slate-100">
                <p class="text-xs text-slate-400 font-semibold uppercase tracking-wide mb-1">Moyenne générale</p>
                <?php $moy = (float)($bulletin->moyenne_generale ?? 0); ?>
                <p class="text-3xl font-black <?= $moy >= 10 ? 'text-emerald-600' : 'text-red-500' ?>">
                    <?= number_format($moy, 2, ',', '') ?>
                </p>
                <p class="text-xs text-slate-400 mt-0.5">/ 20</p>
            </div>

            <!-- Classement -->
            <?php if ($classement): ?>
            <div class="text-center px-6 border-l border-slate-100">
                <p class="text-xs text-slate-400 font-semibold uppercase tracking-wide mb-1">Classement</p>
                <p class="text-3xl font-black text-violet-600">
                    <?= (int)$classement->rang ?><sup class="text-sm font-bold">e</sup>
                </p>
                <p class="text-xs text-slate-400 mt-0.5">/ <?= (int)($classement->effectif ?? 0) ?> élèves</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Matières table -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-5">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <div class="w-7 h-7 rounded-lg bg-violet-100 flex items-center justify-center">
            <i data-lucide="book-open" class="w-3.5 h-3.5 text-violet-600"></i>
        </div>
        <span class="font-semibold text-slate-700">Détail par matière</span>
    </div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50 text-sm">
            <thead>
                <tr>
                    <th>Matière</th>
                    <th class="text-center">Coeff.</th>
                    <th class="text-center">Moyenne</th>
                    <th class="text-center">Min</th>
                    <th class="text-center">Max</th>
                    <th>Mention</th>
                    <th>Appréciation</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($matieres as $m):
                $moyM = (float)($m->moyenne ?? 0);
            ?>
            <tr>
                <td class="font-semibold text-slate-800"><?= htmlspecialchars($m->matiere_nom, ENT_QUOTES) ?></td>
                <td class="text-center text-slate-500"><?= (float)($m->coefficient ?? 1) ?></td>
                <td class="text-center font-bold <?= $moyM >= 10 ? 'text-emerald-600' : 'text-red-500' ?>">
                    <?= number_format($moyM, 2, ',', '') ?>
                </td>
                <td class="text-center text-slate-400"><?= number_format((float)($m->min ?? 0), 2, ',', '') ?></td>
                <td class="text-center text-slate-400"><?= number_format((float)($m->max ?? 0), 2, ',', '') ?></td>
                <td>
                    <?php
                    $men = $m->mention ?? '';
                    $cls = match(true) {
                        str_contains($men,'Excellent') => 'bg-emerald-100 text-emerald-700',
                        str_contains($men,'Très')     => 'bg-emerald-100 text-emerald-700',
                        str_contains($men,'Bien')     => 'bg-sky-100 text-sky-700',
                        str_contains($men,'Passable') => 'bg-amber-100 text-amber-800',
                        default                       => 'bg-red-100 text-red-700',
                    };
                    ?>
                    <?php if ($men): ?>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= $cls ?> text-xs"><?= htmlspecialchars($men, ENT_QUOTES) ?></span>
                    <?php endif; ?>
                </td>
                <td class="text-xs text-slate-400 italic"><?= htmlspecialchars($m->appreciation ?? '', ENT_QUOTES) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($bulletin->appreciation_generale)): ?>
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <div class="w-7 h-7 rounded-lg bg-violet-100 flex items-center justify-center">
            <i data-lucide="message-square" class="w-3.5 h-3.5 text-violet-600"></i>
        </div>
        <span class="font-semibold text-slate-700">Appréciation du conseil de classe</span>
    </div>
    <div class="p-5 p-5">
        <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
            <p class="text-sm text-slate-700 italic leading-relaxed"><?= nl2br(htmlspecialchars($bulletin->appreciation_generale, ENT_QUOTES)) ?></p>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>
