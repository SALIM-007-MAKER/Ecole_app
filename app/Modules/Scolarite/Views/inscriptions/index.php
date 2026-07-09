<?php
$result  = $result  ?? ['data' => [], 'total' => 0, 'page' => 1, 'totalPages' => 1];
$stats   = $stats   ?? ['total' => 0, 'en_attente' => 0, 'validee' => 0, 'rejetee' => 0, 'annulee' => 0];
$filters = $filters ?? null;
$annees  = $annees  ?? [];
$classes = $classes ?? [];
$perms   = \Core\Session::getUser()['permissions'] ?? [];

function statutBadge(string $statut): string {
    return match ($statut) {
        'en_attente' => '<span class="inline-flex items-center gap-1 text-xs font-medium text-amber-700 bg-amber-50 rounded-full px-2 py-0.5"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>En attente</span>',
        'validee'    => '<span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 bg-emerald-50 rounded-full px-2 py-0.5"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Validée</span>',
        'rejetee'    => '<span class="inline-flex items-center gap-1 text-xs font-medium text-red-700 bg-red-50 rounded-full px-2 py-0.5"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>Rejetée</span>',
        'annulee'    => '<span class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 bg-slate-100 rounded-full px-2 py-0.5"><span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>Annulée</span>',
        default      => htmlspecialchars($statut, ENT_QUOTES),
    };
}
?>

<!-- En-tête -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="clipboard-list" class="w-5 h-5 text-violet-500"></i>
            Inscriptions — Scolarité V2
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">
            <?= $stats['total'] ?> inscription(s) · <?= $stats['en_attente'] ?> en attente
        </p>
    </div>
    <?php if (in_array('inscriptions.create', $perms, true)): ?>
    <a href="<?= BASE_URL ?>/v2/scolarite/inscriptions/create" class="btn btn-primary">
        <i data-lucide="plus" class="w-4 h-4"></i>Nouvelle inscription
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

<!-- Stats rapides -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center cursor-pointer hover:border-amber-300 transition"
         onclick="setFilter('statut','en_attente')">
        <p class="text-2xl font-bold text-amber-600"><?= $stats['en_attente'] ?></p>
        <p class="text-xs text-slate-500 mt-0.5">En attente</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center cursor-pointer hover:border-emerald-300 transition"
         onclick="setFilter('statut','validee')">
        <p class="text-2xl font-bold text-emerald-600"><?= $stats['validee'] ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Validées</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center cursor-pointer hover:border-red-300 transition"
         onclick="setFilter('statut','rejetee')">
        <p class="text-2xl font-bold text-red-600"><?= $stats['rejetee'] ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Rejetées</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center cursor-pointer hover:border-slate-300 transition"
         onclick="setFilter('statut','annulee')">
        <p class="text-2xl font-bold text-slate-500"><?= $stats['annulee'] ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Annulées</p>
    </div>
</div>

<!-- Filtres -->
<form method="GET" action="<?= BASE_URL ?>/v2/scolarite/inscriptions" id="filterForm"
      class="flex flex-wrap gap-3 mb-5">
    <input type="text" name="q" placeholder="Nom, prénom, matricule…"
           value="<?= htmlspecialchars($filters?->q ?? '', ENT_QUOTES) ?>"
           class="form-input w-52">
    <select name="statut" class="form-input w-36">
        <option value="">Tous statuts</option>
        <option value="en_attente" <?= ($filters?->statut ?? '') === 'en_attente' ? 'selected' : '' ?>>En attente</option>
        <option value="validee"    <?= ($filters?->statut ?? '') === 'validee'    ? 'selected' : '' ?>>Validées</option>
        <option value="rejetee"    <?= ($filters?->statut ?? '') === 'rejetee'    ? 'selected' : '' ?>>Rejetées</option>
        <option value="annulee"    <?= ($filters?->statut ?? '') === 'annulee'    ? 'selected' : '' ?>>Annulées</option>
    </select>
    <select name="annee_scolaire" class="form-input w-32">
        <option value="">Toutes années</option>
        <?php foreach ($annees as $a): ?>
        <option value="<?= $a ?>" <?= ($filters?->anneeScolaire ?? '') === $a ? 'selected' : '' ?>><?= $a ?></option>
        <?php endforeach; ?>
    </select>
    <select name="classe_id" class="form-input w-44">
        <option value="">Toutes classes</option>
        <?php foreach ($classes as $c): ?>
        <option value="<?= $c->id ?>" <?= ($filters?->classeId ?? '') === (string)$c->id ? 'selected' : '' ?>>
            <?= htmlspecialchars($c->label, ENT_QUOTES) ?>
        </option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-secondary">
        <i data-lucide="search" class="w-4 h-4"></i>Filtrer
    </button>
    <a href="<?= BASE_URL ?>/v2/scolarite/inscriptions" class="btn btn-secondary">
        <i data-lucide="x" class="w-4 h-4"></i>
    </a>
