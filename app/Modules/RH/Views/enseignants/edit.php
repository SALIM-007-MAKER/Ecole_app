<?php
/** @var array $enseignant */
/** @var array $matieres */
/** @var array $assigned */
/** @var array $statuts */
/** @var array $niveaux */

$old    = \Core\Session::getFlash('old')    ?? [];
$errors = \Core\Session::getFlash('errors') ?? [];

function hE2(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function fE2(string $k, array $old, array $enseignant, string $default = ''): string {
    if (array_key_exists($k, $old)) return hE2((string)$old[$k]);
    return hE2((string)($enseignant[$k] ?? $default));
}
function errE2(string $k, array $e): string {
    if (!isset($e[$k])) return '';
    return '<p class="mt-1 text-xs text-red-600">' . hE2(implode(' ', (array)$e[$k])) . '</p>';
}
function hasE2(string $k, array $e): bool { return isset($e[$k]); }

$f    = fn(string $k, string $d = '') => fE2($k, $old, $enseignant, $d);
$e    = fn(string $k) => errE2($k, $errors);
$hasE = fn(string $k) => hasE2($k, $errors);
?>

<div class="flex items-start gap-4 mb-6">
    <a href="<?= BASE_URL ?>/v2/rh/enseignants/<?= (int)$enseignant['id'] ?>"
       class="w-9 h-9 rounded-lg border border-slate-200 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-50 flex-shrink-0 transition-colors">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
    </a>
    <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="user-cog" class="w-5 h-5 text-violet-600"></i>
    </div>
    <div>
        <h2 class="text-xl font-bold text-slate-900">Modifier le profil enseignant</h2>
        <p class="text-sm text-slate-500 mt-0.5">
            <?= hE2(trim(($enseignant['prenom'] ?? '') . ' ' . ($enseignant['nom'] ?? ''))) ?>
            <?php if (!empty($enseignant['matricule'])): ?>
            &mdash; <span class="font-mono"><?= hE2($enseignant['matricule']) ?></span>
            <?php endif; ?>
        </p>
    </div>
</div>

<?php if ($flash = \Core\Session::getFlash('error')): ?>
<div class="flex items-center gap-2 p-3 mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm" role="alert">
    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
    <span><?= hE2($flash) ?></span>
    <button class="ml-auto" onclick="this.closest('[role=alert]').remove()"><i data-lucide="x" class="w-4 h-4"></i></button>
</div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/v2/rh/enseignants/<?= (int)$enseignant['id'] ?>" class="space-y-6">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">

    <!-- Employé associé (immuable) -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <i data-lucide="user" class="w-4 h-4 text-violet-600"></i>
            <h3 class="font-semibold text-slate-900 text-sm">Employé associé</h3>
        </div>
        <div class="p-5">
            <div class="bg-slate-50 border border-slate-100 rounded-lg p-3 text-sm text-slate-600 flex items-center gap-2">
                <i data-lucide="lock" class="w-3.5 h-3.5 shrink-0 text-slate-400"></i>
                <span>
                    <strong class="text-slate-800"><?= hE2(trim(($enseignant['prenom'] ?? '') . ' ' . ($enseignant['nom'] ?? ''))) ?></strong>
                    <?php if (!empty($enseignant['email_pro'])): ?> — <?= hE2($enseignant['email_pro']) ?><?php endif; ?>
                    <?php if (!empty($enseignant['poste_intitule'])): ?> — <?= hE2($enseignant['poste_intitule']) ?><?php endif; ?>
                    <br><span class="text-xs text-slate-400">L'employé associé à ce profil ne peut pas être modifié après création.</span>
                </span>
            </div>
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
                <label class="block text-xs font-medium text-slate-600 mb-1">Statut pédagogique <span class="form-required">*</span></label>
                <select name="statut_pedagogique"
                        class="form-select <?= $hasE('statut_pedagogique') ? 'is-invalid' : '' ?>">
                    <?php foreach ($statuts as $val => $lbl): ?>
                    <option value="<?= hE2($val) ?>" <?= $f('statut_pedagogique', 'titulaire') === $val ? 'selected' : '' ?>>
                        <?= hE2($lbl) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?= $e('statut_pedagogique') ?>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Charge horaire max (h/semaine)</label>
                <input type="number" name="charge_horaire_max" min="1" max="40"
                       value="<?= $f('charge_horaire_max', '18') ?>"
                       class="form-input <?= $hasE('charge_horaire_max') ? 'is-invalid' : '' ?>">
                <?= $e('charge_horaire_max') ?>
                <?php if (isset($enseignant['charge_horaire_actuelle'])): ?>
                <p class="mt-1 text-xs text-slate-400">Charge actuelle affectée : <?= hE2((string)$enseignant['charge_horaire_actuelle']) ?>h</p>
                <?php endif; ?>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Spécialité principale</label>
                <input type="text" name="specialite_principale" value="<?= $f('specialite_principale') ?>"
                       placeholder="ex : Mathématiques, Physique-Chimie…"
                       class="form-input">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Date de début d'enseignement</label>
                <input type="date" name="date_debut_enseignement" value="<?= $f('date_debut_enseignement') ?>"
                       class="form-input <?= $hasE('date_debut_enseignement') ? 'is-invalid' : '' ?>">
                <?= $e('date_debut_enseignement') ?>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Notes pédagogiques</label>
                <textarea name="notes_pedagogiques" rows="2"
                          class="form-textarea"><?= $f('notes_pedagogiques') ?></textarea>
            </div>
        </div>
    </div>

    <!-- Habilitations matières (lecture seule — gérées depuis la fiche détail) -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="book-open" class="w-4 h-4 text-violet-600"></i>
                <h3 class="font-semibold text-slate-900 text-sm">Habilitations matières (<?= count($assigned) ?>)</h3>
            </div>
            <a href="<?= BASE_URL ?>/v2/rh/enseignants/<?= (int)$enseignant['id'] ?>#matieres"
               class="text-xs text-violet-600 hover:text-violet-800 font-medium">Gérer les habilitations</a>
        </div>
        <div class="p-5">
            <?php if (empty($assigned)): ?>
            <p class="text-sm text-slate-400">Aucune matière habilitée pour le moment.</p>
            <?php else: ?>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($assigned as $m): ?>
                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium bg-violet-50 text-violet-700 border border-violet-100">
                    <?= hE2($m['matiere_nom'] ?? $m['nom'] ?? ('#' . (int)($m['matiere_id'] ?? 0))) ?>
                    <?php if (!empty($m['niveaux'])): ?><span class="text-violet-400">· <?= hE2($m['niveaux']) ?></span><?php endif; ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
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
            <i data-lucide="save" class="w-4 h-4"></i>Enregistrer les modifications
        </button>
    </div>
</form>
