<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

class FolderModel
{
    const COULEURS = ['slate', 'violet', 'blue', 'green', 'amber', 'red', 'orange', 'teal', 'indigo', 'purple'];
    const ICONES   = ['folder', 'folder-open', 'archive', 'book', 'briefcase', 'database', 'server', 'box'];

    public static function couleurDefaut(): string { return 'slate'; }
    public static function iconeDefaut(): string   { return 'folder'; }
}
