<?php
declare(strict_types=1);

namespace App\Modules\Portals\Framework;

use App\Modules\Portals\DTO\ShortcutDTO;
use App\Modules\Portals\Repositories\PreferencesRepository;

class ShortcutEngine
{
    private const DEFAULTS = [
        'admin' => [
            ['id' => 1, 'label' => 'Utilisateurs',  'url' => '/utilisateurs',                  'icon' => 'users',       'color' => 'violet', 'ordre' => 1],
            ['id' => 2, 'label' => 'Paramètres',    'url' => '/v2/portals/admin/parametres',   'icon' => 'settings',    'color' => 'slate',  'ordre' => 2],
            ['id' => 3, 'label' => 'Journal audit', 'url' => '/v2/portals/admin/audit',        'icon' => 'list',        'color' => 'gray',   'ordre' => 3],
            ['id' => 4, 'label' => 'Modules',       'url' => '/v2/portals/admin/modules',      'icon' => 'layers',      'color' => 'blue',   'ordre' => 4],
        ],
        'direction' => [
            ['id' => 1, 'label' => 'Rapports',      'url' => '/v2/portals/direction/rapports',  'icon' => 'bar-chart-2', 'color' => 'indigo',  'ordre' => 1],
            ['id' => 2, 'label' => 'Bulletins',     'url' => '/v2/portals/direction/academique','icon' => 'file-text',   'color' => 'teal',    'ordre' => 2],
            ['id' => 3, 'label' => 'Effectifs',     'url' => '/v2/portals/direction/scolarite', 'icon' => 'users',       'color' => 'violet',  'ordre' => 3],
            ['id' => 4, 'label' => 'Finance',       'url' => '/v2/portals/direction/finance',   'icon' => 'dollar-sign', 'color' => 'amber',   'ordre' => 4],
        ],
        'enseignant' => [
            ['id' => 1, 'label' => 'Saisir notes',  'url' => '/v2/portals/enseignant/notes',           'icon' => 'edit',       'color' => 'teal',   'ordre' => 1],
            ['id' => 2, 'label' => 'Faire appel',   'url' => '/v2/portals/enseignant/appel',           'icon' => 'user-check', 'color' => 'green',  'ordre' => 2],
            ['id' => 3, 'label' => 'Mon EDT',       'url' => '/v2/portals/enseignant/emploi-du-temps', 'icon' => 'calendar',   'color' => 'blue',   'ordre' => 3],
            ['id' => 4, 'label' => 'Mes classes',   'url' => '/v2/portals/enseignant/mes-classes',     'icon' => 'book-open',  'color' => 'violet', 'ordre' => 4],
        ],
        'eleve' => [
            ['id' => 1, 'label' => 'Mes notes',     'url' => '/v2/portals/eleve/notes',           'icon' => 'bar-chart', 'color' => 'blue',   'ordre' => 1],
            ['id' => 2, 'label' => 'Mon EDT',       'url' => '/v2/portals/eleve/emploi-du-temps', 'icon' => 'calendar',  'color' => 'teal',   'ordre' => 2],
            ['id' => 3, 'label' => 'Bibliothèque',  'url' => '/v2/portals/eleve/bibliotheque',    'icon' => 'book',      'color' => 'amber',  'ordre' => 3],
            ['id' => 4, 'label' => 'Messagerie',    'url' => '/v2/portals/eleve/messagerie',      'icon' => 'mail',      'color' => 'violet', 'ordre' => 4],
        ],
        'parent' => [
            ['id' => 1, 'label' => 'Mon enfant',    'url' => '/v2/portals/parent/enfants',     'icon' => 'bar-chart',   'color' => 'emerald', 'ordre' => 1],
            ['id' => 2, 'label' => 'Absences',      'url' => '/v2/portals/parent/absences',    'icon' => 'user-x',      'color' => 'red',     'ordre' => 2],
            ['id' => 3, 'label' => 'Paiements',     'url' => '/v2/portals/parent/paiements',   'icon' => 'credit-card', 'color' => 'amber',   'ordre' => 3],
            ['id' => 4, 'label' => 'Messagerie',    'url' => '/v2/portals/parent/messagerie',  'icon' => 'mail',        'color' => 'violet',  'ordre' => 4],
        ],
        'comptabilite' => [
            ['id' => 1, 'label' => 'Nouvelle facture', 'url' => '/v2/finance/factures/create',         'icon' => 'file-plus',   'color' => 'amber',  'ordre' => 1],
            ['id' => 2, 'label' => 'Encaissement',     'url' => '/v2/finance/paiements/create',        'icon' => 'dollar-sign', 'color' => 'green',  'ordre' => 2],
            ['id' => 3, 'label' => 'Caisse du jour',   'url' => '/v2/portals/comptabilite/caisse',     'icon' => 'database',    'color' => 'teal',   'ordre' => 3],
            ['id' => 4, 'label' => 'Rapports fin.',    'url' => '/v2/portals/comptabilite/rapports',   'icon' => 'bar-chart-2', 'color' => 'indigo', 'ordre' => 4],
        ],
        'rh' => [
            ['id' => 1, 'label' => 'Pointage',       'url' => '/v2/portals/rh/presences',    'icon' => 'user-check',  'color' => 'rose',   'ordre' => 1],
            ['id' => 2, 'label' => 'Valider congés', 'url' => '/v2/portals/rh/conges',       'icon' => 'check-circle','color' => 'green',  'ordre' => 2],
            ['id' => 3, 'label' => 'Contrats',       'url' => '/v2/portals/rh/contrats',     'icon' => 'file-text',   'color' => 'violet', 'ordre' => 3],
            ['id' => 4, 'label' => 'Formations',     'url' => '/v2/portals/rh/formations',   'icon' => 'book-open',   'color' => 'teal',   'ordre' => 4],
        ],
    ];

    public function __construct(
        private readonly PreferencesRepository $repo,
    ) {}

    /** @return ShortcutDTO[] */
    public function getDefaults(string $portal): array
    {
        return array_map(
            fn($d) => ShortcutDTO::fromArray($d),
            self::DEFAULTS[$portal] ?? []
        );
    }

    /** @return ShortcutDTO[] */
    public function getUserShortcuts(int $userId, string $portal, int $etablissementId): array
    {
        $row = $this->repo->find($userId, $portal, $etablissementId);
        if ($row === null || empty($row['shortcuts'])) {
            return $this->getDefaults($portal);
        }
        $raw = json_decode($row['shortcuts'], true) ?? [];
        if (empty($raw)) {
            return $this->getDefaults($portal);
        }
        return array_map(fn($d) => ShortcutDTO::fromArray($d), $raw);
    }

    /** @param ShortcutDTO[] $shortcuts */
    public function saveShortcuts(int $userId, string $portal, int $etablissementId, array $shortcuts): void
    {
        $raw = array_map(fn(ShortcutDTO $s) => $s->toArray(), $shortcuts);
        $this->repo->upsertField($userId, $portal, $etablissementId, 'shortcuts', json_encode($raw));
    }
}
