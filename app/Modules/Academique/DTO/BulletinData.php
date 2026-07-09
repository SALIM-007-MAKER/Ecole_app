<?php

namespace App\Modules\Academique\DTO;

/**
 * Snapshot complet et immuable d'un bulletin pour un élève / une période.
 *
 * Les champs float (moyenne, moyenneClasse) sont déjà arrondis par
 * AcademicCalculationService — ne pas recalculer ici.
 */
final class BulletinData
{
    public function __construct(
        // ── Identité élève ────────────────────────────────────────────
        public readonly int     $eleveId,
        public readonly string  $eleveNom,
        public readonly string  $elevePrenom,
        public readonly string  $eleveMatricule,
        public readonly ?string $elevePhoto,

        // ── Classe & Période ──────────────────────────────────────────
        public readonly int     $classeId,
        public readonly string  $classeNom,
        public readonly string  $niveau,
        public readonly int     $periodeId,
        public readonly string  $periodeNom,
        public readonly string  $anneeScolaire,

        // ── Établissement ─────────────────────────────────────────────
        public readonly string  $etablissementNom,
        public readonly ?string $etablissementAdresse,
        public readonly ?string $etablissementLogo,

        // ── Lignes matières ───────────────────────────────────────────
        // Each entry: {matiere_id, matiere_nom, coefficient, notes[],
        //              moyenne, mention_code, mention_label, mention_css,
        //              appreciation, aEliminatoire, nb_notes, nb_absents}
        public readonly array   $lignesMatieres,

        // ── Résultats globaux ─────────────────────────────────────────
        public readonly float   $moyennePeriode,
        public readonly string  $mentionCode,
        public readonly string  $mentionLabel,
        public readonly string  $mentionCss,
        public readonly bool    $admis,
        public readonly bool    $aEliminatoire,
        public readonly int     $rang,
        public readonly int     $nbEleves,
        public readonly ?float  $moyenneClasse,

        // ── Statistiques classe ───────────────────────────────────────
        public readonly array   $statistiquesClasse,

        // ── Décision ─────────────────────────────────────────────────
        public readonly string  $decision,
        public readonly string  $decisionMotif,

        // ── Appréciations ─────────────────────────────────────────────
        public readonly string  $appreciationPp,
        public readonly ?string $appreciationDirecteur,

        // ── Signatures ────────────────────────────────────────────────
        // Each entry: {role, nom, date, signed: bool}
        public readonly array   $signatures,

        // ── Vérification / QR Code ───────────────────────────────────
        public readonly string  $verificationToken,
        public readonly string  $qrCodeUrl,

        // ── Méta ─────────────────────────────────────────────────────
        public readonly string  $statut,
        public readonly string  $generatedAt,
        public readonly int     $generatedById,
        public readonly ?string $publishedAt,
        public readonly ?string $archivedAt,
    ) {}

    // ─────────────────────────────────────────────────────────────────
    //  Transitions de statut (retourne une nouvelle instance)
    // ─────────────────────────────────────────────────────────────────

    public function withStatut(
        string  $statut,
        ?string $publishedAt = null,
        ?string $archivedAt  = null,
    ): self {
        return new self(
            eleveId              : $this->eleveId,
            eleveNom             : $this->eleveNom,
            elevePrenom          : $this->elevePrenom,
            eleveMatricule       : $this->eleveMatricule,
            elevePhoto           : $this->elevePhoto,
            classeId             : $this->classeId,
            classeNom            : $this->classeNom,
            niveau               : $this->niveau,
            periodeId            : $this->periodeId,
            periodeNom           : $this->periodeNom,
            anneeScolaire        : $this->anneeScolaire,
            etablissementNom     : $this->etablissementNom,
            etablissementAdresse : $this->etablissementAdresse,
            etablissementLogo    : $this->etablissementLogo,
            lignesMatieres       : $this->lignesMatieres,
            moyennePeriode       : $this->moyennePeriode,
            mentionCode          : $this->mentionCode,
            mentionLabel         : $this->mentionLabel,
            mentionCss           : $this->mentionCss,
            admis                : $this->admis,
            aEliminatoire        : $this->aEliminatoire,
            rang                 : $this->rang,
            nbEleves             : $this->nbEleves,
            moyenneClasse        : $this->moyenneClasse,
            statistiquesClasse   : $this->statistiquesClasse,
            decision             : $this->decision,
            decisionMotif        : $this->decisionMotif,
            appreciationPp       : $this->appreciationPp,
            appreciationDirecteur: $this->appreciationDirecteur,
            signatures           : $this->signatures,
            verificationToken    : $this->verificationToken,
            qrCodeUrl            : $this->qrCodeUrl,
            statut               : $statut,
            generatedAt          : $this->generatedAt,
            generatedById        : $this->generatedById,
            publishedAt          : $publishedAt ?? $this->publishedAt,
            archivedAt           : $archivedAt  ?? $this->archivedAt,
        );
    }

