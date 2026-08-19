<?php
$calendar      = $calendar      ?? [];
$creneaux      = $creneaux      ?? [];
$month         = $month         ?? (int)date('m');
$year          = $year          ?? (int)date('Y');
$moisNom       = $moisNom       ?? '';
$moisNoms      = $moisNoms      ?? [];
$annee         = $annee         ?? '';
$anneesOptions = $anneesOptions ?? [];
$filters       = $filters       ?? [];
$classes       = $classes       ?? [];
$profs         = $profs         ?? [];
$jours         = $jours         ?? \App\Models\EmploiDuTempsModel::JOURS;
$currentUser   = \Core\Session::getUser();
$canEdit       = in_array('emploi_du_temps.edit', $currentUser['permissions'] ?? [], true);

function menColor(int $matiereId): string {
    return \App\Models\EmploiDuTempsModel::getColor($matiereId);
}
function menLight(string $hex): bool {
    $hex = ltrim($hex, '#');
    [$r,$g,$b] = [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
    return (0.299*$r + 0.587*$g + 0.114*$b) > 180;
}

$prevMonth  = $month === 1  ? ['mois'=>12,'annee_num'=>$year-1] : ['mois'=>$month-1,'annee_num'=>$year];
$nextMonth  = $month === 12 ? ['mois'=>1, 'annee_num'=>$year+1] : ['mois'=>$month+1,'annee_num'=>$year];
$cf         = !empty($filters['classe_id'])    ? '&classe_id='.$filters['classe_id']    : '';
$pf         = !empty($filters['professeur_id'])? '&prof_id='.$filters['professeur_id']  : '';

$weeks    = [];
$firstDow = (int)date('N', mktime(0,0,0,$month,1,$year));
$week     = array_fill(0, $firstDow - 1, null);
foreach ($calendar as $day) {
    $week[] = $day;
    if (count($week) === 7) { $weeks[] = $week; $week = []; }
}
if (!empty($week)) {
    while (count($week) < 7) $week[] = null;
    $weeks[] = $week;
}
$joursNoms = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];
?>

<!-- Header -->
<div class="flex flex-wrap items-start justify-between gap-4 mb-5">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
            <i data-lucide="calendar" class="w-5 h-5 text-violet-600"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-slate-900"><?= htmlspecialchars($moisNom, ENT_QUOTES) ?> <?= $year ?></h2>
            <p class="text-sm text-slate-400 mt-0.5">Vue mensuelle — Année <?= htmlspecialchars($annee, ENT_QUOTES) ?></p>
        </div>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <a href="?mois=<?= $prevMonth['mois'] ?>&annee_num=<?= $prevMonth['annee_num'] ?>&annee=<?= urlencode($annee) ?><?= $cf.$pf ?>"
           class="btn btn-secondary p-2 aspect-square" title="Mois précédent">
            <i data-lucide="chevron-left" class="w-4 h-4"></i>
        </a>
        <form method="GET" class="flex items-center gap-1">
            <select name="mois" class="form-input text-sm w-32" onchange="this.form.submit()">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>>
                    <?= $moisNoms[$m] ?? $m ?>
                </option>
                <?php endfor; ?>
            </select>
            <input type="hidden" name="annee_num" value="<?= $year ?>">
            <input type="hidden" name="annee" value="<?= htmlspecialchars($annee, ENT_QUOTES) ?>">
            <?php if (!empty($filters['classe_id'])): ?><input type="hidden" name="classe_id" value="<?= $filters['classe_id'] ?>"><?php endif; ?>
            <?php if (!empty($filters['professeur_id'])): ?><input type="hidden" name="prof_id" value="<?= $filters['professeur_id'] ?>"><?php endif; ?>
        </form>
        <a href="?mois=<?= $nextMonth['mois'] ?>&annee_num=<?= $nextMonth['annee_num'] ?>&annee=<?= urlencode($annee) ?><?= $cf.$pf ?>"
           class="btn btn-secondary p-2 aspect-square" title="Mois suivant">
            <i data-lucide="chevron-right" class="w-4 h-4"></i>
        </a>
        <div class="w-px h-6 bg-slate-200 mx-1"></div>
        <a href="<?= BASE_URL ?>/emplois-du-temps?annee=<?= urlencode($annee) ?><?= $cf.$pf ?>"
           class="btn btn-secondary">
            <i data-lucide="calendar-days" class="w-4 h-4"></i>Vue hebdo
        </a>
    </div>
</div>

