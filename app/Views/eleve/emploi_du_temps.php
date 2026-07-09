<?php
$eleve    = $eleve    ?? null;
$creneaux = $creneaux ?? [];
$jours    = $jours    ?? ['Lundi','Mardi','Mercredi','Jeudi','Vendredi'];
$seances  = $seances  ?? [];

function eleEdtColor(string $hex): string {
    [$r,$g,$b] = sscanf($hex, '#%02x%02x%02x') ?: [124,58,237];
    return (0.2126*$r + 0.7152*$g + 0.0722*$b) > 128 ? '#1e293b' : '#ffffff';
}

$byJourCreneau = [];
foreach ($seances as $s) {
    $byJourCreneau[$s->jour_semaine][$s->creneau_id] = $s;
}
?>

<!-- Header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center">
            <i data-lucide="calendar" class="w-5 h-5 text-violet-600"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-slate-900">Mon emploi du temps</h2>
            <?php if ($eleve): ?>
            <p class="text-xs text-slate-400"><?= htmlspecialchars($eleve->classe_nom ?? '', ENT_QUOTES) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <a href="<?= BASE_URL ?>/eleve/dashboard" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour
    </a>
</div>

<?php if (empty($creneaux)): ?>
<div class="flex flex-col items-center justify-center gap-3 text-center text-slate-500 py-16">
    <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-4">
        <i data-lucide="calendar-off" class="w-8 h-8 text-slate-300"></i>
    </div>
    <p class="text-slate-500 font-medium">Aucun emploi du temps disponible</p>
    <p class="text-sm text-slate-400 mt-1">L'emploi du temps n'a pas encore été configuré pour votre classe.</p>
</div>
<?php else: ?>
<div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table style="width:100%;border-collapse:collapse;table-layout:fixed;min-width:600px">
            <thead>
                <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0">
                    <th style="width:72px;padding:10px 12px;text-align:left;font-size:11px;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:.05em">Horaires</th>
                    <?php foreach ($jours as $j): ?>
                    <th style="padding:10px 12px;text-align:center;font-size:13px;color:#1e293b;font-weight:700">
                        <?= htmlspecialchars($j, ENT_QUOTES) ?>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($creneaux as $cr): ?>
            <?php if (!empty($cr->is_pause)): ?>
            <tr style="background:#fafafa">
                <td style="padding:5px 12px;font-size:11px;color:#94a3b8;border-top:1px solid #f1f5f9">
                    <?= htmlspecialchars($cr->heure_debut, ENT_QUOTES) ?>
                </td>
                <td colspan="<?= count($jours) ?>" style="padding:5px 12px;font-size:11px;color:#94a3b8;font-style:italic;text-align:center;border-top:1px solid #f1f5f9">
                    Pause — <?= htmlspecialchars($cr->heure_debut, ENT_QUOTES) ?> – <?= htmlspecialchars($cr->heure_fin, ENT_QUOTES) ?>
                </td>
            </tr>
            <?php else: ?>
            <tr>
                <td style="padding:8px 12px;font-size:11px;color:#64748b;border-top:1px solid #f1f5f9;vertical-align:top;white-space:nowrap">
                    <span style="font-weight:700;color:#475569"><?= htmlspecialchars($cr->heure_debut, ENT_QUOTES) ?></span><br>
                    <span style="color:#94a3b8;font-size:10px"><?= htmlspecialchars($cr->heure_fin, ENT_QUOTES) ?></span>
                </td>
                <?php foreach ($jours as $idx => $j):
                    $jourNum = $idx + 1;
                    $s = $byJourCreneau[$jourNum][$cr->id] ?? null;
                ?>
                <td style="padding:4px;border-top:1px solid #f1f5f9;vertical-align:top">
                    <?php if ($s):
                        $hex = $s->couleur ?? '#7c3aed';
                        $txtColor = eleEdtColor($hex);
                    ?>
                    <div style="background:<?= htmlspecialchars($hex, ENT_QUOTES) ?>;color:<?= $txtColor ?>;border-radius:10px;padding:8px 10px;height:100%;min-height:60px;border-left:3px solid rgba(0,0,0,0.12);box-shadow:0 1px 3px rgba(0,0,0,0.06)">
                        <p style="font-weight:700;font-size:12px;margin:0 0 3px;line-height:1.3"><?= htmlspecialchars($s->matiere_nom, ENT_QUOTES) ?></p>
                        <p style="font-size:11px;opacity:.85;margin:0;line-height:1.3"><?= htmlspecialchars($s->enseignant_nom ?? '', ENT_QUOTES) ?></p>
                        <?php if (!empty($s->salle_nom)): ?>
                        <p style="font-size:10px;opacity:.65;margin:3px 0 0;display:flex;align-items:center;gap:2px">
                            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <?= htmlspecialchars($s->salle_nom, ENT_QUOTES) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div style="height:60px"></div>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
