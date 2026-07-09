<?php
use App\Models\UserModel;

$user  = $user  ?? \Core\Session::getUser();
$stats = $stats ?? [];
$role  = $user['role'] ?? '';
$perms = $user['permissions'] ?? [];

$nom   = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
$today = date('d/m/Y');

function can(array $perms, string $p): bool {
    return in_array($p, $perms, true);
}

$roleColors = [
    'admin'      => 'bg-red-100 text-red-700',
    'directeur'  => 'bg-violet-100 text-violet-700',
    'enseignant' => 'bg-amber-100 text-amber-700',
    'comptable'  => 'bg-emerald-100 text-emerald-700',
    'secretaire' => 'bg-sky-100 text-sky-700',
    'parent'     => 'bg-indigo-100 text-indigo-700',
    'eleve'      => 'bg-slate-100 text-slate-700',
];
$roleClass = $roleColors[$role] ?? 'bg-slate-100 text-slate-700';
?>

<!-- ── Greeting bar ─────────────────────────────────────────── -->
<div class="flex items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3 min-w-0">
        <div class="w-9 h-9 rounded-xl bg-violet-100 flex items-center justify-center shrink-0">
            <i data-lucide="layout-dashboard" class="w-4 h-4 text-violet-600"></i>
        </div>
        <div class="min-w-0">
            <h2 class="text-lg font-bold text-slate-900 leading-tight">Tableau de bord</h2>
            <p class="text-xs text-slate-500 flex items-center gap-1">
                <i data-lucide="calendar" class="w-3 h-3"></i>
                <?= $today ?> &mdash; Bonjour, <strong class="text-slate-700 ml-0.5"><?= htmlspecialchars($nom, ENT_QUOTES) ?></strong>
            </p>
        </div>
    </div>
    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold shrink-0 <?= $roleClass ?>">
        <i data-lucide="shield-check" class="w-3 h-3"></i>
        <?= htmlspecialchars(UserModel::roleLabel($role), ENT_QUOTES) ?>
    </span>
</div>


<?php /* ═══════════════════════════════════════════════════════════
   ADMIN & DIRECTEUR : vue globale
   ═══════════════════════════════════════════════════════════ */ ?>
<?php if (in_array($role, ['admin', 'directeur'], true)): ?>

<!-- Stat cards -->
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">

    <div class="stat-card-v">
        <div class="stat-icon" style="background:#ede9fe; color:#7c3aed">
            <i data-lucide="users" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value"><?= $stats['eleves'] ?? 0 ?></div>
            <div class="stat-label">Élèves inscrits</div>
        </div>
    </div>

    <div class="stat-card-v">
        <div class="stat-icon" style="background:#dcfce7; color:#16a34a">
            <i data-lucide="user-check" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value"><?= $stats['enseignants'] ?? 0 ?></div>
            <div class="stat-label">Enseignants</div>
        </div>
    </div>

    <div class="stat-card-v">
        <div class="stat-icon" style="background:#e0f2fe; color:#0284c7">
            <i data-lucide="building-2" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value"><?= $stats['classes'] ?? 0 ?></div>
            <div class="stat-label">Classes actives</div>
        </div>
    </div>

    <div class="stat-card-v">
        <div class="stat-icon" style="background:#fef3c7; color:#d97706">
            <i data-lucide="calendar-x" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value" style="color:#d97706"><?= $stats['absences_today'] ?? 0 ?></div>
            <div class="stat-label">Absences aujourd'hui</div>
        </div>
    </div>

</div>

<?php
/* Données graphiques */
$frMois      = ['','Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'];
$moisAbsMap  = [];
for ($i = 5; $i >= 0; $i--) {
    $ts  = strtotime("-{$i} months");
    $key = (int)date('n', $ts) . '-' . (int)date('Y', $ts);
    $moisAbsMap[$key] = ['label' => $frMois[(int)date('n', $ts)], 'n' => 0];
}
foreach ($stats['absences_mois'] ?? [] as $row) {
    $key = (int)$row->m . '-' . (int)$row->y;
    if (isset($moisAbsMap[$key])) $moisAbsMap[$key]['n'] = (int)$row->n;
}
$absLabels   = json_encode(array_column(array_values($moisAbsMap), 'label'));
$absValues   = json_encode(array_column(array_values($moisAbsMap), 'n'));
$rolesChart  = $stats['roles_chart'] ?? [];
$rolesLabels = json_encode(array_keys($rolesChart));
$rolesValues = json_encode(array_values($rolesChart));
$nbActifs    = (int)($stats['eleves_actifs'] ?? 0);
$nbInactifs  = max(0, (int)($stats['eleves'] ?? 0) - $nbActifs);
?>

