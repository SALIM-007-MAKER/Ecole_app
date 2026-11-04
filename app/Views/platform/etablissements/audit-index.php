<?php
/** @var array $etab; @var bool $indisponible; @var array $filtres, $resultat, $modules, $actions, $acteurs */
if (!empty($indisponible)): ?>
<div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
    <h2 class="text-lg font-bold text-slate-900 mb-2">Journal d'audit</h2>
    <p class="text-sm text-slate-600">Le journal d'audit par établissement n'est pas encore disponible sur cette installation (migration T065 non appliquée).</p>
</div>
<?php return; endif;

$resultats = ['success' => 'Succès', 'failure' => 'Échec'];
$badge = ['success' => 'badge-success', 'failure' => 'badge-danger'];
$sansPage = array_filter($filtres, static fn($v) => $v !== '');
$lien = static fn(int $p): string => BASE_URL . '/platform/etablissements/' . (int)$etab['id'] . '/audit?' . http_build_query($sansPage + ['page' => $p]);
?>
<div class="flex items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="scroll-text" class="w-5 h-5 text-violet-600"></i>Journal d'audit
        </h2>
        <p class="text-sm text-slate-500"><?= (int)$resultat['total'] ?> événement(s) de « <?= htmlspecialchars($etab['nom'], ENT_QUOTES) ?> » — consultation seule, aucune modification possible</p>
    </div>
</div>

<form method="GET" action="<?= BASE_URL ?>/platform/etablissements/<?= (int)$etab['id'] ?>/audit" class="rounded-xl border border-slate-200 bg-white shadow-sm p-4 mb-6 flex flex-wrap items-end gap-3">
    <div>
        <label class="form-label" for="q">Recherche</label>
        <input type="text" id="q" name="q" class="form-input" value="<?= htmlspecialchars($filtres['q'], ENT_QUOTES) ?>" placeholder="Action, entité, n° d'identifiant...">
    </div>
    <div>
        <label class="form-label" for="date_debut">Du</label>
        <input type="date" id="date_debut" name="date_debut" class="form-input" value="<?= htmlspecialchars($filtres['date_debut'], ENT_QUOTES) ?>">
    </div>
    <div>
        <label class="form-label" for="date_fin">Au</label>
        <input type="date" id="date_fin" name="date_fin" class="form-input" value="<?= htmlspecialchars($filtres['date_fin'], ENT_QUOTES) ?>">
    </div>
    <div>
        <label class="form-label" for="user_id">Utilisateur</label>
        <select id="user_id" name="user_id" class="form-input">
            <option value="">Tous</option>
            <?php foreach ($acteurs as $a): ?>
            <option value="<?= (int)$a['id'] ?>" <?= $filtres['user_id'] === (string)$a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['libelle'], ENT_QUOTES) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="form-label" for="module">Module</label>
        <select id="module" name="module" class="form-input">
            <option value="">Tous</option>
            <?php foreach ($modules as $m): ?>
            <option value="<?= htmlspecialchars($m, ENT_QUOTES) ?>" <?= strcasecmp($filtres['module'], $m) === 0 ? 'selected' : '' ?>><?= htmlspecialchars($m, ENT_QUOTES) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="form-label" for="action">Action</label>
        <select id="action" name="action" class="form-input">
            <option value="">Toutes</option>
            <?php foreach ($actions as $a): ?>
            <option value="<?= htmlspecialchars($a, ENT_QUOTES) ?>" <?= strcasecmp($filtres['action'], $a) === 0 ? 'selected' : '' ?>><?= htmlspecialchars($a, ENT_QUOTES) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="form-label" for="resultat">Résultat</label>
        <select id="resultat" name="resultat" class="form-input">
            <option value="">Tous</option>
            <?php foreach ($resultats as $k => $l): ?>
            <option value="<?= $k ?>" <?= $filtres['resultat'] === $k ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-secondary"><i data-lucide="search" class="w-4 h-4"></i>Filtrer</button>
    <a href="<?= BASE_URL ?>/platform/etablissements/<?= (int)$etab['id'] ?>/audit" class="btn btn-outline">Réinitialiser</a>
</form>

<div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-5 py-3 text-left">Date</th>
                <th class="px-5 py-3 text-left">Utilisateur</th>
                <th class="px-5 py-3 text-left">Module</th>
                <th class="px-5 py-3 text-left">Action</th>
                <th class="px-5 py-3 text-left">Entité</th>
                <th class="px-5 py-3 text-left">Résultat</th>
                <th class="px-5 py-3 text-left"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($resultat['rows'] as $l): ?>
            <tr class="hover:bg-slate-50">
                <td class="px-5 py-3 text-slate-500 whitespace-nowrap"><?= htmlspecialchars(substr((string)$l['created_at'], 0, 19), ENT_QUOTES) ?></td>
                <td class="px-5 py-3 text-slate-800"><?= htmlspecialchars((string)($l['acteur_libelle'] ?? ($l['user_id'] ? 'Utilisateur #' . $l['user_id'] : 'Système')), ENT_QUOTES) ?>
                    <span class="text-xs text-slate-400"><?= htmlspecialchars((string)($l['actor_role'] ?? ''), ENT_QUOTES) ?></span></td>
                <td class="px-5 py-3 text-slate-600"><?= htmlspecialchars((string)$l['module'], ENT_QUOTES) ?></td>
                <td class="px-5 py-3 font-mono text-xs text-slate-700"><?= htmlspecialchars((string)$l['action'], ENT_QUOTES) ?></td>
                <td class="px-5 py-3 text-slate-600"><?= htmlspecialchars(trim((string)$l['entite'] . ' ' . ($l['entite_id'] !== null ? '#' . $l['entite_id'] : '')) ?: '—', ENT_QUOTES) ?></td>
                <td class="px-5 py-3"><span class="badge <?= $badge[$l['resultat']] ?? 'badge-secondary' ?>"><?= htmlspecialchars($resultats[$l['resultat']] ?? (string)$l['resultat'], ENT_QUOTES) ?></span></td>
                <td class="px-5 py-3 text-right"><a href="<?= BASE_URL ?>/platform/etablissements/<?= (int)$etab['id'] ?>/audit/<?= (int)$l['id'] ?>" class="text-violet-600 text-xs">Détail</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($resultat['rows'])): ?>
            <tr><td colspan="7" class="px-5 py-6 text-center text-slate-500">Aucun événement ne correspond à ces critères.</td></tr>
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
