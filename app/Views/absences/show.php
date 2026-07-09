<?php
$currentUser   = \Core\Session::getUser();
$absence       = $absence       ?? null;
$justification = $justification ?? null;
$sessions      = $sessions      ?? [];
$statuts       = $statuts       ?? [];
$canEdit       = $canEdit       ?? false;
$canJustify    = $canJustify    ?? false;
$canValider    = $canValider    ?? false;

if (!$absence) {
    echo '<div class="alert alert-danger"><i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i><span>Absence introuvable.</span></div>';
    return;
}

$csrfToken = \Core\Session::getCsrfToken();

function showAbsStatutBadge(string $statut, array $statuts): string {
    $s  = $statuts[$statut] ?? ['label' => $statut, 'class' => 'secondary'];
    $cl = match($s['class'] ?? '') {
        'success' => 'bg-emerald-100 text-emerald-700', 'danger' => 'bg-red-100 text-red-700',
        'warning' => 'bg-amber-100 text-amber-800', 'info'   => 'bg-sky-100 text-sky-700',
        default   => 'bg-slate-100 text-slate-600',
    };
    return '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap ' . $cl . '">' . htmlspecialchars($s['label'], ENT_QUOTES) . '</span>';
}
?>

<!-- ── Page header ───────────────────────────────────────────────────────── -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <div class="page-icon"
             style="background:#ede9fe">
            <i data-lucide="info" class="w-5 h-5" style="color:#7c3aed"></i>
        </div>
        <div>
            <h2 class="text-xl font-bold text-slate-900" style="letter-spacing:-.03em">
                D&eacute;tail de l&rsquo;absence
            </h2>
            <p class="text-sm text-slate-400">
                <?= htmlspecialchars($absence->eleve_nom ?? '', ENT_QUOTES) ?>
                &mdash; <?= date('d/m/Y', strtotime($absence->date_absence)) ?>
            </p>
        </div>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <?php if ($canEdit): ?>
        <form method="POST" action="<?= BASE_URL ?>/absences/<?= $absence->id ?>/delete"
              onsubmit="return confirm('Supprimer cette absence ?')">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
            <button class="btn btn-danger">
                <i data-lucide="trash-2" class="w-4 h-4"></i>Supprimer
            </button>
        </form>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/absences/liste" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour
        </a>
    </div>
</div>

