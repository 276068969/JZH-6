<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title ?? '救护车监管平台') ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <header class="topbar">
        <a class="brand" href="/">
            <span class="brand-mark">A</span>
            <span>救护车监管平台</span>
        </a>
        <nav class="nav">
            <a href="/">前台态势</a>
            <a href="/admin">后台管理</a>
            <?php if (!empty($user)): ?>
                <span class="user"><?= h($user['name']) ?></span>
                <a href="/logout">退出</a>
            <?php else: ?>
                <a href="/login">登录</a>
            <?php endif; ?>
        </nav>
    </header>
    <main class="page">
        <?= $content ?>
    </main>
</body>
</html>
