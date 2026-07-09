<?php
use App\Models\UserModel;

$errors      = $errors ?? [];
$sessionUser = \Core\Session::getUser();
$role        = $sessionUser['role'] ?? '';

$roleColors = [
    'admin'      => 'bg-red-100 text-red-700',
    'directeur'  => 'bg-violet-100 text-violet-700',
    'enseignant' => 'bg-amber-100 text-amber-700',
    'comptable'  => 'bg-emerald-100 text-emerald-700',
    'secretaire' => 'bg-sky-100 text-sky-700',
    'parent'     => 'bg-indigo-100 text-indigo-700',
    'eleve'      => 'bg-slate-100 text-slate-700',
];
$roleClass = $roleColors[$role] ?? 'bg-slate-100 text-slate-700';
$roleLabel = UserModel::roleLabel($role);
?>

<!-- Page header -->
<div class="flex items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg font-bold text-slate-900">Mon profil</h2>
        <p class="text-sm text-slate-500">Gérez vos informations personnelles et votre mot de passe</p>
    </div>
    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold <?= $roleClass ?>">
        <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
        <?= htmlspecialchars($roleLabel, ENT_QUOTES) ?>
    </span>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- ── Informations personnelles ──────────────────────────────────────── -->
    <div class="lg:col-span-2 space-y-5">
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="user-cog" class="w-4 h-4 text-violet-600"></i>
                <span class="font-semibold text-slate-700">Informations personnelles</span>
            </div>
            <div class="p-5 p-6">

                <?php
                $profileKeys   = ['nom','prenom','email','telephone','photo'];
                $profileErrors = array_filter($errors, fn($k) => in_array($k, $profileKeys), ARRAY_FILTER_USE_KEY);
                if (!empty($profileErrors)):
                ?>
                <div class="alert alert-danger" role="alert">
                    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                    <ul class="flex-1 list-disc list-inside space-y-0.5">
                        <?php foreach ($profileErrors as $msgs): foreach ((array)$msgs as $msg): ?>
                        <li class="text-sm"><?= htmlspecialchars($msg, ENT_QUOTES) ?></li>
                        <?php endforeach; endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/profile"
                      enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

                    <!-- Photo -->
                    <div class="flex flex-col items-center gap-3 mb-6 pb-6 border-b border-slate-100">
                        <?php if (!empty($sessionUser['photo'])): ?>
                        <img id="photoPreview"
                             src="<?= BASE_URL ?>/<?= htmlspecialchars($sessionUser['photo'], ENT_QUOTES) ?>"
                             class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-md ring-2 ring-violet-100"
                             alt="Photo de profil">
                        <?php else: ?>
                        <div id="photoPreview"
                             class="w-24 h-24 rounded-full bg-violet-100 flex items-center justify-center text-violet-600 border-4 border-white shadow-md ring-2 ring-violet-100">
                            <i data-lucide="user" class="w-10 h-10"></i>
                        </div>
                        <?php endif; ?>
                        <label class="btn btn-outline cursor-pointer" for="photo">
                            <i data-lucide="camera" class="w-4 h-4"></i>
                            Changer la photo
                        </label>
                        <input type="file" id="photo" name="photo" accept="image/*"
                               class="sr-only" onchange="previewPhoto(this)">
                        <?php if (isset($errors['photo'])): ?>
                        <p class="form-error"><?= htmlspecialchars($errors['photo'][0], ENT_QUOTES) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label class="form-label" for="nom">Nom <span class="text-red-500">*</span></label>
                            <input type="text"
                                   class="form-input <?= isset($errors['nom']) ? 'border-red-400' : '' ?>"
                                   id="nom" name="nom"
                                   value="<?= htmlspecialchars($user->nom ?? '', ENT_QUOTES) ?>"
                                   required>
                            <?php if (isset($errors['nom'])): ?>
                            <p class="form-error"><?= htmlspecialchars($errors['nom'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="prenom">Prénom</label>
                            <input type="text" class="form-input" id="prenom" name="prenom"
                                   value="<?= htmlspecialchars($user->prenom ?? '', ENT_QUOTES) ?>">
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="email">Email <span class="text-red-500">*</span></label>
                            <input type="email"
                                   class="form-input <?= isset($errors['email']) ? 'border-red-400' : '' ?>"
                                   id="email" name="email"
                                   value="<?= htmlspecialchars($user->email ?? '', ENT_QUOTES) ?>"
                                   required>
                            <?php if (isset($errors['email'])): ?>
                            <p class="form-error"><?= htmlspecialchars($errors['email'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="telephone">Téléphone</label>
                            <input type="tel" class="form-input" id="telephone" name="telephone"
                                   value="<?= htmlspecialchars($user->telephone ?? '', ENT_QUOTES) ?>"
                                   placeholder="+213 6xx xx xx xx">
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Rôle</label>
                            <div class="form-input bg-slate-50 text-slate-500 cursor-not-allowed select-none flex items-center gap-2">
                                <i data-lucide="shield" class="w-4 h-4 text-slate-400"></i>
                                <?= htmlspecialchars($roleLabel, ENT_QUOTES) ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Dernière connexion</label>
                            <div class="form-input bg-slate-50 text-slate-500 cursor-not-allowed text-sm flex items-center gap-2">
                                <i data-lucide="clock" class="w-4 h-4 text-slate-400"></i>
                                <?= $user->derniere_connexion
                                    ? date('d/m/Y à H:i', strtotime($user->derniere_connexion))
                                    : 'Inconnue' ?>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-5 border-t border-slate-100">
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Sidebar droite ─────────────────────────────────────────────────── -->
    <div class="space-y-4">

        <!-- Changer le mot de passe -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="lock" class="w-4 h-4 text-amber-500"></i>
                <span class="font-semibold text-slate-700">Changer le mot de passe</span>
            </div>
            <div class="p-5 p-5">

                <?php
                $pwKeys    = ['current_password','password','password_confirmation'];
                $pwErrors  = array_filter($errors, fn($k) => in_array($k, $pwKeys), ARRAY_FILTER_USE_KEY);
                if (!empty($pwErrors)):
                ?>
                <div class="alert alert-danger mb-4" role="alert">
                    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                    <ul class="flex-1 list-disc list-inside space-y-0.5 text-sm">
                        <?php foreach ($pwErrors as $msgs): foreach ((array)$msgs as $msg): ?>
                        <li><?= htmlspecialchars($msg, ENT_QUOTES) ?></li>
                        <?php endforeach; endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/profile/password" novalidate class="space-y-3">
                    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

                    <div class="mb-4">
                        <label class="form-label text-xs" for="current_password">Mot de passe actuel</label>
                        <div class="relative flex items-stretch">
                            <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500"><i data-lucide="lock" class="w-3.5 h-3.5"></i></span>
                            <input type="password"
                                   class="form-input pl-9 text-sm <?= isset($errors['current_password']) ? 'border-red-400' : '' ?>"
                                   id="current_password" name="current_password" required
                                   autocomplete="current-password">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-xs" for="new_password">Nouveau mot de passe</label>
                        <div class="relative flex items-stretch">
                            <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500"><i data-lucide="key" class="w-3.5 h-3.5"></i></span>
                            <input type="password"
                                   class="form-input pl-9 pr-9 text-sm <?= isset($errors['password']) ? 'border-red-400' : '' ?>"
                                   id="new_password" name="password"
                                   minlength="8" required
                                   autocomplete="new-password">
                            <button type="button"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
                                    onclick="toggleVis('new_password')">
                                <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                        <p class="mt-1 block text-xs text-slate-500 text-xs">Minimum 8 caractères</p>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-xs" for="password_confirmation">Confirmer</label>
                        <div class="relative flex items-stretch">
                            <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500"><i data-lucide="key" class="w-3.5 h-3.5"></i></span>
                            <input type="password"
                                   class="form-input pl-9 text-sm <?= isset($errors['password_confirmation']) ? 'border-red-400' : '' ?>"
                                   id="password_confirmation" name="password_confirmation" required
                                   autocomplete="new-password">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning w-full px-2.5 py-1.5 text-xs rounded-md">
                        <i data-lucide="shield-check" class="w-4 h-4"></i>
                        Mettre à jour
                    </button>
                </form>
            </div>
        </div>

        <!-- Sécurité -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="shield-check" class="w-4 h-4 text-emerald-500"></i>
                <span class="font-semibold text-slate-700">Sécurité du compte</span>
            </div>
            <div class="p-5 p-5">
                <ul class="space-y-2.5">
                    <?php
                    $secItems = [
                        'Mots de passe chiffrés (bcrypt)',
                        'Session sécurisée (HTTPOnly, SameSite)',
                        'Protection CSRF active',
                        'Accès par rôle et permissions',
                    ];
                    foreach ($secItems as $item):
                    ?>
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

<script>
function previewPhoto(input) {
    if (!input.files?.[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const el = document.getElementById('photoPreview');
        if (el.tagName === 'IMG') {
            el.src = e.target.result;
        } else {
            const img = document.createElement('img');
            img.id        = 'photoPreview';
            img.src       = e.target.result;
            img.className = 'w-24 h-24 rounded-full object-cover border-4 border-white shadow-md ring-2 ring-violet-100';
            img.alt       = 'Photo de profil';
            el.replaceWith(img);
        }
    };
    reader.readAsDataURL(input.files[0]);
}

function toggleVis(id) {
    const el  = document.getElementById(id);
    const btn = el.nextElementSibling;
    const icon = btn?.querySelector('[data-lucide]');
    if (el.type === 'password') {
        el.type = 'text';
        icon?.setAttribute('data-lucide', 'eye-off');
    } else {
        el.type = 'password';
        icon?.setAttribute('data-lucide', 'eye');
    }
    if (window.lucide && icon) lucide.createIcons({ nodes: [btn] });
}
</script>
