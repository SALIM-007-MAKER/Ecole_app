<?php
$currentUser = \Core\Session::getUser();
$perms       = $currentUser['permissions'] ?? [];
function showpp(array $p, string $k): bool { return in_array($k, $p, true); }

$activeTab     = $activeTab     ?? 'infos';
$enseignements = $enseignements ?? [];
$historique    = $historique    ?? [];
$classes       = $classes       ?? [];
$matieres      = $matieres      ?? [];
$annee         = $annee         ?? (date('Y') . '-' . (date('Y') + 1));

$anciennete = $prof->date_recrutement
    ? (int)(new DateTime())->diff(new DateTime($prof->date_recrutement))->y
    : null;
?>

<!-- Breadcrumb + actions -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <nav class="flex items-center gap-2 text-sm text-slate-500">
        <a href="<?= BASE_URL ?>/professeurs" class="hover:text-amber-600 transition-colors">Enseignants</a>
        <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
        <span class="text-slate-800 font-semibold">
            <?= htmlspecialchars($prof->prenom . ' ' . $prof->nom, ENT_QUOTES) ?>
        </span>
    </nav>
    <div class="flex items-center gap-2">
        <?php if (showpp($perms, 'enseignants.edit')): ?>
        <a href="<?= BASE_URL ?>/professeurs/<?= $prof->id ?>/edit" class="btn btn-warning">
            <i data-lucide="pencil" class="w-4 h-4"></i>Modifier
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/professeurs" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>Liste
        </a>
        <?php if (showpp($perms, 'enseignants.delete') && $prof->nb_enseignements == 0): ?>
        <button class="btn btn-outline text-red-500 border-red-200 hover:bg-red-50"
                onclick="document.getElementById('deleteModal').classList.add('active');document.body.style.overflow='hidden'">
            <i data-lucide="trash-2" class="w-4 h-4"></i>Supprimer
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Carte identité enseignant -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-5">
    <div class="p-5 p-5">
        <div class="flex flex-wrap items-center gap-5">
            <?php if (!empty($prof->photo)): ?>
            <img src="<?= BASE_URL ?>/<?= htmlspecialchars($prof->photo, ENT_QUOTES) ?>"
                 class="w-20 h-20 rounded-full object-cover border-4 border-white shadow-sm shrink-0" alt="">
            <?php else: ?>
            <div class="w-20 h-20 rounded-full bg-amber-100 flex items-center justify-center border-4 border-white shadow-sm shrink-0">
                <i data-lucide="user" class="w-10 h-10 text-amber-600"></i>
            </div>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
                <h3 class="text-xl font-bold text-slate-900">
                    <?= htmlspecialchars($prof->prenom . ' ' . $prof->nom, ENT_QUOTES) ?>
                </h3>
                <?php if (!empty($prof->grade)): ?>
                <p class="text-sm font-semibold text-slate-500 mt-0.5">
                    <?= htmlspecialchars($prof->grade, ENT_QUOTES) ?>
                </p>
                <?php endif; ?>
                <div class="flex flex-wrap items-center gap-2 mt-2">
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-amber-100 text-amber-800">
                        <i data-lucide="graduation-cap" class="w-3 h-3 mr-1"></i>
                        <?= htmlspecialchars($prof->specialite, ENT_QUOTES) ?>
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= $prof->actif ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>">
                        <?= $prof->actif ? 'Actif' : 'Inactif' ?>
                    </span>
                </div>
                <div class="flex flex-wrap items-center gap-4 mt-2 text-sm text-slate-400">
                    <?php if (!empty($prof->telephone)): ?>
                    <a href="tel:<?= htmlspecialchars($prof->telephone, ENT_QUOTES) ?>"
                       class="hover:text-violet-600 flex items-center gap-1.5 transition-colors">
                        <i data-lucide="phone" class="w-3.5 h-3.5"></i>
                        <?= htmlspecialchars($prof->telephone, ENT_QUOTES) ?>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($prof->email)): ?>
                    <a href="mailto:<?= htmlspecialchars($prof->email, ENT_QUOTES) ?>"
                       class="hover:text-violet-600 flex items-center gap-1.5 transition-colors">
                        <i data-lucide="mail" class="w-3.5 h-3.5"></i>
                        <?= htmlspecialchars($prof->email, ENT_QUOTES) ?>
                    </a>
                    <?php endif; ?>
                    <?php if ($anciennete !== null): ?>
                    <span class="flex items-center gap-1.5">
                        <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                        <?= $anciennete ?> an(s) d'ancienneté
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex items-center gap-5 shrink-0">
                <div class="text-center">
                    <p class="text-2xl font-bold text-amber-500"><?= count($enseignements) ?></p>
                    <p class="text-xs text-slate-400 mt-0.5">Cours</p>
                </div>
                <div class="w-px h-10 bg-slate-100"></div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-sky-500"><?= $prof->nb_classes ?></p>
                    <p class="text-xs text-slate-400 mt-0.5">Classes</p>
                </div>
                <div class="w-px h-10 bg-slate-100"></div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-violet-600"><?= $prof->nb_matieres ?></p>
                    <p class="text-xs text-slate-400 mt-0.5">Matières</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="flex items-center gap-1 mb-5 border-b border-slate-100">
    <?php foreach ([
        ['infos',         'user-cog',     'Informations', count([])],
        ['enseignements', 'book-open',    'Enseignements', count($enseignements)],
        ['historique',    'clock',        'Historique',    count($historique)],
    ] as [$tab, $icon, $label, $count]): ?>
    <button id="tab-btn-<?= $tab ?>"
            class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors
                   <?= $activeTab === $tab ? 'border-violet-600 text-violet-700' : 'border-transparent text-slate-500 hover:text-slate-700' ?>"
            onclick="switchTab('<?= $tab ?>')">
        <i data-lucide="<?= $icon ?>" class="w-4 h-4 inline-block mr-1.5"></i>
        <?= $label ?>
        <?php if ($count > 0): ?>
        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600 ml-1"><?= $count ?></span>
        <?php endif; ?>
    </button>
    <?php endforeach; ?>
