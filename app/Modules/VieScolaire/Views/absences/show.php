<?php
$absence       = $absence       ?? [];
$justification = $justification ?? null;
$perms         = $perms         ?? [];

function epAbsShow(array $p, string $k): bool { return in_array($k, $p, true); }

$statutColors = [
    'non_justifiee' => 'red',
    'en_attente'    => 'amber',
    'justifiee'     => 'emerald',
    'refusee'       => 'slate',
];
$statutLabels = [
    'non_justifiee' => 'Non justifiée',
    'en_attente'    => 'En attente de validation',
    'justifiee'     => 'Justifiée',
    'refusee'       => 'Refusée',
];
$typeLabels = ['absence' => 'Absence', 'retard' => 'Retard', 'dispense' => 'Dispense'];

$statut = $absence['statut'] ?? 'non_justifiee';
$color  = $statutColors[$statut] ?? 'slate';
$label  = $statutLabels[$statut] ?? $statut;
$type   = $typeLabels[$absence['type'] ?? 'absence'] ?? ($absence['type'] ?? '');
$csrfToken = \Core\Session::getCsrfToken();
?>

<!-- Header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <a href="<?= BASE_URL ?>/v2/vie-scolaire/absences"
           class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 mb-2">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour à la liste
        </a>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="calendar-x" class="w-5 h-5 text-violet-600"></i>
            Fiche absence — <?= htmlspecialchars($absence['eleve_prenom'] . ' ' . $absence['eleve_nom'], ENT_QUOTES) ?>
        </h2>
    </div>
    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-<?= $color ?>-100 text-<?= $color ?>-700">
        <?= htmlspecialchars($label, ENT_QUOTES) ?>
    </span>
</div>

<?php if ($flash = \Core\Session::getFlash('success')): ?>
<div class="flex items-center gap-2 p-3 mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm" role="alert">
    <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
    <span><?= htmlspecialchars($flash, ENT_QUOTES) ?></span>
    <button class="ml-auto" onclick="this.closest('[role=alert]').remove()"><i data-lucide="x" class="w-4 h-4"></i></button>
