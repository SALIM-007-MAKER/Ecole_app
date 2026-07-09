<?php
$grouped = $grouped ?? [];
$stats   = $stats   ?? ['total' => 0, 'niveaux' => 0, 'annees' => 0, 'total_eleves' => 0];
$filters = $filters ?? null;
$niveaux = $niveaux ?? [];

function cpermV2idx(array $p, string $k): bool {
    return in_array($k, $p, true);
}
$perms = \Core\Session::getUser()['permissions'] ?? [];

function fillColorIdx(int $actuel, int $max): string {
    if ($max <= 0) return 'bg-slate-300';
    $pct = $actuel / $max * 100;
    if ($pct >= 90) return 'bg-red-500';
    if ($pct >= 70) return 'bg-amber-400';
    return 'bg-emerald-400';
}
?>

<!-- En-tête -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="building-2" class="w-5 h-5 text-violet-500"></i>
            Classes — Scolarité V2
        </h2>
        <p class="text-sm text-slate-500 mt-0.5"><?= $stats['total'] ?> classe(s) · <?= $stats['total_eleves'] ?> élèves</p>
    </div>
    <?php if (cpermV2idx($perms, 'classes.create')): ?>
    <a href="<?= BASE_URL ?>/v2/scolarite/classes/create" class="btn btn-primary">
        <i data-lucide="plus" class="w-4 h-4"></i>Nouvelle classe
    </a>
    <?php endif; ?>
</div>

<?php $flash = \Core\Session::getFlash(); ?>
<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> mb-4" role="alert">
    <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-triangle' ?>" class="w-4 h-4 shrink-0"></i>
    <span class="flex-1 text-sm"><?= htmlspecialchars($flash['message'], ENT_QUOTES) ?></span>
    <button onclick="this.closest('[role=alert]').remove()" class="ml-auto opacity-60 hover:opacity-100">
        <i data-lucide="x" class="w-4 h-4"></i>
    </button>
</div>
<?php endif; ?>

<!-- Filtres -->
<form method="GET" action="<?= BASE_URL ?>/v2/scolarite/classes" class="flex flex-wrap gap-3 mb-6">
    <input type="text" name="q" placeholder="Rechercher…"
           value="<?= htmlspecialchars($filters?->q ?? '', ENT_QUOTES) ?>"
           class="form-input w-48">
    <select name="niveau" class="form-input w-44">
        <option value="">Tous les niveaux</option>
        <?php foreach ($niveaux as $groupe => $vals): ?>
        <optgroup label="<?= htmlspecialchars($groupe, ENT_QUOTES) ?>">
            <?php foreach ($vals as $v): ?>
            <option value="<?= $v ?>" <?= ($filters?->niveau ?? '') === $v ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
        </optgroup>
        <?php endforeach; ?>
    </select>
    <input type="text" name="annee_scolaire" placeholder="2025-2026"
           value="<?= htmlspecialchars($filters?->anneeScolaire ?? '', ENT_QUOTES) ?>"
           class="form-input w-36">
    <button type="submit" class="btn btn-secondary">
        <i data-lucide="search" class="w-4 h-4"></i>Filtrer
    </button>
    <a href="<?= BASE_URL ?>/v2/scolarite/classes" class="btn btn-secondary">
        <i data-lucide="x" class="w-4 h-4"></i>
    </a>
</form>

<!-- Statistiques globales -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-violet-600"><?= $stats['total'] ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Classes</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-emerald-600"><?= $stats['total_eleves'] ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Élèves inscrits</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-sky-600"><?= $stats['niveaux'] ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Niveaux</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-amber-600"><?= $stats['annees'] ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Années scolaires</p>
    </div>
</div>

<?php if (empty($grouped)): ?>
<div class="rounded-xl bg-white border border-slate-200 shadow-sm p-12 text-center text-slate-400">
    <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-3 opacity-40"></i>
    <p>Aucune classe trouvée.</p>
    <?php if (cpermV2idx($perms, 'classes.create')): ?>
    <a href="<?= BASE_URL ?>/v2/scolarite/classes/create" class="btn btn-primary mt-4 inline-flex">
        <i data-lucide="plus" class="w-4 h-4"></i>Créer la première classe
    </a>
    <?php endif; ?>
</div>
<?php else: ?>

