<?php
/** @var array $result @var object $filters @var array $stats */
$user = \Core\Session::getUser();
function fPermV2(array $u, string $p): bool {
    $perms = $u['permissions'] ?? [];
    return in_array($p, (array)$perms, true) || in_array('*', (array)$perms, true);
}
$canManage = fPermV2($user, 'familles.manage');
?>
    <div class="max-w-7xl mx-auto">

        <!-- En-tête -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900"><?= htmlspecialchars($title) ?></h1>
                <p class="text-sm text-slate-500 mt-1">Responsables légaux et contacts familiaux</p>
            </div>
            <?php if ($canManage): ?>
            <a href="<?= BASE_URL ?>/v2/scolarite/familles/create"
               class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                <i data-lucide="plus" class="w-4 h-4"></i> Nouvelle famille
            </a>
            <?php endif; ?>
        </div>

        <!-- Flash -->
        <?php foreach (['success','error','warning','info'] as $t): $msg = \Core\Session::getFlash($t); ?>
        <?php if ($msg): ?>
        <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium
            <?= $t==='success'?'bg-green-50 text-green-800 border border-green-200':
               ($t==='error'  ?'bg-red-50 text-red-800 border border-red-200':
               ($t==='warning'?'bg-amber-50 text-amber-800 border border-amber-200':
                               'bg-blue-50 text-blue-800 border border-blue-200')) ?>">
            <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; endforeach; ?>

        <!-- Stats -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <?php $cards = [
                ['label'=>'Total',       'val'=>$stats['total'],       'icon'=>'users',       'color'=>'violet'],
                ['label'=>'Actives',      'val'=>$stats['actives'],     'icon'=>'check-circle','color'=>'green'],
                ['label'=>'Archivées',    'val'=>$stats['archivees'],   'icon'=>'archive',     'color'=>'slate'],
                ['label'=>'Avec élèves',  'val'=>$stats['avec_eleves'], 'icon'=>'link',        'color'=>'blue'],
            ];
            $colorMap = [
                'violet'=>'bg-violet-50 text-violet-700',
                'green' =>'bg-green-50 text-green-700',
                'slate' =>'bg-slate-100 text-slate-600',
                'blue'  =>'bg-blue-50 text-blue-700',
            ];
            foreach ($cards as $c): ?>
            <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-center gap-3">
                <div class="p-2 rounded-lg <?= $colorMap[$c['color']] ?>">
                    <i data-lucide="<?= $c['icon'] ?>" class="w-5 h-5"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-slate-900"><?= $c['val'] ?></p>
                    <p class="text-xs text-slate-500"><?= $c['label'] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Filtres -->
        <form method="GET" class="bg-white border border-slate-200 rounded-xl p-4 mb-6 flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-slate-600 mb-1">Recherche</label>
                <div class="relative">
                    <i data-lucide="search" class="absolute left-3 top-2.5 w-4 h-4 text-slate-400"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($filters->q) ?>"
                           placeholder="Nom, téléphone, email, ville…"
                           class="w-full pl-9 pr-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-violet-500 outline-none">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Statut</label>
                <select name="actif" class="text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500 outline-none">
                    <option value=""  <?= $filters->actif==='' ?'selected':'' ?>>Tous</option>
                    <option value="1" <?= $filters->actif==='1'?'selected':'' ?>>Actives</option>
                    <option value="0" <?= $filters->actif==='0'?'selected':'' ?>>Archivées</option>
                </select>
            </div>
            <button type="submit"
                    class="bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                Filtrer
            </button>
            <a href="<?= BASE_URL ?>/v2/scolarite/familles"
               class="text-sm text-slate-500 hover:text-slate-700 px-3 py-2 rounded-lg border border-slate-200 transition">
                Réinitialiser
            </a>
        </form>

        <!-- Tableau -->
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <?php if (empty($result['data'])): ?>
            <div class="py-16 text-center text-slate-400">
                <i data-lucide="users" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
                <p class="font-medium">Aucune famille trouvée</p>
                <p class="text-sm mt-1">Modifiez vos filtres ou créez une nouvelle famille.</p>
            </div>
            <?php else: ?>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600">Famille</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 hidden md:table-cell">Contact</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 hidden lg:table-cell">Ville</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-600">Élèves</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-600">Statut</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($result['data'] as $f): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-4 py-3">
                            <a href="<?= BASE_URL ?>/v2/scolarite/familles/<?= $f->id ?>"
                               class="font-semibold text-violet-700 hover:text-violet-900">
                                <?= htmlspecialchars($f->nom) ?>
                            </a>
                            <?php if ($f->email): ?>
                            <p class="text-xs text-slate-400"><?= htmlspecialchars($f->email) ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 hidden md:table-cell text-slate-600">
                            <?= htmlspecialchars($f->telephone ?? '—') ?>
                        </td>
                        <td class="px-4 py-3 hidden lg:table-cell text-slate-500">
                            <?= htmlspecialchars(trim(($f->code_postal ?? '') . ' ' . ($f->ville ?? '')) ?: '—') ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                                <?= (int)$f->nb_eleves > 0 ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-500' ?>">
                                <i data-lucide="user" class="w-3 h-3"></i>
                                <?= (int)$f->nb_eleves ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <?php if ($f->actif): ?>
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
                                <a href="<?= BASE_URL ?>/v2/scolarite/familles/<?= $f->id ?>"
                                   class="text-slate-400 hover:text-violet-600 transition" title="Voir">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </a>
                                <?php if ($canManage): ?>
                                <a href="<?= BASE_URL ?>/v2/scolarite/familles/<?= $f->id ?>/edit"
                                   class="text-slate-400 hover:text-blue-600 transition" title="Modifier">
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </a>
                                <?php if ($f->actif): ?>
                                <button type="button"
                                        onclick="confirmArchive(<?= $f->id ?>, '<?= htmlspecialchars(addslashes($f->nom)) ?>')"
                                        class="text-slate-400 hover:text-amber-600 transition" title="Archiver">
                                    <i data-lucide="archive" class="w-4 h-4"></i>
                                </button>
                                <?php endif; ?>
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
            <span><?= $result['total'] ?> famille<?= $result['total']>1?'s':'' ?> — page <?= $result['page'] ?>/<?= $result['total_pages'] ?></span>
            <div class="flex gap-1">
                <?php for ($p = 1; $p <= $result['total_pages']; $p++): ?>
                <a href="?q=<?= urlencode($filters->q) ?>&actif=<?= urlencode($filters->actif) ?>&page=<?= $p ?>"
                   class="px-3 py-1 rounded border <?= $p===$result['page']?'bg-violet-600 text-white border-violet-600':'border-slate-300 hover:border-violet-400' ?> transition">
                    <?= $p ?>
                </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
<!-- Modal archivage -->
<div id="archiveModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-xl shadow-xl p-6 max-w-sm w-full mx-4">
        <h3 class="font-bold text-lg text-slate-900 mb-2">Archiver la famille</h3>
        <p class="text-sm text-slate-600 mb-4">
            Voulez-vous archiver la famille <strong id="archiveNom"></strong> ?
            Elle ne sera plus visible dans les listes actives.
        </p>
        <div class="flex gap-3 justify-end">
            <button onclick="document.getElementById('archiveModal').classList.add('hidden')"
                    class="px-4 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50">Annuler</button>
            <form id="archiveForm" method="POST">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                <button type="submit"
                        class="px-4 py-2 text-sm bg-amber-600 hover:bg-amber-700 text-white rounded-lg">
                    Archiver
                </button>
            </form>
        </div>
    </div>
</div>
<script>
function confirmArchive(id, nom) {
    document.getElementById('archiveNom').textContent = nom;
    document.getElementById('archiveForm').action = '<?= BASE_URL ?>/v2/scolarite/familles/' + id + '/archiver';
    document.getElementById('archiveModal').classList.remove('hidden');
}
</script>
