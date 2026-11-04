<div class="max-w-xl mx-auto rounded-xl border border-slate-200 bg-white shadow-sm p-6">
    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2 mb-4">
        <i data-lucide="key-round" class="w-5 h-5 text-violet-600"></i>Mon mot de passe
    </h2>

    <?php if (!empty($obligatoire)): ?>
    <div class="alert alert-warning mb-4" role="alert">
        <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
        <span>Votre mot de passe est provisoire : choisissez-en un nouveau pour accéder au portail.</span>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/platform/compte/mot-de-passe" autocomplete="off" class="space-y-4">
        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
        <div>
            <label class="form-label" for="current_password">Mot de passe actuel</label>
            <input type="password" id="current_password" name="current_password" class="form-input" required autocomplete="current-password">
        </div>
        <div>
            <label class="form-label" for="new_password">Nouveau mot de passe</label>
            <input type="password" id="new_password" name="new_password" class="form-input" required minlength="<?= (int)$longueur ?>" autocomplete="new-password">
            <p class="text-xs text-slate-500 mt-1"><?= (int)$longueur ?> caractères minimum, avec au moins 3 types parmi minuscules, majuscules, chiffres et symboles ; sans votre e-mail, votre nom ni mot courant.</p>
        </div>
        <div>
            <label class="form-label" for="new_password_confirmation">Confirmer le nouveau mot de passe</label>
            <input type="password" id="new_password_confirmation" name="new_password_confirmation" class="form-input" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary">
            <i data-lucide="key-round" class="w-4 h-4"></i>Changer le mot de passe
        </button>
    </form>
</div>
