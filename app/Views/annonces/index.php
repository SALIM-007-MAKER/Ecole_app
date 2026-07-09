<?php
$annonces    = $annonces    ?? [];
$audiences   = $audiences   ?? [];
$q           = $q           ?? '';
$audFilter   = $audFilter   ?? '';
$canCreate   = $canCreate   ?? false;
$currentUser = \Core\Session::getUser();
$canManage   = in_array($currentUser['role'] ?? '', ['admin','directeur','secretaire','enseignant'], true);

$roleBadge = [
    'tous'        => 'bg-violet-100 text-violet-700',
    'parents'     => 'bg-sky-100 text-sky-700',
    'enseignants' => 'bg-emerald-100 text-emerald-700',
    'eleves'      => 'bg-amber-100 text-amber-700',
];

$threeDaysAgo = strtotime('-3 days');
?>

<!-- Header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="megaphone" class="w-5 h-5 text-violet-600"></i>
            Annonces
            <?php if (!empty($annonces)): ?>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-slate-100 text-slate-600">
                <?= count($annonces) ?>
            </span>
            <?php endif; ?>
        </h2>
        <p class="text-sm text-slate-400 mt-0.5">Communications et annonces publiées</p>
    </div>
    <?php if ($canCreate || $canManage): ?>
    <a href="<?= BASE_URL ?>/annonces/create" class="btn btn-primary">
        <i data-lucide="plus" class="w-4 h-4"></i>Nouvelle annonce
    </a>
    <?php endif; ?>
</div>

<!-- Filtres -->
<?php if ($canManage): ?>
<div class="rounded-xl border border-slate-200 bg-white shadow-sm p-4 mb-5">
    <form method="GET" class="flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-48" style="max-width:320px">
            <label class="form-label">Recherche</label>
            <div class="relative">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES) ?>"
                       placeholder="Titre ou contenu…" class="form-input pl-9">
            </div>
        </div>
        <div class="min-w-36">
            <label class="form-label">Audience</label>
            <select name="audience" class="form-select" onchange="this.form.submit()">
                <option value="">Toute audience</option>
                <?php foreach ($audiences as $k => $aud): ?>
                <option value="<?= htmlspecialchars($k, ENT_QUOTES) ?>" <?= $audFilter === $k ? 'selected' : '' ?>>
                    <?= htmlspecialchars(is_array($aud) ? ($aud['label'] ?? $k) : $aud, ENT_QUOTES) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i data-lucide="search" class="w-4 h-4"></i>Filtrer
            </button>
            <?php if ($q !== '' || $audFilter !== ''): ?>
            <a href="<?= BASE_URL ?>/annonces" class="btn btn-outline">
                <i data-lucide="x" class="w-4 h-4"></i>Réinitialiser
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if (empty($annonces)): ?>
<!-- État vide -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="py-16 text-center">
        <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-4">
            <i data-lucide="megaphone" class="w-8 h-8 text-slate-300"></i>
        </div>
        <p class="font-semibold text-slate-500 text-base mb-1">Aucune annonce</p>
        <?php if ($canCreate || $canManage): ?>
        <p class="text-sm text-slate-400 mb-5">Créez votre première annonce pour l'afficher ici.</p>
        <a href="<?= BASE_URL ?>/annonces/create" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i>Nouvelle annonce
        </a>
        <?php else: ?>
        <p class="text-sm text-slate-400">Aucune annonce publiée pour le moment.</p>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>

