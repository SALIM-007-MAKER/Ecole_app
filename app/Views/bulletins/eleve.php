<?php
/**
 * Alimentée par le moteur Académique V2 (BulletinEngineFactory ->
 * BulletinGenerator::previewBulletin()) — voir BulletinController::eleve().
 * $bulletin est un \App\Modules\Academique\DTO\BulletinData ou null si
 * l'élève n'a encore aucune évaluation publiée pour la période affichée.
 */
$bulletin    = $bulletin ?? null;
$mats        = $bulletin->lignesMatieres ?? [];
$moyGen      = $bulletin?->moyennePeriode;
$rang        = ($bulletin && $bulletin->rang > 0) ? $bulletin->rang : null;
$mention     = $bulletin?->mentionLabel;
$total       = $bulletin?->nbEleves ?? 0;
$statsClasse = $bulletin->statistiquesClasse ?? [];
$meilleureMoy  = $statsClasse['max'] ?? null;
$moinsBonneMoy = $statsClasse['min'] ?? null;
$semestresDisponibles = $semestresDisponibles ?? [];

function bMentionBadge(?string $m): string {
    return match($m) {
        'Très Bien'  => 'bg-emerald-100 text-emerald-700',
        'Bien'       => 'bg-violet-100 text-violet-700',
        'Assez Bien' => 'bg-sky-100 text-sky-700',
        'Passable'   => 'bg-amber-100 text-amber-800',
        default      => 'bg-red-100 text-red-700',
    };
}
function bNoteColor(?float $n): string {
    if ($n === null) return 'text-slate-300';
    if ($n >= 16) return 'text-emerald-600 font-bold';
    if ($n >= 12) return 'text-violet-600';
    if ($n >= 10) return 'text-amber-500';
    return 'text-red-500';
}
?>

<div class="flex flex-wrap items-start justify-between gap-4 mb-5">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center shrink-0">
            <i data-lucide="file-text" class="w-5 h-5 text-emerald-600"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-slate-900">Bulletin de notes</h2>
            <p class="text-sm text-slate-500">
                <?= htmlspecialchars($classe->niveau . ' ' . $classe->nom, ENT_QUOTES) ?>
                &mdash; <?= htmlspecialchars($periode->nom . ' ' . ($periode->annee_scolaire ?? ''), ENT_QUOTES) ?>
            </p>
        </div>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <?php if (empty($semestresDisponibles)): ?>
        <span class="btn btn-secondary opacity-50 cursor-not-allowed" title="Aucun semestre configuré pour <?= htmlspecialchars($periode->annee_scolaire ?? '', ENT_QUOTES) ?>">
            <i data-lucide="printer" class="w-4 h-4"></i>Bulletin officiel indisponible
        </span>
        <?php else: ?>
        <?php foreach ($semestresDisponibles as $sem): ?>
        <a href="<?= BASE_URL ?>/v2/academique/bulletins/<?= $eleve->id ?>/<?= $sem->id ?>/imprimer"
           target="_blank" class="btn btn-success" title="Bulletin officiel — <?= htmlspecialchars($sem->nom, ENT_QUOTES) ?>">
            <i data-lucide="printer" class="w-4 h-4"></i>Bulletin officiel — <?= htmlspecialchars($sem->nom, ENT_QUOTES) ?>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/bulletins" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour
        </a>
    </div>
</div>

<!-- En-tête élève -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-5">
    <div class="p-5 p-5">
        <div class="flex flex-wrap items-center gap-5">
            <?php if (!empty($eleve->photo)): ?>
            <img src="<?= BASE_URL ?>/<?= htmlspecialchars($eleve->photo, ENT_QUOTES) ?>"
                 class="w-20 h-20 rounded-full object-cover border-4 border-slate-100 shadow shrink-0" alt="">
            <?php else: ?>
            <div class="w-20 h-20 rounded-full bg-violet-100 flex items-center justify-center border-4 border-slate-100 shadow shrink-0">
                <i data-lucide="user" class="w-10 h-10 text-violet-400"></i>
            </div>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
                <h3 class="text-xl font-bold text-slate-900">
                    <?= htmlspecialchars($eleve->prenom . ' ' . $eleve->nom, ENT_QUOTES) ?>
                </h3>
                <p class="text-sm text-slate-400 mt-0.5">
                    <span class="font-mono"><?= htmlspecialchars($eleve->matricule ?? '', ENT_QUOTES) ?></span>
                    &nbsp;&middot;&nbsp;
                    <?= $eleve->sexe === 'M' ? 'Masculin' : 'Féminin' ?>
                </p>
                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-sky-100 text-sky-700 mt-2">
                    <i data-lucide="building-2" class="w-3 h-3 mr-1"></i>
                    <?= htmlspecialchars($classe->niveau . ' ' . $classe->nom, ENT_QUOTES) ?>
                </span>
            </div>

            <!-- Stats élève -->
            <div class="flex items-stretch gap-4 text-center shrink-0">
                <div class="px-4">
                    <p class="text-xs text-slate-400 mb-1">Moyenne générale</p>
                    <p class="text-3xl font-bold <?= bNoteColor($moyGen !== null ? (float)$moyGen : null) ?>">
                        <?= $moyGen !== null ? number_format($moyGen, 2) : '—' ?>
                        <?php if ($moyGen !== null): ?>
                        <span class="text-lg text-slate-400">/20</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="w-px bg-slate-100 self-stretch"></div>
                <div class="px-4">
                    <p class="text-xs text-slate-400 mb-1">Rang</p>
                    <p class="text-3xl font-bold text-slate-800">
                        <?= $rang !== null ? $rang : '—' ?>
                        <?php if ($rang !== null && $total > 0): ?>
                        <span class="text-lg text-slate-400">/ <?= $total ?></span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="w-px bg-slate-100 self-stretch"></div>
                <div class="px-4 flex flex-col items-center justify-center">
                    <p class="text-xs text-slate-400 mb-2">Mention</p>
                    <?php if ($mention): ?>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= bMentionBadge($mention) ?> text-sm px-3 py-1">
                        <?= htmlspecialchars($mention, ENT_QUOTES) ?>
                    </span>
                    <?php else: ?>
                    <span class="text-slate-300 text-xl font-bold">—</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Notes par matière -->
