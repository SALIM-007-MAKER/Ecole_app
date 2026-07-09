<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Modules\Communication\DTO\TemplateDTO;
use App\Modules\Communication\Models\TemplateModel;
use App\Modules\Communication\Repositories\TemplateRepository;

class TemplateService
{
    private TemplateRepository $repo;

    public function __construct()
    {
        $this->repo = new TemplateRepository();
    }

    public function creer(TemplateDTO $dto, int $userId): int
    {
        return $this->repo->insert([
            'code'            => $dto->code,
            'nom'             => $dto->nom,
            'canal'           => $dto->canal,
            'langue'          => $dto->langue,
            'module_source'   => $dto->moduleSource,
            'sujet'           => $dto->sujet,
            'corps_html'      => $dto->corpsHtml,
            'corps_texte'     => $dto->corpsTexte,
            'variables'       => $dto->variables,
            'created_by'      => $userId,
        ]);
    }

    public function modifier(int $templateId, TemplateDTO $dto, int $userId): void
    {
        $existing = $this->repo->findById($templateId);
        if ($existing === null) {
            throw new \RuntimeException("Template #{$templateId} introuvable.");
        }
        $this->repo->update($templateId, [
            'nom'        => $dto->nom,
            'sujet'      => $dto->sujet,
            'corps_html' => $dto->corpsHtml,
            'corps_texte'=> $dto->corpsTexte,
            'variables'  => $dto->variables,
        ]);
    }

    public function trouver(int $templateId): ?array
    {
        return $this->repo->findById($templateId);
    }

    public function trouverParCode(string $code, string $canal, string $langue = 'fr'): ?array
    {
        return $this->repo->findByCode($code, $canal, $langue);
    }

    /**
     * Remplace les variables {{nom}} dans le template et retourne le contenu rendu.
     * @return array{sujet:string|null, corps_html:string|null, corps_texte:string|null}
     */
    public function render(array $template, array $variables): array
    {
        $vars = array_merge(
            $this->variablesCommunes(),
            $variables
        );

        $replaceHtml  = function (?string $texte) use ($vars): ?string {
            if ($texte === null) {
                return null;
            }
            foreach ($vars as $key => $val) {
                $texte = str_replace('{{' . $key . '}}', htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8'), $texte);
            }
            return $texte;
        };

        $replaceText = function (?string $texte) use ($vars): ?string {
            if ($texte === null) {
                return null;
            }
            foreach ($vars as $key => $val) {
                $texte = str_replace('{{' . $key . '}}', (string) $val, $texte);
            }
            return $texte;
        };

        return [
            'sujet'       => $replaceText($template['sujet']      ?? null),
            'corps_html'  => $replaceHtml($template['corps_html'] ?? null),
            'corps_texte' => $replaceText($template['corps_texte'] ?? null),
        ];
    }

    public function lister(?string $canal = null, ?string $moduleSource = null): array
    {
        return $this->repo->findAll($canal, $moduleSource);
    }

    public function archiver(int $templateId, int $userId): void
    {
        $this->repo->softDelete($templateId);
    }

    private function variablesCommunes(): array
    {
        return [
            'nom_etablissement' => defined('NOM_ETABLISSEMENT') ? NOM_ETABLISSEMENT : 'Scolaris',
            'date'              => date('d/m/Y'),
            'url_plateforme'    => defined('BASE_URL') ? BASE_URL : '',
        ];
    }
}
