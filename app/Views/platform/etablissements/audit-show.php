<?php
/** @var array $ligne */
$joli = static function (?string $json): string {
    if ($json === null || $json === '') { return '—'; }
    $d = json_decode($json, true);
    return $d === null ? $json : (string)json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};
$champs = [
    'Date'        => (string)$ligne['created_at'],
    'Utilisateur' => (string)($ligne['acteur_libelle'] ?? ($ligne['user_id'] ? 'Utilisateur #' . $ligne['user_id'] : 'Système')),
    'Rôle'        => (string)($ligne['actor_role'] ?? '—'),
    'Module'      => (string)$ligne['module'],
    'Action'      => (string)$ligne['action'],
    'Entité'      => trim((string)$ligne['entite'] . ' ' . ($ligne['entite_id'] !== null ? '#' . $ligne['entite_id'] : '')) ?: '—',
    'Résultat'    => ($ligne['resultat'] ?? 'success') === 'failure' ? 'Échec' : 'Succès',
    'Adresse IP'  => (string)($ligne['ip'] ?? '—'),
];
?>
<div class="flex items-center justify-between gap-4 mb-6">
    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
        <i data-lucide="scroll-text" class="w-5 h-5 text-violet-600"></i>Événement #<?= (int)$ligne['id'] ?>
    </h2>
    <a href="<?= BASE_URL ?>/platform/etablissements/<?= (int)$etab['id'] ?>/audit" class="btn btn-outline"><i data-lucide="arrow-left" class="w-4 h-4"></i>Retour au journal</a>
</div>

<div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5 mb-6">
    <dl class="grid grid-cols-2 gap-4 text-sm">
        <?php foreach ($champs as $k => $v): ?>
        <div>
            <dt class="text-xs uppercase text-slate-500"><?= htmlspecialchars($k, ENT_QUOTES) ?></dt>
            <dd class="text-slate-800"><?= htmlspecialchars($v, ENT_QUOTES) ?></dd>
        </div>
        <?php endforeach; ?>
    </dl>
</div>

<div class="grid grid-cols-2 gap-6">
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-2">Ancienne valeur</h3>
        <pre class="text-xs text-slate-700 overflow-x-auto"><?= htmlspecialchars($joli($ligne['avant']), ENT_QUOTES) ?></pre>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-2">Nouvelle valeur</h3>
        <pre class="text-xs text-slate-700 overflow-x-auto"><?= htmlspecialchars($joli($ligne['apres']), ENT_QUOTES) ?></pre>
    </div>
</div>
