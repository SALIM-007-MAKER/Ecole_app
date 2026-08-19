<?php
/** @var array $user */
/** @var array $dossier */
/** @var array $incidents */
/** @var array $types */

?>
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= BASE_URL ?>/v2/vie-scolaire/discipline/<?= $dossier['id'] ?>"
           class="inline-flex items-center gap-2 px-3 py-1.5 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm transition-colors flex-shrink-0">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour
        </a>
        <h1 class="text-2xl font-bold text-slate-800">Prononcer une sanction</h1>
    </div>

    <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mb-6 text-sm text-amber-800">
        <strong><?= htmlspecialchars($dossier['eleve_prenom'] . ' ' . $dossier['eleve_nom']) ?></strong>
        — Classe <?= htmlspecialchars($dossier['classe_nom']) ?>
        — <?= (int)$dossier['nb_incidents'] ?> incident(s) enregistré(s)
    </div>


    <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/discipline/<?= $dossier['id'] ?>/sanctionner"
          class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-5 max-w-2xl">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">

        <div>
            <label class="form-label">Lier à un incident (optionnel)</label>
            <select name="incident_id"
                    class="form-select">
                <option value="">Aucun incident spécifique</option>
                <?php foreach ($incidents as $inc): ?>
                    <option value="<?= $inc['id'] ?>">
                        #<?= $inc['id'] ?> — <?= date('d/m/Y', strtotime($inc['date_incident'])) ?>
                        — <?= ucfirst($inc['gravite']) ?> — <?= htmlspecialchars(mb_strimwidth($inc['description'], 0, 50, '…')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="form-label">Type de sanction <span class="form-required">*</span></label>
            <select name="type_sanction" id="type_sanction" required
                    onchange="toggleDuree(this.value)"
                    class="form-select">
                <option value="">Sélectionner…</option>
                <?php foreach ($types as $val => $label): ?>
                    <option value="<?= $val ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="bloc_duree" class="hidden">
            <label class="form-label">Durée (jours) <span class="form-required">*</span></label>
            <input type="number" name="duree_jours" min="1" max="365"
                   class="form-input">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="form-label">Date sanction <span class="form-required">*</span></label>
                <input type="date" name="date_sanction" value="<?= date('Y-m-d') ?>" required
                       class="form-input">
            </div>
            <div>
                <label class="form-label">Date début d'effet</label>
                <input type="date" name="date_debut"
                       class="form-input">
            </div>
        </div>

        <div>
            <label class="form-label">Motif <span class="form-required">*</span></label>
            <textarea name="motif" rows="3" required minlength="10"
                      placeholder="Exposé des motifs de la sanction (min. 10 caractères)"
                      class="form-textarea resize-none"></textarea>
        </div>

        <div>
            <label class="form-label">Description complémentaire</label>
            <textarea name="description" rows="2"
                      class="form-textarea resize-none"></textarea>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="<?= BASE_URL ?>/v2/vie-scolaire/discipline/<?= $dossier['id'] ?>"
               class="px-5 py-2 border border-slate-200 text-slate-600 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors">Annuler</a>
            <button type="submit"
                    class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium transition-colors">
                Prononcer la sanction
            </button>
        </div>
    </form>
<script>
lucide.createIcons();
function toggleDuree(val) {
    const bloc = document.getElementById('bloc_duree');
    const needs = ['exclusion_temp'].includes(val);
    bloc.classList.toggle('hidden', !needs);
    bloc.querySelector('input').required = needs;
}
</script>
