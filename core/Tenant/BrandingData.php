<?php

declare(strict_types=1);

namespace Core\Tenant;

/**
 * DTO immuable représentant le branding résolu d'un établissement.
 * Voir MULTI_TENANT_V2_BLUEPRINT.md §9.3, §10.
 */
final class BrandingData
{
    public function __construct(
        public readonly int $etablissementId,
        public readonly ?string $logoUrl,
        public readonly ?string $logoDarkUrl,
        public readonly ?string $faviconUrl,
        public readonly string $primaryColor,
        public readonly string $secondaryColor,
        public readonly string $appName,
        public readonly ?string $welcomeMessage,
        public readonly string $fontFamily,
        public readonly string $themeMode,
        public readonly ?string $loginImageUrl,
        public readonly ?string $contactPhone,
        public readonly ?string $contactEmail,
        public readonly ?string $contactAddress,
        public readonly ?string $footerText,
        public readonly bool $showBreadcrumbs,
    ) {
    }

    /** Préfixe des visuels de la plateforme EduNova (dossier public/assets/img/edunova*). */
    private const PREFIXE_PLATEFORME = 'edunova';

    /** L'établissement a-t-il son PROPRE logo (et non le visuel de repli de la plateforme) ? */
    public function aUnLogoPropre(): bool
    {
        if ($this->logoUrl === null || trim($this->logoUrl) === '') {
            return false;
        }
        return !str_starts_with(strtolower(basename($this->logoUrl)), self::PREFIXE_PLATEFORME);
    }

    /**
     * Logo à imprimer sur les documents de l'établissement (bulletins, reçus, factures, relevés, rapports…) : son logo propre, sinon NULL.
     * Le logo EduNova n'apparaît JAMAIS sur un document d'établissement : il reste celui de la plateforme.
     */
    public function logoPourDocuments(): ?string
    {
        return $this->aUnLogoPropre() ? $this->logoUrl : null;
    }

    /**
     * Identité visuelle de la PLATEFORME EduNova (logo, favicon, couleurs, police) : celle du portail Super-Admin et de sa connexion.
     * Jamais celle d'un établissement, pas même de l'établissement par défaut.
     */
    public static function plateforme(): self
    {
        return self::defaults(0);
    }

    /** Valeurs de repli — identiques à ce qui était codé en dur avant Phase 14.5 (zéro régression visuelle). */
    public static function defaults(int $etablissementId): self
    {
        return new self(
            etablissementId: $etablissementId,
            logoUrl: '/assets/img/edunova-logo.png',
            logoDarkUrl: '/assets/img/edunova-logo.png',
            faviconUrl: '/assets/img/edunova-logo.png',
            primaryColor: '#7c3aed',
            secondaryColor: '#0ea5e9',
            appName: 'EduNova',
            welcomeMessage: 'Bienvenue sur votre espace de gestion scolaire.',
            fontFamily: 'Inter',
            themeMode: 'light',
            loginImageUrl: null,
            contactPhone: null,
            contactEmail: null,
            contactAddress: null,
            footerText: '© ' . date('Y') . ' EduNova — Tous droits réservés',
            showBreadcrumbs: true,
        );
    }
}
