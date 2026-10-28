<?php

declare(strict_types=1);

namespace App\Modules\Academique\Services;

use App\Modules\Academique\Repositories\BulletinRepository;
use Core\Logger;

/**
 * Publie en une fois les bulletins d'une classe pour une période (bouton « Publier avec QR »).
 *
 * Pour chaque élève : si le bulletin est déjà publié ou archivé il n'est PAS touché ; sinon il est régénéré en vue de la publication
 * (jeton officiel de version courante, Conduite complète exigée) puis publié. Un échec isole l'élève concerné et n'empêche pas les
 * autres. Le traitement est idempotent : relancé, il ne modifie jamais un bulletin déjà publié.
 *
 * Seule l'absence/invalidité du secret de jetons ({@see BulletinTokenException}) interrompt le traitement : rien ne peut alors être publié.
 * Les erreurs techniques (base de données…) sont journalisées en détail mais ne sont jamais exposées à l'utilisateur.
 */
final class BulletinClassePublisher
{
    public function __construct(
        private readonly BulletinGenerator $generator,
        private readonly BulletinRepository $repo,
    ) {
    }

    /**
     * @return array{publies:int, deja:int, echecs:list<array{eleve_id:int, nom:string, raison:string, technique:bool}>}
     * @throws BulletinTokenException secret de jetons absent ou invalide
     */
    public function publier(int $classeId, int $periodeId, int $userId): array
    {
        BulletinTokenService::fromEnvironment();   // refus immédiat, AVANT toute écriture, si le secret de jetons est absent ou invalide

        $publies = 0;
        $deja    = 0;
        $echecs  = [];

        foreach ($this->repo->elevesParClasse($classeId) as $eleveId) {
            $eleveId = (int)$eleveId;
            $existant = $this->lire($eleveId, $periodeId);
            if ($existant && in_array($existant['statut'] ?? '', ['publie', 'archive'], true)) {
                $deja++;
                continue;
            }
            if ($existant && (int)($existant['token_version'] ?? 1) !== BulletinTokenService::VERSION) {
                // Jeton hérité : la publication échouerait de toute façon ; on évite de réécrire l'instantané d'un brouillon qui ne sera pas publié.
                $echecs[] = $this->echec($eleveId, 'Jeton hérité (version ' . (int)($existant['token_version'] ?? 1) . ') : la rotation des jetons doit précéder la publication de ce bulletin.', false);
                continue;
            }
            try {
                $bulletin = $this->generator->genererBulletin($eleveId, $periodeId, $userId, pourPublication: true);
                $this->generator->publierBulletin($bulletin->verificationToken, $userId);
                $publies++;
            } catch (BulletinTokenException $e) {
                throw $e;
            } catch (\PDOException $e) {
                Logger::error('Publication de classe : erreur base de données (eleve=' . $eleveId . ' periode=' . $periodeId . ') : ' . $e->getMessage());
                $echecs[] = $this->echec($eleveId, 'erreur technique — réessayez ; si elle persiste, contactez l\'administrateur', true);
            } catch (ConduiteIncompleteException | \RuntimeException $e) {
                // Publié entre-temps par une autre requête (double clic) : ce n'est pas un échec.
                if ($this->estEmis($eleveId, $periodeId)) {
                    $deja++;
                    continue;
                }
                $echecs[] = $this->echec($eleveId, $e->getMessage(), false);
            } catch (\Throwable $e) {
                Logger::error('Publication de classe : erreur inattendue (eleve=' . $eleveId . ' periode=' . $periodeId . ') ' . get_class($e) . ' : ' . $e->getMessage());
                $echecs[] = $this->echec($eleveId, 'erreur technique — réessayez ; si elle persiste, contactez l\'administrateur', true);
            }
        }

        return ['publies' => $publies, 'deja' => $deja, 'echecs' => $echecs];
    }

    /** Ligne du bulletin, ou null (absent ou base injoignable : on laisse alors la génération décider et rapporter l'erreur). */
    private function lire(int $eleveId, int $periodeId): ?array
    {
        try {
            $row = $this->repo->findByEleveEtPeriode($eleveId, $periodeId);
        } catch (\Throwable) {
            return null;
        }
        return $row ?: null;
    }

    /** Le bulletin est-il déjà officiel (publié ou archivé) ? Une base injoignable ne vaut jamais « oui ». */
    private function estEmis(int $eleveId, int $periodeId): bool
    {
        $row = $this->lire($eleveId, $periodeId);
        return $row !== null && in_array($row['statut'] ?? '', ['publie', 'archive'], true);
    }

    /** @return array{eleve_id:int, nom:string, raison:string, technique:bool} */
    private function echec(int $eleveId, string $raison, bool $technique): array
    {
        $nom = 'élève #' . $eleveId;
        try {
            $eleve = $this->repo->infoEleve($eleveId);
            if ($eleve) {
                $nom = trim(($eleve->prenom ?? '') . ' ' . ($eleve->nom ?? '')) ?: $nom;
            }
        } catch (\Throwable) {
            // le nom est un confort : on garde l'identifiant
        }
        return ['eleve_id' => $eleveId, 'nom' => $nom, 'raison' => $raison, 'technique' => $technique];
    }
}
