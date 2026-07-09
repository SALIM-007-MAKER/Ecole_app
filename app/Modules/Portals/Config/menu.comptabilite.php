<?php
declare(strict_types=1);
return [
    ['id' => 'dashboard',   'label' => 'Tableau de bord',   'icon' => 'layout-dashboard', 'url' => '/v2/portals/comptabilite'],
    ['id' => 'factures',    'label' => 'Factures',          'icon' => 'file-text',        'url' => '/v2/finance/factures',    'perm' => 'finance.factures.view'],
    ['id' => 'paiements',   'label' => 'Encaissements',     'icon' => 'credit-card',      'url' => '/v2/finance/paiements',   'perm' => 'finance.paiements.view'],
    ['id' => 'caisse',      'label' => 'Caisse',            'icon' => 'database',         'url' => '/v2/finance/caisse',      'perm' => 'finance.caisse.view'],
    ['id' => 'comptabilite','label' => 'Comptabilité',      'icon' => 'book',             'url' => '/v2/finance/comptabilite','perm' => 'finance.comptabilite.view', 'sep' => true],
    ['id' => 'rapports_fin','label' => 'Rapports financiers','icon' => 'bar-chart-2',     'url' => '/v2/portals/comptabilite/rapports', 'perm' => 'finance.rapports.view'],
    ['id' => 'impayes',     'label' => 'Impayés',           'icon' => 'alert-circle',     'url' => '/v2/portals/comptabilite/impayes',  'perm' => 'finance.impayes.view'],
];