<?php foreach ($grouped as $niveau => $classesDuNiveau): ?>
<div class="mb-8">
    <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2">
        <i data-lucide="layers" class="w-4 h-4"></i>
        <?= htmlspecialchars($niveau, ENT_QUOTES) ?>
        <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
            <?= count($classesDuNiveau) ?>
        </span>
    </h3>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <?php foreach ($classesDuNiveau as $c): ?>
        <?php
            $actuel   = (int)$c->nb_eleves;
            $max      = (int)$c->max_eleves;
            $pct      = $max > 0 ? min(100, round($actuel / $max * 100)) : 0;
            $barColor = fillColorIdx($actuel, $max);
        ?>
        <div class="rounded-xl bg-white border border-slate-200 shadow-sm hover:shadow-md transition-shadow flex flex-col">
            <div class="p-4 flex-1">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-violet-50 flex items-center justify-center">
                        <i data-lucide="building-2" class="w-5 h-5 text-violet-500"></i>
                    </div>
                    <span class="text-xs text-slate-400"><?= htmlspecialchars($c->annee_scolaire ?? '', ENT_QUOTES) ?></span>
                </div>
                <h4 class="font-bold text-slate-900 text-lg tracking-wide">
                    <?= htmlspecialchars($c->niveau . ' ' . $c->nom, ENT_QUOTES) ?>
                </h4>
                <div class="flex items-center gap-3 mt-2 text-sm text-slate-500">
                    <span class="flex items-center gap-1">
                        <i data-lucide="users" class="w-3.5 h-3.5"></i>
                        <?= $actuel ?>/<?= $max ?>
                    </span>
                    <span class="flex items-center gap-1">
                        <i data-lucide="book-open" class="w-3.5 h-3.5"></i>
                        <?= (int)$c->nb_enseignements ?> mat.
                    </span>
                </div>
                <div class="mt-3">
                    <div class="h-1.5 w-full rounded-full bg-slate-100">
                        <div class="h-1.5 rounded-full <?= $barColor ?> transition-all"
                             style="width: <?= $pct ?>%"></div>
                    </div>
                    <p class="text-xs text-slate-400 mt-1"><?= $pct ?>% remplie</p>
                </div>
            </div>
            <div class="flex items-center gap-1 border-t border-slate-100 px-3 py-2">
                <a href="<?= BASE_URL ?>/v2/scolarite/classes/<?= $c->id ?>"
                   class="flex-1 text-center text-xs text-violet-600 hover:text-violet-800 py-1 rounded hover:bg-violet-50 transition">
                    <i data-lucide="eye" class="w-3.5 h-3.5 inline"></i> Voir
                </a>
                <?php if (cpermV2idx($perms, 'classes.update')): ?>
                <a href="<?= BASE_URL ?>/v2/scolarite/classes/<?= $c->id ?>/edit"
                   class="flex-1 text-center text-xs text-amber-600 hover:text-amber-800 py-1 rounded hover:bg-amber-50 transition">
                    <i data-lucide="pencil" class="w-3.5 h-3.5 inline"></i> Modifier
                </a>
                <?php endif; ?>
                <?php if (cpermV2idx($perms, 'classes.delete')): ?>
                <button type="button"
                        onclick="openDeleteModal(<?= $c->id ?>, '<?= htmlspecialchars($c->niveau . ' ' . $c->nom, ENT_JS) ?>', <?= $actuel ?>)"
                        class="flex-1 text-center text-xs text-red-500 hover:text-red-700 py-1 rounded hover:bg-red-50 transition">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5 inline"></i> Supprimer
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Modal suppression -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center">
                <i data-lucide="trash-2" class="w-5 h-5 text-red-500"></i>
            </div>
            <div>
                <h3 class="font-semibold text-slate-900">Supprimer la classe</h3>
                <p id="deleteModalName" class="text-sm text-slate-500"></p>
            </div>
        </div>
        <p id="deleteModalWarning" class="text-sm text-red-600 bg-red-50 rounded-lg p-3 mb-4 hidden">
            Cette classe contient des élèves. Retirez-les avant de supprimer.
        </p>
        <p class="text-sm text-slate-600 mb-5">Cette action est irréversible.</p>
        <form id="deleteForm" method="POST">
            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
            <div class="flex gap-3">
                <button type="submit" id="deleteBtn" class="btn btn-danger flex-1">Supprimer</button>
                <button type="button" onclick="closeDeleteModal()" class="btn btn-secondary flex-1">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script>
function openDeleteModal(id, nom, nbEleves) {
    document.getElementById('deleteModalName').textContent = nom;
    document.getElementById('deleteForm').action = '<?= BASE_URL ?>/v2/scolarite/classes/' + id + '/delete';
    const warn = document.getElementById('deleteModalWarning');
    const btn  = document.getElementById('deleteBtn');
    if (nbEleves > 0) {
        warn.classList.remove('hidden');
        btn.disabled = true;
        btn.classList.add('opacity-50', 'cursor-not-allowed');
    } else {
        warn.classList.add('hidden');
        btn.disabled = false;
        btn.classList.remove('opacity-50', 'cursor-not-allowed');
    }
    document.getElementById('deleteModal').classList.remove('hidden');
    document.getElementById('deleteModal').classList.add('flex');
}
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    document.getElementById('deleteModal').classList.remove('flex');
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});
</script>
