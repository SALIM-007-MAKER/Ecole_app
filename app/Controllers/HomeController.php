<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\UserModel;
use App\Models\EleveModel;
use App\Models\ClasseModel;
use App\Models\ProfesseurModel;
use App\Models\EnseignementModel;

class HomeController extends Controller
{
    public function index(): void
    {
        if (Session::isLogged()) {
            $this->redirect(BASE_URL . '/dashboard');
        }

        $this->render('home/index', ['title' => 'Bienvenue'], 'none');
    }

    public function dashboard(): void
    {
        $this->requireAuth();

        $user = Session::getUser();

        // Espaces dédiés parent & élève
        if ($user['role'] === 'parent') {
            $this->redirect(BASE_URL . '/parent/dashboard');
            return;
        }
        if ($user['role'] === 'eleve') {
            $this->redirect(BASE_URL . '/eleve/dashboard');
            return;
        }

        $this->render('home/dashboard', [
            'title' => 'Tableau de bord',
            'user'  => $user,
            'stats' => $this->getStats($user),
        ]);
    }

    private function getStats(array $user): array
    {
        $role        = $user['role'];
        $eleveModel  = new EleveModel();
        $classeModel = new ClasseModel();
        $userModel   = new UserModel();

        $base = [
            'eleves'         => 0,
            'classes'        => 0,
            'enseignants'    => 0,
            'absences_today' => 0,
            'parents'        => 0,
            'eleves_actifs'  => 0,
            // Spécifiques enseignant
            'prof'           => null,
            'enseignements'  => [],
            'mes_classes'    => 0,
            'mes_matieres'   => 0,
            'mes_eleves'     => 0,
        ];

        if (in_array($role, ['admin', 'directeur', 'secretaire', 'comptable'], true)) {
            $base['eleves']        = $eleveModel->count();
            $base['eleves_actifs'] = $eleveModel->count('actif = 1');
            $base['classes']       = $classeModel->count();
            $base['enseignants']   = $userModel->countByRole('enseignant');
            $base['parents']       = $userModel->countByRole('parent');

            try {
                $row = $eleveModel->queryOne(
                    "SELECT COUNT(*) AS n FROM `vs_absences`
                     WHERE `date_absence` = CURDATE() AND `type` = 'absence' AND `deleted_at` IS NULL"
                );
                $base['absences_today'] = $row ? (int)$row->n : 0;
            } catch (\Throwable) {
                $base['absences_today'] = 0;
            }
        }

        // Données graphiques (admin & directeur)
        if (in_array($role, ['admin', 'directeur'], true)) {
            try {
                $base['absences_mois'] = $eleveModel->query(
                    "SELECT MONTH(date_absence) AS m, YEAR(date_absence) AS y, COUNT(*) AS n
                     FROM `vs_absences`
                     WHERE date_absence >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
                       AND type = 'absence' AND deleted_at IS NULL
                     GROUP BY y, m ORDER BY y ASC, m ASC"
                );
            } catch (\Throwable) {
                $base['absences_mois'] = [];
            }
            $base['roles_chart'] = [
                'Élèves'      => $base['eleves'],
                'Parents'     => $base['parents'],
                'Enseignants' => $base['enseignants'],
                'Secrétaires' => $userModel->countByRole('secretaire'),
                'Comptables'  => $userModel->countByRole('comptable'),
            ];
        }

        if ($role === 'enseignant') {
            $profModel = new ProfesseurModel();
            $ensModel  = new EnseignementModel();
            $annee     = date('Y') . '-' . (date('Y') + 1);

            $prof = $profModel->findByUserId($user['id']);
            if ($prof) {
                $enseignements = $ensModel->findByProfesseurId($prof->id, $annee);
                $classeIds     = array_unique(array_column($enseignements, 'classe_id'));
                $matiereIds    = array_unique(array_column($enseignements, 'matiere_id'));

                $base['prof']          = $prof;
                $base['enseignements'] = $enseignements;
                $base['mes_classes']   = count($classeIds);
                $base['mes_matieres']  = count($matiereIds);
                $base['mes_eleves']    = $profModel->countElevesForUser($user['id'], $annee);
            }

            $base['eleves']  = $eleveModel->count('actif = 1');
            $base['classes'] = $classeModel->count();
        }

        return $base;
    }
}
