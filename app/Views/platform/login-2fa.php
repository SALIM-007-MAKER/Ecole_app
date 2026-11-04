<div style="text-align:center;margin-bottom:1.5rem">
    <h1 style="color:var(--txt-1);font-size:1.25rem;font-weight:700;margin:0 0 .25rem">Vérification en deux étapes</h1>
    <p style="color:var(--txt-3);font-size:.8125rem;margin:0">Saisissez le code à 6 chiffres de votre application d'authentification, ou un code de secours.</p>
</div>

<form method="POST" action="<?= BASE_URL ?>/platform/login/2fa" class="space-y-4" autocomplete="off">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
    <div>
        <label class="form-label" for="code">Code de vérification</label>
        <input type="text" id="code" name="code" class="form-input" inputmode="text" autocomplete="one-time-code" maxlength="16" required autofocus
               style="letter-spacing:.2em;font-family:monospace;text-align:center">
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
        <i data-lucide="shield-check" class="w-4 h-4"></i>Vérifier
    </button>
    <p style="text-align:center;font-size:.75rem;color:var(--txt-3);margin:0"><a href="<?= BASE_URL ?>/platform/login">Recommencer la connexion</a></p>
</form>
