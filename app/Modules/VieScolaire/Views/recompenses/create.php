<?php
/** @var array $user */
/** @var array $categories */
/** @var array $classes */
/** @var array $eleves */

$title = 'Attribuer une récompense';
?>
    <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
        <a href="<?= BASE_URL ?>/v2/vie-scolaire/recompenses" class="hover:text-violet-600">Récompenses</a>
        <i data-lucide="chevron-right" class="w-3 h-3"></i>
        <span class="text-slate-700">Attribuer</span>
    </div>

    <div class="flex items-center justify-between gap-4 mb-6">
        <div class="flex items-start gap-4">
            <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
                <i data-lucide="award" class="w-5 h-5 text-violet-600"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Attribuer une récompense</h1>
                <p class="text-slate-500 text-sm mt-0.5">Valorisez le comportement ou les résultats d'un élève.</p>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/v2/vie-scolaire/recompenses"
           class="inline-flex items-center gap-2 px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm transition-colors flex-shrink-0">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour
        </a>
    </div>

    <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/recompenses"
          enctype="multipart/form-data"
          class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-6 max-w-2xl">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">

        <div class="space-y-4">
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                <i data-lucide="user" class="w-3.5 h-3.5"></i>
                Élève concerné
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Classe <span class="form-required">*</span></label>
                    <select name="classe_id" id="classe_id" required
                            class="form-select">
                        <option value="">Sélectionner</option>
                        <?php foreach ($classes as $c): ?>
                        <option value="<?= $c->id ?>"><?= htmlspecialchars($c->nom) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Élève <span class="form-required">*</span></label>
                    <select name="eleve_id" id="eleve_id" required
                            class="form-select">
                        <option value="">Sélectionner une classe d'abord</option>
                        <?php foreach ($eleves as $e): ?>
                        <option value="<?= $e->id ?>" data-classe="<?= $e->classe_id ?>">
                            <?= htmlspecialchars($e->nom . ' ' . $e->prenom) ?><?= $e->matricule ? ' — ' . htmlspecialchars($e->matricule) : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="space-y-4 pt-2 border-t border-slate-100">
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wide pt-4">
                <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                Détails de la récompense
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Année scolaire <span class="form-required">*</span></label>
                    <input type="text" name="annee_scolaire"
                           value="<?= date('Y') . '-' . (date('Y') + 1) ?>"
                           placeholder="2025-2026" required
                           class="form-input">
                </div>
                <div>
                    <label class="form-label">Date d'attribution <span class="form-required">*</span></label>
                    <input type="date" name="date_attribution" value="<?= date('Y-m-d') ?>" required
                           class="form-input">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Catégorie <span class="form-required">*</span></label>
                    <select name="categorie_id" required
                            class="form-select">
                        <option value="">Sélectionner…</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Niveau <span class="form-required">*</span></label>
                    <select name="niveau" required
                            class="form-select">
                        <option value="classe">Classe</option>
                        <option value="etablissement">Établissement</option>
                        <option value="academique">Académique</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="space-y-4 pt-2 border-t border-slate-100">
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wide pt-4">
                <i data-lucide="message-square" class="w-3.5 h-3.5"></i>
                Motif
            </div>

            <div>
                <label class="form-label">Motif de la récompense <span class="form-required">*</span></label>
                <textarea name="motif" rows="4" required minlength="10"
                          placeholder="Décrivez le motif de la récompense (minimum 10 caractères)"
                          class="form-textarea resize-none"></textarea>
            </div>

            <div>
                <label class="form-label">Pièce jointe (optionnel)</label>
                <input type="file" name="piece_jointe" accept=".pdf,.jpg,.jpeg,.png"
                       class="w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100">
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
            <a href="<?= BASE_URL ?>/v2/vie-scolaire/recompenses"
               class="px-5 py-2 border border-slate-200 text-slate-600 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors">Annuler</a>
            <button type="submit"
                    class="inline-flex items-center gap-2 px-6 py-2 bg-violet-600 hover:bg-violet-700 text-white rounded-lg text-sm font-medium transition-colors">
                <i data-lucide="check" class="w-4 h-4"></i>
                Attribuer la récompense
            </button>
        </div>
    </form>

<script>
(function () {
  const classeSelect = document.getElementById('classe_id');
  const eleveSelect  = document.getElementById('eleve_id');
  const eleveOptions = Array.from(eleveSelect.options).slice(1);

  function filterEleves() {
    const classeId = classeSelect.value;
    const selected = eleveSelect.value;
    eleveSelect.innerHTML = '';
    eleveSelect.appendChild(new Option(classeId ? 'Sélectionner' : 'Sélectionner une classe d\'abord', ''));
    eleveOptions.forEach(function (opt) {
      if (classeId && opt.dataset.classe === classeId) {
        eleveSelect.appendChild(opt.cloneNode(true));
      }
    });
    if ([...eleveSelect.options].some(function (o) { return o.value === selected; })) {
      eleveSelect.value = selected;
    }
  }

  classeSelect.addEventListener('change', filterEleves);
  filterEleves();
})();
</script>
