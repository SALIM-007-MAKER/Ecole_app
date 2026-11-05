<?php
/**
 * Onglet « Programme » de la fiche de classe : matières, coefficient et volume horaire PROPRES à la classe.
 * @var object $classe
 * @var array  $programme  ['dispo','lignes','ajoutables','verrou','paralleles','notes']
 * @var array  $perms
 */
$prog      = $programme ?? ['dispo' => false];
$lignes    = $prog['lignes'] ?? [];
$verrou    = $prog['verrou'] ?? null;
$canEditP  = in_array('classes.edit', $perms ?? [], true) && empty($verrou);
$base      = BASE_URL . '/classes/' . (int)$classe->id . '/programme';
$csrf      = \Core\Session::getCsrfToken();
$e         = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
?>
<div class="space-y-5">

<?php if (empty($prog['dispo'])): ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5 text-sm text-slate-500">
        Le programme par classe n'est pas encore activé sur cette installation.
    </div>
<?php else: ?>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5 text-sm text-slate-600">
        Le programme fixe, <strong>pour cette classe</strong>, les matières avec leur coefficient et leur volume horaire hebdomadaire. Il est
        indépendant des enseignants affectés. Tant qu'une matière n'y figure pas, ses valeurs par défaut (fiche de la matière) s'appliquent :
        rien ne change pour les classes sans programme. Modifier un programme ne régénère aucun bulletin.
    </div>

    <?php if ($verrou): ?>
    <div role="alert" style="display:flex;gap:.75rem;padding:.875rem 1rem;border-radius:.75rem;border:1px solid #fde68a;background:#fffbeb;color:#92400e;font-size:.875rem">
        <i data-lucide="lock" style="width:1rem;height:1rem;margin-top:.125rem;flex-shrink:0"></i>
        <span><?= $e($verrou) ?></span>
    </div>
    <?php endif; ?>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="list-checks" class="w-4 h-4 text-violet-600"></i>
            <span>Matières au programme</span>
            <span class="text-xs font-normal text-slate-400" style="margin-left:auto"><?= count($lignes) ?> matière(s)</span>
        </div>

        <?php if (empty($lignes)): ?>
        <div class="p-6 text-center text-sm text-slate-500">
            <p style="margin-bottom:.75rem">Aucun programme défini pour cette classe : les valeurs par défaut des matières s'appliquent.</p>
            <?php if ($canEditP): ?>
            <form method="POST" action="<?= $base ?>/initialiser" style="display:inline">
                <input type="hidden" name="_csrf_token" value="<?= $e($csrf) ?>">
                <button type="submit" class="btn btn-secondary"
                        onclick="return confirm('Créer le programme à partir des matières actuellement enseignées dans cette classe ? Les valeurs par défaut des matières seront reprises.')">
                    <i data-lucide="wand-2" class="w-4 h-4"></i> Initialiser depuis les enseignements
                </button>
            </form>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <form method="POST" action="<?= $base ?>/enregistrer">
            <input type="hidden" name="_csrf_token" value="<?= $e($csrf) ?>">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr style="background:#f8fafc;text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b">
                            <th style="padding:.6rem 1rem">Matière</th>
                            <th style="padding:.6rem 1rem;text-align:center">Coefficient</th>
                            <th style="padding:.6rem 1rem;text-align:center">H / semaine</th>
                            <th style="padding:.6rem 1rem;text-align:center" title="Une matière facultative est suivie et notée mais ne compte ni dans la moyenne générale ni dans le classement">Facultative</th>
                            <th style="padding:.6rem 1rem;text-align:center">Ordre</th>
                            <th style="padding:.6rem 1rem;text-align:center">Par défaut</th>
                            <th style="padding:.6rem 1rem;text-align:center">Enseignants</th>
                            <?php if ($canEditP): ?><th style="padding:.6rem 1rem;text-align:center">Retirer</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lignes as $l): $mid = (int)$l['matiere_id']; ?>
                        <tr>
                            <td style="padding:.6rem 1rem;font-weight:600;color:#1e293b">
                                <?= $e($l['nom_affiche'] ?? $l['nom']) ?>
                                <?php if (!empty($l['nom_affiche']) && $l['nom_affiche'] !== $l['nom']): ?><span style="font-weight:400;font-size:.75rem;color:#94a3b8"> (réf. : <?= $e($l['nom']) ?>)</span><?php endif; ?>
                                <?php if ((int)($l['matiere_active'] ?? 1) !== 1): ?><span style="margin-left:.4rem;font-size:.7rem;font-weight:600;color:#b45309;background:#fef3c7;border-radius:.4rem;padding:.1rem .4rem">désactivée</span><?php endif; ?>
                            </td>
                            <td style="padding:.4rem 1rem;text-align:center">
                                <?php if ($canEditP): ?>
                                <input type="text" inputmode="decimal" class="form-input" style="width:5rem;text-align:center;margin:auto"
                                       name="programme[<?= $mid ?>][coefficient]" value="<?= $e(rtrim(rtrim(number_format((float)$l['coefficient'], 1, '.', ''), '0'), '.')) ?>" aria-label="Coefficient <?= $e($l['nom']) ?>">
                                <?php else: ?><?= $e(rtrim(rtrim(number_format((float)$l['coefficient'], 1, '.', ''), '0'), '.')) ?><?php endif; ?>
                            </td>
                            <td style="padding:.4rem 1rem;text-align:center">
                                <?php if ($canEditP): ?>
                                <input type="number" min="0" max="40" step="1" class="form-input" style="width:5rem;text-align:center;margin:auto"
                                       name="programme[<?= $mid ?>][volume_horaire]" value="<?= (int)$l['volume_horaire'] ?>" aria-label="Heures <?= $e($l['nom']) ?>">
                                <?php else: ?><?= (int)$l['volume_horaire'] ?> h<?php endif; ?>
                            </td>
                            <td style="padding:.4rem 1rem;text-align:center">
                                <?php if ($canEditP): ?>
                                <input type="hidden" name="programme[<?= $mid ?>][obligatoire]" value="1">
                                <input type="checkbox" class="accent-violet-600" name="programme[<?= $mid ?>][obligatoire]" value="0" aria-label="Facultative <?= $e($l['nom']) ?>" <?= (int)$l['obligatoire'] === 0 ? 'checked' : '' ?>>
                                <?php else: ?><?= (int)$l['obligatoire'] === 0 ? 'Oui' : '—' ?><?php endif; ?>
                            </td>
                            <td style="padding:.4rem 1rem;text-align:center">
                                <?php if ($canEditP): ?>
                                <input type="number" min="0" max="65535" step="1" class="form-input" style="width:4.5rem;text-align:center;margin:auto"
                                       name="programme[<?= $mid ?>][ordre]" value="<?= (int)$l['ordre'] ?>" aria-label="Ordre <?= $e($l['nom']) ?>">
                                <?php else: ?><?= (int)$l['ordre'] ?><?php endif; ?>
                            </td>
                            <td style="padding:.6rem 1rem;text-align:center;color:#94a3b8;font-size:.8rem">
                                coef. <?= $e(rtrim(rtrim(number_format((float)$l['coefficient_defaut'], 1, '.', ''), '0'), '.')) ?> · <?= (int)$l['volume_defaut'] ?> h
                            </td>
                            <td style="padding:.6rem 1rem;text-align:center;color:#64748b"><?= (int)$l['nb_enseignements'] ?: '—' ?></td>
                            <?php if ($canEditP): ?>
                            <td style="padding:.4rem 1rem;text-align:center">
                                <button type="submit" formaction="<?= $base ?>/retirer" name="matiere_id" value="<?= $mid ?>" formnovalidate
                                        class="btn btn-ghost btn-icon text-red-400 hover:text-red-600" title="Retirer du programme"
                                        onclick="return confirm('Retirer <?= $e(addslashes($l['nom'])) ?> du programme de cette classe ?')">
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                </button>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($canEditP): ?>
            <div style="padding:1rem 1.25rem;border-top:1px solid #e2e8f0;display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between">
                <?php if (!empty($prog['notes'])): ?>
                <label style="display:flex;gap:.5rem;align-items:flex-start;font-size:.85rem;color:#92400e;max-width:34rem">
                    <input type="checkbox" name="confirmer_notes" value="1" class="accent-violet-600" style="margin-top:.2rem">
                    <span>Des notes existent déjà dans cette classe : je confirme que modifier un coefficient change les moyennes et classements calculés.</span>
                </label>
                <?php else: ?><span></span><?php endif; ?>
                <button type="submit" class="btn btn-primary"><i data-lucide="save" class="w-4 h-4"></i> Enregistrer le programme</button>
            </div>
            <?php endif; ?>
        </form>
        <?php endif; ?>
    </div>

    <?php if ($canEditP && !empty($prog['ajoutables'])): ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="plus-circle" class="w-4 h-4 text-violet-600"></i><span>Ajouter une matière au programme</span>
        </div>
        <form method="POST" action="<?= $base ?>/ajouter" style="padding:1.25rem;display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end">
            <input type="hidden" name="_csrf_token" value="<?= $e($csrf) ?>">
            <div style="flex:1;min-width:14rem">
                <label class="form-label" for="prog-matiere">Matière</label>
                <select id="prog-matiere" name="matiere_id" class="form-input" required>
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($prog['ajoutables'] as $m): ?>
                    <option value="<?= (int)$m['id'] ?>"><?= $e($m['nom_affiche'] ?? $m['nom']) ?> (défaut : coef. <?= $e(rtrim(rtrim(number_format((float)$m['coefficient'], 1, '.', ''), '0'), '.')) ?>, <?= (int)$m['volume_horaire'] ?> h)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="prog-coef">Coefficient</label>
                <input id="prog-coef" type="text" inputmode="decimal" name="coefficient" class="form-input" style="width:6rem" placeholder="défaut">
            </div>
            <div>
                <label class="form-label" for="prog-vol">H / semaine</label>
                <input id="prog-vol" type="number" min="0" max="40" step="1" name="volume_horaire" class="form-input" style="width:6rem" placeholder="défaut">
            </div>
            <label style="display:inline-flex;gap:.4rem;align-items:center;font-size:.85rem;color:#334155;padding-bottom:.6rem">
                <input type="checkbox" name="facultative" value="1" class="accent-violet-600"> Facultative
            </label>
            <button type="submit" class="btn btn-primary"><i data-lucide="plus" class="w-4 h-4"></i> Ajouter</button>
        </form>
        <p style="padding:0 1.25rem 1rem;font-size:.75rem;color:#94a3b8">Champs vides : les valeurs par défaut de la matière sont reprises. Une matière facultative est notée et affichée mais ne compte ni dans la moyenne générale ni dans le classement. Les matières désactivées ne sont pas proposées. La Conduite n'entre pas dans un programme.</p>
    </div>
    <?php endif; ?>

    <?php if ($canEditP && !empty($lignes) && !empty($prog['paralleles'])): ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="copy" class="w-4 h-4 text-violet-600"></i><span>Reproduire dans les classes parallèles</span>
        </div>
        <form method="POST" action="<?= $base ?>/reproduire" style="padding:1.25rem;display:grid;gap:.9rem">
            <input type="hidden" name="_csrf_token" value="<?= $e($csrf) ?>">
            <p style="font-size:.85rem;color:#64748b;margin:0">
                Classes du même niveau<?= !empty($classe->serie) ? ', de la série ' . $e($classe->serie) : '' ?> et de la même année (<?= $e($classe->annee_scolaire) ?>).
            </p>
            <div style="display:flex;flex-wrap:wrap;gap:.5rem 1.5rem">
                <?php foreach ($prog['paralleles'] as $p): $fige = (int)$p['nb_bulletins_figes'] > 0; ?>
                <label style="display:inline-flex;gap:.5rem;align-items:center;font-size:.875rem;<?= $fige ? 'color:#94a3b8' : 'color:#334155' ?>">
                    <input type="checkbox" name="cibles[]" value="<?= (int)$p['id'] ?>" class="accent-violet-600" <?= $fige ? 'disabled' : '' ?>>
                    <?= $e($classe->niveau . ' ' . $p['nom']) ?>
                    <span style="font-size:.75rem;color:#94a3b8">(<?= $fige ? 'verrouillée : bulletins publiés' : ((int)$p['nb_lignes'] . ' matière(s) au programme') ?>)</span>
                </label>
                <?php endforeach; ?>
            </div>
            <div style="display:grid;gap:.4rem;font-size:.875rem;color:#334155">
                <label style="display:flex;gap:.5rem;align-items:flex-start">
                    <input type="radio" name="mode" value="completer" checked class="accent-violet-600" style="margin-top:.2rem">
                    <span><strong>Compléter</strong> : ajoute les matières manquantes, ne modifie aucune ligne déjà présente (recommandé).</span>
                </label>
                <label style="display:flex;gap:.5rem;align-items:flex-start">
                    <input type="radio" name="mode" value="ecraser" class="accent-violet-600" style="margin-top:.2rem">
                    <span><strong>Aligner</strong> : en plus, remplace les coefficients et heures déjà saisis par ceux de cette classe.</span>
                </label>
                <label style="display:flex;gap:.5rem;align-items:flex-start;margin-left:1.5rem;color:#92400e">
                    <input type="checkbox" name="confirmer_ecrasement" value="1" class="accent-violet-600" style="margin-top:.2rem">
                    <span>Je confirme l'alignement des lignes déjà personnalisées.</span>
                </label>
                <label style="display:flex;gap:.5rem;align-items:flex-start;margin-left:1.5rem;color:#92400e">
                    <input type="checkbox" name="confirmer_notes" value="1" class="accent-violet-600" style="margin-top:.2rem">
                    <span>Je confirme les modifications même si des notes existent dans les classes ciblées.</span>
                </label>
            </div>
            <div><button type="submit" class="btn btn-secondary"><i data-lucide="copy" class="w-4 h-4"></i> Reproduire</button></div>
        </form>
    </div>
    <?php endif; ?>

    <?php if (!empty($prog['historique'])): ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="history" class="w-4 h-4 text-violet-600"></i><span>Historique du programme</span>
            <span class="text-xs font-normal text-slate-400" style="margin-left:auto">tiré du journal d'audit</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr style="background:#f8fafc;text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b">
                        <th style="padding:.6rem 1rem">Date</th><th style="padding:.6rem 1rem">Utilisateur</th>
                        <th style="padding:.6rem 1rem">Action</th><th style="padding:.6rem 1rem">Matière</th>
                        <th style="padding:.6rem 1rem">Coefficient</th><th style="padding:.6rem 1rem">Heures</th><th style="padding:.6rem 1rem">Facultative</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $lib = ['create' => 'Ajout', 'update' => 'Modification', 'delete' => 'Retrait'];
                $fmt = fn($v) => $v === null ? '—' : rtrim(rtrim(number_format((float)$v, 1, '.', ''), '0'), '.');
                $fac = fn($v) => $v === null ? '—' : ((int)$v === 0 ? 'oui' : 'non');
                foreach ($prog['historique'] as $h):
                    $a = $h['avant']; $n = $h['apres'];
                    $chg = fn($k, $f) => (isset($a[$k]) && isset($n[$k]) && $a[$k] != $n[$k]) ? $f($a[$k]) . ' → ' . $f($n[$k]) : $f($n[$k] ?? $a[$k] ?? null);
                ?>
                    <tr>
                        <td style="padding:.5rem 1rem;white-space:nowrap;color:#64748b"><?= $e($h['date']) ?></td>
                        <td style="padding:.5rem 1rem"><?= $e($h['acteur']) ?></td>
                        <td style="padding:.5rem 1rem"><?= $e($lib[$h['action']] ?? $h['action']) ?><?= isset($n['reproduction_depuis_classe']) ? ' (reproduction)' : (isset($n['repris_depuis_classe']) ? ' (reprise)' : '') ?></td>
                        <td style="padding:.5rem 1rem;font-weight:600"><?= $e($h['matiere'] !== '' ? $h['matiere'] : '—') ?></td>
                        <td style="padding:.5rem 1rem"><?= $e($chg('coefficient', $fmt)) ?></td>
                        <td style="padding:.5rem 1rem"><?= $e($chg('volume_horaire', fn($v) => $v === null ? '—' : (int)$v)) ?></td>
                        <td style="padding:.5rem 1rem"><?= $e($chg('obligatoire', $fac)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

<?php endif; ?>
</div>
