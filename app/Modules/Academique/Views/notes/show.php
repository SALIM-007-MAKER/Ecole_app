<?php
$note       = $note       ?? null;
$historique = $historique ?? [];
$statuts    = $statuts    ?? [];
$colors     = $colors     ?? [];
$icons      = $icons      ?? [];
$policy     = $policy     ?? null;
$user       = $user       ?? [];

if (!$note) return;

$color       = $colors[$note->statut] ?? 'slate';
$icon        = $icons[$note->statut]  ?? 'circle';
$statutLabel = $statuts[$note->statut] ?? $note->statut;
$flash       = \Core\Session::getFlash();
?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> mb-4" role="alert">
    <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-triangle' ?>" class="w-4 h-4 shrink-0"></i>
    <span class="flex-1 text-sm"><?= htmlspecialchars($flash['message'], ENT_QUOTES) ?></span>
    <button onclick="this.closest('[role=alert]').remove()" class="ml-auto opacity-60 hover:opacity-100">
        <i data-lucide="x" class="w-4 h-4"></i>
    </button>
</div>
<?php endif; ?>

<!-- Fiche note -->
<div class="max-w-2xl mx-auto">

<div class="flex items-center gap-3 mb-6">
    <a href="<?= BASE_URL ?>/v2/academique/evaluations/<?= $note->evaluation_id ?>/notes"
       class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition">
        <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
        <h2 class="text-xl font-bold text-slate-900">
            <?= htmlspecialchars($note->eleve_prenom . ' ' . $note->eleve_nom, ENT_QUOTES) ?>
        </h2>
        <p class="text-sm text-slate-500">
            <?= htmlspecialchars($note->eval_libelle, ENT_QUOTES) ?>
            · <?= htmlspecialchars($note->classe_nom, ENT_QUOTES) ?>
            · <?= htmlspecialchars($note->matiere_nom, ENT_QUOTES) ?>
        </p>
    </div>
</div>

