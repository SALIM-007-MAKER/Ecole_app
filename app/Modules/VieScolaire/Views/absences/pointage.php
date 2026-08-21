<?php
$classes  = $classes  ?? [];
$roster   = $roster   ?? [];
$classe   = $classe   ?? null;
$classeId = $classeId ?? 0;
$date     = $date     ?? date('Y-m-d');
$csrfToken = \Core\Session::getCsrfToken();
?>

<!-- ── Page header ───────────────────────────────────────────────────────── -->
<div class="flex flex-wrap items-start justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <div class="page-icon" style="background:#ede9fe">
            <i data-lucide="check-square" class="w-5 h-5" style="color:#7c3aed"></i>
        </div>
        <div>
            <h2 class="text-xl font-bold text-slate-900" style="letter-spacing:-.03em">
                Pointage journalier
            </h2>
            <?php if ($classe): ?>
            <p class="text-sm text-slate-400">
                <?= htmlspecialchars($classe->niveau . ' ' . $classe->nom, ENT_QUOTES) ?>
                &mdash; <?= date('d/m/Y', strtotime($date)) ?>
            </p>
            <?php else: ?>
            <p class="text-sm text-slate-400">S&eacute;lectionnez une classe pour commencer</p>
            <?php endif; ?>
        </div>
    </div>
    <a href="<?= BASE_URL ?>/v2/vie-scolaire/absences" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour
    </a>
</div>

<?php if ($msg = \Core\Session::getFlash('success')): ?>
<div class="alert alert-success mb-4" role="alert">
    <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
    <span class="flex-1 text-sm"><?= htmlspecialchars($msg, ENT_QUOTES) ?></span>
</div>
<?php endif; ?>
<?php if ($msg = \Core\Session::getFlash('error')): ?>
<div class="alert alert-danger mb-4" role="alert">
    <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
    <span class="flex-1 text-sm"><?= htmlspecialchars($msg, ENT_QUOTES) ?></span>
</div>
<?php endif; ?>

<!-- ── Formulaire de sélection ───────────────────────────────────────────── -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-5">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <i data-lucide="filter" class="w-4 h-4" style="color:#7c3aed"></i>
        <span class="font-semibold text-slate-700">S&eacute;lection de la classe</span>
    </div>
    <div class="p-5">
        <form method="GET" action="<?= BASE_URL ?>/v2/vie-scolaire/absences/pointage"
              class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-40">
                <label class="form-label">
                    Classe <span class="form-required">*</span>
                </label>
                <select name="classe_id" class="form-select" required>
                    <option value="">&mdash; Choisir une classe &mdash;</option>
                    <?php foreach ($classes as $cl): ?>
                    <option value="<?= $cl->id ?>" <?= $classeId == $cl->id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cl->label, ENT_QUOTES) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1 min-w-36">
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-input"
                       value="<?= htmlspecialchars($date, ENT_QUOTES) ?>"
                       max="<?= date('Y-m-d') ?>">
            </div>
            <div class="shrink-0">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="search" class="w-4 h-4"></i>Charger la classe
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (!$classeId): ?>
<!-- ── Empty state ───────────────────────────────────────────────────────── -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm flex flex-col items-center justify-center gap-3 text-center text-slate-500" style="padding:4rem 1.5rem">
    <i data-lucide="check-square" class="w-14 h-14" style="color:#e4e4ec"></i>
    <p class="text-base font-semibold text-slate-500">Aucune classe s&eacute;lectionn&eacute;e</p>
    <p class="text-sm text-slate-400">Utilisez le formulaire ci-dessus pour charger la liste des &eacute;l&egrave;ves.</p>
</div>

<?php elseif (empty($roster)): ?>
<div class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm border-amber-200 bg-amber-50 text-amber-900">
    <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
    <span>Aucun &eacute;l&egrave;ve actif trouv&eacute; dans cette classe.</span>
</div>

<?php else: ?>

