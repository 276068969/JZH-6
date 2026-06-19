<?php
$activeTab = $active_tab ?? 'dashboard';
ob_start();
?>
<section class="admin-head">
    <div>
        <p class="eyebrow">后台管理</p>
        <h1>调度监管工作台</h1>
    </div>
    <div class="operator">当前用户：<?= h($user['name']) ?>（<?= h($user['role']) ?>）</div>
</section>

<nav class="admin-tabs">
    <a href="/admin" class="tab-link <?= $activeTab === 'dashboard' ? 'active' : '' ?>">调度监管工作台</a>
    <a href="/admin/ambulances/profile" class="tab-link <?= $activeTab === 'profile' ? 'active' : '' ?>">救护车档案管理</a>
</nav>

<?php if (!empty($flash)): ?>
<section class="flash-messages">
    <?php foreach (['success', 'errors', 'warnings'] as $type): ?>
        <?php if (!empty($flash[$type])): ?>
            <div class="flash <?= $type ?>">
                <ul>
                    <?php foreach ($flash[$type] as $msg): ?>
                        <li><?= h($msg) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<div class="tab-content">
    <div class="tab-pane <?= $activeTab === 'dashboard' ? 'active' : '' ?>" id="tab-dashboard">

<section class="metrics compact">
    <div><span>接入车辆</span><strong><?= (int) $overview['ambulances_total'] ?></strong></div>
    <div><span>在线车辆</span><strong><?= (int) $overview['ambulances_online'] ?></strong></div>
    <div id="metric-active-cases"<?= !empty($last_created_case) ? ' class="metric-flash"' : '' ?>><span>事件处理中</span><strong><?= (int) $overview['active_cases'] ?></strong></div>
    <div><span>告警待处置</span><strong><?= (int) $overview['alerts'] ?></strong></div>
</section>

