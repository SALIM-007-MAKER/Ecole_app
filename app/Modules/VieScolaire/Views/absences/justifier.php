<?php
$absence   = $absence ?? [];
$motifs    = $motifs  ?? [];
$errors    = $errors  ?? [];
$old       = $old     ?? [];
$csrfToken = \Core\Session::getCsrfToken();
?>

<div class="flex items-center gap-4 mb-6">
    <a href="<?= BASE_URL ?>/v2/vie-scolaire/absences/<?= $absence['id'] ?>"
       class="inline-flex items-center gap-2 px-3 py-1.5 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm transition-colors flex-shrink-0">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour
    </a>
    <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
        <i data-lucide="file-plus" class="w-5 h-5 text-violet-600"></i>
        Soumettre une justification
    </h2>
</div>

<!-- Rappel absence -->
<div class="bg-slate-50 border border-slate-200 rounded-xl p-4 mb-5 text-sm text-slate-600">
    Absence du <span class="font-medium text-slate-900">
        <?= date('d/m/Y', strtotime($absence['date_absence'])) ?>
    </span>
    — Élève : <span class="font-medium text-slate-900">
        <?= htmlspecialchars($absence['eleve_prenom'] . ' ' . $absence['eleve_nom'], ENT_QUOTES) ?>
    </span>
    — Classe : <span class="font-medium text-slate-900">
        <?= htmlspecialchars($absence['classe_nom'] ?? '', ENT_QUOTES) ?>
    </span>
</div>

<?php if (!empty($errors)): ?>
<div class="mb-4 p-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
    <div class="flex items-center gap-2 font-medium mb-1">
        <i data-lucide="alert-circle" class="w-4 h-4"></i>Erreurs
    </div>
    <ul class="list-disc list-inside space-y-1">
        <?php foreach ((array)$errors as $msgs): ?>
            <?php foreach ((array)$msgs as $msg): ?>
            <li><?= htmlspecialchars($msg, ENT_QUOTES) ?></li>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 max-w-xl">
    <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/absences/<?= $absence['id'] ?>/justifier"
          enctype="multipart/form-data">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">

        <div class="space-y-5">
            <!-- Motif -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Motif</label>
                <select name="motif_id"
                        class="w-full rounded-lg border border-slate-200 text-sm px-3 py-2 bg-white focus:ring-2 focus:ring-violet-300 focus:outline-none">
                    <option value="">-- Aucun motif prédéfini --</option>
                    <?php foreach ($motifs as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= ($old['motif_id'] ?? '') == $m['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['libelle'], ENT_QUOTES) ?>
                        <?php if ($m['necessite_justificatif']): ?>(justificatif requis)<?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Description -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                    Description <span class="text-slate-400 text-xs">(obligatoire si aucun motif)</span>
                </label>
                <textarea name="description" rows="4"
                          class="w-full rounded-lg border border-slate-200 text-sm px-3 py-2 focus:ring-2 focus:ring-violet-300 focus:outline-none"
                          placeholder="Expliquez les circonstances de l'absence…"><?= htmlspecialchars($old['description'] ?? '', ENT_QUOTES) ?></textarea>
            </div>

            <!-- Fichier justificatif -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Fichier justificatif</label>
                <input type="file" name="fichier_justificatif" accept=".pdf,.jpg,.jpeg,.png"
                       class="block w-full text-sm text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100">
                <p class="text-xs text-slate-400 mt-1">PDF, JPG ou PNG — max 5 Mo</p>
            </div>
        </div>

        <div class="mt-6 flex items-center gap-3 pt-5 border-t border-slate-100">
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition-colors">
                <i data-lucide="send" class="w-4 h-4"></i>Soumettre la justification
            </button>
            <a href="<?= BASE_URL ?>/v2/vie-scolaire/absences/<?= $absence['id'] ?>"
               class="text-sm text-slate-500 hover:text-slate-700">Annuler</a>
        </div>
    </form>
</div>
