<?php
/** @var array $user */
/** @var array $sanction */

?>
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= BASE_URL ?>/v2/vie-scolaire/discipline/<?= $sanction['dossier_id'] ?>"
           class="inline-flex items-center gap-2 px-3 py-1.5 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm transition-colors flex-shrink-0">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour
        </a>
        <h1 class="text-2xl font-bold text-slate-800">Faire appel d'une sanction</h1>
    </div>

    <div class="bg-purple-50 border border-purple-200 rounded-lg px-4 py-3 mb-6 text-sm text-purple-800">
        <strong>Sanction :</strong> <?= htmlspecialchars(str_replace('_', ' ', $sanction['type_sanction'])) ?>
        — <strong>Motif :</strong> <?= htmlspecialchars($sanction['motif']) ?>
    </div>


    <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/discipline/sanctions/<?= $sanction['id'] ?>/appel"
          enctype="multipart/form-data"
          class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-5 max-w-2xl">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">

        <div>
            <label class="form-label">Motif de l'appel <span class="form-required">*</span></label>
            <textarea name="description" rows="5" required minlength="20"
                      placeholder="Exposez clairement les raisons de cet appel (minimum 20 caractères)"
                      class="form-textarea resize-none"></textarea>
        </div>

        <div>
            <label class="form-label">Pièce jointe (optionnel)</label>
            <input type="file" name="piece_jointe" accept=".pdf,.jpg,.jpeg,.png"
                   class="w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
        </div>

        <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-xs text-amber-800">
            Un seul appel est possible par sanction. L'appel sera examiné par la direction.
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="<?= BASE_URL ?>/v2/vie-scolaire/discipline/<?= $sanction['dossier_id'] ?>"
               class="px-5 py-2 border border-slate-200 text-slate-600 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors">Annuler</a>
            <button type="submit"
                    class="px-6 py-2 bg-violet-600 hover:bg-violet-700 text-white rounded-lg text-sm font-medium transition-colors">
                Soumettre l'appel
            </button>
        </div>
    </form>
<script>lucide.createIcons();</script>
