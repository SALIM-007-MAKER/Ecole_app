<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Events\DocumentSignatureRequested;
use App\Modules\Documents\Events\DocumentSigned;
use Core\Database;
use Core\EventDispatcher;
use PDO;

/**
 * Stub V3 — Signatures électroniques.
 * Prépare la structure (tables doc_signatures) sans PKI réel.
 * Implémentation complète différée à V3.
 */
class SignatureService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function demanderSignature(int $documentId, array $signataires, int $requestedById): void
    {
        $hash = $this->hashDocument($documentId);

        foreach ($signataires as $sig) {
            $token = bin2hex(random_bytes(32));
            $this->pdo->prepare(
                "INSERT INTO doc_signatures
                    (document_id, signataire_type, signataire_id, signataire_email,
                     signataire_nom, statut, token_signature, hash_document, created_by, expires_at)
                 VALUES (:doc, :type, :uid, :email, :nom, 'en_attente', :token, :hash, :by, :exp)"
            )->execute([
                ':doc'   => $documentId,
                ':type'  => $sig['type'] ?? 'user',
                ':uid'   => $sig['id'] ?? null,
                ':email' => $sig['email'] ?? null,
                ':nom'   => $sig['nom'] ?? null,
                ':token' => $token,
                ':hash'  => $hash,
                ':by'    => $requestedById,
                ':exp'   => date('Y-m-d H:i:s', strtotime('+7 days')),
            ]);
        }

        $this->pdo->prepare(
            "UPDATE doc_documents SET signature_requise=1, signature_statut='en_attente' WHERE id=:id"
        )->execute([':id' => $documentId]);

        EventDispatcher::dispatch(new DocumentSignatureRequested($documentId, $signataires, $requestedById));
    }

    // Paramètre $ip remplacé par $userId — l'IP est capturée depuis $_SERVER
    public function signer(string $token, int $userId): void
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM doc_signatures WHERE token_signature = :token
             AND statut = 'en_attente' AND (expires_at IS NULL OR expires_at > NOW())"
        );
        $st->execute([':token' => $token]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if (!$row) throw new \RuntimeException('Token de signature invalide ou expiré.');

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->pdo->prepare(
            "UPDATE doc_signatures SET statut='signe', signed_at=NOW(), ip_signataire=:ip WHERE id=:id"
        )->execute([':ip' => $ip, ':id' => $row['id']]);

        $this->mettreAJourStatutDocument((int)$row['document_id']);

        EventDispatcher::dispatch(new DocumentSigned(
            (int)$row['document_id'], $userId, date('Y-m-d H:i:s'),
            $this->completionPourcent((int)$row['document_id'])
        ));
    }

    public function rejeter(string $token, ?string $motif, int $userId): void
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM doc_signatures WHERE token_signature = :token AND statut = 'en_attente'"
        );
        $st->execute([':token' => $token]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if (!$row) throw new \RuntimeException('Token invalide ou déjà traité.');

        // 'refuse' = valeur ENUM correcte dans doc_signatures ; motif stocké dans metadata (JSON)
        $this->pdo->prepare(
            "UPDATE doc_signatures SET statut='refuse', signed_at=NOW(), metadata=:meta WHERE id=:id"
        )->execute([':meta' => $motif !== null ? json_encode(['motif' => $motif]) : null, ':id' => $row['id']]);

        $this->mettreAJourStatutDocument((int)$row['document_id']);
    }

    public function signataires(int $documentId): array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM doc_signatures WHERE document_id = :id ORDER BY created_at ASC"
        );
        $st->execute([':id' => $documentId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function verifierStatut(int $documentId): string
    {
        $st = $this->pdo->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(statut='signe') AS signes,
                    SUM(statut='en_attente') AS en_attente
             FROM doc_signatures WHERE document_id = :id"
        );
        $st->execute([':id' => $documentId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);

        if (!$r || (int)$r['total'] === 0) return 'non_requis';
        if ((int)$r['en_attente'] === 0)   return 'complet';
        if ((int)$r['signes'] > 0)         return 'partiel';
        return 'en_attente';
    }

    public function findByToken(string $token): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT s.*, d.titre FROM doc_signatures s
             JOIN doc_documents d ON d.id = s.document_id
             WHERE s.token_signature = :token AND s.statut = 'en_attente'
               AND (s.expires_at IS NULL OR s.expires_at > NOW())"
        );
        $st->execute([':token' => $token]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function hashDocument(int $documentId): string
    {
        $st = $this->pdo->prepare("SELECT chemin_stockage FROM doc_documents WHERE id = :id");
        $st->execute([':id' => $documentId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return '';
        $abs = ROOT_PATH . '/' . $row['chemin_stockage'];
        return is_file($abs) ? hash_file('sha256', $abs) : hash('sha256', (string)$documentId);
    }

    private function completionPourcent(int $documentId): float
    {
        $st = $this->pdo->prepare(
            "SELECT COUNT(*) AS total, SUM(statut='signe') AS signes FROM doc_signatures WHERE document_id=:id"
        );
        $st->execute([':id' => $documentId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r || (int)$r['total'] === 0) return 100.0;
        return round(((int)$r['signes'] / (int)$r['total']) * 100, 1);
    }

    private function mettreAJourStatutDocument(int $documentId): void
    {
        $statut = $this->verifierStatut($documentId);
        $this->pdo->prepare(
            "UPDATE doc_documents SET signature_statut = :statut WHERE id = :id"
        )->execute([':statut' => $statut, ':id' => $documentId]);
    }
}
