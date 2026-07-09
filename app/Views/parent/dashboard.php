<?php
$enfants       = $enfants       ?? [];
$notifications = $notifications ?? [];
$annonces      = $annonces      ?? [];
$user          = \Core\Session::getUser();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-house-heart me-2 text-primary"></i>Mon espace parent</h4>
        <small class="text-muted">Bonjour, <?= htmlspecialchars(($user['prenom'] ?: '') . ' ' . $user['nom'], ENT_QUOTES) ?></small>
    </div>
    <a href="<?= BASE_URL ?>/annonces" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-megaphone me-1"></i>Annonces
    </a>
</div>

<?php if (empty($enfants)): ?>
<div class="alert alert-info d-flex align-items-center">
    <i class="bi bi-info-circle-fill me-3 fs-4"></i>
    <div>Aucun enfant n'est encore associé à votre compte. Contactez l'administration.</div>
</div>
<?php else: ?>

<!-- ═ Enfants ══════════════════════════════════════════════════════════════ -->
<div class="row g-4 mb-4">
    <?php foreach ($enfants as $enfant): ?>
    <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;overflow:hidden">
            <div class="card-header py-3 d-flex align-items-center gap-3"
                 style="background:linear-gradient(135deg,#4e73df,#224abe)">
                <?php if (!empty($enfant->photo)): ?>
                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($enfant->photo, ENT_QUOTES) ?>"
                         class="rounded-circle border border-white border-2"
                         width="48" height="48" style="object-fit:cover">
                <?php else: ?>
                    <div class="rounded-circle bg-white d-flex align-items-center justify-content-center"
                         style="width:48px;height:48px;flex-shrink:0">
                        <i class="bi bi-person-fill text-primary fs-4"></i>
                    </div>
                <?php endif; ?>
                <div class="text-white">
                    <div class="fw-bold"><?= htmlspecialchars($enfant->prenom . ' ' . $enfant->nom, ENT_QUOTES) ?></div>
                    <small class="opacity-75"><?= htmlspecialchars($enfant->classe_niveau . ' — ' . $enfant->classe_nom, ENT_QUOTES) ?></small>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-2 text-center mb-3">
                    <div class="col-4">
                        <div class="p-2 rounded" style="background:#f0f5ff">
                            <div class="fw-bold fs-5 text-primary">
                                <?= $enfant->derniere_moyenne !== null
                                    ? number_format((float)$enfant->derniere_moyenne, 2)
                                    : '—' ?>
                            </div>
                            <div class="text-muted" style="font-size:.72rem">Moyenne</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 rounded" style="background:#fff5f5">
                            <div class="fw-bold fs-5 text-danger"><?= (int)$enfant->absences_mois ?></div>
                            <div class="text-muted" style="font-size:.72rem">Abs. ce mois</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 rounded" style="background:<?= (float)$enfant->solde_impaye > 0 ? '#fff3cd' : '#f0fff4' ?>">
                            <div class="fw-bold fs-5 <?= (float)$enfant->solde_impaye > 0 ? 'text-warning' : 'text-success' ?>">
                                <?= number_format((float)$enfant->solde_impaye, 0, ',', ' ') ?>
                            </div>
                            <div class="text-muted" style="font-size:.72rem">Impayé (FCFA)</div>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= BASE_URL ?>/parent/notes?eleve_id=<?= $enfant->id ?>"
                       class="btn btn-sm btn-outline-primary flex-fill">
                        <i class="bi bi-pencil-square me-1"></i>Notes
                    </a>
                    <a href="<?= BASE_URL ?>/parent/absences?eleve_id=<?= $enfant->id ?>"
                       class="btn btn-sm btn-outline-danger flex-fill">
                        <i class="bi bi-calendar-x me-1"></i>Absences
                    </a>
                    <a href="<?= BASE_URL ?>/parent/paiements?eleve_id=<?= $enfant->id ?>"
                       class="btn btn-sm btn-outline-success flex-fill">
                        <i class="bi bi-cash me-1"></i>Scolarité
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ═ Ligne du bas : Notifications + Annonces ══════════════════════════════ -->
<div class="row g-4">
    <!-- Notifications -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3">
                <span class="fw-semibold"><i class="bi bi-bell me-2 text-warning"></i>Notifications récentes</span>
                <a href="<?= BASE_URL ?>/notifications" class="btn btn-sm btn-outline-secondary">Tout voir</a>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($notifications)): ?>
                <div class="list-group-item text-center text-muted py-4">
                    <i class="bi bi-bell-slash fs-2 d-block mb-2 opacity-25"></i>
                    Aucune notification
                </div>
                <?php else: ?>
                <?php foreach ($notifications as $n):
                    $typeInfo = \App\Models\NotificationModel::TYPES[$n->type] ?? ['icon'=>'info-circle','color'=>'secondary'];
                ?>
                <div class="list-group-item list-group-item-action px-3 py-2">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi bi-<?= $typeInfo['icon'] ?> text-<?= $typeInfo['color'] ?> mt-1"></i>
                        <div class="flex-fill">
                            <div class="small fw-semibold"><?= htmlspecialchars($n->titre, ENT_QUOTES) ?></div>
                            <?php if ($n->message): ?>
                            <div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars(mb_substr($n->message, 0, 60), ENT_QUOTES) ?>…</div>
                            <?php endif; ?>
                            <div class="text-muted" style="font-size:.7rem"><?= date('d/m H:i', strtotime($n->created_at)) ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Annonces -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3">
                <span class="fw-semibold"><i class="bi bi-megaphone me-2 text-primary"></i>Annonces de l'école</span>
                <a href="<?= BASE_URL ?>/annonces" class="btn btn-sm btn-outline-secondary">Tout voir</a>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($annonces)): ?>
                <div class="list-group-item text-center text-muted py-4">
                    <i class="bi bi-megaphone fs-2 d-block mb-2 opacity-25"></i>
                    Aucune annonce récente
                </div>
                <?php else: ?>
                <?php foreach ($annonces as $a): ?>
                <div class="list-group-item px-3 py-3">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div class="fw-semibold"><?= htmlspecialchars($a->titre, ENT_QUOTES) ?></div>
                        <small class="text-muted ms-2 text-nowrap"><?= date('d/m/Y', strtotime($a->published_at)) ?></small>
                    </div>
                    <p class="text-muted small mb-0"><?= nl2br(htmlspecialchars(mb_substr($a->contenu, 0, 150), ENT_QUOTES)) ?>…</p>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>
