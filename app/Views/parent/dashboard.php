<?php
$enfants       = $enfants       ?? [];
$notifications = $notifications ?? [];
$annonces      = $annonces      ?? [];
$user          = \Core\Session::getUser();
?>

<!-- Page header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900">
            Bonjour, <?= htmlspecialchars(($user['prenom'] ?: '') . ' ' . $user['nom'], ENT_QUOTES) ?>
            <span class="wave inline-block">👋</span>
        </h2>
        <p class="text-sm text-slate-400 mt-0.5 flex items-center gap-1">
            <i data-lucide="users" class="w-3.5 h-3.5"></i>
            <?= count($enfants) ?> enfant<?= count($enfants) > 1 ? 's' : '' ?> suivi<?= count($enfants) > 1 ? 's' : '' ?>
        </p>
    </div>
    <a href="<?= BASE_URL ?>/annonces" class="btn btn-outline btn-sm">
        <i data-lucide="megaphone" class="w-4 h-4"></i>Annonces
    </a>
</div>

<?php if (empty($enfants)): ?>
<div class="flex flex-col items-center justify-center gap-3 text-center text-slate-500 py-16 rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-1">
        <i data-lucide="info" class="w-8 h-8 text-slate-300"></i>
    </div>
    <p class="text-slate-500 font-medium">Aucun enfant associé à votre compte</p>
    <p class="text-sm text-slate-400">Contactez l'administration de l'établissement.</p>
</div>
<?php else: ?>

