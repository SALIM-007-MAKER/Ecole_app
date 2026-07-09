<?php

declare(strict_types=1);

namespace App\Modules\Communication\DTO;

class TemplateDTO
{
    public function __construct(
        public readonly string  $code,
        public readonly string  $nom,
        public readonly string  $canal,
        public readonly string  $langue       = 'fr',
        public readonly ?string $moduleSource = null,
        public readonly ?string $sujet        = null,
        public readonly ?string $corpsHtml    = null,
        public readonly ?string $corpsTexte   = null,
        public readonly array   $variables    = [],
    ) {}

    public static function fromRequest(array $data): self
    {
        $vars = $data['variables'] ?? [];
        if (is_string($vars)) {
            $vars = array_filter(array_map('trim', explode(',', $vars)));
        }
        return new self(
            code:         trim($data['code']   ?? ''),
            nom:          trim($data['nom']    ?? ''),
            canal:        $data['canal']       ?? 'internal',
            langue:       $data['langue']      ?? 'fr',
            moduleSource: $data['module_source'] ?? null,
            sujet:        !empty($data['sujet']) ? trim($data['sujet']) : null,
            corpsHtml:    !empty($data['corps_html'])  ? $data['corps_html']  : null,
            corpsTexte:   !empty($data['corps_texte']) ? $data['corps_texte'] : null,
            variables:    array_values((array) $vars),
        );
    }

    public function validate(): array
    {
        $errors = [];
        if (empty($this->code)) {
            $errors[] = 'Le code du template est requis.';
        }
        if (empty($this->nom)) {
            $errors[] = 'Le nom du template est requis.';
        }
        if (!in_array($this->canal, ['email', 'sms', 'push', 'internal'], true)) {
            $errors[] = 'Canal invalide.';
        }
        if ($this->canal === 'email' && empty($this->corpsHtml) && empty($this->corpsTexte)) {
            $errors[] = 'Un contenu (HTML ou texte) est requis pour les emails.';
        }
        return $errors;
    }
}