<!-- Graphiques -->
<div class="grid lg:grid-cols-3 gap-5 mb-6">

    <!-- Donut : Actifs / Inactifs -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4">
            <i data-lucide="pie-chart" class="w-4 h-4 text-violet-600"></i>
            <span class="text-sm font-semibold text-slate-700">Élèves actifs / inactifs</span>
        </div>
        <div class="p-5 flex flex-col items-center gap-4">
            <div style="position:relative;width:148px;height:148px">
                <canvas id="chartElevesDonut"></canvas>
                <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none">
                    <span style="font-size:1.6rem;font-weight:700;color:#0f172a;line-height:1"><?= $stats['eleves'] ?? 0 ?></span>
                    <span style="font-size:.7rem;color:#94a3b8;margin-top:2px">total</span>
                </div>
            </div>
            <div class="flex items-center justify-center gap-5 text-xs">
                <span class="flex items-center gap-1.5">
                    <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#7c3aed;flex-shrink:0"></span>
                    <span class="text-slate-600">Actifs <strong class="text-slate-900"><?= $nbActifs ?></strong></span>
                </span>
                <span class="flex items-center gap-1.5">
                    <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#e2e8f0;flex-shrink:0"></span>
                    <span class="text-slate-600">Inactifs <strong class="text-slate-900"><?= $nbInactifs ?></strong></span>
                </span>
            </div>
        </div>
    </div>

    <!-- Bar horizontal : Communauté scolaire -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4">
            <i data-lucide="bar-chart-horizontal" class="w-4 h-4 text-violet-600"></i>
            <span class="text-sm font-semibold text-slate-700">Communauté scolaire</span>
        </div>
        <div class="px-5 pb-5 pt-3" style="height:192px">
            <canvas id="chartCommunaute"></canvas>
        </div>
    </div>

    <!-- Courbe : Absences sur 6 mois -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4">
            <i data-lucide="trending-up" class="w-4 h-4 text-amber-500"></i>
            <span class="text-sm font-semibold text-slate-700">Absences — 6 derniers mois</span>
        </div>
        <div class="px-5 pb-5 pt-3" style="height:192px">
            <canvas id="chartAbsences"></canvas>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
    Chart.defaults.font.family = "'Inter', ui-sans-serif, system-ui, sans-serif";
    Chart.defaults.color = '#64748b';

    new Chart(document.getElementById('chartElevesDonut'), {
        type: 'doughnut',
        data: {
            labels: ['Actifs', 'Inactifs'],
            datasets: [{ data: [<?= $nbActifs ?>, <?= $nbInactifs ?>], backgroundColor: ['#7c3aed', '#e2e8f0'], borderWidth: 0, hoverOffset: 6 }]
        },
        options: {
            cutout: '72%', responsive: true, maintainAspectRatio: true,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ' ' + ctx.label + ' : ' + ctx.parsed } } }
        }
    });

    new Chart(document.getElementById('chartCommunaute'), {
        type: 'bar',
        data: {
            labels: <?= $rolesLabels ?>,
            datasets: [{ data: <?= $rolesValues ?>, backgroundColor: ['#7c3aed','#0284c7','#16a34a','#d97706','#059669'], borderRadius: 5, borderSkipped: false, barThickness: 14 }]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 11 }, precision: 0 }, border: { display: false } },
                y: { grid: { display: false }, ticks: { font: { size: 11 } }, border: { display: false } }
            }
        }
    });

    new Chart(document.getElementById('chartAbsences'), {
        type: 'line',
        data: {
            labels: <?= $absLabels ?>,
            datasets: [{ data: <?= $absValues ?>, borderColor: '#d97706', backgroundColor: 'rgba(217,119,6,.08)', borderWidth: 2.5, pointRadius: 4, pointBackgroundColor: '#d97706', pointBorderColor: '#fff', pointBorderWidth: 2, fill: true, tension: 0.4 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 11 } }, border: { display: false } },
                y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 11 }, precision: 0 }, border: { display: false }, beginAtZero: true }
            }
        }
    });
})();
</script>

