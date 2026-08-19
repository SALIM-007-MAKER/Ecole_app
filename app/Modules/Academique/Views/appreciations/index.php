<?php
$classes  = $classes  ?? [];
$matieres = $matieres ?? [];
$periodes = $periodes ?? [];
?>

<div class="mb-6">
    <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
        <i data-lucide="message-square-text" class="w-5 h-5 text-violet-500"></i>
        Appréciations par matière
    </h2>
    <p class="text-sm text-slate-500 mt-0.5">
        Saisissez l'appréciation de chaque élève pour une matière et une période —
        affichée telle quelle dans le bulletin imprimé.
    </p>
</div>

<?php $flash = \Core\Session::getFlash(); ?>
<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> mb-4" role="alert">
    <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-triangle' ?>" class="w-4 h-4 shrink-0"></i>
    <span class="flex-1 text-sm"><?= htmlspecialchars($flash['message'], ENT_QUOTES) ?></span>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 max-w-xl">
    <form method="GET" id="formChoixAppreciation" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Classe</label>
            <select name="classe_id" class="form-input w-full" required>
                <option value="">— Choisir —</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= $c->id ?>"><?= htmlspecialchars($c->niveau . ' ' . $c->nom, ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Matière</label>
            <select name="matiere_id" class="form-input w-full" required>
                <option value="">— Choisir —</option>
                <?php foreach ($matieres as $m): ?>
                <option value="<?= $m->id ?>"><?= htmlspecialchars($m->nom, ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Période</label>
            <select name="periode_id" class="form-input w-full" required>
                <option value="">— Choisir —</option>
                <?php foreach ($periodes as $p): ?>
                <option value="<?= $p->id ?>">
                    <?= htmlspecialchars('[' . $p->annee_scolaire . '] ' . $p->nom, ENT_QUOTES) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary w-full">
            <i data-lucide="arrow-right" class="w-4 h-4"></i>Accéder à la saisie
        </button>
    </form>
</div>

<script>
document.getElementById('formChoixAppreciation').addEventListener('submit', function (e) {
    e.preventDefault();
    const classeId  = this.querySelector('[name=classe_id]').value;
    const matiereId = this.querySelector('[name=matiere_id]').value;
    const periodeId = this.querySelector('[name=periode_id]').value;
    if (!classeId || !matiereId || !periodeId) return;
    window.location.href = '<?= BASE_URL ?>/v2/academique/classes/' + classeId
        + '/matieres/' + matiereId + '/appreciations?periode_id=' + periodeId;
});
</script>
