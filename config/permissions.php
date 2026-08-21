<?php

/**
 * Permissions par rôle — chargées en session au moment de la connexion.
 * Évite les requêtes DB répétées pour la vérification des droits.
 */
return [
    'admin' => [
        'eleves.view', 'eleves.create', 'eleves.edit', 'eleves.delete',
        'enseignants.view', 'enseignants.create', 'enseignants.edit', 'enseignants.delete',
        'classes.view', 'classes.create', 'classes.edit', 'classes.delete',
        'matieres.view', 'matieres.create', 'matieres.edit', 'matieres.delete',
        'notes.view', 'notes.create', 'notes.edit', 'notes.delete', 'notes.view_own',
        'absences.view', 'absences.create', 'absences.edit', 'absences.view_own',
        'bulletins.view',
        'emploi_du_temps.view', 'emploi_du_temps.create', 'emploi_du_temps.edit',
        'annonces.view', 'annonces.create', 'annonces.edit', 'annonces.delete',
        'notifications.manage',
        'users.view', 'users.create', 'users.edit', 'users.delete',
        'rapports.view',
        // ── Paramètres — centre de configuration métier (T026) ───────────────────
        // Établissement & Apparence réutilisent branding.* ; Année scolaire réutilise
        // settings.general.* ; Matières et Utilisateurs réutilisent matieres.view/users.view.
        'branding.view', 'branding.update',
        'settings.general.view', 'settings.general.update',
        'settings.academique.view', 'settings.academique.update',
        'settings.notation.view', 'settings.notation.update',
        'settings.finances.view', 'settings.finances.update',
        'settings.documents.view', 'settings.documents.update',
        'settings.notifications.view', 'settings.notifications.update',
        'settings.securite.view', 'settings.securite.update',
        'settings.sauvegarde.view', 'settings.sauvegarde.update',
        'settings.avance.view', 'settings.avance.update',
        // Domaines personnalisés, Stockage & Quotas, Monitoring & Cache : retirés du
        // périmètre établissement (T026) — réservés à l'Administration de la
        // plateforme (Core\Platform\PlatformAuth, Super Administrateur uniquement).
        // ── Finance V2 ──────────────────────────────────────────────────────────
        'finance.dashboard.view',
        'finance.frais.view', 'finance.frais.manage', 'finance.frais.admin',
        'finance.factures.view', 'finance.factures.create', 'finance.factures.masse',
        'finance.factures.emettre', 'finance.factures.annuler', 'finance.factures.remise',
        'finance.paiements.view', 'finance.paiements.create',
        'finance.paiements.annuler', 'finance.paiements.trop_percu',
        'finance.decaissements.view', 'finance.decaissements.create',
        'finance.decaissements.valider', 'finance.decaissements.approuver', 'finance.decaissements.rejeter',
        'finance.caisse.view', 'finance.caisse.ouvrir', 'finance.caisse.fermer', 'finance.caisse.rapprocher',
        'finance.comptabilite.view', 'finance.comptabilite.saisir', 'finance.comptabilite.exercice',
        'finance.rapports.view', 'finance.rapports.export',
        // ── Scolarité V2 ────────────────────────────────────────────────────────
        'eleves.view.own',
        'inscriptions.view', 'inscriptions.create', 'inscriptions.update',
        'familles.view', 'familles.manage',
        // ── Académique V2 ───────────────────────────────────────────────────────
        'academique.periodes.view', 'academique.periodes.manage',
        'academique.evaluations.view', 'academique.evaluations.manage', 'academique.evaluations.admin',
        'academique.notes.view', 'academique.notes.manage', 'academique.notes.admin',
        'academique.moyennes.view', 'academique.moyennes.recalcul',
        'academique.classement.view', 'academique.classement.generer',
        'academique.bulletin.view', 'academique.bulletin.generer', 'academique.bulletin.publier',
        'academique.bulletin.admin',
        'academique.analytics.view',
        // ── Vie Scolaire V2 ─────────────────────────────────────────────────────
        'attendance.view', 'attendance.create', 'attendance.update', 'attendance.delete',
        'attendance.justify', 'attendance.validate',
        'attendance.session.view', 'attendance.session.create',
        'attendance.session.update', 'attendance.session.validate',
        // ── Retards V2 ──────────────────────────────────────────────────────────
        'late.view', 'late.create', 'late.update', 'late.justify',
        'late.validate', 'late.export',
        // ── Discipline V2 ────────────────────────────────────────────────────────
        'discipline.view', 'discipline.create', 'discipline.update',
        'discipline.validate', 'discipline.sanction', 'discipline.export',
        // ── Récompenses V2 ──────────────────────────────────────────────────────
        'reward.view', 'reward.create', 'reward.update', 'reward.validate', 'reward.export',
        // ── Emplois du temps V2 ──────────────────────────────────────────────────
        'timetable.view', 'timetable.create', 'timetable.update', 'timetable.publish', 'timetable.export',
        // ── Activités scolaires V2 ───────────────────────────────────────────────
        'activity.view', 'activity.create', 'activity.update', 'activity.validate', 'activity.publish', 'activity.export',
        // ── RH V2 — Employés ───────────────────────────────────────────────────
        'employee.view', 'employee.create', 'employee.update', 'employee.archive', 'employee.restore', 'employee.export',
        // ── RH V2 — Enseignants ─────────────────────────────────────────────────
        'teacher.view', 'teacher.create', 'teacher.update', 'teacher.assign', 'teacher.export',
        // ── RH V2 — Organisation ─────────────────────────────────────────────────
        'organization.view', 'organization.create', 'organization.update', 'organization.archive', 'organization.export',
        // ── RH V2 — Contrats ─────────────────────────────────────────────────────
        'contract.view', 'contract.create', 'contract.update', 'contract.renew', 'contract.terminate', 'contract.archive', 'contract.export',
        // ── RH V2 — Affectations ─────────────────────────────────────────────────
        'assignment.view', 'assignment.create', 'assignment.update', 'assignment.archive', 'assignment.export',
        // ── RH V2 — Présences ────────────────────────────────────────────────────
        'rh.presence.view', 'rh.presence.create', 'rh.presence.update', 'rh.presence.validate', 'rh.presence.export',
        // ── RH V2 — Congés ───────────────────────────────────────────────────────
        'leave.view', 'leave.create', 'leave.update', 'leave.approve', 'leave.reject', 'leave.cancel', 'leave.export',
        // ── RH V2 — Évaluations ──────────────────────────────────────────────────
        'evaluation.view', 'evaluation.create', 'evaluation.update',
        'evaluation.validate', 'evaluation.publish', 'evaluation.export',
        // ── RH V2 — Formations ───────────────────────────────────────────────────
        'training.view', 'training.create', 'training.update',
        'training.enroll', 'training.validate', 'training.export', 'training.manage_catalog',
        // ── RH V2 — Documents ────────────────────────────────────────────────────
        'hr_document.view', 'hr_document.create', 'hr_document.update',
        'hr_document.archive', 'hr_document.export',
        // ── Documents V2 ─────────────────────────────────────────────────────────
        'document.view', 'document.view_all', 'document.view_secret',
        'document.create', 'document.update', 'document.version',
        'document.archive', 'document.delete', 'document.restore',
        'document.share', 'document.export', 'document.sign', 'document.admin',
        'folder.create', 'folder.delete',
        // ── Communication V2 ─────────────────────────────────────────────────────
        'communication.view', 'communication.send', 'communication.broadcast',
        'communication.manage_templates', 'communication.manage_groups',
        'communication.campaign', 'communication.admin', 'communication.view_all',
        // ── Bibliothèque V2 ──────────────────────────────────────────────────────
        'biblio.view', 'biblio.search', 'biblio.borrow', 'biblio.reserve',
        'biblio.manage_loans', 'biblio.manage_catalogue', 'biblio.manage_exemplaires',
        'biblio.manage_penalties', 'biblio.inventory', 'biblio.analytics', 'biblio.admin',
        // ── Inventaire V2 ────────────────────────────────────────────────────────
        'inventaire.view', 'inventaire.create', 'inventaire.edit', 'inventaire.delete',
        'inventaire.stock.view', 'inventaire.stock.manage',
        'inventaire.commande.view', 'inventaire.commande.create',
        'inventaire.commande.validate', 'inventaire.commande.receive',
        'inventaire.affectation.view', 'inventaire.affectation.create',
        'inventaire.affectation.return', 'inventaire.affectation.lost',
        'inventaire.maintenance.view', 'inventaire.maintenance.create', 'inventaire.maintenance.edit',
        'inventaire.physique.view', 'inventaire.physique.create',
        'inventaire.physique.count', 'inventaire.physique.close',
        'inventaire.reports',
        // ── Portails V2 ──────────────────────────────────────────────────────────
        'portal.admin.access', 'portal.direction.access', 'portal.enseignant.access',
        'portal.eleve.access', 'portal.parent.access', 'portal.comptabilite.access', 'portal.rh.access',
    ],
    'directeur' => [
        'eleves.view', 'eleves.create', 'eleves.edit', 'eleves.delete',
        'enseignants.view', 'enseignants.create', 'enseignants.edit', 'enseignants.delete',
        'classes.view', 'classes.create', 'classes.edit', 'classes.delete',
        'matieres.view', 'matieres.create', 'matieres.edit', 'matieres.delete',
        'notes.view', 'notes.create', 'notes.edit', 'notes.delete',
        'absences.view', 'absences.create', 'absences.edit',
        'bulletins.view',
        'emploi_du_temps.view', 'emploi_du_temps.create', 'emploi_du_temps.edit',
        'annonces.view', 'annonces.create', 'annonces.edit', 'annonces.delete',
        'notifications.manage',
        'users.view', 'users.create', 'users.edit',
        'rapports.view',
        // ── Paramètres — centre de configuration métier (T026) ───────────────────
        // Établissement & Apparence réutilisent branding.* ; Année scolaire réutilise
        // settings.general.* ; Matières et Utilisateurs réutilisent matieres.view/users.view.
        'branding.view', 'branding.update',
        'settings.general.view', 'settings.general.update',
        'settings.academique.view', 'settings.academique.update',
        'settings.notation.view', 'settings.notation.update',
        'settings.finances.view', 'settings.finances.update',
        'settings.documents.view', 'settings.documents.update',
        'settings.notifications.view', 'settings.notifications.update',
        // Sécurité, Sauvegarde et Avancé : retirées du rôle directeur le 21/08/2026 —
        // paramétrage technique réservé à Administrateur/Super-Admin (voir
        // docs/fonctionnel/GUIDE_DIRECTION.md § 10).
        // Domaines personnalisés, Stockage & Quotas, Monitoring & Cache : retirés du
        // périmètre établissement (T026) — réservés à l'Administration de la
        // plateforme (Core\Platform\PlatformAuth, Super Administrateur uniquement).
        // ── Finance V2 ──────────────────────────────────────────────────────────
        'finance.dashboard.view',
        'finance.frais.view', 'finance.frais.manage', 'finance.frais.admin',
        'finance.factures.view', 'finance.factures.create', 'finance.factures.masse',
        'finance.factures.emettre', 'finance.factures.annuler', 'finance.factures.remise',
        'finance.paiements.view', 'finance.paiements.create',
        'finance.paiements.annuler', 'finance.paiements.trop_percu',
        'finance.decaissements.view', 'finance.decaissements.create',
        'finance.decaissements.valider', 'finance.decaissements.approuver', 'finance.decaissements.rejeter',
        'finance.caisse.view', 'finance.caisse.ouvrir', 'finance.caisse.fermer', 'finance.caisse.rapprocher',
        'finance.comptabilite.view', 'finance.comptabilite.exercice',
        'finance.rapports.view', 'finance.rapports.export',
        // ── Académique V2 ───────────────────────────────────────────────────────
        'academique.periodes.view', 'academique.periodes.manage',
        'academique.evaluations.view', 'academique.evaluations.manage', 'academique.evaluations.admin',
        'academique.notes.view', 'academique.notes.manage', 'academique.notes.admin',
        'academique.moyennes.view', 'academique.moyennes.recalcul',
        'academique.classement.view', 'academique.classement.generer',
        'academique.bulletin.view', 'academique.bulletin.generer', 'academique.bulletin.publier',
        'academique.bulletin.admin',
        'academique.analytics.view',
        // ── Vie Scolaire V2 ─────────────────────────────────────────────────────
        'attendance.view', 'attendance.create', 'attendance.update', 'attendance.delete',
        'attendance.justify', 'attendance.validate',
        'attendance.session.view', 'attendance.session.create',
        'attendance.session.update', 'attendance.session.validate',
        // ── Retards V2 ──────────────────────────────────────────────────────────
        'late.view', 'late.create', 'late.update', 'late.justify',
        'late.validate', 'late.export',
        // ── Discipline V2 ────────────────────────────────────────────────────────
        'discipline.view', 'discipline.create', 'discipline.update',
        'discipline.validate', 'discipline.sanction', 'discipline.export',
        // ── Récompenses V2 ──────────────────────────────────────────────────────
        'reward.view', 'reward.create', 'reward.update', 'reward.validate', 'reward.export',
        // ── Emplois du temps V2 ──────────────────────────────────────────────────
        'timetable.view', 'timetable.create', 'timetable.update', 'timetable.publish', 'timetable.export',
        // ── Activités scolaires V2 ───────────────────────────────────────────────
        'activity.view', 'activity.create', 'activity.update', 'activity.validate', 'activity.publish', 'activity.export',
        // ── RH V2 — Employés ───────────────────────────────────────────────────
        'employee.view', 'employee.create', 'employee.update', 'employee.archive', 'employee.restore', 'employee.export',
        // ── RH V2 — Enseignants ─────────────────────────────────────────────────
        'teacher.view', 'teacher.create', 'teacher.update', 'teacher.assign', 'teacher.export',
        // ── RH V2 — Organisation ─────────────────────────────────────────────────
        'organization.view', 'organization.create', 'organization.update', 'organization.archive', 'organization.export',
        // ── RH V2 — Contrats ─────────────────────────────────────────────────────
        'contract.view', 'contract.create', 'contract.update', 'contract.renew', 'contract.terminate', 'contract.archive', 'contract.export',
        // ── RH V2 — Affectations ─────────────────────────────────────────────────
        'assignment.view', 'assignment.create', 'assignment.update', 'assignment.archive', 'assignment.export',
        // ── RH V2 — Présences ────────────────────────────────────────────────────
        'rh.presence.view', 'rh.presence.create', 'rh.presence.update', 'rh.presence.validate', 'rh.presence.export',
        // ── RH V2 — Congés ───────────────────────────────────────────────────────
        'leave.view', 'leave.create', 'leave.update', 'leave.approve', 'leave.reject', 'leave.cancel', 'leave.export',
        // ── RH V2 — Évaluations ──────────────────────────────────────────────────
        'evaluation.view', 'evaluation.create', 'evaluation.update',
        'evaluation.validate', 'evaluation.publish', 'evaluation.export',
        // ── RH V2 — Formations ───────────────────────────────────────────────────
        'training.view', 'training.create', 'training.update',
        'training.enroll', 'training.validate', 'training.export', 'training.manage_catalog',
        // ── RH V2 — Documents ────────────────────────────────────────────────────
        'hr_document.view', 'hr_document.create', 'hr_document.update',
        'hr_document.archive', 'hr_document.export',
        // ── Documents V2 ─────────────────────────────────────────────────────────
        'document.view', 'document.view_all', 'document.view_secret',
        'document.create', 'document.update', 'document.version',
        'document.archive', 'document.delete', 'document.restore',
        'document.share', 'document.export', 'document.sign', 'document.admin',
        'folder.create', 'folder.delete',
        // ── Communication V2 ─────────────────────────────────────────────────────
        'communication.view', 'communication.send', 'communication.broadcast',
        'communication.manage_templates', 'communication.manage_groups',
        'communication.campaign', 'communication.admin', 'communication.view_all',
        // ── Bibliothèque V2 ──────────────────────────────────────────────────────
        'biblio.view', 'biblio.search', 'biblio.borrow', 'biblio.reserve',
        'biblio.manage_loans', 'biblio.manage_catalogue', 'biblio.manage_exemplaires',
        'biblio.manage_penalties', 'biblio.inventory', 'biblio.analytics',
        // ── Inventaire V2 ────────────────────────────────────────────────────────
        'inventaire.view', 'inventaire.create', 'inventaire.edit',
        'inventaire.stock.view', 'inventaire.stock.manage',
        'inventaire.commande.view', 'inventaire.commande.create',
        'inventaire.commande.validate', 'inventaire.commande.receive',
        'inventaire.affectation.view', 'inventaire.affectation.create',
        'inventaire.affectation.return', 'inventaire.affectation.lost',
        'inventaire.maintenance.view', 'inventaire.maintenance.create', 'inventaire.maintenance.edit',
        'inventaire.physique.view', 'inventaire.physique.create',
        'inventaire.physique.count', 'inventaire.physique.close',
        'inventaire.reports',
        // ── Rapports & BI V2 ─────────────────────────────────────────────────────
        'rapports.dashboard.direction', 'rapports.dashboard.administration',
        'rapports.dashboard.scolarite', 'rapports.dashboard.academique',
        'rapports.dashboard.finance', 'rapports.dashboard.rh',
        'rapports.dashboard.vie_scolaire', 'rapports.dashboard.bibliotheque',
        'rapports.dashboard.inventaire',
        'rapports.kpis.voir', 'rapports.exporter', 'rapports.planifier',
        'rapports.api.analytics',
        // ── Portails V2 ──────────────────────────────────────────────────────────
        'portal.direction.access', 'portal.rh.access',
    ],
    'secretaire' => [
        'eleves.view', 'eleves.create', 'eleves.edit',
        'classes.view', 'matieres.view',
        'notes.view', 'bulletins.view',
        'absences.view', 'absences.create', 'absences.edit',
        'emploi_du_temps.view',
        'annonces.view', 'annonces.create', 'annonces.edit', 'annonces.delete',
        'rapports.view',
        // ── Finance V2 ──────────────────────────────────────────────────────────
        'finance.dashboard.view',
        'finance.frais.view',
        'finance.factures.view', 'finance.factures.create',
        'finance.paiements.view', 'finance.paiements.create',
        'finance.decaissements.view', 'finance.decaissements.create',
        'finance.caisse.view', 'finance.caisse.ouvrir', 'finance.caisse.fermer',
        'finance.rapports.view',
        // ── Vie Scolaire V2 ─────────────────────────────────────────────────────
        'attendance.view', 'attendance.create', 'attendance.update',
        'attendance.justify', 'attendance.validate',
        'attendance.session.view', 'attendance.session.create', 'attendance.session.update',
        // ── Retards V2 ──────────────────────────────────────────────────────────
        'late.view', 'late.create', 'late.update', 'late.justify', 'late.validate', 'late.export',
        // ── Discipline V2 ────────────────────────────────────────────────────────
        'discipline.view', 'discipline.create', 'discipline.update',
        'discipline.validate', 'discipline.sanction', 'discipline.export',
        // ── Récompenses V2 ──────────────────────────────────────────────────────
        'reward.view', 'reward.create', 'reward.update', 'reward.validate', 'reward.export',
        // ── Emplois du temps V2 ──────────────────────────────────────────────────
        'timetable.view', 'timetable.create', 'timetable.update', 'timetable.publish', 'timetable.export',
        // ── Activités scolaires V2 ───────────────────────────────────────────────
        'activity.view', 'activity.create', 'activity.update', 'activity.validate', 'activity.export',
        // ── RH V2 — Employés ───────────────────────────────────────────────────
        'employee.view', 'employee.export',
        // ── RH V2 — Enseignants ─────────────────────────────────────────────────
        'teacher.view', 'teacher.export',
        // ── RH V2 — Organisation ─────────────────────────────────────────────────
        'organization.view', 'organization.export',
        // ── RH V2 — Contrats ─────────────────────────────────────────────────────
        'contract.view', 'contract.export',
        // ── RH V2 — Affectations ─────────────────────────────────────────────────
        'assignment.view', 'assignment.export',
        // ── RH V2 — Présences ────────────────────────────────────────────────────
        'rh.presence.view', 'rh.presence.create', 'rh.presence.export',
        // ── RH V2 — Congés ───────────────────────────────────────────────────────
        'leave.view', 'leave.create', 'leave.update', 'leave.reject', 'leave.cancel', 'leave.export',
        // ── RH V2 — Évaluations ──────────────────────────────────────────────────
        'evaluation.view', 'evaluation.export',
        // ── RH V2 — Formations ───────────────────────────────────────────────────
        'training.view', 'training.create', 'training.update',
        'training.enroll', 'training.export',
        // ── RH V2 — Documents ────────────────────────────────────────────────────
        'hr_document.view', 'hr_document.create', 'hr_document.update', 'hr_document.export',
        // ── Documents V2 ─────────────────────────────────────────────────────────
        'document.view', 'document.view_all', 'document.create', 'document.update',
        'document.version', 'document.archive', 'document.delete', 'document.restore',
        'document.share', 'document.export',
        'folder.create', 'folder.delete',
        // ── Communication V2 ─────────────────────────────────────────────────────
        'communication.view', 'communication.send', 'communication.broadcast',
        'communication.manage_templates', 'communication.manage_groups', 'communication.campaign',
        // ── Bibliothèque V2 ──────────────────────────────────────────────────────
        'biblio.view', 'biblio.search', 'biblio.borrow', 'biblio.reserve',
        'biblio.manage_loans', 'biblio.manage_penalties',
        // ── Inventaire V2 ────────────────────────────────────────────────────────
        'inventaire.view', 'inventaire.create', 'inventaire.edit',
        'inventaire.stock.view',
        'inventaire.commande.view',
        'inventaire.affectation.view', 'inventaire.affectation.create',
        'inventaire.affectation.return',
        'inventaire.maintenance.view',
        'inventaire.physique.view', 'inventaire.physique.count',
        // ── Rapports & BI V2 ─────────────────────────────────────────────────────
        'rapports.dashboard.direction', 'rapports.dashboard.scolarite',
        'rapports.dashboard.academique', 'rapports.dashboard.finance',
        'rapports.dashboard.vie_scolaire',
        'rapports.kpis.voir', 'rapports.exporter',
        // ── Portails V2 ──────────────────────────────────────────────────────────
        'portal.comptabilite.access',
    ],
    'comptable' => [
        'eleves.view', 'classes.view', 'matieres.view', 'enseignants.view', 'rapports.view',
        'annonces.view',
        // ── Finance V2 ──────────────────────────────────────────────────────────
        'finance.dashboard.view',
        'finance.frais.view',
        'finance.factures.view', 'finance.factures.emettre',
        'finance.paiements.view', 'finance.paiements.create',
        'finance.paiements.annuler', 'finance.paiements.trop_percu',
        'finance.decaissements.view', 'finance.decaissements.valider',
        'finance.decaissements.approuver', 'finance.decaissements.rejeter',
        'finance.caisse.view', 'finance.caisse.ouvrir', 'finance.caisse.fermer', 'finance.caisse.rapprocher',
        'finance.comptabilite.view', 'finance.comptabilite.saisir',
        'finance.rapports.view', 'finance.rapports.export',
        // ── RH V2 — Présences ────────────────────────────────────────────────────
        'rh.presence.view', 'rh.presence.export',
        // ── RH V2 — Congés ───────────────────────────────────────────────────────
        'leave.view', 'leave.export',
        // ── RH V2 — Évaluations ──────────────────────────────────────────────────
        'evaluation.view',
        // ── RH V2 — Formations ───────────────────────────────────────────────────
        'training.view', 'training.export',
        // ── RH V2 — Documents ────────────────────────────────────────────────────
        'hr_document.view', 'hr_document.export',
        // ── Documents V2 ─────────────────────────────────────────────────────────
        'document.view', 'document.export',
        // ── Communication V2 ─────────────────────────────────────────────────────
        'communication.view', 'communication.send',
        // ── Bibliothèque V2 ──────────────────────────────────────────────────────
        'biblio.view', 'biblio.search', 'biblio.manage_penalties', 'biblio.analytics',
        // ── Inventaire V2 ────────────────────────────────────────────────────────
        'inventaire.view', 'inventaire.stock.view',
        'inventaire.commande.view', 'inventaire.commande.create',
        'inventaire.commande.validate', 'inventaire.commande.receive',
        'inventaire.reports',
        // ── Rapports & BI V2 ─────────────────────────────────────────────────────
        'rapports.dashboard.finance', 'rapports.dashboard.direction',
        'rapports.kpis.voir', 'rapports.exporter',
        // ── Portails V2 ──────────────────────────────────────────────────────────
        'portal.comptabilite.access',
    ],
    'enseignant' => [
        'eleves.view', 'classes.view', 'matieres.view',
        'notes.view', 'notes.create', 'notes.edit',
        'absences.view', 'absences.create', 'absences.edit',
        'bulletins.view',
        'emploi_du_temps.view', 'emploi_du_temps.view_own',
        'annonces.view',
        // ── Académique V2 ───────────────────────────────────────────────────────
        'academique.notes.view', 'academique.notes.manage',
        'academique.evaluations.view', 'academique.evaluations.manage',
        'academique.bulletin.view',
        // Écrans de résultats (ResultatsController) — parité avec l'accès V1
        // équivalent (bulletins.view/notes.view, déjà accordés ci-dessous),
        // sans quoi l'enseignant perdrait l'accès aux écrans classe/
        // classement/moyennes en migrant vers les routes V2.
        'academique.moyennes.view', 'academique.classement.view',
        // ── Vie Scolaire V2 ─────────────────────────────────────────────────────
        'attendance.view', 'attendance.create', 'attendance.validate',
        'attendance.session.view', 'attendance.session.create',
        'attendance.session.update', 'attendance.session.validate',
        // ── Retards V2 ──────────────────────────────────────────────────────────
        'late.view', 'late.create', 'late.update', 'late.validate', 'late.export',
        // ── Discipline V2 ────────────────────────────────────────────────────────
        'discipline.view', 'discipline.create', 'discipline.update',
        'discipline.validate', 'discipline.export',
        // ── Récompenses V2 ──────────────────────────────────────────────────────
        'reward.view', 'reward.create', 'reward.update', 'reward.export',
        // ── Emplois du temps V2 ──────────────────────────────────────────────────
        'timetable.view', 'timetable.create', 'timetable.update', 'timetable.export',
        // ── Activités scolaires V2 ───────────────────────────────────────────────
        'activity.view', 'activity.create', 'activity.update', 'activity.export',
        // ── RH V2 — Employés ───────────────────────────────────────────────────
        'employee.view.own',
        // ── RH V2 — Enseignants ─────────────────────────────────────────────────
        'teacher.view',
        // ── RH V2 — Organisation ─────────────────────────────────────────────────
        'organization.view',
        // ── RH V2 — Contrats ─────────────────────────────────────────────────────
        'contract.view',
        // ── RH V2 — Affectations ─────────────────────────────────────────────────
        'assignment.view',
        // ── RH V2 — Présences ────────────────────────────────────────────────────
        'rh.presence.view',
        // ── RH V2 — Congés ───────────────────────────────────────────────────────
        'leave.view', 'leave.create',
        // ── RH V2 — Évaluations ──────────────────────────────────────────────────
        'evaluation.view',
        // ── RH V2 — Formations ───────────────────────────────────────────────────
        'training.view',
        // ── RH V2 — Documents ────────────────────────────────────────────────────
        'hr_document.view',
        // ── Documents V2 ─────────────────────────────────────────────────────────
        'document.view', 'document.create', 'document.update', 'document.version',
        'document.share', 'document.export',
        'folder.create',
        // ── Communication V2 ─────────────────────────────────────────────────────
        'communication.view', 'communication.send', 'communication.broadcast',
        // ── Bibliothèque V2 ──────────────────────────────────────────────────────
        'biblio.view', 'biblio.search', 'biblio.borrow', 'biblio.reserve',
        // ── Rapports & BI V2 ─────────────────────────────────────────────────────
        'rapports.dashboard.academique', 'rapports.dashboard.vie_scolaire',
        'rapports.kpis.voir',
        // ── Portails V2 ──────────────────────────────────────────────────────────
        'portal.enseignant.access',
    ],
    'parent' => [
        'notes.view_own', 'absences.view_own', 'absences.justify', 'bulletins.view',
        'comptabilite.view_own',
        'emploi_du_temps.view_own',
        'annonces.view',
        // ── Finance V2 ──────────────────────────────────────────────────────────
        'finance.paiements.view.own',
        // ── Académique V2 ───────────────────────────────────────────────────────
        'academique.bulletin.view',
        // ── Vie Scolaire V2 ─────────────────────────────────────────────────────
        'attendance.view', 'attendance.justify',
        'attendance.session.view',
        // ── Retards V2 ──────────────────────────────────────────────────────────
        'late.view', 'late.justify',
        // ── Discipline V2 ────────────────────────────────────────────────────────
        'discipline.view',
        // ── Récompenses V2 ──────────────────────────────────────────────────────
        'reward.view',
        // ── Emplois du temps V2 ──────────────────────────────────────────────────
        'timetable.view',
        // ── Activités scolaires V2 ───────────────────────────────────────────────
        'activity.view',
        // ── Documents V2 ─────────────────────────────────────────────────────────
        'document.view',
        // ── Communication V2 ─────────────────────────────────────────────────────
        'communication.view', 'communication.send',
        // ── Bibliothèque V2 ──────────────────────────────────────────────────────
        'biblio.view', 'biblio.search', 'biblio.reserve',
        // ── Portails V2 ──────────────────────────────────────────────────────────
        'portal.parent.access',
    ],
    'eleve' => [
        'notes.view_own', 'absences.view_own', 'bulletins.view',
        'emploi_du_temps.view_own',
        'annonces.view',
        // ── Finance V2 ──────────────────────────────────────────────────────────
        'finance.paiements.view.own',
        // ── Académique V2 ───────────────────────────────────────────────────────
        'academique.bulletin.view',
        // ── Vie Scolaire V2 ─────────────────────────────────────────────────────
        'attendance.view',
        'attendance.session.view',
        // ── Retards V2 ──────────────────────────────────────────────────────────
        'late.view',
        // ── Discipline V2 ────────────────────────────────────────────────────────
        'discipline.view',
        // ── Récompenses V2 ──────────────────────────────────────────────────────
        'reward.view',
        // ── Emplois du temps V2 ──────────────────────────────────────────────────
        'timetable.view',
        // ── Activités scolaires V2 ───────────────────────────────────────────────
        'activity.view',
        // ── Documents V2 ─────────────────────────────────────────────────────────
        'document.view',
        // ── Communication V2 ─────────────────────────────────────────────────────
        'communication.view', 'communication.send',
        // ── Bibliothèque V2 ──────────────────────────────────────────────────────
        'biblio.view', 'biblio.search', 'biblio.borrow', 'biblio.reserve',
        // ── Portails V2 ──────────────────────────────────────────────────────────
        'portal.eleve.access',
    ],
];