<!-- Main content: 2/3 + sidebar 1/3 -->
<div class="grid lg:grid-cols-3 gap-6 mb-6">

    <!-- Col principale -->
    <div class="lg:col-span-2 flex flex-col gap-6">

        <!-- Vue d'ensemble -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4">
                <i data-lucide="settings-2" class="w-4 h-4 text-violet-600"></i>
                <span class="text-sm font-semibold text-slate-700">Vue d'ensemble</span>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <?php
                    $overviewStats = [
                        [$stats['eleves'] ?? 0,        'Total élèves',     '#ede9fe', '#7c3aed', 'users'],
                        [$stats['eleves_actifs'] ?? 0, 'Élèves actifs',    '#dcfce7', '#16a34a', 'user-check'],
                        [$stats['parents'] ?? 0,       'Parents inscrits', '#e0f2fe', '#0284c7', 'heart-handshake'],
                        [$stats['enseignants'] ?? 0,   'Enseignants',      '#f3e8ff', '#9333ea', 'graduation-cap'],
                    ];
                    foreach ($overviewStats as [$val, $label, $bg, $color, $icon]):
                    ?>
                    <div class="flex flex-col items-center gap-2 p-4 rounded-xl border border-slate-100 bg-slate-50 hover:bg-white hover:border-slate-200 transition-colors">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:<?= $bg ?>">
                            <i data-lucide="<?= $icon ?>" class="w-4 h-4" style="color:<?= $color ?>"></i>
                        </div>
                        <div class="text-2xl font-bold text-slate-900"><?= $val ?></div>
                        <div class="text-xs text-slate-500 text-center"><?= $label ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- Sidebar actions rapides -->
    <div class="flex flex-col gap-4">

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="zap" class="w-4 h-4 text-violet-600"></i>
                <span class="font-semibold text-slate-700">Actions rapides</span>
            </div>
            <div class="p-5 p-3 space-y-1.5">
                <?php if (can($perms, 'eleves.create')): ?>
                <a href="<?= BASE_URL ?>/eleves/create"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-700 hover:bg-violet-50 hover:text-violet-700 transition-colors group">
                    <div class="w-7 h-7 rounded-md bg-violet-100 flex items-center justify-center group-hover:bg-violet-200 transition-colors">
                        <i data-lucide="user-plus" class="w-3.5 h-3.5 text-violet-600"></i>
                    </div>
                    Nouvel élève
                </a>
                <?php endif; ?>
                <?php if (can($perms, 'notes.create')): ?>
                <a href="<?= BASE_URL ?>/notes/controles/create"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-700 hover:bg-emerald-50 hover:text-emerald-700 transition-colors group">
                    <div class="w-7 h-7 rounded-md bg-emerald-100 flex items-center justify-center group-hover:bg-emerald-200 transition-colors">
                        <i data-lucide="pencil-line" class="w-3.5 h-3.5 text-emerald-600"></i>
                    </div>
                    Saisir des notes
                </a>
                <?php endif; ?>
                <?php if (can($perms, 'absences.create')): ?>
                <a href="<?= BASE_URL ?>/absences/create"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-700 hover:bg-amber-50 hover:text-amber-700 transition-colors group">
                    <div class="w-7 h-7 rounded-md bg-amber-100 flex items-center justify-center group-hover:bg-amber-200 transition-colors">
                        <i data-lucide="calendar-plus" class="w-3.5 h-3.5 text-amber-600"></i>
                    </div>
                    Signaler une absence
                </a>
                <?php endif; ?>
                <?php if (can($perms, 'classes.view')): ?>
                <a href="<?= BASE_URL ?>/classes"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-700 hover:bg-sky-50 hover:text-sky-700 transition-colors group">
                    <div class="w-7 h-7 rounded-md bg-sky-100 flex items-center justify-center group-hover:bg-sky-200 transition-colors">
                        <i data-lucide="layout-grid" class="w-3.5 h-3.5 text-sky-600"></i>
                    </div>
                    Gérer les classes
                </a>
                <?php endif; ?>
                <div class="pt-1 border-t border-slate-100 mt-1">
                    <a href="<?= BASE_URL ?>/reporting"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition-colors group">
                        <div class="w-7 h-7 rounded-md bg-slate-100 flex items-center justify-center group-hover:bg-slate-200 transition-colors">
                            <i data-lucide="bar-chart-3" class="w-3.5 h-3.5 text-slate-600"></i>
                        </div>
                        Voir les rapports
                    </a>
                </div>
            </div>
        </div>

        <!-- Liens modules -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="grid-2x2" class="w-4 h-4 text-violet-600"></i>
                <span class="font-semibold text-slate-700">Modules</span>
            </div>
            <div class="p-5 p-3">
                <div class="grid grid-cols-2 gap-1.5">
                    <?php
                    $modules = [
                        ['Élèves',       BASE_URL . '/eleves',       'users',         '#ede9fe', '#7c3aed'],
                        ['Enseignants',  BASE_URL . '/professeurs',  'user-check',    '#dcfce7', '#16a34a'],
                        ['Classes',      BASE_URL . '/classes',      'building-2',    '#e0f2fe', '#0284c7'],
                        ['Notes',        BASE_URL . '/notes',        'book-open',     '#f3e8ff', '#9333ea'],
                        ['Absences',     BASE_URL . '/absences',     'calendar-x',    '#fef3c7', '#d97706'],
                        ['Finance',      BASE_URL . '/comptabilite', 'wallet',        '#dcfce7', '#059669'],
                    ];
                    foreach ($modules as [$label, $url, $icon, $bg, $color]):
                    ?>
                    <a href="<?= $url ?>"
                       class="flex flex-col items-center gap-1.5 p-3 rounded-xl border border-slate-100 hover:border-slate-200 hover:bg-slate-50 transition-colors text-center group">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:<?= $bg ?>">
                            <i data-lucide="<?= $icon ?>" class="w-4 h-4" style="color:<?= $color ?>"></i>
                        </div>
                        <span class="text-xs font-medium text-slate-600 group-hover:text-slate-900"><?= $label ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Quick actions grid (bas de page) -->
