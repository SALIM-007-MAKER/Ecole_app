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

<?php if (!empty($passkeys)): ?>
<div id="bloc-passkey" style="margin-top:1rem;border-top:1px solid var(--bdr, #e2e8f0);padding-top:1rem" hidden>
    <button type="button" id="btn-passkey" class="btn btn-secondary" style="width:100%;justify-content:center">
        <i data-lucide="key-round" class="w-4 h-4"></i>Utiliser ma passkey
    </button>
    <p id="passkey-message" role="alert" style="text-align:center;font-size:.8125rem;color:#dc2626;margin:.5rem 0 0" hidden></p>
</div>
<script>
(function () {
    var bloc = document.getElementById('bloc-passkey');
    if (!bloc || !window.PublicKeyCredential || !navigator.credentials) { return; }
    bloc.hidden = false;
    var token = document.querySelector('[name=_csrf_token]').value, msg = document.getElementById('passkey-message'), btn = document.getElementById('btn-passkey');
    function dec(s) { s = s.replace(/-/g, '+').replace(/_/g, '/'); while (s.length % 4) { s += '='; } var b = atob(s), u = new Uint8Array(b.length); for (var i = 0; i < b.length; i++) { u[i] = b.charCodeAt(i); } return u.buffer; }
    function enc(buf) { var u = new Uint8Array(buf), s = ''; for (var i = 0; i < u.length; i++) { s += String.fromCharCode(u[i]); } return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); }
    function erreur(t) { msg.textContent = t; msg.hidden = false; btn.disabled = false; }
    async function poster(url, donnees) {
        var r = await fetch(url, { method: 'POST', body: donnees, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
        try { return await r.json(); } catch (e) { return { ok: false, message: 'Réponse inattendue du serveur.' }; }
    }
    btn.addEventListener('click', async function () {
        msg.hidden = true; btn.disabled = true;
        try {
            var d0 = new FormData(); d0.append('_csrf_token', token);
            var j = await poster('<?= BASE_URL ?>/platform/login/2fa/passkey/options', d0);
            if (!j.ok) { if (j.redirect) { window.location.href = j.redirect; return; } return erreur(j.message || 'Échec.'); }
            var o = j.options;
            var cred = await navigator.credentials.get({ publicKey: {
                challenge: dec(o.challenge), rpId: o.rpId, timeout: o.timeout, userVerification: o.userVerification,
                allowCredentials: o.allowCredentials.map(function (c) { return { type: c.type, id: dec(c.id) }; })
            } });
            var d = new FormData();
            d.append('_csrf_token', token);
            d.append('credential_id', enc(cred.rawId));
            d.append('client_data', enc(cred.response.clientDataJSON));
            d.append('authenticator_data', enc(cred.response.authenticatorData));
            d.append('signature', enc(cred.response.signature));
            var j2 = await poster('<?= BASE_URL ?>/platform/login/2fa/passkey', d);
            if (j2.ok || j2.redirect) { window.location.href = j2.redirect; } else { erreur(j2.message || 'Échec.'); }
        } catch (err) {
            erreur(err && err.name === 'NotAllowedError' ? 'Opération annulée ou expirée.' : 'Passkey inutilisable sur cet appareil.');
        }
    });
})();
</script>
<?php endif; ?>
