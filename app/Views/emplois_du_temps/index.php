<?php
$creneaux      = $creneaux      ?? [];
$grid          = $grid          ?? [];
$stats         = $stats         ?? (object)[];
$classes       = $classes       ?? [];
$profs         = $profs         ?? [];
$salles        = $salles        ?? [];
$filters       = $filters       ?? [];
$annee         = $annee         ?? '';
$anneesOptions = $anneesOptions ?? [];
$jours         = $jours         ?? \App\Models\EmploiDuTempsModel::JOURS;
$currentUser   = \Core\Session::getUser();
$canCreate     = in_array('emploi_du_temps.create', $currentUser['permissions'] ?? [], true);
$canEdit       = in_array('emploi_du_temps.edit',   $currentUser['permissions'] ?? [], true);
$csrfToken     = \Core\Session::getCsrfToken();

$joursActifs = array_keys($jours);
if (!$canEdit && empty($grid[6])) {
    $joursActifs = array_values(array_filter($joursActifs, fn($j) => $j !== 6));
}

function edtColHex(int $matiereId, ?string $custom = null): string {
    return \App\Models\EmploiDuTempsModel::getColor($matiereId, $custom);
}
function edtIsLight(string $hex): bool {
    $hex = ltrim($hex, '#');
    [$r,$g,$b] = [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
    return (0.299*$r + 0.587*$g + 0.114*$b) > 180;
}
$matieresSeen = [];
foreach ($grid as $jourGrid) {
    foreach ($jourGrid as $crGrid) {
        foreach ($crGrid as $s) {
            if (!isset($matieresSeen[$s->matiere_id])) $matieresSeen[$s->matiere_id] = $s;
        }
    }
}
?>

<div class="flex flex-wrap items-start justify-between gap-4 mb-5">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
            <i data-lucide="calendar-days" class="w-5 h-5 text-violet-600"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-slate-900">Emploi du temps</h2>
            <p class="text-sm text-slate-400">
                <?= $stats->total_seances ?? 0 ?> séances ·
                <?= $stats->total_classes ?? 0 ?> classes ·
                <?= $stats->total_profs ?? 0 ?> enseignants
            </p>
        </div>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <a href="<?= BASE_URL ?>/emplois-du-temps/mensuel?annee=<?= urlencode($annee) ?><?= !empty($filters['classe_id']) ? '&classe_id='.$filters['classe_id'] : '' ?><?= !empty($filters['professeur_id']) ? '&prof_id='.$filters['professeur_id'] : '' ?>"
           class="btn btn-secondary">
            <i data-lucide="calendar" class="w-4 h-4"></i>Vue mensuelle
        </a>
        <a href="<?= BASE_URL ?>/emplois-du-temps/print?annee=<?= urlencode($annee) ?><?= !empty($filters['classe_id']) ? '&classe_id='.$filters['classe_id'] : '' ?><?= !empty($filters['professeur_id']) ? '&prof_id='.$filters['professeur_id'] : '' ?>"
           target="_blank" class="btn btn-secondary">
            <i data-lucide="printer" class="w-4 h-4"></i>Imprimer
        </a>
        <?php if ($canCreate): ?>
        <a href="<?= BASE_URL ?>/emplois-du-temps/create?annee=<?= urlencode($annee) ?>"
           class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i>Ajouter une séance
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filtres -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-4">
    <div class="p-5 p-3">
        <form method="GET" action="<?= BASE_URL ?>/emplois-du-temps" class="flex flex-wrap items-end gap-2">
            <div class="flex-1 min-w-28">
                <label class="form-label text-xs">Année scolaire</label>
                <select name="annee" class="form-input text-sm">
                    <?php foreach ($anneesOptions as $a): ?>
                    <option value="<?= $a ?>" <?= $a === $annee ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($canEdit): ?>
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
            <?php endif; ?>
            <div class="flex gap-2 shrink-0">
                <button class="btn btn-primary p-2 aspect-square">
                    <i data-lucide="search" class="w-4 h-4"></i>
                </button>
                <a href="<?= BASE_URL ?>/emplois-du-temps" class="btn btn-secondary p-2 aspect-square">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Grille hebdomadaire -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-4">
    <div class="p-2 overflow-x-auto">
        <?php if (empty($creneaux)): ?>
        <div class="text-center py-12">
            <i data-lucide="calendar-x" class="w-12 h-12 text-slate-200 mx-auto mb-3"></i>
            <p class="text-slate-400">Aucun créneau configuré.</p>
            <a href="<?= BASE_URL ?>/creneaux" class="btn btn-primary mt-2">Gérer les créneaux</a>
        </div>
        <?php else: ?>
        <table style="table-layout:fixed;border-collapse:separate;border-spacing:3px;width:100%;min-width:700px">
            <thead>
                <tr>
                    <th style="width:80px;background:#1e293b;color:#fff;text-align:center;font-size:.75rem;padding:8px 4px;border-radius:6px">Horaire</th>
                    <?php foreach ($joursActifs as $jour): ?>
                    <th style="background:#1e293b;color:#fff;text-align:center;font-size:.78rem;padding:8px 4px;border-radius:6px">
                        <?= htmlspecialchars($jours[$jour], ENT_QUOTES) ?>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($creneaux as $cr):
                $isPause = $cr->type !== 'cours';
            ?>
            <?php if ($isPause): ?>
            <tr>
                <td style="font-size:.7rem;color:#94a3b8;text-align:center;padding:4px;background:#f8fafc;border-radius:4px">
                    <?= substr($cr->heure_debut,0,5) ?>
                </td>
                <td colspan="<?= count($joursActifs) ?>"
                    style="background:#f1f5f9;text-align:center;font-size:.72rem;color:#94a3b8;font-style:italic;padding:4px;border-radius:4px">
                    ☕ <?= htmlspecialchars($cr->nom, ENT_QUOTES) ?>
                    (<?= substr($cr->heure_debut,0,5) ?> — <?= substr($cr->heure_fin,0,5) ?>)
                </td>
            </tr>
            <?php else: ?>
            <tr>
                <td style="text-align:center;vertical-align:middle;padding:4px;background:#f8fafc;border-radius:4px;font-size:.72rem;color:#64748b">
                    <div style="font-weight:700;font-size:.68rem"><?= htmlspecialchars($cr->nom, ENT_QUOTES) ?></div>
                    <div><?= substr($cr->heure_debut,0,5) ?></div>
                    <div style="opacity:.5">↕</div>
                    <div><?= substr($cr->heure_fin,0,5) ?></div>
                </td>
                <?php foreach ($joursActifs as $jour): ?>
                <?php $seances = $grid[$jour][$cr->id] ?? []; ?>
                <td style="vertical-align:top;padding:3px;position:relative" class="edt-cell">
                    <?php if (!empty($seances)): ?>
                        <?php foreach ($seances as $s):
                            $color = edtColHex($s->matiere_id, $s->couleur);
                            $txtColor = edtIsLight($color) ? '#111827' : '#fff';
                        ?>
                        <div class="edt-seance" style="background:<?= $color ?>;color:<?= $txtColor ?>;border-left:4px solid rgba(0,0,0,.2);border-radius:6px;padding:6px 8px;margin-bottom:3px;font-size:.75rem;line-height:1.3;position:relative">
                            <div style="font-weight:700;font-size:.78rem;margin-bottom:1px"><?= htmlspecialchars($s->matiere_nom, ENT_QUOTES) ?></div>
                            <div style="opacity:.85"><?= htmlspecialchars($s->prof_fullname, ENT_QUOTES) ?></div>
                            <?php if (!empty($filters['professeur_id']) || $canEdit): ?>
                            <div style="opacity:.8;font-size:.68rem">
                                <?= htmlspecialchars($s->classe_niveau . ' ' . $s->classe_nom, ENT_QUOTES) ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($s->salle_nom): ?>
                            <div style="opacity:.8;font-size:.68rem">📍 <?= htmlspecialchars($s->salle_nom, ENT_QUOTES) ?></div>
                            <?php endif; ?>
                            <?php if ($canEdit): ?>
                            <div class="edt-actions" style="display:none;position:absolute;top:4px;right:4px;gap:2px">
                                <a href="<?= BASE_URL ?>/emplois-du-temps/<?= $s->id ?>/edit"
                                   style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;background:rgba(255,255,255,.25);border-radius:4px;text-decoration:none;color:<?= $txtColor ?>;font-size:.7rem">
                                    ✏️
                                </a>
                                <button type="button"
                                        onclick="openDelSeance(<?= $s->id ?>, '<?= htmlspecialchars(addslashes($s->matiere_nom . ' — ' . ($jours[$jour] ?? '')), ENT_QUOTES) ?>')"
                                        style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;background:rgba(220,53,69,.7);border-radius:4px;border:none;cursor:pointer;color:#fff;font-size:.7rem">
                                    🗑
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="min-height:48px;display:flex;align-items:center;justify-content:center;border-radius:6px" class="edt-empty">
                            <?php if ($canCreate): ?>
                            <a href="<?= BASE_URL ?>/emplois-du-temps/create?annee=<?= urlencode($annee) ?>&jour=<?= $jour ?>&creneau_id=<?= $cr->id ?><?= !empty($filters['classe_id']) ? '&classe_id='.$filters['classe_id'] : '' ?>"
                               class="edt-add" title="Ajouter une séance"
                               style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:#7c3aed;color:#fff;text-decoration:none;font-size:1.1rem;opacity:0;transition:opacity .15s">
                                +
                            </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Légende matières -->
<?php if (!empty($matieresSeen)): ?>
<div class="flex flex-wrap gap-2 mb-4">
    <?php foreach ($matieresSeen as $m):
        $c = edtColHex($m->matiere_id, $m->couleur);
        $t = edtIsLight($c) ? '#111827' : '#fff';
    ?>
    <span style="background:<?= $c ?>;color:<?= $t ?>;font-size:.72rem;padding:3px 10px;border-radius:999px;font-weight:600">
        <?= htmlspecialchars($m->matiere_nom, ENT_QUOTES) ?>
    </span>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Liens rapides -->
<?php if ($canEdit): ?>
<div class="flex items-center gap-3">
    <a href="<?= BASE_URL ?>/salles" class="btn btn-secondary">
        <i data-lucide="building" class="w-4 h-4"></i>Gérer les salles
    </a>
    <a href="<?= BASE_URL ?>/creneaux" class="btn btn-secondary">
        <i data-lucide="clock" class="w-4 h-4"></i>Gérer les créneaux
    </a>
</div>
<?php endif; ?>

<?php if ($canEdit): ?>
<!-- Modal suppression séance -->
<div id="modalDelSeance" class="modal-overlay fixed inset-0 z-[9000] hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm [&.active]:flex hidden">
    <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white shadow-2xl max-w-sm">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <span class="font-semibold text-slate-800">Supprimer la séance</span>
            <button onclick="document.getElementById('modalDelSeance').classList.remove('active')"
                    class="btn btn-ghost btn-icon text-slate-400">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div class="px-5 py-5">
            <p class="text-sm text-slate-600">
                Supprimer la séance <strong id="delSeanceNom" class="text-slate-900"></strong> ?
            </p>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 justify-end gap-2">
            <button onclick="document.getElementById('modalDelSeance').classList.remove('active')"
                    class="btn btn-secondary">Annuler</button>
            <form id="formDelSeance" method="POST">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                <input type="hidden" name="redirect" value="<?= BASE_URL ?>/emplois-du-temps?annee=<?= urlencode($annee) ?><?= !empty($filters['classe_id']) ? '&classe_id='.$filters['classe_id'] : '' ?><?= !empty($filters['professeur_id']) ? '&prof_id='.$filters['professeur_id'] : '' ?>">
                <button class="btn btn-danger"><i data-lucide="trash-2" class="w-4 h-4"></i>Supprimer</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.edt-cell:hover .edt-add { opacity: 1 !important; }
.edt-seance:hover .edt-actions { display: flex !important; }
</style>

<script>
function openDelSeance(id, nom) {
    document.getElementById('delSeanceNom').textContent = nom;
    document.getElementById('formDelSeance').action = '<?= BASE_URL ?>/emplois-du-temps/' + id + '/delete';
    document.getElementById('modalDelSeance').classList.add('active');
}
</script>