<h3 class="text-xs font-semibold text-slate-400 uppercase tracking-widest mb-3">Raccourcis</h3>
<div class="grid grid-cols-2 md:grid-cols-4 gap-3">

    <?php if (can($perms, 'eleves.create')): ?>
    <a href="<?= BASE_URL ?>/eleves/create" class="flex flex-col items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white p-5 text-center text-slate-600 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-violet-200 hover:bg-violet-50 hover:text-violet-700 hover:shadow-md">
        <i data-lucide="user-plus" class="w-7 h-7 text-violet-600 mb-2"></i>
        <span class="text-sm font-semibold text-slate-700">Nouvel élève</span>
    </a>
    <?php endif; ?>

    <?php if (can($perms, 'notes.create')): ?>
    <a href="<?= BASE_URL ?>/notes/controles/create" class="flex flex-col items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white p-5 text-center text-slate-600 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-violet-200 hover:bg-violet-50 hover:text-violet-700 hover:shadow-md">
        <i data-lucide="pencil-line" class="w-7 h-7 text-emerald-600 mb-2"></i>
        <span class="text-sm font-semibold text-slate-700">Saisir des notes</span>
    </a>
    <?php endif; ?>

    <?php if (can($perms, 'absences.create')): ?>
    <a href="<?= BASE_URL ?>/absences/create" class="flex flex-col items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white p-5 text-center text-slate-600 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-violet-200 hover:bg-violet-50 hover:text-violet-700 hover:shadow-md">
        <i data-lucide="calendar-plus" class="w-7 h-7 text-amber-600 mb-2"></i>
        <span class="text-sm font-semibold text-slate-700">Signaler une absence</span>
    </a>
    <?php endif; ?>

    <?php if (can($perms, 'classes.view')): ?>
    <a href="<?= BASE_URL ?>/classes" class="flex flex-col items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white p-5 text-center text-slate-600 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-violet-200 hover:bg-violet-50 hover:text-violet-700 hover:shadow-md">
        <i data-lucide="layout-grid" class="w-7 h-7 text-sky-600 mb-2"></i>
        <span class="text-sm font-semibold text-slate-700">Gérer les classes</span>
    </a>
    <?php endif; ?>

</div>


<?php /* ═══════════════════════════════════════════════════════════
   SECRÉTAIRE
   ═══════════════════════════════════════════════════════════ */ ?>
<?php elseif ($role === 'secretaire'): ?>

<div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#ede9fe;color:#7c3aed">
            <i data-lucide="users" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value"><?= $stats['eleves'] ?? 0 ?></div>
            <div class="stat-label">Élèves</div>
        </div>
    </div>
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#e0f2fe;color:#0284c7">
            <i data-lucide="building-2" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value"><?= $stats['classes'] ?? 0 ?></div>
            <div class="stat-label">Classes</div>
        </div>
    </div>
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#fef3c7;color:#d97706">
            <i data-lucide="calendar-x" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value" style="color:#d97706"><?= $stats['absences_today'] ?? 0 ?></div>
            <div class="stat-label">Absences auj.</div>
        </div>
    </div>
