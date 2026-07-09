<?php
declare(strict_types=1);

namespace App\Modules\Api\Documentation;

/**
 * Génère le document OpenAPI 3.1 statiquement.
 * Pas de parsing de code — le schéma est déclaré ici explicitement.
 */
final class OpenApiGenerator
{
    public static function generate(): array
    {
        return [
            'openapi' => '3.1.0',
            'info'    => [
                'title'       => 'EcoleApp API',
                'version'     => '1.0.0',
                'description' => 'API RESTful V1 — Plateforme de gestion scolaire complète.',
                'contact'     => ['email' => 'api@ecoleapp.sn'],
            ],
            'servers' => [
                ['url' => '/api/v1', 'description' => 'Production'],
            ],
            'security' => [['BearerAuth' => []], ['ApiKey' => []]],
            'components' => [
                'securitySchemes' => [
                    'BearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT'],
                    'ApiKey'     => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-API-Key'],
                ],
                'schemas' => self::schemas(),
                'responses' => [
                    'Unauthorized'   => ['description' => '401 — Token absent ou invalide.'],
                    'Forbidden'      => ['description' => '403 — Permission insuffisante.'],
                    'NotFound'       => ['description' => '404 — Ressource introuvable.'],
                    'Validation'     => ['description' => '422 — Données invalides.'],
                    'RateLimit'      => ['description' => '429 — Trop de requêtes.'],
                ],
            ],
            'tags' => [
                ['name' => 'Auth',        'description' => 'Authentification JWT'],
                ['name' => 'Scolarité',   'description' => 'Élèves, classes, inscriptions, matières'],
                ['name' => 'Académique',  'description' => 'Notes, bulletins, classements, périodes'],
                ['name' => 'Finance',     'description' => 'Factures, paiements, caisse'],
                ['name' => 'Vie Scolaire','description' => 'Absences, emplois du temps, activités'],
                ['name' => 'RH',          'description' => 'Employés, congés, formations'],
                ['name' => 'Documents',   'description' => 'Gestion documentaire'],
                ['name' => 'Bibliothèque','description' => 'Livres et emprunts'],
                ['name' => 'Inventaire',  'description' => 'Articles et stocks'],
                ['name' => 'Rapports',    'description' => 'Rapports & BI'],
                ['name' => 'Webhooks',    'description' => 'Abonnements webhook'],
                ['name' => 'Search',      'description' => 'Recherche globale'],
            ],
            'paths' => self::paths(),
        ];
    }

    private static function schemas(): array
    {
        return [
            'Eleve' => [
                'type' => 'object',
                'properties' => [
                    'id'              => ['type' => 'integer'],
                    'matricule'       => ['type' => 'string'],
                    'nom'             => ['type' => 'string'],
                    'prenom'          => ['type' => 'string'],
                    'date_naissance'  => ['type' => 'string', 'format' => 'date'],
                    'sexe'            => ['type' => 'string', 'enum' => ['M', 'F']],
                    'statut'          => ['type' => 'string'],
                    'classe'          => ['type' => 'string', 'nullable' => true],
                    'created_at'      => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'Classe' => [
                'type' => 'object',
                'properties' => [
                    'id'             => ['type' => 'integer'],
                    'nom'            => ['type' => 'string'],
                    'niveau'         => ['type' => 'string'],
                    'annee_scolaire' => ['type' => 'string'],
                    'effectif'       => ['type' => 'integer'],
                ],
            ],
            'Note' => [
                'type' => 'object',
                'properties' => [
                    'id'          => ['type' => 'integer'],
                    'valeur'      => ['type' => 'number'],
                    'bareme'      => ['type' => 'number'],
                    'matiere_nom' => ['type' => 'string'],
                    'eleve_nom'   => ['type' => 'string'],
                ],
            ],
            'Facture' => [
                'type' => 'object',
                'properties' => [
                    'id'             => ['type' => 'integer'],
                    'numero'         => ['type' => 'string'],
                    'statut'         => ['type' => 'string'],
                    'montant_total'  => ['type' => 'number'],
                    'montant_paye'   => ['type' => 'number'],
                    'reste_a_payer'  => ['type' => 'number'],
                ],
            ],
            'PaginatedResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean'],
                    'data'    => ['type' => 'array', 'items' => ['type' => 'object']],
                    'meta'    => [
                        'type' => 'object',
                        'properties' => [
                            'total'    => ['type' => 'integer'],
                            'page'     => ['type' => 'integer'],
                            'per_page' => ['type' => 'integer'],
                            'last_page'=> ['type' => 'integer'],
                        ],
                    ],
                    'links' => ['type' => 'object'],
                ],
            ],
            'ErrorResponse' => [
                'type' => 'object',
                'properties' => [
                    'success'    => ['type' => 'boolean', 'example' => false],
                    'error'      => ['type' => 'string'],
                    'error_code' => ['type' => 'string'],
                    'request_id' => ['type' => 'string'],
                ],
            ],
        ];
    }

    private static function paths(): array
    {
        $paths = [];

        // Auth
        $paths['/auth/login'] = [
            'post' => [
                'tags'    => ['Auth'],
                'summary' => 'Connexion — obtenir access + refresh tokens',
                'security'=> [],
                'requestBody' => ['content' => ['application/json' => ['schema' => [
                    'type' => 'object',
                    'required' => ['email', 'password'],
                    'properties' => [
                        'email'    => ['type' => 'string', 'format' => 'email'],
                        'password' => ['type' => 'string'],
                    ],
                ]]]],
                'responses' => [
                    '200' => ['description' => 'Tokens émis'],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                ],
            ],
        ];
        $paths['/auth/refresh'] = ['post' => ['tags' => ['Auth'], 'summary' => 'Rotation du refresh token']];
        $paths['/auth/logout']  = ['post' => ['tags' => ['Auth'], 'summary' => 'Révocation des tokens']];

        // Élèves
        foreach (['' => 'Lister', '/{id}' => 'Détail'] as $suffix => $label) {
            $paths['/eleves' . $suffix] = [
                ($suffix === '' ? 'get' : 'get') => [
                    'tags'    => ['Scolarité'],
                    'summary' => "$label élève(s)",
                    'responses' => ['200' => ['description' => 'OK'], '401' => ['$ref' => '#/components/responses/Unauthorized']],
                ],
            ];
        }
        $paths['/eleves']['post'] = ['tags' => ['Scolarité'], 'summary' => 'Créer un élève'];
        $paths['/eleves/{id}']['put']    = ['tags' => ['Scolarité'], 'summary' => 'Modifier un élève'];
        $paths['/eleves/{id}']['delete'] = ['tags' => ['Scolarité'], 'summary' => 'Archiver un élève'];

        // Autres ressources — déclaration condensée
        $resources = [
            ['tag' => 'Scolarité',    'path' => '/classes',           'desc' => 'Classes'],
            ['tag' => 'Scolarité',    'path' => '/inscriptions',       'desc' => 'Inscriptions'],
            ['tag' => 'Scolarité',    'path' => '/matieres',           'desc' => 'Matières'],
            ['tag' => 'Académique',   'path' => '/periodes',           'desc' => 'Périodes scolaires'],
            ['tag' => 'Académique',   'path' => '/notes',              'desc' => 'Notes'],
            ['tag' => 'Académique',   'path' => '/bulletins',          'desc' => 'Bulletins'],
            ['tag' => 'Finance',      'path' => '/factures',           'desc' => 'Factures'],
            ['tag' => 'Finance',      'path' => '/paiements',          'desc' => 'Paiements'],
            ['tag' => 'Vie Scolaire', 'path' => '/absences',          'desc' => 'Absences'],
            ['tag' => 'Vie Scolaire', 'path' => '/emplois-du-temps',  'desc' => 'Emplois du temps'],
            ['tag' => 'RH',           'path' => '/employes',          'desc' => 'Employés'],
            ['tag' => 'RH',           'path' => '/conges',            'desc' => 'Congés'],
            ['tag' => 'Documents',    'path' => '/documents',          'desc' => 'Documents'],
            ['tag' => 'Bibliothèque', 'path' => '/livres',            'desc' => 'Livres'],
            ['tag' => 'Bibliothèque', 'path' => '/emprunts',          'desc' => 'Emprunts'],
            ['tag' => 'Inventaire',   'path' => '/articles',          'desc' => 'Articles'],
            ['tag' => 'Rapports',     'path' => '/rapports',          'desc' => 'Rapports'],
            ['tag' => 'Webhooks',     'path' => '/webhooks',          'desc' => 'Webhooks'],
        ];

        foreach ($resources as $r) {
            $paths[$r['path']] = [
                'get' => ['tags' => [$r['tag']], 'summary' => 'Lister ' . $r['desc'],
                    'responses' => ['200' => ['description' => 'OK']]],
            ];
            $paths[$r['path'] . '/{id}'] = [
                'get' => ['tags' => [$r['tag']], 'summary' => 'Détail ' . $r['desc'],
                    'responses' => ['200' => ['description' => 'OK'], '404' => ['description' => 'Non trouvé']]],
            ];
        }

        $paths['/search'] = ['get' => ['tags' => ['Search'], 'summary' => 'Recherche globale multi-types',
            'parameters' => [
                ['name' => 'q', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'minLength' => 2]],
                ['name' => 'types', 'in' => 'query', 'schema' => ['type' => 'string']],
            ],
            'responses' => ['200' => ['description' => 'Résultats']]]];

        $paths['/upload'] = ['post' => ['tags' => ['Documents'], 'summary' => 'Upload de fichier',
            'requestBody' => ['content' => ['multipart/form-data' => ['schema' => [
                'properties' => ['file' => ['type' => 'string', 'format' => 'binary']],
            ]]]],
            'responses' => ['200' => ['description' => 'Fichier uploadé']]]];

        return $paths;
    }
}
