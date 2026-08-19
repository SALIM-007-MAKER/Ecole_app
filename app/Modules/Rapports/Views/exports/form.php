<div class="max-w-2xl mx-auto px-4 py-8">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= BASE_URL ?>/v2/rapports/exports" class="text-slate-400 hover:text-violet-600">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <h1 class="text-xl font-bold text-slate-800">Générer un rapport</h1>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <form method="get" action="<?= BASE_URL ?>/v2/rapports/exports/apercu" class="space-y-5">

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Domaine</label>
                <select name="domaine" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                    <?php foreach (['scolarite','academique','finance','vie_scolaire','rh','bibliotheque','inventaire'] as $d): ?>
                    <option value="<?= $d ?>" <?= ($domaine ?? '') === $d ? 'selected' : '' ?>>
                        <?= str_replace('_', ' ', ucwords($d)) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Date début</label>
                    <input type="date" name="date_debut" value="<?= date('Y-m-01') ?>"
                           class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Date fin</label>
                    <input type="date" name="date_fin" value="<?= date('Y-m-t') ?>"
                           class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Format d'export</label>
                <div class="grid grid-cols-3 gap-3">
                    <?php foreach (['pdf' => 'PDF', 'excel' => 'Excel', 'csv' => 'CSV'] as $val => $label): ?>
                    <label class="flex items-center gap-2 border border-slate-200 rounded-lg p-3 cursor-pointer hover:border-violet-400 has-[:checked]:border-violet-600 has-[:checked]:bg-violet-50">
                        <input type="radio" name="type_export" value="<?= $val ?>" <?= $val === 'pdf' ? 'checked' : '' ?> class="text-violet-600">
                        <span class="text-sm font-medium text-slate-700"><?= $label ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700">
                    <i data-lucide="eye" class="w-4 h-4"></i> Aperçu
                </button>
                <button type="submit" formaction="<?= BASE_URL ?>/v2/rapports/exports/csv"
                        class="px-4 py-2 border border-slate-200 text-slate-700 rounded-lg text-sm hover:bg-slate-50">
                    <i data-lucide="download" class="w-4 h-4 inline-block"></i> CSV
                </button>
                <button type="submit" formaction="<?= BASE_URL ?>/v2/rapports/exports/excel"
                        class="px-4 py-2 border border-slate-200 text-slate-700 rounded-lg text-sm hover:bg-slate-50">
                    <i data-lucide="table" class="w-4 h-4 inline-block"></i> Excel
                </button>
            </div>
        </form>
    </div>
</div>
<script>lucide.createIcons();</script>
