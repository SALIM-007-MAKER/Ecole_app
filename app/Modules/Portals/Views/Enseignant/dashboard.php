<?php /** @var \App\Modules\Portals\DTOs\DashboardDTO $dashboard */ ?>
<div class="space-y-6">

  <!-- Actions rapides enseignant -->
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
    <a href="<?= BASE_URL ?>/v2/portals/enseignant/appel"
       class="flex items-center gap-3 bg-teal-50 border border-teal-200 rounded-xl p-4 hover:bg-teal-100 transition-colors">
      <i data-lucide="check-square" class="w-6 h-6 text-teal-600 flex-shrink-0"></i>
      <span class="text-sm font-medium text-teal-800">Faire l'appel</span>
    </a>
    <a href="<?= BASE_URL ?>/v2/portals/enseignant/notes"
       class="flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-xl p-4 hover:bg-blue-100 transition-colors">
      <i data-lucide="edit-3" class="w-6 h-6 text-blue-600 flex-shrink-0"></i>
      <span class="text-sm font-medium text-blue-800">Saisir les notes</span>
    </a>
    <a href="<?= BASE_URL ?>/v2/portals/enseignant/emploi-du-temps"
       class="flex items-center gap-3 bg-slate-50 border border-slate-200 rounded-xl p-4 hover:bg-slate-100 transition-colors">
      <i data-lucide="calendar" class="w-6 h-6 text-slate-600 flex-shrink-0"></i>
      <span class="text-sm font-medium text-slate-800">Mon emploi du temps</span>
    </a>
    <a href="<?= BASE_URL ?>/v2/portals/enseignant/messagerie"
       class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 rounded-xl p-4 hover:bg-emerald-100 transition-colors">
      <i data-lucide="mail" class="w-6 h-6 text-emerald-600 flex-shrink-0"></i>
      <span class="text-sm font-medium text-emerald-800">Messagerie</span>
    </a>
  </div>

  <!-- Widgets -->
  <?php if (!empty($dashboard->widgets)): ?>
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
    <?php foreach ($dashboard->widgets as $widget): ?>
    <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm
                <?= in_array($widget->taille, ['lg','xl']) ? 'md:col-span-2' : '' ?>"
         data-widget-id="<?= htmlspecialchars($widget->id) ?>">
      <h3 class="text-sm font-semibold text-slate-700 flex items-center gap-2 mb-3">
        <i data-lucide="<?= htmlspecialchars($widget->icon) ?>" class="w-4 h-4 text-teal-500"></i>
        <?= htmlspecialchars($widget->titre) ?>
      </h3>
      <?php if (!empty($widget->data)): ?>
      <div class="text-sm text-slate-600">
        <?php if (isset($widget->data['aujourd_hui'])): ?>
          <?php if (empty($widget->data['aujourd_hui'])): ?>
            <p class="text-slate-400 text-xs">Pas de cours aujourd'hui</p>
          <?php else: ?>
          <ul class="space-y-2">
            <?php foreach ($widget->data['aujourd_hui'] as $cr): ?>
            <li class="flex items-center gap-3 bg-teal-50 rounded-lg p-2">
              <span class="text-xs font-medium text-teal-700 w-16 flex-shrink-0"><?= htmlspecialchars($cr['heure_debut'] ?? '') ?></span>
              <span class="font-medium text-slate-800 truncate"><?= htmlspecialchars($cr['matiere_nom'] ?? '') ?></span>
              <span class="text-slate-500 text-xs ml-auto"><?= htmlspecialchars($cr['classe_nom'] ?? '') ?></span>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        <?php elseif (isset($widget->data['classes'])): ?>
          <ul class="space-y-1">
            <?php foreach (array_slice((array)$widget->data['classes'], 0, 5) as $cls): ?>
            <li class="flex items-center justify-between text-xs py-1 border-b border-slate-100">
              <span class="font-medium"><?= htmlspecialchars($cls['nom'] ?? '') ?></span>
              <span class="text-slate-400"><?= htmlspecialchars($cls['matiere_nom'] ?? '') ?> • <?= (int)($cls['nb_eleves'] ?? 0) ?> élèves</span>
            </li>
            <?php endforeach; ?>
          </ul>
        <?php elseif (isset($widget->data['evaluations'])): ?>
          <?php if (empty($widget->data['evaluations'])): ?>
            <p class="text-green-600 text-xs flex items-center gap-1"><i data-lucide="check-circle" class="w-3 h-3"></i> Tout est à jour</p>
          <?php else: ?>
            <p class="text-amber-600 font-semibold text-lg"><?= (int)$widget->data['total'] ?> <span class="text-sm font-normal text-slate-500">à noter</span></p>
          <?php endif; ?>
        <?php else: ?>
          <p class="text-slate-400 text-xs">Widget disponible</p>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
