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
$lignesMatiere   = $lignesMatiere   ?? [];

function mNoteColor(?float $n): string {
    if ($n === null) return 'text-slate-300';
    if ($n >= 16) return 'text-emerald-600';
    if ($n >= 12) return 'text-violet-600';
    if ($n >= 10) return 'text-amber-500';
    return 'text-red-500';
}
?>

<!-- En-tête -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="calculator" class="w-5 h-5 text-violet-500"></i>
            Tableau des moyennes — Académique V2
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">Moyenne par matière (RankingEngine::classementMatiere()) et moyenne générale de classe, données notes_v2.</p>
    </div>
</div>

<!-- Filtres -->
<form method="GET" action="<?= BASE_URL ?>/v2/academique/resultats/moyennes" class="flex flex-wrap gap-3 mb-6">
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
        <i data-lucide="calculator" class="w-8 h-8 text-slate-300"></i>
    </div>
    <p class="text-slate-500 font-medium">Sélectionnez une période et une classe</p>
</div>
<?php elseif (empty($lignesMatiere)): ?>
<div class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm border-sky-200 bg-sky-50 text-sky-800">
    <i data-lucide="info" class="w-4 h-4 shrink-0"></i>
    <span>Aucune évaluation publiée pour cette classe sur cette période.</span>
</div>
<?php else: ?>

<!-- Moyenne générale de la classe -->
<?php $s = $classement->statistiques; ?>
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-violet-600"><?= $s['moyenne'] !== null ? number_format($s['moyenne'], 2) : '—' ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Moyenne générale classe</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-sky-600"><?= $s['taux_reussite'] !== null ? number_format($s['taux_reussite'], 1) . '%' : '—' ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Taux de réussite</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-slate-700"><?= $s['ecart_type'] !== null ? number_format($s['ecart_type'], 2) : '—' ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Écart-type</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-slate-700"><?= $classement->nbTotal ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Élèves</p>
    </div>
</div>

<!-- Moyennes par matière -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <i data-lucide="book-open-check" class="w-4 h-4 text-violet-600"></i>
        <span><?= htmlspecialchars($classe->niveau . ' ' . $classe->nom, ENT_QUOTES) ?> — <?= htmlspecialchars($periode->nom, ENT_QUOTES) ?></span>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_tbody]:divide-y [&_tbody]:divide-slate-100">
            <thead><tr>
                <th>Matière</th>
                <th class="text-center">Moyenne classe</th>
                <th class="text-center">Min</th>
                <th class="text-center">Max</th>
                <th class="text-center">Taux réussite</th>
                <th class="text-center">Élèves notés</th>
            </tr></thead>
            <tbody>
            <?php foreach ($lignesMatiere as $l): $ms = $l['statistiques']; ?>
            <tr>
                <td class="font-semibold text-slate-800"><?= htmlspecialchars($l['matiere_nom'], ENT_QUOTES) ?></td>
                <td class="text-center text-lg font-bold <?= mNoteColor($l['moyenneClasse']) ?>">
                    <?= $l['moyenneClasse'] !== null ? number_format($l['moyenneClasse'], 2) : '—' ?>
                </td>
                <td class="text-center text-slate-500"><?= ($ms['min'] ?? null) !== null ? number_format($ms['min'], 2) : '—' ?></td>
                <td class="text-center text-slate-500"><?= ($ms['max'] ?? null) !== null ? number_format($ms['max'], 2) : '—' ?></td>
                <td class="text-center text-slate-500"><?= ($ms['taux_reussite'] ?? null) !== null ? number_format($ms['taux_reussite'], 1) . '%' : '—' ?></td>
                <td class="text-center text-slate-500"><?= $l['nbEleves'] ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
