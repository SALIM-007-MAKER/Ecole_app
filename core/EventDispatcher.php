<?php

namespace Core;

class EventDispatcher
{
    private static array $listeners = [];

    public static function listen(string $eventClass, Listener $listener): void
    {
        self::$listeners[$eventClass][] = $listener;
    }

    /**
     * Dispatche un événement vers tous ses listeners enregistrés.
     * Chaque listener est isolé : une exception n'interrompt pas les suivants
     * et ne remonte jamais vers le contrôleur appelant.
     */
    public static function dispatch(Event $event): void
    {
        $eventClass = get_class($event);

        foreach (self::$listeners[$eventClass] ?? [] as $listener) {
            try {
                $listener->handle($event);
            } catch (\Throwable $e) {
                error_log(sprintf(
                    '[EventDispatcher] %s → %s : %s dans %s:%d',
                    $event->getName(),
                    get_class($listener),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                ));
            }
        }
    }

    /** Réinitialise le registre — utile dans les tests unitaires. */
    public static function forget(string $eventClass = null): void
    {
        if ($eventClass !== null) {
            unset(self::$listeners[$eventClass]);
        } else {
            self::$listeners = [];
        }
    }

    public static function getListeners(string $eventClass): array
    {
        return self::$listeners[$eventClass] ?? [];
    }
}