<!-- ── Layout 3/5 + 2/5 ──────────────────────────────────────────────────── -->
<div class="grid grid-cols-1 lg:grid-cols-5 gap-5">

    <!-- ── Colonne principale (3/5) ──────────────────────────────────────── -->
    <div class="lg:col-span-3 space-y-5">

        <!-- Infos absence -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="calendar-x" class="w-4 h-4" style="color:#ef4444"></i>
                <span class="font-semibold text-slate-700">Informations de l&rsquo;absence</span>
                <?= showAbsStatutBadge($absence->statut_justif ?? 'non_justifiee', $statuts) ?>
            </div>
            <div class="p-5">

                <!-- Grid info 2 colonnes -->
                <div class="grid grid-cols-2 gap-x-6 gap-y-4">

                    <div>
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">&#201;l&egrave;ve</p>
                        <p class="font-semibold text-slate-800">
                            <?= htmlspecialchars($absence->eleve_nom ?? '', ENT_QUOTES) ?>
                        </p>
                        <p class="mono text-xs text-slate-400">
                            <?= htmlspecialchars($absence->matricule ?? '', ENT_QUOTES) ?>
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Classe</p>
                        <p class="font-semibold text-slate-800">
                            <?= htmlspecialchars(($absence->classe_niveau ?? '') . ' ' . ($absence->classe_nom ?? ''), ENT_QUOTES) ?>
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Date</p>
                        <p class="font-semibold text-slate-800">
                            <?= date('d/m/Y', strtotime($absence->date_absence)) ?>
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Session</p>
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600">
                            <?= htmlspecialchars($sessions[$absence->session] ?? $absence->session, ENT_QUOTES) ?>
                        </span>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Type</p>
                        <?php if ($absence->type === 'retard'): ?>
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-amber-100 text-amber-800">
                            <i data-lucide="clock" class="w-3 h-3"></i>Retard
                            <?php if ($absence->duree_retard): ?>
                            &mdash; <?= $absence->duree_retard ?>&nbsp;min
                            <?php endif; ?>
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-red-100 text-red-700">
                            <i data-lucide="x-circle" class="w-3 h-3"></i>Absence
                        </span>
                        <?php endif; ?>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Justification</p>
                        <?= showAbsStatutBadge($absence->statut_justif ?? 'non_justifiee', $statuts) ?>
                    </div>

                    <?php if ($absence->motif): ?>
                    <div class="col-span-2">
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Motif d&eacute;clar&eacute;</p>
                        <p class="text-sm text-slate-600">
                            <?= htmlspecialchars($absence->motif, ENT_QUOTES) ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <?php if ($absence->signale_par_nom && trim($absence->signale_par_nom)): ?>
                    <div class="col-span-2">
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Signal&eacute; par</p>
                        <p class="text-xs text-slate-500 flex items-center gap-1.5">
                            <i data-lucide="user" class="w-3 h-3"></i>
                            <?= htmlspecialchars(trim($absence->signale_par_nom), ENT_QUOTES) ?>
                            <?php if ($absence->created_at): ?>
                            &mdash; le <?= date('d/m/Y \à H:i', strtotime($absence->created_at)) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php endif; ?>

                </div>

                <!-- Récapitulatif en bande -->
                <div class="mt-5 grid grid-cols-3 text-center rounded-xl overflow-hidden"
                     style="border:1px solid #e4e4ec">
                    <div class="py-4" style="border-right:1px solid #e4e4ec">
                        <p class="text-xs text-slate-400 uppercase tracking-wide mb-1">Type</p>
                        <p class="font-bold text-slate-700 text-sm">
                            <?= $absence->type === 'retard' ? 'Retard' : 'Absence' ?>
                        </p>
                    </div>
                    <div class="py-4" style="border-right:1px solid #e4e4ec">
                        <p class="text-xs text-slate-400 uppercase tracking-wide mb-1">Dur&eacute;e</p>
                        <p class="font-bold text-slate-700 text-sm">
                            <?php
                            if ($absence->type === 'retard') {
                                echo ($absence->duree_retard ?? '?') . ' min';
                            } else {
                                $h = \App\Models\AbsenceModel::HEURES[$absence->session] ?? 4;
                                echo $h . 'h';
                            }
                            ?>
                        </p>
                    </div>
                    <div class="py-4">
                        <p class="text-xs text-slate-400 uppercase tracking-wide mb-1">Session</p>
                        <p class="font-bold text-slate-700" style="font-size:.72rem">
                            <?= htmlspecialchars($sessions[$absence->session] ?? $absence->session, ENT_QUOTES) ?>
                        </p>
                    </div>
                </div>

            </div>
        </div>

        <!-- Justification existante -->
        <?php if ($justification): ?>
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="file-text" class="w-4 h-4" style="color:#0ea5e9"></i>
                <span class="font-semibold text-slate-700">Justification soumise</span>
                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= match($justification->statut) {
                    'acceptee' => 'bg-emerald-100 text-emerald-700',
                    'refusee'  => 'bg-red-100 text-red-700',
                    default    => 'bg-amber-100 text-amber-800',
                } ?>">
                    <?= match($justification->statut) {
                        'acceptee' => 'Accept&eacute;e',
                        'refusee'  => 'Refus&eacute;e',
                        default    => 'En attente',
                    } ?>
                </span>
            </div>
            <div class="p-5 space-y-4">

                <div>
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-1.5">Motif</p>
                    <p class="text-sm text-slate-700 leading-relaxed">
                        <?= nl2br(htmlspecialchars($justification->motif, ENT_QUOTES)) ?>
                    </p>
                </div>

                <?php if ($justification->document_path): ?>
                <div>
                    <a href="<?= BASE_URL ?>/<?= htmlspecialchars($justification->document_path, ENT_QUOTES) ?>"
                       target="_blank" class="btn btn-secondary">
                        <i data-lucide="paperclip" class="w-4 h-4"></i>Voir le document
                    </a>
                </div>
                <?php endif; ?>

                <?php if ($justification->commentaire_admin): ?>
                <div class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm border-sky-200 bg-sky-50 text-sky-800" style="margin-bottom:0">
                    <i data-lucide="info" class="w-4 h-4 shrink-0"></i>
                    <div>
                        <p class="text-xs font-semibold mb-0.5">Commentaire administration</p>
                        <p class="text-sm">
                            <?= htmlspecialchars($justification->commentaire_admin, ENT_QUOTES) ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($justification->soumis_par_nom && trim($justification->soumis_par_nom)): ?>
                <p class="text-xs text-slate-400 flex items-center gap-1.5">
                    <i data-lucide="user" class="w-3 h-3"></i>
                    Soumis par <?= htmlspecialchars(trim($justification->soumis_par_nom), ENT_QUOTES) ?>
                    le <?= date('d/m/Y', strtotime($justification->created_at)) ?>
                </p>
                <?php endif; ?>

            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- ── Colonne sidebar (2/5) ─────────────────────────────────────────── -->
    <div class="lg:col-span-2 space-y-5">

        <!-- Formulaire de justification -->
        <?php
        $peutSoumettre = $canJustify
            && ($justification === null || $justification->statut === 'refusee')
            && ($absence->statut_justif ?? '') !== 'justifiee';
        ?>
        <?php if ($peutSoumettre): ?>
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="send" class="w-4 h-4" style="color:#059669"></i>
                <span class="font-semibold text-slate-700">
                    <?= $justification ? 'Modifier la justification' : 'Soumettre une justification' ?>
                </span>
            </div>
            <div class="p-5">
                <form method="POST"
                      action="<?= BASE_URL ?>/absences/<?= $absence->id ?>/justifier"
                      enctype="multipart/form-data"
                      class="space-y-4">
                    <input type="hidden" name="_csrf_token"
                           value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">

                    <div>
                        <label class="form-label">
                            Motif <span style="color:#ef4444">*</span>
                        </label>
                        <textarea name="motif" class="form-input" rows="4" required
                                  placeholder="Expliquez la raison de l&rsquo;absence&hellip;"><?= htmlspecialchars($justification->motif ?? '', ENT_QUOTES) ?></textarea>
                    </div>

                    <div>
                        <label class="form-label">Document justificatif (optionnel)</label>
                        <input type="file" name="document" class="form-input"
                               accept=".pdf,.jpg,.jpeg,.png">
                        <p class="text-xs text-slate-400 mt-1">PDF, JPG ou PNG &mdash; max 5&nbsp;Mo</p>
                    </div>

                    <button type="submit" class="btn btn-success w-full">
                        <i data-lucide="send" class="w-4 h-4"></i>Envoyer la justification
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Validation administrative -->
        <?php if ($canValider && $justification && $justification->statut === 'en_attente'): ?>
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm" style="border-color:#fcd34d">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900" style="background:#fffbeb">
                <i data-lucide="check-circle-2" class="w-4 h-4" style="color:#d97706"></i>
                <span class="font-semibold" style="color:#92400e">Validation administrative</span>
                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-amber-100 text-amber-800" style="margin-left:auto">En attente</span>
            </div>
            <div class="p-5">
                <form method="POST"
                      action="<?= BASE_URL ?>/absences/<?= $absence->id ?>/valider"
                      class="space-y-4">
                    <input type="hidden" name="_csrf_token"
                           value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">

                    <div>
                        <label class="form-label">Commentaire (optionnel)</label>
                        <textarea name="commentaire" class="form-input" rows="3"
                                  placeholder="Note pour le parent&hellip;"></textarea>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="submit" name="decision" value="accepter"
                                class="btn btn-success flex-1">
                            <i data-lucide="check-circle" class="w-4 h-4"></i>Accepter
                        </button>
                        <button type="submit" name="decision" value="refuser"
                                class="btn btn-danger flex-1"
                                onclick="return confirm('Refuser cette justification ?')">
                            <i data-lucide="x-circle" class="w-4 h-4"></i>Refuser
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Info card si rien à faire -->
        <?php if (!$peutSoumettre && !($canValider && $justification && $justification->statut === 'en_attente')): ?>
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="p-5 text-center py-8">
                <i data-lucide="check-circle" class="w-10 h-10 mx-auto mb-3" style="color:#34d399"></i>
                <p class="text-sm font-semibold text-slate-700">Aucune action requise</p>
                <p class="text-xs text-slate-400 mt-1">Cette absence ne n&eacute;cessite pas d&rsquo;action de votre part.</p>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>
