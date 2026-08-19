<?php
$errors  = $errors  ?? [];
$old     = $old     ?? [];
$user    = $user    ?? null;
$roles   = \App\Models\UserModel::allRoles();
$roleBadge = [
    'admin'      => 'bg-red-100 text-red-700',
    'directeur'  => 'bg-violet-100 text-violet-700',
    'secretaire' => 'bg-sky-100 text-sky-700',
    'comptable'  => 'bg-emerald-100 text-emerald-700',
    'enseignant' => 'bg-amber-100 text-amber-700',
    'parent'     => 'bg-indigo-100 text-indigo-700',
    'eleve'      => 'bg-slate-100 text-slate-700',
];
$v = fn(string $k) => htmlspecialchars($old[$k] ?? ($user->$k ?? ''), ENT_QUOTES);
?>

<!-- Header -->
<div class="flex items-start gap-4 mb-6">
    <a href="<?= BASE_URL ?>/utilisateurs" class="btn btn-ghost btn-icon">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
    </a>
    <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="user-cog" class="w-5 h-5 text-violet-600"></i>
    </div>
    <div class="flex-1">
        <h2 class="text-xl font-bold text-slate-900">Modifier l'utilisateur</h2>
        <?php if ($user): ?>
        <div class="flex items-center gap-2 mt-0.5">
            <span class="text-sm text-slate-500">
                <?= htmlspecialchars(trim($user->prenom . ' ' . $user->nom), ENT_QUOTES) ?>
            </span>
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold
                         <?= $roleBadge[$user->role] ?? 'bg-slate-100 text-slate-700' ?>">
                <?= \App\Models\UserModel::roleLabel($user->role) ?>
            </span>
        </div>
        <?php endif; ?>
    </div>
</div>

<form method="POST" action="<?= BASE_URL ?>/utilisateurs/<?= (int)$user->id ?>" class="max-w-2xl">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6 space-y-5">

        <!-- Nom / Prénom -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label" for="nom">Nom <span class="text-red-500">*</span></label>
                <input type="text" id="nom" name="nom"
                       value="<?= $v('nom') ?>"
                       class="form-input <?= isset($errors['nom']) ? 'border-red-400' : '' ?>"
                       required>
                <?php if (!empty($errors['nom'])): ?>
                <p class="mt-1 text-xs text-red-600"><?= htmlspecialchars(implode(', ', $errors['nom']), ENT_QUOTES) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label class="form-label" for="prenom">Prénom</label>
                <input type="text" id="prenom" name="prenom"
                       value="<?= $v('prenom') ?>"
                       class="form-input">
            </div>
        </div>

        <!-- Email -->
        <div>
            <label class="form-label" for="email">Adresse email <span class="text-red-500">*</span></label>
            <input type="email" id="email" name="email"
                   value="<?= $v('email') ?>"
                   class="form-input <?= isset($errors['email']) ? 'border-red-400' : '' ?>"
                   required>
            <?php if (!empty($errors['email'])): ?>
            <p class="mt-1 text-xs text-red-600"><?= htmlspecialchars(implode(', ', $errors['email']), ENT_QUOTES) ?></p>
            <?php endif; ?>
        </div>

        <!-- Rôle / Téléphone / Statut -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="form-label" for="role">Rôle <span class="text-red-500">*</span></label>
                <select id="role" name="role"
                        class="form-select <?= isset($errors['role']) ? 'border-red-400' : '' ?>" required>
                    <?php foreach ($roles as $r): ?>
                    <option value="<?= $r ?>" <?= ($old['role'] ?? $user->role ?? '') === $r ? 'selected' : '' ?>>
                        <?= \App\Models\UserModel::roleLabel($r) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="telephone">Téléphone</label>
                <input type="tel" id="telephone" name="telephone"
                       value="<?= $v('telephone') ?>"
                       class="form-input">
            </div>
            <div>
                <label class="form-label">Statut du compte</label>
                <div class="flex items-center gap-3 h-10">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="actif" value="1"
                               <?= (int)($old['actif'] ?? $user->actif ?? 1) ? 'checked' : '' ?>
                               class="w-4 h-4 rounded text-violet-600">
                        <span class="text-sm text-slate-700">Compte actif</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Mot de passe (optionnel) -->
        <div class="border-t border-slate-100 pt-5">
            <p class="text-sm font-medium text-slate-700 mb-3 flex items-center gap-2">
                <i data-lucide="lock" class="w-4 h-4 text-slate-400"></i>
                Changer le mot de passe
                <span class="text-xs text-slate-400 font-normal">(laisser vide pour ne pas modifier)</span>
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label" for="password">Nouveau mot de passe</label>
                    <input type="password" id="password" name="password"
                           class="form-input <?= isset($errors['password']) ? 'border-red-400' : '' ?>"
                           placeholder="Min. 8 caractères" minlength="8" autocomplete="new-password">
                    <?php if (!empty($errors['password'])): ?>
                    <p class="mt-1 text-xs text-red-600"><?= htmlspecialchars(implode(', ', $errors['password']), ENT_QUOTES) ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="form-label" for="password_confirmation">Confirmation</label>
                    <input type="password" id="password_confirmation" name="password_confirmation"
                           class="form-input <?= isset($errors['password_confirmation']) ? 'border-red-400' : '' ?>"
                           placeholder="Répéter le nouveau mot de passe">
                    <?php if (!empty($errors['password_confirmation'])): ?>
                    <p class="mt-1 text-xs text-red-600"><?= htmlspecialchars(implode(', ', $errors['password_confirmation']), ENT_QUOTES) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- Actions -->
    <div class="flex items-center justify-between mt-5">
        <a href="<?= BASE_URL ?>/utilisateurs" class="btn btn-outline">Annuler</a>
        <button type="submit" class="btn btn-primary">
            <i data-lucide="save" class="w-4 h-4"></i>Enregistrer les modifications
        </button>
    </div>
</form>
