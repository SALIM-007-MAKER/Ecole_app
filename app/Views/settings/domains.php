<?php
/** @var array $domains */
$errors = $errors ?? [];
?>

<!-- Page header -->
<div class="flex items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="globe" class="w-5 h-5 text-violet-600"></i>
            Domaines personnalisés
        </h2>
        <p class="text-sm text-slate-500">Associez un sous-domaine ou un nom de domaine personnalisé à votre établissement</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-5" role="alert">
    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
    <ul class="flex-1 list-disc list-inside space-y-0.5">
        <?php foreach ($errors as $msg): ?>
        <li class="text-sm"><?= htmlspecialchars((string)$msg, ENT_QUOTES) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Liste des domaines -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-6">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <i data-lucide="list" class="w-4 h-4 text-violet-600"></i>Domaines enregistrés
    </div>

    <?php if (empty($domains)): ?>
    <div class="p-6 text-sm text-slate-500">Aucun domaine enregistré pour le moment.</div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-5 py-3 text-left">Domaine</th>
                    <th class="px-5 py-3 text-left">Type</th>
                    <th class="px-5 py-3 text-left">Statut</th>
                    <th class="px-5 py-3 text-left">SSL</th>
                    <th class="px-5 py-3 text-left col-actions">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($domains as $d): ?>
                <tr>
                    <td class="px-5 py-3 font-mono text-slate-800"><?= htmlspecialchars($d['domain'], ENT_QUOTES) ?></td>
                    <td class="px-5 py-3 text-slate-600"><?= $d['type'] === 'subdomain' ? 'Sous-domaine' : 'Domaine personnalisé' ?></td>
                    <td class="px-5 py-3">
                        <?php if ($d['verified']): ?>
                        <span class="badge badge-success"><i data-lucide="check" class="w-3 h-3"></i>Vérifié</span>
                        <?php else: ?>
                        <span class="badge badge-warning"><i data-lucide="clock" class="w-3 h-3"></i>En attente</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-slate-500"><?= htmlspecialchars($d['ssl_status'], ENT_QUOTES) ?></td>
                    <td class="px-5 py-3 col-actions">
                        <div class="flex items-center gap-2">
                            <?php if ($canEdit && !$d['verified']): ?>
                            <form method="POST" action="<?= BASE_URL ?>/parametres/domaines/<?= (int)$d['id'] ?>/verifier" class="inline">
                                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                                <button type="submit" class="btn btn-sm btn-secondary" title="Vérifier">
                                    <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if ($canEdit): ?>
                            <form method="POST" action="<?= BASE_URL ?>/parametres/domaines/<?= (int)$d['id'] ?>/toggle" class="inline">
                                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                                <input type="hidden" name="active" value="<?= $d['verified'] ? '0' : '1' ?>">
                                <button type="submit" class="btn btn-sm btn-secondary" title="<?= $d['verified'] ? 'Désactiver' : 'Réactiver' ?>">
                                    <i data-lucide="<?= $d['verified'] ? 'toggle-right' : 'toggle-left' ?>" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                            <form method="POST" action="<?= BASE_URL ?>/parametres/domaines/<?= (int)$d['id'] ?>/supprimer" class="inline" onsubmit="return confirm('Supprimer ce domaine ?');">
                                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                                <button type="submit" class="btn btn-sm btn-danger" title="Supprimer">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php if (!$d['verified']): ?>
                <tr class="bg-slate-50">
                    <td colspan="5" class="px-5 py-3 text-xs text-slate-600">
                        Pour vérifier ce domaine, ajoutez l'enregistrement DNS TXT suivant, puis cliquez sur "Vérifier" :
                        <div class="mt-1 font-mono text-slate-800">
                            <div>Nom : <span class="font-semibold">_edunova-verify.<?= htmlspecialchars($d['domain'], ENT_QUOTES) ?></span></div>
                            <div>Valeur : <span class="font-semibold">edunova-verify=<?= htmlspecialchars((string)$d['verification_token'], ENT_QUOTES) ?></span></div>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php if ($canEdit): ?>
<!-- Ajouter un domaine -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <i data-lucide="plus-circle" class="w-4 h-4 text-violet-600"></i>Ajouter un domaine
    </div>
    <form method="POST" action="<?= BASE_URL ?>/parametres/domaines" class="p-6 flex items-end gap-3" novalidate>
        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
        <div class="flex-1">
            <label class="form-label" for="domain">Nom de domaine</label>
            <input type="text" id="domain" name="domain" class="form-input font-mono" placeholder="ecole-exemple.com" required>
            <p class="text-xs text-slate-400 mt-1">Un enregistrement DNS TXT sera à créer pour prouver la propriété du domaine.</p>
        </div>
        <button type="submit" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i>Ajouter
        </button>
    </form>
</div>
<?php endif; ?>
