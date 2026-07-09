<?php
$inscription = $inscription ?? null;
$historique  = $historique  ?? [];
$classes     = $classes     ?? [];
$perms       = \Core\Session::getUser()['permissions'] ?? [];

function inscStatutBadge(string $statut): string {
    return match ($statut) {
        'en_attente' => '<span class="inline-flex items-center gap-1.5 text-sm font-medium text-amber-700 bg-amber-50 rounded-full px-3 py-1"><span class="w-2 h-2 rounded-full bg-amber-500"></span>En attente</span>',
        'validee'    => '<span class="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-700 bg-emerald-50 rounded-full px-3 py-1"><span class="w-2 h-2 rounded-full bg-emerald-500"></span>Validée</span>',
        'rejetee'    => '<span class="inline-flex items-center gap-1.5 text-sm font-medium text-red-700 bg-red-50 rounded-full px-3 py-1"><span class="w-2 h-2 rounded-full bg-red-500"></span>Rejetée</span>',
        'annulee'    => '<span class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 bg-slate-100 rounded-full px-3 py-1"><span class="w-2 h-2 rounded-full bg-slate-400"></span>Annulée</span>',
        default      => htmlspecialchars($statut, ENT_QUOTES),
    };
}
?>

<!-- Fil d'Ariane -->
<nav class="flex items-center gap-2 text-sm text-slate-400 mb-4">
    <a href="<?= BASE_URL ?>/v2/scolarite/inscriptions" class="hover:text-violet-600 transition">Inscriptions</a>
    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
    <span class="text-slate-700 font-medium">
        #<?= $inscription->id ?? '' ?> — <?= htmlspecialchars(($inscription->eleve_nom ?? '') . ' ' . ($inscription->eleve_prenom ?? ''), ENT_QUOTES) ?>
    </span>
</nav>

