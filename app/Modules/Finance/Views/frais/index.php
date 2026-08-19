<?php
/**
 * Finance V2 — Référentiel des frais
 * GET /v2/finance/frais
 */
?>
<div class="space-y-6">

    <!-- En-tête -->
    <div class="flex items-center gap-2 text-sm text-slate-500">
        <a href="<?= BASE_URL ?>/v2/finance/rapports/dashboard" class="hover:text-violet-600">Finance</a>
        <i data-lucide="chevron-right" class="w-3 h-3"></i>
        <span class="text-slate-700">Référentiel des frais</span>
    </div>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-start gap-4">
            <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
                <i data-lucide="list-checks" class="w-5 h-5 text-violet-600"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Référentiel des frais</h1>
                <p class="text-sm text-slate-500 mt-0.5">Catégories, types, tarifs et statuts des frais scolaires</p>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap flex-shrink-0">
            <a href="<?= BASE_URL ?>/v2/finance/frais/categories"
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors">
                <i data-lucide="tag" class="w-4 h-4"></i> Catégories
            </a>
            <?php if ($canCreate): ?>
            <a href="<?= BASE_URL ?>/v2/finance/frais/create"
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-violet-600 rounded-lg hover:bg-violet-700 transition-colors">
                <i data-lucide="plus" class="w-4 h-4"></i> Nouveau frais
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cartes stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center mb-2">
                <i data-lucide="list-checks" class="w-4 h-4 text-slate-600"></i>
            </div>
            <div class="text-2xl font-bold text-slate-800"><?= $stats['total'] ?></div>
            <div class="text-xs text-slate-500 mt-1">Total types de frais</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center mb-2">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
            </div>
            <div class="text-2xl font-bold text-emerald-600"><?= $stats['actifs'] ?></div>
            <div class="text-xs text-slate-500 mt-1">Actifs</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center mb-2">
                <i data-lucide="star" class="w-4 h-4 text-amber-500"></i>
            </div>
            <div class="text-2xl font-bold text-amber-500"><?= $stats['obligatoires'] ?></div>
            <div class="text-xs text-slate-500 mt-1">Obligatoires</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="w-8 h-8 rounded-lg bg-violet-100 flex items-center justify-center mb-2">
                <i data-lucide="tag" class="w-4 h-4 text-violet-600"></i>
            </div>
            <div class="text-2xl font-bold text-violet-600"><?= $stats['nb_categories'] ?></div>
            <div class="text-xs text-slate-500 mt-1">Catégories actives</div>
        </div>
    </div>

    <!-- Filtres -->
    <form method="GET" class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <input type="text" name="q" value="<?= htmlspecialchars($filters->q) ?>"
                   placeholder="Rechercher..."
                   class="form-input">

            <select name="statut" class="form-select">
                <option value="">Tous les statuts</option>
                <option value="actif"    <?= $filters->statut === 'actif'    ? 'selected' : '' ?>>Actifs</option>
                <option value="inactif"  <?= $filters->statut === 'inactif'  ? 'selected' : '' ?>>Inactifs</option>
                <option value="archive"  <?= $filters->statut === 'archive'  ? 'selected' : '' ?>>Archivés</option>
            </select>

            <select name="periodicite" class="form-select">
                <option value="">Toutes périodicités</option>
                <?php foreach ($periodicites as $val => $label): ?>
                <option value="<?= $val ?>" <?= $filters->periodicite === $val ? 'selected' : '' ?>>
                    <?= $label ?>
                </option>
                <?php endforeach; ?>
            </select>

            <select name="categorie_id" class="form-select">
                <option value="">Toutes catégories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat->id ?>" <?= (int)$filters->categorieId === (int)$cat->id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat->nom) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-center gap-2 mt-3">
            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-violet-600 rounded-lg hover:bg-violet-700">
                <i data-lucide="search" class="inline w-4 h-4 mr-1"></i> Filtrer
            </button>
            <a href="<?= BASE_URL ?>/v2/finance/frais" class="px-4 py-2 text-sm text-slate-500 hover:text-slate-700">Réinitialiser</a>
        </div>
    </form>

    <!-- Flash messages -->
    <?php if ($flash = \Core\Session::getFlash('success')): ?>
    <div class="flex items-center gap-3 p-4 bg-emerald-50 text-emerald-800 rounded-xl border border-emerald-200">
        <i data-lucide="check-circle" class="w-5 h-5 flex-shrink-0"></i>
        <span><?= htmlspecialchars($flash) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($flash = \Core\Session::getFlash('error')): ?>
    <div class="flex items-center gap-3 p-4 bg-red-50 text-red-800 rounded-xl border border-red-200">
        <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0"></i>
        <span><?= htmlspecialchars($flash) ?></span>
    </div>
    <?php endif; ?>

    <!-- Tableau -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200">
                    <th class="px-4 py-3 text-left font-medium text-slate-600">Code / Nom</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-600">Catégorie</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-600">Montant défaut</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-600">Périodicité</th>
                    <th class="px-4 py-3 text-center font-medium text-slate-600">Obligatoire</th>
                    <th class="px-4 py-3 text-center font-medium text-slate-600">Statut</th>
                    <th class="px-4 py-3 text-center font-medium text-slate-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($result['data'])): ?>
                <tr>
                    <td colspan="7" class="py-14">
                        <div class="flex flex-col items-center gap-2 text-slate-400">
                            <div class="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center">
                                <i data-lucide="list-checks" class="w-5 h-5"></i>
                            </div>
                            <p class="text-sm">Aucun type de frais trouvé.</p>
                            <?php if ($canCreate): ?>
                            <a href="<?= BASE_URL ?>/v2/finance/frais/create"
                               class="inline-flex items-center gap-1 mt-2 text-violet-600 hover:text-violet-800 text-sm font-medium">
                                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Créer le premier frais
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($result['data'] as $frais): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-4 py-3">
                        <a href="<?= BASE_URL ?>/v2/finance/frais/<?= $frais->id ?>"
                           class="font-medium text-slate-800 hover:text-violet-700">
                            <?= htmlspecialchars($frais->nom) ?>
                        </a>
                        <div class="text-xs text-slate-400 font-mono"><?= htmlspecialchars($frais->code) ?></div>
                        <?php if ($frais->annee_scolaire): ?>
                        <div class="text-xs text-slate-400"><?= htmlspecialchars($frais->annee_scolaire) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <?php if ($frais->categorie_nom): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium text-white"
                              style="background-color:<?= htmlspecialchars($frais->categorie_couleur ?? '#6366f1') ?>">
                            <?= htmlspecialchars($frais->categorie_nom) ?>
                        </span>
                        <?php else: ?>
                        <span class="text-slate-400">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 font-mono text-right text-slate-700">
                        <?= number_format($frais->montant_defaut, 0, ',', ' ') ?>
                        <span class="text-xs text-slate-400"><?= htmlspecialchars($frais->devise) ?></span>
                    </td>
                    <td class="px-4 py-3 text-slate-600">
                        <?= \App\Modules\Finance\DTO\FraisTypeDTO::PERIODICITES[$frais->periodicite] ?? $frais->periodicite ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($frais->est_obligatoire): ?>
                        <span class="inline-block w-5 h-5 rounded-full bg-amber-400 text-white text-xs flex items-center justify-center" title="Obligatoire">✓</span>
                        <?php else: ?>
                        <span class="inline-block w-5 h-5 rounded-full bg-slate-200 text-slate-400 text-xs flex items-center justify-center" title="Optionnel">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <?php
                        $badgeClass = match($frais->statut) {
                            'actif'   => 'bg-emerald-100 text-emerald-700',
                            'inactif' => 'bg-amber-100 text-amber-700',
                            'archive' => 'bg-slate-100 text-slate-500',
                            default   => 'bg-slate-100 text-slate-500',
                        };
                        $badgeLabel = match($frais->statut) {
                            'actif'   => 'Actif',
                            'inactif' => 'Inactif',
                            'archive' => 'Archivé',
                            default   => $frais->statut,
                        };
                        ?>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $badgeClass ?>">
                            <?= $badgeLabel ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <div class="flex items-center justify-center gap-1">