<?php if (empty($mats)): ?>
<div class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm border-sky-200 bg-sky-50 text-sky-800">
    <i data-lucide="info" class="w-4 h-4 shrink-0"></i>
    <span>Aucun résultat disponible pour cette période.</span>
</div>
<?php else: ?>
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-5">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <i data-lucide="book-open-check" class="w-4 h-4 text-violet-600"></i>
        <span class="font-semibold text-slate-700">
            Détail par matière &mdash; <?= htmlspecialchars($periode->nom ?? '', ENT_QUOTES) ?>
        </span>
        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600 ml-auto"><?= count($mats) ?> matière(s)</span>
    </div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50">
            <thead><tr>
                <th>Matière</th>
                <th class="text-center w-16">Coef.</th>
                <th>Évaluations</th>
                <th class="text-center w-32">Moy. matière</th>
            </tr></thead>
            <tbody>
            <?php foreach ($mats as $mat): ?>
            <tr>
                <td>
                    <p class="font-semibold text-slate-800"><?= htmlspecialchars($mat['matiere_nom'], ENT_QUOTES) ?></p>
                </td>
                <td class="text-center">
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600"><?= $mat['coefficient'] ?></span>
                </td>
                <td>
                    <div class="flex flex-wrap gap-2">
                    <?php foreach ($mat['notes'] as $note): ?>
                    <?php
                    if ($note['est_absent']) {
                        $badge = 'bg-amber-100 text-amber-800'; $val = 'Abs.';
                    } elseif ($note['valeur'] !== null) {
                        $pct = (float)$note['valeur'] / (float)$note['note_max'] * 20;
                        $badge = $pct >= 16 ? 'bg-emerald-100 text-emerald-700' : ($pct >= 12 ? 'bg-violet-100 text-violet-700' : ($pct >= 10 ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-700'));
                        $val   = number_format($note['valeur'], 2) . '/' . (int)$note['note_max'];
                    } else {
                        $badge = 'bg-slate-100 text-slate-600'; $val = '—';
                    }
                    ?>
                    <div class="text-center">
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= $badge ?>"><?= $val ?></span>
                    </div>
                    <?php endforeach; ?>
                    </div>
                </td>
                <td class="text-center">
                    <?php if ($mat['moyenne'] !== null): ?>
                    <span class="text-xl font-bold <?= bNoteColor((float)$mat['moyenne']) ?>">
                        <?= number_format($mat['moyenne'], 2) ?>
                    </span>
                    <span class="text-slate-400 text-sm">/20</span>
                    <?php else: ?>
                    <span class="text-slate-300 text-lg">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="bg-slate-50">
                    <td class="px-4 py-3 text-right font-bold text-slate-700" colspan="3">
                        Moyenne générale pondérée
                    </td>
                    <td class="text-center px-4 py-3 text-xl font-bold <?= bNoteColor($moyGen !== null ? (float)$moyGen : null) ?>">
                        <?= $moyGen !== null ? number_format($moyGen, 2) . '/20' : '—' ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Stats classe -->
<?php if ($meilleureMoy !== null || $moinsBonneMoy !== null): ?>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#dcfce7;color:#16a34a">
            <i data-lucide="trending-up" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value text-emerald-600">
                <?= $meilleureMoy !== null ? number_format($meilleureMoy, 2) : '—' ?>
            </div>
            <div class="stat-label">Meilleure moy. classe</div>
        </div>
    </div>
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#fee2e2;color:#ef4444">
            <i data-lucide="trending-down" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value text-red-500">
                <?= $moinsBonneMoy !== null ? number_format($moinsBonneMoy, 2) : '—' ?>
            </div>
            <div class="stat-label">Moins bonne moy.</div>
        </div>
    </div>
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#e0f2fe;color:#0284c7">
            <i data-lucide="users" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value"><?= $total ?></div>
            <div class="stat-label">Élève(s) dans la classe</div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>
