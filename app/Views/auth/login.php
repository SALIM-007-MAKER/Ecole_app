<?php
$errors = $errors ?? [];
$old    = $old    ?? [];

$branding = $branding ?? \Core\Tenant\BrandingService::forCurrentRequest();
$__loginLogo = $branding->logoUrl;
if ($__loginLogo && !file_exists(ROOT_PATH . '/public' . $__loginLogo)) {
    $__loginLogo = null;
}
?>

<div class="text-center mb-8">
    <?php if ($__loginLogo): ?>
    <img src="<?= BASE_URL ?><?= $__loginLogo ?>" alt="<?= htmlspecialchars($branding->appName, ENT_QUOTES) ?>" class="mx-auto mb-4" style="width:80px;height:80px;object-fit:contain;border-radius:1rem;border:1px solid rgba(148,163,184,0.35);padding:0.5rem;background:#fff;" />
    <?php endif; ?>
    <h2 class="text-2xl font-bold text-slate-900 mb-2">Connexion</h2>
    <p class="text-sm text-slate-500"><?= htmlspecialchars($branding->welcomeMessage ?: 'Entrez vos identifiants pour accéder à votre espace', ENT_QUOTES) ?></p>
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

<form method="POST" action="<?= BASE_URL ?>/login" novalidate class="space-y-4">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

    <div class="mb-4">
        <label class="form-label" for="email">Adresse email</label>
        <div class="relative flex items-stretch">
            <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500"><i data-lucide="mail" class="w-4 h-4"></i></span>
            <input type="email"
                   class="form-input pl-10 <?= isset($errors['email']) ? 'border-red-400 focus:ring-red-500' : '' ?>"
                   id="email" name="email"
                   value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES) ?>"
                   placeholder="votre@email.com"
                   required autofocus autocomplete="email">
        </div>
        <?php if (isset($errors['email'])): ?>
        <p class="form-error"><?= htmlspecialchars($errors['email'][0], ENT_QUOTES) ?></p>
        <?php endif; ?>
    </div>

    <div class="mb-4">
        <div class="flex items-center justify-between mb-1.5">
            <label class="form-label mb-0" for="password">Mot de passe</label>
            <a href="<?= BASE_URL ?>/forgot-password"
               class="text-xs text-violet-600 hover:text-violet-700 font-medium transition-colors">
                Mot de passe oublié ?
            </a>
        </div>
        <div class="relative flex items-stretch">
            <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500"><i data-lucide="lock" class="w-4 h-4"></i></span>
            <input type="password"
                   class="form-input pl-10 pr-10 <?= isset($errors['password']) ? 'border-red-400 focus:ring-red-500' : '' ?>"
                   id="password" name="password"
                   placeholder="••••••••"
                   required autocomplete="current-password">
            <button type="button" id="togglePassword"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
                    aria-label="Afficher/Masquer">
                <i data-lucide="eye" class="w-4 h-4"></i>
            </button>
        </div>
        <?php if (isset($errors['password'])): ?>
        <p class="form-error"><?= htmlspecialchars($errors['password'][0], ENT_QUOTES) ?></p>
        <?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary w-full mt-2 h-11 text-sm font-semibold">
        <i data-lucide="log-in" class="w-4 h-4"></i>
        Se connecter
    </button>
</form>

<?php $appConfig = require ROOT_PATH . '/config/app.php'; if ($appConfig['debug'] ?? false): ?>
<details class="mt-6 group">
    <summary class="text-xs text-slate-400 text-center cursor-pointer hover:text-slate-600 select-none transition-colors">
        <span class="inline-flex items-center gap-1">
            <i data-lucide="info" class="w-3 h-3"></i>Comptes de démonstration
        </span>
    </summary>
    <div class="mt-3 p-3 bg-slate-50 rounded-xl border border-slate-100 overflow-x-auto">
        <table class="w-full text-xs">
            <thead>
                <tr class="text-slate-500">
                    <th class="pb-2 font-semibold text-left">Rôle</th>
                    <th class="pb-2 font-semibold text-left">Email</th>
                    <th class="pb-2 font-semibold text-left">Mot de passe</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php
                $demos = [
                    ['admin',      'bg-red-100 text-red-700',     'Admin',      'admin@ecole.dz'],
                    ['directeur',  'bg-violet-100 text-violet-700','Directeur',  'directeur@ecole.dz'],
                    ['secretaire', 'bg-sky-100 text-sky-700',     'Secrétaire', 'secretaire@ecole.dz'],
                    ['comptable',  'bg-emerald-100 text-emerald-700','Comptable','comptable@ecole.dz'],
                    ['enseignant', 'bg-amber-100 text-amber-700', 'Enseignant', 'enseignant@ecole.dz'],
                    ['parent',     'bg-indigo-100 text-indigo-700','Parent',    'parent@ecole.dz'],
                    ['eleve',      'bg-slate-100 text-slate-700', 'Élève',      'eleve@ecole.dz'],
                ];
                foreach ($demos as [$role, $cls, $label, $email]):
                ?>
                <tr>
                    <td class="py-1.5"><span class="px-1.5 py-0.5 rounded text-xs font-medium <?= $cls ?>"><?= $label ?></span></td>
                    <td class="py-1.5 text-slate-600 mono"><?= $email ?></td>
                    <td class="py-1.5 text-slate-500 mono">password</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</details>
<?php endif; ?>
