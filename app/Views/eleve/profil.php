<?php
$eleve     = $eleve     ?? null;
$user      = $user      ?? [];
$errors    = $errors    ?? [];
$csrfToken = \Core\Session::getCsrfToken();
?>

<!-- Header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center">
            <i data-lucide="user" class="w-5 h-5 text-violet-600"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-slate-900">Mon profil</h2>
            <p class="text-xs text-slate-400">Informations personnelles &amp; sécurité</p>
        </div>
    </div>
    <a href="<?= BASE_URL ?>/eleve/dashboard" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour
    </a>
</div>

<?php $flash = \Core\Session::getFlash('success'); if ($flash): ?>
<div class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm border-emerald-200 bg-emerald-50 text-emerald-800 mb-5">
    <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
    <span class="flex-1"><?= htmlspecialchars($flash, ENT_QUOTES) ?></span>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    <!-- Info card -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm h-fit">
        <div class="p-5 p-6 text-center">
            <!-- Avatar -->
            <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center mx-auto mb-4 shadow-md">
                <i data-lucide="user" class="w-10 h-10 text-white"></i>
            </div>
            <p class="text-xl font-bold text-slate-900"><?= htmlspecialchars(trim(($eleve->prenom ?? '') . ' ' . ($eleve->nom ?? '')), ENT_QUOTES) ?></p>
            <p class="text-sm text-slate-400 mt-0.5 mb-1">
                <span class="inline-flex items-center gap-1">
                    <i data-lucide="layers" class="w-3 h-3"></i>
                    <?= htmlspecialchars($eleve->classe_nom ?? '-', ENT_QUOTES) ?>
                </span>
            </p>
            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-violet-100 text-violet-700 mb-5">Élève</span>

            <div class="space-y-3 text-left pt-4 border-t border-slate-100">
                <?php
                $infos = [
                    ['icon'=>'hash',      'label'=>'Matricule', 'val'=>$eleve->matricule ?? '-'],
                    ['icon'=>'calendar',  'label'=>'Naissance', 'val'=>!empty($eleve->date_naissance) ? date('d/m/Y', strtotime($eleve->date_naissance)) : '-'],
                    ['icon'=>'map-pin',   'label'=>'Adresse',   'val'=>$eleve->adresse ?? '-'],
                    ['icon'=>'phone',     'label'=>'Téléphone', 'val'=>$eleve->telephone ?? '-'],
                ];
                foreach ($infos as $inf): ?>
                <div class="flex items-start gap-3">
                    <div class="w-7 h-7 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                        <i data-lucide="<?= $inf['icon'] ?>" class="w-3.5 h-3.5 text-slate-500"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-slate-400"><?= $inf['label'] ?></p>
                        <p class="text-sm text-slate-700 font-medium truncate"><?= htmlspecialchars($inf['val'], ENT_QUOTES) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Change password -->
    <div class="lg:col-span-2 space-y-4">
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm h-fit">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <div class="w-7 h-7 rounded-lg bg-amber-100 flex items-center justify-center">
                    <i data-lucide="lock" class="w-3.5 h-3.5 text-amber-500"></i>
                </div>
                <span class="font-semibold text-slate-700">Changer le mot de passe</span>
            </div>
            <div class="p-5 p-5">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                    <ul class="list-disc list-inside space-y-0.5 flex-1">
                        <?php foreach ($errors as $e): ?>
                        <li class="text-sm"><?= htmlspecialchars($e, ENT_QUOTES) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <form method="POST" action="<?= BASE_URL ?>/eleve/profil/password" class="space-y-4">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                    <div class="mb-4">
                        <label class="form-label" for="current_password">Mot de passe actuel</label>
                        <div class="relative flex items-stretch">
                            <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500"><i data-lucide="lock" class="w-4 h-4"></i></span>
                            <input type="password" id="current_password" name="current_password"
                                   class="form-input pl-10" autocomplete="current-password" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="new_password">Nouveau mot de passe</label>
                        <div class="relative flex items-stretch">
                            <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500"><i data-lucide="key" class="w-4 h-4"></i></span>
                            <input type="password" id="new_password" name="new_password"
                                   class="form-input pl-10 pr-10" autocomplete="new-password" required
                                   minlength="8" placeholder="Minimum 8 caractères">
                            <button type="button"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
                                    onclick="this.previousElementSibling.type==='password'?(this.previousElementSibling.type='text'):(this.previousElementSibling.type='password')"
                                    aria-label="Afficher/Masquer">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </div>
                        <p class="mt-1 block text-xs text-slate-500">Minimum 8 caractères</p>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="confirm_password">Confirmer le mot de passe</label>
                        <div class="relative flex items-stretch">
                            <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500"><i data-lucide="key" class="w-4 h-4"></i></span>
                            <input type="password" id="confirm_password" name="confirm_password"
                                   class="form-input pl-10" autocomplete="new-password" required>
                        </div>
                    </div>
                    <div class="flex justify-end pt-1">
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="save" class="w-4 h-4"></i>Mettre à jour le mot de passe
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Security notice -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <div class="w-7 h-7 rounded-lg bg-emerald-100 flex items-center justify-center">
                    <i data-lucide="shield-check" class="w-3.5 h-3.5 text-emerald-500"></i>
                </div>
                <span class="font-semibold text-slate-700">Sécurité du compte</span>
            </div>
            <div class="p-5 p-4">
                <ul class="space-y-2">
                    <?php
                    $secItems = [
                        'Mot de passe chiffré (bcrypt)',
                        'Session sécurisée avec token CSRF',
                        'Accès restreint aux données personnelles',
                    ];
                    foreach ($secItems as $item): ?>
                    <li class="flex items-center gap-2.5 text-sm text-slate-600">
                        <i data-lucide="check-circle" class="w-4 h-4 text-emerald-500 shrink-0"></i>
                        <?= htmlspecialchars($item, ENT_QUOTES) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
