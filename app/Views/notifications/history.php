<?php
$logs      = $logs      ?? [];
$stats     = $stats     ?? [];
$types     = $types     ?? [];
$canaux    = $canaux    ?? ['interne'=>'Interne','email'=>'Email','sms'=>'SMS'];
$statuts   = $statuts   ?? ['envoyee'=>'Envoyée','echec'=>'Échec','en_attente'=>'En attente'];
$typeFilter   = $typeFilter   ?? '';
$canalFilter  = $canalFilter  ?? '';
$statutFilter = $statutFilter ?? '';
$csrfToken = \Core\Session::getCsrfToken();

function histStatutBadge(string $s): string {
    return match($s) {
        'envoyee'    => '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-emerald-100 text-emerald-700">Envoyée</span>',
        'echec'      => '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-red-100 text-red-700">Échec</span>',
        'en_attente' => '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-amber-100 text-amber-800">En attente</span>',
        default      => '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600">'.htmlspecialchars($s,ENT_QUOTES).'</span>',
    };
}
function histCanalBadge(string $c): string {
    return match($c) {
        'interne' => '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-violet-100 text-violet-700">Interne</span>',
        'email'   => '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-sky-100 text-sky-700">Email</span>',
        'sms'     => '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-amber-100 text-amber-800">SMS</span>',
        default   => '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600">'.htmlspecialchars($c,ENT_QUOTES).'</span>',
    };
}
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-5">
    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
        <i data-lucide="history" class="w-5 h-5 text-violet-600"></i>Historique des notifications
    </h2>
    <div class="flex items-center gap-2">
        <a href="<?= BASE_URL ?>/notifications" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour
        </a>
        <button onclick="openTestModal()" class="btn btn-primary">
            <i data-lucide="send" class="w-4 h-4"></i>Tester l'envoi
        </button>
    </div>
</div>

<!-- KPI row -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
    <?php
    $kpis = [
        ['val'=>(int)($stats['total']??0),     'label'=>'Total envoyées', 'icon'=>'bell',         'bg'=>'bg-violet-100 text-violet-600'],
        ['val'=>(int)($stats['envoyees']??0),   'label'=>'Réussies',       'icon'=>'check-circle', 'bg'=>'bg-emerald-100 text-emerald-600'],
        ['val'=>(int)($stats['echecs']??0),     'label'=>'Échecs',         'icon'=>'x-circle',     'bg'=>'bg-red-100 text-red-500'],
        ['val'=>(int)($stats['en_attente']??0), 'label'=>'En attente',     'icon'=>'clock',        'bg'=>'bg-amber-100 text-amber-600'],
    ];
    foreach ($kpis as $k): ?>
    <div class="stat-card-v text-center">
        <div class="stat-icon <?= $k['bg'] ?> mx-auto mb-2">
            <i data-lucide="<?= $k['icon'] ?>" class="w-4 h-4"></i>
        </div>
        <p class="text-xl font-bold text-slate-900"><?= $k['val'] ?></p>
        <p class="text-xs text-slate-400"><?= $k['label'] ?></p>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-4">
    <div class="p-5 p-3">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <select name="type" class="form-input text-sm" onchange="this.form.submit()">
                <option value="">Tous les types</option>
                <?php foreach ($types as $k => $lbl): ?>
                <option value="<?= htmlspecialchars($k, ENT_QUOTES) ?>" <?= $typeFilter === $k ? 'selected' : '' ?>>
                    <?= htmlspecialchars($lbl, ENT_QUOTES) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <select name="canal" class="form-input text-sm" onchange="this.form.submit()">
                <option value="">Tous les canaux</option>
                <?php foreach ($canaux as $k => $lbl): ?>
                <option value="<?= htmlspecialchars($k, ENT_QUOTES) ?>" <?= $canalFilter === $k ? 'selected' : '' ?>>
                    <?= htmlspecialchars($lbl, ENT_QUOTES) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <select name="statut" class="form-input text-sm" onchange="this.form.submit()">
                <option value="">Tous les statuts</option>
                <?php foreach ($statuts as $k => $lbl): ?>
                <option value="<?= htmlspecialchars($k, ENT_QUOTES) ?>" <?= $statutFilter === $k ? 'selected' : '' ?>>
                    <?= htmlspecialchars($lbl, ENT_QUOTES) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <a href="<?= BASE_URL ?>/admin/notifications" class="btn btn-ghost text-slate-400">
                <i data-lucide="x" class="w-4 h-4"></i>Réinitialiser
            </a>
        </form>
    </div>
