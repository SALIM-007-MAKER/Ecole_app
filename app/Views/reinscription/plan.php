<?php
$source              = $source              ?? '';
$destination         = $destination         ?? '';
$classesSource        = $classesSource       ?? [];
$classesDestination   = $classesDestination  ?? [];
$suggestions          = $suggestions         ?? [];
$dejaTraites          = $dejaTraites         ?? 0;
?>

<!-- Page header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="repeat" class="w-5 h-5 text-violet-600"></i>Plan de passage
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">
            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600"><?= htmlspecialchars($source, ENT_QUOTES) ?></span>
            <i data-lucide="arrow-right" class="w-3.5 h-3.5 inline-block mx-1"></i>
            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-violet-100 text-violet-700"><?= htmlspecialchars($destination, ENT_QUOTES) ?></span>
        </p>
    </div>
    <a href="<?= BASE_URL ?>/reinscription" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour
    </a>
</div>

<?php if ($dejaTraites > 0): ?>
<div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 mb-5 text-sm text-amber-800">
    <i data-lucide="alert-triangle" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
    <div><?= $dejaTraites ?> élève(s) ont déjà été réinscrit(s) de <?= htmlspecialchars($source, ENT_QUOTES) ?> vers <?= htmlspecialchars($destination, ENT_QUOTES) ?> précédemment. Poursuivre ne les affectera pas à nouveau (ils ne sont déjà plus dans les classes source), mais vérifiez qu'il ne s'agit pas d'une répétition involontaire.</div>
</div>
<?php endif; ?>

<?php if (empty($classesSource)): ?>
<div class="flex flex-col items-center justify-center gap-3 text-center text-slate-500 py-16 rounded-xl border border-slate-200 bg-white shadow-sm">
    <p class="text-slate-500 font-medium">Aucune classe active trouvée pour <?= htmlspecialchars($source, ENT_QUOTES) ?>.</p>
</div>
<?php elseif (empty($classesDestination)): ?>
<div class="flex flex-col items-center justify-center gap-3 text-center text-slate-500 py-16 rounded-xl border border-amber-200 bg-amber-50 shadow-sm">
    <i data-lucide="building-2" class="w-8 h-8 text-amber-400"></i>
    <p class="text-amber-800 font-medium">Aucune classe n'existe encore pour <?= htmlspecialchars($destination, ENT_QUOTES) ?>.</p>
    <p class="text-sm text-amber-700">Créez d'abord les classes de la nouvelle année scolaire.</p>
    <a href="<?= BASE_URL ?>/classes/create" class="btn btn-primary mt-2" target="_blank">
        <i data-lucide="plus" class="w-4 h-4"></i>Nouvelle classe
    </a>
</div>
<?php else: ?>

<form method="POST" action="<?= BASE_URL ?>/reinscription/apercu" id="formPlan">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
    <input type="hidden" name="source" value="<?= htmlspecialchars($source, ENT_QUOTES) ?>">
    <input type="hidden" name="destination" value="<?= htmlspecialchars($destination, ENT_QUOTES) ?>">

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-5">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="arrow-right-left" class="w-4 h-4 text-violet-600"></i>
            <span class="font-semibold text-slate-700">Pour chaque classe, choisissez sa destination</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50">
                <thead>
                    <tr>
                        <th style="width:36px"></th>
                        <th>Classe source (<?= htmlspecialchars($source, ENT_QUOTES) ?>)</th>
                        <th class="text-center">Effectif</th>
                        <th>Classe destination (<?= htmlspecialchars($destination, ENT_QUOTES) ?>)</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($classesSource as $cs):
                    $suggestion = $suggestions[$cs->id] ?? null;
                    $vide       = (int)$cs->nb_eleves === 0;
                ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="classes_source[]" value="<?= $cs->id ?>"
                                   <?= $vide ? '' : 'checked' ?> class="rounded border-slate-300">
                        </td>
                        <td class="font-semibold text-slate-800">
                            <?= htmlspecialchars($cs->niveau . ' — ' . $cs->nom, ENT_QUOTES) ?>
                        </td>
                        <td class="text-center">
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600"><?= (int)$cs->nb_eleves ?></span>
                        </td>
                        <td>
                            <select name="classe_dest_<?= $cs->id ?>" class="form-input text-sm w-auto">
                                <option value="sortant" <?= $suggestion === null ? 'selected' : '' ?>>— Sortant (fin de cycle) —</option>
                                <?php foreach ($classesDestination as $cd):
                                    $restant = (int)$cd->max_eleves > 0 ? (int)$cd->max_eleves - (int)$cd->nb_eleves : null;
                                ?>
                                <option value="<?= $cd->id ?>" <?= ($suggestion && $suggestion->id === $cd->id) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cd->niveau . ' — ' . $cd->nom, ENT_QUOTES) ?>
                                    (<?= (int)$cd->nb_eleves ?><?= $restant !== null ? '/' . (int)$cd->max_eleves : '' ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="<?= BASE_URL ?>/reinscription" class="btn btn-secondary">
            <i data-lucide="x" class="w-4 h-4"></i>Annuler
        </a>
        <button type="submit" class="btn btn-primary">
            <i data-lucide="eye" class="w-4 h-4"></i>Aperçu avant validation
        </button>
    </div>
</form>
<?php endif; ?>
