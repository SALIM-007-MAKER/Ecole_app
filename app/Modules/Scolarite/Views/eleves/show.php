<?php
$perms = $perms ?? [];
function sp2(array $p, string $k): bool { return in_array($k, $p, true); }
function photoUrlV2s(string $photo): string {
    if (str_starts_with($photo, 'storage/')) {
        $parts    = explode('/', $photo);
        $filename = array_pop($parts);
        $typeDir  = array_pop($parts);
        return BASE_URL . '/uploads/serve/' . $typeDir . '/' . $filename;
    }
    return BASE_URL . '/' . $photo;
}

$sexeLabel = $eleve->sexe === 'M' ? 'Masculin' : 'Féminin';
$isMale    = $eleve->sexe === 'M';
$age       = $eleve->date_naissance
    ? (int)((new DateTime())->diff(new DateTime($eleve->date_naissance))->y)
    : null;
?>

<!-- Breadcrumb + actions -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <nav class="flex items-center gap-1.5 text-sm text-slate-500 mb-1">
            <a href="<?= BASE_URL ?>/v2/scolarite/eleves" class="hover:text-violet-600 transition-colors flex items-center gap-1">
                <i data-lucide="users" class="w-3.5 h-3.5"></i>Élèves
            </a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-slate-300"></i>
            <span class="text-slate-700 font-medium">
                <?= htmlspecialchars($eleve->nom . ' ' . $eleve->prenom, ENT_QUOTES) ?>
            </span>
        </nav>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="user" class="w-5 h-5 text-violet-600"></i>Fiche élève
        </h2>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <?php if (sp2($perms,'eleves.update')): ?>
        <a href="<?= BASE_URL ?>/v2/scolarite/eleves/<?= $eleve->id ?>/edit" class="btn btn-warning">
            <i data-lucide="pencil" class="w-4 h-4"></i>Modifier
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/v2/scolarite/eleves" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>Liste
        </a>
        <?php if (sp2($perms,'eleves.delete')): ?>
        <button class="btn btn-ghost text-amber-600 border border-amber-200 hover:bg-amber-50"
                onclick="document.getElementById('archiveModal').classList.add('active');document.body.style.overflow='hidden'">
            <i data-lucide="archive" class="w-4 h-4"></i>Archiver
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($flash = \Core\Session::getFlash('success')): ?>
<div class="alert alert-success mb-4" role="alert">
    <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
    <span><?= htmlspecialchars($flash, ENT_QUOTES) ?></span>
    <button class="ml-auto" onclick="this.closest('[role=alert]').remove()"><i data-lucide="x" class="w-4 h-4"></i></button>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Carte identité -->
    <div class="space-y-4">
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm text-center">
            <div class="p-6">
                <?php if (!empty($eleve->photo)): ?>
                <img src="<?= photoUrlV2s($eleve->photo) ?>"
                     class="w-32 h-32 rounded-full object-cover border-4 border-slate-100 shadow-md mx-auto mb-4" alt="">
                <?php else: ?>
                <div class="w-32 h-32 rounded-full mx-auto mb-4 flex items-center justify-center border-4 border-slate-100 shadow-sm <?= $isMale ? 'bg-sky-50' : 'bg-pink-50' ?>">
                    <i data-lucide="user" class="w-14 h-14 <?= $isMale ? 'text-sky-400' : 'text-pink-400' ?>"></i>
                </div>
                <?php endif; ?>

                <h3 class="text-base font-bold text-slate-900">
                    <?= htmlspecialchars($eleve->prenom . ' ' . $eleve->nom, ENT_QUOTES) ?>
                </h3>
                <p class="font-mono text-sm text-slate-400 mt-0.5">
                    <?= htmlspecialchars($eleve->matricule, ENT_QUOTES) ?>
                </p>

                <div class="flex items-center justify-center gap-2 mt-3 flex-wrap">
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $isMale ? 'bg-sky-100 text-sky-700' : 'bg-red-100 text-red-700' ?>">
                        <?= $sexeLabel ?>
                    </span>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $eleve->actif ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>">
                        <?= $eleve->actif ? 'Actif' : 'Archivé' ?>
                    </span>
                </div>

                <?php if (!empty($eleve->classe_nom)): ?>
                <div class="mt-4 p-3 bg-violet-50 rounded-xl border border-violet-100">
                    <p class="text-xs text-violet-400 mb-0.5">Classe</p>
                    <p class="font-semibold text-violet-700 text-sm flex items-center justify-center gap-1.5">
                        <i data-lucide="building-2" class="w-3.5 h-3.5"></i>
                        <?= htmlspecialchars($eleve->classe_niveau . ' — ' . $eleve->classe_nom, ENT_QUOTES) ?>
                    </p>
                </div>
                <?php endif; ?>

                <?php if ($age !== null): ?>
                <p class="text-sm text-slate-500 mt-3 flex items-center justify-center gap-1.5">
                    <i data-lucide="cake" class="w-3.5 h-3.5"></i>
                    <?= date('d/m/Y', strtotime($eleve->date_naissance)) ?>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-slate-100 text-slate-600"><?= $age ?> ans</span>
                </p>
                <?php endif; ?>
            </div>

            <?php if (sp2($perms,'eleves.update')): ?>
            <div class="border-t border-slate-200 bg-slate-50 px-5 py-4">
                <a href="<?= BASE_URL ?>/v2/scolarite/eleves/<?= $eleve->id ?>/edit"
                   class="btn btn-warning w-full text-xs">
                    <i data-lucide="pencil" class="w-4 h-4"></i>Modifier la fiche
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Parent -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4">
                <i data-lucide="users" class="w-4 h-4 text-amber-500"></i>
                <span class="font-semibold text-slate-700 text-sm">Parent responsable</span>
            </div>
            <div class="p-4">
                <?php if (!empty($eleve->parent_nom) && trim($eleve->parent_nom) !== ''): ?>
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
                        <i data-lucide="user" class="w-5 h-5 text-amber-600"></i>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-800 text-sm"><?= htmlspecialchars($eleve->parent_nom, ENT_QUOTES) ?></p>
                        <?php if (!empty($eleve->parent_telephone)): ?>
                        <a href="tel:<?= htmlspecialchars($eleve->parent_telephone, ENT_QUOTES) ?>"
                           class="text-xs text-slate-500 hover:text-violet-600 flex items-center gap-1 mt-0.5">
                            <i data-lucide="phone" class="w-3 h-3"></i><?= htmlspecialchars($eleve->parent_telephone, ENT_QUOTES) ?>
                        </a>
                        <?php endif; ?>
                        <?php if (!empty($eleve->parent_email)): ?>
                        <a href="mailto:<?= htmlspecialchars($eleve->parent_email, ENT_QUOTES) ?>"
                           class="text-xs text-slate-500 hover:text-violet-600 flex items-center gap-1 mt-0.5">
                            <i data-lucide="mail" class="w-3 h-3"></i><?= htmlspecialchars($eleve->parent_email, ENT_QUOTES) ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="text-center py-3">
                    <i data-lucide="user-x" class="w-8 h-8 text-slate-200 mx-auto mb-2"></i>
                    <p class="text-sm text-slate-400 mb-2">Aucun parent affecté</p>
                    <?php if (sp2($perms,'eleves.update')): ?>
                    <a href="<?= BASE_URL ?>/v2/scolarite/eleves/<?= $eleve->id ?>/edit" class="btn btn-outline">
                        <i data-lucide="link" class="w-4 h-4"></i>Affecter un parent
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Contenu principal avec tabs -->
    <div class="lg:col-span-2 space-y-4">
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-4">
                <nav class="flex gap-0 -mb-px">
                    <button type="button"
                            class="tab-btn active px-4 py-3 text-sm font-medium border-b-2 border-violet-600 text-violet-600 flex items-center gap-1.5 transition-colors"
                            onclick="switchTab('infos', this)">
                        <i data-lucide="user-cog" class="w-4 h-4"></i>Informations
                    </button>
                    <button type="button"
                            class="tab-btn px-4 py-3 text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700 flex items-center gap-1.5 transition-colors"
                            onclick="switchTab('notes', this)">
                        <i data-lucide="book-open-check" class="w-4 h-4"></i>Notes
                    </button>
                    <button type="button"
                            class="tab-btn px-4 py-3 text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700 flex items-center gap-1.5 transition-colors"
                            onclick="switchTab('absences', this)">
                        <i data-lucide="calendar-x" class="w-4 h-4"></i>Absences
                    </button>
                    <button type="button"
                            class="tab-btn px-4 py-3 text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700 flex items-center gap-1.5 transition-colors"
                            onclick="switchTab('paiements', this)">
                        <i data-lucide="credit-card" class="w-4 h-4"></i>Paiements
                    </button>
                </nav>
            </div>

            <div id="tab-infos" class="tab-panel">
                <dl class="divide-y divide-slate-50">
                    <?php
                    $infos = [
                        ['Matricule',         $eleve->matricule,       true],
                        ['Nom',               $eleve->nom,             false],
                        ['Prénom',            $eleve->prenom,          false],
                        ['Sexe',              $sexeLabel,              false],
                        ['Date de naissance', $eleve->date_naissance ? date('d/m/Y', strtotime($eleve->date_naissance)) : '—', false],
                        ['Âge',               $age !== null ? $age . ' ans' : '—', false],
                    ];
                    foreach ($infos as [$label, $value, $mono]):
                    ?>
                    <div class="flex items-center px-5 py-3">
                        <dt class="text-xs font-medium text-slate-400 uppercase tracking-wide w-40 shrink-0"><?= $label ?></dt>
                        <dd class="text-sm font-semibold text-slate-800 <?= $mono ? 'font-mono' : '' ?>">
                            <?= htmlspecialchars((string)$value, ENT_QUOTES) ?>
                        </dd>
                    </div>
                    <?php endforeach; ?>
                </dl>
                <?php if (!empty($eleve->telephone) || !empty($eleve->email) || !empty($eleve->adresse)): ?>
                <div class="border-t border-slate-100 px-5 py-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Coordonnées</p>
                    <div class="space-y-2">
                        <?php if (!empty($eleve->telephone)): ?>
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0"><i data-lucide="phone" class="w-3.5 h-3.5 text-emerald-600"></i></div>
                            <a href="tel:<?= htmlspecialchars($eleve->telephone, ENT_QUOTES) ?>" class="text-sm font-medium text-slate-800 hover:text-violet-600"><?= htmlspecialchars($eleve->telephone, ENT_QUOTES) ?></a>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($eleve->email)): ?>
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-sky-100 flex items-center justify-center shrink-0"><i data-lucide="mail" class="w-3.5 h-3.5 text-sky-600"></i></div>
                            <a href="mailto:<?= htmlspecialchars($eleve->email, ENT_QUOTES) ?>" class="text-sm font-medium text-slate-800 hover:text-violet-600"><?= htmlspecialchars($eleve->email, ENT_QUOTES) ?></a>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($eleve->adresse)): ?>
                        <div class="flex items-start gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-violet-100 flex items-center justify-center shrink-0 mt-0.5"><i data-lucide="map-pin" class="w-3.5 h-3.5 text-violet-600"></i></div>
                            <p class="text-sm font-medium text-slate-800"><?= nl2br(htmlspecialchars($eleve->adresse, ENT_QUOTES)) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div id="tab-notes" class="tab-panel hidden p-5">
                <div class="flex justify-between items-center mb-4">
                    <p class="text-sm font-semibold text-slate-700">Relevé de notes</p>
                    <a href="<?= BASE_URL ?>/notes?eleve_id=<?= $eleve->id ?>" class="btn btn-primary"><i data-lucide="external-link" class="w-4 h-4"></i>Voir toutes les notes</a>
                </div>
                <div class="flex flex-col items-center py-10 text-slate-400">
                    <i data-lucide="book-open-check" class="w-10 h-10 text-slate-300 mb-3"></i>
                    <p class="text-sm">Disponible dans le module académique</p>
                </div>
            </div>

            <div id="tab-absences" class="tab-panel hidden p-5">
                <div class="flex justify-between items-center mb-4">
                    <p class="text-sm font-semibold text-slate-700">Suivi des absences</p>
                    <a href="<?= BASE_URL ?>/absences?eleve_id=<?= $eleve->id ?>" class="btn btn-warning"><i data-lucide="external-link" class="w-4 h-4"></i>Voir toutes les absences</a>
                </div>
                <div class="flex flex-col items-center py-10 text-slate-400">
                    <i data-lucide="calendar-x" class="w-10 h-10 text-slate-300 mb-3"></i>
                    <p class="text-sm">Disponible dans le module absences</p>
                </div>
            </div>

            <div id="tab-paiements" class="tab-panel hidden p-5">
                <div class="flex justify-between items-center mb-4">
                    <p class="text-sm font-semibold text-slate-700">Historique des paiements</p>
                    <a href="<?= BASE_URL ?>/comptabilite/paiements?eleve_id=<?= $eleve->id ?>" class="btn btn-success"><i data-lucide="external-link" class="w-4 h-4"></i>Voir les paiements</a>
                </div>
                <div class="flex flex-col items-center py-10 text-slate-400">
                    <i data-lucide="credit-card" class="w-10 h-10 text-slate-300 mb-3"></i>
                    <p class="text-sm">Disponible dans la comptabilité</p>
                </div>
            </div>
        </div>

        <!-- Accès rapides -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4">
                <i data-lucide="zap" class="w-4 h-4 text-violet-600"></i>
                <span class="font-semibold text-slate-700 text-sm">Accès rapides</span>
            </div>
            <div class="p-4 grid grid-cols-3 gap-3">
                <a href="<?= BASE_URL ?>/notes?eleve_id=<?= $eleve->id ?>"
                   class="flex flex-col items-center gap-2 rounded-xl border border-slate-200 bg-white p-5 text-center shadow-sm transition-all hover:-translate-y-0.5 hover:border-violet-200 hover:bg-violet-50">
                    <i data-lucide="book-open-check" class="w-7 h-7 text-violet-600"></i>
                    <span class="text-sm font-semibold text-slate-700">Notes</span>
                    <span class="text-xs text-slate-400">Relevé</span>
                </a>
                <a href="<?= BASE_URL ?>/absences?eleve_id=<?= $eleve->id ?>"
                   class="flex flex-col items-center gap-2 rounded-xl border border-slate-200 bg-white p-5 text-center shadow-sm transition-all hover:-translate-y-0.5 hover:border-amber-200 hover:bg-amber-50">
                    <i data-lucide="calendar-x" class="w-7 h-7 text-amber-500"></i>
                    <span class="text-sm font-semibold text-slate-700">Absences</span>
                    <span class="text-xs text-slate-400">Assiduité</span>
                </a>
                <a href="<?= BASE_URL ?>/comptabilite/paiements?eleve_id=<?= $eleve->id ?>"
                   class="flex flex-col items-center gap-2 rounded-xl border border-slate-200 bg-white p-5 text-center shadow-sm transition-all hover:-translate-y-0.5 hover:border-emerald-200 hover:bg-emerald-50">
                    <i data-lucide="credit-card" class="w-7 h-7 text-emerald-500"></i>
                    <span class="text-sm font-semibold text-slate-700">Paiements</span>
                    <span class="text-xs text-slate-400">Scolarité</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (sp2($perms,'eleves.delete')): ?>
