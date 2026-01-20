<!DOCTYPE html>
<html lang="<?= $t->getLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $t->trans('app_title') ?></title>
    <link href="<?= $basePath ?>/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $basePath ?>/style.css" rel="stylesheet">
</head>
<body class="d-flex flex-column align-items-center justify-content-center" style="min-height: 100vh; background: #f8f9fa;">

<div class="card shadow-sm" style="max-width: 400px; width: 100%;">
    <div class="card-body text-center p-5">
        <h3 class="mb-4" style="color: var(--primary-color);"><?= $t->trans('app_title') ?></h3>
        <p class="text-muted mb-4"><?= $t->trans('login_title') ?></p>
        
        <a href="<?= $loginUrl ?>" id="btnLogin" class="btn btn-primary w-100 py-2 mb-3">
            <?= $t->trans('login_btn') ?>
        </a>

        <div class="form-check d-inline-block text-start">
            <input class="form-check-input" type="checkbox" id="rememberMe">
            <label class="form-check-label small text-muted" for="rememberMe">
                <?= $t->trans('login_remember') ?>
            </label>
            
            <?php if ($privacyUrl): ?>
                <a href="#" class="text-decoration-none small" tabindex="0" role="button" 
                   data-bs-toggle="popover" 
                   data-bs-trigger="focus" 
                   data-bs-title="<?= $t->trans('login_remember') ?>" 
                   data-bs-html="true" 
                   data-bs-content="<?= htmlspecialchars($t->trans('login_hint_text', ['%url%' => htmlspecialchars($privacyUrl)])) ?>">
                   <?= $t->trans('login_hint') ?>
                </a>
            <?php endif; ?>
        </div>

        <?php if ($privacyUrl): ?>
            <p class="mt-4 mb-0 small text-muted">
                <?= $t->trans('login_privacy_note', ['%url%' => htmlspecialchars($privacyUrl)]) ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<?php if ($imprintUrl || $privacyUrl): ?>
    <footer class="mt-4 pt-3 border-top text-center text-muted small" style="width: 100%; max-width: 400px;">
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

<script src="<?= $basePath ?>/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
    const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
    const popoverList = [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl));
    document.getElementById('btnLogin').addEventListener('click', function() {
        const isChecked = document.getElementById('rememberMe').checked;
        const basePath = "<?= $basePath ?>" || "/";
        document.cookie = "temp_remember_me=" + (isChecked ? "1" : "0") + "; path=" + basePath + "; max-age=300; SameSite=Lax";
    });
</script>
</body>
</html>