<!-- Filtres (admin/dir) -->
<?php if ($canEdit): ?>
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-4">
    <div class="p-5 p-3">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="mois" value="<?= $month ?>">
            <input type="hidden" name="annee_num" value="<?= $year ?>">
            <div class="flex-1 min-w-28">
                <label class="form-label text-xs">Année scolaire</label>
                <select name="annee" class="form-input text-sm">
                    <?php foreach ($anneesOptions as $a): ?>
                    <option value="<?= $a ?>" <?= $a === $annee ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1 min-w-36">
                <label class="form-label text-xs">Classe</label>
                <select name="classe_id" class="form-input text-sm">
                    <option value="">— Toutes —</option>
                    <?php foreach ($classes as $cl): ?>
                    <option value="<?= $cl->id ?>" <?= ($filters['classe_id'] ?? '') == $cl->id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cl->niveau . ' — ' . $cl->nom, ENT_QUOTES) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1 min-w-36">
                <label class="form-label text-xs">Enseignant</label>
                <select name="prof_id" class="form-input text-sm">
                    <option value="">— Tous —</option>
                    <?php foreach ($profs as $p): ?>
                    <option value="<?= $p->id ?>" <?= ($filters['professeur_id'] ?? '') == $p->id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p->prenom . ' ' . $p->nom, ENT_QUOTES) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-2 shrink-0">
                <button type="submit" class="btn btn-primary p-2 aspect-square" title="Filtrer">
                    <i data-lucide="search" class="w-4 h-4"></i>
                </button>
                <a href="<?= BASE_URL ?>/emplois-du-temps/mensuel?annee=<?= urlencode($annee) ?>&mois=<?= $month ?>&annee_num=<?= $year ?>"
                   class="btn btn-secondary p-2 aspect-square" title="Réinitialiser">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Calendrier -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="p-4">
        <!-- En-têtes des jours -->
        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-bottom:6px">
            <?php foreach ($joursNoms as $idx => $n): ?>
            <div style="
                text-align:center;
                font-size:.72rem;
                font-weight:700;
                letter-spacing:.04em;
                text-transform:uppercase;
                color:<?= $idx >= 5 ? '#ef4444' : '#94a3b8' ?>;
                padding:6px 0;
                background:<?= $idx >= 5 ? '#fff7f7' : '#f8fafc' ?>;
                border-radius:6px;
            ">
                <?= $n ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Semaines -->
        <?php foreach ($weeks as $week): ?>
        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-bottom:4px">
            <?php foreach ($week as $idx => $day): ?>
            <?php
                $isWeekend = $idx >= 5;
                $isToday   = $day && $day['date'] === date('Y-m-d');
                $isEmpty   = $day === null;
            ?>
            <div style="
                min-height:94px;
                border:1.5px solid <?= $isToday ? '#7c3aed' : '#e2e8f0' ?>;
                border-radius:8px;
                padding:5px;
                background:<?= $isEmpty ? '#f8fafc' : ($isToday ? '#faf5ff' : ($isWeekend ? '#fff7f7' : '#fff')) ?>;
                opacity:<?= $isEmpty ? '.35' : '1' ?>;
                transition:box-shadow .15s;
            ">
                <?php if (!$isEmpty): ?>
                <!-- Numéro du jour -->
                <div style="
                    display:inline-flex;
                    align-items:center;
                    justify-content:center;
                    width:22px;height:22px;
                    border-radius:50%;
                    font-size:.78rem;
                    font-weight:<?= $isToday ? '800' : '600' ?>;
                    color:<?= $isToday ? '#fff' : ($isWeekend ? '#ef4444' : '#475569') ?>;
                    background:<?= $isToday ? '#7c3aed' : 'transparent' ?>;
                    margin-bottom:3px;
                ">
                    <?= $day['day'] ?>
                </div>

                <?php if (!empty($day['seances'])): ?>
                    <?php $shown = 0; ?>
                    <?php foreach ($day['seances'] as $crSeances): ?>
                    <?php foreach ($crSeances as $s):
                        if ($shown >= 3) break 2;
                        $color = menColor($s->matiere_id);
                        $txt   = menLight($color) ? '#111827' : '#fff';
                        $shown++;
                    ?>
                    <div style="
                        background:<?= $color ?>;
                        color:<?= $txt ?>;
                        border-radius:4px;
                        padding:2px 5px;
                        margin-bottom:2px;
                        font-size:.62rem;
                        line-height:1.25;
                    ">
                        <div style="font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($s->matiere_nom, ENT_QUOTES) ?></div>
                        <div style="opacity:.85"><?= substr($s->heure_debut,0,5) ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                    <?php
                        $total = array_sum(array_map('count', $day['seances']));
                        if ($total > 3):
                    ?>
                    <div style="font-size:.6rem;color:#94a3b8;font-weight:600">+<?= $total - 3 ?> de plus</div>
                    <?php endif; ?>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Légende -->
    <div class="border-t border-slate-100 px-4 py-2.5 flex items-center gap-4 text-xs text-slate-400 bg-slate-50">
        <span class="flex items-center gap-1.5">
            <span style="width:14px;height:14px;border-radius:50%;background:#7c3aed;display:inline-block"></span>
            Aujourd'hui
        </span>
        <span class="flex items-center gap-1.5">
            <span style="width:14px;height:14px;border-radius:4px;background:#fee2e2;border:1px solid #fca5a5;display:inline-block"></span>
            Week-end
        </span>
        <span class="flex items-center gap-1.5">
            <i data-lucide="info" class="w-3 h-3"></i>
            Cliquez sur la vue hebdo pour voir le détail
        </span>
    </div>
</div>