    public function withAppreciationDirecteur(string $appreciation): self
    {
        return new self(
            eleveId              : $this->eleveId,
            eleveNom             : $this->eleveNom,
            elevePrenom          : $this->elevePrenom,
            eleveMatricule       : $this->eleveMatricule,
            elevePhoto           : $this->elevePhoto,
            classeId             : $this->classeId,
            classeNom            : $this->classeNom,
            niveau               : $this->niveau,
            periodeId            : $this->periodeId,
            periodeNom           : $this->periodeNom,
            anneeScolaire        : $this->anneeScolaire,
            etablissementNom     : $this->etablissementNom,
            etablissementAdresse : $this->etablissementAdresse,
            etablissementLogo    : $this->etablissementLogo,
            lignesMatieres       : $this->lignesMatieres,
            moyennePeriode       : $this->moyennePeriode,
            mentionCode          : $this->mentionCode,
            mentionLabel         : $this->mentionLabel,
            mentionCss           : $this->mentionCss,
            admis                : $this->admis,
            aEliminatoire        : $this->aEliminatoire,
            rang                 : $this->rang,
            nbEleves             : $this->nbEleves,
            moyenneClasse        : $this->moyenneClasse,
            statistiquesClasse   : $this->statistiquesClasse,
            decision             : $this->decision,
            decisionMotif        : $this->decisionMotif,
            appreciationPp       : $this->appreciationPp,
            appreciationDirecteur: $appreciation,
            signatures           : $this->signatures,
            verificationToken    : $this->verificationToken,
            qrCodeUrl            : $this->qrCodeUrl,
            statut               : $this->statut,
            generatedAt          : $this->generatedAt,
            generatedById        : $this->generatedById,
            publishedAt          : $this->publishedAt,
            archivedAt           : $this->archivedAt,
        );
    }

    // ─────────────────────────────────────────────────────────────────
    //  Sérialisation
    // ─────────────────────────────────────────────────────────────────

    public function toArray(): array
    {
        return [
            'eleve_id'               => $this->eleveId,
            'eleve_nom'              => $this->eleveNom,
            'eleve_prenom'           => $this->elevePrenom,
            'eleve_matricule'        => $this->eleveMatricule,
            'eleve_photo'            => $this->elevePhoto,
            'classe_id'              => $this->classeId,
            'classe_nom'             => $this->classeNom,
            'niveau'                 => $this->niveau,
            'periode_id'             => $this->periodeId,
            'periode_nom'            => $this->periodeNom,
            'annee_scolaire'         => $this->anneeScolaire,
            'etablissement_nom'      => $this->etablissementNom,
            'etablissement_adresse'  => $this->etablissementAdresse,
            'etablissement_logo'     => $this->etablissementLogo,
            'lignes_matieres'        => $this->lignesMatieres,
            'moyenne_periode'        => $this->moyennePeriode,
            'mention_code'           => $this->mentionCode,
            'mention_label'          => $this->mentionLabel,
            'mention_css'            => $this->mentionCss,
            'admis'                  => $this->admis,
            'a_eliminatoire'         => $this->aEliminatoire,
            'rang'                   => $this->rang,
            'nb_eleves'              => $this->nbEleves,
            'moyenne_classe'         => $this->moyenneClasse,
            'statistiques_classe'    => $this->statistiquesClasse,
            'decision'               => $this->decision,
            'decision_motif'         => $this->decisionMotif,
            'appreciation_pp'        => $this->appreciationPp,
            'appreciation_directeur' => $this->appreciationDirecteur,
            'signatures'             => $this->signatures,
            'verification_token'     => $this->verificationToken,
            'qr_code_url'            => $this->qrCodeUrl,
            'statut'                 => $this->statut,
            'generated_at'           => $this->generatedAt,
            'generated_by_id'        => $this->generatedById,
            'published_at'           => $this->publishedAt,
            'archived_at'            => $this->archivedAt,
        ];
    }

