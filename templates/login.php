<?php ob_start(); ?>
<section class="login-page">
    <div class="login-brand">
        <div class="login-brand-inner">
            <div class="login-brand-mark">
                <span class="brand-mark-badge">120</span>
            </div>
            <p class="login-eyebrow">实时监管 · 急救调度 · 风险预警</p>
            <h1 class="login-title">城市院前急救<br>运行监管平台</h1>
            <p class="login-summary">集中调度救护车资源，全程追踪急救事件流转，实时监测设备与响应异常，为急救监管部门提供一体化指挥后台。</p>
            <div class="login-stats">
                <div>
                    <span>接入车辆</span>
                    <strong>全天候</strong>
                </div>
                <div>
                    <span>响应时效</span>
                    <strong>分钟级</strong>
                </div>
                <div>
                    <span>风险告警</span>
                    <strong>实时推送</strong>
                </div>
            </div>
            <div class="login-scene-tag">
                <span class="scene-dot"></span>
                急救调度指挥中心
            </div>
        </div>
        <div class="login-brand-decoration"></div>
    </div>

    <div class="login-form-wrap">
        <div class="login-form-card">
            <div class="login-form-head">
                <h2>后台登录</h2>
                <p>请选择调度身份并输入账号凭证</p>
            </div>

            <div class="role-selector" id="role-selector">
                <button type="button" class="role-option role-admin active" data-role="admin" data-username="admin" data-password="admin123">
                    <span class="role-icon role-icon-admin">⚙</span>
                    <span class="role-info">
                        <span class="role-name">平台管理员</span>
                        <span class="role-desc">全局配置、告警处置、数据看板</span>
                    </span>
                    <span class="role-check"></span>
                </button>
                <button type="button" class="role-option role-dispatcher" data-role="dispatcher" data-username="dispatcher" data-password="dispatch123">
                    <span class="role-icon role-icon-dispatcher">🚑</span>
                    <span class="role-info">
                        <span class="role-name">调度员</span>
                        <span class="role-desc">派车调度、事件管理、状态更新</span>
                    </span>
                    <span class="role-check"></span>
                </button>
            </div>

            <form class="login-form" method="post" action="/login" id="login-form">
                <label>
                    <span>账号</span>
                    <input name="username" id="username" autocomplete="username" required placeholder="请输入账号">
                </label>
                <label>
                    <span>密码</span>
                    <input name="password" id="password" type="password" autocomplete="current-password" required placeholder="请输入密码">
                </label>

                <?php if (!empty($error)): ?>
                    <?php
                        $errorClass = 'login-error';
                        $errorTitle = '登录失败';
                        
                        $specialReasons = [
                            '账号已被停用' => ['title' => '账号已停用', 'class' => 'login-error disabled'],
                            '权限不足' => ['title' => '访问受限', 'class' => 'login-error denied'],
                        ];
                        
                        if (isset($specialReasons[$error])) {
                            $errorTitle = $specialReasons[$error]['title'];
                            $errorClass = $specialReasons[$error]['class'];
                        }
                    ?>
                    <div class="<?= $errorClass ?>">
                        <span class="error-icon">⚠</span>
                        <div>
                            <strong><?= h($errorTitle) ?></strong>
                            <p><?= h($error) ?>，请检查账号状态或联系管理员。</p>
                        </div>
                    </div>
                <?php endif; ?>

                <button type="submit" class="login-submit">
                    <span>进入指挥调度后台</span>
                    <span class="submit-arrow">→</span>
                </button>

                <div class="login-footnote">
                    <div class="test-account-box">
                        <span class="test-account-title">测试账号速填</span>
                        <div class="test-account-list">
                            <button type="button" class="test-account-chip" data-fill="admin">
                                <b>管理员</b><span>admin / admin123</span>
                            </button>
                            <button type="button" class="test-account-chip" data-fill="dispatcher">
                                <b>调度员</b><span>dispatcher / dispatch123</span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="login-copyright">
            <span>© 2026 城市院前急救运行监管平台 · 仅限授权人员访问</span>
        </div>
    </div>
</section>

<script>
(function() {
    var roleButtons = document.querySelectorAll('.role-option');
    var usernameInput = document.getElementById('username');
    var passwordInput = document.getElementById('password');
    var chips = document.querySelectorAll('.test-account-chip');
    var form = document.getElementById('login-form');

    roleButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            roleButtons.forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            usernameInput.value = btn.getAttribute('data-username') || '';
            passwordInput.value = btn.getAttribute('data-password') || '';
        });
    });

    chips.forEach(function(chip) {
        chip.addEventListener('click', function() {
            var type = chip.getAttribute('data-fill');
            var target = document.querySelector('.role-option[data-role="' + type + '"]');
            if (target) {
                target.click();
            }
        });
    });

    form.addEventListener('submit', function() {
        var submitBtn = form.querySelector('.login-submit');
        if (submitBtn) {
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
        }
    });
})();
</script>
<?php
$content = ob_get_clean();
$title = '后台登录 - 城市院前急救运行监管平台';
require __DIR__ . '/layout.php';
