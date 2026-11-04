<?php
/**
 * Finance V2 — Ouverture de caisse
 */
$errors = $errors ?? [];
$old    = $old    ?? [];
$old_v  = fn(string $k, $def = '') => htmlspecialchars((string)($old[$k] ?? $def));
?>
<div class="max-w-lg mx-auto space-y-6">

    <div class="flex items-center gap-3">
        <a href="<?= BASE_URL ?>/v2/finance/caisse"
           class="p-2 text-slate-500 hover:text-violet-600 rounded-lg hover:bg-violet-50">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Ouvrir la caisse</h1>
            <p class="text-sm text-slate-500">Session du <?= date('d/m/Y') ?> à <?= date('H:i') ?></p>
        </div>
    </div>

    <?php if (!empty($errors['global'])): ?>
    <div class="flex items-center gap-3 p-4 bg-red-50 text-red-800 rounded-xl border border-red-200">
        <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0"></i>
        <span><?= htmlspecialchars($errors['global']) ?></span>
    </div>
    <?php endif; ?>

    <!-- Info -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-800">
        <div class="flex items-center gap-2 mb-1">
            <i data-lucide="info" class="w-4 h-4"></i>
            <strong>Règle</strong>
        </div>
        <p>Une seule session de caisse peut être ouverte par caissier. Chaque encaissement enregistré sera automatiquement imputé à votre session active.</p>
    </div>

    <form method="POST" action="<?= BASE_URL ?>/v2/finance/caisse" class="bg-white rounded-xl border border-slate-200 p-6 space-y-5">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">

        <!-- Solde initial -->
        <div>
            <label class="form-label">
                Fonds d'ouverture (solde initial)
            </label>
            <div class="relative">
                <input type="number" name="solde_initial" value="<?= $old_v('solde_initial', '0') ?>"
                       min="0" step="0.01"
                       class="form-input <?= isset($errors['solde_initial']) ? 'is-invalid' : '' ?> pl-3 pr-16">
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-slate-500">XOF</span>
            </div>
            <?php if (isset($errors['solde_initial'])): ?>
            <p class="mt-1 text-xs text-red-600"><?= htmlspecialchars($errors['solde_initial']) ?></p>
            <?php else: ?>
            <p class="mt-1 text-xs text-slate-500">Montant des espèces en caisse à l'ouverture (peut être 0).</p>
            <?php endif; ?>
        </div>

        <!-- Note -->
        <div>
            <label class="form-label">Note d'ouverture (optionnel)</label>
            <textarea name="note" rows="2"
                      class="form-textarea"
                      placeholder="Observations…"><?= $old_v('note') ?></textarea>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn btn-success flex-1">
                <i data-lucide="unlock" class="w-4 h-4"></i> Ouvrir la caisse
            </button>
            <a href="<?= BASE_URL ?>/v2/finance/caisse"
               class="px-4 py-3 text-sm text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200">
                Annuler
            </a>
        </div>
    </form>
</div>