</div>

<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-6">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <i data-lucide="zap" class="w-4 h-4 text-violet-600"></i>
        <span class="font-semibold text-slate-700">Accès rapides</span>
    </div>
    <div class="p-5 p-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <a href="<?= BASE_URL ?>/eleves" class="flex flex-col items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white p-5 text-center text-slate-600 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-violet-200 hover:bg-violet-50 hover:text-violet-700 hover:shadow-md">
                <i data-lucide="users" class="w-7 h-7 text-violet-600 mb-2"></i>
                <span class="text-sm font-semibold text-slate-700">Liste des élèves</span>
            </a>
            <a href="<?= BASE_URL ?>/eleves/create" class="flex flex-col items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white p-5 text-center text-slate-600 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-violet-200 hover:bg-violet-50 hover:text-violet-700 hover:shadow-md">
                <i data-lucide="user-plus" class="w-7 h-7 text-emerald-600 mb-2"></i>
                <span class="text-sm font-semibold text-slate-700">Inscrire un élève</span>
            </a>
            <a href="<?= BASE_URL ?>/absences/create" class="flex flex-col items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white p-5 text-center text-slate-600 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-violet-200 hover:bg-violet-50 hover:text-violet-700 hover:shadow-md">
                <i data-lucide="calendar-plus" class="w-7 h-7 text-amber-600 mb-2"></i>
                <span class="text-sm font-semibold text-slate-700">Signaler absence</span>
            </a>
            <a href="<?= BASE_URL ?>/classes" class="flex flex-col items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white p-5 text-center text-slate-600 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-violet-200 hover:bg-violet-50 hover:text-violet-700 hover:shadow-md">
                <i data-lucide="layout-grid" class="w-7 h-7 text-sky-600 mb-2"></i>
                <span class="text-sm font-semibold text-slate-700">Voir les classes</span>
            </a>
        </div>
    </div>
</div>


<?php /* ═══════════════════════════════════════════════════════════
   COMPTABLE
   ═══════════════════════════════════════════════════════════ */ ?>
<?php elseif ($role === 'comptable'): ?>

<div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#ede9fe;color:#7c3aed">
            <i data-lucide="users" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value"><?= $stats['eleves'] ?? 0 ?></div>
            <div class="stat-label">Élèves inscrits</div>
        </div>
    </div>
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#e0f2fe;color:#0284c7">
            <i data-lucide="building-2" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value"><?= $stats['classes'] ?? 0 ?></div>
            <div class="stat-label">Classes</div>
        </div>
    </div>
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#dcfce7;color:#16a34a">
            <i data-lucide="trending-up" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value text-emerald-600">&mdash;</div>
            <div class="stat-label">Rapports disponibles</div>
        </div>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6">

    <!-- Module Finance CTA -->
    <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="bar-chart-2" class="w-4 h-4 text-violet-600"></i>
            <span class="font-semibold text-slate-700">Rapports et statistiques financières</span>
        </div>
        <div class="p-5 p-10 flex flex-col items-center text-center">
            <div class="w-16 h-16 rounded-2xl bg-emerald-100 flex items-center justify-center mb-4">
                <i data-lucide="bar-chart-2" class="w-8 h-8 text-emerald-600"></i>
            </div>
            <p class="text-sm text-slate-500 mb-6 max-w-xs">
                Les rapports financiers, paiements et dépenses sont disponibles dans le module Finance.
            </p>
            <div class="flex items-center gap-3">
                <a href="<?= BASE_URL ?>/comptabilite" class="btn btn-primary">
                    <i data-lucide="wallet" class="w-4 h-4"></i>Accéder à la Finance
                </a>
                <a href="<?= BASE_URL ?>/eleves" class="btn btn-outline">
                    <i data-lucide="users" class="w-4 h-4"></i>Élèves
                </a>
            </div>
        </div>
    </div>

    <!-- Accès rapides -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="zap" class="w-4 h-4 text-violet-600"></i>
            <span class="font-semibold text-slate-700">Accès rapides</span>
        </div>
        <div class="p-5 p-3 space-y-1.5">
            <?php
            $comptableLinks = [
                ['Tableau de bord finance',  BASE_URL . '/comptabilite',          'wallet',        'emerald'],
                ['Paiements',                BASE_URL . '/comptabilite/paiements','credit-card',   'violet'],
                ['Dépenses',                 BASE_URL . '/comptabilite/depenses', 'receipt',       'amber'],
                ['Rapports',                 BASE_URL . '/reporting',             'bar-chart-3',   'sky'],
                ['Liste des élèves',         BASE_URL . '/eleves',                'users',         'slate'],
            ];
            $comptableColors = [
                'emerald' => ['bg-emerald-100', 'text-emerald-600', 'hover:bg-emerald-50', 'hover:text-emerald-700'],
                'violet'  => ['bg-violet-100',  'text-violet-600',  'hover:bg-violet-50',  'hover:text-violet-700'],
                'amber'   => ['bg-amber-100',   'text-amber-600',   'hover:bg-amber-50',   'hover:text-amber-700'],
                'sky'     => ['bg-sky-100',     'text-sky-600',     'hover:bg-sky-50',     'hover:text-sky-700'],
                'slate'   => ['bg-slate-100',   'text-slate-600',   'hover:bg-slate-50',   'hover:text-slate-900'],
            ];
            foreach ($comptableLinks as [$label, $url, $icon, $c]):
                [$ibg, $ic, $hbg, $htc] = $comptableColors[$c];
            ?>
            <a href="<?= $url ?>"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-700 <?= $hbg ?> <?= $htc ?> transition-colors group">
                <div class="w-7 h-7 rounded-md <?= $ibg ?> flex items-center justify-center transition-colors">
                    <i data-lucide="<?= $icon ?>" class="w-3.5 h-3.5 <?= $ic ?>"></i>
                </div>
                <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

