<?php
$paiements     = $paiements     ?? [];
$pagination    = $pagination    ?? [];
$filters       = $filters       ?? [];
$classes       = $classes       ?? [];
$fraisTypes    = $fraisTypes    ?? [];
$anneesOptions = $anneesOptions ?? [];
$modes         = $modes         ?? [];
$totalMontant  = $totalMontant  ?? 0;
$currentUser   = \Core\Session::getUser();
$csrfToken     = \Core\Session::getCsrfToken();
$canCreate     = in_array('comptabilite.create', $currentUser['permissions'] ?? [], true);
$canEdit       = in_array('comptabilite.edit', $currentUser['permissions'] ?? [], true);

function fmtPI(float $n): string {
    return number_format($n, 2, ',', ' ') . ' FCFA';
}

$buildUrl = function(array $extra) use ($filters): string {
    $params = array_merge($filters, $extra);
    return BASE_URL . '/paiements?' . http_build_query(array_filter($params, fn($v) => $v !== ''));
};
?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
            <i data-lucide="receipt" class="w-6 h-6 text-emerald-600"></i>Paiements
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">
            <?= $pagination['total'] ?? count($paiements) ?> paiement(s)
            <?php if ($totalMontant > 0): ?>
            — Total : <strong class="text-emerald-600"><?= fmtPI((float)$totalMontant) ?></strong>
            <?php endif; ?>
        </p>
    </div>
    <?php if ($canCreate): ?>
    <a href="<?= BASE_URL ?>/paiements/create" class="btn btn-primary">
        <i data-lucide="plus-circle" class="w-4 h-4"></i>Nouveau paiement
    </a>
    <?php endif; ?>
</div>

<!-- Filtres -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm p-4 mb-6">
    <form method="GET" action="<?= BASE_URL ?>/paiements" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-40">
            <input type="text" name="q" class="form-input"
                   value="<?= htmlspecialchars($filters['q'] ?? '', ENT_QUOTES) ?>"
                   placeholder="Élève, référence…">
        </div>
        <div class="flex-1 min-w-36">
            <select name="classe_id" class="form-select">
                <option value="">— Classe —</option>
                <?php foreach ($classes as $cl): ?>
                <option value="<?= $cl->id ?>" <?= ($filters['classe_id'] ?? '') == $cl->id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cl->niveau . ' ' . $cl->nom, ENT_QUOTES) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex-1 min-w-36">
            <select name="mode" class="form-select">
                <option value="">— Mode —</option>
                <?php foreach ($modes as $k => $m): ?>
                <option value="<?= $k ?>" <?= ($filters['mode'] ?? '') === $k ? 'selected' : '' ?>>
                    <?= htmlspecialchars($m['label'], ENT_QUOTES) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex-1 min-w-32">
            <select name="annee" class="form-select">
                <option value="">— Année —</option>
                <?php foreach ($anneesOptions as $a): ?>
                <option value="<?= $a ?>" <?= ($filters['annee'] ?? '') === $a ? 'selected' : '' ?>><?= $a ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex-1 min-w-36">
            <input type="date" name="date_debut" class="form-input"
                   value="<?= $filters['date_debut'] ?? '' ?>">
        </div>
        <div class="flex gap-2">
            <button class="btn btn-primary">
                <i data-lucide="search" class="w-4 h-4"></i>
            </button>
            <a href="<?= BASE_URL ?>/paiements" class="btn btn-outline">
                <i data-lucide="x" class="w-4 h-4"></i>
            </a>
        </div>
    </form>
</div>