<!-- Liste des annonces -->
<div class="space-y-3">
    <?php foreach ($annonces as $ann):
        $isNew    = !empty($ann->created_at) && strtotime($ann->created_at) >= $threeDaysAgo;
        $audience = $ann->audience ?? 'tous';
    ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm hover:shadow-md transition-shadow">
        <div class="p-5">
            <div class="flex items-start gap-4">

                <!-- Icône -->
                <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0 bg-violet-100">
                    <i data-lucide="megaphone" class="w-5 h-5 text-violet-600"></i>
                </div>

                <!-- Contenu -->
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1.5">
                        <h3 class="font-bold text-slate-900 text-sm">
                            <?= htmlspecialchars($ann->titre, ENT_QUOTES) ?>
                        </h3>

                        <!-- Badge audience -->
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
                                     <?= $roleBadge[$audience] ?? 'bg-slate-100 text-slate-600' ?>">
                            <?php
                            $audDef = $audiences[$audience] ?? null;
                            echo htmlspecialchars(is_array($audDef) ? ($audDef['label'] ?? $audience) : ($audDef ?: ucfirst($audience)), ENT_QUOTES);
                            ?>
                        </span>

                        <?php if ($isNew): ?>
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-emerald-100 text-emerald-700">
                            Nouveau
                        </span>
                        <?php endif; ?>
                    </div>

                    <!-- Extrait -->
                    <?php if (!empty($ann->contenu)): ?>
                    <p class="text-sm text-slate-500 line-clamp-2 leading-relaxed">
                        <?= nl2br(htmlspecialchars(mb_strimwidth($ann->contenu, 0, 280, '…'), ENT_QUOTES)) ?>
                    </p>
                    <?php endif; ?>

                    <!-- Méta -->
                    <div class="flex flex-wrap items-center gap-4 mt-3 text-xs text-slate-400">
                        <span class="flex items-center gap-1">
                            <i data-lucide="user" class="w-3 h-3"></i>
                            <?= htmlspecialchars($ann->auteur_nom ?? 'Admin', ENT_QUOTES) ?>
                        </span>
                        <?php if (!empty($ann->created_at)): ?>
                        <span class="flex items-center gap-1">
                            <i data-lucide="calendar" class="w-3 h-3"></i>
                            <?= date('d/m/Y à H:i', strtotime($ann->created_at)) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Actions (staff uniquement) -->
                <?php if ($canManage): ?>
                <div class="flex items-center gap-1 flex-shrink-0">
                    <a href="<?= BASE_URL ?>/annonces/<?= (int)$ann->id ?>/edit"
                       class="btn btn-ghost btn-icon btn-sm text-slate-400 hover:text-violet-600" title="Modifier">
                        <i data-lucide="pencil" class="w-4 h-4"></i>
                    </a>
                    <button onclick="openDelAnnonce(<?= (int)$ann->id ?>, '<?= htmlspecialchars(addslashes($ann->titre), ENT_QUOTES) ?>')"
                            class="btn btn-ghost btn-icon btn-sm text-slate-400 hover:text-red-500" title="Supprimer">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($canManage): ?>
<!-- Modal suppression -->
<div id="delAnnonceModal" style="display:none;position:fixed;inset:0;z-index:9000;
     background:rgba(15,23,42,.6);backdrop-filter:blur(4px);
     align-items:center;justify-content:center;padding:1rem">
    <div class="w-full max-w-sm rounded-xl border border-slate-200 bg-white shadow-2xl">
        <div class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
            <i data-lucide="trash-2" class="w-4 h-4 text-red-500"></i>
            <h3 class="font-semibold text-slate-800">Supprimer l'annonce</h3>
        </div>
        <div class="px-5 py-5">
            <p class="text-sm text-slate-600">
                Voulez-vous supprimer <strong id="annonceName" class="text-slate-900"></strong> ?
                <br><span class="text-xs text-slate-400">Cette action est irréversible.</span>
            </p>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 rounded-b-xl">
            <button onclick="closeDelAnnonce()" class="btn btn-secondary">Annuler</button>
            <form id="delAnnonceForm" method="POST">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                <button type="submit" class="btn btn-danger">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>Supprimer
                </button>
            </form>
        </div>
    </div>
</div>
<script>
function openDelAnnonce(id, name) {
    document.getElementById('annonceName').textContent = name;
    document.getElementById('delAnnonceForm').action = '<?= BASE_URL ?>/annonces/' + id + '/delete';
    var m = document.getElementById('delAnnonceModal');
    m.style.display = 'flex';
}
function closeDelAnnonce() {
    document.getElementById('delAnnonceModal').style.display = 'none';
}
document.getElementById('delAnnonceModal').addEventListener('click', function(e) {
    if (e.target === this) closeDelAnnonce();
});
</script>
<?php endif; ?>

<?php endif; ?>