</div>


<?php /* ═══════════════════════════════════════════════════════════
   ENSEIGNANT
   ═══════════════════════════════════════════════════════════ */ ?>
<?php elseif ($role === 'enseignant'): ?>

<?php $prof = $stats['prof'] ?? null; $enseignements = $stats['enseignements'] ?? []; ?>

<!-- Profil enseignant -->
<?php if ($prof): ?>
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-6">
    <div class="p-5 p-5">
        <div class="flex items-center gap-4">
            <?php if (!empty($prof->photo)): ?>
            <img src="<?= BASE_URL ?>/<?= htmlspecialchars($prof->photo, ENT_QUOTES) ?>"
                 class="w-14 h-14 rounded-xl object-cover border-2 border-slate-100 shrink-0" alt="">
            <?php else: ?>
            <div class="w-14 h-14 rounded-xl bg-amber-100 flex items-center justify-center shrink-0">
                <i data-lucide="user" class="w-6 h-6 text-amber-600"></i>
            </div>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
                <div class="font-bold text-slate-900 text-base leading-tight">
                    <?= htmlspecialchars($prof->prenom . ' ' . $prof->nom, ENT_QUOTES) ?>
                </div>
                <?php if (!empty($prof->grade)): ?>
                <div class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($prof->grade, ENT_QUOTES) ?></div>
                <?php endif; ?>
                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-amber-100 text-amber-800 mt-1.5">
                    <?= htmlspecialchars($prof->specialite ?? '', ENT_QUOTES) ?>
                </span>
            </div>
            <a href="<?= BASE_URL ?>/professeurs/<?= $prof->id ?>" class="btn btn-outline shrink-0">
                <i data-lucide="id-card" class="w-4 h-4"></i>Ma fiche
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Stat cards enseignant -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#dcfce7;color:#16a34a">
            <i data-lucide="calendar" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value" style="color:#16a34a"><?= count($enseignements) ?></div>
            <div class="stat-label">Cours cette année</div>
        </div>
    </div>
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#e0f2fe;color:#0284c7">
            <i data-lucide="building-2" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value" style="color:#0284c7"><?= $stats['mes_classes'] ?? 0 ?></div>
            <div class="stat-label">Mes classes</div>
        </div>
    </div>
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#ede9fe;color:#7c3aed">
            <i data-lucide="book-open" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value" style="color:#7c3aed"><?= $stats['mes_matieres'] ?? 0 ?></div>
            <div class="stat-label">Matières</div>
        </div>
    </div>
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#fef3c7;color:#d97706">
            <i data-lucide="users" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value" style="color:#d97706"><?= $stats['mes_eleves'] ?? 0 ?></div>
            <div class="stat-label">Mes élèves</div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Enseignements (2/3) -->
    <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="calendar-days" class="w-4 h-4 text-violet-600"></i>
            <span class="font-semibold text-slate-700">
                Mes enseignements &mdash; <?= date('Y') . '-' . (date('Y') + 1) ?>
            </span>
            <span class="ml-auto inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600"><?= count($enseignements) ?> cours</span>
        </div>
        <?php if (empty($enseignements)): ?>
        <div class="p-5 p-12 flex flex-col items-center text-center">
            <div class="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center mb-4">
                <i data-lucide="calendar-x" class="w-7 h-7 text-slate-400"></i>
            </div>
            <p class="text-sm font-medium text-slate-600 mb-1">Aucun enseignement cette année</p>
            <p class="text-xs text-slate-400">
                <a href="<?= BASE_URL ?>/professeurs" class="text-violet-600 hover:underline">Voir mon profil</a>
                pour plus d'informations.
            </p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50">
                <thead>
                    <tr>
                        <th>Matière</th>
                        <th>Classe</th>
                        <th class="text-center">H/sem</th>
                        <th class="text-center">Élèves</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($enseignements as $en): ?>
                <tr>
                    <td class="font-semibold text-slate-800">
                        <?= htmlspecialchars($en->matiere_nom, ENT_QUOTES) ?>
                    </td>
                    <td>
                        <a href="<?= BASE_URL ?>/classes/<?= $en->classe_id ?>" class="no-underline">
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-sky-100 text-sky-700">
                                <?= htmlspecialchars($en->classe_niveau . ' ' . $en->classe_nom, ENT_QUOTES) ?>
                            </span>
                        </a>
                    </td>
                    <td class="text-center text-slate-500"><?= $en->volume_horaire ?>h</td>
                    <td class="text-center">
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-emerald-100 text-emerald-700"><?= $en->nb_eleves ?></span>
                    </td>
                    <td class="text-center">
                        <a href="<?= BASE_URL ?>/notes/controles/create?classe_id=<?= $en->classe_id ?>&matiere_id=<?= $en->matiere_id ?>"
                           class="btn btn-success p-2 aspect-square px-2.5 py-1.5 text-xs rounded-md" title="Saisir des notes">
                            <i data-lucide="pencil-line" class="w-4 h-4"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Accès rapides enseignant (1/3) -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="zap" class="w-4 h-4 text-violet-600"></i>
            <span class="font-semibold text-slate-700">Accès rapides</span>
        </div>
        <div class="p-5 p-3 space-y-1.5">
            <a href="<?= BASE_URL ?>/notes/controles/create"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-700 hover:bg-emerald-50 hover:text-emerald-700 transition-colors group">
                <div class="w-7 h-7 rounded-md bg-emerald-100 flex items-center justify-center">
                    <i data-lucide="pencil-line" class="w-3.5 h-3.5 text-emerald-600"></i>
                </div>
                Saisir des notes
            </a>
            <a href="<?= BASE_URL ?>/notes"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-700 hover:bg-violet-50 hover:text-violet-700 transition-colors group">
                <div class="w-7 h-7 rounded-md bg-violet-100 flex items-center justify-center">
                    <i data-lucide="book-open-check" class="w-3.5 h-3.5 text-violet-600"></i>
                </div>
                Consulter les notes
            </a>
            <a href="<?= BASE_URL ?>/absences/create"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-700 hover:bg-amber-50 hover:text-amber-700 transition-colors group">
                <div class="w-7 h-7 rounded-md bg-amber-100 flex items-center justify-center">
                    <i data-lucide="calendar-plus" class="w-3.5 h-3.5 text-amber-600"></i>
                </div>
                Signaler une absence
            </a>
            <a href="<?= BASE_URL ?>/absences"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition-colors group">
                <div class="w-7 h-7 rounded-md bg-slate-100 flex items-center justify-center">
                    <i data-lucide="calendar-x" class="w-3.5 h-3.5 text-slate-500"></i>
                </div>
                Voir les absences
            </a>
            <a href="<?= BASE_URL ?>/eleves"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-700 hover:bg-sky-50 hover:text-sky-700 transition-colors group">
                <div class="w-7 h-7 rounded-md bg-sky-100 flex items-center justify-center">
                    <i data-lucide="users" class="w-3.5 h-3.5 text-sky-600"></i>
                </div>
                Liste des élèves
            </a>
            <?php if ($prof): ?>
            <div class="pt-1 border-t border-slate-100 mt-1">
                <a href="<?= BASE_URL ?>/professeurs/<?= $prof->id ?>?tab=historique"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition-colors group">
                    <div class="w-7 h-7 rounded-md bg-slate-100 flex items-center justify-center">
                        <i data-lucide="history" class="w-3.5 h-3.5 text-slate-500"></i>
                    </div>
                    Mon historique
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>


