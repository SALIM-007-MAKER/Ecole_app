<?php
/**
 * Finance V2 — Tableau de bord Caisse
 */
$result    = $result   ?? ['data' => [], 'total' => 0, 'total_pages' => 1, 'page' => 1];
$filters   = $filters  ?? null;
$stats     = $stats    ?? null;
$actives   = $actives  ?? [];
$caissiers = $caissiers ?? [];
$statuts   = $statuts  ?? [];
$maSession = $maSession ?? null;

$fmtMontant = fn(float $v): string => number_format($v, 0, ',', ' ') . ' XOF';
$badgeStatut = fn(string $s): string => match ($s) {
    'ouverte'     => 'bg-blue-100 text-blue-700',
    'en_activite' => 'bg-emerald-100 text-emerald-700',
    'fermee'      => 'bg-slate-100 text-slate-600',
    'annulee'     => 'bg-rose-100 text-rose-600',
    default       => 'bg-slate-100 text-slate-600',
};
?>
<div class="space-y-6">

    <!-- En-tête -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Caisse</h1>
            <p class="text-sm text-slate-500 mt-1"><?= $result['total'] ?> session(s) trouvée(s)</p>
        </div>
        <div class="flex items-center gap-2">
            <?php if ($maSession): ?>
            <a href="<?= BASE_URL ?>/v2/finance/caisse/<?= $maSession->id ?>"
               class="px-4 py-2 text-sm font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg hover:bg-emerald-100">
                <i data-lucide="circle-dot" class="inline w-4 h-4 mr-1"></i> Ma caisse active
            </a>
            <?php elseif ($canOuvrir): ?>
            <a href="<?= BASE_URL ?>/v2/finance/caisse/create"
               class="px-4 py-2 text-sm font-medium text-white bg-violet-600 rounded-lg hover:bg-violet-700">
                <i data-lucide="unlock" class="inline w-4 h-4 mr-1"></i> Ouvrir la caisse
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Flash -->
    <?php if ($flash = \Core\Session::getFlash('success')): ?>
    <div class="flex items-center gap-3 p-4 bg-emerald-50 text-emerald-800 rounded-xl border border-emerald-200">
        <i data-lucide="check-circle" class="w-5 h-5"></i><span><?= htmlspecialchars($flash) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($flash = \Core\Session::getFlash('error')): ?>
    <div class="flex items-center gap-3 p-4 bg-red-50 text-red-800 rounded-xl border border-red-200">
        <i data-lucide="alert-circle" class="w-5 h-5"></i><span><?= htmlspecialchars($flash) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($flash = \Core\Session::getFlash('warning')): ?>
    <div class="flex items-center gap-3 p-4 bg-amber-50 text-amber-800 rounded-xl border border-amber-200">
        <i data-lucide="alert-triangle" class="w-5 h-5"></i><span><?= htmlspecialchars($flash) ?></span>
    </div>
    <?php endif; ?>

    <!-- Sessions actives en cours -->
    <?php if ($actives): ?>
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
        <div class="flex items-center gap-2 mb-3">
            <i data-lucide="activity" class="w-4 h-4 text-emerald-600"></i>
            <span class="text-sm font-semibold text-emerald-800"><?= count($actives) ?> session(s) active(s) en ce moment</span>
        </div>
        <div class="flex flex-wrap gap-3">
            <?php foreach ($actives as $a): ?>
            <a href="<?= BASE_URL ?>/v2/finance/caisse/<?= $a->id ?>"
               class="inline-flex items-center gap-2 px-3 py-2 bg-white rounded-lg border border-emerald-200 text-sm hover:border-emerald-400">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span class="font-mono text-xs text-violet-700"><?= htmlspecialchars($a->numero) ?></span>
                <span class="text-slate-700"><?= htmlspecialchars($a->caissier_nom) ?></span>
                <span class="text-xs text-slate-500"><?= date('H:i', strtotime($a->heure_ouverture)) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <?php if ($stats): ?>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">Total recettes</div>
            <div class="text-xl font-bold text-emerald-700 mt-1"><?= $fmtMontant((float)$stats->total_recettes) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">Total décaissements</div>
            <div class="text-xl font-bold text-rose-600 mt-1"><?= $fmtMontant((float)$stats->total_decaissements) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">Sessions actives</div>
            <div class="text-xl font-bold text-blue-600 mt-1"><?= (int)$stats->sessions_actives ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">Sessions fermées</div>
            <div class="text-xl font-bold text-slate-700 mt-1"><?= (int)$stats->sessions_fermees ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filtres -->
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
            <div class="sm:col-span-1">
                <input type="text" name="q" value="<?= htmlspecialchars($filters->q ?? '') ?>"
                       placeholder="Nº session, caissier…"
                       class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-violet-300">
            </div>
            <div>
                <select name="statut" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-violet-300">
                    <option value="">Tous statuts</option>
                    <?php foreach ($statuts as $k => $v): ?>
                    <option value="<?= $k ?>" <?= ($filters->statut ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <select name="caissier_id" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-violet-300">
                    <option value="">Tous caissiers</option>
                    <?php foreach ($caissiers as $c): ?>
                    <option value="<?= $c->id ?>" <?= ($filters->caissierId ?? 0) == $c->id ? 'selected' : '' ?>><?= htmlspecialchars($c->nom) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <input type="date" name="date_debut" value="<?= htmlspecialchars($filters->dateDebut ?? '') ?>"
                       class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-violet-300">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-3 py-2 text-sm font-medium text-white bg-violet-600 rounded-lg hover:bg-violet-700">
                    <i data-lucide="search" class="inline w-4 h-4"></i>
                </button>
                <a href="<?= BASE_URL ?>/v2/finance/caisse" class="px-3 py-2 text-sm text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200">
                    <i data-lucide="x" class="inline w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Table sessions -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Session</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Caissier</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Ouverture</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Fermeture</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-600">Solde initial</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-600">Recettes</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-600">Décaissements</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Statut</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($result['data'])): ?>
                <tr>
                    <td colspan="9" class="px-4 py-12 text-center text-slate-400">
                        <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                        <p>Aucune session de caisse</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($result['data'] as $s): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <div class="font-mono text-xs font-semibold text-violet-700"><?= htmlspecialchars($s->numero) ?></div>
                    </td>
                    <td class="px-4 py-3 font-medium text-slate-800"><?= htmlspecialchars($s->caissier_nom) ?></td>
                    <td class="px-4 py-3 text-slate-600">
                        <?= date('d/m/Y', strtotime($s->date_ouverture)) ?>
                        <span class="text-xs text-slate-400"><?= substr($s->heure_ouverture, 0, 5) ?></span>
                    </td>
                    <td class="px-4 py-3 text-slate-600">
                        <?= $s->date_fermeture ? date('d/m/Y', strtotime($s->date_fermeture)) . ' <span class="text-xs text-slate-400">' . substr($s->heure_fermeture, 0, 5) . '</span>' : '—' ?>
                    </td>
                    <td class="px-4 py-3 text-right text-slate-700"><?= $fmtMontant((float)$s->solde_initial) ?></td>
                    <td class="px-4 py-3 text-right font-semibold text-emerald-700"><?= $fmtMontant((float)$s->total_recettes) ?></td>
                    <td class="px-4 py-3 text-right font-semibold text-rose-600"><?= $fmtMontant((float)$s->total_decaissements) ?></td>
                    <td class="px-4 py-3">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= $badgeStatut($s->statut) ?>">
                            <?= $statuts[$s->statut] ?? $s->statut ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="<?= BASE_URL ?>/v2/finance/caisse/<?= $s->id ?>"
                           class="p-1.5 text-slate-400 hover:text-violet-600 rounded">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($result['total_pages'] > 1): ?>
    <div class="flex items-center justify-between text-sm text-slate-600">
        <span>Page <?= $result['page'] ?> / <?= $result['total_pages'] ?></span>
        <div class="flex gap-1">
            <?php for ($i = 1; $i <= $result['total_pages']; $i++): $qs = $_GET; $qs['page'] = $i; ?>
            <a href="?<?= http_build_query($qs) ?>"
               class="px-3 py-1 rounded <?= $i === $result['page'] ? 'bg-violet-600 text-white' : 'bg-white border border-slate-300 hover:bg-slate-50' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>

</div>
