<?php
$prefs     = $prefs     ?? [];
$triggers  = $triggers  ?? [];
$user      = $user      ?? [];
$csrfToken = \Core\Session::getCsrfToken();

$labels = [
    'note'     => ['icon'=>'file-text',     'desc'=>'Quand des notes ou bulletins sont publiés',   'color'=>'bg-blue-100 text-blue-600'],
    'absence'  => ['icon'=>'user-x',        'desc'=>'Quand une absence ou un retard est signalé',  'color'=>'bg-red-100 text-red-500'],
    'paiement' => ['icon'=>'banknote',      'desc'=>'Quand un paiement est enregistré',            'color'=>'bg-emerald-100 text-emerald-600'],
    'annonce'  => ['icon'=>'megaphone',     'desc'=>'Quand une nouvelle annonce est publiée',      'color'=>'bg-violet-100 text-violet-600'],
    'alerte'   => ['icon'=>'alert-triangle','desc'=>'Alertes importantes du système',              'color'=>'bg-amber-100 text-amber-600'],
];
?>

<!-- Header -->
<div class="flex items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="settings" class="w-5 h-5 text-violet-600"></i>Préférences de notifications
        </h2>
        <p class="text-sm text-slate-400 mt-0.5">Choisissez comment vous souhaitez être notifié</p>
    </div>
    <a href="<?= BASE_URL ?>/notifications" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour
    </a>
</div>

<?php $flash = \Core\Session::getFlash('success'); if ($flash): ?>
<div class="alert alert-success mb-4">
    <i data-lucide="check-circle" style="width:1rem;height:1rem;flex-shrink:0"></i>
    <span><?= htmlspecialchars($flash, ENT_QUOTES) ?></span>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    <!-- Formulaire préférences -->
    <div class="lg:col-span-2">
        <form method="POST" action="<?= BASE_URL ?>/notifications/preferences">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">

            <div class="space-y-3">
                <?php foreach ($triggers as $type => $trigger):
                    $lbl  = $labels[$type] ?? ['icon'=>'bell','desc'=>'','color'=>'bg-slate-100 text-slate-500'];
                    $pref = $prefs[$type] ?? [];
                ?>
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm hover:shadow-md transition-shadow">
                    <div class="p-4">
                        <div class="flex items-start gap-4">
                            <!-- Icône -->
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 <?= $lbl['color'] ?>">
                                <i data-lucide="<?= $lbl['icon'] ?>" class="w-5 h-5"></i>
                            </div>
                            <!-- Info + checkboxes -->
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-sm text-slate-800"><?= htmlspecialchars($trigger['label'] ?? $type, ENT_QUOTES) ?></p>
                                <p class="text-xs text-slate-400 mt-0.5"><?= $lbl['desc'] ?></p>
                                <!-- Canaux -->
                                <div class="flex items-center gap-5 mt-3">
                                    <!-- Interne -->
                                    <label class="flex items-center gap-2 cursor-pointer group/cb">
                                        <div class="relative">
                                            <input type="checkbox" name="pref[<?= $type ?>][interne]" value="1"
                                                   class="w-4 h-4 accent-violet-600"
                                                   <?= !empty($pref['interne']) ? 'checked' : '' ?>>
                                        </div>
                                        <span class="text-sm text-slate-600 group-hover/cb:text-slate-900 transition-colors flex items-center gap-1.5">
                                            <i data-lucide="bell" class="w-3.5 h-3.5 text-slate-400"></i>Interne
                                        </span>
                                    </label>
                                    <!-- Email -->
                                    <label class="flex items-center gap-2 cursor-pointer group/cb">
                                        <input type="checkbox" name="pref[<?= $type ?>][email]" value="1"
                                               class="w-4 h-4 accent-violet-600"
                                               <?= !empty($pref['email']) ? 'checked' : '' ?>>
                                        <span class="text-sm text-slate-600 group-hover/cb:text-slate-900 transition-colors flex items-center gap-1.5">
                                            <i data-lucide="mail" class="w-3.5 h-3.5 text-slate-400"></i>Email
                                        </span>
                                    </label>
                                    <!-- SMS (conditionnel) -->
                                    <?php if (!empty($trigger['sms_enabled'])): ?>
                                    <label class="flex items-center gap-2 cursor-pointer group/cb">
                                        <input type="checkbox" name="pref[<?= $type ?>][sms]" value="1"
                                               class="w-4 h-4 accent-violet-600"
                                               <?= !empty($pref['sms']) ? 'checked' : '' ?>>
                                        <span class="text-sm text-slate-600 group-hover/cb:text-slate-900 transition-colors flex items-center gap-1.5">
                                            <i data-lucide="smartphone" class="w-3.5 h-3.5 text-slate-400"></i>SMS
                                        </span>
                                    </label>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="flex items-center justify-end gap-3 mt-5">
                <a href="<?= BASE_URL ?>/notifications" class="btn btn-secondary">Annuler</a>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="save" class="w-4 h-4"></i>Enregistrer les préférences
                </button>
            </div>
        </form>
    </div>

    <!-- Sidebar info -->
    <div class="space-y-4">
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="info" class="w-4 h-4 text-violet-600"></i>
                <span class="font-semibold text-slate-700">À propos des canaux</span>
            </div>
            <div class="p-4 space-y-3">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center shrink-0">
                        <i data-lucide="bell" class="w-3.5 h-3.5 text-slate-500"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-700">Interne</p>
                        <p class="text-xs text-slate-400 mt-0.5">S'affiche directement dans l'application, accessible depuis la cloche.</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
                        <i data-lucide="mail" class="w-3.5 h-3.5 text-blue-500"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-700">Email</p>
                        <p class="text-xs text-slate-400 mt-0.5">Envoyé à votre adresse email enregistrée dans votre profil.</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center shrink-0">
                        <i data-lucide="smartphone" class="w-3.5 h-3.5 text-emerald-500"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-700">SMS</p>
                        <p class="text-xs text-slate-400 mt-0.5">Disponible uniquement si activé par l'administrateur.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm bg-violet-50 border-violet-100">
            <div class="p-4">
                <p class="text-xs font-semibold text-violet-700 flex items-center gap-1.5 mb-1.5">
                    <i data-lucide="lightbulb" class="w-3.5 h-3.5"></i>Conseil
                </p>
                <p class="text-xs text-violet-600">Activez au minimum le canal "Interne" pour ne pas manquer les notifications importantes.</p>
            </div>
        </div>
    </div>
</div>
