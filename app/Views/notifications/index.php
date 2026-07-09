<?php
$notifications = $notifications ?? [];
$types         = $types         ?? \App\Models\NotificationModel::TYPES;
$csrfToken     = \Core\Session::getCsrfToken();
$currentUser   = \Core\Session::getUser();

$grouped = [];
foreach ($notifications as $n) {
    $day = date('Y-m-d', strtotime($n->created_at));
    $grouped[$day][] = $n;
}

function notifTypeIcon(string $type): string {
    return match($type) {
        'note'     => 'file-text',
        'absence'  => 'user-x',
        'paiement' => 'banknote',
        'annonce'  => 'megaphone',
        'alerte'   => 'alert-triangle',
        default    => 'bell',
    };
}
function notifTypeBg(string $type): string {
    return match($type) {
        'note'     => 'bg-blue-100 text-blue-600',
        'absence'  => 'bg-red-100 text-red-500',
        'paiement' => 'bg-emerald-100 text-emerald-600',
        'annonce'  => 'bg-violet-100 text-violet-600',
        'alerte'   => 'bg-amber-100 text-amber-600',
        default    => 'bg-slate-100 text-slate-500',
    };
}

$unread = count(array_filter($notifications, fn($n) => !$n->lu));
?>

<!-- Header -->
<div class="flex flex-wrap items-start justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="bell" class="w-5 h-5 text-amber-500"></i>
            Mes notifications
            <?php if ($unread): ?>
            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-red-100 text-red-700"><?= $unread ?> non lu<?= $unread > 1 ? 's' : '' ?></span>
            <?php endif; ?>
        </h2>
        <p class="text-sm text-slate-400 mt-0.5">
            <?= count($notifications) ?> notification<?= count($notifications) > 1 ? 's' : '' ?> au total
        </p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <a href="<?= BASE_URL ?>/notifications/preferences" class="btn btn-secondary">
            <i data-lucide="settings" class="w-4 h-4"></i>Préférences
        </a>
        <?php if (!empty($notifications)): ?>
        <form method="POST" action="<?= BASE_URL ?>/notifications/mark-all-read">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
            <button class="btn btn-secondary">
                <i data-lucide="check-check" class="w-4 h-4"></i>Tout marquer lu
            </button>
        </form>
        <?php endif; ?>
        <?php if (in_array($currentUser['role'] ?? '', ['admin','directeur'], true)): ?>
        <a href="<?= BASE_URL ?>/admin/notifications" class="btn btn-secondary">
            <i data-lucide="history" class="w-4 h-4"></i>Historique
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($notifications)): ?>
<!-- État vide -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="p-5 py-16 text-center">
        <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-4">
            <i data-lucide="bell-off" class="w-8 h-8 text-slate-300"></i>
        </div>
        <p class="font-semibold text-slate-500 text-base mb-1">Aucune notification</p>
        <p class="text-sm text-slate-400">Vous n'avez aucune notification pour le moment.</p>
    </div>
</div>
<?php else: ?>

<div class="space-y-5">
    <?php foreach ($grouped as $day => $dayNotifs): ?>
    <div>
        <!-- Label de date -->
        <div class="flex items-center gap-3 mb-2">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-widest">
                <?php
                $ts = strtotime($day);
                $today = date('Y-m-d');
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                if ($day === $today) echo 'Aujourd\'hui';
                elseif ($day === $yesterday) echo 'Hier';
                else echo date('d/m/Y', $ts);
                ?>
            </p>
            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600 text-xs"><?= count($dayNotifs) ?></span>
        </div>

        <!-- Notifications du jour -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden divide-y divide-slate-100">
            <?php foreach ($dayNotifs as $n):
                $typeInfo = $types[$n->type] ?? ['icon'=>'bell','color'=>'slate','label'=>$n->type];
                $iconBg = notifTypeBg($n->type);
            ?>
            <div class="flex items-start gap-4 px-4 py-3.5 <?= !$n->lu ? 'bg-violet-50/60' : 'bg-white' ?> hover:bg-slate-50 transition-colors group">

                <!-- Indicateur non lu -->
                <div class="flex-shrink-0 mt-1">
                    <?php if (!$n->lu): ?>
                    <span class="block w-2 h-2 rounded-full bg-violet-500 mt-1"></span>
                    <?php else: ?>
                    <span class="block w-2 h-2"></span>
                    <?php endif; ?>
                </div>

                <!-- Icône type -->
                <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 <?= $iconBg ?>">
                    <i data-lucide="<?= notifTypeIcon($n->type) ?>" class="w-4 h-4"></i>
                </div>

                <!-- Contenu -->
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-slate-800 <?= !$n->lu ? 'font-semibold' : 'font-medium' ?> leading-snug">
                        <?= htmlspecialchars($n->titre ?? $n->message ?? '', ENT_QUOTES) ?>
                    </p>
                    <?php if (!empty($n->message) && !empty($n->titre)): ?>
                    <p class="text-xs text-slate-500 mt-0.5 line-clamp-2"><?= htmlspecialchars($n->message, ENT_QUOTES) ?></p>
                    <?php endif; ?>
                    <p class="text-xs text-slate-400 mt-1.5 flex items-center gap-1">
                        <i data-lucide="clock" class="w-3 h-3"></i>
                        <?= date('H:i', strtotime($n->created_at)) ?>
                    </p>
                </div>

                <!-- Actions (visibles au hover) -->
                <div class="flex items-center gap-1 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                    <?php if (!$n->lu): ?>
                    <form method="POST" action="<?= BASE_URL ?>/notifications/<?= $n->id ?>/read">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                        <button class="btn btn-ghost btn-icon text-violet-500" title="Marquer comme lu">
                            <i data-lucide="check" class="w-4 h-4"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if (!empty($n->lien)): ?>
                    <a href="<?= htmlspecialchars($n->lien, ENT_QUOTES) ?>"
                       class="btn btn-ghost btn-icon text-slate-400" title="Voir">
                        <i data-lucide="external-link" class="w-4 h-4"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>
