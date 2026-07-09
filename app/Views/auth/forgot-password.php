<?php
$errors = $errors ?? [];
$old    = $old    ?? [];
?>

<div class="text-center mb-7">
    <div class="w-12 h-12 rounded-2xl bg-amber-500 flex items-center justify-center mx-auto mb-4 shadow-lg shadow-amber-200">
        <i data-lucide="key-round" class="w-6 h-6 text-white"></i>
    </div>
    <h2 class="text-xl font-bold text-slate-900 mb-1">Mot de passe oublié</h2>
    <p class="text-sm text-slate-500">
        Entrez votre email et nous vous enverrons un lien de réinitialisation.
    </p>
</div>

<?php if (\Core\Session::hasFlash('success')): ?>
<div class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm border-emerald-200 bg-emerald-50 text-emerald-800 mb-5" role="alert">
    <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
    <span class="flex-1 text-sm"><?= htmlspecialchars(\Core\Session::getFlash('success'), ENT_QUOTES) ?></span>
</div>
<?php endif; ?>

<?php if (\Core\Session::hasFlash('info')): ?>
<?php $devLink = \Core\Session::getFlash('info'); ?>
<?php preg_match('/(https?:\/\/\S+)/', $devLink, $m); ?>
<div class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm border-sky-200 bg-sky-50 text-sky-800 mb-5" role="alert">
    <i data-lucide="info" class="w-4 h-4 shrink-0"></i>
    <div class="flex-1 text-sm">
        <span class="font-medium">Mode développement :</span><br>
        <?php if (!empty($m[1])): ?>
        <a href="<?= htmlspecialchars($m[1], ENT_QUOTES) ?>"
           class="text-sky-700 underline break-all">
            <?= htmlspecialchars($m[1], ENT_QUOTES) ?>
        </a>
        <?php else: ?>
        <?= htmlspecialchars($devLink, ENT_QUOTES) ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

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

<form method="POST" action="<?= BASE_URL ?>/forgot-password" novalidate class="space-y-4">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

    <div class="mb-4">
        <label class="form-label" for="email">Adresse email</label>
        <div class="relative flex items-stretch">
            <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500"><i data-lucide="mail" class="w-4 h-4"></i></span>
            <input type="email"
                   class="form-input pl-10 <?= isset($errors['email']) ? 'border-red-400' : '' ?>"
                   id="email" name="email"
                   value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES) ?>"
                   placeholder="votre@email.com"
                   required autofocus autocomplete="email">
        </div>
        <?php if (isset($errors['email'])): ?>
        <p class="form-error"><?= htmlspecialchars($errors['email'][0], ENT_QUOTES) ?></p>
        <?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary w-full mt-2 h-11 text-sm font-semibold">
        <i data-lucide="send" class="w-4 h-4"></i>
        Envoyer le lien
    </button>
</form>

<div class="mt-5 text-center">
    <a href="<?= BASE_URL ?>/login"
       class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700 transition-colors">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
        Retour à la connexion
    </a>
</div>
