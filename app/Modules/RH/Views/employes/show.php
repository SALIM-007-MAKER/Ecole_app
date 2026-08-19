<?php
/** @var array $employe */
/** @var array $contacts */
/** @var bool $canUpdate */
/** @var bool $canArchive */
/** @var bool $canRestore */

function hShow(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$statutColors = [
    'actif'          => 'emerald',
    'inactif'        => 'slate',
    'suspendu'       => 'orange',
    'conge'          => 'blue',
    'retraite'       => 'purple',
    'demissionnaire' => 'red',
];
$statutLabels = [
    'actif'          => 'Actif',
    'inactif'        => 'Inactif',
    'suspendu'       => 'Suspendu',
    'conge'          => 'En congé',
    'retraite'       => 'Retraité',
    'demissionnaire' => 'Démissionnaire',
];
$typeLabels = [
    'enseignant'    => 'Enseignant',
    'administratif' => 'Administratif',
    'support'       => 'Support',
    'direction'     => 'Direction',
    'technique'     => 'Technique',
];
$color = $statutColors[$employe['statut']] ?? 'slate';
$label = $statutLabels[$employe['statut']] ?? $employe['statut'];
$type  = $typeLabels[$employe['type_personnel']] ?? $employe['type_personnel'];
$isArchived = !empty($employe['deleted_at']);
?>

<!-- En-tête -->
<div class="flex flex-wrap items-start justify-between gap-4 mb-6">
    <div class="flex items-start gap-4">
        <a href="<?= BASE_URL ?>/v2/rh/employes"
           class="w-9 h-9 rounded-lg border border-slate-200 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-50 flex-shrink-0 transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
        </a>
        <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
            <i data-lucide="user" class="w-5 h-5 text-violet-600"></i>
        </div>
        <div>
            <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                <?= hShow($employe['prenom'] . ' ' . $employe['nom']) ?>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $color ?>-100 text-<?= $color ?>-700">
                    <?= hShow($label) ?>
                </span>
            </h2>
            <p class="text-sm text-slate-500 font-mono mt-0.5"><?= hShow($employe['matricule']) ?></p>
        </div>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <?php if (!$isArchived && $canUpdate): ?>
        <a href="<?= BASE_URL ?>/v2/rh/employes/<?= (int)$employe['id'] ?>/edit"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
            <i data-lucide="pencil" class="w-4 h-4"></i>Modifier
        </a>
        <?php endif; ?>
        <?php if (!$isArchived && $canArchive): ?>
        <form method="POST" action="<?= BASE_URL ?>/v2/rh/employes/<?= (int)$employe['id'] ?>/archive"
              onsubmit="return confirm('Archiver cet employé ?')">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
            <button type="submit"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-red-200 bg-white text-sm font-medium text-red-600 hover:bg-red-50 transition-colors">
                <i data-lucide="archive" class="w-4 h-4"></i>Archiver
            </button>
        </form>
        <?php endif; ?>
        <?php if ($isArchived && $canRestore): ?>
        <form method="POST" action="<?= BASE_URL ?>/v2/rh/employes/<?= (int)$employe['id'] ?>/restore">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
            <button type="submit"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 transition-colors">
                <i data-lucide="rotate-ccw" class="w-4 h-4"></i>Restaurer
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($flash = \Core\Session::getFlash('success')): ?>
<div class="flex items-center gap-2 p-3 mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm" role="alert">
    <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
    <span><?= hShow($flash) ?></span>
    <button class="ml-auto" onclick="this.closest('[role=alert]').remove()"><i data-lucide="x" class="w-4 h-4"></i></button>
</div>
<?php endif; ?>
<?php if ($flash = \Core\Session::getFlash('error')): ?>
<div class="flex items-center gap-2 p-3 mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm" role="alert">
    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
    <span><?= hShow($flash) ?></span>
    <button class="ml-auto" onclick="this.closest('[role=alert]').remove()"><i data-lucide="x" class="w-4 h-4"></i></button>
</div>
<?php endif; ?>

<?php if ($isArchived): ?>
<div class="flex items-center gap-2 p-3 mb-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
    <i data-lucide="archive" class="w-4 h-4 shrink-0"></i>
    <span>Cet employé est archivé depuis le <?= date('d/m/Y', strtotime($employe['deleted_at'])) ?>.</span>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Colonne principale -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Informations personnelles -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <i data-lucide="user" class="w-4 h-4 text-violet-600"></i>
                <h3 class="font-semibold text-slate-900 text-sm">Informations personnelles</h3>
            </div>
            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Nom complet</span>
                    <p class="mt-0.5 text-slate-900 font-medium"><?= hShow($employe['prenom'] . ' ' . $employe['nom']) ?></p>
                </div>
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Genre</span>
                    <p class="mt-0.5 text-slate-700">
                        <?= $employe['genre'] === 'M' ? 'Masculin' : ($employe['genre'] === 'F' ? 'Féminin' : 'Autre') ?>
                    </p>
                </div>
                <?php if ($employe['date_naissance']): ?>
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Date de naissance</span>
                    <p class="mt-0.5 text-slate-700"><?= date('d/m/Y', strtotime($employe['date_naissance'])) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($employe['lieu_naissance']): ?>
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Lieu de naissance</span>
                    <p class="mt-0.5 text-slate-700"><?= hShow($employe['lieu_naissance']) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($employe['nationalite']): ?>
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Nationalité</span>
                    <p class="mt-0.5 text-slate-700"><?= hShow($employe['nationalite']) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($employe['cni_numero']): ?>
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">N° CNI</span>
                    <p class="mt-0.5 text-slate-700"><?= hShow($employe['cni_numero']) ?>
                        <?php if ($employe['cni_expiration']): ?>
                        <span class="text-xs text-slate-400">(exp. <?= date('d/m/Y', strtotime($employe['cni_expiration'])) ?>)</span>
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
                <?php if ($employe['adresse']): ?>
                <div class="sm:col-span-2">
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Adresse</span>
                    <p class="mt-0.5 text-slate-700"><?= hShow($employe['adresse']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Informations professionnelles -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <i data-lucide="briefcase" class="w-4 h-4 text-violet-600"></i>
                <h3 class="font-semibold text-slate-900 text-sm">Informations professionnelles</h3>
            </div>
            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Type de personnel</span>
                    <p class="mt-0.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-slate-100 text-slate-700">
                            <?= hShow($type) ?>
                        </span>
                    </p>
                </div>
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Statut</span>
                    <p class="mt-0.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $color ?>-100 text-<?= $color ?>-700">
                            <?= hShow($label) ?>
                        </span>
                    </p>
                </div>
                <?php if ($employe['departement_nom']): ?>
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Département</span>
                    <p class="mt-0.5 text-slate-700"><?= hShow($employe['departement_nom']) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($employe['poste_intitule']): ?>
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Poste</span>
                    <p class="mt-0.5 text-slate-700"><?= hShow($employe['poste_intitule']) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($employe['date_entree']): ?>
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Date d'entrée</span>
                    <p class="mt-0.5 text-slate-700"><?= date('d/m/Y', strtotime($employe['date_entree'])) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($employe['date_sortie']): ?>
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Date de sortie</span>
                    <p class="mt-0.5 text-slate-700"><?= date('d/m/Y', strtotime($employe['date_sortie'])) ?>
                        <?php if ($employe['motif_sortie']): ?>
                        <span class="text-xs text-slate-400">(<?= hShow($employe['motif_sortie']) ?>)</span>
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
                <?php if ($employe['diplome']): ?>
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Diplôme</span>
                    <p class="mt-0.5 text-slate-700"><?= hShow($employe['diplome']) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($employe['specialite']): ?>
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Spécialité</span>
                    <p class="mt-0.5 text-slate-700"><?= hShow($employe['specialite']) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($employe['notes']): ?>
                <div class="sm:col-span-2">
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Notes</span>
                    <p class="mt-0.5 text-slate-700"><?= hShow($employe['notes']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Contacts d'urgence -->
        <?php if (!empty($contacts)): ?>
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <i data-lucide="phone-call" class="w-4 h-4 text-violet-600"></i>
                <h3 class="font-semibold text-slate-900 text-sm">Contacts d'urgence</h3>
            </div>
            <div class="divide-y divide-slate-50">
            <?php foreach ($contacts as $c): ?>
            <div class="px-5 py-4 flex items-start gap-3 text-sm">
                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center shrink-0">
                    <i data-lucide="user" class="w-4 h-4 text-slate-400"></i>
                </div>
                <div>
                    <div class="font-medium text-slate-900">
                        <?= hShow($c['nom_complet']) ?>
                        <?php if ($c['principal']): ?>
                        <span class="ml-1 text-xs bg-violet-100 text-violet-700 px-1.5 py-0.5 rounded-md">Principal</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($c['lien']): ?>
                    <div class="text-xs text-slate-400 mt-0.5"><?= hShow($c['lien']) ?></div>
                    <?php endif; ?>
                    <div class="flex flex-wrap gap-3 mt-1">
                        <a href="tel:<?= hShow($c['telephone']) ?>" class="text-violet-600 hover:underline">
                            <i data-lucide="phone" class="w-3.5 h-3.5 inline"></i> <?= hShow($c['telephone']) ?>
                        </a>
                        <?php if ($c['telephone2']): ?>
                        <a href="tel:<?= hShow($c['telephone2']) ?>" class="text-violet-600 hover:underline">
                            <?= hShow($c['telephone2']) ?>
                        </a>
                        <?php endif; ?>
                        <?php if ($c['email']): ?>
                        <a href="mailto:<?= hShow($c['email']) ?>" class="text-violet-600 hover:underline">
                            <i data-lucide="mail" class="w-3.5 h-3.5 inline"></i> <?= hShow($c['email']) ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Colonne latérale -->
    <div class="space-y-6">

        <!-- Coordonnées -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <i data-lucide="contact" class="w-4 h-4 text-violet-600"></i>
                <h3 class="font-semibold text-slate-900 text-sm">Coordonnées</h3>
            </div>
            <div class="p-5 space-y-3 text-sm">
                <?php if ($employe['telephone']): ?>
                <div class="flex items-center gap-2">
                    <i data-lucide="phone" class="w-4 h-4 text-slate-400 shrink-0"></i>
                    <a href="tel:<?= hShow($employe['telephone']) ?>" class="text-violet-600 hover:underline">
                        <?= hShow($employe['telephone']) ?>
                    </a>
                </div>
                <?php endif; ?>
                <?php if ($employe['email_pro']): ?>
                <div class="flex items-center gap-2">
                    <i data-lucide="mail" class="w-4 h-4 text-slate-400 shrink-0"></i>
                    <a href="mailto:<?= hShow($employe['email_pro']) ?>" class="text-violet-600 hover:underline truncate">
                        <?= hShow($employe['email_pro']) ?>
                    </a>
                    <span class="text-xs text-slate-400 shrink-0">Pro</span>
                </div>
                <?php endif; ?>
                <?php if ($employe['email_perso']): ?>
                <div class="flex items-center gap-2">
                    <i data-lucide="mail" class="w-4 h-4 text-slate-400 shrink-0"></i>
                    <a href="mailto:<?= hShow($employe['email_perso']) ?>" class="text-violet-600 hover:underline truncate">
                        <?= hShow($employe['email_perso']) ?>
                    </a>
                    <span class="text-xs text-slate-400 shrink-0">Perso</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Changer le statut -->
        <?php if (!$isArchived && $canUpdate): ?>
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <i data-lucide="toggle-right" class="w-4 h-4 text-violet-600"></i>
                <h3 class="font-semibold text-slate-900 text-sm">Changer le statut</h3>
            </div>
            <div class="p-5">
                <form method="POST" action="<?= BASE_URL ?>/v2/rh/employes/<?= (int)$employe['id'] ?>/statut">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                    <select name="statut"
                            class="form-select mb-3">
                        <?php
                        $statutLabels = ['actif'=>'Actif','inactif'=>'Inactif','suspendu'=>'Suspendu','conge'=>'En congé','retraite'=>'Retraité','demissionnaire'=>'Démissionnaire'];
                        foreach ($statutLabels as $val => $lbl): ?>
                        <option value="<?= hShow($val) ?>" <?= $employe['statut'] === $val ? 'selected' : '' ?>><?= hShow($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit"
                            class="w-full py-2 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition-colors">
                        Appliquer
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Méta -->
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-xs text-slate-400 space-y-1">
            <div>Créé le <?= date('d/m/Y à H:i', strtotime($employe['created_at'])) ?></div>
            <div>Modifié le <?= date('d/m/Y à H:i', strtotime($employe['updated_at'])) ?></div>
            <?php if ($employe['user_nom']): ?>
            <div>Compte : <?= hShow($employe['user_nom']) ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>
