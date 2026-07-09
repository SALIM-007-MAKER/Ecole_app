<?php
$errors = $errors ?? [];
$old    = $old    ?? [];
$user   = $user   ?? [];
?>
<div class="p-6 max-w-2xl mx-auto space-y-6">

  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-slate-800">Nouvel exercice comptable</h1>
      <p class="text-slate-500 text-sm">Définissez la période et le report à nouveau</p>
    </div>
    <a href="/v2/finance/comptabilite/exercices" class="text-sm text-slate-500 hover:text-slate-700">← Exercices</a>
  </div>

  <?php if (isset($errors['general'])): ?>
  <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 flex items-start gap-3">
    <i data-lucide="alert-circle" class="w-5 h-5 text-rose-500 flex-shrink-0 mt-0.5"></i>
    <p class="text-rose-700 text-sm"><?= htmlspecialchars($errors['general']) ?></p>
  </div>
  <?php endif; ?>

  <form method="POST" action="/v2/finance/comptabilite/exercices" class="space-y-5">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

    <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-5">

      <!-- Libellé -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">
          Libellé <span class="text-rose-500">*</span>
        </label>
        <input type="text" name="libelle"
               value="<?= htmlspecialchars($old['libelle'] ?? '') ?>"
               placeholder="Ex: Exercice 2026-2027"
               class="w-full rounded-lg border <?= isset($errors['libelle']) ? 'border-rose-300 bg-rose-50' : 'border-slate-200' ?> px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-300">
        <?php if (isset($errors['libelle'])): ?>
        <p class="text-rose-500 text-xs mt-1"><?= htmlspecialchars($errors['libelle']) ?></p>
        <?php endif; ?>
      </div>

      <!-- Dates -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1.5">
            Date de début <span class="text-rose-500">*</span>
          </label>
          <input type="date" name="date_debut"
                 value="<?= htmlspecialchars($old['date_debut'] ?? '') ?>"
                 class="w-full rounded-lg border <?= isset($errors['date_debut']) ? 'border-rose-300 bg-rose-50' : 'border-slate-200' ?> px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-300">
          <?php if (isset($errors['date_debut'])): ?>
          <p class="text-rose-500 text-xs mt-1"><?= htmlspecialchars($errors['date_debut']) ?></p>
          <?php endif; ?>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1.5">
            Date de fin <span class="text-rose-500">*</span>
          </label>
          <input type="date" name="date_fin"
                 value="<?= htmlspecialchars($old['date_fin'] ?? '') ?>"
                 class="w-full rounded-lg border <?= isset($errors['date_fin']) ? 'border-rose-300 bg-rose-50' : 'border-slate-200' ?> px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-300">
          <?php if (isset($errors['date_fin'])): ?>
          <p class="text-rose-500 text-xs mt-1"><?= htmlspecialchars($errors['date_fin']) ?></p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Report à nouveau -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">
          Report à nouveau (solde initial)
        </label>
        <div class="relative">
          <input type="number" name="solde_report" min="0" step="1"
                 value="<?= htmlspecialchars($old['solde_report'] ?? '0') ?>"
                 placeholder="0"
                 class="w-full rounded-lg border border-slate-200 px-4 py-2.5 pr-16 text-sm focus:outline-none focus:ring-2 focus:ring-violet-300">
          <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-medium">XOF</span>
        </div>
        <p class="text-xs text-slate-400 mt-1">Solde de report du résultat de l'exercice précédent (0 si premier exercice).</p>
      </div>

      <!-- Note -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Note (optionnelle)</label>
        <textarea name="note" rows="2" placeholder="Observations…"
                  class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-300"><?= htmlspecialchars($old['note'] ?? '') ?></textarea>
      </div>
    </div>

    <!-- Info génération périodes -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-start gap-3">
      <i data-lucide="info" class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5"></i>
      <p class="text-blue-700 text-sm">
        Les <strong>périodes mensuelles</strong> seront générées automatiquement pour toute la durée de l'exercice (max 12 périodes).
        Toutes les périodes seront créées avec le statut <em>Ouverte</em>.
      </p>
    </div>

    <!-- Actions -->
    <div class="flex gap-3">
      <button type="submit" class="flex-1 bg-violet-600 text-white py-3 rounded-xl font-medium hover:bg-violet-700 transition-colors">
        <i data-lucide="check" class="w-4 h-4 inline mr-1"></i>Créer l'exercice
      </button>
      <a href="/v2/finance/comptabilite/exercices"
         class="px-6 py-3 bg-slate-100 text-slate-600 rounded-xl font-medium hover:bg-slate-200 transition-colors text-center">
        Annuler
      </a>
    </div>
  </form>
</div>