</div>

<!-- Onglet Informations -->
<div id="tab-infos" class="<?= $activeTab !== 'infos' ? 'hidden' : '' ?>">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="user-cog" class="w-4 h-4 text-violet-600"></i>
                <span class="font-semibold text-slate-700">Données personnelles</span>
            </div>
            <div class="p-5 p-0">
                <dl class="divide-y divide-slate-50">
                    <?php
                    $infos = [
                        ['Nom',              $prof->nom],
                        ['Prénom',           $prof->prenom],
                        ['Spécialité',       $prof->specialite],
                        ['Grade',            $prof->grade ?? '—'],
                        ['Date de recrutement', $prof->date_recrutement ? date('d/m/Y', strtotime($prof->date_recrutement)) : '—'],
                        ['Ancienneté',       $anciennete !== null ? $anciennete . ' an(s)' : '—'],
                    ];
                    foreach ($infos as [$lbl, $val]):
                    ?>
                    <div class="flex items-center px-4 py-3">
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide w-44 shrink-0"><?= $lbl ?></dt>
                        <dd class="text-sm font-semibold text-slate-800"><?= htmlspecialchars((string)$val, ENT_QUOTES) ?></dd>
                    </div>
                    <?php endforeach; ?>
                </dl>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                    <i data-lucide="phone" class="w-4 h-4 text-sky-500"></i>
                    <span class="font-semibold text-slate-700">Coordonnées</span>
                </div>
                <div class="p-5 p-4 space-y-3">
                    <?php if (!empty($prof->telephone)): ?>
                    <div>
                        <p class="text-xs text-slate-400 mb-1">Téléphone</p>
                        <a href="tel:<?= htmlspecialchars($prof->telephone, ENT_QUOTES) ?>"
                           class="font-semibold text-sm flex items-center gap-1.5 hover:text-violet-600 transition-colors">
                            <i data-lucide="phone" class="w-3.5 h-3.5 text-slate-400"></i>
                            <?= htmlspecialchars($prof->telephone, ENT_QUOTES) ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($prof->email)): ?>
                    <div>
                        <p class="text-xs text-slate-400 mb-1">Email</p>
                        <a href="mailto:<?= htmlspecialchars($prof->email, ENT_QUOTES) ?>"
                           class="font-semibold text-sm flex items-center gap-1.5 hover:text-violet-600 transition-colors">
                            <i data-lucide="mail" class="w-3.5 h-3.5 text-slate-400"></i>
                            <?= htmlspecialchars($prof->email, ENT_QUOTES) ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($prof->adresse)): ?>
                    <div>
                        <p class="text-xs text-slate-400 mb-1">Adresse</p>
                        <p class="text-sm font-semibold flex items-start gap-1.5">
                            <i data-lucide="map-pin" class="w-3.5 h-3.5 text-slate-400 mt-0.5 shrink-0"></i>
                            <?= nl2br(htmlspecialchars($prof->adresse, ENT_QUOTES)) ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    <?php if (empty($prof->telephone) && empty($prof->email) && empty($prof->adresse)): ?>
                    <p class="text-sm text-slate-400">Aucune coordonnée renseignée.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                    <i data-lucide="key-round" class="w-4 h-4 text-slate-400"></i>
                    <span class="font-semibold text-slate-700">Compte utilisateur</span>
                </div>
                <div class="p-5 p-4">
                    <?php if (!empty($prof->user_email)): ?>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-violet-100 flex items-center justify-center shrink-0">
                            <i data-lucide="user" class="w-5 h-5 text-violet-600"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-sm text-slate-800"><?= htmlspecialchars($prof->user_email, ENT_QUOTES) ?></p>
                            <?php if (!empty($prof->derniere_connexion)): ?>
                            <p class="text-xs text-slate-400 mt-0.5">
                                Dernière connexion : <?= date('d/m/Y H:i', strtotime($prof->derniere_connexion)) ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <p class="text-sm text-slate-400 flex items-center gap-1.5">
                        <i data-lucide="info" class="w-4 h-4 text-sky-400"></i>
                        Aucun compte utilisateur lié.
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Onglet Enseignements -->
<div id="tab-enseignements" class="<?= $activeTab !== 'enseignements' ? 'hidden' : '' ?>">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="book-open" class="w-4 h-4 text-emerald-500"></i>
                <span class="font-semibold text-slate-700">Enseignements <?= htmlspecialchars($annee, ENT_QUOTES) ?></span>
                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-emerald-100 text-emerald-700 ml-auto"><?= count($enseignements) ?></span>
            </div>
            <?php if (empty($enseignements)): ?>
            <div class="p-5 flex flex-col items-center justify-center gap-3 text-center text-slate-500 py-10">
                <i data-lucide="calendar-x" class="w-10 h-10 text-slate-300 mx-auto mb-2"></i>
                <p class="text-sm text-slate-400">Aucun enseignement pour cette année.</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50">
                    <thead><tr>
                        <th>Matière</th>
                        <th>Classe</th>
                        <th class="text-center">Coef.</th>
                        <th class="text-center">H/sem</th>
                        <th class="text-center">Élèves</th>
                        <?php if (showpp($perms, 'enseignants.edit')): ?><th class="text-center w-16">Action</th><?php endif; ?>
                    </tr></thead>
                    <tbody>
                    <?php $totalH = 0; foreach ($enseignements as $en): $totalH += $en->volume_horaire; ?>
                    <tr>
                        <td class="font-semibold text-slate-800"><?= htmlspecialchars($en->matiere_nom, ENT_QUOTES) ?></td>
                        <td>
                            <a href="<?= BASE_URL ?>/classes/<?= $en->classe_id ?>"
                               class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-sky-100 text-sky-700 hover:opacity-80 transition-opacity">
                                <?= htmlspecialchars($en->classe_niveau . ' ' . $en->classe_nom, ENT_QUOTES) ?>
                            </a>
                        </td>
                        <td class="text-center"><span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-violet-100 text-violet-700"><?= number_format($en->coefficient, 1) ?></span></td>
                        <td class="text-center text-slate-500 text-sm"><?= $en->volume_horaire ?>h</td>
                        <td class="text-center"><span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-emerald-100 text-emerald-700"><?= $en->nb_eleves ?></span></td>
                        <?php if (showpp($perms, 'enseignants.edit')): ?>
                        <td class="text-center">
                            <form method="POST" action="<?= BASE_URL ?>/professeurs/<?= $prof->id ?>/retirer-enseignement"
                                  onsubmit="return confirm('Retirer cet enseignement ?')">
                                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                                <input type="hidden" name="enseignement_id" value="<?= $en->id ?>">
                                <button class="btn btn-ghost btn-icon text-red-400 hover:text-red-600" type="submit" title="Retirer">
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-50 font-semibold text-slate-700 text-sm">
                            <td class="px-4 py-2.5" colspan="3">Total</td>
                            <td class="text-center px-4 py-2.5"><?= $totalH ?>h</td>
                            <td colspan="<?= showpp($perms, 'enseignants.edit') ? 2 : 1 ?>"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <?php if (showpp($perms, 'enseignants.edit')): ?>
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="plus-circle" class="w-4 h-4 text-emerald-600"></i>
                <span class="font-semibold text-slate-700">Affecter un enseignement</span>
            </div>
            <div class="p-5 p-4 space-y-3">
                <form method="POST" action="<?= BASE_URL ?>/professeurs/<?= $prof->id ?>/affecter-enseignement">
                    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                    <div>
                        <label class="form-label text-xs" for="matiere_id">Matière</label>
                        <select id="matiere_id" name="matiere_id" class="form-input text-sm" required>
                            <option value="">— Choisir —</option>
                            <?php foreach ($matieres as $m): ?>
                            <option value="<?= $m->id ?>"><?= htmlspecialchars($m->label, ENT_QUOTES) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label text-xs" for="classe_id">Classe</label>
                        <select id="classe_id" name="classe_id" class="form-input text-sm" required>
                            <option value="">— Choisir —</option>
                            <?php foreach ($classes as $c): ?>
                            <option value="<?= $c->id ?>"><?= htmlspecialchars($c->label, ENT_QUOTES) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label text-xs" for="annee_scolaire">Année scolaire</label>
                        <input type="text" id="annee_scolaire" name="annee_scolaire"
                               class="form-input text-sm"
                               value="<?= htmlspecialchars($annee, ENT_QUOTES) ?>"
                               pattern="\d{4}-\d{4}" required>
                    </div>
                    <button type="submit" class="btn btn-success w-full px-2.5 py-1.5 text-xs rounded-md mt-1">
                        <i data-lucide="plus" class="w-4 h-4"></i>Affecter
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Onglet Historique -->
<div id="tab-historique" class="<?= $activeTab !== 'historique' ? 'hidden' : '' ?>">
    <?php if (empty($historique)): ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm py-14 flex flex-col items-center justify-center gap-3 text-center text-slate-500">
        <div class="w-14 h-14 rounded-2xl bg-slate-50 flex items-center justify-center mx-auto mb-3">
            <i data-lucide="clock" class="w-7 h-7 text-slate-300"></i>
        </div>
        <p class="text-slate-400 text-sm">Aucun historique d'enseignement disponible.</p>
    </div>
    <?php else: ?>
    <div class="space-y-4">
        <?php foreach ($historique as $anneeH => $lignes): ?>
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="calendar-check" class="w-4 h-4 text-slate-400"></i>
                <span class="font-semibold text-slate-700">
                    Année scolaire <?= htmlspecialchars($anneeH, ENT_QUOTES) ?>
                </span>
                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600 ml-auto"><?= count($lignes) ?> cours</span>
            </div>
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50">
                    <thead><tr>
                        <th>Matière</th>
                        <th>Classe</th>
                        <th class="text-center">Coef.</th>
                        <th class="text-center">H/sem</th>
                        <th class="text-center">Élèves</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($lignes as $l): ?>
                    <tr>
                        <td class="text-sm text-slate-700"><?= htmlspecialchars($l->matiere_nom, ENT_QUOTES) ?></td>
                        <td><span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600"><?= htmlspecialchars($l->classe_niveau . ' ' . $l->classe_nom, ENT_QUOTES) ?></span></td>
                        <td class="text-center text-sm"><?= number_format($l->coefficient, 1) ?></td>
                        <td class="text-center text-sm text-slate-400"><?= $l->volume_horaire ?>h</td>
                        <td class="text-center text-sm"><?= $l->nb_eleves ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-50 font-semibold text-sm text-slate-700">
                            <td class="px-4 py-2.5" colspan="3">Total</td>
                            <td class="text-center px-4 py-2.5"><?= array_sum(array_column($lignes, 'volume_horaire')) ?>h</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal suppression -->