<a href="<?= BASE_URL ?>/v2/finance/frais/<?= $frais->id ?>"
                               class="p-1.5 text-slate-400 hover:text-violet-600 hover:bg-violet-50 rounded transition" title="Détail">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </a>
                            <?php if ($canUpdate && $frais->statut !== 'archive'): ?>
                            <a href="<?= BASE_URL ?>/v2/finance/frais/<?= $frais->id ?>/edit"
                               class="p-1.5 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded transition" title="Modifier">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($canUpdate && $frais->statut === 'actif'): ?>
                            <button onclick="document.getElementById('form-desactiver-<?= $frais->id ?>').submit()"
                                    class="p-1.5 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded transition" title="Désactiver">
                                <i data-lucide="pause-circle" class="w-4 h-4"></i>
                            </button>
                            <form id="form-desactiver-<?= $frais->id ?>" method="POST" action="<?= BASE_URL ?>/v2/finance/frais/<?= $frais->id ?>/desactiver" class="hidden">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                            </form>
                            <?php elseif ($canUpdate && $frais->statut === 'inactif'): ?>
                            <button onclick="document.getElementById('form-activer-<?= $frais->id ?>').submit()"
                                    class="p-1.5 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded transition" title="Activer">
                                <i data-lucide="play-circle" class="w-4 h-4"></i>
                            </button>
                            <form id="form-activer-<?= $frais->id ?>" method="POST" action="<?= BASE_URL ?>/v2/finance/frais/<?= $frais->id ?>/activer" class="hidden">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                            </form>
                            <?php endif; ?>
                            <?php if ($canArchive && $frais->statut !== 'archive'): ?>
                            <button onclick="archiverFrais(<?= $frais->id ?>)"
                                    class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded transition" title="Archiver">
                                <i data-lucide="archive" class="w-4 h-4"></i>
                            </button>
                            <?php endif; ?>
                            <?php if ($canDelete && $frais->statut !== 'actif'): ?>
                            <button onclick="if(confirm('Supprimer définitivement ce frais ?')) document.getElementById('form-delete-<?= $frais->id ?>').submit()"
                                    class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded transition" title="Supprimer définitivement">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                            <form id="form-delete-<?= $frais->id ?>" method="POST" action="<?= BASE_URL ?>/v2/finance/frais/<?= $frais->id ?>/delete" class="hidden">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($result['total_pages'] > 1): ?>
        <div class="px-4 py-3 border-t border-slate-100 flex items-center justify-between">
            <span class="text-sm text-slate-500">
                <?= $result['total'] ?> résultat(s) — page <?= $result['page'] ?> / <?= $result['total_pages'] ?>
            </span>
            <div class="flex gap-1">
                <?php if ($result['page'] > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $result['page'] - 1])) ?>"
                   class="px-3 py-1 text-sm rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                </a>
                <?php endif; ?>
                <?php if ($result['page'] < $result['total_pages']): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $result['page'] + 1])) ?>"
                   class="px-3 py-1 text-sm rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal archivage -->
<div id="modal-archiver" class="hidden fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Archiver ce frais</h3>
        <p class="text-sm text-slate-600 mb-4">
            Le frais archivé restera consultable dans l'historique mais ne sera plus
            utilisable pour de nouvelles factures.
        </p>
        <form method="POST" id="form-archiver">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
            <div class="mb-4">
                <label class="form-label">Motif d'archivage *</label>
                <textarea name="motif" rows="3" required
                          class="form-textarea"
                          placeholder="Ex: Frais remplacé par un nouveau tarif 2026-2027"></textarea>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-archiver').classList.add('hidden')"
                        class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">
                    Annuler
                </button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-amber-600 rounded-lg hover:bg-amber-700">
                    Archiver
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function archiverFrais(id) {
    document.getElementById('form-archiver').action = `<?= BASE_URL ?>/v2/finance/frais/${id}/archiver`;
    document.getElementById('modal-archiver').classList.remove('hidden');
}
</script>
