<?php
/** @var ?\App\Modules\Academique\DTO\RankingResultDTO $classement */
$anneesScolaires = $anneesScolaires ?? [];
$periodes        = $periodes        ?? [];
$classes         = $classes         ?? [];
$anneeScolaire   = $anneeScolaire   ?? '';
$periodeId       = $periodeId       ?? 0;
$classeId        = $classeId        ?? 0;
$periode         = $periode         ?? null;
$classe          = $classe          ?? null;
$classement      = $classement      ?? null;

function rMentionBadge(?string $css): string {
    return match($css) {
        'emerald' => 'bg-emerald-100 text-emerald-700',
        'blue'    => 'bg-violet-100 text-violet-700',
        'cyan'    => 'bg-sky-100 text-sky-700',
        'amber'   => 'bg-amber-100 text-amber-800',
        default   => 'bg-red-100 text-red-700',
    };
}
?>

<!-- En-tête -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="users" class="w-5 h-5 text-violet-500"></i>
            Résultats de classe — Académique V2
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">Calculés par RankingEngine — mêmes données que les bulletins.</p>
    </div>
    <a href="<?= BASE_URL ?>/v2/academique/resultats/classement<?= $classeId && $periodeId ? "?classe_id=$classeId&periode_id=$periodeId" : '' ?>" class="btn btn-secondary">
        <i data-lucide="trophy" class="w-4 h-4"></i>Voir le classement
    </a>
</div>

<!-- Filtres -->
<form method="GET" action="<?= BASE_URL ?>/v2/academique/resultats/classe" class="flex flex-wrap gap-3 mb-6">
    <select name="annee_scolaire" class="form-input w-40" onchange="this.form.submit()">
        <option value="">Toutes années</option>
        <?php foreach ($anneesScolaires as $a): ?>
        <option value="<?= htmlspecialchars($a, ENT_QUOTES) ?>" <?= $anneeScolaire === $a ? 'selected' : '' ?>>
            <?= htmlspecialchars($a, ENT_QUOTES) ?>
        </option>
        <?php endforeach; ?>
    </select>

    <select name="periode_id" class="form-input w-48">
        <option value="">Choisir une période</option>
        <?php foreach ($periodes as $p): ?>
        <option value="<?= $p->id ?>" <?= $periodeId === (int)$p->id ? 'selected' : '' ?>>
            <?= htmlspecialchars($p->nom . ' — ' . $p->annee_scolaire, ENT_QUOTES) ?>
        </option>
        <?php endforeach; ?>
    </select>

    <select name="classe_id" class="form-input w-48">
        <option value="">Choisir une classe</option>
        <?php foreach ($classes as $c): ?>
        <option value="<?= $c->id ?>" <?= $classeId === (int)$c->id ? 'selected' : '' ?>>
            <?= htmlspecialchars($c->niveau . ' ' . $c->nom, ENT_QUOTES) ?>
        </option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-primary">
        <i data-lucide="search" class="w-4 h-4"></i>Afficher
    </button>
</form>

<?php if (!$classeId || !$periodeId): ?>
<div class="flex flex-col items-center justify-center gap-3 text-center text-slate-500 py-16">
    <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-4">
        <i data-lucide="users" class="w-8 h-8 text-slate-300"></i>
    </div>
    <p class="text-slate-500 font-medium">Sélectionnez une période et une classe</p>
</div>
<?php elseif (!$classement || $classement->isEmpty()): ?>
<div class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm border-sky-200 bg-sky-50 text-sky-800">
    <i data-lucide="info" class="w-4 h-4 shrink-0"></i>
    <span>Aucun résultat disponible — aucune évaluation publiée pour <?= htmlspecialchars($classe->niveau . ' ' . $classe->nom, ENT_QUOTES) ?> sur cette période.</span>
</div>
<?php else: ?>

<!-- Statistiques classe -->
<?php $s = $classement->statistiques; ?>
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-violet-600"><?= $classement->nbTotal ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Élèves classés</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-emerald-600"><?= $s['moyenne'] !== null ? number_format($s['moyenne'], 2) : '—' ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Moyenne de classe</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-sky-600"><?= $s['taux_reussite'] !== null ? number_format($s['taux_reussite'], 1) . '%' : '—' ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Taux de réussite</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-slate-700"><?= $s['min'] !== null ? number_format($s['min'], 2) : '—' ?> – <?= $s['max'] !== null ? number_format($s['max'], 2) : '—' ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Min – Max</p>
    </div>
</div>

<!-- Liste des élèves -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <i data-lucide="list" class="w-4 h-4 text-violet-600"></i>
        <span><?= htmlspecialchars($classe->niveau . ' ' . $classe->nom, ENT_QUOTES) ?> — <?= htmlspecialchars($periode->nom, ENT_QUOTES) ?></span>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_tbody]:divide-y [&_tbody]:divide-slate-100">
            <thead><tr>
                <th class="text-center w-16">Rang</th>
                <th>Élève</th>
                <th class="text-center">Moyenne</th>
                <th class="text-center">Mention</th>
                <th class="text-center">Admis</th>
            </tr></thead>
            <tbody>
            <?php foreach ($classement->rankings as $e): ?>
            <tr>
                <td class="text-center font-bold text-slate-700"><?= $e['rang'] ?></td>
                <td>
                    <p class="font-semibold text-slate-800"><?= htmlspecialchars($e['prenom'] . ' ' . $e['nom'], ENT_QUOTES) ?></p>
                    <p class="text-xs text-slate-400 font-mono"><?= htmlspecialchars($e['matricule'] ?? '', ENT_QUOTES) ?></p>
                </td>
                <td class="text-center font-bold">
                    <?= $e['moyenne']->isEmpty() ? '—' : number_format($e['moyenne']->getValue(), 2) ?>
                </td>
                <td class="text-center">
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= rMentionBadge($e['mention_css']) ?>">
                        <?= htmlspecialchars($e['mention_label'], ENT_QUOTES) ?>
                    </span>
                </td>
                <td class="text-center">
                    <?php if ($e['admis']): ?>
                    <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 inline-block"></i>
                    <?php else: ?>
                    <i data-lucide="x-circle" class="w-4 h-4 text-red-500 inline-block"></i>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
