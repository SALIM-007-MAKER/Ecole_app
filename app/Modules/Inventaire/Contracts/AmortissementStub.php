<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Contracts;

/** Calcul d'amortissement linéaire/dégressif — stub V2, moteur complet V3 */
class AmortissementStub implements AmortissementInterface
{
    public function calculerLineaire(float $valeurAchat, int $dureeMois, float $valeurResiduelle, int $moisEcoules): float
    {
        if ($dureeMois <= 0) return $valeurResiduelle;
        $ratio = min(1.0, $moisEcoules / $dureeMois);
        return round($valeurAchat - ($valeurAchat - $valeurResiduelle) * $ratio, 2);
    }

    public function calculerDegressif(float $valeurAchat, int $dureeMois, float $valeurResiduelle, int $moisEcoules): float
    {
        if ($dureeMois <= 0) return $valeurResiduelle;
        $tauxLineaire  = 1 / ($dureeMois / 12);
        $coefficients  = [3 => 1.25, 5 => 1.75, 6 => 2.25];
        $dureeAns      = (int)ceil($dureeMois / 12);
        $coeff         = $coefficients[$dureeAns] ?? 2.25;
        $tauxDegressif = $tauxLineaire * $coeff;
        $valeur        = $valeurAchat;
        $anneesEcoulees = (int)($moisEcoules / 12);
        for ($i = 0; $i < $anneesEcoulees; $i++) {
            $valeur -= $valeur * $tauxDegressif;
            if ($valeur < $valeurResiduelle) return $valeurResiduelle;
        }
        return round(max($valeurResiduelle, $valeur), 2);
    }

    public function tableauAmortissement(array $params): array
    {
        $rows = [];
        $valeurAchat      = (float)($params['valeur_achat'] ?? 0);
        $dureeMois        = (int)($params['duree_mois'] ?? 60);
        $valeurResiduelle = (float)($params['valeur_residuelle'] ?? 0);
        $methode          = $params['methode'] ?? 'lineaire';
        for ($m = 1; $m <= $dureeMois; $m++) {
            $vnc = $methode === 'lineaire'
                ? $this->calculerLineaire($valeurAchat, $dureeMois, $valeurResiduelle, $m)
                : $this->calculerDegressif($valeurAchat, $dureeMois, $valeurResiduelle, $m);
            $rows[] = ['mois' => $m, 'valeur_nette' => $vnc];
        }
        return $rows;
    }
}
