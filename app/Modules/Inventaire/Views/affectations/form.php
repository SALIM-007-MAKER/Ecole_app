<?php /** @var array $articles @var array $emplacements @var string|null $error */ ?>
<div class="max-w-2xl mx-auto px-4 py-6">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= BASE_URL ?>/v2/inventaire/affectations" class="text-slate-500 hover:text-slate-700">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <h1 class="text-2xl font-bold text-slate-800">Affecter un article</h1>
    </div>

    <?php if (!empty($error)): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 mb-4 text-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/v2/inventaire/affectations" class="bg-white rounded-xl shadow-sm p-6 space-y-5">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Article <span class="text-red-500">*</span></label>
            <select name="article_id" required
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                <option value="">— Sélectionner un article —</option>
                <?php foreach ($articles as $a): ?>
                <option value="<?=$a['id']?>"><?= htmlspecialchars($a['designation']) ?> (<?= htmlspecialchars($a['reference']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Emplacement source <span class="text-red-500">*</span></label>
            <select name="emplacement_id" required
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                <option value="">— Sélectionner l'emplacement —</option>
                <?php foreach ($emplacements as $e): ?>
                <option value="<?=$e['id']?>"><?= htmlspecialchars($e['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">ID Utilisateur / Employé <span class="text-red-500">*</span></label>
                <input type="number" name="user_id" required min="1"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Quantité <span class="text-red-500">*</span></label>
                <input type="number" name="quantite" required min="0.01" step="0.01" value="1"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Date affectation <span class="text-red-500">*</span></label>
                <input type="date" name="date_affectation" required value="<?= date('Y-m-d') ?>"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Retour prévu</label>
                <input type="date" name="date_retour_prevue"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
            <textarea name="notes" rows="2"
                      class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500"></textarea>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="<?= BASE_URL ?>/v2/inventaire/affectations" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Annuler</a>
            <button type="submit" class="bg-violet-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-violet-700">
                Affecter
            </button>
        </div>
    </form>
</div>
<script>lucide.createIcons();</script>
