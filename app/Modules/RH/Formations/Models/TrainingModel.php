<?php

declare(strict_types=1);

namespace App\Modules\RH\Formations\Models;

class TrainingModel
{
    const TYPES_FORMATION = [
        'interne'       => 'Interne',
        'externe'       => 'Externe',
        'e_learning'    => 'E-learning',
        'certification' => 'Certification',
        'coaching'      => 'Coaching',
        'seminaire'     => 'Séminaire',
    ];

    const TYPE_COLORS = [
        'interne'       => 'bg-blue-100 text-blue-700',
        'externe'       => 'bg-violet-100 text-violet-700',
        'e_learning'    => 'bg-cyan-100 text-cyan-700',
        'certification' => 'bg-emerald-100 text-emerald-700',
        'coaching'      => 'bg-amber-100 text-amber-700',
        'seminaire'     => 'bg-slate-100 text-slate-700',
    ];

    const NIVEAUX = [
        'debutant'      => 'Débutant',
        'intermediaire' => 'Intermédiaire',
        'avance'        => 'Avancé',
        'expert'        => 'Expert',
    ];

    const MODALITES = [
        'presentiel'  => 'Présentiel',
        'distanciel'  => 'Distanciel',
        'hybride'     => 'Hybride',
    ];

    const SESSION_STATUTS = [
        'planifiee' => 'Planifiée',
        'ouverte'   => 'Ouverte',
        'en_cours'  => 'En cours',
        'terminee'  => 'Terminée',
        'annulee'   => 'Annulée',
    ];

    const SESSION_STATUT_COLORS = [
        'planifiee' => 'bg-slate-100 text-slate-600',
        'ouverte'   => 'bg-blue-100 text-blue-700',
        'en_cours'  => 'bg-amber-100 text-amber-700',
        'terminee'  => 'bg-emerald-100 text-emerald-700',
        'annulee'   => 'bg-red-100 text-red-600',
    ];

    const SESSION_TRANSITIONS = [
        'planifiee' => ['ouvrir'],
        'ouverte'   => ['demarrer', 'annuler'],
        'en_cours'  => ['terminer', 'annuler'],
        'terminee'  => [],
        'annulee'   => [],
    ];

    const INSCRIPTION_STATUTS = [
        'inscrit'  => 'Inscrit',
        'confirme' => 'Confirmé',
        'present'  => 'Présent',
        'absent'   => 'Absent',
        'valide'   => 'Validé',
        'annule'   => 'Annulé',
    ];

    const INSCRIPTION_COLORS = [
        'inscrit'  => 'bg-slate-100 text-slate-600',
        'confirme' => 'bg-blue-100 text-blue-700',
        'present'  => 'bg-amber-100 text-amber-700',
        'absent'   => 'bg-red-100 text-red-700',
        'valide'   => 'bg-emerald-100 text-emerald-700',
        'annule'   => 'bg-slate-100 text-slate-400',
    ];

    const FINANCEURS = [
        'etablissement' => 'Établissement',
        'organisme'     => 'Organisme externe',
        'personnel'     => 'Personnel',
        'mixte'         => 'Mixte',
    ];

    const CERT_STATUTS = [
        'valide'      => 'Valide',
        'expiree'     => 'Expirée',
        'a_renouveler'=> 'À renouveler',
    ];

    const CERT_COLORS = [
        'valide'      => 'bg-emerald-100 text-emerald-700',
        'expiree'     => 'bg-red-100 text-red-700',
        'a_renouveler'=> 'bg-amber-100 text-amber-700',
    ];

    const COMPETENCE_NIVEAUX = [
        'debutant'      => 'Débutant',
        'intermediaire' => 'Intermédiaire',
        'avance'        => 'Avancé',
        'expert'        => 'Expert',
    ];

    const COMPETENCE_NIVEAU_COLORS = [
        'debutant'      => 'bg-slate-100 text-slate-600',
        'intermediaire' => 'bg-blue-100 text-blue-700',
        'avance'        => 'bg-violet-100 text-violet-700',
        'expert'        => 'bg-emerald-100 text-emerald-700',
    ];

    const COMPETENCE_CATEGORIES = [
        'technique'        => 'Technique',
        'comportementale'  => 'Comportementale',
        'management'       => 'Management',
        'pedagogique'      => 'Pédagogique',
        'transversale'     => 'Transversale',
    ];

    public static function sessionStatutLabel(string $s): string { return self::SESSION_STATUTS[$s] ?? $s; }
    public static function sessionStatutColor(string $s): string { return self::SESSION_STATUT_COLORS[$s] ?? 'bg-slate-100 text-slate-600'; }

    public static function canSessionTransition(string $statut, string $action): bool
    {
        return in_array($action, self::SESSION_TRANSITIONS[$statut] ?? [], true);
    }

    public static function inscriptionStatutLabel(string $s): string { return self::INSCRIPTION_STATUTS[$s] ?? $s; }
    public static function inscriptionStatutColor(string $s): string { return self::INSCRIPTION_COLORS[$s] ?? 'bg-slate-100 text-slate-600'; }

    public static function typeLabel(string $t): string  { return self::TYPES_FORMATION[$t] ?? $t; }
    public static function typeColor(string $t): string  { return self::TYPE_COLORS[$t] ?? 'bg-slate-100 text-slate-600'; }
    public static function niveauLabel(string $n): string { return self::NIVEAUX[$n] ?? $n; }
    public static function modaliteLabel(string $m): string { return self::MODALITES[$m] ?? $m; }

    public static function certStatutLabel(string $s): string { return self::CERT_STATUTS[$s] ?? $s; }
    public static function certStatutColor(string $s): string { return self::CERT_COLORS[$s] ?? 'bg-slate-100 text-slate-600'; }

    public static function competenceNiveauLabel(string $n): string { return self::COMPETENCE_NIVEAUX[$n] ?? $n; }
    public static function competenceNiveauColor(string $n): string { return self::COMPETENCE_NIVEAU_COLORS[$n] ?? 'bg-slate-100 text-slate-600'; }

    public static function formatDuree(float $heures): string
    {
        $h = (int)$heures;
        $m = (int)(($heures - $h) * 60);
        return $m > 0 ? "{$h}h{$m}" : "{$h}h";
    }

    public static function tauxCompletion(int $validates, int $total): float
    {
        return $total > 0 ? round($validates / $total * 100, 1) : 0.0;
    }
}
