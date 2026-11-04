<?php

namespace App\Modules\Academique\Services;

use PDO;

/**
 * Protection SQL de la matière de Conduite (migration T050) : définition des trois déclencheurs sur `matieres`,
 * installation tout-ou-rien et contrôle de métadonnées.
 *
 * Ce fichier ne dépend QUE de PDO : il est chargé tel quel par la migration T050 et par l'outil
 * database/tools/conduite_protection.php, hors de l'application.
 *
 * Le contrôle ne fait AUCUNE écriture ni tentative de suppression : il lit information_schema.triggers et compare
 * nom, moment, événement, table ET corps normalisé de chaque déclencheur à l'attendu (un déclencheur remplacé par un
 * corps vide est donc détecté).
 */
final class ConduiteProtectionStatus
{
    public const OPERATIONNELLE     = 'operationnelle';
    public const NON_OPERATIONNELLE = 'non_operationnelle';

    /** @return array<string,array{timing:string,event:string,sql:string,body:string}> */
    public static function definitions(): array
    {
        $refus = static fn(string $msg): string => "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '" . $msg . "';";

        $bodyInsert = "BEGIN\n"
            . "  IF NEW.est_conduite IS NOT NULL AND (NEW.est_conduite <> 1 OR NEW.coefficient <> 1 OR NEW.volume_horaire <> 0) THEN\n"
            . '    ' . $refus('Conduite : le marqueur vaut 1, le coefficient 1 et le volume horaire 0.') . "\n"
            . "  END IF;\n"
            . "END";

        $bodyUpdate = "BEGIN\n"
            . "  IF OLD.est_conduite = 1 THEN\n"
            . "    IF NOT (NEW.est_conduite <=> 1) THEN\n"
            . '      ' . $refus('Conduite : le marqueur ne peut pas etre retire ni modifie.') . "\n"
            . "    END IF;\n"
            . "    IF NEW.coefficient <> 1 OR NEW.volume_horaire <> 0 THEN\n"
            . '      ' . $refus('Conduite : coefficient 1 et volume horaire 0 imposes.') . "\n"
            . "    END IF;\n"
            . "    IF NEW.etablissement_id <> OLD.etablissement_id THEN\n"
            . '      ' . $refus('Conduite : changement d etablissement interdit.') . "\n"
            . "    END IF;\n"
            . "  ELSEIF NEW.est_conduite IS NOT NULL THEN\n"
            . "    IF NEW.est_conduite <> 1 OR NEW.coefficient <> 1 OR NEW.volume_horaire <> 0 THEN\n"
            . '      ' . $refus('Conduite : le marqueur vaut 1, le coefficient 1 et le volume horaire 0.') . "\n"
            . "    END IF;\n"
            . "  END IF;\n"
            . "END";

        $bodyDelete = "BEGIN\n"
            . "  IF OLD.est_conduite = 1 THEN\n"
            . '    ' . $refus('Conduite : la matiere de Conduite ne peut pas etre supprimee.') . "\n"
            . "  END IF;\n"
            . "END";

        $defs = [
            'trg_matieres_conduite_bi' => ['timing' => 'BEFORE', 'event' => 'INSERT', 'body' => $bodyInsert],
            'trg_matieres_conduite_bu' => ['timing' => 'BEFORE', 'event' => 'UPDATE', 'body' => $bodyUpdate],
            'trg_matieres_conduite_bd' => ['timing' => 'BEFORE', 'event' => 'DELETE', 'body' => $bodyDelete],
        ];
        foreach ($defs as $name => &$d) {
            $d['sql'] = "CREATE TRIGGER `{$name}` {$d['timing']} {$d['event']} ON `matieres` FOR EACH ROW\n{$d['body']}";
        }
        unset($d);
        return $defs;
    }

    /**
     * Mode strict (CONDUITE_PROTECTION_REQUIRED) : sans protection SQL opérationnelle, la régénération pour publication
     * et la publication sont bloquées. ACTIVÉ PAR DÉFAUT EN PRODUCTION (APP_ENV absent ou « production »), désactivable
     * explicitement (CONDUITE_PROTECTION_REQUIRED=0) — notamment en développement.
     */
    public static function strictRequis(?array $env = null): bool
    {
        $lire = static function (string $cle) use ($env) {
            $v = $env !== null ? ($env[$cle] ?? null) : ($_ENV[$cle] ?? getenv($cle));
            return ($v === false || $v === null) ? null : trim((string)$v);
        };
        $explicite = $lire('CONDUITE_PROTECTION_REQUIRED');
        if ($explicite !== null && $explicite !== '') {
            return in_array(strtolower($explicite), ['1', 'true', 'yes', 'on'], true);
        }
        return strtolower($lire('APP_ENV') ?: 'production') === 'production';
    }

    /** Normalise un corps pour la comparaison : espaces, retours à la ligne et casse des mots-clés sans effet. */
    public static function normalise(string $body): string
    {
        return strtolower(trim((string)preg_replace('/\s+/', ' ', $body)));
    }

