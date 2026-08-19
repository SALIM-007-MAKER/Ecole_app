<?php
$errors = $errors ?? [];
$old    = $old    ?? [];
$roles  = \App\Models\UserModel::allRoles();
?>

<!-- Header -->
<div class="flex items-start gap-4 mb-6">
    <a href="<?= BASE_URL ?>/utilisateurs" class="btn btn-ghost btn-icon">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
    </a>
    <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="user-plus" class="w-5 h-5 text-violet-600"></i>
    </div>
    <div>
        <h2 class="text-xl font-bold text-slate-900">Nouvel utilisateur</h2>
        <p class="text-sm text-slate-500 mt-0.5">Créer un nouveau compte d'accès</p>
    </div>
</div>

<form method="POST" action="<?= BASE_URL ?>/utilisateurs/store" class="max-w-2xl">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6 space-y-5">

        <!-- Nom / Prénom -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label" for="nom">Nom <span class="text-red-500">*</span></label>
                <input type="text" id="nom" name="nom"
                       value="<?= htmlspecialchars($old['nom'] ?? '', ENT_QUOTES) ?>"
                       class="form-input <?= isset($errors['nom']) ? 'border-red-400' : '' ?>"
                       placeholder="Dupont" required>
                <?php if (!empty($errors['nom'])): ?>
                <p class="mt-1 text-xs text-red-600"><?= htmlspecialchars(implode(', ', $errors['nom']), ENT_QUOTES) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label class="form-label" for="prenom">Prénom</label>
                <input type="text" id="prenom" name="prenom"
                       value="<?= htmlspecialchars($old['prenom'] ?? '', ENT_QUOTES) ?>"
                       class="form-input"
                       placeholder="Jean">
            </div>
        </div>

        <!-- Email -->
        <div>
            <label class="form-label" for="email">Adresse email <span class="text-red-500">*</span></label>
            <input type="email" id="email" name="email"
                   value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES) ?>"
                   class="form-input <?= isset($errors['email']) ? 'border-red-400' : '' ?>"
                   placeholder="jean.dupont@ecole.fr" required>
            <?php if (!empty($errors['email'])): ?>
            <p class="mt-1 text-xs text-red-600"><?= htmlspecialchars(implode(', ', $errors['email']), ENT_QUOTES) ?></p>
            <?php endif; ?>
        </div>

        <!-- Rôle / Téléphone -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label" for="role">Rôle <span class="text-red-500">*</span></label>
                <select id="role" name="role"
                        class="form-select <?= isset($errors['role']) ? 'border-red-400' : '' ?>" required>
                    <option value="">— Choisir un rôle —</option>
                    <?php foreach ($roles as $r): ?>
                    <option value="<?= $r ?>" <?= ($old['role'] ?? '') === $r ? 'selected' : '' ?>>
                        <?= \App\Models\UserModel::roleLabel($r) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['role'])): ?>
                <p class="mt-1 text-xs text-red-600"><?= htmlspecialchars(implode(', ', $errors['role']), ENT_QUOTES) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label class="form-label" for="telephone">Téléphone</label>
                <input type="tel" id="telephone" name="telephone"
                       value="<?= htmlspecialchars($old['telephone'] ?? '', ENT_QUOTES) ?>"
                       class="form-input"
                       placeholder="0612345678">
            </div>
        </div>

        <!-- Mot de passe -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label" for="password">Mot de passe <span class="text-red-500">*</span></label>
                <input type="password" id="password" name="password"
                       class="form-input <?= isset($errors['password']) ? 'border-red-400' : '' ?>"
                       placeholder="Min. 8 caractères" required minlength="8">
                <?php if (!empty($errors['password'])): ?>
                <p class="mt-1 text-xs text-red-600"><?= htmlspecialchars(implode(', ', $errors['password']), ENT_QUOTES) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label class="form-label" for="password_confirmation">Confirmation <span class="text-red-500">*</span></label>
                <input type="password" id="password_confirmation" name="password_confirmation"
                       class="form-input <?= isset($errors['password_confirmation']) ? 'border-red-400' : '' ?>"
                       placeholder="Répéter le mot de passe" required>
                <?php if (!empty($errors['password_confirmation'])): ?>
                <p class="mt-1 text-xs text-red-600"><?= htmlspecialchars(implode(', ', $errors['password_confirmation']), ENT_QUOTES) ?></p>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Actions -->
    <div class="flex items-center justify-end gap-3 mt-5">
        <a href="<?= BASE_URL ?>/utilisateurs" class="btn btn-outline">Annuler</a>
        <button type="submit" class="btn btn-primary">
            <i data-lucide="user-plus" class="w-4 h-4"></i>Créer l'utilisateur
        </button>
    </div>
</form>
