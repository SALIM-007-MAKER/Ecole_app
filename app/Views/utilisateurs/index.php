<?php
$pagination  = $pagination  ?? ['data'=>[],'total'=>0,'last_page'=>1,'current_page'=>1];
$stats       = $stats       ?? ['total'=>0];
$filters     = $filters     ?? ['search'=>'','role'=>'','actif'=>''];
$currentUser = \Core\Session::getUser();
$perms       = $currentUser['permissions'] ?? [];

function buildQ(array $base, array $override = []): string {
    $p = array_filter(array_merge($base, $override), fn($v) => $v !== '');
    return $p ? '?' . http_build_query($p) : '';
}

$f = $filters;
$roleBadge = [
    'admin'      => 'bg-red-100 text-red-700',
    'directeur'  => 'bg-violet-100 text-violet-700',
    'secretaire' => 'bg-sky-100 text-sky-700',
    'comptable'  => 'bg-emerald-100 text-emerald-700',
    'enseignant' => 'bg-amber-100 text-amber-700',
    'parent'     => 'bg-indigo-100 text-indigo-700',
    'eleve'      => 'bg-slate-100 text-slate-700',
];
?>

<!-- Header -->
<div class="flex flex-wrap items-start justify-between gap-4 mb-6">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
            <i data-lucide="users-2" class="w-5 h-5 text-violet-600"></i>
        </div>
        <div>
            <h2 class="text-xl font-bold text-slate-900">Gestion des utilisateurs</h2>
            <p class="text-sm text-slate-500 mt-0.5"><?= (int)$stats['total'] ?> compte(s) enregistré(s)</p>
        </div>
    </div>
    <?php if (in_array('users.create', $perms, true)): ?>
    <a href="<?= BASE_URL ?>/utilisateurs/create" class="btn btn-primary">
        <i data-lucide="user-plus" class="w-4 h-4"></i>Nouvel utilisateur
    </a>
    <?php endif; ?>
</div>

<!-- Stats rôles -->
<div class="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-7 gap-3 mb-6">
    <?php
    $roleIcons = [
        'admin'      => ['icon'=>'shield',      'bg'=>'bg-red-100',    'tc'=>'text-red-700'],
        'directeur'  => ['icon'=>'award',        'bg'=>'bg-violet-100', 'tc'=>'text-violet-700'],
        'secretaire' => ['icon'=>'clipboard',    'bg'=>'bg-sky-100',    'tc'=>'text-sky-700'],
        'comptable'  => ['icon'=>'calculator',   'bg'=>'bg-emerald-100','tc'=>'text-emerald-700'],
        'enseignant' => ['icon'=>'book-open',    'bg'=>'bg-amber-100',  'tc'=>'text-amber-700'],
        'parent'     => ['icon'=>'heart',        'bg'=>'bg-indigo-100', 'tc'=>'text-indigo-700'],
        'eleve'      => ['icon'=>'user',         'bg'=>'bg-slate-100',  'tc'=>'text-slate-700'],
    ];
    $roleLabels = \App\Models\UserModel::allRoles();
    foreach ($roleLabels as $r):
        $ri = $roleIcons[$r] ?? ['icon'=>'user','bg'=>'bg-slate-100','tc'=>'text-slate-700'];
        $isActive = ($f['role'] === $r);
    ?>
    <a href="<?= BASE_URL ?>/utilisateurs<?= buildQ($f, ['role' => $isActive ? '' : $r, 'page' => '']) ?>"
       class="rounded-xl border border-slate-200 bg-white shadow-sm p-3 text-center hover:border-violet-300 transition-colors
              <?= $isActive ? 'ring-2 ring-violet-500' : '' ?>">
        <div class="mx-auto mb-1 w-8 h-8 rounded-full <?= $ri['bg'] ?> flex items-center justify-center">
            <i data-lucide="<?= $ri['icon'] ?>" class="w-4 h-4 <?= $ri['tc'] ?>"></i>
        </div>
        <p class="text-base font-bold text-slate-800"><?= (int)($stats[$r] ?? 0) ?></p>
        <p class="text-xs text-slate-500 mt-0.5"><?= \App\Models\UserModel::roleLabel($r) ?></p>
    </a>
    <?php endforeach; ?>
</div>

<!-- Filtres -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm p-4 mb-4">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-48">
            <label class="form-label">Recherche</label>
            <div class="relative">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($f['search'], ENT_QUOTES) ?>"
                       placeholder="Nom, prénom ou email…"
                       class="form-input pl-9">
            </div>
        </div>
        <div class="min-w-36">
            <label class="form-label">Rôle</label>
            <select name="role" class="form-select">
                <option value="">Tous les rôles</option>
                <?php foreach (\App\Models\UserModel::allRoles() as $r): ?>
                <option value="<?= $r ?>" <?= $f['role'] === $r ? 'selected' : '' ?>><?= \App\Models\UserModel::roleLabel($r) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="min-w-32">
            <label class="form-label">Statut</label>
            <select name="actif" class="form-select">
                <option value="">Tous</option>
                <option value="1" <?= $f['actif'] === '1' ? 'selected' : '' ?>>Actifs</option>
                <option value="0" <?= $f['actif'] === '0' ? 'selected' : '' ?>>Désactivés</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i data-lucide="search" class="w-4 h-4"></i>Filtrer
            </button>
            <a href="<?= BASE_URL ?>/utilisateurs" class="btn btn-outline">
                <i data-lucide="x" class="w-4 h-4"></i>Réinitialiser
            </a>
        </div>
    </form>
