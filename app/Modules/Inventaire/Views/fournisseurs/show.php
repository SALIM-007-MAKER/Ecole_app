<?php /** @var array $fournisseur */ ?>
<div class="max-w-4xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="<?= BASE_URL ?>/v2/inventaire/fournisseurs" class="text-slate-500 hover:text-slate-700">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($fournisseur['nom']) ?></h1>
                <?php $colors=['actif'=>'green','inactif'=>'slate','bloque'=>'red']; $col=$colors[$fournisseur['statut']]??'slate'; ?>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-<?=$col?>-100 text-<?=$col?>-700">
                    <?= ucfirst($fournisseur['statut']) ?>
                </span>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/v2/inventaire/fournisseurs/<?=$fournisseur['id']?>/modifier"
           class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg hover:bg-violet-700 text-sm font-medium">
            <i data-lucide="pencil" class="w-4 h-4"></i> Modifier
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6">
        <dl class="grid grid-cols-2 gap-x-8 gap-y-4 text-sm">
            <div>
                <dt class="text-slate-500 mb-0.5">Email</dt>
                <dd class="font-medium"><?= htmlspecialchars($fournisseur['email'] ?? '—') ?></dd>
            </div>
            <div>
                <dt class="text-slate-500 mb-0.5">Téléphone</dt>
                <dd class="font-medium"><?= htmlspecialchars($fournisseur['telephone'] ?? '—') ?></dd>
            </div>
            <div>
                <dt class="text-slate-500 mb-0.5">SIRET</dt>
                <dd class="font-medium font-mono text-xs"><?= htmlspecialchars($fournisseur['siret'] ?? '—') ?></dd>
            </div>
            <div>
                <dt class="text-slate-500 mb-0.5">Délai livraison</dt>
                <dd class="font-medium"><?= $fournisseur['delai_livraison_j'] ?? '—' ?> jours</dd>
            </div>
            <?php if (!empty($fournisseur['adresse'])): ?>
            <div class="col-span-2">
                <dt class="text-slate-500 mb-0.5">Adresse</dt>
                <dd class="font-medium"><?= nl2br(htmlspecialchars($fournisseur['adresse'])) ?></dd>
            </div>
            <?php endif; ?>
            <?php if (!empty($fournisseur['notes'])): ?>
            <div class="col-span-2">
                <dt class="text-slate-500 mb-0.5">Notes</dt>
                <dd class="text-slate-700"><?= nl2br(htmlspecialchars($fournisseur['notes'])) ?></dd>
            </div>
            <?php endif; ?>
        </dl>

        <?php if ($fournisseur['statut'] !== 'bloque'): ?>
        <div class="mt-6 pt-4 border-t flex justify-end">
            <form method="POST" action="<?= BASE_URL ?>/v2/inventaire/fournisseurs/<?=$fournisseur['id']?>/bloquer">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <button type="submit"
                        onclick="return confirm('Bloquer ce fournisseur ?')"
                        class="text-sm text-red-600 hover:text-red-800 font-medium">
                    <i data-lucide="ban" class="w-4 h-4 inline mr-1"></i>Bloquer
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>lucide.createIcons();</script>
