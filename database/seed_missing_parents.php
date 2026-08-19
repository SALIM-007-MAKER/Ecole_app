<?php

declare(strict_types=1);

/**
 * Rattache un responsable légal de démonstration à chaque élève qui n'a pas
 * encore de parent. Le script est idempotent : il ne modifie jamais un
 * parent_id existant et ne crée pas de doublon pour une adresse déjà créée.
 *
 * Les coordonnées créées sont volontairement des données de démonstration à
 * remplacer depuis le module Familles avant une mise en production.
 */
define('ROOT_PATH', dirname(__DIR__));

require ROOT_PATH . '/core/Database.php';

$pdo = \Core\Database::getInstance()->getConnection();
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

$prenomsPere = ['Moussa', 'Ibrahim', 'Omar', 'Abdou', 'Issouf', 'Seydou', 'Ali', 'Hamidou', 'Mahamadou'];
$prenomsMere = ['Aïssata', 'Mariama', 'Fatouma', 'Halima', 'Zainabou', 'Ramatou', 'Hadiza', 'Fati', 'Maimouna'];

$eleves = $pdo->query(
    'SELECT id, nom, prenom, sexe, etablissement_id
     FROM eleves
     WHERE parent_id IS NULL OR parent_id = 0
     ORDER BY id'
)->fetchAll(\PDO::FETCH_ASSOC);

$findUser = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$insertUser = $pdo->prepare(
    "INSERT INTO users (etablissement_id, nom, prenom, email, telephone, password, role, actif)
     VALUES (?, ?, ?, ?, ?, ?, 'parent', 1)"
);
$insertFamille = $pdo->prepare(
    'INSERT INTO familles (nom, adresse, ville, telephone, email, notes, actif)
     VALUES (?, ?, ?, ?, ?, ?, 1)'
);
$insertLien = $pdo->prepare(
    "INSERT INTO familles_eleves
        (famille_id, eleve_id, lien_parente, est_responsable_legal, est_contact_principal, est_contact_urgence, ordre)
     VALUES (?, ?, ?, 1, 1, 1, 1)
     ON DUPLICATE KEY UPDATE est_responsable_legal = 1, est_contact_principal = 1, est_contact_urgence = 1"
);
$updateEleve = $pdo->prepare('UPDATE eleves SET parent_id = ? WHERE id = ? AND (parent_id IS NULL OR parent_id = 0)');

$pdo->beginTransaction();
try {
    foreach ($eleves as $index => $eleve) {
        $isMere = $eleve['sexe'] === 'F';
        $prenomParent = ($isMere ? $prenomsMere : $prenomsPere)[$index % 9];
        $lien = $isMere ? 'mere' : 'pere';
        $email = 'parent.eleve-' . $eleve['id'] . '@demo.ecole.local';
        $telephone = '+227 90 00 ' . str_pad((string)$eleve['id'], 2, '0', STR_PAD_LEFT) . ' 01';

        $findUser->execute([$email]);
        $parentId = (int)$findUser->fetchColumn();
        if ($parentId === 0) {
            // Mot de passe aléatoire : le compte doit être réinitialisé avant
            // sa première utilisation, aucune crédentielle de démonstration
            // réutilisable n'est créée.
            $insertUser->execute([
                (int)$eleve['etablissement_id'],
                $eleve['nom'],
                $prenomParent,
                $email,
                $telephone,
                password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
            ]);
            $parentId = (int)$pdo->lastInsertId();
        }

        $insertFamille->execute([
            'Famille ' . $eleve['nom'],
            'Adresse à compléter',
            'Niamey',
            $telephone,
            $email,
            'Fiche de démonstration créée automatiquement ; coordonnées à vérifier.',
        ]);
        $familleId = (int)$pdo->lastInsertId();

        $insertLien->execute([$familleId, (int)$eleve['id'], $lien]);
        $updateEleve->execute([$parentId, (int)$eleve['id']]);

        echo "Élève #{$eleve['id']} : {$prenomParent} {$eleve['nom']} créé et associé." . PHP_EOL;
    }

    $pdo->commit();
    echo count($eleves) . ' parent(s) créé(s) et associé(s).' . PHP_EOL;
} catch (\Throwable $exception) {
    $pdo->rollBack();
    throw $exception;
}
