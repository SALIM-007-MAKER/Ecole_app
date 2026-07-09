<?php
$salle     = $salle ?? null;
$types     = $types ?? \App\Models\SalleModel::TYPES;
$old       = $old   ?? [];
$csrfToken = \Core\Session::getCsrfToken();
$v = fn($k, $def = '') => $old[$k] ?? ($salle ? ($salle->$k ?? $def) : $def);
?>

<!-- Header -->
<div class="flex items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="pencil" class="w-5 h-5 text-violet-600"></i>Modifier la salle
        </h2>
        <p class="text-sm text-slate-400 mt-0.5">Mettez à jour les informations de la salle</p>
    </div>
    <a href="<?= BASE_URL ?>/salles" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour aux salles
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <!-- Formulaire principal -->
    <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="building" class="w-4 h-4 text-violet-600"></i>
            <span class="font-semibold text-slate-700">Informations de la salle</span>
        </div>
        <div class="p-5 p-5">
            <form method="POST" action="<?= BASE_URL ?>/salles/<?= $salle->id ?? '' ?>" class="space-y-5">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">

                <!-- Identité -->
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Identité</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="mb-4 sm:col-span-2">
                            <label class="form-label">Nom <span class="text-red-500">*</span></label>
                            <input type="text" name="nom" class="form-input"
                                   value="<?= htmlspecialchars($v('nom'), ENT_QUOTES) ?>"
                                   placeholder="ex. Salle A01" required autofocus>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Type</label>
                            <select name="type" class="form-input">
                                <?php foreach ($types as $k => $t): ?>
                                <option value="<?= $k ?>" <?= $v('type','salle_cours') === $k ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['label'], ENT_QUOTES) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Bâtiment</label>
                            <input type="text" name="batiment" class="form-input"
                                   value="<?= htmlspecialchars($v('batiment'), ENT_QUOTES) ?>"
                                   placeholder="ex. Bâtiment A">
                        </div>
                    </div>
                </div>

                <hr class="border-slate-100">

                <!-- Capacité & description -->
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Détails</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label class="form-label">Capacité (places)</label>
                            <input type="number" name="capacite" class="form-input"
                                   value="<?= $v('capacite', 0) ?>" min="0">
                        </div>
                        <div class="mb-4 sm:col-span-1" style="grid-column: span 1;">
                        </div>
                        <div class="mb-4 sm:col-span-2">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" class="form-input"
                                   value="<?= htmlspecialchars($v('description'), ENT_QUOTES) ?>"
                                   placeholder="Équipements, particularités…">
                        </div>
                    </div>
                </div>

                <hr class="border-slate-100">

                <!-- Statut & actions -->
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-3 cursor-pointer p-2 rounded-lg hover:bg-slate-50 transition-colors">
                        <input type="checkbox" name="actif" value="1" id="actifEdit"
                               class="w-4 h-4 accent-violet-600"
                               <?= $v('actif', 1) ? 'checked' : '' ?>>
                        <div>
                            <span class="text-sm font-medium text-slate-700">Salle active</span>
                            <p class="text-xs text-slate-400">La salle est disponible pour le planning</p>
                        </div>
                    </label>
                    <div class="flex items-center gap-3">
                        <a href="<?= BASE_URL ?>/salles" class="btn btn-secondary">Annuler</a>
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="save" class="w-4 h-4"></i>Mettre à jour
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Panneau info -->
    <div class="space-y-4">
        <?php if ($salle): ?>
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="info" class="w-4 h-4 text-violet-600"></i>
                <span class="font-semibold text-slate-700">Informations</span>
            </div>
            <div class="p-5 p-4 space-y-3">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-500">Utilisation</span>
                    <span class="font-semibold text-slate-800">
                        <?php $nb = (int)($salle->nb_seances ?? 0); ?>
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= $nb > 0 ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-600' ?>"><?= $nb ?> séance(s)/sem.</span>
                    </span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-500">Statut actuel</span>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= $salle->actif ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>">
                        <?= $salle->actif ? 'Active' : 'Inactive' ?>
                    </span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm bg-amber-50 border-amber-100">
            <div class="p-5 p-4">
                <p class="text-xs font-semibold text-amber-700 flex items-center gap-1.5 mb-2">
                    <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>Attention
                </p>
                <p class="text-xs text-amber-600">Une salle désactivée ne sera plus proposée lors de la création de séances mais les séances existantes restent inchangées.</p>
            </div>
        </div>
    </div>
</div>