<div id="archiveModal" class="modal-overlay fixed inset-0 z-[9000] hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm [&.active]:flex">
    <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white shadow-2xl">
        <div class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
            <i data-lucide="archive" class="w-5 h-5 text-amber-500"></i>
            <h3 class="text-base font-semibold text-slate-900">Archiver l'élève</h3>
            <button class="ml-auto btn btn-ghost btn-icon text-slate-400"
                    onclick="document.getElementById('archiveModal').classList.remove('active');document.body.style.overflow=''">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/v2/scolarite/eleves/<?= $eleve->id ?>/delete">
            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
            <input type="hidden" name="action" value="archive">
            <div class="px-5 py-5">
                <p class="text-slate-700 mb-3">
                    Archiver <strong><?= htmlspecialchars($eleve->prenom . ' ' . $eleve->nom, ENT_QUOTES) ?></strong> ?
                    Les données (notes, absences) sont conservées.
                </p>
                <div>
                    <label class="form-label" for="motif">Motif (optionnel)</label>
                    <input type="text" id="motif" name="motif" class="form-input" placeholder="Transfert, Déménagement…">
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
                <button type="button" class="btn btn-secondary"
                        onclick="document.getElementById('archiveModal').classList.remove('active');document.body.style.overflow=''">Annuler</button>
                <button type="submit" class="btn btn-warning">
                    <i data-lucide="archive" class="w-4 h-4"></i>Archiver
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function switchTab(tabId, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('active', 'border-violet-600', 'text-violet-600');
        b.classList.add('border-transparent', 'text-slate-500');
    });
    document.getElementById('tab-' + tabId).classList.remove('hidden');
    btn.classList.add('active', 'border-violet-600', 'text-violet-600');
    btn.classList.remove('border-transparent', 'text-slate-500');
}
</script>
