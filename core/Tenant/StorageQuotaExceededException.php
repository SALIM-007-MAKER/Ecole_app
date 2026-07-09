<?php

declare(strict_types=1);

namespace Core\Tenant;

/** Levée par TenantQuotaService::checkUploadAllowed() — MULTI_TENANT_V2_BLUEPRINT.md §12.4. */
final class StorageQuotaExceededException extends \RuntimeException
{
}
