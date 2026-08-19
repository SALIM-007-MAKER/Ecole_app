<?php
/**
 * Partial pour rendre la navigation dynamique
 * Utilisé dans le layout main.php
 */

use App\Services\MenuService;

$currentUri = $currentUri ?? ($_SERVER['REQUEST_URI'] ?? '');
$role = $currentUser['role'] ?? '';
$activeGroupId = MenuService::resolveActiveGroupId($menus ?? [], $currentUri);

if (empty($menus)): ?>
    <nav class="sidebar-nav" role="navigation" aria-label="Navigation principale">
        <a href="<?= BASE_URL ?>/dashboard" class="nav-item">
            <i class="fa-solid fa-house"></i>
            <span class="nav-item-text">Tableau de bord</span>
        </a>
    </nav>
<?php else: ?>

<nav class="sidebar-nav" role="navigation" aria-label="Navigation principale">
    <?php foreach ($menus as $item): ?>
        <?php
        $itemActive = MenuService::isMenuActive($item['url'] ?? '', $currentUri);
        $itemClass = MenuService::getMenuItemClass($item['url'] ?? '', $currentUri, false);
        $isGroup = !empty($item['children']) && is_array($item['children']);
        ?>

        <?php if ($isGroup): ?>
            <?php
            $groupActive = ($item['id'] ?? null) !== null && $item['id'] === $activeGroupId;
            ?>
            <button class="nav-item<?= $groupActive ? ' nav-active group-open' : '' ?>"
                    data-group="<?= htmlspecialchars($item['id'] ?? '', ENT_QUOTES) ?>"
                    data-default-open="<?= $groupActive ? 'true' : 'false' ?>"
                    title="<?= htmlspecialchars($item['label'] ?? '', ENT_QUOTES) ?>"
                    aria-expanded="<?= $groupActive ? 'true' : 'false' ?>">
                <i class="fa-solid <?= htmlspecialchars(MenuService::getMenuIconClass($item['icon'] ?? 'chevron-right'), ENT_QUOTES) ?>"></i>
                <span class="nav-item-text"><?= htmlspecialchars($item['label'] ?? '', ENT_QUOTES) ?></span>
                <i class="fa-solid fa-chevron-right nav-group-arrow"></i>
            </button>

            <div class="nav-group-content"
                 id="sg-<?= htmlspecialchars($item['id'] ?? '', ENT_QUOTES) ?>"
                 <?= $groupActive ? 'style="max-height:500px"' : '' ?>>
                <?php foreach ($item['children'] as $child): ?>
                    <?php
                    $childClass = MenuService::getMenuItemClass($child['url'] ?? '', $currentUri, true);
                    $childUrl = BASE_URL . ($child['url'] ?? '/');
                    ?>
                    <a href="<?= $childUrl ?>"
                       class="<?= $childClass ?>"
                       title="<?= htmlspecialchars($child['label'] ?? '', ENT_QUOTES) ?>">
                        <i class="fa-solid <?= htmlspecialchars(MenuService::getMenuIconClass($child['icon'] ?? 'chevron-right'), ENT_QUOTES) ?>"></i>
                        <span><?= htmlspecialchars($child['label'] ?? '', ENT_QUOTES) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <?php
            $itemUrl = BASE_URL . ($item['url'] ?? '/');
            $badge = $item['badge'] ?? null;
            ?>
            <a href="<?= $itemUrl ?>"
               class="<?= $itemClass ?>"
               title="<?= htmlspecialchars($item['label'] ?? '', ENT_QUOTES) ?>">
                <i class="fa-solid <?= htmlspecialchars(MenuService::getMenuIconClass($item['icon'] ?? 'chevron-right'), ENT_QUOTES) ?>"></i>
                <span class="nav-item-text">
                    <?= htmlspecialchars($item['label'] ?? '', ENT_QUOTES) ?>
                </span>
                <?php if ($badge === 'notifications'): ?>
                    <span id="sidebar-notif-badge-<?= htmlspecialchars($item['id'] ?? '', ENT_QUOTES) ?>"
                          class="sidebar-notif-badge"
                          style="display:none"></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

    <?php endforeach; ?>
</nav>

<?php endif; ?>
