<?php
$errors = $errors ?? [];
$token  = $token  ?? '';
?>

<div class="text-center mb-7">
    <div class="w-12 h-12 rounded-2xl bg-emerald-600 flex items-center justify-center mx-auto mb-4 shadow-lg shadow-emerald-200">
        <i data-lucide="shield-check" class="w-6 h-6 text-white"></i>
    </div>
    <h2 class="text-xl font-bold text-slate-900 mb-1">Nouveau mot de passe</h2>
    <p class="text-sm text-slate-500">Choisissez un mot de passe fort d'au moins 8 caractères.</p>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" role="alert">
    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
    <ul class="flex-1 list-disc list-inside space-y-0.5">
        <?php foreach ($errors as $msgs): foreach ((array)$msgs as $msg): ?>
        <li class="text-sm"><?= htmlspecialchars($msg, ENT_QUOTES) ?></li>
        <?php endforeach; endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/reset-password" novalidate class="space-y-4">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">

    <div class="mb-4">
        <label class="form-label" for="password">Nouveau mot de passe</label>
        <div class="relative flex items-stretch">
            <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500"><i data-lucide="lock" class="w-4 h-4"></i></span>
            <input type="password"
                   class="form-input pl-10 pr-10 <?= isset($errors['password']) ? 'border-red-400' : '' ?>"
                   id="password" name="password"
                   placeholder="Minimum 8 caractères"
                   required autofocus minlength="8"
                   autocomplete="new-password">
            <button type="button" id="togglePassword"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
                    aria-label="Afficher/Masquer">
                <i data-lucide="eye" class="w-4 h-4"></i>
            </button>
        </div>
        <?php if (isset($errors['password'])): ?>
        <p class="form-error"><?= htmlspecialchars($errors['password'][0], ENT_QUOTES) ?></p>
        <?php else: ?>
        <p class="mt-1 block text-xs text-slate-500">Au moins 8 caractères, avec lettres et chiffres.</p>
        <?php endif; ?>

        <!-- Strength indicator -->
        <div class="mt-2">
            <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                <div id="strengthBar" class="h-full rounded-full transition-all duration-300" style="width:0%"></div>
            </div>
            <p id="strengthText" class="text-xs text-slate-400 mt-1"></p>
        </div>
    </div>

    <div class="mb-4">
        <label class="form-label" for="password_confirmation">Confirmer le mot de passe</label>
        <div class="relative flex items-stretch">
            <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500"><i data-lucide="lock-keyhole" class="w-4 h-4"></i></span>
            <input type="password"
                   class="form-input pl-10 <?= isset($errors['password_confirmation']) ? 'border-red-400' : '' ?>"
                   id="password_confirmation" name="password_confirmation"
                   placeholder="Répétez le mot de passe"
                   required autocomplete="new-password">
        </div>
        <?php if (isset($errors['password_confirmation'])): ?>
        <p class="form-error"><?= htmlspecialchars($errors['password_confirmation'][0], ENT_QUOTES) ?></p>
        <?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary w-full mt-2 h-11 text-sm font-semibold">
        <i data-lucide="check-circle" class="w-4 h-4"></i>
        Enregistrer le mot de passe
    </button>
</form>

<script>
document.getElementById('password')?.addEventListener('input', function () {
    const v = this.value;
    let score = 0;
    if (v.length >= 8)           score++;
    if (/[A-Z]/.test(v))         score++;
    if (/[0-9]/.test(v))         score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;

    const bar  = document.getElementById('strengthBar');
    const text = document.getElementById('strengthText');
    const levels = [
        {w: '0%',   cls: '',               txt: ''},
        {w: '25%',  cls: 'bg-red-500',     txt: 'Faible'},
        {w: '50%',  cls: 'bg-amber-500',   txt: 'Moyen'},
        {w: '75%',  cls: 'bg-sky-500',     txt: 'Fort'},
        {w: '100%', cls: 'bg-emerald-500', txt: 'Très fort'},
    ];
    const lvl = levels[Math.min(score, 4)];
    bar.style.width  = lvl.w;
    bar.className    = 'h-full rounded-full transition-all duration-300 ' + lvl.cls;
    text.textContent = lvl.txt;
});
</script>