<!-- Tableau -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-800 text-slate-100">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">Référence</th>
                    <th class="px-4 py-3 text-left font-semibold">Élève</th>
                    <th class="px-4 py-3 text-left font-semibold">Classe</th>
                    <th class="px-4 py-3 text-left font-semibold">Frais</th>
                    <th class="px-4 py-3 text-center font-semibold">Mode</th>
                    <th class="px-4 py-3 text-left font-semibold">Date</th>
                    <th class="px-4 py-3 text-right font-semibold">Montant</th>
                    <th class="px-4 py-3 text-center font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php if (empty($paiements)): ?>
            <tr>
                <td colspan="8" class="px-4 py-12 text-center text-slate-400">
                    <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
                    <p>Aucun paiement trouvé</p>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($paiements as $p): ?>
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3 font-mono text-xs text-slate-600">
                    <?= htmlspecialchars($p->reference ?? '#' . $p->id, ENT_QUOTES) ?>
                </td>
                <td class="px-4 py-3">
                    <p class="font-semibold text-slate-800"><?= htmlspecialchars($p->eleve_nom ?? '', ENT_QUOTES) ?></p>
                    <p class="text-xs text-slate-400"><?= htmlspecialchars($p->matricule ?? '', ENT_QUOTES) ?></p>
                </td>
                <td class="px-4 py-3 text-xs text-slate-500">
                    <?= htmlspecialchars(($p->classe_niveau ?? '') . ' ' . ($p->classe_nom ?? ''), ENT_QUOTES) ?>
                </td>
                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($p->frais_nom ?? 'Paiement libre', ENT_QUOTES) ?></td>
                <td class="px-4 py-3 text-center">
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-slate-100 text-slate-700">
                        <?= htmlspecialchars($modes[$p->mode_paiement]['label'] ?? $p->mode_paiement, ENT_QUOTES) ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-xs text-slate-500"><?= date('d/m/Y', strtotime($p->date_paiement)) ?></td>
                <td class="px-4 py-3 text-right font-bold text-emerald-600"><?= fmtPI((float)$p->montant) ?></td>
                <td class="px-4 py-3">
                    <div class="flex items-center justify-center gap-1">
                        <a href="<?= BASE_URL ?>/paiements/<?= $p->id ?>"
                           class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-600" title="Voir">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </a>
                        <a href="<?= BASE_URL ?>/paiements/<?= $p->id ?>/recu" target="_blank"
                           class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-600" title="Reçu">
                            <i data-lucide="receipt" class="w-4 h-4"></i>
                        </a>
                        <?php if ($canEdit): ?>
                        <button type="button"
                                onclick="openDelModal('<?= htmlspecialchars($p->reference ?? '#'.$p->id, ENT_QUOTES) ?>', '<?= BASE_URL ?>/paiements/<?= $p->id ?>/delete')"
                                class="p-1.5 rounded-lg border border-red-200 hover:bg-red-50 text-red-500" title="Supprimer">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            <?php if (!empty($paiements)): ?>
            <tfoot class="bg-slate-50 border-t border-slate-200 font-semibold text-sm">
                <tr>
                    <td colspan="6" class="px-4 py-3 text-right text-slate-600">Total :</td>
                    <td class="px-4 py-3 text-right text-emerald-600"><?= fmtPI((float)$totalMontant) ?></td>
                    <td></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>

    <!-- Pagination -->
    <?php if (!empty($pagination) && $pagination['last_page'] > 1): ?>
    <div class="flex items-center justify-between px-5 py-3 border-t border-slate-200">
        <span class="text-sm text-slate-500">
            Page <?= $pagination['current_page'] ?> / <?= $pagination['last_page'] ?>
        </span>
        <nav class="flex items-center gap-1">
            <?php if ($pagination['current_page'] > 1): ?>
            <a href="<?= $buildUrl(['page' => $pagination['current_page'] - 1]) ?>"
               class="px-3 py-1.5 text-sm rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-600">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
            </a>
            <?php endif; ?>
            <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['last_page'], $pagination['current_page'] + 2); $i++): ?>
            <a href="<?= $buildUrl(['page' => $i]) ?>"
               class="px-3 py-1.5 text-sm rounded-lg <?= $i === $pagination['current_page'] ? 'bg-violet-600 text-white' : 'border border-slate-200 hover:bg-slate-50 text-slate-600' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
            <?php if ($pagination['current_page'] < $pagination['last_page']): ?>
            <a href="<?= $buildUrl(['page' => $pagination['current_page'] + 1]) ?>"
               class="px-3 py-1.5 text-sm rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-600">
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </a>
            <?php endif; ?>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php if ($canEdit): ?>
<!-- Modal suppression -->
<div class="modal-overlay" id="delModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Supprimer le paiement</h3>
            <button class="modal-close" onclick="closeModal('delModal')">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div class="modal-body">
            <p class="text-sm text-slate-500">
                Êtes-vous sûr de vouloir supprimer <strong id="delRef"></strong> ?
            </p>
        </div>
        <div class="modal-footer">
            <form id="delForm" method="POST">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                <div class="flex items-center justify-end gap-2">
                    <button type="button" class="btn btn-outline" onclick="closeModal('delModal')">Annuler</button>
                    <button type="submit" class="btn bg-red-600 text-white hover:bg-red-700 border-red-600">Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
function openDelModal(ref, url) {
    document.getElementById('delRef').textContent = ref;
    document.getElementById('delForm').action = url;
    openModal('delModal');
}
</script>
<?php endif; ?>
