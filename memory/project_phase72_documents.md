---
name: project_phase72_documents
description: Phase 7.2 — Implémentation complète du module Documents V2, 76 fichiers, 44 routes, 16 events, 4 listeners, prêt Phase 7.3
metadata:
  type: project
---

Module Documents V2 entièrement implémenté le 2026-07-02.

**Why:** Module transversal servant 8 modules consumers (Scolarité, Académique, Finance, VS, RH, etc.)

**How to apply:** Le module est désactivé (`enabled: false`) — activer uniquement après Phase 7.3 (System Integration Review).

## Fichiers clés
- Migration SQL : `database/migrations/doc_001_documents.sql` (11 tables doc_*)
- Routes : `app/Modules/Documents/routes.php` (44 routes /v2/documents/*)
- Rapport : `DOCUMENTS_IMPLEMENTATION_REPORT.md`

## Dettes connues
- DT-D-001 : SignatureService stub V2 (DB OK, pas d'email/OTP)
- DT-D-002 : SearchIndexListener no-op V2 (FULLTEXT MySQL natif)
- DT-D-003 : verifierExpirations() doit être appelé par cron
- DT-D-004 : Vérifier que `base_path()` existe dans Core (utilisé dans DocumentService::telecharger)

## Architecture
- Namespace : `App\Modules\Documents\`
- Prefix tables : `doc_`
- StorageService : MIME via mime_content_type(), renomme en {module}_{random16hex}.{ext}
- Quota : OverflowException catchable spécifiquement
- Soft delete uniquement (deleted_at)
- Toutes écritures via Events uniquement (AuditListener)
