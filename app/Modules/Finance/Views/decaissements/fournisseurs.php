<?php
/**
 * Finance V2 — Fournisseurs
 * GET /v2/finance/fournisseurs
 */
$result = $result ?? ['data' => [], 'total' => 0, 'total_pages' => 1, 'page' => 1];
$q      = $q      ?? '';
?>
<div class="space-y-6">

    <!-- En-tête -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="<?= BASE_URL ?>/v2/finance/decaissements" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Fournisseurs</h1>
                <p class="text-sm text-slate-500 mt-1"><?= $result['total'] ?> fournisseur(s) au total</p>
            </div>
        </div>
        <button onclick="ouvrirCreation()" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i> Nouveau fournisseur
        </button>
    </div>

    <!-- Recherche -->
    <form method="GET" class="bg-white rounded-xl border border-slate-200 p-4 flex gap-3">
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Rechercher un fournisseur..."
               class="form-input flex-1">
        <button type="submit" class="btn btn-primary">
            <i data-lucide="search" class="w-4 h-4"></i> Rechercher
        </button>
    </form>

    <!-- Flash messages -->
    <?php if ($flash = \Core\Session::getFlash('success')): ?>
    <div class="flex items-center gap-3 p-4 bg-emerald-50 text-emerald-800 rounded-xl border border-emerald-200">
        <i data-lucide="check-circle" class="w-5 h-5"></i>
        <span><?= htmlspecialchars($flash) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($flash = \Core\Session::getFlash('error')): ?>
    <div class="flex items-center gap-3 p-4 bg-red-50 text-red-800 rounded-xl border border-red-200">
        <i data-lucide="alert-circle" class="w-5 h-5"></i>
        <span><?= htmlspecialchars($flash) ?></span>
    </div>
    <?php endif; ?>

    <!-- Tableau -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200">
                    <th class="px-4 py-3 text-left font-medium text-slate-600">Code / Nom</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-600">Contact</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-600">Téléphone / Email</th>
                    <th class="px-4 py-3 text-center font-medium text-slate-600">Décaissements</th>
                    <th class="px-4 py-3 text-center font-medium text-slate-600">Statut</th>
                    <th class="px-4 py-3 text-center font-medium text-slate-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($result['data'])): ?>
                <tr>
                    <td colspan="6" class="px-4 py-12 text-center text-slate-400">
                        <i data-lucide="truck" class="w-10 h-10 mx-auto mb-3 opacity-40"></i>
                        <p>Aucun fournisseur enregistré.</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($result['data'] as $f): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-4 py-3">
                        <div class="font-medium text-slate-800"><?= htmlspecialchars($f->nom) ?></div>
                        <div class="text-xs text-slate-400 font-mono"><?= htmlspecialchars($f->code) ?></div>
                    </td>
                    <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($f->contact ?? '—') ?></td>
                    <td class="px-4 py-3 text-slate-600">
                        <?= htmlspecialchars($f->telephone ?? '—') ?>
                        <?php if ($f->email): ?><div class="text-xs text-slate-400"><?= htmlspecialchars($f->email) ?></div><?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center text-slate-600"><?= (int)$f->nb_decaissements ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $f->actif ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                            <?= $f->actif ? 'Actif' : 'Inactif' ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <div class="flex items-center justify-center gap-1">
                            <button onclick='ouvrirEdition(<?= json_encode([
                                "id" => $f->id, "code" => $f->code, "nom" => $f->nom, "contact" => $f->contact,
                                "telephone" => $f->telephone, "email" => $f->email, "adresse" => $f->adresse, "iban" => $f->iban,
                            ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                    class="p-1.5 text-slate-400 hover:text-violet-600 rounded" title="Modifier">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                            </button>
                            <form method="POST" action="<?= BASE_URL ?>/v2/finance/fournisseurs/<?= $f->id ?>/toggle" class="inline">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                                <button type="submit"
                                        class="p-1.5 text-slate-400 hover:text-<?= $f->actif ? 'amber' : 'emerald' ?>-600 rounded"
                                        title="<?= $f->actif ? 'Désactiver' : 'Activer' ?>">
                                    <i data-lucide="<?= $f->actif ? 'pause' : 'play' ?>" class="w-4 h-4"></i>
                                </button>
                            </form>
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
                   class="px-3 py-1 text-sm rounded border border-slate-200 hover:bg-slate-50">←</a>
                <?php endif; ?>
                <?php if ($result['page'] < $result['total_pages']): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $result['page'] + 1])) ?>"
                   class="px-3 py-1 text-sm rounded border border-slate-200 hover:bg-slate-50">→</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal création/édition fournisseur -->
<div id="modal-frs" class="hidden fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6">
        <h3 id="modal-frs-title" class="text-lg font-bold text-slate-800 mb-4">Nouveau fournisseur</h3>
        <form method="POST" id="form-frs" action="<?= BASE_URL ?>/v2/finance/fournisseurs" class="space-y-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Code <span class="form-required">*</span></label>
                    <input type="text" name="code" id="frs-code" required placeholder="EX: FRS001"
                           class="form-input font-mono">
                </div>
                <div>
                    <label class="form-label">Nom <span class="form-required">*</span></label>
                    <input type="text" name="nom" id="frs-nom" required
                           class="form-input">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Contact</label>
                    <input type="text" name="contact" id="frs-contact"
                           class="form-input">
                </div>
                <div>
                    <label class="form-label">Téléphone</label>
                    <input type="text" name="telephone" id="frs-telephone"
                           class="form-input">
                </div>
            </div>

            <div>
                <label class="form-label">Email</label>
                <input type="email" name="email" id="frs-email"
                       class="form-input">
            </div>

            <div>
                <label class="form-label">Adresse</label>
                <input type="text" name="adresse" id="frs-adresse"
                       class="form-input">
            </div>

            <div>
                <label class="form-label">IBAN</label>
                <input type="text" name="iban" id="frs-iban"
                       class="form-input font-mono">
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modal-frs').classList.add('hidden')"
                        class="btn btn-secondary">Annuler</button>
                <button type="submit" id="modal-frs-submit" class="btn btn-primary">
                    Créer le fournisseur
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function ouvrirCreation() {
    document.getElementById('modal-frs-title').textContent = 'Nouveau fournisseur';
    document.getElementById('modal-frs-submit').textContent = 'Créer le fournisseur';
    document.getElementById('form-frs').action = '<?= BASE_URL ?>/v2/finance/fournisseurs';
    ['code','nom','contact','telephone','email','adresse','iban'].forEach(f => document.getElementById('frs-' + f).value = '');
    document.getElementById('modal-frs').classList.remove('hidden');
}
function ouvrirEdition(f) {
    document.getElementById('modal-frs-title').textContent = 'Modifier le fournisseur';
    document.getElementById('modal-frs-submit').textContent = 'Enregistrer';
    document.getElementById('form-frs').action = '<?= BASE_URL ?>/v2/finance/fournisseurs/' + f.id;
    document.getElementById('frs-code').value = f.code || '';
    document.getElementById('frs-nom').value = f.nom || '';
    document.getElementById('frs-contact').value = f.contact || '';
    document.getElementById('frs-telephone').value = f.telephone || '';
    document.getElementById('frs-email').value = f.email || '';
    document.getElementById('frs-adresse').value = f.adresse || '';
    document.getElementById('frs-iban').value = f.iban || '';
    document.getElementById('modal-frs').classList.remove('hidden');
}
</script>
