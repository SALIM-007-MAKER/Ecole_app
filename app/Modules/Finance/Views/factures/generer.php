<?php
/**
 * Finance V2 — Génération de factures en masse
 */
$fraisTypes = $fraisTypes ?? [];
$classes    = $classes    ?? [];
$niveaux    = $niveaux    ?? [];
$errors     = $errors     ?? [];
?>
<div class="max-w-2xl mx-auto space-y-6">

    <div class="flex items-center gap-4">
        <a href="<?= BASE_URL ?>/v2/finance/factures" class="text-slate-400 hover:text-slate-600">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($title) ?></h1>
            <p class="text-sm text-slate-500 mt-1">Créer des factures pour tous les élèves d'une classe ou d'un niveau.</p>
        </div>
    </div>

    <?php if (!empty($errors['global'])): ?>
    <div class="flex items-start gap-3 p-4 bg-red-50 text-red-800 rounded-xl border border-red-200">
        <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0 mt-0.5"></i>
        <span><?= htmlspecialchars($errors['global']) ?></span>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/v2/finance/factures/generer">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">

        <div class="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100">

            <!-- Période -->
            <div class="p-5 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Période & scope</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">
                            Année scolaire <span class="form-required">*</span>
                        </label>
                        <input type="text" name="annee_scolaire" placeholder="ex: 2026-2027"
                               pattern="\d{4}-\d{4}" required
                               class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Date d'échéance</label>
                        <input type="date" name="date_echeance"
                               class="form-input">
                    </div>
                </div>

                <div>
                    <label class="form-label">Générer pour</label>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="scope" value="classe" checked
                                   class="text-violet-600" onchange="toggleScope()">
                            <span class="text-sm">Une classe</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="scope" value="niveau"
                                   class="text-violet-600" onchange="toggleScope()">
                            <span class="text-sm">Un niveau entier</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="scope" value="tous"
                                   class="text-violet-600" onchange="toggleScope()">
                            <span class="text-sm">Tous les élèves actifs</span>
                        </label>
                    </div>
                </div>

                <div id="scope-classe">
                    <label class="form-label">Classe</label>
                    <select name="classe_id"
                            class="form-select">
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($classes as $cl): ?>
                        <option value="<?= $cl->id ?>"><?= htmlspecialchars($cl->nom) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="scope-niveau" class="hidden">
                    <label class="form-label">Niveau</label>
                    <select name="niveau"
                            class="form-select">
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($niveaux as $n): ?>
                        <option value="<?= htmlspecialchars($n) ?>"><?= htmlspecialchars($n) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Types de frais -->
            <div class="p-5 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">
                    Types de frais à facturer <span class="form-required">*</span>
                </h2>
                <?php if (empty($fraisTypes)): ?>
                <p class="text-sm text-amber-700 bg-amber-50 p-3 rounded-lg">
                    Aucun type de frais actif. Créez d'abord des frais dans le référentiel.
                </p>
                <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($fraisTypes as $ft): ?>
                    <label class="flex items-center gap-3 p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-violet-50 hover:border-violet-300 transition-colors">
                        <input type="checkbox" name="frais_type_ids[]" value="<?= $ft->id ?>"
                               class="rounded text-violet-600 w-4 h-4">
                        <div class="flex-1">
                            <div class="text-sm font-medium text-slate-800"><?= htmlspecialchars($ft->nom) ?></div>
                            <div class="text-xs text-slate-400">
                                <?= number_format((float)$ft->montant_defaut, 0, ',', ' ') ?> XOF
                                · <?= $ft->periodicite ?>
                                <?= $ft->est_obligatoire ? '· <span class="text-amber-600">Obligatoire</span>' : '' ?>
                            </div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Notice -->
            <div class="p-5 bg-blue-50">
                <div class="flex items-start gap-3 text-sm text-blue-800">
                    <i data-lucide="info" class="w-5 h-5 flex-shrink-0 mt-0.5"></i>
                    <div>
                        <strong>Important :</strong> Les factures seront créées en statut <em>brouillon</em>.
                        Si un élève possède déjà une facture active pour les mêmes frais sur la même année, il sera ignoré.
                        Les factures devront être émises manuellement.
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="p-5 flex justify-end gap-3">
                <a href="<?= BASE_URL ?>/v2/finance/factures" class="px-4 py-2 text-sm text-slate-600">Annuler</a>
                <button type="submit"
                        onclick="return confirm('Lancer la génération en masse ? Cette opération peut créer de nombreuses factures.')"
                        class="px-6 py-2 text-sm font-medium text-white bg-violet-600 rounded-lg hover:bg-violet-700">
                    <i data-lucide="zap" class="inline w-4 h-4 mr-1"></i> Lancer la génération
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function toggleScope() {
    const scope = document.querySelector('[name=scope]:checked')?.value;
    document.getElementById('scope-classe').classList.toggle('hidden', scope !== 'classe');
    document.getElementById('scope-niveau').classList.toggle('hidden', scope !== 'niveau');
}
</script>