    /**
     * Contrôle de métadonnées (lecture seule).
     *
     * @return array{etat:string,ok:bool,details:array<string,string>,message:string}
     */
    public static function check(PDO $pdo): array
    {
        $details = [];
        $stmt = $pdo->query(
            "SELECT trigger_name AS n, action_timing AS t, event_manipulation AS e, event_object_table AS o, action_statement AS s
               FROM information_schema.triggers
              WHERE trigger_schema = DATABASE() AND trigger_name LIKE 'trg\\_matieres\\_conduite\\_%'"
        );
        $trouves = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $trouves[$r['n']] = $r; }

        foreach (self::definitions() as $name => $def) {
            $r = $trouves[$name] ?? null;
            if ($r === null) { $details[$name] = 'absent'; continue; }
            if (strtoupper($r['t']) !== $def['timing'] || strtoupper($r['e']) !== $def['event'] || strtolower($r['o']) !== 'matieres') {
                $details[$name] = 'moment, événement ou table inattendu';
                continue;
            }
            $details[$name] = self::normalise($r['s']) === self::normalise($def['body']) ? 'ok' : 'corps modifié';
        }
        foreach ($trouves as $n => $_) {
            if (!isset(self::definitions()[$n])) { $details[$n] = 'déclencheur inattendu'; }
        }

        $ok = $details !== [] && count(array_filter($details, fn($v) => $v !== 'ok')) === 0;
        return [
            'etat'    => $ok ? self::OPERATIONNELLE : self::NON_OPERATIONNELLE,
            'ok'      => $ok,
            'details' => $details,
            'message' => $ok ? 'Protection SQL de la Conduite : opérationnelle.'
                             : 'Protection SQL de la Conduite : NON opérationnelle (' . self::resume($details) . ').',
        ];
    }

    private static function resume(array $details): string
    {
        $mauvais = array_filter($details, fn($v) => $v !== 'ok');
        $out = [];
        foreach ($mauvais as $n => $v) { $out[] = "$n : $v"; }
        return implode(' ; ', $out);
    }

    /**
     * Pré-contrôle d'environnement : droit TRIGGER, et (journal binaire inactif OU SUPER OU log_bin_trust_function_creators = 1).
     * @return string|null message d'échec, ou null si la création est possible
     */
    public static function precontrole(PDO $pdo): ?string
    {
        $grants = [];
        foreach ($pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN) as $g) { $grants[] = strtoupper((string)$g); }
        $texte = implode(' | ', $grants);
        $tout  = str_contains($texte, 'ALL PRIVILEGES');
        $super = $tout || str_contains($texte, 'SUPER');
        $droitTrigger = $tout || $super || str_contains($texte, 'TRIGGER');
        if (!$droitTrigger) {
            return 'le compte MySQL n\'a pas le droit TRIGGER';
        }
        $logBin = (int)$pdo->query('SELECT @@log_bin')->fetchColumn();
        $trust  = (int)$pdo->query('SELECT @@log_bin_trust_function_creators')->fetchColumn();
        if ($logBin === 1 && !$super && $trust !== 1) {
            return 'le journal binaire est actif, le compte n\'a pas SUPER et log_bin_trust_function_creators vaut 0 (erreur 1419 probable)';
        }
        return null;
    }

    /**
     * Installation idempotente, tout ou rien : pré-contrôle, recréation des trois déclencheurs, vérification finale.
     * En cas d'échec, les déclencheurs déjà créés sont supprimés et une exception explicite est levée.
     *
     * @param callable|null $avantCreation hook de test, appelé avec le rang (1..3) avant chaque création ; peut lever
     */
    public static function installer(PDO $pdo, ?callable $avantCreation = null): void
    {
        $echec = static fn(string $cause): \RuntimeException => new \RuntimeException(
            'T050 ÉCHEC — la protection SQL de la Conduite N\'EST PAS opérationnelle : ' . $cause
            . '. Corriger la cause (droits MySQL, log_bin_trust_function_creators) puis relancer database/migrate.php ; '
            . 'vérifier avec database/tools/conduite_protection.php --check.'
        );

        $colonne = (int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'matieres' AND column_name = 'est_conduite'"
        )->fetchColumn();
        if ($colonne === 0) {
            throw $echec('la colonne matieres.est_conduite est absente (appliquer T048 d\'abord)');
        }
        if (($cause = self::precontrole($pdo)) !== null) {
            throw $echec($cause);
        }

        self::supprimer($pdo);
        $rang = 0;
        try {
            foreach (self::definitions() as $def) {
                $rang++;
                if ($avantCreation !== null) { $avantCreation($rang); }
                $pdo->exec($def['sql']);
            }
            $etat = self::check($pdo);
            if (!$etat['ok']) {
                throw new \RuntimeException('vérification finale négative : ' . self::resume($etat['details']));
            }
        } catch (\Throwable $e) {
            self::supprimer($pdo);
            throw $echec($e->getMessage());
        }
    }

    /** Supprime les trois déclencheurs (retour arrière / nettoyage). */
    public static function supprimer(PDO $pdo): void
    {
        foreach (array_keys(self::definitions()) as $name) {
            $pdo->exec("DROP TRIGGER IF EXISTS `{$name}`");
        }
    }
}
