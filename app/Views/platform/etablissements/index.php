<?php
/** @var array $etablissements, $filters, $plans, $canManage */
$statutBadge = [
    'trial' => 'badge-secondary', 'active' => 'badge-success', 'suspended' => 'badge-warning',
    'cancelled' => 'badge-danger', 'archived' => 'badge-secondary',
];
?>
<div class="flex items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="building-2" class="w-5 h-5 text-violet-600"></i>Établissements
        </h2>
        <p class="text-sm text-slate-500"><?= count($etablissements) ?> résultat(s)</p>
    </div>
    <?php if ($canManage): ?>
    <a href="<?= BASE_URL ?>/platform/etablissements/create" class="btn btn-primary"><i data-lucide="plus" class="w-4 h-4"></i>Nouvel établissement</a>
    <?php endif; ?>
</div>

<form method="GET" action="<?= BASE_URL ?>/platform/etablissements" class="rounded-xl border border-slate-200 bg-white shadow-sm p-4 mb-6 flex flex-wrap items-end gap-3">
    <div>
        <label class="form-label" for="q">Recherche</label>
        <input type="text" id="q" name="q" class="form-input" value="<?= htmlspecialchars($filters['search'] ?? '', ENT_QUOTES) ?>" placeholder="Nom, slug, code...">
    </div>
    <div>
        <label class="form-label" for="statut">Statut</label>
        <select id="statut" name="statut" class="form-input">
            <option value="">Tous</option>
            <?php foreach (['trial', 'active', 'suspended', 'cancelled', 'archived'] as $s): ?>
            <option value="<?= $s ?>" <?= ($filters['statut'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="form-label" for="plan_id">Plan</label>
        <select id="plan_id" name="plan_id" class="form-input">
            <option value="">Tous</option>
            <?php foreach ($plans as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= (string)($filters['plan_id'] ?? '') === (string)$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nom'], ENT_QUOTES) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-secondary"><i data-lucide="search" class="w-4 h-4"></i>Filtrer</button>
</form>

<div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-5 py-3 text-left">Établissement</th>
                <th class="px-5 py-3 text-left">Slug</th>
                <th class="px-5 py-3 text-left">Statut</th>
                <th class="px-5 py-3 text-left">Plan</th>
                <th class="px-5 py-3 text-left">Créé le</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($etablissements as $e): ?>
            <tr class="hover:bg-slate-50 cursor-pointer" onclick="location.href='<?= BASE_URL ?>/platform/etablissements/<?= (int)$e['id'] ?>'">
                <td class="px-5 py-3 font-semibold text-slate-800"><?= htmlspecialchars($e['nom'], ENT_QUOTES) ?></td>
                <td class="px-5 py-3 font-mono text-xs text-slate-500"><?= htmlspecialchars($e['slug'], ENT_QUOTES) ?></td>
                <td class="px-5 py-3"><span class="badge <?= $statutBadge[$e['statut']] ?? 'badge-secondary' ?>"><?= htmlspecialchars($e['statut'], ENT_QUOTES) ?></span></td>
                <td class="px-5 py-3 text-slate-600"><?= htmlspecialchars($e['plan_nom'] ?? '—', ENT_QUOTES) ?></td>
                <td class="px-5 py-3 text-slate-500"><?= htmlspecialchars((string)$e['created_at'], ENT_QUOTES) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($etablissements)): ?>
            <tr><td colspan="5" class="px-5 py-6 text-center text-slate-500">Aucun établissement ne correspond à ces critères.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
