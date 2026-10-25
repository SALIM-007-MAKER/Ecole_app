<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/**
 * Données de test pour les bulletins : classe, élève et bulletin à statut / version de jeton choisis.
 * À utiliser DANS une transaction annulée à la fin du test (aucune donnée ne doit subsister).
 */
final class BulletinFixtures
{
    public int $classeId = 0;
    private int $n = 0;
    private string $uniq;

    public function __construct(private readonly PDO $pdo, private readonly int $etablissementId = 1)
    {
        $this->uniq = substr(md5((string)microtime(true) . random_int(0, PHP_INT_MAX)), 0, 8);
        $this->pdo->prepare("INSERT INTO classes (etablissement_id, nom, niveau, annee_scolaire) VALUES (?, ?, 'Test', '2099-2100')")
            ->execute([$this->etablissementId, "BF_{$this->uniq}"]);
        $this->classeId = (int)$this->pdo->lastInsertId();
    }

    /** Un élève de test (nom reconnaissable pour vérifier qu'il ne fuit jamais dans une réponse publique). */
    public function eleve(string $nom = 'SECRETNOM'): int
    {
        $this->n++;
        $this->pdo->prepare("INSERT INTO eleves (etablissement_id, matricule, nom, prenom, date_naissance, sexe, classe_id, actif)
                             VALUES (?, ?, ?, 'Prenom', '2010-01-01', 'M', ?, 1)")
            ->execute([$this->etablissementId, substr("BF-{$this->n}-{$this->uniq}", 0, 20), $nom . $this->n, $this->classeId]);
        return (int)$this->pdo->lastInsertId();
    }

    /** Première période existante (les bulletins de test sont rattachés à des élèves de test, donc sans conflit). */
    public function periode(): int
    {
        return (int)$this->pdo->query("SELECT id FROM periodes_scolaires ORDER BY id LIMIT 1")->fetchColumn();
    }

    /**
     * Insère un bulletin. $dataJson : modèle de snapshot (le jeton y est remplacé par $token).
     *
     * @return array{id:int, eleve_id:int, periode_id:int, token:string}
     */
    public function bulletin(int $eleveId, int $periodeId, string $token, string $statut = 'brouillon', int $version = 2, ?string $dataJsonModele = null): array
    {
        $modele = $dataJsonModele ?? (string)$this->pdo->query("SELECT data_json FROM bulletins_v2 ORDER BY id LIMIT 1")->fetchColumn();
        $json = json_decode($modele, true) ?: [];
        $json['verification_token'] = $token;
        $json['qr_code_url'] = '';
        $json['statut'] = $statut;
        $json['eleve_id'] = $eleveId;
        $json['periode_id'] = $periodeId;
        $json['classe_id'] = $this->classeId;
        $this->pdo->prepare("INSERT INTO bulletins_v2 (eleve_id, classe_id, periode_id, verification_token, token_version, moyenne, rang, nb_eleves, mention_code, decision, statut, data_json, generated_by, generated_at)
                             VALUES (?, ?, ?, ?, ?, 12.34, 1, 1, 'AB', 'admis', ?, ?, 1, NOW())")
            ->execute([$eleveId, $this->classeId, $periodeId, $token, $version, $statut, json_encode($json, JSON_UNESCAPED_UNICODE)]);
        return ['id' => (int)$this->pdo->lastInsertId(), 'eleve_id' => $eleveId, 'periode_id' => $periodeId, 'token' => $token];
    }
}
