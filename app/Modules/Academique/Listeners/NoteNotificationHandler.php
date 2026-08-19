<?php

declare(strict_types=1);

namespace App\Modules\Academique\Listeners;

use App\Models\EleveModel;
use App\Services\NotificationService;
use Core\Database;
use Core\Event;
use Core\Listener;

/**
 * Notifie élève + parent quand des notes sont publiées (moteur V2).
 *
 * Comble le vide laissé par la migration de la saisie de notes vers
 * Academique\Controllers\NoteController : en V1, NoteController::storeSaisie()
 * dispatchait NoteAjoutee, écouté par App\Listeners\NotificationHandler
 * (notifie si periodePubliee=true). Une fois V1 déserté, NoteAjoutee cesse
 * d'être émis — sans ce listener, la notification "vos résultats sont
 * disponibles" disparaîtrait silencieusement.
 *
 * L'équivalent V2 naturel de "periodePubliee" est l'action explicite de
 * publication (NoteController::publier() → NotePublished), pas chaque note
 * individuelle — un seul envoi groupé par publication plutôt qu'un envoi par
 * note comme en V1, cf. NoteAjoutee::isBatch().
 *
 * Le pendant "notifier quand LE BULLETIN complet est publié" existe déjà
 * indépendamment via BulletinPublished → CrossModuleListener → 'bulletin_publie'
 * (app/Modules/Communication) — non touché ici, chantier distinct.
 *
 * Réutilise App\Services\NotificationService::notify() (canal interne/email/SMS
 * déjà robuste, aucune notification "maison" réinventée) et EleveModel::
 * findById() (eleves.user_id — FK fiable, cf. migration M007 — pas de
 * résolution par email).
 */
class NoteNotificationHandler implements Listener
{
    private NotificationService $notif;
    private EleveModel          $eleveModel;
    private \PDO                $db;

    public function __construct()
    {
        $this->notif      = new NotificationService();
        $this->eleveModel = new EleveModel();
        $this->db         = Database::getInstance()->getConnection();
    }

    public function handle(Event $event): void
    {
        if ($event instanceof \App\Modules\Academique\Events\NotePublished) {
            $this->onPublished($event);
        }
    }

    private function onPublished(\App\Modules\Academique\Events\NotePublished $e): void
    {
        if ($e->count <= 0) {
            return;
        }

        $stmt = $this->db->prepare(
            "SELECT DISTINCT n.eleve_id, m.nom AS matiere_nom, ev.libelle AS evaluation_libelle
               FROM notes_v2 n
               JOIN evaluations ev ON ev.id = n.evaluation_id
               JOIN matieres m     ON m.id = ev.matiere_id
              WHERE n.evaluation_id = :evaluation_id
                AND n.valeur IS NOT NULL"
        );
        $stmt->execute([':evaluation_id' => $e->evaluationId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_OBJ);

        foreach ($rows as $row) {
            $eleve = $this->eleveModel->findById((int)$row->eleve_id);
            if (!$eleve) {
                continue;
            }

            $titre = "Nouvelle note — {$row->matiere_nom}";
            $msg   = "Une nouvelle note en {$row->matiere_nom} ({$row->evaluation_libelle}) "
                   . "a été publiée pour {$eleve->prenom} {$eleve->nom}.";

            if (!empty($eleve->parent_id)) {
                $this->notif->notify((int)$eleve->parent_id, 'note', $titre, $msg, BASE_URL . '/parent/notes');
            }
            if (!empty($eleve->user_id)) {
                $this->notif->notify((int)$eleve->user_id, 'note', $titre, $msg, BASE_URL . '/eleve/notes');
            }
        }
    }
}
