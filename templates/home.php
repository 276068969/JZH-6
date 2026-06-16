<?php ob_start(); ?>
<section class="hero">
    <div>
        <p class="eyebrow">实时监管 · 急救调度 · 风险预警</p>
        <h1>城市院前急救运行态势</h1>
        <p class="summary">集中展示救护车在线状态、急救事件流转、异常告警和现场位置，为监管部门提供统一看板。</p>
    </div>
    <div class="hero-panel">
        <span>今日活跃车辆</span>
        <strong><?= (int) $overview['ambulances_online'] ?></strong>
        <small>共 <?= (int) $overview['ambulances_total'] ?> 辆接入监管</small>
    </div>
</section>

<section class="metrics">
    <div><span>接入车辆</span><strong><?= (int) $overview['ambulances_total'] ?></strong></div>
    <div><span>在线车辆</span><strong><?= (int) $overview['ambulances_online'] ?></strong></div>
    <div><span>进行中事件</span><strong><?= (int) $overview['active_cases'] ?></strong></div>
    <div><span>未处理告警</span><strong><?= (int) $overview['alerts'] ?></strong></div>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>车辆态势监管</h2>
        <div class="vehicle-status-stats">
            <span class="stat-item stat-dispatching"><i></i>出车 <?= count(array_filter($ambulances, fn($a) => $a['status'] === 'dispatching')) ?></span>
            <span class="stat-item stat-transporting"><i></i>转运 <?= count(array_filter($ambulances, fn($a) => $a['status'] === 'transporting')) ?></span>
            <span class="stat-item stat-onscene"><i></i>现场 <?= count(array_filter($ambulances, fn($a) => $a['status'] === 'on_scene')) ?></span>
            <span class="stat-item stat-standby"><i></i>待命 <?= count(array_filter($ambulances, fn($a) => $a['status'] === 'standby')) ?></span>
            <span class="stat-item stat-maintenance"><i></i>检修 <?= count(array_filter($ambulances, fn($a) => $a['status'] === 'maintenance')) ?></span>
        </div>
    </div>
    <div class="vehicle-grid">
        <?php foreach ($ambulances as $ambulance): ?>
            <?php
                $status = $ambulance['status'];
                $hasActiveCase = !empty($ambulance['active_case_no']);
                $isAbnormal = ($status === 'dispatching' || $status === 'on_scene' || $status === 'transporting') && !$hasActiveCase;
                $isMaintenance = $status === 'maintenance';
            ?>
            <article class="vehicle-status-card vehicle-status-<?= h($status) ?><?= $isAbnormal ? ' vehicle-abnormal' : '' ?><?= $isMaintenance ? ' vehicle-maintenance' : '' ?>">
                <div class="vehicle-card-header">
                    <div class="vehicle-code-block">
                        <span class="vehicle-code"><?= h($ambulance['code']) ?></span>
                        <span class="vehicle-plate"><?= h($ambulance['plate_no']) ?></span>
                    </div>
                    <span class="vehicle-status-badge status-<?= h($status) ?>">
                        <span class="pulse-dot"></span>
                        <?= statusText($status) ?>
                    </span>
                </div>
                <div class="vehicle-card-body">
                    <div class="vehicle-info-row">
                        <span class="info-label">所属医院</span>
                        <span class="info-value hospital"><?= h($ambulance['hospital']) ?></span>
                    </div>
                    <div class="vehicle-info-row">
                        <span class="info-label">驾驶员</span>
                        <span class="info-value"><?= h($ambulance['driver_name']) ?></span>
                    </div>
                    <div class="vehicle-info-row location-row">
                        <span class="info-label">当前位置</span>
                        <span class="info-value location"><?= h($ambulance['location']) ?></span>
                    </div>
                </div>
                <div class="vehicle-card-footer">
                    <?php if ($hasActiveCase): ?>
                        <div class="active-case-info">
                            <span class="case-label">关联事件</span>
                            <span class="case-no"><?= h($ambulance['active_case_no']) ?></span>
                            <span class="case-status status-tag-small status-<?= h($ambulance['active_case_status']) ?>">
                                <?= statusText($ambulance['active_case_status']) ?>
                            </span>
                        </div>
                    <?php elseif ($status === 'standby'): ?>
                        <div class="dispatch-available">
                            <span class="available-icon">✓</span>
                            <span>可立即派车</span>
                        </div>
                    <?php elseif ($isMaintenance): ?>
                        <div class="maintenance-info">
                            <span class="maintenance-icon">⚙</span>
                            <span>检修中 · 暂不可派</span>
                        </div>
                    <?php else: ?>
                        <div class="abnormal-warning">
                            <span class="warning-icon">⚠</span>
                            <span>状态异常 · 未关联事件</span>
                        </div>
                    <?php endif; ?>
                    <div class="update-time">
                        更新于 <?= h(date('H:i', strtotime($ambulance['updated_at']))) ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>急救事件</h2>
        <span>最近 12 条</span>
    </div>
    <table>
        <thead>
            <tr><th>编号</th><th>级别</th><th>状态</th><th>车辆</th></tr>
        </thead>
        <tbody>
        <?php foreach ($cases as $case): ?>
            <tr>
                <td><?= h($case['case_no']) ?></td>
                <td><span class="badge priority-<?= h($case['priority']) ?>"><?= priorityText($case['priority']) ?></span></td>
                <td><?= statusText($case['status']) ?></td>
                <td><?= h($case['assigned_ambulance'] ?: '待派车') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>风险告警</h2>
        <span>设备、响应、轨迹异常</span>
    </div>
    <div class="alerts">
        <?php foreach ($alerts as $alert): ?>
            <article>
                <b><?= h($alert['title']) ?></b>
                <span><?= statusText($alert['status']) ?></span>
                <p><?= h($alert['description']) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php
$content = ob_get_clean();
$title = '前台态势 - 救护车监管平台';
require __DIR__ . '/layout.php';
