<?php /** @var array $plans, $errors */ ?>
<a href="<?= BASE_URL ?>/platform/etablissements" class="text-sm text-slate-500 hover:underline flex items-center gap-1 mb-4"><i data-lucide="arrow-left" class="w-4 h-4"></i>Retour à la liste</a>

<h2 class="text-lg font-bold text-slate-900 flex items-center gap-2 mb-6">
    <i data-lucide="plus-circle" class="w-5 h-5 text-violet-600"></i>Nouvel établissement
</h2>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-5" role="alert">
    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
    <ul class="flex-1 list-disc list-inside space-y-0.5">
        <?php foreach ($errors as $msg): ?><li class="text-sm"><?= htmlspecialchars((string)$msg, ENT_QUOTES) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/platform/etablissements" class="rounded-xl border border-slate-200 bg-white shadow-sm p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
    <div>
        <label class="form-label" for="nom">Nom</label>
        <input type="text" id="nom" name="nom" class="form-input" required>
    </div>
    <div>
        <label class="form-label" for="nom_court">Nom court</label>
        <input type="text" id="nom_court" name="nom_court" class="form-input">
    </div>
    <div>
        <label class="form-label" for="slug">Slug</label>
        <input type="text" id="slug" name="slug" class="form-input font-mono" placeholder="lycee-ibn-badis" required>
    </div>
    <fieldset class="sm:col-span-2" id="bloc-cycles" style="border:0;padding:0;margin:0;min-width:0">
        <legend class="form-label" style="padding:0">Cycles proposés <span class="text-slate-400 font-normal">(plusieurs choix possibles)</span></legend>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem 1.5rem;margin-top:.25rem">
            <?php foreach (\Core\Platform\EtablissementCycles::CYCLES as $cycle): ?>
            <label class="text-sm text-slate-700" style="display:inline-flex;align-items:center;gap:.5rem;cursor:pointer">
                <input type="checkbox" name="cycles[]" value="<?= htmlspecialchars($cycle, ENT_QUOTES) ?>" class="cycle-box">
                <?= htmlspecialchars($cycle, ENT_QUOTES) ?>
            </label>
            <?php endforeach; ?>
        </div>
        <p class="text-xs text-slate-500 mt-1">Le type de l'établissement en est déduit automatiquement (un seul cycle : son type ; plusieurs : groupe scolaire).</p>
    </fieldset>
    <div class="sm:col-span-2">
        <label class="form-label" for="type_hors_cycle">Ou autre type d'établissement</label>
        <select id="type_hors_cycle" name="type_hors_cycle" class="form-input">
            <option value="">Établissement scolaire (cycles ci-dessus)</option>
            <option value="universite">Université</option>
            <option value="formation_pro">Formation professionnelle</option>
        </select>
        <p class="text-xs text-slate-500 mt-1">Une université ou une formation professionnelle n'a pas de cycles scolaires : les cases ci-dessus sont alors désactivées.</p>
    </div>
    <div>
        <label class="form-label" for="pays">Pays</label>
        <input type="text" id="pays" name="pays" class="form-input" value="DZ" maxlength="2">
    </div>
    <?php if (!empty($peutChoisirPlan)): ?>
    <div>
        <label class="form-label" for="plan_id">Plan initial</label>
        <select id="plan_id" name="plan_id" class="form-input">
            <option value="">Aucun</option>
            <?php foreach ($plans as $p): ?>
            <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nom'], ENT_QUOTES) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="sm:col-span-2 flex justify-end">
        <button type="submit" class="btn btn-primary"><i data-lucide="save" class="w-4 h-4"></i>Créer</button>
    </div>
</form>
<script>
// Université / formation pro : pas de cycles scolaires → cases désactivées et décochées (le serveur revalide de toute façon).
(function () {
    var sel = document.getElementById('type_hors_cycle');
    var boxes = document.querySelectorAll('.cycle-box');
    function sync() {
        var hors = sel.value !== '';
        boxes.forEach(function (b) { if (hors) { b.checked = false; } b.disabled = hors; });
        document.getElementById('bloc-cycles').style.opacity = hors ? '.5' : '1';
    }
    sel.addEventListener('change', sync);
    sync();
})();
</script>