</div>
<?php endif; ?>
<?php if ($flash = \Core\Session::getFlash('error')): ?>
<div class="flex items-center gap-2 p-3 mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm" role="alert">
    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
    <span><?= htmlspecialchars($flash, ENT_QUOTES) ?></span>
    <button class="ml-auto" onclick="this.closest('[role=alert]').remove()"><i data-lucide="x" class="w-4 h-4"></i></button>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Détails absence -->
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-2">
                <i data-lucide="info" class="w-4 h-4 text-violet-500"></i>Détails
            </h3>
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-slate-500">Élève</dt>
                    <dd class="font-medium text-slate-900 mt-0.5">
                        <?= htmlspecialchars($absence['eleve_prenom'] . ' ' . $absence['eleve_nom'], ENT_QUOTES) ?>
                        <?php if (!empty($absence['matricule'])): ?>
                        <span class="text-xs text-slate-400 ml-1">(<?= htmlspecialchars($absence['matricule'], ENT_QUOTES) ?>)</span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500">Classe</dt>
                    <dd class="font-medium text-slate-900 mt-0.5"><?= htmlspecialchars($absence['classe_nom'] ?? '', ENT_QUOTES) ?></dd>
                </div>
                <div>
                    <dt class="text-slate-500">Date</dt>
                    <dd class="font-medium text-slate-900 mt-0.5"><?= date('d/m/Y', strtotime($absence['date_absence'])) ?></dd>
                </div>
                <div>
                    <dt class="text-slate-500">Type</dt>
                    <dd class="font-medium text-slate-900 mt-0.5"><?= htmlspecialchars($type, ENT_QUOTES) ?></dd>
                </div>
                <?php if (!empty($absence['heure_debut'])): ?>
                <div>
                    <dt class="text-slate-500">Horaire</dt>
                    <dd class="font-medium text-slate-900 mt-0.5">
                        <?= htmlspecialchars($absence['heure_debut'], ENT_QUOTES) ?>
                        <?php if (!empty($absence['heure_fin'])): ?>
                        → <?= htmlspecialchars($absence['heure_fin'], ENT_QUOTES) ?>
                        <?php endif; ?>
                    </dd>
                </div>
                <?php endif; ?>
                <?php if (!empty($absence['duree_heures'])): ?>
                <div>
                    <dt class="text-slate-500">Durée</dt>
                    <dd class="font-medium text-slate-900 mt-0.5"><?= number_format($absence['duree_heures'], 1) ?> h</dd>
                </div>
                <?php endif; ?>
                <div>
                    <dt class="text-slate-500">Année scolaire</dt>
                    <dd class="font-medium text-slate-900 mt-0.5"><?= htmlspecialchars($absence['annee_scolaire'] ?? '', ENT_QUOTES) ?></dd>
                </div>
                <div>
                    <dt class="text-slate-500">Saisie par</dt>
                    <dd class="font-medium text-slate-900 mt-0.5"><?= htmlspecialchars($absence['saisie_par_nom'] ?? '', ENT_QUOTES) ?></dd>
                </div>
                <?php if (!empty($absence['observation'])): ?>
                <div class="col-span-2">
                    <dt class="text-slate-500">Observation</dt>
                    <dd class="mt-0.5 text-slate-700"><?= nl2br(htmlspecialchars($absence['observation'], ENT_QUOTES)) ?></dd>
                </div>
                <?php endif; ?>
            </dl>
        </div>

        <!-- Justification -->
        <?php if ($justification): ?>
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-2">
                <i data-lucide="file-check" class="w-4 h-4 text-emerald-500"></i>Justification
            </h3>
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-slate-500">Statut</dt>
                    <dd class="mt-0.5">
                        <?php $jStatut = $justification['statut'];
                        $jColors = ['en_attente' => 'amber', 'validee' => 'emerald', 'refusee' => 'red'];
                        $jLabels = ['en_attente' => 'En attente', 'validee' => 'Validée', 'refusee' => 'Refusée'];
                        ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $jColors[$jStatut] ?? 'slate' ?>-100 text-<?= $jColors[$jStatut] ?? 'slate' ?>-700">
                            <?= htmlspecialchars($jLabels[$jStatut] ?? $jStatut, ENT_QUOTES) ?>
                        </span>
                    </dd>
                </div>
                <?php if (!empty($justification['motif_libelle'])): ?>
                <div>
                    <dt class="text-slate-500">Motif</dt>
                    <dd class="font-medium text-slate-900 mt-0.5"><?= htmlspecialchars($justification['motif_libelle'], ENT_QUOTES) ?></dd>
                </div>
                <?php endif; ?>
                <?php if (!empty($justification['description'])): ?>
                <div class="col-span-2">
                    <dt class="text-slate-500">Description</dt>
                    <dd class="mt-0.5 text-slate-700"><?= nl2br(htmlspecialchars($justification['description'], ENT_QUOTES)) ?></dd>
                </div>
                <?php endif; ?>
                <?php if (!empty($justification['motif_refus'])): ?>
                <div class="col-span-2">
                    <dt class="text-slate-500 text-red-600">Motif de refus</dt>
                    <dd class="mt-0.5 text-red-700"><?= nl2br(htmlspecialchars($justification['motif_refus'], ENT_QUOTES)) ?></dd>
                </div>
                <?php endif; ?>
            </dl>

            <?php if ($justification['statut'] === 'en_attente' && epAbsShow($perms, 'attendance.validate')): ?>
            <div class="mt-5 pt-4 border-t border-slate-100 flex gap-3">
                <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/absences/<?= $absence['id'] ?>/valider">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 transition-colors">
                        <i data-lucide="check" class="w-4 h-4"></i>Valider
                    </button>
                </form>
                <button onclick="document.getElementById('refus-form').classList.toggle('hidden')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-red-200 text-red-600 text-sm font-medium hover:bg-red-50 transition-colors">
                    <i data-lucide="x" class="w-4 h-4"></i>Refuser
                </button>
            </div>
            <form id="refus-form" method="POST"
                  action="<?= BASE_URL ?>/v2/vie-scolaire/absences/<?= $absence['id'] ?>/refuser"
                  class="hidden mt-3">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                <label class="block text-xs font-medium text-slate-600 mb-1">Motif de refus <span class="text-red-500">*</span></label>
                <textarea name="motif_refus" rows="2" required
                          class="w-full rounded-lg border border-slate-200 text-sm px-3 py-2 focus:ring-2 focus:ring-violet-300 focus:outline-none"
                          placeholder="Expliquez le motif du refus…"></textarea>
                <button type="submit" class="mt-2 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700 transition-colors">
                    <i data-lucide="send" class="w-4 h-4"></i>Confirmer le refus
                </button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Actions -->
    <div class="space-y-4">
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">Actions</h3>
            <div class="space-y-2">
                <?php if ($statut === 'non_justifiee' && epAbsShow($perms, 'attendance.justify')): ?>
                <a href="<?= BASE_URL ?>/v2/vie-scolaire/absences/<?= $absence['id'] ?>/justifier"
                   class="w-full inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition-colors">
                    <i data-lucide="file-plus" class="w-4 h-4"></i>Soumettre une justification
                </a>
                <?php endif; ?>

                <?php if (epAbsShow($perms, 'attendance.delete')): ?>
                <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/absences/<?= $absence['id'] ?>/delete"
                      onsubmit="return confirm('Archiver cette absence ?')">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit"
                            class="w-full inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-red-200 text-red-600 text-sm font-medium hover:bg-red-50 transition-colors">
                        <i data-lucide="archive" class="w-4 h-4"></i>Archiver
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-xs text-slate-500 space-y-1">
            <p>Créée le <?= date('d/m/Y H:i', strtotime($absence['created_at'])) ?></p>
            <?php if (!empty($absence['updated_at']) && $absence['updated_at'] !== $absence['created_at']): ?>
            <p>Modifiée le <?= date('d/m/Y H:i', strtotime($absence['updated_at'])) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
