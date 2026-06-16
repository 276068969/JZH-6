<?php ob_start(); ?>
<section class="auth-wrap">
    <form class="auth-card" method="post" action="/login">
        <h1>后台登录</h1>
        <label>
            <span>账号</span>
            <input name="username" autocomplete="username" required>
        </label>
        <label>
            <span>密码</span>
            <input name="password" type="password" autocomplete="current-password" required>
        </label>
        <?php if (!empty($error)): ?>
            <p class="error"><?= h($error) ?></p>
        <?php endif; ?>
        <button type="submit">进入管理后台</button>
        <p class="hint">测试账号：admin / admin123，dispatcher / dispatch123</p>
    </form>
</section>
<?php
$content = ob_get_clean();
$title = '后台登录 - 救护车监管平台';
require __DIR__ . '/layout.php';
