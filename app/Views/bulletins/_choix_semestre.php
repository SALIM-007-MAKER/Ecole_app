<?php
/**
 * Bouton « bulletin » + boîte de dialogue de choix du semestre.
 *
 * Variables attendues :
 *   $csEleveId   int     élève concerné
 *   $csSemestres array   périodes proposées (objets/tableaux avec id, nom)
 *   $csLabel     string  libellé du bouton (défaut « Télécharger PDF »)
 *   $csIcon      string  icône Lucide (défaut « download »)
 */
$csLabel = $csLabel ?? 'Télécharger PDF';
$csIcon  = $csIcon  ?? 'download';
$csUid   = 'bsem-' . (int)$csEleveId . '-' . substr(md5($csLabel . count($csSemestres)), 0, 6);
$csGet   = static fn($s, string $k) => is_array($s) ? ($s[$k] ?? null) : ($s->$k ?? null);
?>
<?php if (empty($csSemestres)): ?>
<span class="btn btn-secondary opacity-50 cursor-not-allowed" title="Aucun semestre configuré">
    <i data-lucide="<?= htmlspecialchars($csIcon, ENT_QUOTES) ?>" class="w-4 h-4"></i>Bulletin indisponible
</span>
<?php else: ?>
<button type="button" class="btn btn-primary" onclick="document.getElementById('<?= $csUid ?>').showModal()">
    <i data-lucide="<?= htmlspecialchars($csIcon, ENT_QUOTES) ?>" class="w-4 h-4"></i><?= htmlspecialchars($csLabel, ENT_QUOTES) ?>
</button>
<dialog id="<?= $csUid ?>" class="rounded-xl border border-slate-200 shadow-xl p-0 w-full max-w-sm backdrop:bg-slate-900/40">
    <form method="dialog" class="p-5">
        <h3 class="text-base font-bold text-slate-900 mb-1">Quel bulletin ?</h3>
        <p class="text-sm text-slate-500 mb-4">Choisissez le bulletin à télécharger ou imprimer.</p>
        <div class="flex flex-col gap-2">
            <?php foreach ($csSemestres as $s): ?>
            <a href="<?= BASE_URL ?>/v2/academique/bulletins/<?= (int)$csEleveId ?>/<?= (int)$csGet($s, 'id') ?>/imprimer"
               target="_blank" class="btn btn-success justify-center"
               onclick="this.closest('dialog').close()">
                <i data-lucide="printer" class="w-4 h-4"></i><?= htmlspecialchars((string)$csGet($s, 'nom'), ENT_QUOTES) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <button class="btn btn-secondary w-full justify-center mt-3">Annuler</button>
    </form>
</dialog>
<?php endif; ?>
