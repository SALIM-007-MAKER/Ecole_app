<?php
$frais    = $frais    ?? [];
$periodes = $periodes ?? [];
$old      = $old      ?? [];
$csrfToken= \Core\Session::getCsrfToken();
$currentUser = \Core\Session::getUser();
$canEdit  = in_array('comptabilite.edit', $currentUser['permissions'] ?? [], true);

function pctBar(float $attendu, float $enc): float {
    return $attendu > 0 ? min(100, round($enc / $attendu * 100)) : 0;
}
?>

<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
            <i data-lucide="tags" class="w-5 h-5 text-violet-600"></i>
        </div>
        <h2 class="text-xl font-bold text-slate-800 pt-2">Types de frais scolaires</h2>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= BASE_URL ?>/comptabilite/frais/affecter" class="btn btn-primary">
            <i data-lucide="user-plus" class="w-4 h-4"></i>Affecter aux élèves
        </a>
        <a href="<?= BASE_URL ?>/comptabilite" class="btn btn-outline">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>Dashboard
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Liste -->
    <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-800 text-slate-100">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Nom</th>
                        <th class="px-4 py-3 text-center font-semibold">Périodicité</th>
                        <th class="px-4 py-3 text-right font-semibold">Montant défaut</th>
                        <th class="px-4 py-3 text-center font-semibold">Affect.</th>
                        <th class="px-4 py-3 font-semibold">Recouvrement</th>
                        <th class="px-4 py-3 text-center font-semibold">Statut</th>
                        <?php if ($canEdit): ?><th class="px-4 py-3 text-right font-semibold">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php if (empty($frais)): ?>
                <tr>
                    <td colspan="7" class="px-4 py-10 text-center text-slate-400">
                        <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
                        <p>Aucun type de frais configuré</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($frais as $f): ?>
                <?php
                    $pct = pctBar((float)$f->total_attendu, (float)$f->total_encaisse);
                    $reste = (float)$f->total_attendu - (float)$f->total_encaisse;
                ?>
                <tr class="hover:bg-slate-50 <?= !$f->actif ? 'opacity-60' : '' ?>">
                    <td class="px-4 py-3">
                        <p class="font-semibold text-slate-800"><?= htmlspecialchars($f->nom, ENT_QUOTES) ?></p>
                        <?php if ($f->description): ?>
                        <p class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($f->description, ENT_QUOTES) ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-slate-100 text-slate-700">
                            <?= htmlspecialchars($periodes[$f->periodicite] ?? $f->periodicite, ENT_QUOTES) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right font-bold text-slate-800">
                        <?= number_format((float)$f->montant_defaut, 2, ',', ' ') ?> FCFA
                    </td>
                    <td class="px-4 py-3 text-center text-slate-500 text-xs"><?= (int)$f->nb_affectations ?></td>
                    <td class="px-4 py-3" style="min-width:120px">
                        <?php if ((float)$f->total_attendu > 0): ?>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-slate-500"><?= $pct ?>%</span>
                            <span class="text-red-500"><?= number_format($reste, 0, ',', ' ') ?> F restant</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-1.5">
                            <div class="bg-emerald-500 h-1.5 rounded-full" style="width:<?= $pct ?>%"></div>
                        </div>
                        <?php else: ?>
                        <span class="text-slate-400 text-xs">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($f->actif): ?>
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-emerald-100 text-emerald-700">Actif</span>
                        <?php else: ?>
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-slate-100 text-slate-500">Inactif</span>
                        <?php endif; ?>
                    </td>
                    <?php if ($canEdit): ?>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-1">
                            <a href="<?= BASE_URL ?>/comptabilite/frais/<?= $f->id ?>/edit"
                               class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-600" title="Modifier">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                            </a>
                            <form method="POST" action="<?= BASE_URL ?>/comptabilite/frais/<?= $f->id ?>/delete"
                                  onsubmit="return confirm('Supprimer ce type de frais ?')">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                                <button class="p-1.5 rounded-lg border border-red-200 hover:bg-red-50 text-red-500" title="Supprimer">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Formulaire création -->
    <?php if ($canEdit): ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3 flex items-center gap-2">
            <i data-lucide="plus-circle" class="w-4 h-4 text-emerald-500"></i>
            <span class="font-semibold text-slate-700">Nouveau type de frais</span>
        </div>
        <div class="p-4">
            <form method="POST" action="<?= BASE_URL ?>/comptabilite/frais/store" class="space-y-3">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                <div>
                    <label class="form-label">Nom <span class="text-red-500">*</span></label>
                    <input type="text" name="nom" class="form-input"
                           value="<?= htmlspecialchars($old['nom'] ?? '', ENT_QUOTES) ?>"
                           placeholder="Ex: Frais d'inscription" required>
                </div>
                <div>
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-input resize-none" rows="2"
                              placeholder="Description optionnelle"><?= htmlspecialchars($old['description'] ?? '', ENT_QUOTES) ?></textarea>
                </div>
                <div>
                    <label class="form-label">Montant par défaut (FCFA) <span class="text-red-500">*</span></label>
                    <input type="number" name="montant_defaut" class="form-input"
                           value="<?= $old['montant_defaut'] ?? 0 ?>" min="0" step="0.01" required>
                </div>
                <div>
                    <label class="form-label">Périodicité</label>
                    <select name="periodicite" class="form-select">
                        <?php foreach ($periodes as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($old['periodicite'] ?? 'annuel') === $k ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v, ENT_QUOTES) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary w-full">
                    <i data-lucide="save" class="w-4 h-4"></i>Créer le type de frais
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