<?php /* ═══════════════════════════════════════════════════════════
   PARENT
   ═══════════════════════════════════════════════════════════ */ ?>
<?php elseif ($role === 'parent'): ?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md group">
        <div class="p-5 p-8 flex flex-col items-center text-center">
            <div class="w-16 h-16 rounded-2xl bg-emerald-100 flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                <i data-lucide="book-open" class="w-8 h-8 text-emerald-600"></i>
            </div>
            <h3 class="font-bold text-slate-900 mb-1">Notes de mon enfant</h3>
            <p class="text-sm text-slate-500 mb-5">Consultez les résultats scolaires, moyennes et classements par matière.</p>
            <a href="<?= BASE_URL ?>/parent/notes" class="btn btn-success">
                <i data-lucide="eye" class="w-4 h-4"></i>Voir les notes
            </a>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md group">
        <div class="p-5 p-8 flex flex-col items-center text-center">
            <div class="w-16 h-16 rounded-2xl bg-amber-100 flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                <i data-lucide="calendar-x" class="w-8 h-8 text-amber-600"></i>
            </div>
            <h3 class="font-bold text-slate-900 mb-1">Absences</h3>
            <p class="text-sm text-slate-500 mb-5">Suivez les absences enregistrées et leur statut de justification.</p>
            <a href="<?= BASE_URL ?>/parent/absences" class="btn btn-warning">
                <i data-lucide="eye" class="w-4 h-4"></i>Voir les absences
            </a>
        </div>
    </div>