<?php $flash = \Core\Session::getFlash(); ?>
<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> mb-4" role="alert">
    <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-triangle' ?>" class="w-4 h-4 shrink-0"></i>
    <span class="flex-1 text-sm"><?= htmlspecialchars($flash['message'], ENT_QUOTES) ?></span>
    <button onclick="this.closest('[role=alert]').remove()" class="ml-auto opacity-60 hover:opacity-100">
        <i data-lucide="x" class="w-4 h-4"></i>
    </button>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Carte identité inscription -->
    <div class="lg:col-span-1 space-y-4">
        <div class="rounded-xl bg-white border border-slate-200 shadow-sm overflow-hidden">
            <div class="bg-gradient-to-br from-violet-600 to-violet-800 p-5 text-white">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                        <i data-lucide="clipboard-list" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-base">Inscription #<?= $inscription->id ?></h3>
                        <p class="text-violet-200 text-sm"><?= htmlspecialchars($inscription->annee_scolaire ?? '', ENT_QUOTES) ?></p>
                    </div>
                </div>
                <?= inscStatutBadge($inscription->statut ?? '') ?>
            </div>
            <div class="p-4 space-y-3">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-500">Élève</span>
                    <a href="<?= BASE_URL ?>/v2/scolarite/eleves/<?= $inscription->eleve_id ?>"
                       class="font-medium text-violet-600 hover:underline">
                        <?= htmlspecialchars(($inscription->eleve_nom ?? '') . ' ' . ($inscription->eleve_prenom ?? ''), ENT_QUOTES) ?>
                    </a>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-500">Matricule</span>
                    <span class="font-mono text-slate-600 text-xs"><?= htmlspecialchars($inscription->eleve_matricule ?? '', ENT_QUOTES) ?></span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-500">Classe</span>
                    <span class="font-medium text-slate-700">
                        <?php if ($inscription->classe_label): ?>
                        <a href="<?= BASE_URL ?>/v2/scolarite/classes/<?= $inscription->classe_id ?>"
                           class="text-violet-600 hover:underline">
                            <?= htmlspecialchars($inscription->classe_label, ENT_QUOTES) ?>
                        </a>
                        <?php else: ?>
                        <span class="text-slate-400 italic text-xs">Non assignée</span>
                        <?php endif; ?>
                    </span>
                </div>
                <?php if ($inscription->valide_le): ?>
                <div class="flex items-center justify-between text-sm border-t border-slate-100 pt-3">
                    <span class="text-slate-500">
                        <?= $inscription->statut === 'validee' ? 'Validée le' : 'Traitée le' ?>
                    </span>
                    <span class="text-slate-600"><?= date('d/m/Y', strtotime($inscription->valide_le)) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($inscription->notes)): ?>
                <div class="border-t border-slate-100 pt-3">
                    <p class="text-xs text-slate-500 font-medium mb-1">Observations</p>
                    <p class="text-sm text-slate-600"><?= htmlspecialchars($inscription->notes, ENT_QUOTES) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($inscription->motif_rejet)): ?>
                <div class="border-t border-slate-100 pt-3">
                    <p class="text-xs text-slate-500 font-medium mb-1">Motif</p>
                    <p class="text-sm text-red-600"><?= htmlspecialchars($inscription->motif_rejet, ENT_QUOTES) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Actions contextuelles -->
        <?php if (in_array('inscriptions.update', $perms, true)): ?>
        <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 space-y-2">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Actions</p>

            <?php if ($inscription->statut === 'en_attente'): ?>
            <form method="POST" action="<?= BASE_URL ?>/v2/scolarite/inscriptions/<?= $inscription->id ?>/valider">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit" class="btn btn-primary w-full justify-center">
                    <i data-lucide="check" class="w-4 h-4"></i>Valider l'inscription
                </button>
            </form>
            <button type="button" onclick="openRejetModal()" class="btn btn-danger w-full justify-center">
                <i data-lucide="x" class="w-4 h-4"></i>Rejeter
            </button>
            <a href="<?= BASE_URL ?>/v2/scolarite/inscriptions/<?= $inscription->id ?>/edit"
               class="btn btn-secondary w-full justify-center">
                <i data-lucide="pencil" class="w-4 h-4"></i>Modifier
            </a>
            <?php endif; ?>

            <?php if ($inscription->statut === 'validee'): ?>
            <!-- Changement de classe -->
            <div class="bg-slate-50 rounded-xl p-3 border border-slate-200">
                <p class="text-xs font-semibold text-slate-600 mb-2">Changer la classe</p>
                <form method="POST" action="<?= BASE_URL ?>/v2/scolarite/inscriptions/<?= $inscription->id ?>/changer-classe"
                      class="space-y-2">
                    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                    <select name="nouvelle_classe_id" class="form-input text-sm" required>
                        <option value="">— Nouvelle classe —</option>
                        <?php foreach ($classes as $c): ?>
                        <option value="<?= $c->id ?>"><?= htmlspecialchars($c->label, ENT_QUOTES) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-secondary w-full justify-center text-sm">
                        <i data-lucide="arrow-right-left" class="w-3.5 h-3.5"></i>Transférer
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <?php if (in_array($inscription->statut, ['en_attente', 'validee'], true)): ?>
            <button type="button" onclick="openAnnulModal()"
                    class="btn btn-secondary w-full justify-center text-red-500 hover:bg-red-50">
                <i data-lucide="ban" class="w-4 h-4"></i>Annuler l'inscription
            </button>
            <?php endif; ?>

            <?php if ($inscription->statut === 'validee'): ?>
            <!-- Réinscription -->
            <div class="bg-violet-50 rounded-xl p-3 border border-violet-200 mt-2">
                <p class="text-xs font-semibold text-violet-700 mb-2">Réinscription</p>
                <form method="POST" action="<?= BASE_URL ?>/v2/scolarite/inscriptions/<?= $inscription->id ?>/reinscrire"
                      class="space-y-2">
                    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                    <input type="text" name="nouvelle_annee" placeholder="2026-2027"
                           pattern="\d{4}-\d{4}" required
                           class="form-input text-sm">
                    <button type="submit" class="btn btn-primary w-full justify-center text-sm">
                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>Réinscrire
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Historique -->
    <div class="lg:col-span-2">
        <div class="rounded-xl bg-white border border-slate-200 shadow-sm overflow-hidden">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4">
                <i data-lucide="history" class="w-4 h-4 text-violet-500"></i>
                <span class="font-semibold text-slate-700 text-sm">Historique des inscriptions de l'élève</span>
            </div>
            <?php if (empty($historique)): ?>
            <div class="p-8 text-center text-slate-400">
                <p class="text-sm">Aucune inscription dans l'historique.</p>
            </div>
            <?php else: ?>
            <div class="divide-y divide-slate-50">
                <?php foreach ($historique as $h): ?>
                <div class="flex items-center gap-4 px-5 py-3 hover:bg-slate-50 transition-colors
                            <?= $h->id === $inscription->id ? 'bg-violet-50 border-l-2 border-violet-500' : '' ?>">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-medium text-slate-700 text-sm"><?= htmlspecialchars($h->annee_scolaire, ENT_QUOTES) ?></span>
                            <?php if ($h->classe_label): ?>
                            <span class="text-xs text-slate-500">· <?= htmlspecialchars($h->classe_label, ENT_QUOTES) ?></span>
                            <?php endif; ?>
                            <?php if ($h->id === $inscription->id): ?>
                            <span class="text-xs text-violet-600 bg-violet-100 rounded px-1.5 py-0.5">actuelle</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-3 mt-1">
                            <?= inscStatutBadge($h->statut) ?>
                            <span class="text-xs text-slate-400"><?= date('d/m/Y', strtotime($h->created_at)) ?></span>
                        </div>
                        <?php if (!empty($h->motif_rejet)): ?>
                        <p class="text-xs text-red-500 mt-1"><?= htmlspecialchars($h->motif_rejet, ENT_QUOTES) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($h->id !== $inscription->id): ?>
                    <a href="<?= BASE_URL ?>/v2/scolarite/inscriptions/<?= $h->id ?>"
                       class="text-xs text-violet-600 hover:underline shrink-0">Voir</a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal rejet -->
