<?php
declare(strict_types=1);

namespace App\Modules\Portals\DTO;

class SearchResultDTO
{
    public function __construct(
        public readonly string $module,
        public readonly string $type,
        public readonly int    $id,
        public readonly string $titre,
        public readonly string $sousTitre,
        public readonly string $url,
        public readonly string $icon,
        public readonly string $highlight = '',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            module:    (string)($data['module']     ?? ''),
            type:      (string)($data['type']       ?? ''),
            id:        (int)($data['id']            ?? 0),
            titre:     (string)($data['titre']      ?? ''),
            sousTitre: (string)($data['sous_titre'] ?? $data['sousTitre'] ?? ''),
            url:       (string)($data['url']        ?? '#'),
            icon:      (string)($data['icon']       ?? 'file'),
            highlight: (string)($data['highlight']  ?? ''),
        );
    }

    public function toArray(): array
    {
        return [
            'module'     => $this->module,
            'type'       => $this->type,
            'id'         => $this->id,
            'titre'      => $this->titre,
            'sous_titre' => $this->sousTitre,
            'url'        => $this->url,
            'icon'       => $this->icon,
            'highlight'  => $this->highlight,
        ];
    }
}
