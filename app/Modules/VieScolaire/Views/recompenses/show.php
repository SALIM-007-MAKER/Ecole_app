<?php
/** @var array $user */
/** @var array $reward */
/** @var array $historique */
/** @var \App\Modules\VieScolaire\Recompenses\Policies\RewardPolicy $policy */

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$statutBadge = [
    'attribuee' => 'bg-amber-100 text-amber-800',
    'validee'   => 'bg-green-100 text-green-800',
    'revoquee'  => 'bg-red-100 text-red-600',
];
$niveauLabel = [
    'classe'       => 'Classe',
    'etablissement'=> 'Établissement',
    'academique'   => 'Académique',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Récompense #<?= $reward['id'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">

<?php include BASE_PATH . '/app/Views/partials/sidebar.php'; ?>

<main class="ml-64 p-8">
    <div class="flex items-center gap-3 mb-6">
        <a href="/v2/vie-scolaire/recompenses" class="text-slate-400 hover:text-slate-600">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <h1 class="text-2xl font-bold text-slate-800">Récompense #<?= $reward['id'] ?></h1>
        <span class="px-3 py-1 rounded-full text-sm font-medium <?= $statutBadge[$reward['statut']] ?? 'bg-slate-100' ?>">
            <?= ucfirst($reward['statut']) ?>
        </span>
    </div>

    <?php if ($flash_success): ?>
        <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg"><?= htmlspecialchars($flash_success) ?></div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
        <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg"><?= htmlspecialchars($flash_error) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Détails -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Carte récompense -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <div class="flex items-start gap-4 mb-6">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center"
                         style="background-color: <?= htmlspecialchars($reward['categorie_couleur']) ?>1a;">
                        <i data-lucide="award" class="w-6 h-6" style="color: <?= htmlspecialchars($reward['categorie_couleur']) ?>"></i>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-800"><?= htmlspecialchars($reward['categorie_nom']) ?></p>
                        <p class="text-sm text-slate-500">Niveau : <?= $niveauLabel[$reward['niveau']] ?? $reward['niveau'] ?></p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <p class="text-xs text-slate-500">Élève</p>
                        <p class="font-semibold text-slate-800"><?= htmlspecialchars($reward['eleve_prenom'] . ' ' . $reward['eleve_nom']) ?></p>
                        <p class="text-xs text-slate-400"><?= htmlspecialchars($reward['eleve_matricule'] ?? '') ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Classe</p>
                        <p class="font-semibold text-slate-800"><?= htmlspecialchars($reward['classe_nom']) ?></p>
                        <p class="text-xs text-slate-400"><?= htmlspecialchars($reward['annee_scolaire']) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Date d'attribution</p>
                        <p class="font-semibold text-slate-800"><?= date('d/m/Y', strtotime($reward['date_attribution'])) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Attribué par</p>
                        <p class="font-semibold text-slate-800">
                            <?= htmlspecialchars($reward['attribue_par_prenom'] . ' ' . $reward['attribue_par_nom']) ?>
                        </p>
                    </div>
                    <?php if ($reward['valide_par_nom']): ?>
                        <div>
                            <p class="text-xs text-slate-500">Validé par</p>
                            <p class="font-semibold text-slate-800">
                                <?= htmlspecialchars($reward['valide_par_prenom'] . ' ' . $reward['valide_par_nom']) ?>
                            </p>
                            <p class="text-xs text-slate-400"><?= date('d/m/Y H:i', strtotime($reward['valide_le'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <p class="text-xs text-slate-500 mb-1">Motif</p>
                    <p class="text-slate-800 bg-slate-50 rounded-lg px-4 py-3"><?= nl2br(htmlspecialchars($reward['motif'])) ?></p>
                </div>

                <?php if ($reward['motif_revocation']): ?>
                    <div class="mt-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                        <p class="text-xs font-medium text-red-700 mb-1">Motif de révocation</p>
                        <p class="text-sm text-red-800"><?= nl2br(htmlspecialchars($reward['motif_revocation'])) ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($reward['piece_jointe']): ?>
                    <div class="mt-4">
                        <a href="/<?= htmlspecialchars($reward['piece_jointe']) ?>" target="_blank"
                           class="inline-flex items-center gap-2 text-sm text-violet-600 hover:underline">
                            <i data-lucide="paperclip" class="w-4 h-4"></i> Pièce jointe
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <?php if ($reward['statut'] !== 'revoquee'): ?>
                <div class="flex flex-wrap gap-3">
                    <?php if ($policy->canModify($user, $reward)): ?>
                        <a href="/v2/vie-scolaire/recompenses/<?= $reward['id'] ?>/edit"
                           class="inline-flex items-center gap-2 bg-slate-600 hover:bg-slate-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                            <i data-lucide="edit" class="w-4 h-4"></i> Modifier
                        </a>
                    <?php endif; ?>

                    <?php if ($reward['statut'] === 'attribuee' && $policy->canValidate($user)): ?>
                        <form method="POST" action="/v2/vie-scolaire/recompenses/<?= $reward['id'] ?>/valider">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                            <button type="submit"
                                    class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                <i data-lucide="check-circle" class="w-4 h-4"></i> Valider
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($policy->canRevoke($user, $reward)): ?>
                        <button onclick="document.getElementById('bloc-revoquer').classList.toggle('hidden')"
                                class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                            <i data-lucide="x-circle" class="w-4 h-4"></i> Révoquer
                        </button>
                    <?php endif; ?>
                </div>

                <div id="bloc-revoquer" class="hidden bg-red-50 border border-red-200 rounded-xl p-4">
                    <form method="POST" action="/v2/vie-scolaire/recompenses/<?= $reward['id'] ?>/revoquer" class="space-y-3">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <label class="block text-sm font-medium text-red-800">Motif de révocation <span class="text-red-500">*</span></label>
                        <textarea name="motif" rows="3" required minlength="10"
                                  placeholder="Expliquer la raison de la révocation (min. 10 caractères)"
                                  class="w-full border border-red-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 outline-none resize-none"></textarea>
                        <div class="flex gap-3">
                            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                                Confirmer la révocation
                            </button>
                            <button type="button" onclick="document.getElementById('bloc-revoquer').classList.add('hidden')"
                                    class="px-4 py-2 border border-slate-200 rounded-lg text-sm text-slate-600 hover:bg-slate-50">
                                Annuler
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- Historique -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden h-fit">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <i data-lucide="history" class="w-4 h-4 text-slate-400"></i>
                <h2 class="font-semibold text-slate-800">Historique</h2>
            </div>
            <?php if (empty($historique)): ?>
                <p class="text-slate-400 text-sm text-center py-6">Aucun historique</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($historique as $h): ?>
                        <li class="px-5 py-3">
                            <div class="flex items-center justify-between gap-2 mb-0.5">
                                <span class="text-xs font-medium text-slate-700">
                                    <?= htmlspecialchars($h['nouveau_statut']) ?>
                                </span>
                                <span class="text-xs text-slate-400">
                                    <?= date('d/m/Y H:i', strtotime($h['modifie_le'])) ?>
                                </span>
                            </div>
                            <p class="text-xs text-slate-500">
                                <?= htmlspecialchars($h['modifie_par_prenom'] . ' ' . $h['modifie_par_nom']) ?>
                            </p>
                            <?php if ($h['motif']): ?>
                                <p class="text-xs text-slate-400 mt-0.5 italic"><?= htmlspecialchars(mb_strimwidth($h['motif'], 0, 60, '…')) ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</main>
<script>lucide.createIcons();</script>
</body>
</html>
