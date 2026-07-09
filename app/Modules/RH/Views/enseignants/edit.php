<?php
/** @var array $enseignant */
/** @var array $matieres */
/** @var array $assigned */
/** @var array $statuts */
/** @var array $niveaux */

$old    = \Core\Session::getFlash('old')    ?? $enseignant;
$errors = \Core\Session::getFlash('errors') ?? [];

function hEdit2(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function oldEdit2(string $k, array $old, string $d = ''): string { return hEdit2((string)($old[$k] ?? $d)); }
function errEdit2(string $k, array $e): string {
    if (!isset($e[$k])) return '';
    return '<p class="mt-1 text-xs text-red-600">' . hEdit2(implode(' ', (array)$e[$k])) . '</p>';
}
function hasEdit2(string $k, array $e): bool { return isset($e[$k]); }

$f    = fn(string $k) => oldEdit2($k, $old);
$e    = fn(string $k) => errEdit2($k, $errors);
$hasE = fn(string $k) => hasEdit2($k, $errors);
$ok   = 'border-slate-200 focus:ring-2 focus:ring-violet-300';
$err  = 'ring-2 ring-red-300 border-red-300';

// IDs des matières déjà affectées pour pré-sélection
$assignedIds = array_column($assigned, null, 'matiere_id');
?>

<div class="flex items-center gap-3 mb-6">
    <a href="<?= BASE_URL ?>/v2/rh/enseignants/<?= (int)$enseignant['id'] ?>" class="text-slate-400 hover:text-slate-600 transition-colors">
        <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="pencil" class="w-5 h-5 text-violet-600"></i>
            Modifier — <?= hEdit2($enseignant['prenom'] . ' ' . $enseignant['nom']) ?>
        </h2>
        <p class="text-sm text-slate-500 font-mono mt-0.5"><?= hEdit2($enseignant['matricule']) ?></p>
    </div>
</div>

<?php if ($flash = \Core\Session::getFlash('error')): ?>
<div class="flex items-center gap-2 p-3 mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm" role="alert">
    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
    <span><?= hEdit2($flash) ?></span>
    <button class="ml-auto" onclick="this.closest('[role=alert]').remove()"><i data-lucide="x" class="w-4 h-4"></i></button>
</div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/v2/rh/enseignants/<?= (int)$enseignant['id'] ?>" class="space-y-6">
    <input type="hidden" name="csrf_token" value="<?= hEdit2($_SESSION['csrf_token'] ?? '') ?>">
    <input type="hidden" name="employe_id" value="<?= (int)$enseignant['employe_id'] ?>">

    <!-- Employé lié (lecture seule) -->
    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 flex items-center gap-3 text-sm">
        <i data-lucide="user" class="w-5 h-5 text-slate-400 shrink-0"></i>
        <div>
            <span class="font-medium text-slate-900"><?= hEdit2($enseignant['prenom'] . ' ' . $enseignant['nom']) ?></span>
            <span class="text-slate-400 font-mono ml-2">[<?= hEdit2($enseignant['matricule']) ?>]</span>
        </div>
        <span class="ml-auto text-xs text-slate-400">Lien immuable</span>
        <a href="<?= BASE_URL ?>/v2/rh/employes/<?= (int)$enseignant['employe_id'] ?>"
           class="text-xs text-violet-600 hover:underline">Voir fiche</a>
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
                    <option value="<?= hEdit2($val) ?>" <?= $f('statut_pedagogique') === $val ? 'selected' : '' ?>>
                        <?= hEdit2($lbl) ?>
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

    <!-- Boutons -->
    <div class="flex items-center justify-end gap-3">
        <a href="<?= BASE_URL ?>/v2/rh/enseignants/<?= (int)$enseignant['id'] ?>"
           class="px-4 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
            Annuler
        </a>
        <button type="submit"
                class="inline-flex items-center gap-1.5 px-5 py-2 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition-colors">
            <i data-lucide="save" class="w-4 h-4"></i>Enregistrer
        </button>
    </div>
</form>

<!-- Gestion des habilitations matières (formulaire séparé) -->
<div class="bg-white border border-slate-200 rounded-xl shadow-sm mt-6">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i data-lucide="book-open" class="w-4 h-4 text-violet-600"></i>
            <h3 class="font-semibold text-slate-900 text-sm">Habilitations matières (<?= count($assigned) ?>)</h3>
        </div>
        <button type="button" id="addMatiere2"
                class="inline-flex items-center gap-1 text-xs text-violet-600 hover:text-violet-800 font-medium">
            <i data-lucide="plus" class="w-3.5 h-3.5"></i>Ajouter
        </button>
    </div>
    <div class="p-5">
        <form method="POST" action="<?= BASE_URL ?>/v2/rh/enseignants/<?= (int)$enseignant['id'] ?>/matieres">
            <input type="hidden" name="csrf_token" value="<?= hEdit2($_SESSION['csrf_token'] ?? '') ?>">
            <div class="space-y-2 mb-4" id="matieresContainer2">
                <?php foreach ($assigned as $i => $a): ?>
                <div class="matiere-block flex items-center gap-3 text-sm border border-slate-100 rounded-lg p-3">
                    <input type="hidden" name="matieres[<?= $i ?>][matiere_id]" value="<?= (int)$a['matiere_id'] ?>">
                    <span class="w-40 font-medium text-slate-800 truncate" title="<?= hEdit2($a['matiere_nom']) ?>">
                        <?= hEdit2($a['matiere_nom']) ?>
                    </span>
                    <input type="text" name="matieres[<?= $i ?>][niveaux]" value="<?= hEdit2($a['niveaux'] ?? '') ?>"
                           placeholder="primaire,moyen,secondaire"
                           class="flex-1 rounded border border-slate-200 text-xs px-2 py-1.5 focus:ring-1 focus:ring-violet-300 focus:outline-none">
                    <select name="matieres[<?= $i ?>][priorite]"
                            class="rounded border border-slate-200 text-xs px-2 py-1.5 bg-white focus:ring-1 focus:ring-violet-300 focus:outline-none">
                        <option value="1" <?= (int)($a['priorite'] ?? 1) === 1 ? 'selected' : '' ?>>Principale</option>
                        <option value="2" <?= (int)($a['priorite'] ?? 1) === 2 ? 'selected' : '' ?>>Secondaire</option>
                        <option value="3" <?= (int)($a['priorite'] ?? 1) === 3 ? 'selected' : '' ?>>Tertiaire</option>
                    </select>
                    <button type="button" onclick="this.closest('.matiere-block').remove()" class="text-slate-300 hover:text-red-400 shrink-0">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="flex items-center justify-between">
                <p class="text-xs text-slate-400">Les niveaux sont séparés par des virgules : primaire,moyen,secondaire</p>
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-lg bg-slate-800 text-white text-sm font-medium hover:bg-slate-700 transition-colors">
                    <i data-lucide="save" class="w-4 h-4"></i>Mettre à jour les habilitations
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const matieresData = <?= json_encode(array_map(fn($m) => ['id' => (int)$m['id'], 'nom' => $m['nom']], $matieres)) ?>;
    let idx = <?= count($assigned) ?>;

    document.getElementById('addMatiere2').addEventListener('click', function() {
        const options = matieresData.map(m => `<option value="${m.id}">${m.nom.replace(/</g,'&lt;')}</option>`).join('');
        const tpl = `<div class="matiere-block flex items-center gap-3 text-sm border border-slate-100 rounded-lg p-3">
            <select name="matieres[${idx}][matiere_id]" class="w-40 rounded border border-slate-200 text-xs px-2 py-1.5 bg-white focus:ring-1 focus:ring-violet-300 focus:outline-none">
                <option value="">— Matière —</option>${options}
            </select>
            <input type="text" name="matieres[${idx}][niveaux]" placeholder="primaire,moyen,secondaire"
                   class="flex-1 rounded border border-slate-200 text-xs px-2 py-1.5 focus:ring-1 focus:ring-violet-300 focus:outline-none">
            <select name="matieres[${idx}][priorite]" class="rounded border border-slate-200 text-xs px-2 py-1.5 bg-white focus:ring-1 focus:ring-violet-300 focus:outline-none">
                <option value="1">Principale</option>
                <option value="2">Secondaire</option>
                <option value="3">Tertiaire</option>
            </select>
            <button type="button" onclick="this.closest('.matiere-block').remove()" class="text-slate-300 hover:text-red-400 shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>`;
        document.getElementById('matieresContainer2').insertAdjacentHTML('beforeend', tpl);
        idx++;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
})();
</script>
