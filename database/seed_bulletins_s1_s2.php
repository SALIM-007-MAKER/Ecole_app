<?php

declare(strict_types=1);

/** Jeu de données réel et idempotent pour tester les bulletins V2 S1/S2. */
define('ROOT_PATH', dirname(__DIR__));
require ROOT_PATH . '/core/Database.php';

$pdo = \Core\Database::getInstance()->getConnection();
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

$etablissementId = 1;
$annee = '2026-2027';
$classeCode = 'TEST-BUL-2026-2027';
$adminId = (int)$pdo->query("SELECT id FROM users WHERE etablissement_id = 1 AND role IN ('admin', 'directeur') AND actif = 1 ORDER BY id LIMIT 1")->fetchColumn();
$professeurId = (int)$pdo->query('SELECT id FROM professeurs WHERE etablissement_id = 1 AND actif = 1 ORDER BY id LIMIT 1')->fetchColumn();
if ($adminId === 0 || $professeurId === 0) throw new RuntimeException('Administrateur ou enseignant actif introuvable.');

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT id FROM annees_scolaires WHERE libelle = ? LIMIT 1');
    $stmt->execute([$annee]);
    if (!$stmt->fetchColumn()) {
        $pdo->prepare('INSERT INTO annees_scolaires (libelle, date_debut, date_fin, active) VALUES (?, ?, ?, 0)')
            ->execute([$annee, '2026-09-01', '2027-06-30']);
    }

    $stmt = $pdo->prepare('SELECT id FROM classes WHERE etablissement_id = ? AND description = ? LIMIT 1');
    $stmt->execute([$etablissementId, $classeCode]);
    $classeId = (int)$stmt->fetchColumn();
    if ($classeId === 0) {
        $pdo->prepare('INSERT INTO classes (etablissement_id, nom, niveau, annee_scolaire, max_eleves, description) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$etablissementId, 'TB-01', 'Seconde', $annee, 30, $classeCode]);
        $classeId = (int)$pdo->lastInsertId();
    }

    $periods = [
        1 => ['Premier Semestre', '2026-09-01', '2027-01-31', 'cloturee', 0, 1],
        2 => ['Deuxième Semestre', '2027-02-01', '2027-06-30', 'ouverte', 1, 2],
    ];
    $periodeIds = [];
    foreach ($periods as $numero => [$nom, $debut, $fin, $statut, $active, $ordre]) {
        $stmt = $pdo->prepare('SELECT id FROM periodes_scolaires WHERE annee_scolaire = ? AND type_periode = ? AND numero = ? LIMIT 1');
        $stmt->execute([$annee, 'semestre', $numero]);
        $periodeId = (int)$stmt->fetchColumn();
        if ($periodeId === 0) {
            $pdo->prepare('INSERT INTO periodes_scolaires (annee_scolaire, type_periode, numero, nom, date_debut, date_fin, statut, is_active, notes_saisie_ouverte, ordre, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)')
                ->execute([$annee, 'semestre', $numero, $nom, $debut, $fin, $statut, $active, $ordre, $adminId]);
            $periodeId = (int)$pdo->lastInsertId();
        }
        $periodeIds[$numero] = $periodeId;
    }

    $eleves = [
        ['Diallo', 'Aminata', 'F'], ['Moussa', 'Ibrahim', 'M'], ['Garba', 'Salma', 'F'],
        ['Issa', 'Yacouba', 'M'], ['Maïga', 'Aïcha', 'F'], ['Oumarou', 'Hassan', 'M'],
        ['Adamou', 'Fati', 'F'], ['Boubacar', 'Mahamane', 'M'], ['Abdou', 'Zara', 'F'],
        ['Sani', 'Karim', 'M'], ['Idi', 'Mariam', 'F'], ['Toudou', 'Abdoul', 'M'],
    ];
    $eleveIds = [];
    foreach ($eleves as $index => [$nom, $prenom, $sexe]) {
        $matricule = sprintf('TESTBUL26-%02d', $index + 1);
        $stmt = $pdo->prepare('SELECT id FROM eleves WHERE matricule = ? LIMIT 1');
        $stmt->execute([$matricule]);
        $eleveId = (int)$stmt->fetchColumn();
        if ($eleveId === 0) {
            $pdo->prepare('INSERT INTO eleves (etablissement_id, matricule, nom, prenom, date_naissance, sexe, adresse, telephone, email, classe_id, actif) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)')
                ->execute([$etablissementId, $matricule, $nom, $prenom, sprintf('2009-%02d-%02d', ($index % 12) + 1, ($index % 27) + 1), $sexe, 'Niamey', '+227 80 10 ' . sprintf('%02d', $index + 1) . ' 26', strtolower('testbul' . ($index + 1)) . '@example.test', $classeId]);
            $eleveId = (int)$pdo->lastInsertId();
        } else {
            $pdo->prepare('UPDATE eleves SET classe_id = ?, actif = 1 WHERE id = ?')->execute([$classeId, $eleveId]);
        }
        $eleveIds[] = $eleveId;
        $check = $pdo->prepare('SELECT id FROM inscriptions WHERE eleve_id = ? AND annee_scolaire = ? LIMIT 1');
        $check->execute([$eleveId, $annee]);
        if (!(int)$check->fetchColumn()) {
            $pdo->prepare("INSERT INTO inscriptions (eleve_id, classe_id, annee_scolaire, statut, notes, inscription_par, valide_par, valide_le) VALUES (?, ?, ?, 'validee', ?, ?, ?, NOW())")
                ->execute([$eleveId, $classeId, $annee, 'Jeu de données bulletin S1/S2', $adminId, $adminId]);
        }
    }

    $matieres = [
        'Français' => [-0.2, 'litteraire'], 'Mathématiques' => [-0.7, 'scientifique'],
        'Anglais' => [0.1, 'litteraire'], 'Histoire-Géographie' => [-0.1, 'litteraire'],
        'S.V.T' => [0.3, 'scientifique'], 'Physique-Chimie' => [-0.5, 'scientifique'],
        'Education Physique' => [1.0, 'autre'], 'Conduite' => [0.6, 'autre'],
    ];
    $subjectIds = [];
    foreach ($matieres as $nom => $_) {
        $stmt = $pdo->prepare('SELECT id FROM matieres WHERE etablissement_id = ? AND nom = ? LIMIT 1');
        $stmt->execute([$etablissementId, $nom]);
        $subjectIds[$nom] = (int)$stmt->fetchColumn();
        if ($subjectIds[$nom] === 0) throw new RuntimeException("Matière manquante: {$nom}");
        $pdo->prepare('INSERT IGNORE INTO enseignements (etablissement_id, professeur_id, matiere_id, classe_id, annee_scolaire) VALUES (?, ?, ?, ?, ?)')
            ->execute([$etablissementId, $professeurId, $subjectIds[$nom], $classeId, $annee]);
    }

    $types = $pdo->query("SELECT code, id FROM types_evaluations WHERE code IN ('devoir', 'examen') AND actif = 1")->fetchAll(\PDO::FETCH_KEY_PAIR);
    if (!isset($types['devoir'], $types['examen'])) throw new RuntimeException('Types devoir/examen manquants.');
    $profiles = [17.6, 15.8, 14.7, 13.6, 12.4, 11.3, 10.1, 9.2, 8.1, 7.0, 5.9, 4.8];
    $appreciations = ['Excellent travail', 'Très bon travail', 'Bon travail', 'Assez bien', 'Efforts réguliers', 'Résultats corrects', 'Travail à poursuivre', 'Doit progresser', 'Résultats fragiles', 'Efforts indispensables', 'Difficultés à combler', 'Accompagnement nécessaire'];
    $noteCount = 0;

    foreach ($periodeIds as $semestre => $periodeId) {
        foreach ($matieres as $nom => [$offset]) {
            foreach (['devoir' => [1.0, 12], 'examen' => [2.0, 20]] as $code => [$coef, $jour]) {
                $libelle = "[TEST BULLETIN {$annee} S{$semestre}] {$nom} - {$code}";
                $find = $pdo->prepare('SELECT id FROM evaluations WHERE libelle = ? LIMIT 1');
                $find->execute([$libelle]);
                $evaluationId = (int)$find->fetchColumn();
                if ($evaluationId === 0) {
                    $pdo->prepare("INSERT INTO evaluations (periode_scolaire_id, type_evaluation_id, matiere_id, classe_id, enseignant_id, libelle, description, date_evaluation, coefficient, note_max, statut, notes_saisie_ouverte, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 20, 'publiee', 0, ?)")
                        ->execute([$periodeId, $types[$code], $subjectIds[$nom], $classeId, $professeurId, $libelle, 'Évaluation du jeu de données bulletin', sprintf('202%d-%02d-%02d', 6 + ($semestre === 2 ? 1 : 0), $semestre === 1 ? 11 : 4, $jour), $coef, $adminId]);
                    $evaluationId = (int)$pdo->lastInsertId();
                }
                foreach ($eleveIds as $i => $eleveId) {
                    $variation = (($i * 3 + strlen($nom) + $semestre) % 5 - 2) * 0.22 + ($code === 'examen' ? 0.18 : -0.12) + ($semestre === 2 ? 0.25 : 0);
                    $valeur = max(0, min(20, round($profiles[$i] + $offset + $variation, 2)));
                    $exists = $pdo->prepare('SELECT id FROM notes_v2 WHERE evaluation_id = ? AND eleve_id = ? LIMIT 1');
                    $exists->execute([$evaluationId, $eleveId]);
                    $noteId = (int)$exists->fetchColumn();
                    if ($noteId) {
                        $pdo->prepare("UPDATE notes_v2 SET valeur = ?, est_absent = 0, commentaire = ?, statut = 'publiee', updated_at = NOW() WHERE id = ?")
                            ->execute([$valeur, 'Jeu de données bulletin', $noteId]);
                    } else {
                        $pdo->prepare("INSERT INTO notes_v2 (evaluation_id, eleve_id, valeur, est_absent, commentaire, statut, created_by) VALUES (?, ?, ?, 0, ?, 'publiee', ?)")
                            ->execute([$evaluationId, $eleveId, $valeur, 'Jeu de données bulletin', $adminId]);
                    }
                    $noteCount++;
                }
            }
            foreach ($eleveIds as $i => $eleveId) {
                $find = $pdo->prepare('SELECT id FROM appreciations_matiere WHERE eleve_id = ? AND matiere_id = ? AND periode_id = ? LIMIT 1');
                $find->execute([$eleveId, $subjectIds[$nom], $periodeId]);
                $id = (int)$find->fetchColumn();
                if ($id) {
                    $pdo->prepare('UPDATE appreciations_matiere SET texte = ?, professeur_id = ? WHERE id = ?')->execute([$appreciations[$i], $professeurId, $id]);
                } else {
                    $pdo->prepare('INSERT INTO appreciations_matiere (eleve_id, matiere_id, periode_id, classe_id, professeur_id, texte, etablissement_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                        ->execute([$eleveId, $subjectIds[$nom], $periodeId, $classeId, $professeurId, $appreciations[$i], $etablissementId, $adminId]);
                }
            }
        }
    }
    $pdo->commit();
    echo "Classe #{$classeId}; 12 élèves; 8 matières; 32 évaluations; {$noteCount} notes S1/S2 insérées ou mises à jour." . PHP_EOL;
} catch (\Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