<!-- Carte principale -->
<div class="bg-white rounded-xl border border-slate-200 shadow-sm mb-4">
    <div class="p-6 border-b border-slate-100 flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-4">
            <?php if ((int)$note->est_absent): ?>
            <div class="w-16 h-16 rounded-2xl bg-amber-50 flex items-center justify-center">
                <i data-lucide="user-x" class="w-7 h-7 text-amber-500"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-amber-600">Absent</p>
                <p class="text-sm text-slate-500">Note non attribuée</p>
            </div>
            <?php elseif ($note->valeur !== null): ?>
            <div class="w-16 h-16 rounded-2xl bg-violet-50 flex items-center justify-center">
                <span class="text-2xl font-bold text-violet-700 font-mono">
                    <?= number_format((float)$note->valeur, 2) ?>
                </span>
            </div>
            <div>
                <p class="text-slate-500 text-sm">/<?= number_format((float)$note->note_max, 0) ?></p>
                <p class="text-slate-400 text-xs">coeff ×<?= number_format((float)$note->coefficient, 2) ?></p>
                <?php
                $pct = (float)$note->note_max > 0
                    ? round((float)$note->valeur / (float)$note->note_max * 100, 1) : 0;
                ?>
                <div class="mt-2 w-24 h-2 bg-slate-100 rounded-full">
                    <div class="h-2 rounded-full <?= $pct >= 50 ? 'bg-emerald-500' : 'bg-red-400' ?>"
                         style="width:<?= $pct ?>%"></div>
                </div>
            </div>
            <?php else: ?>
            <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center">
                <i data-lucide="minus-circle" class="w-7 h-7 text-slate-400"></i>
            </div>
            <div>
                <p class="text-slate-400">Note non saisie</p>
            </div>
            <?php endif; ?>
        </div>

        <span class="inline-flex items-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-full
              bg-<?= $color ?>-100 text-<?= $color ?>-700">
            <i data-lucide="<?= $icon ?>" class="w-4 h-4"></i>
            <?= $statutLabel ?>
        </span>
    </div>

    <div class="p-5">
        <dl class="space-y-3 text-sm">
            <?php if ($note->commentaire): ?>
            <div>
                <dt class="text-slate-500 mb-1">Commentaire enseignant</dt>
                <dd class="text-slate-800 bg-slate-50 rounded-lg px-3 py-2 text-sm">
                    <?= htmlspecialchars($note->commentaire, ENT_QUOTES) ?>
                </dd>
            </div>
            <?php endif; ?>
            <div class="flex justify-between">
                <dt class="text-slate-500">Type évaluation</dt>
                <dd class="text-slate-700"><?= htmlspecialchars($note->type_nom, ENT_QUOTES) ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">Matricule</dt>
                <dd class="text-slate-700 font-mono"><?= htmlspecialchars($note->matricule ?? '—', ENT_QUOTES) ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">Dernière modification</dt>
                <dd class="text-slate-600">
                    <?= $note->updated_at ? date('d/m/Y H:i', strtotime($note->updated_at)) : '—' ?>
                </dd>
            </div>
            <?php if ($note->statut === 'verrouillee' && $note->verrouille_le): ?>
            <div class="flex justify-between">
                <dt class="text-slate-500">Verrouillée le</dt>
                <dd class="font-medium text-red-700"><?= date('d/m/Y H:i', strtotime($note->verrouille_le)) ?></dd>
            </div>
            <?php endif; ?>
        </dl>

        <?php if ($policy && $policy->canModifier($user, $note)): ?>
        <div class="mt-5 pt-5 border-t border-slate-100">
            <form method="POST" action="<?= BASE_URL ?>/v2/academique/notes/<?= $note->id ?>">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <p class="text-sm font-medium text-slate-700 mb-3">Modifier la note</p>
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">
                            Note /<?= number_format((float)$note->note_max, 0) ?>
                        </label>
                        <input type="number"
                               name="valeur"
                               value="<?= $note->valeur !== null ? htmlspecialchars($note->valeur, ENT_QUOTES) : '' ?>"
                               min="0" max="<?= (float)$note->note_max ?>" step="0.25"
                               class="w-24 px-3 py-1.5 border border-slate-300 rounded-lg text-sm font-mono
                                      focus:outline-none focus:ring-2 focus:ring-violet-400">
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="est_absent_edit" name="est_absent" value="1"
                               <?= (int)$note->est_absent ? 'checked' : '' ?>
                               class="w-4 h-4 rounded accent-amber-500">
                        <label for="est_absent_edit" class="text-sm text-slate-600">Absent</label>
                    </div>
                    <div class="flex-1 min-w-48">
                        <label class="block text-xs text-slate-500 mb-1">Commentaire</label>
                        <input type="text"
                               name="commentaire"
                               value="<?= htmlspecialchars($note->commentaire ?? '', ENT_QUOTES) ?>"
                               maxlength="200"
                               placeholder="Commentaire optionnel…"
                               class="w-full px-3 py-1.5 border border-slate-300 rounded-lg text-sm
                                      focus:outline-none focus:ring-2 focus:ring-violet-400">
                    </div>
                    <button type="submit" class="btn btn-primary text-sm">
                        <i data-lucide="save" class="w-4 h-4"></i>Enregistrer
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Historique -->
<?php if (!empty($historique)): ?>
<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
    <h3 class="font-semibold text-slate-700 mb-4 flex items-center gap-2">
        <i data-lucide="history" class="w-4 h-4 text-violet-500"></i>
        Historique des modifications (<?= count($historique) ?>)
    </h3>
    <ol class="relative border-l border-slate-200 space-y-4 pl-5">
        <?php foreach ($historique as $h): ?>
        <li class="relative">
            <div class="absolute -left-[1.375rem] w-3 h-3 bg-white border-2 border-violet-400 rounded-full"></div>
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p class="text-sm text-slate-800">
                        <?php if ((int)$h->absent_avant !== (int)$h->absent_apres): ?>
                            Passage
                            <?= (int)$h->absent_apres ? '<strong>absent</strong>' : '<strong>présent</strong>' ?>
                        <?php else: ?>
                            Note modifiée :
                            <span class="font-mono text-red-600 line-through">
                                <?= $h->valeur_avant !== null ? htmlspecialchars($h->valeur_avant, ENT_QUOTES) : 'ABS' ?>
                            </span>
                            → <span class="font-mono text-emerald-700 font-semibold">
                                <?= $h->valeur_apres !== null ? htmlspecialchars($h->valeur_apres, ENT_QUOTES) : 'ABS' ?>
                            </span>
                        <?php endif; ?>
                    </p>
                    <?php if ($h->commentaire): ?>
                    <p class="text-xs text-slate-400 mt-0.5">
                        « <?= htmlspecialchars($h->commentaire, ENT_QUOTES) ?> »
                    </p>
                    <?php endif; ?>
                </div>
                <div class="text-right text-xs text-slate-400">
                    <p><?= date('d/m/Y H:i', strtotime($h->modifie_le)) ?></p>
                    <?php if ($h->modifie_par_nom): ?>
                    <p class="text-slate-500"><?= htmlspecialchars($h->modifie_par_nom, ENT_QUOTES) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </li>
        <?php endforeach; ?>
    </ol>
</div>
<?php endif; ?>

</div>
