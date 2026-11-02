<?php
/** @var array $filtres, $resultat, $actions, $operateurs, $etablissements; @var bool $peutExporter */
$badge = ['success' => 'badge-success', 'failure' => 'badge-danger', 'denied' => 'badge-warning'];
$libelle = ['success' => 'Succès', 'failure' => 'Échec', 'denied' => 'Refusé'];
$sansPage = array_filter($filtres, static fn($v) => $v !== '');
$lien = static fn(int $p): string => BASE_URL . '/platform/audit?' . http_build_query($sansPage + ['page' => $p]);
$exportUrl = BASE_URL . '/platform/audit/export' . ($sansPage ? '?' . http_build_query($sansPage) : '');
?>
<div class="flex items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="scroll-text" class="w-5 h-5 text-violet-600"></i>Journal d'audit
        </h2>
        <p class="text-sm text-slate-500"><?= (int)$resultat['total'] ?> entrée(s) — journal en ajout seul, non modifiable depuis l'application</p>
    </div>
    <?php if ($peutExporter): ?>
    <a href="<?= htmlspecialchars($exportUrl, ENT_QUOTES) ?>" class="btn btn-secondary"><i data-lucide="download" class="w-4 h-4"></i>Exporter en CSV</a>
    <?php endif; ?>
</div>

<form method="GET" action="<?= BASE_URL ?>/platform/audit" class="rounded-xl border border-slate-200 bg-white shadow-sm p-4 mb-6 flex flex-wrap items-end gap-3">
    <div>
        <label class="form-label" for="q">Recherche</label>
        <input type="text" id="q" name="q" class="form-input" value="<?= htmlspecialchars($filtres['q'], ENT_QUOTES) ?>" placeholder="Action, e-mail, IP, n° d'entité...">
    </div>
    <div>
        <label class="form-label" for="date_from">Du</label>
        <input type="date" id="date_from" name="date_from" class="form-input" value="<?= htmlspecialchars($filtres['date_from'], ENT_QUOTES) ?>">
    </div>
    <div>
        <label class="form-label" for="date_to">Au</label>
        <input type="date" id="date_to" name="date_to" class="form-input" value="<?= htmlspecialchars($filtres['date_to'], ENT_QUOTES) ?>">
    </div>
    <div>
        <label class="form-label" for="operator_id">Opérateur</label>
        <select id="operator_id" name="operator_id" class="form-input">
            <option value="">Tous</option>
            <?php foreach ($operateurs as $o): ?>
            <option value="<?= (int)$o['id'] ?>" <?= $filtres['operator_id'] === (string)$o['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$o['email'], ENT_QUOTES) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="form-label" for="action">Action</label>
        <select id="action" name="action" class="form-input">
            <option value="">Toutes</option>
            <?php foreach ($actions as $a): ?>
            <option value="<?= htmlspecialchars($a, ENT_QUOTES) ?>" <?= $filtres['action'] === $a ? 'selected' : '' ?>><?= htmlspecialchars($a, ENT_QUOTES) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="form-label" for="etablissement_id">Établissement</label>
        <select id="etablissement_id" name="etablissement_id" class="form-input">
            <option value="">Tous</option>
            <?php foreach ($etablissements as $e): ?>
            <option value="<?= (int)$e['id'] ?>" <?= $filtres['etablissement_id'] === (string)$e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['nom'], ENT_QUOTES) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="form-label" for="resultat">Résultat</label>
        <select id="resultat" name="resultat" class="form-input">
            <option value="">Tous</option>
            <?php foreach ($libelle as $k => $l): ?>
            <option value="<?= $k ?>" <?= $filtres['resultat'] === $k ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-secondary"><i data-lucide="search" class="w-4 h-4"></i>Filtrer</button>
    <a href="<?= BASE_URL ?>/platform/audit" class="btn btn-outline">Réinitialiser</a>
</form>

<div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-5 py-3 text-left">Date</th>
                <th class="px-5 py-3 text-left">Acteur</th>
                <th class="px-5 py-3 text-left">Action</th>
                <th class="px-5 py-3 text-left">Établissement</th>
                <th class="px-5 py-3 text-left">Entité</th>
                <th class="px-5 py-3 text-left">Résultat</th>
                <th class="px-5 py-3 text-left">IP</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($resultat['rows'] as $l): ?>
            <tr class="hover:bg-slate-50 cursor-pointer" onclick="location.href='<?= BASE_URL ?>/platform/audit/<?= (int)$l['id'] ?>'">
                <td class="px-5 py-3 text-slate-500 whitespace-nowrap"><?= htmlspecialchars(substr((string)$l['occurred_at'], 0, 19), ENT_QUOTES) ?></td>
                <td class="px-5 py-3 text-slate-800"><?= htmlspecialchars((string)($l['actor_email'] ?? ($l['actor_type'] === 'system' ? 'système' : '—')), ENT_QUOTES) ?>
                    <span class="text-xs text-slate-400"><?= htmlspecialchars((string)($l['actor_niveau'] ?? ''), ENT_QUOTES) ?></span></td>
                <td class="px-5 py-3 font-mono text-xs text-slate-700"><?= htmlspecialchars($l['action'], ENT_QUOTES) ?></td>
                <td class="px-5 py-3 text-slate-600"><?= htmlspecialchars((string)($l['etablissement_nom'] ?? ($l['etablissement_id'] ? '#' . $l['etablissement_id'] : '—')), ENT_QUOTES) ?></td>
                <td class="px-5 py-3 text-slate-600"><?= htmlspecialchars(trim((string)$l['entity_type'] . ' ' . ($l['entity_id'] !== null ? '#' . $l['entity_id'] : '')) ?: '—', ENT_QUOTES) ?></td>
                <td class="px-5 py-3"><span class="badge <?= $badge[$l['resultat']] ?? 'badge-secondary' ?>"><?= htmlspecialchars($libelle[$l['resultat']] ?? $l['resultat'], ENT_QUOTES) ?></span></td>
                <td class="px-5 py-3 font-mono text-xs text-slate-500"><?= htmlspecialchars((string)$l['ip'], ENT_QUOTES) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($resultat['rows'])): ?>
            <tr><td colspan="7" class="px-5 py-6 text-center text-slate-500">Aucune entrée ne correspond à ces critères.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($resultat['pages'] > 1): ?>
<div class="flex items-center justify-between gap-4 mt-4 text-sm text-slate-500">
    <span>Page <?= (int)$resultat['page'] ?> sur <?= (int)$resultat['pages'] ?></span>
    <div class="flex gap-2">
        <?php if ($resultat['page'] > 1): ?>
        <a href="<?= htmlspecialchars($lien($resultat['page'] - 1), ENT_QUOTES) ?>" class="btn btn-outline">Précédent</a>
        <?php endif; ?>
        <?php if ($resultat['page'] < $resultat['pages']): ?>
        <a href="<?= htmlspecialchars($lien($resultat['page'] + 1), ENT_QUOTES) ?>" class="btn btn-outline">Suivant</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
