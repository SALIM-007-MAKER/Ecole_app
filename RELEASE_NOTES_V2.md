# Notes de version — SCOLARIS V2 / EduNova

## Vue d'ensemble

SCOLARIS V2 (EduNova) est une refonte architecturale majeure de la plateforme de
gestion scolaire, introduisant une architecture modulaire par domaine métier et
une infrastructure SaaS multi-établissements complète. Cette version fait suite à
une V1 mono-établissement, dont le socle fonctionnel (élèves, classes, notes,
absences, authentification) reste le cœur opérationnel démontré de la plateforme
aujourd'hui.

## Nouveautés principales

### Infrastructure SaaS Multi-Tenant
Un seul déploiement peut désormais servir plusieurs établissements, chacun avec
ses propres utilisateurs, son branding (logo, couleurs, police), son domaine
personnalisé, ses quotas de stockage, et une isolation stricte des données
vérifiée à chaque niveau (313 tests dédiés). Portail Super-Admin dédié à
l'exploitation de la plateforme (gestion des établissements, plans tarifaires,
supervision, sauvegardes). *Architecture prête, activation complète en production
en attente — voir `docs/technique/MULTI_TENANT.md` §2.*

### API REST Platform
API versionnée (`/api/v1/*`) avec authentification JWT et par clé API, limitation
de débit, pagination/filtrage/tri standardisés, webhooks, documentation OpenAPI
interactive (`/api/docs`). *Corrigée en Phase 15.1 après détection d'un défaut de
routage la rendant totalement inaccessible — pleinement fonctionnelle depuis.*

### Modules métier étendus (architecture posée)
Finance (facturation, encaissements, caisse, comptabilité PCG), Ressources
Humaines (contrats, congés, évaluations, formations), Vie Scolaire (présences,
discipline, emplois du temps, activités), Documents, Communication, Bibliothèque,
Inventaire, Rapports & BI, Portails par profil. *Code et architecture livrés et
documentés module par module ; mise en données réelle et activation en production
restent à finaliser pour la majorité d'entre eux — voir
`docs/technique/ARCHITECTURE_MODULES.md` §4 pour l'état précis de chaque module.*

### Académique
Notes, contrôles, moyennes automatiques, bulletins PDF, classements, tableaux de
bord analytiques — pleinement opérationnel.

### Progressive Web App
Installation sur mobile/desktop, fonctionnement hors ligne partiel, notifications
push.

### Modernisation de l'interface
Refonte visuelle complète (Tailwind CSS, iconographie Lucide) sur l'ensemble des
écrans.

## Ce qui n'est PAS encore prêt pour un déploiement commercial multi-établissements

Cette version pose une architecture SaaS complète et testée, mais certaines
conditions restent à lever avant un lancement commercial à grande échelle — voir
`RELEASE_CANDIDATE_RC1_REPORT.md` pour le détail complet :

- `TenantMiddleware` n'est pas encore activé sur le trafic HTTP réel.
- Les modules Finance, RH et Vie Scolaire nécessitent l'application de leurs
  migrations SQL avant tout usage réel.
- Les modules Documents, Communication, Bibliothèque, Inventaire, Rapports & BI et
  Portails restent désactivés, jamais exécutés contre une base réelle.
- Aucun dépôt de contrôle de version ni documentation d'installation
  consolidée n'existait avant cette phase de documentation (désormais fournie,
  voir `docs/`).

## Migration depuis la V1

Aucune action requise pour les fonctionnalités socle (élèves, classes, notes,
absences) — elles restent servies par le code V1 existant, inchangé dans son
comportement observable. Voir `docs/deploiement/MISE_A_JOUR.md`.

## Remerciements et méthode

Cette version a été développée par phases successives, chacune accompagnée d'un
blueprint de conception, d'un rapport d'implémentation, et — pour les jalons
majeurs — d'une revue d'intégration indépendante avant validation. 79+ documents
de suivi technique sont conservés à la racine du projet comme trace historique
complète des décisions de conception (voir `docs/` pour la documentation de
référence consolidée).