<!-- ── Légende + boutons rapides ─────────────────────────────────────────── -->
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div class="flex items-center gap-2 flex-wrap">
        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-emerald-100 text-emerald-700">
            <i data-lucide="check" class="w-3 h-3"></i>Pr&eacute;sent
        </span>
        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-red-100 text-red-700">
            <i data-lucide="x" class="w-3 h-3"></i>Absent
        </span>
        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-amber-100 text-amber-800">
            <i data-lucide="clock" class="w-3 h-3"></i>Retard
        </span>
        <span class="text-xs text-slate-400" style="margin-left:.5rem">
            <?= count($roster) ?> &eacute;l&egrave;ve(s) &mdash; <?= date('d/m/Y', strtotime($date)) ?>
        </span>
    </div>
    <div class="flex items-center gap-2">
        <button type="button" class="btn btn-success" id="btnTousPresents">
            <i data-lucide="check-check" class="w-4 h-4"></i>Tous pr&eacute;sents
        </button>
        <button type="button" class="btn btn-outline-danger" id="btnTousAbsents">
            <i data-lucide="x-circle" class="w-4 h-4"></i>Tous absents
        </button>
    </div>
</div>

<!-- ── Formulaire de pointage ─────────────────────────────────────────────── -->
<form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/absences/pointage" id="formPointage">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
    <input type="hidden" name="classe_id" value="<?= $classeId ?>">
    <input type="hidden" name="date"      value="<?= htmlspecialchars($date, ENT_QUOTES) ?>">

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50" id="tablePointage">
                <thead>
                    <tr>
                        <th style="width:2.5rem">#</th>
                        <th>&#201;l&egrave;ve</th>
                        <th class="text-center" style="width:13rem">Statut</th>
                        <th class="text-center" style="width:7.5rem">Retard (min)</th>
                        <th>Motif</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($roster as $i => $row): ?>
                <?php
                    $eid     = (int)$row['eleve_id'];
                    $current = $row['retard_id'] ? 'retard' : ($row['absence_id'] ? 'absence' : 'present');
                    $duree   = $row['retard_duree'] ?? 15;
                    $motif   = $row['retard_observation'] ?? $row['absence_observation'] ?? '';
                    $rowStyle = match ($current) {
                        'absence' => 'background:#fff5f5',
                        'retard'  => 'background:#fffbeb',
                        default   => '',
                    };
                ?>
                <tr class="pointage-row" id="row_<?= $eid ?>" style="<?= $rowStyle ?>">
                    <td class="text-slate-400 text-xs font-medium"><?= $i + 1 ?></td>
                    <td>
                        <p class="font-semibold text-slate-800 text-sm leading-tight">
                            <?= htmlspecialchars($row['prenom'] . ' ' . $row['nom'], ENT_QUOTES) ?>
                        </p>
                        <span class="mono text-xs text-slate-400">
                            <?= htmlspecialchars($row['matricule'] ?? '', ENT_QUOTES) ?>
                        </span>
                    </td>
                    <td>
                        <div class="flex justify-center gap-1">
                            <label class="cursor-pointer statut-btn">
                                <input type="radio" class="sr-only statut-radio"
                                       name="statuts[<?= $eid ?>]" value="present"
                                       <?= $current === 'present' ? 'checked' : '' ?>>
                                <span id="lp_<?= $eid ?>"
                                      class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1.5 rounded-lg border transition-all"
                                      style="border-color:#6ee7b7;color:#065f46<?= $current === 'present' ? ';background:#059669;color:#fff;border-color:#059669' : '' ?>">
                                    <i data-lucide="check" class="w-3 h-3"></i>Pr&eacute;sent
                                </span>
                            </label>
                            <label class="cursor-pointer statut-btn">
                                <input type="radio" class="sr-only statut-radio"
                                       name="statuts[<?= $eid ?>]" value="absence"
                                       <?= $current === 'absence' ? 'checked' : '' ?>>
                                <span id="la_<?= $eid ?>"
                                      class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1.5 rounded-lg border transition-all"
                                      style="border-color:#fca5a5;color:#991b1b<?= $current === 'absence' ? ';background:#dc2626;color:#fff;border-color:#dc2626' : '' ?>">
                                    <i data-lucide="x" class="w-3 h-3"></i>Absent
                                </span>
                            </label>
                            <label class="cursor-pointer statut-btn">
                                <input type="radio" class="sr-only statut-radio"
                                       name="statuts[<?= $eid ?>]" value="retard"
                                       <?= $current === 'retard' ? 'checked' : '' ?>>
                                <span id="lr_<?= $eid ?>"
                                      class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1.5 rounded-lg border transition-all"
                                      style="border-color:#fcd34d;color:#92400e<?= $current === 'retard' ? ';background:#d97706;color:#fff;border-color:#d97706' : '' ?>">
                                    <i data-lucide="clock" class="w-3 h-3"></i>Retard
                                </span>
                            </label>
                        </div>
                    </td>
                    <td class="text-center">
                        <input type="number" name="durees[<?= $eid ?>]"
                               class="form-input text-center text-sm duree-input"
                               style="width:5rem;margin:0 auto"
                               value="<?= (int)$duree ?>" min="1" max="240"
                               <?= $current !== 'retard' ? 'disabled' : '' ?>>
                    </td>
                    <td>
                        <input type="text" name="motifs[<?= $eid ?>]"
                               class="form-input text-sm motif-input"
                               value="<?= htmlspecialchars($motif, ENT_QUOTES) ?>"
                               placeholder="Motif optionnel"
                               <?= $current === 'present' ? 'disabled' : '' ?>>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="flex items-center gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4" style="justify-content:space-between">
            <div class="flex items-center gap-5 text-sm text-slate-500">
                <span><span id="nbPresents" class="font-bold text-lg" style="color:#059669">0</span><span class="text-xs ml-0.5">pr&eacute;sents</span></span>
                <span><span id="nbAbsents"  class="font-bold text-lg" style="color:#dc2626">0</span><span class="text-xs ml-0.5">absents</span></span>
                <span><span id="nbRetards"  class="font-bold text-lg" style="color:#d97706">0</span><span class="text-xs ml-0.5">retards</span></span>
            </div>
            <button type="submit" class="btn btn-primary">
                <i data-lucide="save" class="w-4 h-4"></i>Enregistrer le pointage
            </button>
        </div>
    </div>
