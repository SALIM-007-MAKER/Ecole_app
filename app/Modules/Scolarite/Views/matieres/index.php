<?php
/** @var array $result @var object $filters @var array $stats @var array $filieres @var array $niveaux @var bool $canCreate @var bool $canUpdate @var bool $canDelete */
$allNiveaux = [];
foreach ($niveaux as $groupe => $liste) {
    foreach ($liste as $n) $allNiveaux[] = $n;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <?php include BASE_PATH . '/app/Views/layouts/head_assets.php'; ?>
</head>
<body class="bg-slate-50 text-slate-800">
<?php include BASE_PATH . '/app/Views/layouts/sidebar.php'; ?>

<main class="ml-64 p-6 min-h-screen">
    <div class="max-w-7xl mx-auto">

        <!-- En-tête -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900"><?= htmlspecialchars($title) ?></h1>
                <p class="text-sm text-slate-500 mt-1">Matières, coefficients, volumes horaires, filières</p>
            </div>
            <?php if ($canCreate): ?>
            <a href="<?= BASE_URL ?>/v2/scolarite/matieres/create"
               class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                <i data-lucide="plus" class="w-4 h-4"></i> Nouvelle matière
            </a>
            <?php endif; ?>
        </div>

        <!-- Flash -->
        <?php foreach (['success','error','warning'] as $t): $msg = \Core\Session::getFlash($t); ?>
        <?php if ($msg): ?>
        <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium
            <?= $t==='success'?'bg-green-50 text-green-800 border border-green-200':
               ($t==='error'  ?'bg-red-50 text-red-800 border border-red-200':
                               'bg-amber-50 text-amber-800 border border-amber-200') ?>">
            <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; endforeach; ?>

        <!-- Stats -->
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
            <?php $cards = [
                ['label'=>'Total',         'val'=>$stats['total'],           'icon'=>'book',      'color'=>'emerald', 'fmt'=>false],
                ['label'=>'Actives',        'val'=>$stats['actives'],         'icon'=>'check',     'color'=>'green',   'fmt'=>false],
                ['label'=>'Archivées',      'val'=>$stats['archivees'],       'icon'=>'archive',   'color'=>'slate',   'fmt'=>false],
                ['label'=>'Coef. total',    'val'=>$stats['total_coef'],      'icon'=>'hash',      'color'=>'violet',  'fmt'=>true],
                ['label'=>'Heures/sem',     'val'=>$stats['total_heures'],    'icon'=>'clock',     'color'=>'blue',    'fmt'=>false],
            ];
            $cm = ['emerald'=>'bg-emerald-50 text-emerald-700','green'=>'bg-green-50 text-green-700',
                   'slate'=>'bg-slate-100 text-slate-600','violet'=>'bg-violet-50 text-violet-700',
                   'blue'=>'bg-blue-50 text-blue-700'];
            foreach ($cards as $c): ?>
            <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-center gap-3">
                <div class="p-2 rounded-lg <?= $cm[$c['color']] ?>">
                    <i data-lucide="<?= $c['icon'] ?>" class="w-5 h-5"></i>
                </div>
                <div>
                    <p class="text-xl font-bold text-slate-900">
                        <?= $c['fmt'] ? number_format((float)$c['val'], 1) : $c['val'] ?>
                    </p>
                    <p class="text-xs text-slate-500"><?= $c['label'] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Filtres -->
        <form method="GET" class="bg-white border border-slate-200 rounded-xl p-4 mb-6 flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[180px]">
                <label class="block text-xs font-medium text-slate-600 mb-1">Recherche</label>
                <div class="relative">
                    <i data-lucide="search" class="absolute left-3 top-2.5 w-4 h-4 text-slate-400"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($filters->q) ?>"
                           placeholder="Nom, description…"
                           class="w-full pl-9 pr-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Statut</label>
                <select name="actif" class="text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 outline-none">
                    <option value=""  <?= $filters->actif==='' ?'selected':'' ?>>Tous</option>
                    <option value="1" <?= $filters->actif==='1'?'selected':'' ?>>Actives</option>
                    <option value="0" <?= $filters->actif==='0'?'selected':'' ?>>Archivées</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Filière</label>
                <select name="filiere" class="text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 outline-none">
                    <option value="">Toutes</option>
                    <?php foreach ($filieres as $f): ?>
                    <option value="<?= htmlspecialchars($f) ?>" <?= $filters->filiere===$f?'selected':'' ?>>
                        <?= htmlspecialchars($f) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Niveau</label>
                <select name="niveau" class="text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 outline-none">
                    <option value="">Tous</option>
                    <?php foreach ($niveaux as $groupe => $liste): ?>
                    <optgroup label="<?= htmlspecialchars($groupe) ?>">
                        <?php foreach ($liste as $n): ?>
                        <option value="<?= $n ?>" <?= $filters->niveau===$n?'selected':'' ?>><?= $n ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                Filtrer
            </button>
            <a href="<?= BASE_URL ?>/v2/scolarite/matieres"
               class="text-sm text-slate-500 hover:text-slate-700 px-3 py-2 rounded-lg border border-slate-200 transition">
                Reset
            </a>
        </form>

        <!-- Tableau -->
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <?php if (empty($result['data'])): ?>
            <div class="py-16 text-center text-slate-400">
                <i data-lucide="book-open" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
                <p class="font-medium">Aucune matière trouvée</p>
                <p class="text-sm mt-1">Modifiez vos filtres ou créez une nouvelle matière.</p>
            </div>
            <?php else: ?>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600">Matière</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-600">Coef.</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-600 hidden md:table-cell">H/sem</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 hidden lg:table-cell">Niveaux</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 hidden xl:table-cell">Filière</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-600">Cours</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-600">Statut</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($result['data'] as $m):
                        $nvsArr = $m->niveaux ? array_filter(explode(',', $m->niveaux)) : [];
                        $dotColor = $m->couleur ?: '#6366F1';
                    ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="w-3 h-3 rounded-full shrink-0" style="background:<?= htmlspecialchars($dotColor) ?>"></span>
                                <div>
                                    <a href="<?= BASE_URL ?>/v2/scolarite/matieres/<?= $m->id ?>"
                                       class="font-semibold text-slate-900 hover:text-emerald-700">
                                        <?= htmlspecialchars($m->nom) ?>
                                    </a>
                                    <?php if ($m->responsable_nom && trim($m->responsable_nom) !== ' '): ?>
                                    <p class="text-xs text-slate-400"><?= htmlspecialchars($m->responsable_nom) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-violet-100 text-violet-700">
                                ×<?= number_format((float)$m->coefficient, 1) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center hidden md:table-cell">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-700">
                                <?= $m->volume_horaire ?>h
                            </span>
                        </td>
                        <td class="px-4 py-3 hidden lg:table-cell">
                            <div class="flex flex-wrap gap-1">
                                <?php foreach (array_slice($nvsArr, 0, 4) as $nv): ?>
                                <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600"><?= $nv ?></span>
                                <?php endforeach; ?>
                                <?php if (count($nvsArr) > 4): ?>
                                <span class="text-xs text-slate-400">+<?= count($nvsArr)-4 ?></span>
                                <?php endif; ?>
                                <?php if (empty($nvsArr)): ?>
                                <span class="text-xs text-slate-300">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 hidden xl:table-cell text-slate-500 text-xs">
                            <?= $m->filiere ? htmlspecialchars($m->filiere) : '—' ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                                <?= (int)$m->nb_enseignements > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                                <i data-lucide="users" class="w-3 h-3"></i> <?= (int)$m->nb_enseignements ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <?php if ($m->actif): ?>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                <i data-lucide="check-circle" class="w-3 h-3"></i> Active
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-500">
                                <i data-lucide="archive" class="w-3 h-3"></i> Archivée
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="<?= BASE_URL ?>/v2/scolarite/matieres/<?= $m->id ?>"
                                   class="text-slate-400 hover:text-emerald-600 transition" title="Voir">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </a>
                                <?php if ($canUpdate): ?>
                                <a href="<?= BASE_URL ?>/v2/scolarite/matieres/<?= $m->id ?>/edit"
                                   class="text-slate-400 hover:text-blue-600 transition" title="Modifier">
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </a>
                                <?php if ($m->actif): ?>
                                <button type="button"
                                        onclick="confirmArchive(<?= $m->id ?>, '<?= htmlspecialchars(addslashes($m->nom)) ?>', <?= (int)$m->nb_enseignements ?>)"
                                        class="text-slate-400 hover:text-amber-600 transition" title="Archiver">
                                    <i data-lucide="archive" class="w-4 h-4"></i>
                                </button>
                                <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($canDelete && !$m->nb_enseignements): ?>
                                <button type="button"
                                        onclick="confirmDelete(<?= $m->id ?>, '<?= htmlspecialchars(addslashes($m->nom)) ?>')"
                                        class="text-slate-400 hover:text-red-600 transition" title="Supprimer">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($result['total_pages'] > 1): ?>
        <div class="flex items-center justify-between mt-4 text-sm text-slate-500">
            <span><?= $result['total'] ?> matière<?= $result['total']>1?'s':'' ?> — page <?= $result['page'] ?>/<?= $result['total_pages'] ?></span>
            <div class="flex gap-1">
                <?php for ($p = 1; $p <= $result['total_pages']; $p++): ?>
                <a href="?q=<?= urlencode($filters->q) ?>&actif=<?= urlencode($filters->actif) ?>&filiere=<?= urlencode($filters->filiere) ?>&niveau=<?= urlencode($filters->niveau) ?>&page=<?= $p ?>"
                   class="px-3 py-1 rounded border <?= $p===$result['page']?'bg-emerald-600 text-white border-emerald-600':'border-slate-300 hover:border-emerald-400' ?> transition">
                    <?= $p ?>
                </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</main>

<!-- Modal archivage -->
<div id="archiveModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-xl shadow-xl p-6 max-w-sm w-full mx-4">
        <h3 class="font-bold text-lg text-slate-900 mb-2">Archiver la matière</h3>
        <p class="text-sm text-slate-600 mb-1">Archiver <strong id="archiveNom"></strong> ?</p>
        <p id="archiveWarn" class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2 mb-4 hidden">
            <i data-lucide="alert-triangle" class="w-3.5 h-3.5 inline mr-1"></i>
            Cette matière est encore utilisée dans des enseignements.
        </p>
        <div class="flex gap-3 justify-end">
            <button onclick="document.getElementById('archiveModal').classList.add('hidden')"
                    class="px-4 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50">Annuler</button>
            <form id="archiveForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit"
                        class="px-4 py-2 text-sm bg-amber-600 hover:bg-amber-700 text-white rounded-lg">Archiver</button>
            </form>
        </div>
    </div>
</div>

<!-- Modal suppression -->
<div id="deleteModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-xl shadow-xl p-6 max-w-sm w-full mx-4">
        <h3 class="font-bold text-lg text-slate-900 mb-2">Supprimer la matière</h3>
        <p class="text-sm text-slate-600 mb-4">
            Supprimer définitivement <strong id="deleteNom"></strong> ? Cette action est irréversible.
        </p>
        <div class="flex gap-3 justify-end">
            <button onclick="document.getElementById('deleteModal').classList.add('hidden')"
                    class="px-4 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50">Annuler</button>
            <form id="deleteForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit"
                        class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg">Supprimer</button>
            </form>
        </div>
    </div>
</div>

<script>
function confirmArchive(id, nom, nbEns) {
    document.getElementById('archiveNom').textContent = nom;
    document.getElementById('archiveWarn').classList.toggle('hidden', nbEns === 0);
    document.getElementById('archiveForm').action = '<?= BASE_URL ?>/v2/scolarite/matieres/' + id + '/archiver';
    document.getElementById('archiveModal').classList.remove('hidden');
}
function confirmDelete(id, nom) {
    document.getElementById('deleteNom').textContent = nom;
    document.getElementById('deleteForm').action = '<?= BASE_URL ?>/v2/scolarite/matieres/' + id + '/delete';
    document.getElementById('deleteModal').classList.remove('hidden');
}
</script>

<?php include BASE_PATH . '/app/Views/layouts/footer_assets.php'; ?>
</body>
</html>
