<?php
declare(strict_types=1);

namespace App\Modules\Portals\DTO;

class PreferencesDTO
{
    public function __construct(
        public readonly array  $widgetLayout,
        public readonly array  $hiddenWidgets,
        public readonly array  $shortcuts,
        public readonly string $theme,
        public readonly string $defaultPage,
        public readonly array  $notifPrefs,
        public readonly string $lang,
    ) {}

    public static function defaults(): self
    {
        return new self(
            widgetLayout:  [],
            hiddenWidgets: [],
            shortcuts:     [],
            theme:         'default',
            defaultPage:   '',
            notifPrefs:    [],
            lang:          'fr',
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            widgetLayout:  $data['widget_layout']   ?? [],
            hiddenWidgets: $data['hidden_widgets']  ?? [],
            shortcuts:     $data['shortcuts']       ?? [],
            theme:         $data['theme']           ?? 'default',
            defaultPage:   $data['default_page']    ?? '',
            notifPrefs:    $data['notif_prefs']     ?? [],
            lang:          $data['lang']            ?? 'fr',
        );
    }

    public function toArray(): array
    {
        return [
            'widget_layout'  => $this->widgetLayout,
            'hidden_widgets' => $this->hiddenWidgets,
            'shortcuts'      => $this->shortcuts,
            'theme'          => $this->theme,
            'default_page'   => $this->defaultPage,
            'notif_prefs'    => $this->notifPrefs,
            'lang'           => $this->lang,
        ];
    }
}