</div>

<!-- Info banner parent -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="p-5 p-4 flex items-start gap-4">
        <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center shrink-0">
            <i data-lucide="info" class="w-5 h-5 text-indigo-600"></i>
        </div>
        <div>
            <div class="font-semibold text-slate-800 text-sm">Espace parent</div>
            <div class="text-sm text-slate-500 mt-0.5">
                Vous avez accès aux informations scolaires de votre enfant.
                Pour toute question, contactez la secrétaire de l'établissement.
            </div>
        </div>
    </div>
</div>


<?php /* ═══════════════════════════════════════════════════════════
   ÉLÈVE
   ═══════════════════════════════════════════════════════════ */ ?>
<?php elseif ($role === 'eleve'): ?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md group">
        <div class="p-5 p-8 flex flex-col items-center text-center">
            <div class="w-16 h-16 rounded-2xl bg-violet-100 flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                <i data-lucide="book-open" class="w-8 h-8 text-violet-600"></i>
            </div>
            <h3 class="font-bold text-slate-900 mb-1">Mes notes</h3>
            <p class="text-sm text-slate-500 mb-5">Consultez vos résultats par matière et par trimestre.</p>
            <a href="<?= BASE_URL ?>/eleve/notes" class="btn btn-primary">
                <i data-lucide="eye" class="w-4 h-4"></i>Voir mes notes
            </a>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md group">
        <div class="p-5 p-8 flex flex-col items-center text-center">
            <div class="w-16 h-16 rounded-2xl bg-amber-100 flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                <i data-lucide="calendar-x" class="w-8 h-8 text-amber-600"></i>
            </div>
            <h3 class="font-bold text-slate-900 mb-1">Mes absences</h3>
            <p class="text-sm text-slate-500 mb-5">Suivez votre assiduité et les absences enregistrées.</p>
            <a href="<?= BASE_URL ?>/absences" class="btn btn-warning">
                <i data-lucide="eye" class="w-4 h-4"></i>Voir mes absences
            </a>
        </div>
    </div>

</div>

<!-- Conseil élève -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="p-5 p-4 flex items-start gap-4">
        <div class="w-10 h-10 rounded-xl bg-sky-100 flex items-center justify-center shrink-0">
            <i data-lucide="lightbulb" class="w-5 h-5 text-sky-600"></i>
        </div>
        <div>
            <div class="font-semibold text-slate-800 text-sm">Conseil</div>
            <div class="text-sm text-slate-500 mt-0.5">
                Consultez votre profil pour mettre à jour vos informations de contact.
                En cas de problème, contactez la secrétaire de l'établissement.
            </div>
        </div>
    </div>
</div>

<?php endif; ?>
