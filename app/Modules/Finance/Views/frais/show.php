<?php
/**
 * Finance V2 — Détail d'un type de frais
 * GET /v2/finance/frais/{id}
 */
$frais    = $frais ?? null;
$tarifs   = $tarifs ?? [];
$niveaux  = $niveaux ?? [];
$historique = $historique ?? [];

$statutClass = match($frais->statut) {
    'actif'   => 'bg-emerald-100 text-emerald-700',
    'inactif' => 'bg-amber-100 text-amber-700',
    'archive' => 'bg-slate-100 text-slate-500',
    default   => 'bg-slate-100 text-slate-500',
};
$statutLabel = match($frais->statut) {
    'actif'   => 'Actif',
    'inactif' => 'Inactif',
    'archive' => 'Archivé',
    default   => $frais->statut,
};
?>
<div class="space-y-6">

    <!-- En-tête -->
    <div class="flex items-start justify-between gap-4">
        <div class="flex items-start gap-4">
            <a href="<?= BASE_URL ?>/v2/finance/frais"
               class="w-9 h-9 rounded-lg border border-slate-200 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-50 flex-shrink-0 transition-colors">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
            </a>
            <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
                <i data-lucide="list-checks" class="w-5 h-5 text-violet-600"></i>
            </div>
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($frais->nom) ?></h1>
                    <span class="px-2.5 py-0.5 text-xs font-medium rounded-full <?= $statutClass ?>">
                        <?= $statutLabel ?>
                    </span>
                </div>
                <div class="flex items-center gap-2 mt-1">
                    <code class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded font-mono">
                        <?= htmlspecialchars($frais->code) ?>
                    </code>
                    <?php if ($frais->categorie_nom): ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium text-white"
                          style="background-color:<?= htmlspecialchars($frais->categorie_couleur ?? '#6366f1') ?>">
                        <?= htmlspecialchars($frais->categorie_nom) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <?php if ($frais->statut !== 'archive'): ?>
        <div class="flex items-center gap-2">
            <?php if ($frais->statut === 'actif' && $canUpdate): ?>
            <form method="POST" action="<?= BASE_URL ?>/v2/finance/frais/<?= $frais->id ?>/desactiver">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                <button type="submit" class="px-3 py-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100">
                    <i data-lucide="pause-circle" class="inline w-4 h-4 mr-1"></i> Désactiver
                </button>
            </form>
            <?php elseif ($frais->statut === 'inactif' && $canUpdate): ?>
            <form method="POST" action="<?= BASE_URL ?>/v2/finance/frais/<?= $frais->id ?>/activer">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                <button type="submit" class="px-3 py-2 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg hover:bg-emerald-100">
                    <i data-lucide="play-circle" class="inline w-4 h-4 mr-1"></i> Activer
                </button>
            </form>
            <?php endif; ?>

            <?php if ($canUpdate): ?>
            <a href="<?= BASE_URL ?>/v2/finance/frais/<?= $frais->id ?>/edit" class="btn btn-outline">
                <i data-lucide="pencil" class="w-4 h-4"></i> Modifier
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Flash messages -->
    <?php if ($flash = \Core\Session::getFlash('success')): ?>
    <div class="flex items-center gap-3 p-4 bg-emerald-50 text-emerald-800 rounded-xl border border-emerald-200">
        <i data-lucide="check-circle" class="w-5 h-5 flex-shrink-0"></i>
        <span><?= htmlspecialchars($flash) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($flash = \Core\Session::getFlash('error')): ?>
    <div class="flex items-center gap-3 p-4 bg-red-50 text-red-800 rounded-xl border border-red-200">
        <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0"></i>
        <span><?= htmlspecialchars($flash) ?></span>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-3 gap-6">

        <!-- Colonne gauche : infos + tarifs -->
        <div class="col-span-2 space-y-6">

            <!-- Fiche principale -->
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4">Informations</h2>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div>
                        <dt class="text-slate-500">Montant par défaut</dt>
                        <dd class="font-semibold text-slate-800 font-mono">
                            <?= number_format((float)$frais->montant_defaut, 0, ',', ' ') ?>
                            <span class="font-normal text-slate-400"><?= htmlspecialchars($frais->devise) ?></span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Périodicité</dt>
                        <dd class="text-slate-800">
                            <?= \App\Modules\Finance\DTO\FraisTypeDTO::PERIODICITES[$frais->periodicite] ?? $frais->periodicite ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Type</dt>
                        <dd class="text-slate-800">
                            <?= $frais->est_obligatoire ? '⚠ Obligatoire' : 'Optionnel' ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Remise autorisée</dt>
                        <dd class="text-slate-800"><?= $frais->peut_avoir_remise ? 'Oui' : 'Non' ?></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Année scolaire</dt>
                        <dd class="text-slate-800"><?= $frais->annee_scolaire ?? '—  (toutes années)' ?></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Date limite</dt>
                        <dd class="text-slate-800"><?= $frais->date_limite ?? '—' ?></dd>
                    </div>
                    <?php if (!empty($niveaux)): ?>
                    <div class="col-span-2">
                        <dt class="text-slate-500">Niveaux cibles</dt>
                        <dd class="flex flex-wrap gap-1 mt-1">
                            <?php foreach ($niveaux as $niv): ?>
                            <span class="px-2 py-0.5 bg-violet-50 text-violet-700 text-xs rounded-full border border-violet-200">
                                <?= htmlspecialchars($niv) ?>
                            </span>
                            <?php endforeach; ?>
                        </dd>
                    </div>
                    <?php endif; ?>
                    <?php if ($frais->description): ?>
                    <div class="col-span-2">
                        <dt class="text-slate-500">Description</dt>
                        <dd class="text-slate-700"><?= htmlspecialchars($frais->description) ?></dd>
                    </div>
                    <?php endif; ?>
                </dl>
            </div>

            <!-- Tarifs par niveau/classe -->
            <div class="bg-white rounded-xl border border-slate-200">
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-semibold text-slate-700">Tarifs spéciaux par niveau / classe</h2>
                    <?php if ($canTarifs && $frais->statut !== 'archive'): ?>
                    <button onclick="document.getElementById('form-tarif').classList.toggle('hidden')"
                            class="text-xs text-violet-600 hover:underline">+ Ajouter un tarif</button>
                    <?php endif; ?>
                </div>

                <!-- Formulaire ajout tarif (caché par défaut) -->
                <?php if ($canTarifs && $frais->statut !== 'archive'): ?>
                <form id="form-tarif" class="hidden p-4 bg-slate-50 border-b border-slate-100"
                      method="POST" action="<?= BASE_URL ?>/v2/finance/frais/<?= $frais->id ?>/tarif">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                    <div class="grid grid-cols-4 gap-3 items-end">
                        <div>
                            <label class="form-label text-xs mb-1">Année scolaire *</label>
                            <input type="text" name="annee_scolaire" placeholder="2026-2027" required
                                   pattern="\d{4}-\d{4}"
                                   class="form-input">
                        </div>
                        <div>
                            <label class="form-label text-xs mb-1">Niveau</label>
                            <select name="niveau" class="form-select">
                                <option value="">Tous niveaux</option>
                                <?php foreach (array_merge(...array_values(\App\Models\ClasseModel::NIVEAUX)) as $n): ?>
                                <option value="<?= $n ?>"><?= $n ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label text-xs mb-1">Montant *</label>
                            <input type="number" name="montant" step="0.01" min="0.01" required
                                   class="form-input">
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary w-full">
                                Enregistrer
                            </button>
                        </div>
                    </div>
                </form>
                <?php endif; ?>

                <?php if (empty($tarifs)): ?>
                <div class="px-5 py-8 text-center text-slate-400 text-sm">
                    Aucun tarif spécial défini — le montant par défaut s'applique.
                </div>
                <?php else: ?>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-xs text-slate-500 uppercase">
                            <th class="px-4 py-2 text-left">Année</th>
                            <th class="px-4 py-2 text-left">Niveau</th>
                            <th class="px-4 py-2 text-left">Classe</th>
                            <th class="px-4 py-2 text-right">Montant</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($tarifs as $tarif): ?>
                        <tr>
                            <td class="px-4 py-2 font-mono text-xs"><?= htmlspecialchars($tarif->annee_scolaire) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($tarif->niveau ?? '—') ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($tarif->classe_nom ?? '—') ?></td>
                            <td class="px-4 py-2 text-right font-semibold font-mono">
                                <?= number_format((float)$tarif->montant, 0, ',', ' ') ?>
                                <span class="font-normal text-slate-400 text-xs"><?= htmlspecialchars($tarif->devise) ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Colonne droite : historique + actions danger -->
        <div class="space-y-6">

            <!-- Actions danger -->
            <?php if ($frais->statut !== 'archive' && ($canArchive || $canDelete)): ?>
            <div class="bg-white rounded-xl border border-slate-200 p-4 space-y-3">
                <h2 class="text-sm font-semibold text-slate-700">Actions</h2>

                <?php if ($canArchive): ?>
                <button onclick="document.getElementById('modal-archiver').classList.remove('hidden')"
                        class="w-full px-4 py-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100">
                    <i data-lucide="archive" class="inline w-4 h-4 mr-1"></i> Archiver ce frais
                </button>
                <?php endif; ?>

                <?php if ($canDelete && $frais->statut !== 'actif'): ?>
                <button onclick="if(confirm('Supprimer définitivement ce frais ?')) document.getElementById('form-delete').submit()"
                        class="w-full px-4 py-2 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100">
                    <i data-lucide="trash-2" class="inline w-4 h-4 mr-1"></i> Supprimer définitivement
                </button>
                <form id="form-delete" method="POST" action="<?= BASE_URL ?>/v2/finance/frais/<?= $frais->id ?>/delete">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Historique -->
            <div class="bg-white rounded-xl border border-slate-200">
                <div class="px-4 py-3 border-b border-slate-100">
                    <h2 class="text-sm font-semibold text-slate-700">Historique des modifications</h2>
                </div>
                <div class="divide-y divide-slate-50 max-h-80 overflow-y-auto">
                    <?php if (empty($historique)): ?>
                    <div class="px-4 py-6 text-center text-slate-400 text-sm">Aucun historique.</div>
                    <?php else: ?>
                    <?php foreach ($historique as $h): ?>
                    <div class="px-4 py-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium text-slate-700 capitalize"><?= htmlspecialchars($h->action) ?></span>
                            <span class="text-xs text-slate-400"><?= date('d/m H:i', strtotime($h->created_at)) ?></span>
                        </div>
                        <?php if ($h->user_nom): ?>
                        <div class="text-xs text-slate-400 mt-0.5">
                            <?= htmlspecialchars($h->user_prenom . ' ' . $h->user_nom) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal archivage -->
<div id="modal-archiver" class="hidden fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-2">Archiver ce frais</h3>
        <p class="text-sm text-slate-600 mb-4">
            Le frais archivé restera consultable dans l'historique mais ne pourra plus être utilisé
            pour de nouvelles factures. Cette action est irréversible.
        </p>
        <form method="POST" action="<?= BASE_URL ?>/v2/finance/frais/<?= $frais->id ?>/archiver">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
            <div class="mb-4">
                <label class="form-label">Motif d'archivage *</label>
                <textarea name="motif" rows="3" required
                          class="form-textarea"
                          placeholder="Ex: Remplacé par le tarif 2027-2028"></textarea>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-archiver').classList.add('hidden')"
                        class="btn btn-secondary">Annuler</button>
                <button type="submit" class="btn btn-warning">
                    Confirmer l'archivage
                </button>
            </div>
        </form>
    </div>
</div>