</div>

<!-- Log table -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50 text-sm">
            <thead>
                <tr>
                    <th>Destinataire</th>
                    <th>Type</th>
                    <th>Message</th>
                    <th class="text-center">Canal</th>
                    <th class="text-center">Statut</th>
                    <th>Date</th>
                    <th>Erreur</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="7" class="py-10 text-center text-slate-300">
                    <div class="flex flex-col items-center gap-2">
                        <i data-lucide="inbox" class="w-8 h-8"></i>
                        <span class="text-sm">Aucune notification trouvée</span>
                    </div>
                </td></tr>
            <?php else: ?>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td>
                    <p class="font-semibold text-slate-800 text-xs"><?= htmlspecialchars($log->destinataire_nom ?? $log->user_nom ?? '-', ENT_QUOTES) ?></p>
                    <?php if (!empty($log->destinataire_email)): ?>
                    <p class="text-xs text-slate-400"><?= htmlspecialchars($log->destinataire_email, ENT_QUOTES) ?></p>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600 text-xs"><?= htmlspecialchars($log->type ?? '-', ENT_QUOTES) ?></span>
                </td>
                <td class="max-w-xs">
                    <p class="text-slate-700 truncate" title="<?= htmlspecialchars($log->message ?? '', ENT_QUOTES) ?>">
                        <?= htmlspecialchars(mb_strimwidth($log->message ?? '', 0, 60, '…'), ENT_QUOTES) ?>
                    </p>
                </td>
                <td class="text-center"><?= histCanalBadge($log->canal ?? 'interne') ?></td>
                <td class="text-center"><?= histStatutBadge($log->statut ?? 'en_attente') ?></td>
                <td class="text-xs text-slate-400 whitespace-nowrap">
                    <?= date('d/m/Y H:i', strtotime($log->created_at)) ?>
                </td>
                <td class="max-w-xs">
                    <?php if (!empty($log->erreur)): ?>
                    <span class="text-xs text-red-500 truncate block" title="<?= htmlspecialchars($log->erreur, ENT_QUOTES) ?>">
                        <?= htmlspecialchars(mb_strimwidth($log->erreur, 0, 40, '…'), ENT_QUOTES) ?>
                    </span>
                    <?php else: ?>
                    <span class="text-slate-300 text-xs">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Test send modal -->
<div id="testModal" class="modal-overlay fixed inset-0 z-[9000] hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm [&.active]:flex hidden">
    <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white shadow-2xl max-w-md">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <h3 class="font-semibold text-slate-800 flex items-center gap-2">
                <i data-lucide="send" class="w-4 h-4 text-violet-600"></i>Test d'envoi de notification
            </h3>
            <button onclick="closeTestModal()" class="btn btn-ghost btn-icon text-slate-400">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/admin/notifications/test">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
            <div class="px-5 py-5 space-y-4">
                <div>
                    <label class="form-label">Type de notification</label>
                    <select name="type" class="form-input" required>
                        <?php foreach ($types as $k => $lbl): ?>
                        <option value="<?= htmlspecialchars($k, ENT_QUOTES) ?>"><?= htmlspecialchars($lbl, ENT_QUOTES) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Canal</label>
                    <select name="canal" class="form-input" required>
                        <?php foreach ($canaux as $k => $lbl): ?>
                        <option value="<?= htmlspecialchars($k, ENT_QUOTES) ?>"><?= htmlspecialchars($lbl, ENT_QUOTES) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Message de test</label>
                    <textarea name="message" rows="3" class="form-input w-full" placeholder="Entrez un message…" required></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
                <button type="button" onclick="closeTestModal()" class="btn btn-secondary">Annuler</button>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="send" class="w-4 h-4"></i>Envoyer le test
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openTestModal()  { document.getElementById('testModal').classList.add('active'); }
function closeTestModal() { document.getElementById('testModal').classList.remove('active'); }
document.getElementById('testModal').addEventListener('click', function(e) {
    if (e.target === this) closeTestModal();
});
</script>
