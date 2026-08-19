<?php
/** @var array $user */
/** @var array $reward */
/** @var array $categories */

?>
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= BASE_URL ?>/v2/vie-scolaire/recompenses/<?= $reward['id'] ?>"
           class="inline-flex items-center gap-2 px-3 py-1.5 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm transition-colors flex-shrink-0">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour
        </a>
        <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
            <i data-lucide="pencil" class="w-5 h-5 text-violet-600"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800">Modifier la récompense #<?= $reward['id'] ?></h1>
    </div>

    <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mb-6 text-sm text-amber-800">
        Seuls le motif, la catégorie, le niveau et la date peuvent être modifiés.
        L'élève et la classe ne sont pas modifiables.
    </div>


    <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/recompenses/<?= $reward['id'] ?>/update"
          class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-5 max-w-2xl">
        <input type="hidden" name="_csrf_token"     value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
        <input type="hidden" name="eleve_id"       value="<?= $reward['eleve_id'] ?>">
        <input type="hidden" name="classe_id"      value="<?= $reward['classe_id'] ?>">
        <input type="hidden" name="annee_scolaire" value="<?= htmlspecialchars($reward['annee_scolaire']) ?>">

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="form-label">Catégorie <span class="form-required">*</span></label>
                <select name="categorie_id" required
                        class="form-select">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $reward['categorie_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Niveau <span class="form-required">*</span></label>
                <select name="niveau" required
                        class="form-select">
                    <option value="classe"        <?= $reward['niveau'] === 'classe'        ? 'selected' : '' ?>>Classe</option>
                    <option value="etablissement" <?= $reward['niveau'] === 'etablissement' ? 'selected' : '' ?>>Établissement</option>
                    <option value="academique"    <?= $reward['niveau'] === 'academique'    ? 'selected' : '' ?>>Académique</option>
                </select>
            </div>
        </div>

        <div>
            <label class="form-label">Date d'attribution <span class="form-required">*</span></label>
            <input type="date" name="date_attribution"
                   value="<?= htmlspecialchars($reward['date_attribution']) ?>" required
                   class="form-input max-w-xs">
        </div>

        <div>
            <label class="form-label">Motif <span class="form-required">*</span></label>
            <textarea name="motif" rows="4" required minlength="10"
                      class="form-textarea resize-none"
                      ><?= htmlspecialchars($reward['motif']) ?></textarea>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="<?= BASE_URL ?>/v2/vie-scolaire/recompenses/<?= $reward['id'] ?>"
               class="px-5 py-2 border border-slate-200 text-slate-600 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors">Annuler</a>
            <button type="submit"
                    class="px-6 py-2 bg-violet-600 hover:bg-violet-700 text-white rounded-lg text-sm font-medium transition-colors">
                Enregistrer
            </button>
        </div>
    </form>
<script>lucide.createIcons();</script>
