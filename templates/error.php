<!DOCTYPE html>
<html lang="<?= $t->getLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link href="<?= $basePath ?>/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height: 100vh; background: #f8f9fa;">
    <div style="text-align:center; padding: 50px; font-family: sans-serif; max-width: 600px;">
        <h1 style="color: #d0101c;"><?= $title ?></h1>
        <p class="lead"><?= $message ?></p>
        <p class="mt-4">
            <a href="<?= $basePath ?>/login" class="btn btn-outline-secondary">
                <?= $t->trans('back_to_login') ?>
            </a>
        </p>
    </div>
</body>
</html>
