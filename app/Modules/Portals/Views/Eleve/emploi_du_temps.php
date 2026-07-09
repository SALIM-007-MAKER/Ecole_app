<?php /** @var array $creneaux, int $today */ ?>
<div class="space-y-4">
  <?php
  $jours = [1=>'Lundi',2=>'Mardi',3=>'Mercredi',4=>'Jeudi',5=>'Vendredi',6=>'Samedi'];
  $byJour = [];
  foreach ($creneaux as $cr) { $byJour[(int)$cr['jour']][] = $cr; }
  ?>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($jours as $num => $nom): ?>
    <div class="bg-white border <?= $today === $num ? 'border-blue-400 ring-1 ring-blue-400' : 'border-slate-200' ?> rounded-xl p-4">
      <h3 class="font-semibold text-sm <?= $today === $num ? 'text-blue-700' : 'text-slate-700' ?> mb-3"><?= $nom ?></h3>
      <?php if (empty($byJour[$num])): ?>
        <p class="text-xs text-slate-300">Pas de cours</p>
      <?php else: ?>
        <ul class="space-y-2">
          <?php foreach ($byJour[$num] as $cr): ?>
          <li class="text-xs bg-blue-50 rounded-lg p-2">
            <div class="flex items-center justify-between">
              <span class="font-medium text-blue-700"><?= htmlspecialchars($cr['heure_debut'] ?? '') ?>-<?= htmlspecialchars($cr['heure_fin'] ?? '') ?></span>
              <span class="text-slate-400"><?= htmlspecialchars($cr['salle_nom'] ?? '') ?></span>
            </div>
            <p class="text-slate-700 mt-1"><?= htmlspecialchars($cr['matiere_nom'] ?? '') ?></p>
            <p class="text-slate-400"><?= htmlspecialchars($cr['enseignant_nom'] ?? '') ?></p>
          </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