<?php if (showpp($perms, 'enseignants.delete') && $prof->nb_enseignements == 0): ?>
<div id="deleteModal" class="modal-overlay fixed inset-0 z-[9000] hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm [&.active]:flex">
    <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white shadow-2xl" style="max-width:420px">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <h3 class="text-base font-semibold text-slate-950 flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center shrink-0">
                    <i data-lucide="trash-2" class="w-4 h-4 text-red-600"></i>
                </span>
                Supprimer l'enseignant
            </h3>
            <button class="inline-flex rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                    onclick="document.getElementById('deleteModal').classList.remove('active');document.body.style.overflow=''">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div class="px-5 py-5">
            <p class="text-slate-600 text-sm">
                Supprimer définitivement
                <strong class="text-slate-900"><?= htmlspecialchars($prof->prenom . ' ' . $prof->nom, ENT_QUOTES) ?></strong> ?
                Cette action est irréversible.
            </p>
            <div class="alert alert-danger mt-3">
                <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
                <span class="text-sm">Cette action est permanente et ne peut pas être annulée.</span>
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
            <button class="btn btn-secondary"
                    onclick="document.getElementById('deleteModal').classList.remove('active');document.body.style.overflow=''">
                Annuler
            </button>
            <form method="POST" action="<?= BASE_URL ?>/professeurs/<?= $prof->id ?>/delete">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button class="btn btn-danger">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>Supprimer
                </button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function switchTab(tab) {
    ['infos','enseignements','historique'].forEach(t => {
        document.getElementById('tab-' + t).classList.toggle('hidden', t !== tab);
        const btn = document.getElementById('tab-btn-' + t);
        btn.classList.toggle('border-violet-600', t === tab);
        btn.classList.toggle('text-violet-700',   t === tab);
        btn.classList.toggle('border-transparent', t !== tab);
        btn.classList.toggle('text-slate-500',     t !== tab);
    });
}
</script>
