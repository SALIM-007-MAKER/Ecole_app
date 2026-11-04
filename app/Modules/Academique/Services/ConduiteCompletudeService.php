<?php

namespace App\Modules\Academique\Services;

use Core\Database;
use PDO;

/**
 * Complétude de la Conduite d'une classe pour une période (règle stricte A1/A2 et R2).
 *
 * Une classe est « complète » quand, pour CHAQUE évaluation de Conduite active (statut publiée ou verrouillée — les
 * seules prises en compte par le calcul), CHAQUE élève actif (eleves.actif = 1) possède une note VALIDE :
 * valeur non nulle, case « absent » non cochée, valeur comprise entre 0 et la note maximale (zéro est valide).
 * Les doublons historiques (plusieurs évaluations de Conduite actives) sont tous exigés ; jamais archivés ici.
 *
 * Sans matière Conduite marquée (matieres.est_conduite = 1) dans l'établissement de la classe, aucun contrôle ne s'applique.
 *
 * Avec $verrouiller = true, les trois lectures prennent des verrous partagés (LOCK IN SHARE MODE) dans l'ORDRE
 * évaluations → élèves → notes, sur les index de plage (idx_ev_classe_per, idx_classe, uq_note_eval_eleve) : sous
 * REPEATABLE READ, cela empêche toute écriture concurrente qui changerait le résultat (insertion de la note manquante,
 * modification d'une note, désactivation ou arrivée d'un élève, archivage ou création d'une évaluation de Conduite).
 * Ces verrous ne sont valables qu'à l'intérieur d'une transaction REPEATABLE READ (cf. BulletinGenerator::publierBulletin).
 */
class ConduiteCompletudeService
{
    public const RAISON_NON_CONCERNE      = 'non_concerne';
    public const RAISON_OK                = 'ok';
    public const RAISON_AUCUNE_EVALUATION = 'aucune_evaluation';
    public const RAISON_NON_PUBLIEE       = 'evaluation_non_publiee';
    public const RAISON_NOTES_MANQUANTES  = 'notes_manquantes';

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    /**
     * @return array{
     *   concerne:bool, complete:bool, raison:string, message:string, matiere_id:?int,
     *   evaluations:array<int,array{id:int,libelle:string,note_max:float,coefficient:float}>,
     *   manquants:array<int,array{eleve_id:int,nom:string,prenom:string,evaluation_id:int,evaluation:string}>
     * }
     */
    public function etatClasse(int $classeId, int $periodeId, bool $verrouiller = false): array
    {
        $lock = $verrouiller ? ' LOCK IN SHARE MODE' : '';

        $stmt = $this->db->prepare(
            "SELECT m.id FROM matieres m JOIN classes c ON c.etablissement_id = m.etablissement_id
              WHERE c.id = ? AND m.est_conduite = 1 LIMIT 1"
        );
        $stmt->execute([$classeId]);
        $matiereId = $stmt->fetchColumn();
        if ($matiereId === false) {
            return $this->resultat(false, true, self::RAISON_NON_CONCERNE, 'Aucune matière de Conduite dans cet établissement.', null, [], []);
        }
        $matiereId = (int)$matiereId;

        // 1) évaluations de Conduite actives de la classe et de la période (verrou de plage sur idx_ev_classe_per)
        $stmt = $this->db->prepare(
            "SELECT id, libelle, note_max, coefficient FROM evaluations
              WHERE classe_id = ? AND periode_scolaire_id = ? AND matiere_id = ? AND statut IN ('publiee','verrouillee')
              ORDER BY id{$lock}"
        );
        $stmt->execute([$classeId, $periodeId, $matiereId]);
        $evaluations = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $e) {
            $evaluations[(int)$e['id']] = [
                'id' => (int)$e['id'], 'libelle' => (string)$e['libelle'],
                'note_max' => (float)$e['note_max'], 'coefficient' => (float)$e['coefficient'],
            ];
        }

        // 2) élèves actifs de la classe (verrou de plage sur idx_classe)
        $stmt = $this->db->prepare("SELECT id, nom, prenom FROM eleves WHERE classe_id = ? AND actif = 1 ORDER BY nom, prenom, id{$lock}");
        $stmt->execute([$classeId]);
        $eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($evaluations === []) {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM evaluations
                  WHERE classe_id = ? AND periode_scolaire_id = ? AND matiere_id = ? AND statut = 'brouillon'"
            );
            $stmt->execute([$classeId, $periodeId, $matiereId]);
            $brouillons = (int)$stmt->fetchColumn();
            return $brouillons > 0
                ? $this->resultat(true, false, self::RAISON_NON_PUBLIEE, 'Évaluation de Conduite non publiée : publiez-la pour cette période.', $matiereId, [], [])
                : $this->resultat(true, false, self::RAISON_AUCUNE_EVALUATION, 'Aucune évaluation de Conduite pour cette classe et cette période.', $matiereId, [], []);
        }

        // 3) notes existantes (verrou sur uq_note_eval_eleve)
        $ids = implode(',', array_map('intval', array_keys($evaluations)));
        $notes = $this->db->query(
            "SELECT evaluation_id, eleve_id, valeur, est_absent FROM notes_v2 WHERE evaluation_id IN ({$ids}){$lock}"
        )->fetchAll(PDO::FETCH_ASSOC);
        $valides = [];
        foreach ($notes as $n) {
            $ev = $evaluations[(int)$n['evaluation_id']] ?? null;
            if ($ev === null || $n['valeur'] === null || (int)$n['est_absent'] === 1) { continue; }
            $v = (float)$n['valeur'];
            if ($v >= 0.0 && $v <= $ev['note_max']) { $valides[$n['evaluation_id'] . '-' . $n['eleve_id']] = true; }
        }

        $manquants = [];
        foreach ($evaluations as $ev) {
            foreach ($eleves as $el) {
                if (!isset($valides[$ev['id'] . '-' . $el['id']])) {
                    $manquants[] = [
                        'eleve_id' => (int)$el['id'], 'nom' => (string)$el['nom'], 'prenom' => (string)$el['prenom'],
                        'evaluation_id' => $ev['id'], 'evaluation' => $ev['libelle'],
                    ];
                }
            }
        }

        if ($manquants === []) {
            return $this->resultat(true, true, self::RAISON_OK, 'Conduite complète.', $matiereId, $evaluations, []);
        }
        $noms = array_unique(array_map(fn($m) => trim($m['prenom'] . ' ' . $m['nom']), $manquants));
        return $this->resultat(
            true, false, self::RAISON_NOTES_MANQUANTES,
            'Note de Conduite manquante pour ' . count($noms) . ' élève(s) : ' . implode(', ', array_slice($noms, 0, 10))
                . (count($noms) > 10 ? '…' : '') . '.',
            $matiereId, $evaluations, $manquants
        );
    }

    /** L'élève donné figure-t-il parmi les manquants ? (indication d'affichage « Non saisie ») */
    public function eleveManquant(array $etat, int $eleveId): bool
    {
        foreach ($etat['manquants'] as $m) {
            if ($m['eleve_id'] === $eleveId) { return true; }
        }
        return false;
    }

    private function resultat(bool $concerne, bool $complete, string $raison, string $message, ?int $matiereId, array $evaluations, array $manquants): array
    {
        return [
            'concerne' => $concerne, 'complete' => $complete, 'raison' => $raison, 'message' => $message,
            'matiere_id' => $matiereId, 'evaluations' => $evaluations, 'manquants' => $manquants,
        ];
    }
}