<div id="rejetModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <h3 class="font-semibold text-slate-900 mb-4 flex items-center gap-2">
            <i data-lucide="x-circle" class="w-5 h-5 text-red-500"></i>
            Rejeter l'inscription
        </h3>
        <form method="POST" action="<?= BASE_URL ?>/v2/scolarite/inscriptions/<?= $inscription->id ?>/rejeter">
            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
            <label class="form-label">Motif du rejet <span class="text-slate-400 font-normal">(optionnel)</span></label>
            <textarea name="motif_rejet" rows="3"
                      class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 outline-none mb-4"
                      placeholder="Ex: Dossier incomplet, capacité atteinte…"></textarea>
            <div class="flex gap-3">
                <button type="submit" class="btn btn-danger flex-1">Rejeter</button>
                <button type="button" onclick="closeRejetModal()" class="btn btn-secondary flex-1">Annuler</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal annulation -->
<div id="annulModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <h3 class="font-semibold text-slate-900 mb-4 flex items-center gap-2">
            <i data-lucide="ban" class="w-5 h-5 text-red-500"></i>
            Annuler l'inscription
        </h3>
        <p class="text-sm text-slate-600 mb-4">
            Cette action est irréversible. Si l'inscription est validée, l'élève sera retiré de sa classe.
        </p>
        <form method="POST" action="<?= BASE_URL ?>/v2/scolarite/inscriptions/<?= $inscription->id ?>/annuler">
            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
            <label class="form-label">Motif <span class="text-slate-400 font-normal">(optionnel)</span></label>
            <textarea name="motif" rows="2"
                      class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 outline-none mb-4"
                      placeholder="Raison de l'annulation…"></textarea>
            <div class="flex gap-3">
                <button type="submit" class="btn btn-danger flex-1">Confirmer l'annulation</button>
                <button type="button" onclick="closeAnnulModal()" class="btn btn-secondary flex-1">Retour</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRejetModal()  { document.getElementById('rejetModal').classList.replace('hidden','flex'); }
function closeRejetModal() { document.getElementById('rejetModal').classList.replace('flex','hidden'); }
function openAnnulModal()  { document.getElementById('annulModal').classList.replace('hidden','flex'); }
function closeAnnulModal() { document.getElementById('annulModal').classList.replace('flex','hidden'); }
['rejetModal','annulModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', e => { if (e.target === e.currentTarget) e.currentTarget.classList.replace('flex','hidden'); });
});
</script>