<section class="grid two">
    <div class="panel">
        <div class="panel-head">
            <h2>新增急救事件</h2>
            <span>调度录入 · 派车校验</span>
        </div>
        <form class="stack-form" method="post" action="/admin/cases" id="case-form">
            <label><span>患者/呼叫人</span><input name="patient_name" required></label>
            <label><span>地址</span><input name="address" required></label>
            <div class="form-row">
                <label>
                    <span>优先级</span>
                    <select name="priority">
                        <option value="high">高</option>
                        <option value="medium" selected>中</option>
                        <option value="low">低</option>
                    </select>
                </label>
                <label>
                    <span>状态</span>
                    <select name="status">
                        <option value="reported">已上报</option>
                        <option value="accepted">已受理</option>
                    </select>
                </label>
            </div>
            <label>
                <span>派车（选择救护车）</span>
                <select name="assigned_ambulance" id="assigned_ambulance">
                    <option value="">-- 暂不派车 --</option>
                    <?php foreach ($ambulances as $ambulance): ?>
                        <?php
                            $dispatchable = isAmbulanceDispatchable($ambulance);
                            $optionClass = '';
                            $disabled = '';
                            if (!$dispatchable) {
                                if ($ambulance['status'] === 'maintenance') {
                                    $optionClass = 'maintenance';
                                    $disabled = 'disabled';
                                } else {
                                    $optionClass = 'busy';
                                    $disabled = 'disabled';
                                }
                            } else {
                                $optionClass = 'dispatchable';
                            }
                        ?>
                        <option value="<?= h($ambulance['code']) ?>" class="<?= $optionClass ?>" <?= $disabled ?>
                            data-status="<?= h($ambulance['status']) ?>"
                            data-hospital="<?= h($ambulance['hospital']) ?>"
                            data-location="<?= h($ambulance['location']) ?>"
                            data-dispatchable="<?= $dispatchable ? '1' : '0' ?>"
                            data-active-case="<?= h($ambulance['active_case_no'] ?? '') ?>">
                            <?= h(ambulanceOptionLabel($ambulance)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="dispatch-hint" id="dispatch-hint">请选择救护车，系统将自动校验车辆是否可派，并按状态匹配矩阵校验一致性</div>
            <div class="matrix-panel">
                <div class="matrix-title">状态匹配矩阵</div>
                <div class="matrix-grid">
                    <div class="matrix-row head">
                        <span>事件状态</span>
                        <span>期望车辆状态</span>
                        <span>说明</span>
                    </div>
                    <?php foreach (dispatchStatusMatrix() as $key => $rule): ?>
                        <div class="matrix-row">
                            <span class="status-tag <?= statusClass($key) ?>"><?= $rule['label'] ?></span>
                            <span>
                                <?php foreach ($rule['expected'] as $i => $s): ?>
                                    <?= $i > 0 ? ' / ' : '' ?><span class="status-tag-small status-<?= $s ?>"><?= statusText($s) ?></span>
                                <?php endforeach; ?>
                            </span>
                            <span class="muted"><?= $rule['description'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="submit">保存事件并派车</button>
        </form>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h2>车辆状态维护</h2>
            <span>监管更新 · 联动检查</span>
        </div>
        <form class="stack-form" method="post" action="/admin/ambulances">
            <label>
                <span>车辆</span>
                <select name="id" id="ambulance-select">
                    <?php foreach ($ambulances as $ambulance): ?>
                        <option value="<?= (int) $ambulance['id'] ?>"
                            data-status="<?= h($ambulance['status']) ?>"
                            data-active-case="<?= h($ambulance['active_case_no'] ?? '') ?>">
                            <?= h($ambulance['code']) ?> · <?= h($ambulance['hospital']) ?> · <?= statusText($ambulance['status']) ?>
                            <?php if (!empty($ambulance['active_case_no'])): ?>
                                · 关联 <?= h($ambulance['active_case_no']) ?>
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>状态</span>
                <select name="status" id="ambulance-status">
                    <option value="standby">待命</option>
                    <option value="dispatching">出车</option>
                    <option value="on_scene">现场处置</option>
                    <option value="transporting">转运中</option>
                    <option value="maintenance">检修</option>
                </select>
            </label>
            <label><span>当前位置</span><input name="location" required></label>
            <div class="dispatch-hint" id="ambulance-linkage-hint">选择车辆后，若设为待命/检修将自动检查关联事件状态</div>
            <button type="submit">更新车辆</button>
        </form>
    </div>
</section>

<section class="grid two">
    <?php
        $hospitals = [];
        foreach ($ambulances as $amb) {
            if (!empty($amb['hospital']) && !in_array($amb['hospital'], $hospitals, true)) {
                $hospitals[] = $amb['hospital'];
            }
        }
        sort($hospitals);
    ?>
    <div class="panel" id="ambulance-list-panel">
        <div class="panel-head">
            <h2>车辆列表</h2>
            <span>状态 · 当前任务</span>
        </div>
        <div class="vehicle-filter-bar">
            <div class="filter-item">
                <label>状态</label>
                <select id="filter-status">
                    <option value="">全部状态</option>
                    <option value="standby">待命</option>
                    <option value="dispatching">出车</option>
                    <option value="on_scene">现场处置</option>
                    <option value="transporting">转运中</option>
                    <option value="maintenance">检修</option>
                </select>
            </div>
            <div class="filter-item">
                <label>医院</label>
                <select id="filter-hospital">
                    <option value="">全部医院</option>
                    <?php foreach ($hospitals as $h): ?>
                        <option value="<?= h($h) ?>"><?= h($h) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item filter-code">
                <label>车辆编号</label>
                <input type="text" id="filter-code" placeholder="输入编号，如 A-001">
            </div>
            <div class="filter-item filter-actions">
                <button type="button" id="filter-reset" class="btn-reset">重置筛选</button>
            </div>
            <div class="filter-result-info">
                <span id="filter-result-count">共 <?= count($ambulances) ?> 辆</span>
            </div>
        </div>
        <table class="vehicle-table">
            <thead>
                <tr>
                    <th>车辆</th>
                    <th>医院</th>
                    <th>位置</th>
                    <th>状态</th>
                    <th>当前任务</th>
                </tr>
            </thead>
            <tbody id="ambulance-tbody">
            <?php foreach ($ambulances as $ambulance): ?>
                <tr data-status="<?= h($ambulance['status']) ?>"
                    data-hospital="<?= h($ambulance['hospital']) ?>"
                    data-code="<?= h($ambulance['code']) ?>">
                    <td><?= h($ambulance['code']) ?></td>
                    <td><?= h($ambulance['hospital']) ?></td>
                    <td><?= h($ambulance['location']) ?></td>
                    <td>
                        <span class="status-tag <?= statusClass($ambulance['status']) ?>">
                            <?= statusText($ambulance['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!empty($ambulance['active_case_no'])): ?>
                            <span class="dispatch-tag busy">
                                占用 · <?= h($ambulance['active_case_no']) ?>
                                <small>(<?= statusText($ambulance['active_case_status']) ?>)</small>
                            </span>
                        <?php elseif ($ambulance['status'] === 'standby'): ?>
                            <span class="dispatch-tag">可派车</span>
                        <?php elseif ($ambulance['status'] === 'maintenance'): ?>
                            <span class="dispatch-tag none">检修中</span>
                        <?php else: ?>
                            <span class="dispatch-tag busy">勤务中</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div id="empty-filter-result" class="empty-filter-result" style="display:none;">
            <span class="empty-icon">🔍</span>
            <p>没有匹配的车辆，请调整筛选条件</p>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h2>事件列表</h2>
            <span>派车 · 状态联动 · 快照校验</span>
        </div>
        <table class="case-table">
            <thead>
                <tr>
                    <th>编号</th>
                    <th>患者</th>
                    <th>优先级</th>
                    <th>派车</th>
                    <th>事件状态</th>
                    <th>车辆状态</th>
                    <th>匹配状态</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cases as $case): ?>
                <?php
                    $hasAmbulance = !empty($case['assigned_ambulance']);
                    $match = checkCaseVehicleStatusMatch(
                        $case['status'],
                        $case['ambulance_status'] ?? null,
                        $hasAmbulance
                    );
                    $isNewCase = !empty($last_created_case) && $last_created_case === $case['case_no'];
                ?>
                <tr class="case-row-<?= $match['level'] ?><?= $isNewCase ? ' case-row-new' : '' ?>" data-case-no="<?= h($case['case_no']) ?>">
                    <td class="case-link">
                        <a href="/admin/cases/<?= urlencode($case['case_no']) ?>"><?= h($case['case_no']) ?></a>
                        <?php if ($isNewCase): ?>
                            <span class="new-case-badge">新增</span>
                        <?php endif; ?>
                    </td>
                    <td><?= h($case['patient_name']) ?></td>
                    <td><span class="badge priority-<?= h($case['priority']) ?>"><?= priorityText($case['priority']) ?></span></td>
                    <td>
                        <?php if ($hasAmbulance): ?>
                            <div>
                                <strong><?= h($case['assigned_ambulance']) ?></strong>
                                <?php if (!empty($case['ambulance_hospital'])): ?>
                                    <div style="font-size:12px;color:var(--muted)"><?= h($case['ambulance_hospital']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($case['dispatch_vehicle_status'])): ?>
                                    <div class="snapshot-info">
                                        派车时：<span class="status-tag-small status-<?= h($case['dispatch_vehicle_status']) ?>"><?= statusText($case['dispatch_vehicle_status']) ?></span>
                                        <?php if (!empty($case['dispatched_at'])): ?>
                                            <span class="snapshot-time"><?= h($case['dispatched_at']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <span class="dispatch-tag none">待派车</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-tag <?= statusClass($case['status']) ?>">
                            <?= statusText($case['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($hasAmbulance && !empty($case['ambulance_status'])): ?>
                            <span class="status-tag <?= statusClass($case['ambulance_status']) ?>">
                                <?= statusText($case['ambulance_status']) ?>
                            </span>
                        <?php elseif ($hasAmbulance): ?>
                            <span style="color:var(--muted);font-size:12px">车辆已移除</span>
                        <?php else: ?>
                            <span style="color:var(--muted);font-size:13px">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($match['level'] === 'match'): ?>
                            <span class="match-badge match">✓ <?= matchLevelText($match['level']) ?></span>
                        <?php elseif ($match['level'] === 'warn'): ?>
                            <span class="match-badge warn">⚡ <?= matchLevelText($match['level']) ?></span>
                        <?php elseif ($match['level'] === 'error'): ?>
                            <span class="match-badge error">⚠ <?= matchLevelText($match['level']) ?></span>
                        <?php else: ?>
                            <span class="match-badge none">— <?= matchLevelText($match['level']) ?></span>
                        <?php endif; ?>
                        <?php if ($match['level'] !== 'none' && $match['level'] !== 'match'): ?>
                            <div class="match-reason"><?= h($match['reason']) ?></div>
                        <?php endif; ?>
                        <?php if ($match['level'] === 'match'): ?>
                            <div class="match-reason muted"><?= h($match['expected']) ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel alerts-panel">
    <div class="panel-head">
        <h2>风险告警处置</h2>
        <span>监管发现 · 后台处置 · 前台同步</span>
    </div>
    <div class="alerts-section">
        <div class="alerts-open-section">
            <div class="alerts-section-head">
                <h3>未处理告警 <span class="alert-count-badge"><?= count($open_alerts) ?></span></h3>
            </div>
            <?php if (empty($open_alerts)): ?>
                <div class="alerts-empty">
                    <span class="empty-icon">✓</span>
                    <p>当前暂无未处理告警，系统运行正常</p>
                </div>
            <?php else: ?>
                <div class="alerts-open-list">
                    <?php foreach ($open_alerts as $alert): ?>
                        <?php $typeInfo = alertTypeInfo($alert['title'], $alert['description'] ?? ''); ?>
                        <article class="alert-card alert-open <?= h($typeInfo['class']) ?>">
                            <div class="alert-card-head">
                                <div class="alert-title-row">
                                    <span class="alert-type-badge">
                                        <span class="alert-type-icon"><?= h($typeInfo['icon']) ?></span>
                                        <?= h($typeInfo['label']) ?>
                                    </span>
                                    <h4><?= h($alert['title']) ?></h4>
                                    <span class="status-tag status-open">未处理</span>
                                </div>
                                <div class="alert-meta">
                                    <span class="alert-time">告警时间：<?= h($alert['created_at']) ?></span>
                                </div>
                            </div>
                            <div class="alert-card-body">
                                <p class="alert-description"><?= h($alert['description']) ?></p>
                                <form class="alert-handle-form" method="post" action="/admin/alerts/handle">
                                    <input type="hidden" name="alert_id" value="<?= (int) $alert['id'] ?>">
                                    <label>
                                        <span>处置说明</span>
                                        <textarea name="handling_notes" rows="2" placeholder="请填写处置措施、原因分析或跟进结果..." required></textarea>
                                    </label>
                                    <div class="alert-form-actions">
                                        <button type="submit" class="btn-handle">确认处置并关闭告警</button>
                                    </div>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="alerts-resolved-section">
            <div class="alerts-section-head">
                <h3>已处理告警 <span class="alert-count-badge resolved"><?= count($resolved_alerts) ?></span></h3>
            </div>
            <?php if (empty($resolved_alerts)): ?>
                <div class="alerts-empty muted">
                    <p>暂无已处理记录</p>
                </div>
            <?php else: ?>
                <div class="alerts-resolved-list">
                    <?php foreach ($resolved_alerts as $alert): ?>
                        <?php $typeInfo = alertTypeInfo($alert['title'], $alert['description'] ?? ''); ?>
                        <article class="alert-card alert-resolved <?= h($typeInfo['class']) ?>">
                            <div class="alert-card-head">
                                <div class="alert-title-row">
                                    <span class="alert-type-badge">
                                        <span class="alert-type-icon"><?= h($typeInfo['icon']) ?></span>
                                        <?= h($typeInfo['label']) ?>
                                    </span>
                                    <h4><?= h($alert['title']) ?></h4>
                                    <span class="status-tag status-resolved">已处理</span>
                                </div>
                                <div class="alert-meta">
                                    <span class="alert-time">告警：<?= h($alert['created_at']) ?></span>
                                    <span class="alert-handle-info">
                                        处置人：<strong><?= h($alert['handler_name'] ?? '系统') ?></strong>
                                        · <?= h($alert['handled_at']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="alert-card-body">
                                <p class="alert-description"><?= h($alert['description']) ?></p>
                                <div class="alert-handling-notes">
                                    <span class="handling-notes-label">处置说明：</span>
                                    <span class="handling-notes-content"><?= h($alert['handling_notes']) ?></span>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="panel audit-panel">
    <div class="panel-head">
        <h2>车辆状态变更审计</h2>
        <span>操作留痕 · 调度追溯 · 监管审计</span>
    </div>
    <?php if (empty($audit_table_available)): ?>
        <div class="alerts-empty">
            <p class="muted">⚠ 审计功能待初始化</p>
            <p class="muted" style="font-size: 12px; margin-top: 6px;">
                数据库表 <code>ambulance_status_audit</code> 尚未创建，系统已触发自动迁移。
                请刷新页面或联系管理员执行迁移脚本。
            </p>
        </div>
    <?php elseif (empty($status_audit_logs)): ?>
        <div class="alerts-empty muted">
            <p>暂无车辆状态变更记录</p>
        </div>
    <?php else: ?>
        <div class="audit-log-list">
            <table class="audit-table">
                <thead>
                    <tr>
                        <th>时间</th>
                        <th>车辆</th>
                        <th>状态变更</th>
                        <th>位置变更</th>
                        <th>操作人</th>
                        <th>变更类型</th>
                        <th>关联事件</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($status_audit_logs as $log): ?>
                        <tr class="audit-log-row">
                            <td class="audit-time">
                                <span class="audit-time-date"><?= h(date('Y-m-d', strtotime($log['created_at'] ?? ''))) ?></span>
                                <span class="audit-time-time"><?= h(date('H:i:s', strtotime($log['created_at'] ?? ''))) ?></span>
                            </td>
                            <td class="audit-vehicle">
                                <strong><?= h($log['ambulance_code'] ?? '—') ?></strong>
                            </td>
                            <td class="audit-status">
                                <div class="status-transition">
                                    <?php 
                                        $oldStatus = $log['old_status'] ?? 'unknown';
                                        $newStatus = $log['new_status'] ?? 'unknown';
                                    ?>
                                    <span class="status-tag-small status-<?= $oldStatus ?>"><?= statusText($oldStatus) ?></span>
                                    <span class="transition-arrow">→</span>
                                    <span class="status-tag-small status-<?= $newStatus ?>"><?= statusText($newStatus) ?></span>
                                </div>
                            </td>
                            <td class="audit-location">
                                <?php 
                                    $oldLoc = $log['old_location'] ?? null;
                                    $newLoc = $log['new_location'] ?? null;
                                ?>
                                <?php if ($oldLoc !== $newLoc): ?>
                                    <div class="location-change">
                                        <span class="location-old muted"><?= h($oldLoc ?? '') ?></span>
                                        <span class="transition-arrow">→</span>
                                        <span class="location-new"><?= h($newLoc ?? '') ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="muted"><?= h($newLoc ?? '—') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="audit-operator">
                                <div class="operator-info">
                                    <strong><?= h($log['operator_name'] ?? '—') ?></strong>
                                    <small class="muted">(<?= h($log['operator_role'] ?? 'unknown') ?>)</small>
                                </div>
                            </td>
                            <td class="audit-type">
                                <?php if (($log['change_type'] ?? '') === 'dispatch'): ?>
                                    <span class="audit-type-tag dispatch">派车联动</span>
                                <?php else: ?>
                                    <span class="audit-type-tag manual">手动更新</span>
                                <?php endif; ?>
                            </td>
                            <td class="audit-case">
                                <?php if (!empty($log['related_case_no'])): ?>
                                    <span class="case-link"><a href="/admin/cases/<?= urlencode($log['related_case_no']) ?>"><?= h($log['related_case_no']) ?></a></span>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<script>
(function() {
    var select = document.getElementById('assigned_ambulance');
    var caseStatusSelect = document.querySelector('select[name="status"]');
    var hint = document.getElementById('dispatch-hint');
    var form = document.getElementById('case-form');

    var statusTextMap = {
        'standby': '待命',
        'dispatching': '出车',
        'on_scene': '现场处置',
        'transporting': '转运中',
        'maintenance': '检修'
    };

    var matrix = {
        'reported': {
            label: '已上报',
            expected: ['dispatching'],
            allowed: ['dispatching', 'on_scene', 'transporting'],
            ideal: 'dispatching',
            description: '事件已上报派车，车辆应处于「出车」状态'
        },
        'accepted': {
            label: '已受理',
            expected: ['on_scene', 'transporting'],
            allowed: ['dispatching', 'on_scene', 'transporting'],
            ideal: 'on_scene',
            description: '事件已受理，车辆应处于「现场处置」或「转运中」'
        },
        'closed': {
            label: '已关闭',
            expected: ['standby', 'maintenance'],
            allowed: ['standby', 'maintenance'],
            ideal: 'standby',
            description: '事件已关闭，车辆应回到「待命」或「检修」'
        }
    };

    function checkMatch(caseStatus, vehicleStatus) {
        var rule = matrix[caseStatus];
        if (!rule) return { level: 'none', reason: '' };
        if (rule.expected.indexOf(vehicleStatus) !== -1) {
            return { level: 'match', reason: '状态匹配' };
        }
        if (rule.allowed.indexOf(vehicleStatus) !== -1) {
            return { level: 'warn', reason: '状态有偏差（' + statusTextMap[vehicleStatus] + '），但在合理范围内' };
        }
        return {
            level: 'error',
            reason: '状态不一致：事件' + rule.label + ' 但车辆' + statusTextMap[vehicleStatus]
        };
    }

    function updateHint() {
        var opt = select.options[select.selectedIndex];
        var caseStatus = caseStatusSelect ? caseStatusSelect.value : 'reported';
        var rule = matrix[caseStatus] || null;

        if (!opt || !opt.value) {
            hint.className = 'dispatch-hint';
            if (rule) {
                hint.innerHTML = '请选择救护车。<span class="muted">按状态矩阵：' + rule.description + '</span>';
            } else {
                hint.textContent = '请选择救护车，系统将自动校验车辆是否可派，并按状态匹配矩阵校验一致性';
            }
            return;
        }

        var status = opt.getAttribute('data-status') || '';
        var hospital = opt.getAttribute('data-hospital') || '';
        var location = opt.getAttribute('data-location') || '';
        var dispatchable = opt.getAttribute('data-dispatchable') === '1';
        var activeCase = opt.getAttribute('data-active-case') || '';

        var info = '车辆 <strong>' + opt.value + '</strong> · ' + hospital + ' · 当前位置：' + location;

        if (!dispatchable) {
            hint.className = 'dispatch-hint error';
            if (status === 'maintenance') {
                hint.innerHTML = '<strong>不可派车：</strong>' + info + '<br>车辆处于检修状态，请选择其他车辆';
            } else if (activeCase) {
                hint.innerHTML = '<strong>不可派车：</strong>' + info + '<br>车辆已被事件 <strong>' + activeCase + '</strong> 占用，请勿重复派车';
            } else {
                hint.innerHTML = '<strong>不可派车：</strong>' + info + '<br>状态：' + (statusTextMap[status] || status);
            }
            return;
        }

        var afterDispatchStatus = rule ? rule.ideal : 'dispatching';
        var match = checkMatch(caseStatus, afterDispatchStatus);
        var snapshotNote = '（派车快照：' + statusTextMap[status] + ' → ' + statusTextMap[afterDispatchStatus] + '）';

        if (match.level === 'match') {
            hint.className = 'dispatch-hint success';
            hint.innerHTML = '<strong>✓ 可派车 · 状态匹配</strong><br>' + info +
                '<br>按矩阵规则「' + rule.label + '」→ 派车后自动设为<strong>' + statusTextMap[afterDispatchStatus] + '</strong>' +
                '<br>期望：' + rule.expected.map(function(s){return statusTextMap[s]}).join(' / ') +
                ' · ' + snapshotNote;
        } else if (match.level === 'warn') {
            hint.className = 'dispatch-hint warning';
            hint.innerHTML = '<strong>⚡ 可派车 · 状态有偏差</strong><br>' + info +
                '<br>按矩阵规则「' + rule.label + '」→ 派车后设为<strong>' + statusTextMap[afterDispatchStatus] + '</strong>' +
                '，但允许范围：' + rule.allowed.map(function(s){return statusTextMap[s]}).join(' / ') +
                '<br><span class="muted">' + rule.description + '</span> ' + snapshotNote;
        } else {
            hint.className = 'dispatch-hint';
            hint.innerHTML = '<strong>可派车（注意）</strong><br>' + info +
                '<br>派车后将设为「' + statusTextMap[afterDispatchStatus] + '」，与「' + rule.label + '」期望不符' +
                '<br><span class="muted">' + rule.description + '</span> ' + snapshotNote;
        }
    }

    select.addEventListener('change', updateHint);
    if (caseStatusSelect) {
        caseStatusSelect.addEventListener('change', updateHint);
    }
    updateHint();

    form.addEventListener('submit', function(e) {
        var opt = select.options[select.selectedIndex];
        if (opt && opt.value) {
            var dispatchable = opt.getAttribute('data-dispatchable') === '1';
            if (!dispatchable) {
                if (!confirm('所选车辆不可派车，是否仍要提交事件（不分配车辆）？')) {
                    e.preventDefault();
                    return;
                }
                select.value = '';
            }
        }
    });

    var ambSelect = document.getElementById('ambulance-select');
    var ambStatus = document.getElementById('ambulance-status');
    var ambHint = document.getElementById('ambulance-linkage-hint');

    function updateAmbulanceHint() {
        var opt = ambSelect.options[ambSelect.selectedIndex];
        var targetStatus = ambStatus.value;
        if (!opt) {
            ambHint.className = 'dispatch-hint';
            ambHint.textContent = '选择车辆后，若设为待命/检修将自动检查关联事件状态';
            return;
        }

        var code = opt.textContent.split('·')[0].trim();
        var activeCase = opt.getAttribute('data-active-case') || '';
        var currentStatus = opt.getAttribute('data-status') || '';

        if (targetStatus === 'standby' && activeCase) {
            ambHint.className = 'dispatch-hint warning';
            ambHint.innerHTML = '<strong>联动提示：</strong>车辆 ' + code + ' 设为待命，但仍有关联进行中事件 <strong>' + activeCase + '</strong>，请确认是否需要结案。';
        } else if (targetStatus === 'maintenance' && activeCase) {
            ambHint.className = 'dispatch-hint error';
            ambHint.innerHTML = '<strong>警告：</strong>车辆 ' + code + ' 设为检修，但仍有关联进行中事件 <strong>' + activeCase + '</strong>，请先重新调度再设检修。';
        } else if (targetStatus === 'dispatching' && !activeCase && currentStatus === 'standby') {
            ambHint.className = 'dispatch-hint success';
            ambHint.innerHTML = '<strong>提示：</strong>车辆 ' + code + ' 设为出车，请确认已通过新增急救事件完成派车关联。';
        } else {
            ambHint.className = 'dispatch-hint';
            ambHint.textContent = '车辆 ' + code + ' · 当前状态：' + ({
                'standby':'待命','dispatching':'出车','on_scene':'现场处置','transporting':'转运中','maintenance':'检修'
            }[currentStatus] || currentStatus) + (activeCase ? ' · 关联事件：' + activeCase : '');
        }
    }

    ambSelect.addEventListener('change', updateAmbulanceHint);
    ambStatus.addEventListener('change', updateAmbulanceHint);
    updateAmbulanceHint();

    var filterStatus = document.getElementById('filter-status');
    var filterHospital = document.getElementById('filter-hospital');
    var filterCode = document.getElementById('filter-code');
    var filterReset = document.getElementById('filter-reset');
    var tbody = document.getElementById('ambulance-tbody');
    var resultCount = document.getElementById('filter-result-count');
    var emptyResult = document.getElementById('empty-filter-result');
    var totalCount = tbody ? tbody.querySelectorAll('tr').length : 0;

    function applyFilters() {
        if (!tbody) return;
        var rows = tbody.querySelectorAll('tr');
        var statusVal = (filterStatus ? filterStatus.value : '').trim();
        var hospitalVal = (filterHospital ? filterHospital.value : '').trim();
        var codeVal = (filterCode ? filterCode.value : '').trim().toLowerCase();
        var visible = 0;

        rows.forEach(function(row) {
            var matchStatus = !statusVal || row.getAttribute('data-status') === statusVal;
            var matchHospital = !hospitalVal || row.getAttribute('data-hospital') === hospitalVal;
            var matchCode = !codeVal || (row.getAttribute('data-code') || '').toLowerCase().indexOf(codeVal) !== -1;

            if (matchStatus && matchHospital && matchCode) {
                row.style.display = '';
                visible++;
            } else {
                row.style.display = 'none';
            }
        });

        if (resultCount) {
            resultCount.textContent = '显示 ' + visible + ' / 共 ' + totalCount + ' 辆';
        }
        if (emptyResult) {
            emptyResult.style.display = visible === 0 ? '' : 'none';
        }
    }

    function resetFilters() {
        if (filterStatus) filterStatus.value = '';
        if (filterHospital) filterHospital.value = '';
        if (filterCode) filterCode.value = '';
        applyFilters();
    }

    if (filterStatus) filterStatus.addEventListener('change', applyFilters);
    if (filterHospital) filterHospital.addEventListener('change', applyFilters);
    if (filterCode) {
        var timer;
        filterCode.addEventListener('input', function() {
            clearTimeout(timer);
            timer = setTimeout(applyFilters, 150);
        });
    }
    if (filterReset) filterReset.addEventListener('click', resetFilters);

    applyFilters();
})();
</script>
<script>
(function() {
    var newRow = document.querySelector('.case-row-new');
    if (newRow) {
        var metric = document.getElementById('metric-active-cases');
        setTimeout(function() {
            newRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 250);
        if (metric) {
            metric.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }
})();
</script>

    </div>

    <div class="tab-pane <?= $activeTab === 'profile' ? 'active' : '' ?>" id="tab-profile">

<section class="metrics compact">
    <div><span>档案总数</span><strong><?= count($ambulance_profiles ?? []) ?></strong></div>
    <div><span>待命车辆</span><strong><?= count(array_filter($ambulance_profiles ?? [], fn($a) => $a['status'] === 'standby')) ?></strong></div>
    <div><span>勤务中</span><strong><?= count(array_filter($ambulance_profiles ?? [], fn($a) => in_array($a['status'], ['dispatching', 'on_scene', 'transporting'], true))) ?></strong></div>
    <div><span>检修中</span><strong><?= count(array_filter($ambulance_profiles ?? [], fn($a) => $a['status'] === 'maintenance')) ?></strong></div>
</section>

<section class="grid two">
    <div class="panel">
        <div class="panel-head">
            <h2>新增救护车档案</h2>
            <span>车辆接入 · 基础信息录入</span>
        </div>
        <form class="stack-form" method="post" action="/admin/ambulances/create" id="ambulance-create-form">
            <label><span>车辆编号</span><input name="code" required placeholder="如：AMB-021"></label>
            <label><span>车牌号</span><input name="plate_no" required placeholder="如：沪A·12021"></label>
            <label><span>所属医院</span><input name="hospital" required placeholder="如：第一人民医院"></label>
            <label><span>驾驶员</span><input name="driver_name" required placeholder="如：李师傅"></label>
            <div class="form-row">
                <label>
                    <span>初始状态</span>
                    <select name="status" id="profile-status">
                        <option value="standby">待命</option>
                        <option value="dispatching">出车</option>
                        <option value="on_scene">现场处置</option>
                        <option value="transporting">转运中</option>
                        <option value="maintenance">检修</option>
                    </select>
                </label>
            </div>
            <label><span>当前位置</span><input name="location" id="profile-location" placeholder="待命车辆需填写位置"></label>
            <div class="dispatch-hint" id="profile-hint">填写车辆基础档案信息，用于车辆接入监管流程</div>
            <button type="submit">创建档案</button>
        </form>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h2>档案列表</h2>
            <span>基础信息管理 · 全部车辆</span>
        </div>
        <div class="vehicle-filter-bar">
            <div class="filter-item">
                <label>状态</label>
                <select id="profile-filter-status">
                    <option value="">全部状态</option>
                    <option value="standby">待命</option>
                    <option value="dispatching">出车</option>
                    <option value="on_scene">现场处置</option>
                    <option value="transporting">转运中</option>
                    <option value="maintenance">检修</option>
                </select>
            </div>
            <div class="filter-item">
                <label>医院</label>
                <select id="profile-filter-hospital">
                    <option value="">全部医院</option>
                    <?php
                        $profileHospitals = [];
                        foreach ($ambulance_profiles ?? [] as $amb) {
                            if (!empty($amb['hospital']) && !in_array($amb['hospital'], $profileHospitals, true)) {
                                $profileHospitals[] = $amb['hospital'];
                            }
                        }
                        sort($profileHospitals);
                    ?>
                    <?php foreach ($profileHospitals as $h): ?>
                        <option value="<?= h($h) ?>"><?= h($h) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item filter-code">
                <label>编号/车牌</label>
                <input type="text" id="profile-filter-keyword" placeholder="输入编号或车牌号">
            </div>
            <div class="filter-item filter-actions">
                <button type="button" id="profile-filter-reset" class="btn-reset">重置</button>
            </div>
            <div class="filter-result-info">
                <span id="profile-filter-result-count">共 <?= count($ambulance_profiles ?? []) ?> 辆</span>
            </div>
        </div>
        <table class="vehicle-table profile-table">
            <thead>
                <tr>
                    <th>车辆编号</th>
                    <th>车牌号</th>
                    <th>所属医院</th>
                    <th>驾驶员</th>
                    <th>状态</th>
                    <th>位置</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody id="profile-tbody">
            <?php foreach ($ambulance_profiles ?? [] as $amb): ?>
                <tr data-status="<?= h($amb['status']) ?>"
                    data-hospital="<?= h($amb['hospital']) ?>"
                    data-code="<?= h($amb['code']) ?>"
                    data-plate="<?= h($amb['plate_no']) ?>"
                    data-id="<?= (int)$amb['id'] ?>">
                    <td><strong><?= h($amb['code']) ?></strong></td>
                    <td><?= h($amb['plate_no']) ?></td>
                    <td><?= h($amb['hospital']) ?></td>
                    <td><?= h($amb['driver_name']) ?></td>
                    <td>
                        <span class="status-tag <?= statusClass($amb['status']) ?>">
                            <?= statusText($amb['status']) ?>
                        </span>
                    </td>
                    <td class="muted"><?= h($amb['location'] ?? '—') ?></td>
                    <td>
                        <div class="row-actions">
                            <button type="button" class="btn-edit" data-id="<?= (int)$amb['id'] ?>">编辑</button>
                            <form method="post" action="/admin/ambulances/delete" class="inline-form" 
                                  onsubmit="return confirm('确定要删除救护车 <?= h($amb['code']) ?> 的档案吗？');">
                                <input type="hidden" name="id" value="<?= (int)$amb['id'] ?>">
                                <button type="submit" class="btn-delete">删除</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div id="profile-empty-result" class="empty-filter-result" style="display:none;">
            <span class="empty-icon">🔍</span>
            <p>没有匹配的车辆档案</p>
        </div>
    </div>
</section>

<div class="modal" id="edit-modal" style="display:none;">
    <div class="modal-overlay" data-close="modal"></div>
    <div class="modal-dialog">
        <div class="modal-head">
            <h3>编辑救护车档案</h3>
            <button type="button" class="modal-close" data-close="modal">×</button>
        </div>
        <form class="stack-form" method="post" action="/admin/ambulances/update-profile" id="edit-form">
            <input type="hidden" name="id" id="edit-id">
            <label><span>车辆编号</span><input name="code" id="edit-code" required></label>
            <label><span>车牌号</span><input name="plate_no" id="edit-plate" required></label>
            <label><span>所属医院</span><input name="hospital" id="edit-hospital" required></label>
            <label><span>驾驶员</span><input name="driver_name" id="edit-driver" required></label>
            <div class="form-row">
                <label>
                    <span>状态</span>
                    <select name="status" id="edit-status">
                        <option value="standby">待命</option>
                        <option value="dispatching">出车</option>
                        <option value="on_scene">现场处置</option>
                        <option value="transporting">转运中</option>
                        <option value="maintenance">检修</option>
                    </select>
                </label>
            </div>
            <label><span>当前位置</span><input name="location" id="edit-location"></label>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" data-close="modal">取消</button>
                <button type="submit" class="btn-submit">保存修改</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var profileStatus = document.getElementById('profile-status');
    var profileLocation = document.getElementById('profile-location');
    var profileHint = document.getElementById('profile-hint');

    function updateProfileHint() {
        if (!profileStatus || !profileHint) return;
        var status = profileStatus.value;
        if (status === 'standby') {
            profileHint.className = 'dispatch-hint warning';
            profileHint.textContent = '待命车辆必须填写当前位置，用于派车调度参考';
            if (profileLocation) profileLocation.required = true;
        } else if (status === 'maintenance') {
            profileHint.className = 'dispatch-hint';
            profileHint.textContent = '检修车辆暂不可参与派车调度';
            if (profileLocation) profileLocation.required = false;
        } else {
            profileHint.className = 'dispatch-hint';
            profileHint.textContent = '勤务中车辆将自动参与调度匹配';
            if (profileLocation) profileLocation.required = false;
        }
    }
    if (profileStatus) profileStatus.addEventListener('change', updateProfileHint);
    updateProfileHint();

    var filterStatus = document.getElementById('profile-filter-status');
    var filterHospital = document.getElementById('profile-filter-hospital');
    var filterKeyword = document.getElementById('profile-filter-keyword');
    var filterReset = document.getElementById('profile-filter-reset');
    var tbody = document.getElementById('profile-tbody');
    var resultCount = document.getElementById('profile-filter-result-count');
    var emptyResult = document.getElementById('profile-empty-result');
    var totalCount = tbody ? tbody.querySelectorAll('tr').length : 0;

    function applyProfileFilters() {
        if (!tbody) return;
        var rows = tbody.querySelectorAll('tr');
        var statusVal = (filterStatus ? filterStatus.value : '').trim();
        var hospitalVal = (filterHospital ? filterHospital.value : '').trim();
        var keywordVal = (filterKeyword ? filterKeyword.value : '').trim().toLowerCase();
        var visible = 0;

        rows.forEach(function(row) {
            var matchStatus = !statusVal || row.getAttribute('data-status') === statusVal;
            var matchHospital = !hospitalVal || row.getAttribute('data-hospital') === hospitalVal;
            var code = (row.getAttribute('data-code') || '').toLowerCase();
            var plate = (row.getAttribute('data-plate') || '').toLowerCase();
            var matchKeyword = !keywordVal || code.indexOf(keywordVal) !== -1 || plate.indexOf(keywordVal) !== -1;

            if (matchStatus && matchHospital && matchKeyword) {
                row.style.display = '';
                visible++;
            } else {
                row.style.display = 'none';
            }
        });

        if (resultCount) {
            resultCount.textContent = '显示 ' + visible + ' / 共 ' + totalCount + ' 辆';
        }
        if (emptyResult) {
            emptyResult.style.display = visible === 0 ? '' : 'none';
        }
    }

    function resetProfileFilters() {
        if (filterStatus) filterStatus.value = '';
        if (filterHospital) filterHospital.value = '';
        if (filterKeyword) filterKeyword.value = '';
        applyProfileFilters();
    }

    if (filterStatus) filterStatus.addEventListener('change', applyProfileFilters);
    if (filterHospital) filterHospital.addEventListener('change', applyProfileFilters);
    if (filterKeyword) {
        var timer;
        filterKeyword.addEventListener('input', function() {
            clearTimeout(timer);
            timer = setTimeout(applyProfileFilters, 150);
        });
    }
    if (filterReset) filterReset.addEventListener('click', resetProfileFilters);

    var editModal = document.getElementById('edit-modal');
    var editForm = document.getElementById('edit-form');
    var editId = document.getElementById('edit-id');
    var editCode = document.getElementById('edit-code');
    var editPlate = document.getElementById('edit-plate');
    var editHospital = document.getElementById('edit-hospital');
    var editDriver = document.getElementById('edit-driver');
    var editStatus = document.getElementById('edit-status');
    var editLocation = document.getElementById('edit-location');

    var profilesData = {};
    <?php foreach ($ambulance_profiles ?? [] as $amb): ?>
    profilesData[<?= (int)$amb['id'] ?>] = {
        id: <?= (int)$amb['id'] ?>,
        code: '<?= addslashes($amb['code']) ?>',
        plate_no: '<?= addslashes($amb['plate_no']) ?>',
        hospital: '<?= addslashes($amb['hospital']) ?>',
        driver_name: '<?= addslashes($amb['driver_name']) ?>',
        status: '<?= addslashes($amb['status']) ?>',
        location: '<?= addslashes($amb['location'] ?? '') ?>'
    };
    <?php endforeach; ?>

    function openEditModal(id) {
        var data = profilesData[id];
        if (!data || !editModal) return;
        editId.value = data.id;
        editCode.value = data.code;
        editPlate.value = data.plate_no;
        editHospital.value = data.hospital;
        editDriver.value = data.driver_name;
        editStatus.value = data.status;
        editLocation.value = data.location || '';
        editModal.style.display = '';
    }

    function closeEditModal() {
        if (editModal) editModal.style.display = 'none';
    }

    var editButtons = document.querySelectorAll('.btn-edit');
    editButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = parseInt(btn.getAttribute('data-id'), 10);
            openEditModal(id);
        });
    });

    document.querySelectorAll('[data-close="modal"]').forEach(function(el) {
        el.addEventListener('click', closeEditModal);
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeEditModal();
    });
})();
</script>

    </div>
</div>

<?php
$content = ob_get_clean();
$title = '后台管理 - 救护车监管平台';
require __DIR__ . '/layout.php';
