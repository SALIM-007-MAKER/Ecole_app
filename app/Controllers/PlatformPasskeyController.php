<?php

namespace App\Controllers;

use Core\Database;
use Core\Platform\PlatformController;
use Core\Platform\PlatformAuth;
use Core\Platform\PlatformPasskeyService;
use Core\Platform\PlatformTwoFactorService;
use Core\Platform\TwoFactorException;
use Core\Session;

/**
 * Gestion des passkeys (WebAuthn) de l'opérateur connecté : ajout, renommage, suppression.
 *
 * Ajout et suppression exigent le mot de passe (ré-authentification, comme pour le TOTP). Les tentatives de mot de passe partagent le compteur
 * de la page « Double authentification » (5 échecs / 15 min par opérateur et IP, 15 par opérateur). Une passkey s'ajoute à la 2FA par application,
 * qui doit déjà être active.
 */
class PlatformPasskeyController extends PlatformController
{
    private function pdo(): \PDO
    {
        return Database::getInstance()->getConnection();
    }

    private function service(): PlatformPasskeyService
    {
        return new PlatformPasskeyService($this->pdo());
    }

    /** @return array{0:\App\Shared\Http\LoginThrottle, 1:list<string>} */
    private function limiter(bool $json): array
    {
        $id = $this->operatorId();
        $throttle = $this->throttle();
        $cles = ['tfa:' . $id . '|' . $this->request->ip() => 5, 'tfn:' . $id => 15];
        $attente = 0;
        foreach ($cles as $cle => $limite) {
            $attente = max($attente, $throttle->retryAfter($cle, $limite));
        }
        if ($attente > 0) {
            $msg = 'Trop de tentatives. Réessayez dans ' . (int)ceil($attente / 60) . ' minute(s).';
            if ($json) {
                $this->json(['ok' => false, 'message' => $msg], 429);
            }
            Session::flash('errors', [$msg]);
            $this->redirect(BASE_URL . '/platform/compte/2fa');
        }
        return [$throttle, array_keys($cles)];
    }

    private function exigerDisponible(bool $json): void
    {
        if (!PlatformPasskeyService::disponible($this->pdo()) || !PlatformTwoFactorService::disponible($this->pdo())) {
            $msg = 'Les passkeys ne sont pas encore activées sur cette installation (migration T073).';
            if ($json) {
                $this->json(['ok' => false, 'message' => $msg], 503);
            }
            Session::flash('errors', [$msg]);
            $this->redirect(BASE_URL . '/platform/compte/2fa');
        }
    }

    /** Mot de passe exigé ; un mot de passe faux compte comme un échec, un succès remet le compteur à zéro. */
    private function reauthentifier(bool $json): void
    {
        [$throttle, $cles] = $this->limiter($json);
        try {
            (new PlatformTwoFactorService($this->pdo()))->verifierMotDePasse($this->operatorId(), (string)$this->request->post('current_password', ''));
        } catch (TwoFactorException $e) {
            foreach ($cles as $cle) {
                $throttle->recordFailure($cle);
            }
            if ($json) {
                $this->json(['ok' => false, 'message' => $e->getMessage()], 403);
            }
            Session::flash('errors', [$e->getMessage()]);
            $this->redirect(BASE_URL . '/platform/compte/2fa');
        }
        foreach ($cles as $cle) {
            $throttle->clear($cle);
        }
    }

    /** Étape 1 de l'ajout : mot de passe vérifié, puis options du défi pour navigator.credentials.create(). */
    public function options(): void
    {
        $this->requirePlatformAuth();
        $this->verifyCsrf();
        $this->exigerDisponible(true);
        $this->reauthentifier(true);
        try {
            $this->json(['ok' => true, 'options' => $this->service()->optionsEnregistrement($this->operatorId(), (string)(PlatformAuth::current()['email'] ?? ''))]);
        } catch (TwoFactorException $e) {
            $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /** Étape 2 de l'ajout : réponse de l'appareil. */
    public function enregistrer(): void
    {
        $this->requirePlatformAuth();
        $this->verifyCsrf();
        $this->exigerDisponible(true);
        try {
            $this->service()->enregistrer(
                $this->operatorId(),
                (string)$this->request->post('nom', ''),
                (string)$this->request->post('client_data', ''),
                (string)$this->request->post('attestation', '')
            );
        } catch (TwoFactorException $e) {
            $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Session::flash('success', 'Passkey ajoutée. Vous pouvez désormais l\'utiliser à la connexion.');
        $this->json(['ok' => true, 'redirect' => BASE_URL . '/platform/compte/2fa']);
    }

    public function renommer(int $id): void
    {
        $this->requirePlatformAuth();
        $this->verifyCsrf();
        $this->exigerDisponible(false);
        try {
            $this->service()->renommer($this->operatorId(), $id, (string)$this->request->post('nom', ''));
            Session::flash('success', 'Passkey renommée.');
        } catch (TwoFactorException $e) {
            Session::flash('errors', [$e->getMessage()]);
        }
        $this->redirect(BASE_URL . '/platform/compte/2fa');
    }

    public function supprimer(int $id): void
    {
        $this->requirePlatformAuth();
        $this->verifyCsrf();
        $this->exigerDisponible(false);
        $this->reauthentifier(false);
        try {
            $this->service()->supprimer($this->operatorId(), $id);
            Session::flash('success', 'Passkey supprimée.');
        } catch (TwoFactorException $e) {
            Session::flash('errors', [$e->getMessage()]);
        }
        $this->redirect(BASE_URL . '/platform/compte/2fa');
    }
}
