<?php

/**
 * Configuration SMS
 * Surcharger via le fichier .env :
 *   SMS_DRIVER=rest
 *   SMS_API_URL=https://api.mongateway.com/send
 *   SMS_API_KEY=votre_cle_api
 *   SMS_SENDER=EcoleApp
 *
 * Drivers disponibles :
 *   stub — Aucun envoi réel (mode test, logs statut=echoue)
 *   rest — Appel REST JSON générique (configurable via payload_tpl)
 *
 * Payload template : les variables {phone}, {message}, {sender}
 * sont remplacées dynamiquement.
 */
return [
    'driver'      => $_ENV['SMS_DRIVER']      ?? 'stub',
    'sender'      => $_ENV['SMS_SENDER']      ?? 'EcoleApp',
    'api_url'     => $_ENV['SMS_API_URL']     ?? '',
    'api_key'     => $_ENV['SMS_API_KEY']     ?? '',
    'payload_tpl' => $_ENV['SMS_PAYLOAD_TPL'] ?? '{"to":"{phone}","message":"{message}","from":"{sender}"}',
    // Code pays par défaut si numéro local (9 chiffres)
    'country_code'=> $_ENV['SMS_COUNTRY_CODE'] ?? '221', // Sénégal
];