</form>

<script>
(function () {
    var rowBg  = { present: '', absence: '#fff5f5', retard: '#fffbeb' };
    var btnOn  = {
        present: 'background:#059669;color:#fff;border-color:#059669',
        absence: 'background:#dc2626;color:#fff;border-color:#dc2626',
        retard:  'background:#d97706;color:#fff;border-color:#d97706',
    };
    var btnOff = {
        present: 'border-color:#6ee7b7;color:#065f46',
        absence: 'border-color:#fca5a5;color:#991b1b',
        retard:  'border-color:#fcd34d;color:#92400e',
    };
    var pfx = { present: 'lp_', absence: 'la_', retard: 'lr_' };

    function paintRow(eid, val) {
        var row   = document.getElementById('row_' + eid);
        var duree = row.querySelector('.duree-input');
        var motif = row.querySelector('.motif-input');
        row.style.background = rowBg[val] || '';
        duree.disabled = (val !== 'retard');
        motif.disabled = (val === 'present');
        ['present', 'absence', 'retard'].forEach(function (k) {
            var el = document.getElementById(pfx[k] + eid);
            if (!el) return;
            el.style.cssText = (k === val) ? btnOn[k] : btnOff[k];
        });
    }

    function updateCounts() {
        var p = 0, a = 0, r = 0;
        document.querySelectorAll('.statut-radio:checked').forEach(function (radio) {
            if (radio.value === 'present') p++;
            else if (radio.value === 'absence') a++;
            else r++;
        });
        document.getElementById('nbPresents').textContent = p;
        document.getElementById('nbAbsents').textContent  = a;
        document.getElementById('nbRetards').textContent  = r;
    }

    document.querySelectorAll('.statut-radio').forEach(function (radio) {
        var eid = radio.name.match(/\[(\d+)\]/)[1];
        radio.addEventListener('change', function () {
            paintRow(eid, this.value);
            updateCounts();
        });
        if (radio.checked) paintRow(eid, radio.value);
    });

    document.getElementById('btnTousPresents').addEventListener('click', function () {
        document.querySelectorAll('.statut-radio[value="present"]').forEach(function (r) {
            r.checked = true;
            r.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
    document.getElementById('btnTousAbsents').addEventListener('click', function () {
        document.querySelectorAll('.statut-radio[value="absence"]').forEach(function (r) {
            r.checked = true;
            r.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    updateCounts();
})();
</script>
<?php endif; ?>
<script>if (window.lucide) lucide.createIcons();</script>
