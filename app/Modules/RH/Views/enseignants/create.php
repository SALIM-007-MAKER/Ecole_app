<?php
/** @var array $employes */
/** @var array $statuts */
/** @var array $niveaux */
/** @var array $matieres */

$old    = \Core\Session::getFlash('old')    ?? [];
$errors = \Core\Session::getFlash('errors') ?? [];

function hC2(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function oldC2(string $k, array $old, string $d = ''): string { return hC2((string)($old[$k] ?? $d)); }
function errC2(string $k, array $e): string {
    if (!isset($e[$k])) return '';
    return '<p class="mt-1 text-xs text-red-600">' . hC2(implode(' ', (array)$e[$k])) . '</p>';
}
function hasC2(string $k, array $e): bool { return isset($e[$k]); }

$f    = fn(string $k) => oldC2($k, $old);
$e    = fn(string $k) => errC2($k, $errors);
$hasE = fn(string $k) => hasC2($k, $errors);
$ok   = 'border-slate-200 focus:ring-2 focus:ring-violet-300';
$err  = 'ring-2 ring-red-300 border-red-300';
?>

<div class="flex items-center gap-3 mb-6">
    <a href="<?= BASE_URL ?>/v2/rh/enseignants" class="text-slate-400 hover:text-slate-600 transition-colors">
        <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="user-plus" class="w-5 h-5 text-violet-600"></i>
            Nouveau profil enseignant
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">Créer un profil pédagogique lié à un employé existant</p>
    </div>
</div>

<?php if ($flash = \Core\Session::getFlash('error')): ?>
<div class="flex items-center gap-2 p-3 mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm" role="alert">
    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
    <span><?= hC2($flash) ?></span>
    <button class="ml-auto" onclick="this.closest('[role=alert]').remove()"><i data-lucide="x" class="w-4 h-4"></i></button>
</div>
<?php endif; ?>

<?php if (empty($employes)): ?>
<div class="bg-amber-50 border border-amber-200 rounded-xl p-5 text-sm text-amber-800 flex items-start gap-3">
    <i data-lucide="alert-triangle" class="w-5 h-5 shrink-0 mt-0.5"></i>
    <div>
        <strong>Aucun employé disponible.</strong><br>
        Tous les employés actifs ont déjà un profil enseignant, ou il n'existe aucun employé actif.
        <a href="<?= BASE_URL ?>/v2/rh/employes/create" class="underline ml-1">Créer un employé</a>
    </div>
</div>
<?php else: ?>

<form method="POST" action="<?= BASE_URL ?>/v2/rh/enseignants" class="space-y-6">
    <input type="hidden" name="csrf_token" value="<?= hC2($_SESSION['csrf_token'] ?? '') ?>">

    <!-- Sélection de l'employé -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <i data-lucide="user" class="w-4 h-4 text-violet-600"></i>
            <h3 class="font-semibold text-slate-900 text-sm">Employé associé <span class="text-red-500">*</span></h3>
        </div>
        <div class="p-5">
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-700 mb-4 flex items-center gap-2">
                <i data-lucide="info" class="w-4 h-4 shrink-0"></i>
                Seuls les employés actifs sans profil enseignant sont listés.
            </div>
            <select name="employe_id" required
                    class="w-full rounded-lg border text-sm px-3 py-2 bg-white focus:outline-none <?= $hasE('employe_id') ? $err : $ok ?>">
                <option value="">— Sélectionner un employé —</option>
                <?php foreach ($employes as $emp): ?>
                <option value="<?= (int)$emp['id'] ?>" <?= $f('employe_id') == $emp['id'] ? 'selected' : '' ?>>
                    <?= hC2($emp['label']) ?>
                    <?php if ($emp['type_personnel']): ?>(<?= hC2($emp['type_personnel']) ?>)<?php endif; ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?= $e('employe_id') ?>
        </div>
    </div>

    <!-- Profil pédagogique -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <i data-lucide="graduation-cap" class="w-4 h-4 text-violet-600"></i>
            <h3 class="font-semibold text-slate-900 text-sm">Profil pédagogique</h3>
        </div>
        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Statut pédagogique <span class="text-red-500">*</span></label>
                <select name="statut_pedagogique"
                        class="w-full rounded-lg border text-sm px-3 py-2 bg-white focus:outline-none <?= $hasE('statut_pedagogique') ? $err : $ok ?>">
                    <?php foreach ($statuts as $val => $lbl): ?>
                    <option value="<?= hC2($val) ?>" <?= ($f('statut_pedagogique') ?: 'titulaire') === $val ? 'selected' : '' ?>>
                        <?= hC2($lbl) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?= $e('statut_pedagogique') ?>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Charge horaire max (h/semaine)</label>
                <input type="number" name="charge_horaire_max" min="1" max="40"
                       value="<?= $f('charge_horaire_max') ?: '18' ?>"
                       class="w-full rounded-lg border text-sm px-3 py-2 focus:outline-none <?= $hasE('charge_horaire_max') ? $err : $ok ?>">
                <?= $e('charge_horaire_max') ?>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Spécialité principale</label>
                <input type="text" name="specialite_principale" value="<?= $f('specialite_principale') ?>"
                       placeholder="ex : Mathématiques, Physique-Chimie…"
                       class="w-full rounded-lg border text-sm px-3 py-2 focus:outline-none <?= $ok ?>">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Date de début d'enseignement</label>
                <input type="date" name="date_debut_enseignement" value="<?= $f('date_debut_enseignement') ?>"
                       class="w-full rounded-lg border text-sm px-3 py-2 focus:outline-none <?= $hasE('date_debut_enseignement') ? $err : $ok ?>">
                <?= $e('date_debut_enseignement') ?>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Notes pédagogiques</label>
                <textarea name="notes_pedagogiques" rows="2"
                          class="w-full rounded-lg border text-sm px-3 py-2 focus:outline-none resize-none <?= $ok ?>"><?= $f('notes_pedagogiques') ?></textarea>
            </div>
        </div>
    </div>

    <!-- Habilitations matières (optionnel à la création) -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="book-open" class="w-4 h-4 text-violet-600"></i>
                <h3 class="font-semibold text-slate-900 text-sm">Habilitations matières <span class="text-xs font-normal text-slate-400">(optionnel)</span></h3>
            </div>
            <button type="button" id="addMatiere"
                    class="inline-flex items-center gap-1 text-xs text-violet-600 hover:text-violet-800 font-medium">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i>Ajouter
            </button>
        </div>
        <div class="p-5 space-y-3" id="matieresContainer">
            <!-- Lignes ajoutées dynamiquement -->
        </div>
    </div>

    <!-- Boutons -->
    <div class="flex items-center justify-end gap-3">
        <a href="<?= BASE_URL ?>/v2/rh/enseignants"
           class="px-4 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
            Annuler
        </a>
        <button type="submit"
                class="inline-flex items-center gap-1.5 px-5 py-2 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition-colors">
            <i data-lucide="save" class="w-4 h-4"></i>Créer le profil
        </button>
    </div>
</form>

<script>
(function() {
    const matieresData = <?= json_encode(array_map(fn($m) => ['id' => (int)$m['id'], 'nom' => $m['nom']], $matieres)) ?>;
    const niveauxData  = <?= json_encode(array_keys($niveaux)) ?>;
    let idx = 0;

    document.getElementById('addMatiere').addEventListener('click', function() {
        const options = matieresData.map(m => `<option value="${m.id}">${m.nom.replace(/"/g,'&quot;')}</option>`).join('');
        const niveauxOpts = niveauxData.map(n => `<label class="flex items-center gap-1 text-xs cursor-pointer"><input type="checkbox" name="matieres[${idx}][niveaux_arr][]" value="${n}" class="rounded border-slate-300 text-violet-600"> ${n}</label>`).join('');

        const tpl = `<div class="matiere-block border border-slate-100 rounded-lg p-3 relative">
            <button type="button" onclick="this.closest('.matiere-block').remove()"
                    class="absolute top-2 right-2 text-slate-300 hover:text-red-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Matière</label>
                    <select name="matieres[${idx}][matiere_id]" class="w-full rounded border border-slate-200 text-sm px-2 py-1.5 bg-white focus:ring-1 focus:ring-violet-300 focus:outline-none">
                        <option value="">— Choisir —</option>${options}
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Niveaux (séparés par virgule)</label>
                    <input type="text" name="matieres[${idx}][niveaux]" placeholder="primaire,moyen,secondaire"
                           class="w-full rounded border border-slate-200 text-sm px-2 py-1.5 focus:ring-1 focus:ring-violet-300 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Priorité</label>
                    <select name="matieres[${idx}][priorite]" class="w-full rounded border border-slate-200 text-sm px-2 py-1.5 bg-white focus:ring-1 focus:ring-violet-300 focus:outline-none">
                        <option value="1">1 — Principale</option>
                        <option value="2">2 — Secondaire</option>
                        <option value="3">3 — Tertiaire</option>
                    </select>
                </div>
            </div>
        </div>`;
        document.getElementById('matieresContainer').insertAdjacentHTML('beforeend', tpl);
        idx++;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
})();
</script>
<?php endif; ?>
