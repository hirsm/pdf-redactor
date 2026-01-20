<!DOCTYPE html>
<html lang="<?= $t->getLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=4.0, user-scalable=yes">
    <title><?= $t->trans('app_title') ?></title>
    <link href="<?= $basePath ?>/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $basePath ?>/style.css" rel="stylesheet">
    <script>
        window.APP_BASE_PATH = "<?= $basePath ?>";
        window.TRANS = <?= $t->getJsTranslations() ?>;
    </script>
</head>
<body>

<nav class="navbar navbar-dark mb-4">
    <div class="container d-flex justify-content-between align-items-center">
        <span class="navbar-brand mb-0 h1">
            <?= $t->trans('app_title') ?>
        </span>

        <?php if ($isLoggedIn): ?>
            <a href="<?= $basePath ?>/logout" class="btn btn-outline-light btn-sm">
                <?= $t->trans('logout') ?>
            </a>
        <?php endif; ?>
    </div>
</nav>

<div class="container pb-5">
    
    <div id="drop-zone" class="p-5 text-center border rounded-3 mb-4 shadow-sm bg-white">
        <h3 class="mb-3"><?= $t->trans('drop_title') ?></h3>
        <p class="text-muted"><?= $t->trans('drop_text') ?></p>
        <input type="file" id="file-input" accept="application/pdf" hidden>
        <button class="btn btn-primary btn-lg" onclick="document.getElementById('file-input').click()">
            <?= $t->trans('btn_select') ?>
        </button>
    </div>

    <div id="app-area" class="d-none">
        <div id="toolbar" class="sticky-top-custom p-2 p-md-3 rounded mb-3 shadow-sm border bg-white">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="d-flex gap-2 align-items-center">
                    <div class="btn-group btn-group-sm">
                        <button id="btn-reset" class="btn btn-outline-secondary" title="<?= $t->trans('btn_reset') ?>">&larr; <?= $t->trans('btn_reset') ?></button>
                        <button id="btn-delete" class="btn btn-outline-danger" disabled title="<?= $t->trans('btn_delete') ?>">🗑️</button>
                        <button id="btn-clear-all" class="btn btn-outline-danger" title="<?= $t->trans('btn_clear_all') ?>">✖ <?= $t->trans('btn_clear_all') ?></button>
                    </div>
                    <div class="btn-group btn-group-sm mobile-only-tools" role="group">
                        <button id="tool-hand" class="btn btn-outline-primary" title="<?= $t->trans('btn_hand') ?>">🖐️</button>
                        <button id="tool-pen" class="btn btn-primary" title="<?= $t->trans('btn_pen') ?>">✏️</button>
                    </div>
                    <div class="btn-group btn-group-sm ms-2" role="group">
                        <button id="btn-zoom-out" class="btn btn-outline-dark" title="<?= $t->trans('btn_zoom_out') ?>">➖</button>
                        <button id="btn-zoom-in" class="btn btn-outline-dark" title="<?= $t->trans('btn_zoom_in') ?>">➕</button>
                    </div>
                </div>
                <button id="btn-process" class="btn btn-primary">
                    <span id="spinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                    🔒 <?= $t->trans('btn_process') ?>
                </button>
            </div>
            <div class="text-muted small mt-2 d-none d-md-block text-center">
                <?= $t->trans('info_desktop') ?>
            </div>
        </div>
        <div id="pages-container" class="text-center"></div>
    </div>
    
    <div id="alert-box" class="alert alert-danger d-none mt-3 sticky-bottom shadow" role="alert"></div>

    <?php if ($imprintUrl || $privacyUrl): ?>
        <footer class="mt-5 pt-4 border-top text-center text-muted small">
            <p class="mb-0">
                <?php if ($imprintUrl): ?>
                    <a href="<?= htmlspecialchars($imprintUrl) ?>" target="_blank" class="text-muted text-decoration-none"><?= $t->trans('footer_imprint') ?></a>
                <?php endif; ?>
                <?php if ($imprintUrl && $privacyUrl): ?> <span class="mx-2">|</span> <?php endif; ?>
                <?php if ($privacyUrl): ?>
                    <a href="<?= htmlspecialchars($privacyUrl) ?>" target="_blank" class="text-muted text-decoration-none"><?= $t->trans('footer_privacy') ?></a>
                <?php endif; ?>
            </p>
        </footer>
    <?php endif; ?>
</div>

<link rel="modulepreload" href="<?= $basePath ?>/pdfjs/pdf.mjs">
<link rel="modulepreload" href="<?= $basePath ?>/pdfjs/pdf.worker.mjs">

<script type="module">
    // 1. Importieren der Module
    import * as pdfjsLib from '<?= $basePath ?>/pdf.js/pdf.mjs';
    
    // 2. Worker konfigurieren (Pfad zum lokalen Worker)
    pdfjsLib.GlobalWorkerOptions.workerSrc = '<?= $basePath ?>/pdf.js/pdf.worker.mjs';

    // 3. WICHTIG: "Brücke" bauen
    // Da app.js kein Modul ist, erwartet es 'pdfjsLib' im globalen Scope (window).
    // Wir hängen das importierte Modul also manuell an das window-Objekt.
    window.pdfjsLib = pdfjsLib;
</script>
<script src="<?= $basePath ?>/app.js"></script>
</body>
</html>
