<?php
/** @var bool $actif, $obligatoire; @var array $statut; @var ?string $secret, $qr; @var array $codes */
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);
$token = \Core\Session::getCsrfToken();
$base = BASE_URL . '/platform/compte/2fa';
?>
<div class="max-w-2xl mx-auto space-y-5">
    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2"><i data-lucide="shield-check" class="w-5 h-5 text-violet-600"></i>Double authentification</h2>

    <?php if (!$actif): ?>
    <p class="text-sm text-slate-500">La double authentification n'est pas encore activée sur cette installation (migration T072 à appliquer).</p>
    <?php else: ?>

    <?php if ($obligatoire && !$statut['active']): ?>
    <div class="alert alert-warning" role="alert"><i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i><span>La double authentification est obligatoire : activez-la pour accéder au portail.</span></div>
    <?php endif; ?>

    <?php if (!\Core\Platform\PlatformSecurityNotifier::disponible()): ?>
    <p class="text-xs text-slate-500">Alertes de sécurité par e-mail inactives : le SMTP n'est pas configuré sur ce serveur (voir docs/deploiement/EMAIL_GMAIL.md). Aucun e-mail n'est envoyé lors d'une connexion ou d'un changement de sécurité.</p>
    <?php endif; ?>

    <?php if ($codes): ?>
    <div class="rounded-xl border border-amber-300 bg-amber-50 p-5">
        <p class="text-sm font-semibold text-amber-900 mb-1">Codes de secours — à conserver maintenant</p>
        <p class="text-xs text-amber-800 mb-3">Chaque code ne sert qu'une fois, si vous perdez votre téléphone. Ils ne seront <strong>plus jamais affichés</strong> : notez-les ou imprimez cette page.</p>
        <ul class="grid grid-cols-2 gap-2 font-mono text-sm text-slate-900">
            <?php foreach ($codes as $c): ?><li class="rounded bg-white border border-amber-200 px-3 py-1.5 text-center"><?= $h($c) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (!$statut['active']): ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5 space-y-4">
        <p class="text-sm font-semibold text-slate-900">1. Enregistrer l'application</p>
        <p class="text-sm text-slate-600">Dans Google Authenticator, Microsoft Authenticator, Aegis ou FreeOTP, ajoutez un compte en scannant ce QR code.</p>
        <div class="flex flex-wrap items-center gap-6">
            <div class="border border-slate-200 rounded p-2 bg-white"><?= $qr /* SVG généré côté serveur, sans donnée utilisateur */ ?></div>
            <div class="text-sm"><p class="text-slate-500 text-xs mb-1">Impossible de scanner ? Saisissez cette clé :</p><code class="font-mono text-slate-900 break-all"><?= $h(trim(chunk_split((string)$secret, 4, ' '))) ?></code></div>
        </div>
        <p class="text-sm font-semibold text-slate-900">2. Confirmer avec un code</p>
        <form method="POST" action="<?= $base ?>/activer" class="flex flex-wrap items-end gap-2" autocomplete="off">
            <input type="hidden" name="_csrf_token" value="<?= $token ?>">
            <input type="password" name="current_password" placeholder="Mot de passe" class="form-input w-44" required autocomplete="current-password">
            <input type="text" name="code" inputmode="numeric" maxlength="8" placeholder="123456" class="form-input font-mono w-40" required autocomplete="one-time-code">
            <button type="submit" class="btn btn-primary">Activer la double authentification</button>
        </form>
    </div>
    <?php else: ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5 space-y-4">
        <p class="text-sm"><span class="badge badge-success text-xs">Activée</span> depuis le <?= $h($statut['depuis']) ?> · <?= (int)$statut['codes_restants'] ?> code(s) de secours restant(s)</p>
        <form method="POST" action="<?= $base ?>/codes" class="flex flex-wrap items-end gap-2" autocomplete="off">
            <input type="hidden" name="_csrf_token" value="<?= $token ?>">
            <input type="text" name="code" inputmode="numeric" maxlength="8" placeholder="Code à 6 chiffres" class="form-input font-mono w-44" required autocomplete="one-time-code">
            <button type="submit" class="btn btn-secondary">Régénérer les codes de secours</button>
        </form>
        <?php if (!$obligatoire): ?>
        <form method="POST" action="<?= $base ?>/desactiver" class="flex flex-wrap items-end gap-2 border-t border-slate-100 pt-4" autocomplete="off">
            <input type="hidden" name="_csrf_token" value="<?= $token ?>">
            <input type="password" name="current_password" placeholder="Mot de passe" class="form-input w-44" required autocomplete="current-password">
            <input type="text" name="code" inputmode="numeric" maxlength="8" placeholder="Code à 6 chiffres" class="form-input font-mono w-44" required autocomplete="one-time-code">
            <button type="submit" class="btn btn-secondary">Désactiver</button>
        </form>
        <?php else: ?>
        <p class="text-xs text-slate-500">La double authentification est obligatoire pour tous les opérateurs : elle ne peut pas être désactivée. En cas de téléphone perdu, un autre super_admin ou l'accès serveur peut la réinitialiser.</p>
        <?php endif; ?>
    </div>
    <?php if (!empty($passkeysDispo)): ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5 space-y-4" id="passkeys">
        <div>
            <p class="text-sm font-semibold text-slate-900 flex items-center gap-2"><i data-lucide="key-round" class="w-4 h-4 text-violet-600"></i>Passkeys</p>
            <p class="text-xs text-slate-500 mt-1">Une passkey (clé de sécurité, empreinte, Windows Hello, téléphone) peut remplacer le code à 6 chiffres à la connexion. Elle s'ajoute à l'application d'authentification, qui reste utilisable avec vos codes de secours.</p>
        </div>

        <?php if ($passkeys): ?>
        <ul class="divide-y divide-slate-100 border border-slate-100 rounded-lg">
            <?php foreach ($passkeys as $p): ?>
            <li class="p-3 space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm font-medium text-slate-900"><?= $h($p['nom']) ?></span>
                    <span class="text-xs text-slate-500">Ajoutée le <?= $h($p['created_at']) ?> · <?= $p['last_used_at'] ? 'dernier usage le ' . $h($p['last_used_at']) : 'jamais utilisée' ?></span>
                </div>
                <div class="flex flex-wrap gap-2 items-end">
                    <form method="POST" action="<?= $base ?>/passkeys/<?= (int)$p['id'] ?>/renommer" class="flex gap-2 items-end">
                        <input type="hidden" name="_csrf_token" value="<?= $token ?>">
                        <input type="text" name="nom" maxlength="80" value="<?= $h($p['nom']) ?>" class="form-input w-44" required aria-label="Nom de la passkey">
                        <button type="submit" class="btn btn-secondary">Renommer</button>
                    </form>
                    <form method="POST" action="<?= $base ?>/passkeys/<?= (int)$p['id'] ?>/supprimer" class="flex gap-2 items-end" autocomplete="off">
                        <input type="hidden" name="_csrf_token" value="<?= $token ?>">
                        <input type="password" name="current_password" placeholder="Mot de passe" class="form-input w-40" required autocomplete="current-password">
                        <button type="submit" class="btn btn-secondary">Supprimer</button>
                    </form>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="text-sm text-slate-500">Aucune passkey enregistrée.</p>
        <?php endif; ?>

        <?php if (count($passkeys) < $passkeysMax): ?>
        <form id="form-passkey" method="POST" action="<?= $base ?>/passkeys/options" class="flex flex-wrap items-end gap-2 border-t border-slate-100 pt-4" autocomplete="off">
            <input type="hidden" name="_csrf_token" value="<?= $token ?>">
            <input type="text" name="nom" maxlength="80" placeholder="Nom (ex. : YubiKey, Téléphone)" class="form-input w-56" required>
            <input type="password" name="current_password" placeholder="Mot de passe" class="form-input w-44" required autocomplete="current-password">
            <button type="submit" class="btn btn-primary" id="btn-passkey">Ajouter une passkey</button>
        </form>
        <p id="passkey-message" class="text-sm text-red-600" role="alert" hidden></p>
        <p id="passkey-indispo" class="text-xs text-slate-500" hidden>Ce navigateur ou cette connexion ne permet pas les passkeys (HTTPS ou localhost requis).</p>
        <script>
        (function () {
            var form = document.getElementById('form-passkey');
            if (!form) { return; }
            var msg = document.getElementById('passkey-message');
            if (!window.PublicKeyCredential || !navigator.credentials) {
                form.hidden = true;
                document.getElementById('passkey-indispo').hidden = false;
                return;
            }
            function dec(s) { s = s.replace(/-/g, '+').replace(/_/g, '/'); while (s.length % 4) { s += '='; } var b = atob(s), u = new Uint8Array(b.length); for (var i = 0; i < b.length; i++) { u[i] = b.charCodeAt(i); } return u.buffer; }
            function enc(buf) { var u = new Uint8Array(buf), s = ''; for (var i = 0; i < u.length; i++) { s += String.fromCharCode(u[i]); } return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); }
            function erreur(t) { msg.textContent = t; msg.hidden = false; document.getElementById('btn-passkey').disabled = false; }
            async function poster(url, donnees) {
                var r = await fetch(url, { method: 'POST', body: donnees, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                try { return await r.json(); } catch (e) { return { ok: false, message: 'Réponse inattendue du serveur (session expirée ?).' }; }
            }
            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                msg.hidden = true;
                document.getElementById('btn-passkey').disabled = true;
                try {
                    var j = await poster(form.action, new FormData(form));
                    if (!j.ok) { return erreur(j.message || 'Échec.'); }
                    var o = j.options;
                    var cred = await navigator.credentials.create({ publicKey: {
                        rp: o.rp, user: { id: dec(o.user.id), name: o.user.name, displayName: o.user.displayName },
                        challenge: dec(o.challenge), pubKeyCredParams: o.pubKeyCredParams, timeout: o.timeout, attestation: o.attestation,
                        authenticatorSelection: o.authenticatorSelection,
                        excludeCredentials: o.excludeCredentials.map(function (c) { return { type: c.type, id: dec(c.id) }; })
                    } });
                    var d = new FormData();
                    d.append('_csrf_token', form.querySelector('[name=_csrf_token]').value);
                    d.append('nom', form.querySelector('[name=nom]').value);
                    d.append('client_data', enc(cred.response.clientDataJSON));
                    d.append('attestation', enc(cred.response.attestationObject));
                    var j2 = await poster('<?= $base ?>/passkeys', d);
                    if (j2.ok) { window.location.href = j2.redirect; } else { erreur(j2.message || 'Échec.'); }
                } catch (err) {
                    erreur(err && err.name === 'NotAllowedError' ? 'Opération annulée ou expirée.' : 'Impossible de créer la passkey sur cet appareil.');
                }
            });
        })();
        </script>
        <?php else: ?>
        <p class="text-xs text-slate-500">Nombre maximal de passkeys atteint (<?= (int)$passkeysMax ?>) : supprimez-en une pour en ajouter.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>
</div>