</div>

<!-- Table -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="border-b border-slate-200 px-5 py-3 flex items-center justify-between">
        <span class="font-semibold text-slate-700 flex items-center gap-2">
            <i data-lucide="list" class="w-4 h-4 text-violet-600"></i>
            Liste des utilisateurs
        </span>
        <span class="text-xs text-slate-500"><?= (int)$pagination['total'] ?> résultat(s)</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-800 text-slate-100">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">Utilisateur</th>
                    <th class="px-4 py-3 text-left font-semibold">Email</th>
                    <th class="px-4 py-3 text-left font-semibold">Rôle</th>
                    <th class="px-4 py-3 text-left font-semibold">Téléphone</th>
                    <th class="px-4 py-3 text-center font-semibold">Statut</th>
                    <th class="px-4 py-3 text-center font-semibold">Dernière connexion</th>
                    <th class="px-4 py-3 text-right font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($pagination['data'] as $u): ?>
            <tr class="hover:bg-slate-50 <?= (int)$u->actif === 0 ? 'opacity-60' : '' ?>">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-bold text-white
                                    bg-gradient-to-br from-violet-500 to-indigo-600">
                            <?= strtoupper(substr($u->prenom ?: $u->nom, 0, 1)) ?>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-800">
                                <?= htmlspecialchars(trim($u->prenom . ' ' . $u->nom), ENT_QUOTES) ?>
                            </p>
                            <p class="text-xs text-slate-400">#<?= (int)$u->id ?></p>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3 text-slate-600">
                    <?= htmlspecialchars($u->email, ENT_QUOTES) ?>
                </td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
                                 <?= $roleBadge[$u->role] ?? 'bg-slate-100 text-slate-700' ?>">
                        <?= \App\Models\UserModel::roleLabel($u->role) ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-slate-500 text-xs">
                    <?= $u->telephone ? htmlspecialchars($u->telephone, ENT_QUOTES) : '—' ?>
                </td>
                <td class="px-4 py-3 text-center">
                    <?php if ((int)$u->actif === 1): ?>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-emerald-100 text-emerald-700">
                        <i data-lucide="check-circle" class="w-3 h-3"></i>Actif
                    </span>
                    <?php else: ?>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-slate-100 text-slate-500">
                        <i data-lucide="x-circle" class="w-3 h-3"></i>Inactif
                    </span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-center text-xs text-slate-400">
                    <?= $u->derniere_connexion
                        ? date('d/m/Y H:i', strtotime($u->derniere_connexion))
                        : 'Jamais' ?>
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center justify-end gap-1">
                        <?php if (in_array('users.edit', $perms, true)): ?>
                        <a href="<?= BASE_URL ?>/utilisateurs/<?= (int)$u->id ?>/edit"
                           class="btn btn-ghost btn-sm btn-icon" title="Modifier">
                            <i data-lucide="pencil" class="w-4 h-4"></i>
                        </a>
                        <form method="POST" action="<?= BASE_URL ?>/utilisateurs/<?= (int)$u->id ?>/toggle-actif"
                              style="display:inline">
                            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                            <button type="submit" class="btn btn-ghost btn-sm btn-icon"
                                    title="<?= (int)$u->actif ? 'Désactiver' : 'Activer' ?>">
                                <i data-lucide="<?= (int)$u->actif ? 'toggle-right' : 'toggle-left' ?>"
                                   class="w-4 h-4 <?= (int)$u->actif ? 'text-emerald-600' : 'text-slate-400' ?>"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php if (in_array('users.delete', $perms, true) && (int)$u->id !== ($currentUser['id'] ?? 0)): ?>
                        <form method="POST" action="<?= BASE_URL ?>/utilisateurs/<?= (int)$u->id ?>/delete"
                              onsubmit="return confirm('Supprimer cet utilisateur ?')" style="display:inline">
                            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                            <button type="submit" class="btn btn-ghost btn-sm btn-icon text-red-500" title="Supprimer">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($pagination['data'])): ?>
            <tr>
                <td colspan="7" class="px-4 py-12 text-center text-slate-400">
                    <i data-lucide="users" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
                    <p>Aucun utilisateur trouvé</p>
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['last_page'] > 1): ?>
    <div class="border-t border-slate-200 px-5 py-3 flex items-center justify-between text-sm">
        <span class="text-slate-500">
            Page <?= $pagination['current_page'] ?> / <?= $pagination['last_page'] ?>
            &mdash; <?= (int)$pagination['total'] ?> résultat(s)
        </span>
        <div class="flex gap-1">
            <?php for ($p = 1; $p <= $pagination['last_page']; $p++): ?>
            <a href="<?= BASE_URL ?>/utilisateurs<?= buildQ($f, ['page' => $p]) ?>"
               class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-xs font-medium
                      <?= $p === $pagination['current_page']
                          ? 'bg-violet-600 text-white'
                          : 'text-slate-600 hover:bg-slate-100' ?>">
                <?= $p ?>
            </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
