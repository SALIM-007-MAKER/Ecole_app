<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Bulletin', ENT_QUOTES) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @page { size: A4 portrait; margin: 12mm 15mm; }
        body  { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11pt; background: #fff; }
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
            table { page-break-inside: avoid; }
        }
        @media screen {
            body { background: #e9ecef; }
            .page { width: 210mm; min-height: 297mm; margin: 20px auto;
                    background: #fff; padding: 20mm 18mm; box-shadow: 0 4px 24px rgba(0,0,0,.15); }
        }
    </style>
</head>
<body>

<div class="no-print flex items-center justify-center gap-2 bg-slate-950 px-4 py-3 text-white">
    <button onclick="window.print()" class="inline-flex items-center justify-center rounded-lg bg-white px-3 py-1.5 text-xs font-medium text-slate-900 transition hover:bg-slate-100">
        &#128424; Imprimer / Enregistrer en PDF
    </button>
    <button onclick="window.close()" class="inline-flex items-center justify-center rounded-lg border border-white/30 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-white/10">&#10005; Fermer</button>
</div>

<div class="page">
    <?= $content ?? '' ?>
</div>

</body>
</html>
