<?php /** @var \App\Modules\Portals\DTOs\DashboardDTO $dashboard */ ?>
<div class="space-y-6">

  <!-- Liens rapides élève -->
  <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-7 gap-3">
    <?php foreach ([
      ['Emploi du temps','calendar','eleve/emploi-du-temps','text-blue-600 bg-blue-50'],
      ['Mes notes','star','eleve/notes','text-amber-600 bg-amber-50'],
      ['Bulletins','file-text','eleve/bulletins','text-green-600 bg-green-50'],
      ['Absences','user-x','eleve/absences','text-red-600 bg-red-50'],
      ['Bibliothèque','book','eleve/bibliotheque','text-purple-600 bg-purple-50'],
      ['Messagerie','mail','eleve/messagerie','text-teal-600 bg-teal-50'],
      ['Mon profil','user','eleve/profil','text-slate-600 bg-slate-50'],
    ] as [$label, $icon, $path, $colors]): ?>
    <a href="/v2/portals/<?= $path ?>"
       class="flex flex-col items-center gap-2 p-3 rounded-xl border border-slate-200 hover:shadow-sm transition-shadow <?= $colors ?>">
      <i data-lucide="<?= $icon ?>" class="w-5 h-5"></i>
      <span class="text-xs font-medium text-center leading-tight"><?= $label ?></span>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Widgets élève -->
  <?php if (!empty($dashboard->widgets)): ?>
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($dashboard->widgets as $widget): ?>
    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm
                <?= in_array($widget->taille, ['lg','xl']) ? 'md:col-span-2' : '' ?>">
      <h3 class="text-sm font-semibold text-slate-700 flex items-center gap-2 mb-3">
        <i data-lucide="<?= htmlspecialchars($widget->icon) ?>" class="w-4 h-4 text-blue-500"></i>
        <?= htmlspecialchars($widget->titre) ?>
      </h3>
      <?php if (!empty($widget->data)): ?>
      <div class="text-sm text-slate-600">
        <?php if (isset($widget->data['notes'])): ?>
          <ul class="space-y-1.5">
            <?php foreach (array_slice((array)$widget->data['notes'], 0, 4) as $n): ?>
            <li class="flex items-center justify-between text-xs">
              <span class="text-slate-600 truncate"><?= htmlspecialchars($n['matiere_nom'] ?? '') ?></span>
              <span class="font-bold text-blue-700 ml-2"><?= number_format((float)($n['valeur'] ?? 0), 1) ?>/<?= (int)($n['bareme'] ?? 20) ?></span>
            </li>
            <?php endforeach; ?>
          </ul>
        <?php elseif (isset($widget->data['aujourd_hui'])): ?>
          <?php if (empty($widget->data['aujourd_hui'])): ?>
            <p class="text-slate-400 text-xs">Pas de cours aujourd'hui</p>
          <?php else: ?>
            <ul class="space-y-1">
              <?php foreach ($widget->data['aujourd_hui'] as $cr): ?>
              <li class="text-xs bg-blue-50 rounded px-2 py-1 flex items-center gap-2">
                <span class="text-blue-600 font-medium"><?= htmlspecialchars($cr['heure_debut'] ?? '') ?></span>
                <span><?= htmlspecialchars($cr['matiere_nom'] ?? '') ?></span>
              </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        <?php elseif (isset($widget->data['total']) && isset($widget->data['justifiees'])): ?>
          <div class="flex items-baseline gap-1">
            <span class="text-2xl font-bold text-slate-800"><?= (int)$widget->data['total'] ?></span>
            <span class="text-xs text-slate-400">absences</span>
          </div>
          <p class="text-xs text-red-500"><?= (int)($widget->data['non_justifiees'] ?? 0) ?> non justifiée(s)</p>
        <?php elseif (isset($widget->data['disponible'])): ?>
          <?php if ($widget->data['disponible'] && $widget->data['bulletin']): ?>
            <div class="text-center">
              <p class="text-3xl font-bold text-blue-600"><?= number_format((float)($widget->data['bulletin']['moyenne_generale'] ?? 0), 2) ?></p>
              <p class="text-xs text-slate-400 mt-1">Moy. générale — <?= htmlspecialchars($widget->data['bulletin']['mention'] ?? '') ?></p>
              <a href="/v2/portals/eleve/bulletins" class="mt-2 inline-block text-xs text-blue-600 hover:underline">Voir le bulletin</a>
            </div>
          <?php else: ?>
            <p class="text-xs text-slate-400">Bulletin non disponible</p>
          <?php endif; ?>
        <?php else: ?>
          <p class="text-slate-400 text-xs">—</p>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
