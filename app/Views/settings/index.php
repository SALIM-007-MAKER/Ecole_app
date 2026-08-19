<?php
$categories = $categories ?? [];
?>

<div class="flex items-start gap-4 mb-6">
    <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="settings" class="w-5 h-5 text-violet-600"></i>
    </div>
    <div>
        <h2 class="text-lg font-bold text-slate-900">Paramètres</h2>
        <p class="text-sm text-slate-500">Centre de configuration de votre établissement</p>
    </div>
</div>

<?php if (empty($categories)): ?>
<div class="rounded-xl bg-white border border-slate-200 shadow-sm p-12 text-center text-slate-400">
    <i data-lucide="lock" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
    <p class="font-medium">Aucun paramètre accessible avec votre rôle actuel.</p>
</div>
<?php else: ?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($categories as $cat): ?>
    <a href="<?= BASE_URL . htmlspecialchars($cat['url'], ENT_QUOTES) ?>"
       class="flex items-start gap-4 p-5 rounded-xl bg-white border border-slate-200 shadow-sm hover:border-violet-300 hover:shadow-md transition">
        <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center shrink-0">
            <i data-lucide="<?= htmlspecialchars($cat['icon'], ENT_QUOTES) ?>" class="w-5 h-5 text-violet-600"></i>
        </div>
        <div>
            <div class="font-semibold text-slate-900"><?= htmlspecialchars($cat['label'], ENT_QUOTES) ?></div>
            <div class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($cat['desc'], ENT_QUOTES) ?></div>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
