<?php

namespace App\Controllers;

use Core\Database;
use Core\Platform\PlatformAuth;
use Core\Platform\PlatformController;
use Core\Platform\PlatformOperatorService;
use Core\Platform\PlatformTwoFactorService;
use Core\Platform\TwoFactorException;
use Core\QrCode\QrEncoder;
use Core\Session;

/**
 * Double authentification de l'opérateur connecté : activation (QR code + code de confirmation), codes de secours, désactivation.
 *
 * Page accessible tant que la 2FA est OBLIGATOIRE et pas encore activée (c'est la seule page du portail alors ouverte, avec le changement
 * de mot de passe). Chaque saisie de code est limitée (5 échecs / 15 min par opérateur et par IP). Les codes de secours sont montrés une
 * seule fois, juste après l'activation ou la régénération.
 */
class PlatformTwoFactorController extends PlatformController
{
    protected bool $accessibleAvecDeuxFacteursARegler = true;

    private const CLE_SECRET = '_platform_2fa_setup';
    /** Durée de validité du secret en cours d'enregistrement (secondes). */
    private const DUREE_SECRET = 900;

    private function service(): PlatformTwoFactorService
    {
        return new PlatformTwoFactorService(Database::getInstance()->getConnection());
    }

    private function actif(): bool
    {
        return PlatformTwoFactorService::disponible(Database::getInstance()->getConnection());
    }

    public function show(): void
    {
        $this->requirePlatformAuth();
        $actif = $this->actif();
        $statut = ['active' => false, 'depuis' => null, 'codes_restants' => 0];
        $qr = null;
        $secret = null;
        if ($actif) {
            $statut = $this->service()->statut($this->operatorId());
            if (!$statut['active']) {
                $prep = Session::get(self::CLE_SECRET);
                if (!is_array($prep) || time() - (int)($prep['at'] ?? 0) > self::DUREE_SECRET) {
                    $prep = ['secret' => PlatformTwoFactorService::nouveauSecret(), 'at' => time()];
                    Session::set(self::CLE_SECRET, $prep);
                }
                $secret = (string)$prep['secret'];
                $email = (string)(PlatformAuth::current()['email'] ?? '');
                $qr = (new QrEncoder())->generateSvg(PlatformTwoFactorService::uri($email, $secret), 5);
            }
        }
        $this->render('platform/compte/deux-facteurs', [
            'title'       => 'Double authentification',
            'actif'       => $actif,
            'statut'      => $statut,
            'secret'      => $secret,
            'qr'          => $qr,
            'codes'       => Session::getFlash('recovery_codes', []),
            'obligatoire' => PlatformTwoFactorService::obligatoire(),
            'errors'      => Session::getFlash('errors', []),
        ], 'platform');
    }

    /** Limitation commune aux trois formulaires : renvoie à la page avec un message si trop d'échecs. */
    private function limiter(): array
    {
        $id = $this->operatorId();
        $throttle = $this->throttle();
        $cles = ['tfa:' . $id . '|' . $this->request->ip() => 5, 'tfn:' . $id => 15];
        $attente = 0;
        foreach ($cles as $cle => $limite) {
            $attente = max($attente, $throttle->retryAfter($cle, $limite));
        }
        if ($attente > 0) {
            Session::flash('errors', ['Trop de tentatives. Réessayez dans ' . (int)ceil($attente / 60) . ' minute(s).']);
            $this->redirect(BASE_URL . '/platform/compte/2fa');
        }
        return [$throttle, array_keys($cles)];
    }

    /** @param callable():mixed $operation */
    private function executer(callable $operation, string $succes): void
    {
        $this->requirePlatformAuth();
        $this->verifyCsrf();
        if (!$this->actif()) {
            Session::flash('errors', ['La double authentification n\'est pas encore activée sur cette installation (migration T072).']);
            $this->redirect(BASE_URL . '/platform/compte/2fa');
        }
        [$throttle, $cles] = $this->limiter();
        try {
            $operation();
            foreach ($cles as $cle) {
                $throttle->clear($cle);
            }
            Session::flash('success', $succes);
        } catch (TwoFactorException $e) {
            if ($e->estEchecIdentifiants()) {
                foreach ($cles as $cle) {
                    $throttle->recordFailure($cle);
                }
            }
            Session::flash('errors', [$e->getMessage()]);
        }
        $this->redirect(BASE_URL . '/platform/compte/2fa');
    }

    public function activer(): void
    {
        $this->executer(function (): void {
            $prep = Session::get(self::CLE_SECRET);
            if (!is_array($prep) || empty($prep['secret']) || time() - (int)($prep['at'] ?? 0) > self::DUREE_SECRET) {
                Session::delete(self::CLE_SECRET);
                throw new TwoFactorException('La session d\'enregistrement a expiré : rechargez la page pour obtenir un nouveau QR code.');
            }
            // Ré-authentification : une session ouverte laissée sans surveillance ne suffit pas pour lier la 2FA au téléphone d'un tiers.
            $this->service()->verifierMotDePasse($this->operatorId(), (string)$this->request->post('current_password', ''));
            $codes = $this->service()->activer($this->operatorId(), (string)$prep['secret'], (string)$this->request->post('code', ''));
            Session::delete(self::CLE_SECRET);
            Session::flash('recovery_codes', $codes);
            if (PlatformAuth::mustSetupTwoFactor()) {
                PlatformAuth::twoFactorSetupDone();
            }
        }, 'Double authentification activée. Conservez vos codes de secours en lieu sûr : ils ne seront plus affichés.');
    }

    public function regenerer(): void
    {
        $this->executer(function (): void {
            Session::flash('recovery_codes', $this->service()->regenererCodes($this->operatorId(), (string)$this->request->post('code', '')));
        }, 'Nouveaux codes de secours générés : les anciens ne fonctionnent plus.');
    }

    public function desactiver(): void
    {
        $this->executer(function (): void {
            $this->service()->desactiver($this->operatorId(), (string)$this->request->post('current_password', ''), (string)$this->request->post('code', ''));
        }, 'Double authentification désactivée.');
    }
}
