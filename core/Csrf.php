<?php

namespace Core;

/**
 * Aide de vue pour le champ CSRF caché. Délègue à Session (source de vérité
 * pour le token) — évite de dupliquer le HTML dans chaque vue.
 */
class Csrf
{
    public static function field(): void
    {
        echo '<input type="hidden" name="_csrf_token" value="'
            . htmlspecialchars(Session::getCsrfToken(), ENT_QUOTES)
            . '">';
    }
}
