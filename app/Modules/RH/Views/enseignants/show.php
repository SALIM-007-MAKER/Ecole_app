<?php
/** @var array $enseignant */
/** @var array $matieres */
/** @var array $qualifications */
/** @var bool $canUpdate */
/** @var bool $canAssign */

function hShow2(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$statutColors = ['titulaire'=>'emerald','vacataire'=>'blue','remplacant'=>'amber','stagiaire'=>'purple','contractuel'=>'slate'];
$statutLabels = ['titulaire'=>'Titulaire','vacataire'=>'Vacataire','remplacant'=>'Remplaçant','stagiaire'=>'Stagiaire','contractuel'=>'Contractuel'];
$qualLabels   = ['diplome'=>'Diplôme','certification'=>'Certification','formation'=>'Formation','experience'=>'Expérience','autre'=>'Autre'];
$qualColors   = ['diplome'=>'violet','certification'=>'emerald','formation'=>'blue','experience'=>'amber','autre'=>'slate'];

$color     = $statutColors[$enseignant['statut_pedagogique']] ?? 'slate';
$label     = $statutLabels[$enseignant['statut_pedagogique']] ?? $enseignant['statut_pedagogique'];
$isArchived = !empty($enseignant['deleted_at']);
$chargeMax  = (int)($enseignant['charge_horaire_max']      ?? 0);
$chargeAct  = (int)($enseignant['charge_horaire_actuelle'] ?? 0);
$chargePct  = $chargeMax > 0 ? min(100, round($chargeAct / $chargeMax * 100)) : 0;
$chargeColor = $chargePct >= 90 ? 'red' : ($chargePct >= 70 ? 'amber' : 'emerald');
?>

<div class="flex flex-wrap items-start justify-between gap-4 mb-6">
    <div class="flex items-start gap-4">
        <a href="<?= BASE_URL ?>/v2/rh/enseignants"
           class="w-9 h-9 rounded-lg border border-slate-200 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-50 flex-shrink-0 transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
        </a>
        <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
            <i data-lucide="graduation-cap" class="w-5 h-5 text-violet-600"></i>
        </div>
        <div>
            <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                <?= hShow2($enseignant['prenom'] . ' ' . $enseignant['nom']) ?>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $color ?>-100 text-<?= $color ?>-700">
                    <?= hShow2($label) ?>
                </span>
            </h2>
            <p class="text-sm text-slate-500 font-mono mt-0.5"><?= hShow2($enseignant['matricule']) ?></p>
        </div>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <?php if ($canUpdate && !$isArchived): ?>
        <a href="<?= BASE_URL ?>/v2/rh/enseignants/<?= (int)$enseignant['id'] ?>/edit"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
            <i data-lucide="pencil" class="w-4 h-4"></i>Modifier
        </a>
        <form method="POST" action="<?= BASE_URL ?>/v2/rh/enseignants/<?= (int)$enseignant['id'] ?>/archive"
              onsubmit="return confirm('Archiver ce profil enseignant ?')">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
            <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-red-200 bg-white text-sm font-medium text-red-600 hover:bg-red-50 transition-colors">
                <i data-lucide="archive" class="w-4 h-4"></i>Archiver
            </button>
        </form>
        <?php endif; ?>
        <?php if ($isArchived && $canUpdate): ?>
        <form method="POST" action="<?= BASE_URL ?>/v2/rh/enseignants/<?= (int)$enseignant['id'] ?>/restore">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
            <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 transition-colors">
                <i data-lucide="rotate-ccw" class="w-4 h-4"></i>Restaurer
            </button>
        </form>
        <?php endif; ?>
        <!-- Lien vers la fiche employé -->
        <a href="<?= BASE_URL ?>/v2/rh/employes/<?= (int)$enseignant['employe_id'] ?>"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-violet-200 bg-violet-50 text-sm font-medium text-violet-700 hover:bg-violet-100 transition-colors">
            <i data-lucide="user" class="w-4 h-4"></i>Fiche employé
        </a>
    </div>
</div>

<?php if ($flash = \Core\Session::getFlash('success')): ?>
<div class="flex items-center gap-2 p-3 mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm" role="alert">
    <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i><span><?= hShow2($flash) ?></span>
    <button class="ml-auto" onclick="this.closest('[role=alert]').remove()"><i data-lucide="x" class="w-4 h-4"></i></button>
</div>
<?php endif; ?>
<?php if ($flash = \Core\Session::getFlash('error')): ?>
<div class="flex items-center gap-2 p-3 mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm" role="alert">
    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i><span><?= hShow2($flash) ?></span>
    <button class="ml-auto" onclick="this.closest('[role=alert]').remove()"><i data-lucide="x" class="w-4 h-4"></i></button>
</div>
<?php endif; ?>

<?php if ($isArchived): ?>
<div class="flex items-center gap-2 p-3 mb-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
    <i data-lucide="archive" class="w-4 h-4 shrink-0"></i>
    <span>Profil archivé depuis le <?= date('d/m/Y', strtotime($enseignant['deleted_at'])) ?>.</span>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Colonne principale -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Profil pédagogique -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <i data-lucide="graduation-cap" class="w-4 h-4 text-violet-600"></i>
                <h3 class="font-semibold text-slate-900 text-sm">Profil pédagogique</h3>
            </div>
            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Statut</span>
                    <p class="mt-0.5">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $color ?>-100 text-<?= $color ?>-700">
                            <?= hShow2($label) ?>
                        </span>
                    </p>
                </div>
                <?php if ($enseignant['specialite_principale']): ?>
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Spécialité principale</span>
                    <p class="mt-0.5 text-slate-900 font-medium"><?= hShow2($enseignant['specialite_principale']) ?></p>
                </div>
                <?php endif; ?>
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Charge horaire</span>
                    <div class="mt-1 flex items-center gap-2">
                        <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-<?= $chargeColor ?>-500 rounded-full" style="width:<?= $chargePct ?>%"></div>
                        </div>
                        <span class="text-sm font-semibold text-slate-900"><?= $chargeAct ?>h / <?= $chargeMax ?>h</span>
                    </div>
                </div>
                <?php if ($enseignant['date_debut_enseignement']): ?>
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Début d'enseignement</span>
                    <p class="mt-0.5 text-slate-700"><?= date('d/m/Y', strtotime($enseignant['date_debut_enseignement'])) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($enseignant['departement_nom']): ?>
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Département</span>
                    <p class="mt-0.5 text-slate-700"><?= hShow2($enseignant['departement_nom']) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($enseignant['poste_intitule']): ?>
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Poste</span>
                    <p class="mt-0.5 text-slate-700"><?= hShow2($enseignant['poste_intitule']) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($enseignant['notes_pedagogiques']): ?>
                <div class="sm:col-span-2">
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Notes pédagogiques</span>
                    <p class="mt-0.5 text-slate-700"><?= hShow2($enseignant['notes_pedagogiques']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Habilitations matières -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm" id="matieres">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i data-lucide="book-open" class="w-4 h-4 text-violet-600"></i>
                    <h3 class="font-semibold text-slate-900 text-sm">Habilitations matières (<?= count($matieres) ?>)</h3>
                </div>
                <?php if ($canAssign && !$isArchived): ?>
                <button onclick="document.getElementById('assignForm').classList.toggle('hidden')"
                        class="inline-flex items-center gap-1 text-xs text-violet-600 hover:text-violet-800 font-medium">
                    <i data-lucide="settings" class="w-3.5 h-3.5"></i>Gérer
                </button>
                <?php endif; ?>
            </div>

            <!-- Formulaire affectation (caché par défaut) -->
            <?php if ($canAssign && !$isArchived): ?>
            <div id="assignForm" class="hidden border-b border-slate-100 p-5 bg-slate-50">
                <form method="POST" action="<?= BASE_URL ?>/v2/rh/enseignants/<?= (int)$enseignant['id'] ?>/matieres">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                    <p class="text-xs text-slate-500 mb-3">Sélectionnez les matières et les niveaux pour cet enseignant.</p>
                    <div id="matieresList" class="space-y-2 mb-4">
                        <?php foreach ($matieres as $i => $m): ?>
                        <div class="matiere-row flex items-center gap-3 text-sm">
                            <span class="text-slate-700 w-40 truncate" title="<?= hShow2($m['matiere_nom']) ?>"><?= hShow2($m['matiere_nom']) ?></span>
                            <input type="hidden" name="matieres[<?= $i ?>][matiere_id]" value="<?= (int)$m['matiere_id'] ?>">
                            <input type="text" name="matieres[<?= $i ?>][niveaux]" value="<?= hShow2($m['niveaux'] ?? '') ?>"
                                   placeholder="primaire,moyen…"
                                   class="flex-1 rounded border border-slate-200 text-xs px-2 py-1 focus:ring-1 focus:ring-violet-300 focus:outline-none">
                            <button type="button" onclick="this.closest('.matiere-row').remove()" class="text-slate-300 hover:text-red-400">
                                <i data-lucide="x" class="w-4 h-4"></i>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="flex items-center gap-2 mb-4">
                        <select id="addMatiereSelect" class="form-select">
                            <option value="">Ajouter une matière…</option>
                            <?php
                            // Import global matieres array via parent controller vars
                            // In practice the controller passes $matieresPourSelect under 'matieres' in create view
                            // Here we need to pass all matieres - they come from the show controller via $matieres (assigned)
                            // The full list would need to be passed separately; we handle this in the edit view pattern
                            ?>
                        </select>
                    </div>
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition-colors">
                        <i data-lucide="save" class="w-4 h-4"></i>Enregistrer les habilitations
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <?php if (empty($matieres)): ?>
            <div class="py-8 text-center text-slate-400 text-sm">Aucune matière habilitée.</div>
            <?php else: ?>
            <div class="divide-y divide-slate-50">
            <?php foreach ($matieres as $m): ?>
            <div class="px-5 py-3 flex items-center gap-3 text-sm">
                <div class="w-2 h-2 rounded-full bg-violet-400 shrink-0"></div>
                <div class="flex-1">
                    <span class="font-medium text-slate-900"><?= hShow2($m['matiere_nom']) ?></span>
                    <?php if ($m['niveaux']): ?>
                    <span class="ml-2 text-xs text-slate-400"><?= hShow2($m['niveaux']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <?php if ($m['priorite'] == 1): ?>
                    <span class="text-xs bg-violet-100 text-violet-700 px-1.5 py-0.5 rounded-md">Principale</span>
                    <?php endif; ?>
                    <span class="text-xs text-slate-400">Coeff. <?= hShow2((string)($m['coefficient'] ?? '—')) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Qualifications -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm" id="qualifications">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i data-lucide="award" class="w-4 h-4 text-violet-600"></i>
                    <h3 class="font-semibold text-slate-900 text-sm">Qualifications & certifications (<?= count($qualifications) ?>)</h3>
                </div>
                <?php if ($canUpdate && !$isArchived): ?>
                <button onclick="document.getElementById('qualForm').classList.toggle('hidden')"
                        class="inline-flex items-center gap-1 text-xs text-violet-600 hover:text-violet-800 font-medium">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>Ajouter
                </button>
                <?php endif; ?>
            </div>

            <!-- Formulaire ajout qualification -->
            <?php if ($canUpdate && !$isArchived): ?>
            <div id="qualForm" class="hidden border-b border-slate-100 p-5 bg-slate-50">
                <form method="POST" action="<?= BASE_URL ?>/v2/rh/enseignants/<?= (int)$enseignant['id'] ?>/qualifications">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Type *</label>
                            <select name="type" class="w-full rounded border border-slate-200 text-sm px-3 py-1.5 bg-white focus:ring-1 focus:ring-violet-300 focus:outline-none">
                                <?php foreach (['diplome'=>'Diplôme','certification'=>'Certification','formation'=>'Formation','experience'=>'Expérience','autre'=>'Autre'] as $v => $l): ?>
                                <option value="<?= hShow2($v) ?>"><?= hShow2($l) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Intitulé *</label>
                            <input type="text" name="intitule" required
                                   class="w-full rounded border border-slate-200 text-sm px-3 py-1.5 focus:ring-1 focus:ring-violet-300 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Organisme</label>
                            <input type="text" name="organisme"
                                   class="w-full rounded border border-slate-200 text-sm px-3 py-1.5 focus:ring-1 focus:ring-violet-300 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Date d'obtention</label>
                            <input type="date" name="date_obtention"
                                   class="w-full rounded border border-slate-200 text-sm px-3 py-1.5 focus:ring-1 focus:ring-violet-300 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Date d'expiration</label>
                            <input type="date" name="date_expiration"
                                   class="w-full rounded border border-slate-200 text-sm px-3 py-1.5 focus:ring-1 focus:ring-violet-300 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Notes</label>
                            <input type="text" name="notes"
                                   class="w-full rounded border border-slate-200 text-sm px-3 py-1.5 focus:ring-1 focus:ring-violet-300 focus:outline-none">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition-colors">
                            <i data-lucide="save" class="w-4 h-4"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <?php if (empty($qualifications)): ?>
            <div class="py-8 text-center text-slate-400 text-sm">Aucune qualification enregistrée.</div>
            <?php else: ?>
            <div class="divide-y divide-slate-50">
            <?php foreach ($qualifications as $q):
                $qColor = $qualColors[$q['type']] ?? 'slate';
                $qLabel = $qualLabels[$q['type']] ?? $q['type'];
            ?>
            <div class="px-5 py-4 flex items-start gap-3 text-sm">
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-<?= $qColor ?>-100 text-<?= $qColor ?>-700 shrink-0 mt-0.5">
                    <?= hShow2($qLabel) ?>
                </span>
                <div class="flex-1">
                    <div class="font-medium text-slate-900"><?= hShow2($q['intitule']) ?></div>
                    <div class="text-xs text-slate-400 mt-0.5">
                        <?php if ($q['organisme']): ?><?= hShow2($q['organisme']) ?><?php endif; ?>
                        <?php if ($q['date_obtention']): ?>
                        · <?= date('d/m/Y', strtotime($q['date_obtention'])) ?>
                        <?php endif; ?>
                        <?php if ($q['date_expiration']): ?>
                        → <?= date('d/m/Y', strtotime($q['date_expiration'])) ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($q['notes']): ?>
                    <div class="text-xs text-slate-500 mt-1"><?= hShow2($q['notes']) ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($canUpdate && !$isArchived): ?>
                <form method="POST" action="<?= BASE_URL ?>/v2/rh/enseignants/<?= (int)$enseignant['id'] ?>/qualifications/<?= (int)$q['id'] ?>/delete"
                      onsubmit="return confirm('Supprimer cette qualification ?')">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                    <button type="submit" class="text-slate-300 hover:text-red-400 mt-0.5">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Colonne latérale -->
    <div class="space-y-6">

        <!-- Coordonnées rapides -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <i data-lucide="contact" class="w-4 h-4 text-violet-600"></i>
                <h3 class="font-semibold text-slate-900 text-sm">Contact</h3>
            </div>
            <div class="p-5 space-y-3 text-sm">
                <?php if ($enseignant['email_pro']): ?>
                <div class="flex items-center gap-2">
                    <i data-lucide="mail" class="w-4 h-4 text-slate-400 shrink-0"></i>
                    <a href="mailto:<?= hShow2($enseignant['email_pro']) ?>" class="text-violet-600 hover:underline truncate">
                        <?= hShow2($enseignant['email_pro']) ?>
                    </a>
                </div>
                <?php endif; ?>
                <?php if ($enseignant['telephone']): ?>
                <div class="flex items-center gap-2">
                    <i data-lucide="phone" class="w-4 h-4 text-slate-400 shrink-0"></i>
                    <a href="tel:<?= hShow2($enseignant['telephone']) ?>" class="text-violet-600 hover:underline">
                        <?= hShow2($enseignant['telephone']) ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Méta -->
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-xs text-slate-400 space-y-1">
            <div>Profil créé le <?= date('d/m/Y à H:i', strtotime($enseignant['created_at'])) ?></div>
            <div>Modifié le <?= date('d/m/Y à H:i', strtotime($enseignant['updated_at'])) ?></div>
            <div class="pt-2">
                <a href="<?= BASE_URL ?>/v2/rh/employes/<?= (int)$enseignant['employe_id'] ?>"
                   class="text-violet-500 hover:text-violet-700 hover:underline">
                    → Voir la fiche employé complète
                </a>
            </div>
        </div>
    </div>
</div>
