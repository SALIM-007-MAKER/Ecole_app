<?php

namespace App\Modules\Academique\Contracts;

use App\Modules\Academique\DTO\BulletinData;

/**
 * Contrat du moteur de génération de bulletins.
 *
 * Toute implémentation doit respecter les règles d'or :
 *   • Aucun calcul de moyenne — déléguer à AcademicCalculationService
 *   • Aucun classement — déléguer à RankingEngine
 *   • Journaliser via AuditService
 *   • Vérifier les permissions via BulletinPolicy
 */
interface BulletinGeneratorInterface
{
    /**
     * Génère et persiste le bulletin d'un élève pour une période.
     * Dispatch BulletinGenerated.
     */
    public function genererBulletin(int $eleveId, int $periodeId, int $userId): BulletinData;

    /**
     * Génère les bulletins de toute une classe en un seul passage.
     * Optimisé : le classement est calculé une seule fois pour la classe entière.
     *
     * @return BulletinData[]
     */
    public function genererBulletinsClasse(int $classeId, int $periodeId, int $userId): array;

    /**
     * Prévisualise le bulletin sans persistance ni événements.
     */
    public function previewBulletin(int $eleveId, int $periodeId): BulletinData;

    /**
     * Passe un bulletin de 'brouillon' à 'publie'.
     * Dispatch BulletinPublished.
     */
    public function publierBulletin(string $verificationToken, int $userId): BulletinData;

    /**
     * Archive un bulletin publié.
     * Dispatch BulletinArchived.
     */
    public function archiverBulletin(string $verificationToken, int $userId): BulletinData;

    /**
     * Exporte le bulletin en HTML prêt pour impression/PDF.
     */
    public function exportHtml(BulletinData $bulletin): string;

    /**
     * Sérialise le bulletin pour les futures API REST.
     */
    public function toApiPayload(BulletinData $bulletin): array;
}