<!-- Enfants -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-5">
    <?php foreach ($enfants as $enfant):
        $moy   = $enfant->derniere_moyenne;
        $abs   = (int)$enfant->absences_mois;
        $solde = (float)$enfant->solde_impaye;
    ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-4 border-b border-slate-200 bg-slate-50">
            <?php if (!empty($enfant->photo)): ?>
                <img src="<?= BASE_URL ?>/<?= htmlspecialchars($enfant->photo, ENT_QUOTES) ?>"
                     class="w-11 h-11 rounded-full object-cover border border-white shadow-sm flex-shrink-0">
            <?php else: ?>
                <div class="w-11 h-11 rounded-full bg-violet-100 flex items-center justify-center flex-shrink-0">
                    <i data-lucide="user" class="w-5 h-5 text-violet-600"></i>
                </div>
            <?php endif; ?>
            <div class="min-w-0">
                <p class="font-semibold text-sm text-slate-900 truncate"><?= htmlspecialchars($enfant->prenom . ' ' . $enfant->nom, ENT_QUOTES) ?></p>
                <p class="text-xs text-slate-400 truncate"><?= htmlspecialchars(($enfant->classe_niveau ?? '') . ' — ' . ($enfant->classe_nom ?? ''), ENT_QUOTES) ?></p>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-2 px-5 py-4">
            <div class="rounded-lg bg-violet-50 text-center py-2.5">
                <p class="text-lg font-black text-violet-600"><?= $moy !== null ? number_format((float)$moy, 2) : '—' ?></p>
                <p class="text-[11px] text-slate-400 mt-0.5">Moyenne</p>
            </div>
            <div class="rounded-lg bg-red-50 text-center py-2.5">
                <p class="text-lg font-black text-red-500"><?= $abs ?></p>
                <p class="text-[11px] text-slate-400 mt-0.5">Abs. ce mois</p>
            </div>
            <div class="rounded-lg <?= $solde > 0 ? 'bg-amber-50' : 'bg-emerald-50' ?> text-center py-2.5">
                <p class="text-lg font-black <?= $solde > 0 ? 'text-amber-600' : 'text-emerald-600' ?>"><?= number_format($solde, 0, ',', ' ') ?></p>
                <p class="text-[11px] text-slate-400 mt-0.5">Impayé (FCFA)</p>
            </div>
        </div>

        <div class="flex items-center gap-2 px-5 pb-4">
            <a href="<?= BASE_URL ?>/parent/notes?eleve_id=<?= $enfant->id ?>" class="btn btn-outline btn-sm flex-1 justify-center">
                <i data-lucide="file-text" class="w-3.5 h-3.5"></i>Notes
            </a>
            <a href="<?= BASE_URL ?>/parent/absences?eleve_id=<?= $enfant->id ?>" class="btn btn-outline-danger btn-sm flex-1 justify-center">
                <i data-lucide="calendar-x" class="w-3.5 h-3.5"></i>Absences
            </a>
            <a href="<?= BASE_URL ?>/v2/finance/mes-paiements?eleve_id=<?= $enfant->id ?>" class="btn btn-outline-success btn-sm flex-1 justify-center">
                <i data-lucide="wallet" class="w-3.5 h-3.5"></i>Scolarité
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Notifications + Annonces -->
<div class="grid grid-cols-1 lg:grid-cols-5 gap-4">

    <!-- Notifications -->
    <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <div class="w-7 h-7 rounded-lg bg-amber-100 flex items-center justify-center">
                <i data-lucide="bell" class="w-3.5 h-3.5 text-amber-500"></i>
            </div>
            <span class="font-semibold text-slate-700">Notifications</span>
            <a href="<?= BASE_URL ?>/notifications" class="ml-auto text-xs text-violet-600 hover:text-violet-700 font-medium transition-colors">
                Tout voir →
            </a>
        </div>
        <div class="p-3">
            <?php if (empty($notifications)): ?>
            <div class="p-8 text-center">
                <i data-lucide="bell-off" class="w-8 h-8 text-slate-200 mx-auto mb-2"></i>
                <p class="text-sm text-slate-400">Aucune notification</p>
            </div>
            <?php else: ?>
            <div class="space-y-2">
            <?php foreach ($notifications as $n):
                $typeInfo = \App\Models\NotificationModel::TYPES[$n->type] ?? ['icon'=>'info-circle','color'=>'secondary'];
            ?>
            <div class="flex items-start gap-3 rounded-lg border border-slate-100 bg-slate-50/60 p-3 hover:bg-slate-50 hover:border-slate-200 transition-colors">
                <div class="w-8 h-8 rounded-full bg-violet-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <i data-lucide="bell" class="w-3.5 h-3.5 text-violet-600"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-slate-800"><?= htmlspecialchars($n->titre, ENT_QUOTES) ?></p>
                    <?php if ($n->message): ?>
                    <p class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars(mb_substr($n->message, 0, 70), ENT_QUOTES) ?>…</p>
                    <?php endif; ?>
                    <p class="text-[11px] text-slate-300 mt-1"><?= date('d/m H:i', strtotime($n->created_at)) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Annonces -->
    <div class="lg:col-span-3 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <div class="w-7 h-7 rounded-lg bg-violet-100 flex items-center justify-center">
                <i data-lucide="megaphone" class="w-3.5 h-3.5 text-violet-600"></i>
            </div>
            <span class="font-semibold text-slate-700">Annonces de l'école</span>
            <a href="<?= BASE_URL ?>/annonces" class="ml-auto text-xs text-violet-600 hover:text-violet-700 font-medium transition-colors">
                Tout voir →
            </a>
        </div>
        <div class="p-3">
            <?php if (empty($annonces)): ?>
            <div class="p-8 text-center">
                <i data-lucide="megaphone" class="w-8 h-8 text-slate-200 mx-auto mb-2"></i>
                <p class="text-sm text-slate-400">Aucune annonce récente</p>
            </div>
            <?php else: ?>
            <div class="space-y-2">
            <?php foreach ($annonces as $a): ?>
            <div class="rounded-lg border border-slate-100 bg-slate-50/60 p-3 hover:bg-slate-50 hover:border-slate-200 transition-colors">
                <div class="flex items-start justify-between gap-2 mb-1">
                    <p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($a->titre, ENT_QUOTES) ?></p>
                    <span class="text-[11px] text-slate-300 whitespace-nowrap flex-shrink-0"><?= date('d/m/Y', strtotime($a->published_at)) ?></span>
                </div>
                <p class="text-xs text-slate-500"><?= nl2br(htmlspecialchars(mb_substr($a->contenu, 0, 150), ENT_QUOTES)) ?>…</p>
            </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php endif; ?>
