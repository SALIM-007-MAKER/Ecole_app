
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center shrink-0">
            <i data-lucide="file-text" class="w-5 h-5 text-violet-600"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-slate-900">Bulletins de notes</h2>
            <p class="text-sm text-slate-500">Résultats, classements et bulletins individuels</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- Sélection bulletins/classement -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="filter" class="w-4 h-4 text-violet-600"></i>
            <span class="font-semibold text-slate-700">Accès aux bulletins</span>
        </div>
        <div class="p-5 p-5 space-y-6">

            <!-- Résultats classe -->
            <div>
                <h3 class="text-sm font-bold text-violet-700 flex items-center gap-2 mb-3">
                    <span class="w-7 h-7 rounded-lg bg-violet-100 flex items-center justify-center shrink-0">
                        <i data-lucide="table-2" class="w-3.5 h-3.5 text-violet-600"></i>
                    </span>
                    Résultats de la classe
                </h3>
                <form method="GET" action="<?= BASE_URL ?>/bulletins/classe" class="space-y-2">
                    <select name="classe_id" class="form-input" required>
                        <option value="">— Sélectionner une classe —</option>
                        <?php foreach ($classes as $cl): ?>
                        <option value="<?= $cl->id ?>">
                            <?= htmlspecialchars($cl->niveau . ' — ' . $cl->nom, ENT_QUOTES) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="periode_id" class="form-input" required>
                        <option value="">— Sélectionner une période —</option>
                        <?php foreach ($periodes as $p): ?>
                        <option value="<?= $p->id ?>">
                            <?= htmlspecialchars($p->nom . ' — ' . $p->annee_scolaire, ENT_QUOTES) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary w-full">
                        <i data-lucide="table-2" class="w-4 h-4"></i>Voir les résultats
                    </button>
                </form>
            </div>

            <hr class="border-slate-100">

            <!-- Classement -->
            <div>
                <h3 class="text-sm font-bold text-amber-600 flex items-center gap-2 mb-3">
                    <span class="w-7 h-7 rounded-lg bg-amber-100 flex items-center justify-center shrink-0">
                        <i data-lucide="trophy" class="w-3.5 h-3.5 text-amber-600"></i>
                    </span>
                    Classement de la classe
                </h3>
                <form method="GET" action="<?= BASE_URL ?>/bulletins/classement" class="space-y-2">
                    <select name="classe_id" class="form-input" required>
                        <option value="">— Sélectionner une classe —</option>
                        <?php foreach ($classes as $cl): ?>
                        <option value="<?= $cl->id ?>">
                            <?= htmlspecialchars($cl->niveau . ' — ' . $cl->nom, ENT_QUOTES) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="periode_id" class="form-input" required>
                        <option value="">— Sélectionner une période —</option>
                        <?php foreach ($periodes as $p): ?>
                        <option value="<?= $p->id ?>">
                            <?= htmlspecialchars($p->nom . ' — ' . $p->annee_scolaire, ENT_QUOTES) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-warning w-full">
                        <i data-lucide="trophy" class="w-4 h-4"></i>Voir le classement
                    </button>
                </form>
            </div>

        </div>
    </div>

    <!-- Guide mentions + liens rapides -->
    <div class="space-y-4">
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="award" class="w-4 h-4 text-amber-500"></i>
                <span class="font-semibold text-slate-700">Guide des mentions</span>
            </div>
            <div class="p-5 p-5">
                <p class="text-sm text-slate-500 mb-4">
                    Les bulletins sont générés automatiquement dès que les notes sont saisies.
                    La moyenne générale est pondérée par les coefficients des matières.
                </p>
                <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white mb-4">
                    <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50 text-sm">
                        <thead><tr>
                            <th>Mention</th><th>Seuil</th><th>Description</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach (\App\Modules\Academique\ValueObjects\MentionValue::thresholds() as $seuil => $m): ?>
                        <tr>
                            <td>
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-<?= $m['css'] ?>-100 text-<?= $m['css'] ?>-700"><?= htmlspecialchars($m['label'], ENT_QUOTES) ?></span>
                            </td>
                            <td class="font-semibold text-slate-700">≥ <?= $seuil ?>/20</td>
                            <td class="text-slate-500">
                                <?= match($m['code']) {
                                    'TB'  => 'Excellence scolaire',
                                    'B'   => 'Très bon niveau',
                                    'AB'  => 'Bon niveau',
                                    'P'   => 'Niveau suffisant',
                                    default => 'Rattrapage requis',
                                } ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="zap" class="w-4 h-4 text-amber-500"></i>
                <span class="font-semibold text-slate-700">Liens rapides</span>
            </div>
            <div class="p-5 p-4 space-y-2">
                <a href="<?= BASE_URL ?>/v2/academique/evaluations" class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-50 transition-colors">
                    <div class="w-8 h-8 rounded-lg bg-violet-100 flex items-center justify-center shrink-0">
                        <i data-lucide="book-open-check" class="w-4 h-4 text-violet-600"></i>
                    </div>
                    <span class="text-sm font-semibold text-slate-700">Gestion des notes et évaluations</span>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300 ml-auto"></i>
                </a>
                <a href="<?= BASE_URL ?>/v2/academique/resultats/moyennes" class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-50 transition-colors">
                    <div class="w-8 h-8 rounded-lg bg-sky-100 flex items-center justify-center shrink-0">
                        <i data-lucide="table-2" class="w-4 h-4 text-sky-600"></i>
                    </div>
                    <span class="text-sm font-semibold text-slate-700">Tableau des moyennes</span>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300 ml-auto"></i>
                </a>
            </div>
        </div>
    </div>
</div>
