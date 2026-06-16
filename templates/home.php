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

<section class="grid two">
    <div class="panel">
        <div class="panel-head">
            <h2>车辆监管</h2>
            <span><?= date('Y-m-d H:i') ?></span>
        </div>
        <div class="vehicle-map">
            <?php foreach ($ambulances as $index => $ambulance): ?>
                <article class="vehicle-card">
                    <span class="dot status-<?= h($ambulance['status']) ?>"></span>
                    <h3><?= h($ambulance['code']) ?></h3>
                    <p><?= h($ambulance['hospital']) ?></p>
                    <p><?= h($ambulance['location']) ?></p>
                    <b><?= statusText($ambulance['status']) ?></b>
                </article>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="panel">
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
    </div>
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
