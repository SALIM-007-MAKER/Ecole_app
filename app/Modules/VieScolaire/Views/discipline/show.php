<?php
/** @var array $user */
/** @var array $dossier */
/** @var array $incidents */
/** @var array $sanctions */
/** @var array $appels */
/** @var \App\Modules\VieScolaire\Discipline\Policies\DisciplinePolicy $policy */


$graviteClasses = [
    'mineur'     => 'bg-yellow-100 text-yellow-800',
    'moyen'      => 'bg-orange-100 text-orange-800',
    'grave'      => 'bg-red-100 text-red-700',
    'tres_grave' => 'bg-red-200 text-red-900 font-bold',
];
$sanctionStatutClass = [
    'prononcee' => 'bg-amber-100 text-amber-800',
    'effective' => 'bg-blue-100 text-blue-800',
    'executee'  => 'bg-green-100 text-green-800',
    'levee'     => 'bg-slate-100 text-slate-600',
    'appelee'   => 'bg-purple-100 text-purple-800',
];
?>
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= BASE_URL ?>/v2/vie-scolaire/discipline"
           class="inline-flex items-center gap-2 px-3 py-1.5 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm transition-colors flex-shrink-0">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour
        </a>
        <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
            <i data-lucide="shield-alert" class="w-5 h-5 text-violet-600"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800">
            Dossier — <?= htmlspecialchars($dossier['eleve_prenom'] . ' ' . $dossier['eleve_nom']) ?>
        </h1>
        <?php if ($dossier['statut'] === 'clos'): ?>
            <span class="px-3 py-1 bg-slate-200 text-slate-600 rounded-full text-sm font-medium">Clos</span>
        <?php elseif ($dossier['statut'] === 'en_cours'): ?>
            <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-medium">En cours</span>
        <?php else: ?>
            <span class="px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-sm font-medium">Ouvert</span>
        <?php endif; ?>
    </div>


    <!-- Infos dossier -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6 grid grid-cols-2 md:grid-cols-4 gap-4">
        <div>
            <p class="text-xs text-slate-500">Matricule</p>
            <p class="font-semibold text-slate-800"><?= htmlspecialchars($dossier['eleve_matricule'] ?? '—') ?></p>
        </div>
        <div>
            <p class="text-xs text-slate-500">Classe</p>
            <p class="font-semibold text-slate-800"><?= htmlspecialchars($dossier['classe_nom']) ?></p>
        </div>
        <div>
            <p class="text-xs text-slate-500">Année</p>
            <p class="font-semibold text-slate-800"><?= htmlspecialchars($dossier['annee_scolaire']) ?></p>
        </div>
        <div>
            <p class="text-xs text-slate-500">Nb incidents</p>
            <p class="font-bold text-2xl <?= $dossier['nb_incidents'] >= 3 ? 'text-red-600' : 'text-slate-800' ?>">
                <?= (int)$dossier['nb_incidents'] ?>
            </p>
        </div>
    </div>

    <!-- Actions dossier -->
    <?php if ($policy->canAddIncident($user, $dossier) || $policy->canProposeSanction($user, $dossier) || $policy->canValidate($user)): ?>
        <div class="flex flex-wrap gap-3 mb-6">
            <?php if ($policy->canAddIncident($user, $dossier)): ?>
            <a href="<?= BASE_URL ?>/v2/vie-scolaire/discipline/create?eleve_id=<?= $dossier['eleve_id'] ?>&classe_id=<?= $dossier['classe_id'] ?>&annee=<?= $dossier['annee_scolaire'] ?>"
               class="inline-flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                <i data-lucide="alert-triangle" class="w-4 h-4"></i> Signaler incident
            </a>
            <?php endif; ?>
            <?php if ($policy->canProposeSanction($user, $dossier)): ?>
            <a href="<?= BASE_URL ?>/v2/vie-scolaire/discipline/<?= $dossier['id'] ?>/sanctionner"
               class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                <i data-lucide="gavel" class="w-4 h-4"></i> Prononcer sanction
            </a>
            <?php endif; ?>
            <?php if ($policy->canValidate($user)): ?>
                <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/discipline/<?= $dossier['id'] ?>/cloturer"
                      onsubmit="return confirm('Clôturer définitivement ce dossier ?')">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                    <button type="submit"
                            class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                        <i data-lucide="lock" class="w-4 h-4"></i> Clôturer dossier
                    </button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Incidents -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4 text-orange-500"></i>
                <h2 class="font-semibold text-slate-800">Incidents (<?= count($incidents) ?>)</h2>
            </div>
            <?php if (empty($incidents)): ?>
                <p class="text-slate-400 text-sm text-center py-6">Aucun incident enregistré</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($incidents as $inc): ?>
                        <li class="px-5 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $graviteClasses[$inc['gravite']] ?? 'bg-slate-100 text-slate-600' ?>">
                                            <?= ucfirst(str_replace('_', ' ', $inc['gravite'])) ?>
                                        </span>
                                        <span class="text-xs text-slate-500"><?= htmlspecialchars($inc['categorie_nom'] ?? '') ?></span>
                                    </div>
                                    <p class="text-sm text-slate-700 line-clamp-2"><?= htmlspecialchars($inc['description']) ?></p>
                                    <p class="text-xs text-slate-400 mt-1">
                                        Le <?= date('d/m/Y', strtotime($inc['date_incident'])) ?>
                                        <?php if ($inc['lieu']): ?> — <?= htmlspecialchars($inc['lieu']) ?><?php endif; ?>
                                    </p>
                                </div>
                                <span class="text-xs px-2 py-0.5 rounded <?= $inc['statut'] === 'traite' ? 'bg-green-100 text-green-700' : ($inc['statut'] === 'classe' ? 'bg-slate-100 text-slate-500' : 'bg-amber-50 text-amber-700') ?>">
                                    <?= ucfirst($inc['statut']) ?>
                                </span>
                            </div>
                            <?php if ($inc['statut'] === 'ouvert' && $policy->canUpdate($user)): ?>
                                <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/discipline/incidents/<?= $inc['id'] ?>/traiter" class="mt-2">
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                                    <button type="submit" class="text-xs text-blue-600 hover:underline">Marquer traité</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Sanctions -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <i data-lucide="gavel" class="w-4 h-4 text-red-500"></i>
                <h2 class="font-semibold text-slate-800">Sanctions (<?= count($sanctions) ?>)</h2>
            </div>
            <?php if (empty($sanctions)): ?>
                <p class="text-slate-400 text-sm text-center py-6">Aucune sanction</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($sanctions as $s): ?>
                        <li class="px-5 py-4">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex-1">
                                    <p class="font-medium text-sm text-slate-800"><?= htmlspecialchars(str_replace('_', ' ', $s['type_sanction'])) ?></p>
                                    <p class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($s['motif']) ?></p>
                                    <?php if ($s['duree_jours']): ?>
                                        <p class="text-xs text-slate-400"><?= $s['duree_jours'] ?> jour(s)</p>
                                    <?php endif; ?>
                                </div>
                                <span class="text-xs px-2 py-0.5 rounded-full <?= $sanctionStatutClass[$s['statut']] ?? 'bg-slate-100' ?>">
                                    <?= ucfirst($s['statut']) ?>
                                </span>
                            </div>
                            <div class="flex gap-4 mt-2">
                                <?php if ($s['statut'] === 'prononcee' && $policy->canValidate($user)): ?>
                                    <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/discipline/sanctions/<?= $s['id'] ?>/valider">
                                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                                        <button type="submit" class="text-xs text-green-600 hover:underline">Valider</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($policy->canAppeal($user, $s)): ?>
                                    <?php $existingAppel = null;
                                    foreach ($appels as $ap) {
                                        if ((int)$ap['sanction_id'] === (int)$s['id']) {
                                            $existingAppel = $ap;
                                            break;
                                        }
                                    } ?>
                                    <?php if ($existingAppel === null): ?>
                                        <a href="<?= BASE_URL ?>/v2/vie-scolaire/discipline/sanctions/<?= $s['id'] ?>/appel"
                                           class="text-xs text-purple-600 hover:underline">Faire appel</a>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-400">Appel: <?= $existingAppel['statut'] ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($policy->canValidate($user) && in_array($s['statut'], ['prononcee','effective','executee'])): ?>
                                    <button onclick="document.getElementById('lever-<?= $s['id'] ?>').classList.toggle('hidden')"
                                            class="text-xs text-red-600 hover:underline">Lever</button>
                                    <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/discipline/sanctions/<?= $s['id'] ?>/lever"
                                          id="lever-<?= $s['id'] ?>" class="hidden mt-1 flex items-center gap-2">
                                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                                        <input type="text" name="motif" placeholder="Motif de levée" required
                                               class="border border-slate-200 rounded px-2 py-1 text-xs w-48">
                                        <button type="submit" class="bg-red-600 text-white text-xs px-2 py-1 rounded">OK</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Appels -->
    <?php if (!empty($appels)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mt-6">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <i data-lucide="scale" class="w-4 h-4 text-purple-500"></i>
                <h2 class="font-semibold text-slate-800">Appels (<?= count($appels) ?>)</h2>
            </div>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($appels as $ap): ?>
                    <li class="px-5 py-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm font-medium text-slate-700">Sanction: <?= htmlspecialchars($ap['type_sanction'] ?? '—') ?></p>
                                <p class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($ap['description']) ?></p>
                                <p class="text-xs text-slate-400 mt-1">Déposé le <?= date('d/m/Y', strtotime($ap['depose_le'])) ?></p>
                                <?php if ($ap['decision']): ?>
                                    <p class="text-xs text-slate-700 mt-1 italic"><?= htmlspecialchars($ap['decision']) ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-purple-100 text-purple-700">
                                <?= ucfirst($ap['statut']) ?>
                            </span>
                        </div>
                        <?php if ($ap['statut'] === 'depose' && $policy->canValidate($user)): ?>
                            <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/discipline/appels/<?= $ap['id'] ?>/traiter"
                                  class="mt-3 grid grid-cols-2 gap-2">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                                <input type="hidden" name="dossier_id" value="<?= $dossier['id'] ?>">
                                <input type="text" name="decision" placeholder="Décision motivée" required
                                       class="col-span-2 border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                <button type="submit" name="statut" value="accepte"
                                        class="bg-green-600 hover:bg-green-700 text-white text-sm py-1.5 rounded-lg">Accepter</button>
                                <button type="submit" name="statut" value="rejete"
                                        class="bg-red-600 hover:bg-red-700 text-white text-sm py-1.5 rounded-lg">Rejeter</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
<script>lucide.createIcons();</script>
