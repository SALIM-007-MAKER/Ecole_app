<?php

declare(strict_types=1);

namespace App\Modules\RH\Formations\Services;

use Core\EventDispatcher;
use App\Modules\RH\Formations\Repositories\TrainingRepository;
use App\Modules\RH\Formations\Events\CertificationGranted;
use App\Modules\RH\Formations\Events\CertificationExpired;

class CertificationService
{
    private TrainingRepository $repo;

    public function __construct()
    {
        $this->repo = new TrainingRepository();
    }

    public function accorderCertification(int $employeId, int $certificationId, array $data, int $userId): int
    {
        $catalogue = $this->repo->findAllCertificationsCatalogue();
        $cert = null;
        foreach ($catalogue as $c) {
            if ((int)$c['id'] === $certificationId) { $cert = $c; break; }
        }
        if (!$cert) throw new \RuntimeException('Certification introuvable dans le catalogue.');

        $dateObtention  = $data['date_obtention'] ?? date('Y-m-d');
        $dateExpiration = null;

        if ($cert['duree_validite_mois'] !== null) {
            $dateExpiration = date(
                'Y-m-d',
                strtotime($dateObtention . ' + ' . $cert['duree_validite_mois'] . ' months')
            );
        }

        $empCertId = $this->repo->insertEmployeCertification([
            'employe_id'       => $employeId,
            'certification_id' => $certificationId,
            'date_obtention'   => $dateObtention,
            'date_expiration'  => $dateExpiration,
            'session_id'       => ($data['session_id'] ?? '') !== '' ? (int)$data['session_id'] : null,
            'reference'        => trim($data['reference_certificat'] ?? ''),
            'notes'            => trim($data['notes'] ?? ''),
            'created_by'       => $userId,
        ]);

        EventDispatcher::dispatch(new CertificationGranted(
            $empCertId, $employeId, $certificationId,
            $cert['code'], $dateObtention, $dateExpiration, $userId
        ));

        return $empCertId;
    }

    public function verifierExpirations(): int
    {
        $expiring = $this->repo->findExpiringCertifications(0);
        $count    = 0;

        foreach ($expiring as $ec) {
            if (($ec['date_expiration'] ?? '') <= date('Y-m-d')) {
                $this->repo->updateCertificationStatut((int)$ec['id'], 'expiree');
                EventDispatcher::dispatch(new CertificationExpired(
                    (int)$ec['id'], (int)$ec['employe_id'],
                    $ec['cert_code'], $ec['date_expiration']
                ));
                $count++;
            } elseif (strtotime($ec['date_expiration']) <= strtotime('+30 days')) {
                $this->repo->updateCertificationStatut((int)$ec['id'], 'a_renouveler');
                $count++;
            }
        }

        return $count;
    }
}