</form>

<!-- Tableau -->
<?php if (empty($result['data'])): ?>
<div class="rounded-xl bg-white border border-slate-200 shadow-sm p-12 text-center text-slate-400">
    <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-3 opacity-40"></i>
    <p>Aucune inscription trouvée.</p>
    <?php if (in_array('inscriptions.create', $perms, true)): ?>
    <a href="<?= BASE_URL ?>/v2/scolarite/inscriptions/create" class="btn btn-primary mt-4 inline-flex">
        <i data-lucide="plus" class="w-4 h-4"></i>Créer une inscription
    </a>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="rounded-xl bg-white border border-slate-200 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-slate-100 bg-slate-50">
                <th class="text-left py-3 px-4 font-semibold text-slate-600">Élève</th>
                <th class="text-left py-3 px-4 font-semibold text-slate-600">Classe</th>
                <th class="text-left py-3 px-4 font-semibold text-slate-600">Année</th>
                <th class="text-left py-3 px-4 font-semibold text-slate-600">Statut</th>
                <th class="text-left py-3 px-4 font-semibold text-slate-600">Date</th>
                <th class="py-3 px-4"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            <?php foreach ($result['data'] as $insc): ?>
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="py-3 px-4">
                    <a href="<?= BASE_URL ?>/v2/scolarite/inscriptions/<?= $insc->id ?>"
                       class="font-medium text-violet-600 hover:underline">
                        <?= htmlspecialchars($insc->eleve_nom . ' ' . $insc->eleve_prenom, ENT_QUOTES) ?>
                    </a>
                    <p class="text-xs text-slate-400 font-mono"><?= htmlspecialchars($insc->eleve_matricule ?? '', ENT_QUOTES) ?></p>
                </td>
                <td class="py-3 px-4 text-slate-600">
                    <?php if ($insc->classe_label): ?>
                    <?= htmlspecialchars($insc->classe_label, ENT_QUOTES) ?>
                    <?php else: ?>
                    <span class="text-slate-400 italic text-xs">Non assignée</span>
                    <?php endif; ?>
                </td>
                <td class="py-3 px-4 font-mono text-sm text-slate-600">
                    <?= htmlspecialchars($insc->annee_scolaire, ENT_QUOTES) ?>
                </td>
                <td class="py-3 px-4">
                    <?= statutBadge($insc->statut) ?>
                </td>
                <td class="py-3 px-4 text-xs text-slate-400">
                    <?= date('d/m/Y', strtotime($insc->created_at)) ?>
                </td>
                <td class="py-3 px-4 text-right">
                    <a href="<?= BASE_URL ?>/v2/scolarite/inscriptions/<?= $insc->id ?>"
                       class="text-xs text-violet-600 hover:underline">Voir</a>
                    <?php if ($insc->statut === 'en_attente' && in_array('inscriptions.update', $perms, true)): ?>
                    <span class="text-slate-300 mx-1">|</span>
                    <a href="<?= BASE_URL ?>/v2/scolarite/inscriptions/<?= $insc->id ?>/edit"
                       class="text-xs text-amber-600 hover:underline">Modifier</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($result['totalPages'] > 1): ?>
<div class="flex items-center justify-between mt-4">
    <p class="text-sm text-slate-500"><?= $result['total'] ?> résultat(s) · page <?= $result['page'] ?>/<?= $result['totalPages'] ?></p>
    <div class="flex gap-1">
        <?php for ($p = 1; $p <= $result['totalPages']; $p++): ?>
        <a href="?<?= http_build_query(array_merge($filters->toArray(), ['page' => $p])) ?>"
           class="w-8 h-8 flex items-center justify-center rounded-lg text-sm
                  <?= $p === $result['page'] ? 'bg-violet-600 text-white' : 'text-slate-600 hover:bg-slate-100' ?>">
            <?= $p ?>
        </a>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
function setFilter(name, value) {
    document.querySelector('[name="' + name + '"]').value = value;
    document.getElementById('filterForm').submit();
}
</script>
