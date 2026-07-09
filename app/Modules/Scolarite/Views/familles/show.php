<?php
/** @var object $famille @var array $elevesDisponibles @var array $liens @var bool $canManage @var bool $canLinkEleve */
$lienLabels = $liens;
function fpLienBadge(string $lien, array $labels): string {
    $colors = ['pere'=>'blue','mere'=>'pink','tuteur'=>'violet','autre'=>'slate'];
    $c = $colors[$lien] ?? 'slate';
    $label = $labels[$lien] ?? ucfirst($lien);
    return "<span class=\"inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
        bg-{$c}-100 text-{$c}-700\">{$label}</span>";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <?php include BASE_PATH . '/app/Views/layouts/head_assets.php'; ?>
</head>
<body class="bg-slate-50 text-slate-800">
<?php include BASE_PATH . '/app/Views/layouts/sidebar.php'; ?>

<main class="ml-64 p-6 min-h-screen">
    <div class="max-w-5xl mx-auto">

        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-sm text-slate-500 mb-4">
            <a href="<?= BASE_URL ?>/v2/scolarite/familles" class="hover:text-violet-600">Familles</a>
            <i data-lucide="chevron-right" class="w-4 h-4"></i>
            <span class="text-slate-800 font-medium"><?= htmlspecialchars($famille->nom) ?></span>
        </nav>

        <!-- Flash -->
        <?php foreach (['success','error','warning'] as $t): $msg = \Core\Session::getFlash($t); ?>
        <?php if ($msg): ?>
        <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium
            <?= $t==='success'?'bg-green-50 text-green-800 border border-green-200':
               ($t==='error'  ?'bg-red-50 text-red-800 border border-red-200':
                               'bg-amber-50 text-amber-800 border border-amber-200') ?>">
            <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; endforeach; ?>

        <!-- Carte identité famille -->
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden mb-6">
            <!-- Header gradient -->
            <div class="bg-gradient-to-r from-violet-600 to-purple-700 px-6 py-5 flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-3 mb-1">
                        <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center">
                            <i data-lucide="home" class="w-6 h-6 text-white"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-white"><?= htmlspecialchars($famille->nom) ?></h1>
                            <p class="text-violet-200 text-sm">
                                <?= (int)count($famille->eleves) ?> élève<?= count($famille->eleves)>1?'s':'' ?> rattaché<?= count($famille->eleves)>1?'s':'' ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex gap-2">
                    <?php if (!$famille->actif): ?>
                    <span class="inline-flex items-center gap-1 bg-amber-100 text-amber-800 text-xs font-medium px-3 py-1 rounded-full">
                        <i data-lucide="archive" class="w-3 h-3"></i> Archivée
                    </span>
                    <?php endif; ?>
                    <?php if ($canManage): ?>
                    <a href="<?= BASE_URL ?>/v2/scolarite/familles/<?= $famille->id ?>/edit"
                       class="inline-flex items-center gap-1 bg-white/20 hover:bg-white/30 text-white text-sm px-3 py-1.5 rounded-lg transition">
                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Modifier
                    </a>
                    <?php if ($famille->actif): ?>
                    <button type="button" onclick="document.getElementById('archiveModal').classList.remove('hidden')"
                            class="inline-flex items-center gap-1 bg-amber-400/20 hover:bg-amber-400/30 text-white text-sm px-3 py-1.5 rounded-lg transition">
                        <i data-lucide="archive" class="w-3.5 h-3.5"></i> Archiver
                    </button>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Corps -->
            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Coordonnées -->
                <div class="md:col-span-2 space-y-4">
                    <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide">Coordonnées</h2>
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-slate-400">Téléphone</dt>
                            <dd class="font-medium"><?= htmlspecialchars($famille->telephone ?? '—') ?></dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Email</dt>
                            <dd class="font-medium"><?= htmlspecialchars($famille->email ?? '—') ?></dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-slate-400">Adresse</dt>
                            <dd class="font-medium">
                                <?= $famille->adresse ? htmlspecialchars(trim($famille->adresse . ', ' . $famille->code_postal . ' ' . $famille->ville)) : '—' ?>
                            </dd>
                        </div>
                        <?php if ($famille->notes): ?>
                        <div class="col-span-2">
                            <dt class="text-slate-400">Notes</dt>
                            <dd class="text-slate-600 italic"><?= nl2br(htmlspecialchars($famille->notes)) ?></dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                </div>

                <!-- Contact d'urgence -->
                <div class="bg-red-50 border border-red-100 rounded-xl p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <i data-lucide="alert-triangle" class="w-4 h-4 text-red-500"></i>
                        <h2 class="text-sm font-semibold text-red-700">Contact d'urgence</h2>
                    </div>
                    <?php if ($famille->contact_urgence_nom): ?>
                    <dl class="text-sm space-y-2">
                        <div>
                            <dt class="text-red-400 text-xs">Nom</dt>
                            <dd class="font-semibold text-slate-800"><?= htmlspecialchars($famille->contact_urgence_nom) ?></dd>
                        </div>
                        <?php if ($famille->contact_urgence_lien): ?>
                        <div>
                            <dt class="text-red-400 text-xs">Lien</dt>
                            <dd><?= htmlspecialchars($famille->contact_urgence_lien) ?></dd>
                        </div>
                        <?php endif; ?>
                        <?php if ($famille->contact_urgence_telephone): ?>
                        <div>
                            <dt class="text-red-400 text-xs">Téléphone</dt>
                            <dd class="font-semibold text-red-700"><?= htmlspecialchars($famille->contact_urgence_telephone) ?></dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                    <?php else: ?>
                    <p class="text-sm text-red-400 italic">Aucun contact d'urgence renseigné.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Élèves rattachés -->
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="font-semibold text-slate-900 flex items-center gap-2">
                    <i data-lucide="users" class="w-4 h-4 text-violet-500"></i>
                    Élèves rattachés
                    <span class="text-sm font-normal text-slate-400">(<?= count($famille->eleves) ?>)</span>
                </h2>
            </div>

            <?php if (empty($famille->eleves)): ?>
            <div class="py-10 text-center text-slate-400">
                <i data-lucide="user-x" class="w-8 h-8 mx-auto mb-2 opacity-30"></i>
                <p class="text-sm">Aucun élève rattaché à cette famille.</p>
            </div>
            <?php else: ?>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600">Élève</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600">Classe</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600">Lien</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-600">Responsable</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-600">Contact princ.</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-600">Urgence</th>
                        <?php if ($canLinkEleve): ?>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($famille->eleves as $e): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-4 py-3">
                            <a href="<?= BASE_URL ?>/v2/scolarite/eleves/<?= $e->id ?>"
                               class="font-medium text-violet-700 hover:text-violet-900">
                                <?= htmlspecialchars($e->prenom . ' ' . $e->nom) ?>
                            </a>
                            <?php if ($e->matricule): ?>
                            <span class="text-xs text-slate-400 ml-1"><?= htmlspecialchars($e->matricule) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($e->classe_nom ?? '—') ?></td>
                        <td class="px-4 py-3">
                            <?= fpLienBadge($e->lien_parente, $lienLabels) ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <?= $e->est_responsable_legal
                                ? '<i data-lucide="check-circle" class="w-4 h-4 text-green-500 mx-auto"></i>'
                                : '<i data-lucide="minus" class="w-4 h-4 text-slate-300 mx-auto"></i>' ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <?= $e->est_contact_principal
                                ? '<i data-lucide="check-circle" class="w-4 h-4 text-green-500 mx-auto"></i>'
                                : '<i data-lucide="minus" class="w-4 h-4 text-slate-300 mx-auto"></i>' ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <?= $e->est_contact_urgence
                                ? '<i data-lucide="alert-circle" class="w-4 h-4 text-red-500 mx-auto"></i>'
                                : '<i data-lucide="minus" class="w-4 h-4 text-slate-300 mx-auto"></i>' ?>
                        </td>
                        <?php if ($canLinkEleve): ?>
                        <td class="px-4 py-3 text-right">
                            <button type="button"
                                    onclick="confirmDetach(<?= $e->id ?>, '<?= htmlspecialchars(addslashes($e->prenom . ' ' . $e->nom)) ?>')"
                                    class="text-slate-400 hover:text-red-600 transition" title="Retirer">
                                <i data-lucide="unlink" class="w-4 h-4"></i>
                            </button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Rattacher un élève -->
        <?php if ($canLinkEleve && !empty($elevesDisponibles)): ?>
        <div class="bg-white border border-slate-200 rounded-xl p-6">
            <h2 class="font-semibold text-slate-900 mb-4 flex items-center gap-2">
                <i data-lucide="link" class="w-4 h-4 text-violet-500"></i>
                Rattacher un élève
            </h2>
            <form method="POST" action="<?= BASE_URL ?>/v2/scolarite/familles/<?= $famille->id ?>/rattacher-eleve"
                  class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <input type="hidden" name="csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Élève</label>
                    <select name="eleve_id" required
                            class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500 outline-none">
                        <option value="">Sélectionner…</option>
                        <?php foreach ($elevesDisponibles as $el): ?>
                        <option value="<?= $el->id ?>">
                            <?= htmlspecialchars($el->prenom . ' ' . $el->nom) ?>
                            <?= $el->classe_nom ? '(' . htmlspecialchars($el->classe_nom) . ')' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Lien de parenté</label>
                    <select name="lien_parente"
                            class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500 outline-none">
                        <?php foreach ($lienLabels as $k => $v): ?>
                        <option value="<?= $k ?>"><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex flex-col gap-2 justify-center">
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="est_contact_principal" value="1"
                               class="rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                        Contact principal
                    </label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="est_contact_urgence" value="1"
                               class="rounded border-slate-300 text-red-500 focus:ring-red-400">
                        Contact d'urgence
                    </label>
                </div>

                <div class="flex items-end">
                    <button type="submit"
                            class="w-full bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition flex items-center justify-center gap-2">
                        <i data-lucide="link" class="w-4 h-4"></i> Rattacher
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

    </div>
</main>

<!-- Modal détachement -->
<div id="detachModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-xl shadow-xl p-6 max-w-sm w-full mx-4">
        <h3 class="font-bold text-lg text-slate-900 mb-2">Retirer l'élève</h3>
        <p class="text-sm text-slate-600 mb-4">
            Voulez-vous retirer <strong id="detachNom"></strong> de cette famille ?
            L'élève conserve son inscription et ses données.
        </p>
        <div class="flex gap-3 justify-end">
            <button onclick="document.getElementById('detachModal').classList.add('hidden')"
                    class="px-4 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50">Annuler</button>
            <form method="POST" action="<?= BASE_URL ?>/v2/scolarite/familles/<?= $famille->id ?>/detacher-eleve">
                <input type="hidden" name="csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <input type="hidden" name="eleve_id" id="detachEleveId">
                <button type="submit"
                        class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg">Retirer</button>
            </form>
        </div>
    </div>
</div>

<!-- Modal archivage -->
<div id="archiveModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-xl shadow-xl p-6 max-w-sm w-full mx-4">
        <h3 class="font-bold text-lg text-slate-900 mb-2">Archiver la famille</h3>
        <p class="text-sm text-slate-600 mb-4">
            Cette famille sera marquée comme archivée et n'apparaîtra plus dans les listes actives.
            Les données sont conservées.
        </p>
        <div class="flex gap-3 justify-end">
            <button onclick="document.getElementById('archiveModal').classList.add('hidden')"
                    class="px-4 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50">Annuler</button>
            <form method="POST" action="<?= BASE_URL ?>/v2/scolarite/familles/<?= $famille->id ?>/archiver">
                <input type="hidden" name="csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit"
                        class="px-4 py-2 text-sm bg-amber-600 hover:bg-amber-700 text-white rounded-lg">Archiver</button>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDetach(eleveId, nom) {
    document.getElementById('detachNom').textContent = nom;
    document.getElementById('detachEleveId').value = eleveId;
    document.getElementById('detachModal').classList.remove('hidden');
}
</script>

<?php include BASE_PATH . '/app/Views/layouts/footer_assets.php'; ?>
</body>
</html>