    public static function fromArray(array $d): self
    {
        return new self(
            eleveId              : (int)$d['eleve_id'],
            eleveNom             : $d['eleve_nom']              ?? '',
            elevePrenom          : $d['eleve_prenom']           ?? '',
            eleveMatricule       : $d['eleve_matricule']        ?? '',
            elevePhoto           : $d['eleve_photo']            ?? null,
            classeId             : (int)($d['classe_id']        ?? 0),
            classeNom            : $d['classe_nom']             ?? '',
            niveau               : $d['niveau']                 ?? '',
            periodeId            : (int)($d['periode_id']       ?? 0),
            periodeNom           : $d['periode_nom']            ?? '',
            anneeScolaire        : $d['annee_scolaire']         ?? '',
            etablissementNom     : $d['etablissement_nom']      ?? '',
            etablissementAdresse : $d['etablissement_adresse']  ?? null,
            etablissementLogo    : $d['etablissement_logo']     ?? null,
            lignesMatieres       : $d['lignes_matieres']        ?? [],
            moyennePeriode       : (float)($d['moyenne_periode']?? 0),
            mentionCode          : $d['mention_code']           ?? '',
            mentionLabel         : $d['mention_label']          ?? '',
            mentionCss           : $d['mention_css']            ?? '',
            admis                : (bool)($d['admis']           ?? false),
            aEliminatoire        : (bool)($d['a_eliminatoire']  ?? false),
            rang                 : (int)($d['rang']             ?? 0),
            nbEleves             : (int)($d['nb_eleves']        ?? 0),
            moyenneClasse        : isset($d['moyenne_classe']) ? (float)$d['moyenne_classe'] : null,
            statistiquesClasse   : $d['statistiques_classe']    ?? [],
            decision             : $d['decision']               ?? 'indeterminate',
            decisionMotif        : $d['decision_motif']         ?? '',
            appreciationPp       : $d['appreciation_pp']        ?? '',
            appreciationDirecteur: $d['appreciation_directeur'] ?? null,
            signatures           : $d['signatures']             ?? [],
            verificationToken    : $d['verification_token']     ?? '',
            qrCodeUrl            : $d['qr_code_url']            ?? '',
            statut               : $d['statut']                 ?? 'brouillon',
            generatedAt          : $d['generated_at']           ?? '',
            generatedById        : (int)($d['generated_by_id']  ?? 0),
            publishedAt          : $d['published_at']           ?? null,
            archivedAt           : $d['archived_at']            ?? null,
        );
    }

    public function toSummary(): BulletinSummary
    {
        return new BulletinSummary(
            eleveId          : $this->eleveId,
            eleveNom         : $this->eleveNom,
            elevePrenom      : $this->elevePrenom,
            eleveMatricule   : $this->eleveMatricule,
            classeId         : $this->classeId,
            classeNom        : $this->classeNom,
            periodeId        : $this->periodeId,
            periodeNom       : $this->periodeNom,
            moyenne          : $this->moyennePeriode,
            mentionCode      : $this->mentionCode,
            mentionLabel     : $this->mentionLabel,
            rang             : $this->rang,
            nbEleves         : $this->nbEleves,
            decision         : $this->decision,
            statut           : $this->statut,
            verificationToken: $this->verificationToken,
            generatedAt      : $this->generatedAt,
        );
    }
}
