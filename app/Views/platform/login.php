<div style="text-align:center;margin-bottom:1.5rem">
    <h1 style="color:#f1f5f9;font-size:1.25rem;font-weight:700;margin:0 0 .25rem">Connexion opérateur</h1>
    <p style="color:#64748b;font-size:.8125rem;margin:0">Portail Super-Admin — accès plateforme uniquement</p>
</div>

<form method="POST" action="<?= BASE_URL ?>/platform/login" class="space-y-4">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
    <div>
        <label class="form-label" for="email">Email</label>
        <input type="email" id="email" name="email" class="form-input" required autofocus>
    </div>
    <div>
        <label class="form-label" for="password">Mot de passe</label>
        <input type="password" id="password" name="password" class="form-input" required>
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
        <i data-lucide="log-in" class="w-4 h-4"></i>Se connecter
    </button>
</form>
