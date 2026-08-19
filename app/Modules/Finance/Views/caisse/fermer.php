<?php
/**
 * Finance V2 — Fermeture de caisse
 */
$session = $session ?? null;
$totaux  = $totaux  ?? null;
$errors  = $errors  ?? [];
$old     = $old     ?? [];
$old_v   = fn(string $k, $def = '') => htmlspecialchars((string)($old[$k] ?? $def));
$fmtMontant = fn(float $v): string => number_format($v, 0, ',', ' ') . ' XOF';

$soldeInitial       = (float)($session->solde_initial ?? 0);
$totalCredits       = (float)($totaux->total_credits ?? 0);
$totalDebits        = (float)($totaux->total_debits ?? 0);
$soldeTheorique     = round($soldeInitial + $totalCredits - $totalDebits, 2);
$totalRecettes      = (float)($totaux->total_recettes ?? 0);
$totalDecaissements = (float)($totaux->total_decaissements ?? 0);
?>
<div class="max-w-2xl mx-auto space-y-6">

    <div class="flex items-center gap-3">
        <a href="<?= BASE_URL ?>/v2/finance/caisse/<?= $session->id ?>"
           class="p-2 text-slate-500 hover:text-violet-600 rounded-lg hover:bg-violet-50">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Fermer la caisse</h1>
            <p class="text-sm text-slate-500"><?= htmlspecialchars($session->numero ?? '') ?> — <?= htmlspecialchars($session->caissier_nom ?? '') ?></p>
        </div>
    </div>

    <?php if (!empty($errors['global'])): ?>
    <div class="flex items-center gap-3 p-4 bg-red-50 text-red-800 rounded-xl border border-red-200">
        <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0"></i>
        <span><?= htmlspecialchars($errors['global']) ?></span>
    </div>
    <?php endif; ?>

    <!-- Récap automatique -->
    <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-4">
        <div class="text-sm font-semibold text-slate-600 uppercase tracking-wide">Récapitulatif calculé automatiquement</div>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between py-2 border-b border-slate-100">
                <span class="text-slate-500">Solde d'ouverture</span>
                <span class="font-semibold text-slate-700"><?= $fmtMontant($soldeInitial) ?></span>
            </div>
            <div class="flex justify-between py-2 border-b border-slate-100">
                <span class="text-slate-500">Total recettes</span>
                <span class="font-semibold text-emerald-700">+ <?= $fmtMontant($totalRecettes) ?></span>
            </div>
            <div class="flex justify-between py-2 border-b border-slate-100">
                <span class="text-slate-500">Total décaissements</span>
                <span class="font-semibold text-rose-600">− <?= $fmtMontant($totalDecaissements) ?></span>
            </div>
            <?php if ($totalCredits !== $totalRecettes || $totalDebits !== $totalDecaissements): ?>
            <div class="flex justify-between py-2 border-b border-slate-100">
                <span class="text-slate-500">Corrections nettes</span>
                <span class="font-semibold text-amber-600"><?= $fmtMontant($totalCredits - $totalRecettes - ($totalDebits - $totalDecaissements)) ?></span>
            </div>
            <?php endif; ?>
            <div class="flex justify-between py-3 bg-violet-50 rounded-lg px-3 mt-2">
                <span class="font-bold text-slate-800">Solde théorique</span>
                <span class="font-black text-violet-700 text-lg"><?= $fmtMontant($soldeTheorique) ?></span>
            </div>
        </div>
    </div>

    <!-- Formulaire fermeture -->
    <form method="POST" action="<?= BASE_URL ?>/v2/finance/caisse/<?= $session->id ?>/fermer"
          class="bg-white rounded-xl border border-rose-200 p-6 space-y-5">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">

        <div class="text-sm font-semibold text-rose-700 uppercase tracking-wide">Comptage réel de la caisse</div>

        <!-- Solde réel -->
        <div>
            <label class="form-label">
                Solde réel compté <span class="form-required">*</span>
            </label>
            <div class="relative">
                <input type="number" name="solde_reel" id="solde_reel"
                       value="<?= $old_v('solde_reel', $soldeTheorique) ?>"
                       min="0" step="0.01" required
                       oninput="calculerEcart()"
                       class="form-input <?= isset($errors['solde_reel']) ? 'is-invalid' : '' ?> pl-3 pr-16">
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-slate-500">XOF</span>
            </div>
            <?php if (isset($errors['solde_reel'])): ?>
            <p class="mt-1 text-xs text-red-600"><?= htmlspecialchars($errors['solde_reel']) ?></p>
            <?php endif; ?>
        </div>

        <!-- Écart live -->
        <div id="ecart_zone" class="p-3 rounded-lg bg-slate-50 text-sm">
            <div class="flex justify-between">
                <span class="text-slate-500">Écart (réel − théorique)</span>
                <span id="ecart_val" class="font-bold text-slate-700">— XOF</span>
            </div>
        </div>

        <!-- Note -->
        <div>
            <label class="form-label">Note de fermeture (optionnel)</label>
            <textarea name="note" rows="2"
                      class="form-textarea"
                      placeholder="Commentaires, anomalies constatées…"><?= $old_v('note') ?></textarea>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit"
                    class="flex-1 px-6 py-3 text-sm font-bold text-white bg-rose-600 rounded-lg hover:bg-rose-700"
                    onclick="return confirm('Confirmer la fermeture de la caisse ?')">
                <i data-lucide="lock" class="inline w-4 h-4 mr-1"></i> Confirmer la fermeture
            </button>
            <a href="<?= BASE_URL ?>/v2/finance/caisse/<?= $session->id ?>"
               class="px-4 py-3 text-sm text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200">
                Annuler
            </a>
        </div>
    </form>
</div>

<script>
const soldeTheorique = <?= $soldeTheorique ?>;
function calculerEcart() {
    const reel  = parseFloat(document.getElementById('solde_reel').value) || 0;
    const ecart = reel - soldeTheorique;
    const zone  = document.getElementById('ecart_zone');
    const val   = document.getElementById('ecart_val');
    const signe = ecart >= 0 ? '+' : '';
    val.textContent = signe + new Intl.NumberFormat('fr-FR').format(Math.round(ecart)) + ' XOF';
    val.className = 'font-bold ' + (ecart < -0.01 ? 'text-rose-600' : ecart > 0.01 ? 'text-amber-600' : 'text-emerald-600');
}
document.addEventListener('DOMContentLoaded', calculerEcart);
</script>
