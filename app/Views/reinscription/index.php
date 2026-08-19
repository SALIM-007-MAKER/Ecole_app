<?php
$annees = $annees ?? [];
$source = $source ?? '';

/** Propose l'année suivante au format "2025-2026" -> "2026-2027". */
function reinscSuggererAnneeSuivante(string $annee): string {
    if (!preg_match('/^(\d{4})-(\d{4})$/', $annee, $m)) {
        return '';
    }
    return ((int)$m[1] + 1) . '-' . ((int)$m[2] + 1);
}
?>

<!-- Page header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="repeat" class="w-5 h-5 text-violet-600"></i>Réinscription — passage de classe
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">Faites passer en une seule opération tous les élèves d'une année scolaire vers la suivante.</p>
    </div>
    <a href="<?= BASE_URL ?>/classes" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Classes
    </a>
</div>

<?php if (empty($annees)): ?>
<div class="flex flex-col items-center justify-center gap-3 text-center text-slate-500 py-16 rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-1">
        <i data-lucide="info" class="w-8 h-8 text-slate-300"></i>
    </div>
    <p class="text-slate-500 font-medium">Aucune classe enregistrée pour l'instant.</p>
    <p class="text-sm text-slate-400">Créez d'abord les classes de l'année en cours.</p>
</div>
<?php else: ?>

<div class="flex justify-center">
<div class="w-full" style="max-width:38rem">
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <i data-lucide="calendar-range" class="w-4 h-4" style="color:#7c3aed"></i>
        <span class="font-semibold text-slate-700">Choisir les deux années</span>
    </div>
    <div class="p-5">
        <form method="GET" action="<?= BASE_URL ?>/reinscription/plan" class="space-y-5">
            <div>
                <label class="form-label">Année source <span style="color:#ef4444">*</span></label>
                <select name="source" id="selSource" class="form-input" required>
                    <?php foreach ($annees as $a): ?>
                    <option value="<?= htmlspecialchars($a, ENT_QUOTES) ?>" <?= $a === $source ? 'selected' : '' ?>><?= htmlspecialchars($a, ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-slate-400 mt-1">Les élèves actuellement inscrits dans les classes de cette année.</p>
            </div>
            <div>
                <label class="form-label">Année destination <span style="color:#ef4444">*</span></label>
                <input type="text" name="destination" id="selDestination" class="form-input"
                       value="<?= htmlspecialchars(reinscSuggererAnneeSuivante($source), ENT_QUOTES) ?>"
                       pattern="\d{4}-\d{4}" placeholder="2026-2027" required>
                <p class="text-xs text-slate-400 mt-1">Les classes de cette année doivent déjà exister (créez-les depuis <a href="<?= BASE_URL ?>/classes/create" class="text-violet-600 hover:underline" target="_blank">Nouvelle classe</a> si besoin).</p>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2" style="border-top:1px solid #e4e4ec;padding-top:1.25rem">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>Établir le plan de passage
                </button>
            </div>
        </form>
    </div>
</div>
</div>
</div>

<script>
document.getElementById('selSource').addEventListener('change', function () {
    var m = this.value.match(/^(\d{4})-(\d{4})$/);
    if (m) {
        document.getElementById('selDestination').value = (parseInt(m[1],10)+1) + '-' + (parseInt(m[2],10)+1);
    }
});
</script>
<?php endif; ?>
