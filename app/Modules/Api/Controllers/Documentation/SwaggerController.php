<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\Documentation;

use App\Modules\Api\Documentation\OpenApiGenerator;
use Core\Controller;

class SwaggerController extends Controller
{
    /** GET /api/docs — Swagger UI */
    public function ui(): void
    {
        echo $this->renderSwaggerUi();
        exit;
    }

    /** GET /api/docs/openapi.json — spec OpenAPI 3.1 */
    public function spec(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        echo json_encode(OpenApiGenerator::generate(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function renderSwaggerUi(): string
    {
        $specUrl = '/api/docs/openapi.json';
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>EcoleApp API — Documentation</title>
  <style>
    body { margin:0; padding:0; font-family: -apple-system, sans-serif; }
    #swagger-ui { max-width: 1400px; margin: 0 auto; padding: 20px; }
    .topbar { background: #4f46e5; padding: 12px 24px; display:flex; align-items:center; gap:12px; }
    .topbar h1 { color: white; margin: 0; font-size: 1.2rem; }
    .topbar small { color: #c7d2fe; font-size: 0.85rem; }
    /* Minimal Swagger-UI inline styles */
    .swagger-ui .info { margin: 20px 0; }
    .swagger-ui .opblock-tag { font-size: 1rem; font-weight: 600; }
    .swagger-ui .opblock { border-radius: 6px; margin: 4px 0; }
    .swagger-ui .opblock-get .opblock-summary { background: #e8f4fd; }
    .swagger-ui .opblock-post .opblock-summary { background: #e6f6e6; }
    .swagger-ui .opblock-put .opblock-summary { background: #fff8e6; }
    .swagger-ui .opblock-delete .opblock-summary { background: #fde8e8; }
    pre.schema { background: #f8f8f8; padding: 16px; border-radius: 6px; overflow-x: auto; font-size: 0.85rem; }
    .endpoint { border: 1px solid #e2e8f0; border-radius: 6px; margin: 8px 0; overflow: hidden; }
    .ep-header { padding: 10px 16px; display:flex; align-items:center; gap:12px; cursor:pointer; }
    .ep-header:hover { background: #f8fafc; }
    .method { font-size: 0.75rem; font-weight: 700; padding: 2px 8px; border-radius: 4px; min-width: 52px; text-align:center; }
    .GET    { background: #dbeafe; color: #1d4ed8; }
    .POST   { background: #dcfce7; color: #15803d; }
    .PUT    { background: #fef9c3; color: #854d0e; }
    .DELETE { background: #fee2e2; color: #b91c1c; }
    .PATCH  { background: #ede9fe; color: #6d28d9; }
    .ep-path { font-family: monospace; font-size: 0.9rem; color: #374151; }
    .ep-desc { color: #6b7280; font-size: 0.85rem; margin-left: auto; }
    .section-title { font-size: 1.1rem; font-weight: 700; margin: 28px 0 12px; color: #1e293b;
                     padding-bottom: 6px; border-bottom: 2px solid #e2e8f0; }
  </style>
</head>
<body>
<div class="topbar">
  <h1>EcoleApp API</h1>
  <small>v1.0.0 — OpenAPI 3.1</small>
</div>
<div id="swagger-ui">
  <p style="margin:16px 0; color:#475569;">
    Authentification: <code>Authorization: Bearer &lt;token&gt;</code> ou <code>X-API-Key: sk_live_xxx</code>
    — <a href="{$specUrl}" target="_blank">Télécharger openapi.json</a>
  </p>

  <div class="section-title">Authentification</div>
  <div class="endpoint"><div class="ep-header"><span class="method POST">POST</span><span class="ep-path">/api/v1/auth/login</span><span class="ep-desc">Obtenir access_token + refresh_token</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method POST">POST</span><span class="ep-path">/api/v1/auth/refresh</span><span class="ep-desc">Rotation du refresh token</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method POST">POST</span><span class="ep-path">/api/v1/auth/logout</span><span class="ep-desc">Révocation de tous les tokens</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/api-keys</span><span class="ep-desc">Lister les clés API</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method POST">POST</span><span class="ep-path">/api/v1/api-keys</span><span class="ep-desc">Créer une clé API</span></div></div>

  <div class="section-title">Scolarité</div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/eleves</span><span class="ep-desc">Liste paginée (filter, sort, search)</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method POST">POST</span><span class="ep-path">/api/v1/eleves</span><span class="ep-desc">Créer un élève</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/eleves/{id}</span><span class="ep-desc">Détail élève</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method PUT">PUT</span><span class="ep-path">/api/v1/eleves/{id}</span><span class="ep-desc">Modifier un élève</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method DELETE">DELETE</span><span class="ep-path">/api/v1/eleves/{id}</span><span class="ep-desc">Archiver (soft delete)</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/classes</span><span class="ep-desc">Liste des classes</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/classes/{id}/eleves</span><span class="ep-desc">Élèves d'une classe</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/inscriptions</span><span class="ep-desc">Inscriptions</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/matieres</span><span class="ep-desc">Référentiel matières</span></div></div>

  <div class="section-title">Académique</div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/notes</span><span class="ep-desc">Notes (filtrable par élève/matière/période)</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method POST">POST</span><span class="ep-path">/api/v1/notes/batch</span><span class="ep-desc">Saisie en masse de notes</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/bulletins</span><span class="ep-desc">Bulletins scolaires</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/classements/classe/{id}</span><span class="ep-desc">Classement par classe</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/classements/global</span><span class="ep-desc">Classement général</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/periodes</span><span class="ep-desc">Périodes scolaires</span></div></div>

  <div class="section-title">Finance</div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/factures</span><span class="ep-desc">Factures (filtrable statut)</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/factures/impayes</span><span class="ep-desc">Résumé impayés</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/paiements</span><span class="ep-desc">Paiements encaissés</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/paiements/stats</span><span class="ep-desc">CA mensuel</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/caisse/solde</span><span class="ep-desc">Solde caisse</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/caisse/mouvements</span><span class="ep-desc">Mouvements caisse</span></div></div>

  <div class="section-title">Vie Scolaire</div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/absences</span><span class="ep-desc">Absences</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/absences/stats</span><span class="ep-desc">Statistiques absences</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/emplois-du-temps</span><span class="ep-desc">Grille EDT par classe/enseignant</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/activites</span><span class="ep-desc">Activités scolaires</span></div></div>

  <div class="section-title">RH</div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/employes</span><span class="ep-desc">Employés</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/conges</span><span class="ep-desc">Demandes de congés</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/formations</span><span class="ep-desc">Formations RH</span></div></div>

  <div class="section-title">Documents & Bibliothèque</div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/documents</span><span class="ep-desc">Documents</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/documents/{id}/download</span><span class="ep-desc">URL de téléchargement signée</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method POST">POST</span><span class="ep-path">/api/v1/upload</span><span class="ep-desc">Upload fichier (multipart)</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/livres</span><span class="ep-desc">Catalogue bibliothèque</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/emprunts</span><span class="ep-desc">Emprunts actifs</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/emprunts/en-retard</span><span class="ep-desc">Emprunts en retard</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/articles</span><span class="ep-desc">Inventaire articles</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/articles/alertes-stock</span><span class="ep-desc">Articles sous seuil</span></div></div>

  <div class="section-title">Rapports & BI</div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/rapports</span><span class="ep-desc">Liste des rapports</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/rapports/dashboard</span><span class="ep-desc">Métriques agrégées globales</span></div></div>

  <div class="section-title">Webhooks & Infra</div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/webhooks</span><span class="ep-desc">Subscriptions webhook</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method POST">POST</span><span class="ep-path">/api/v1/webhooks</span><span class="ep-desc">Créer une subscription</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method DELETE">DELETE</span><span class="ep-path">/api/v1/webhooks/{id}</span><span class="ep-desc">Désactiver un webhook</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/webhooks/{id}/deliveries</span><span class="ep-desc">Historique livraisons</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/search</span><span class="ep-desc">Recherche globale multi-types</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/notifications</span><span class="ep-desc">Notifications utilisateur</span></div></div>
  <div class="endpoint"><div class="ep-header"><span class="method GET">GET</span><span class="ep-path">/api/v1/health</span><span class="ep-desc">Health check</span></div></div>
</div>
</body>
</html>
HTML;
    }
}
