<?php ob_start(); ?>
<section class="admin-head">
    <div>
        <p class="eyebrow">
            <a href="/admin" class="breadcrumb-link">← 返回后台管理</a>
        </p>
        <h1>急救事件详情</h1>
    </div>
    <div class="operator">当前用户：<?= h($user['name']) ?>（<?= h($user['role']) ?>）</div>
</section>

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

<section class="case-detail-header panel">
    <div class="panel-head">
        <h2>事件基本信息</h2>
        <span>调度处理 · 集中查看</span>
    </div>
    <div class="case-detail-info">
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">事件编号</span>
                <span class="info-value case-no-value"><?= h($case['case_no']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">患者/呼叫人</span>
                <span class="info-value"><?= h($case['patient_name']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">地址</span>
                <span class="info-value address-value"><?= h($case['address']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">优先级</span>
                <span class="info-value">
                    <span class="badge priority-<?= h($case['priority']) ?> priority-large"><?= priorityText($case['priority']) ?></span>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">派车编号</span>
                <span class="info-value">
                    <?php if ($has_ambulance): ?>
                        <strong class="ambulance-code"><?= h($case['assigned_ambulance']) ?></strong>
                    <?php else: ?>
                        <span class="dispatch-tag none">待派车</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">当前状态</span>
                <span class="info-value">
                    <span class="status-tag <?= statusClass($case['status']) ?> status-large"><?= statusText($case['status']) ?></span>
                </span>
            </div>
        </div>

        <div class="case-meta-row">
            <div class="meta-item">
                <span class="meta-label">创建时间</span>
                <span class="meta-value"><?= h($case['created_at']) ?></span>
            </div>
            <?php if ($has_ambulance && !empty($case['dispatched_at'])): ?>
            <div class="meta-item">
                <span class="meta-label">派车时间</span>
                <span class="meta-value"><?= h($case['dispatched_at']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($has_ambulance && !empty($case['dispatch_vehicle_status'])): ?>
            <div class="meta-item">
                <span class="meta-label">派车时车辆状态</span>
                <span class="meta-value">
                    <span class="status-tag-small status-<?= h($case['dispatch_vehicle_status']) ?>"><?= statusText($case['dispatch_vehicle_status']) ?></span>
                </span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($has_ambulance): ?>
<section class="panel case-detail-ambulance">
    <div class="panel-head">
        <h2>派车信息</h2>
        <span>关联车辆 · 实时状态</span>
    </div>
    <div class="ambulance-detail-info">
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">车辆编号</span>
                <span class="info-value"><strong><?= h($case['assigned_ambulance']) ?></strong></span>
            </div>
            <?php if (!empty($case['ambulance_plate_no'])): ?>
            <div class="info-item">
                <span class="info-label">车牌号</span>
                <span class="info-value"><?= h($case['ambulance_plate_no']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($case['ambulance_hospital'])): ?>
            <div class="info-item">
                <span class="info-label">所属医院</span>
                <span class="info-value"><?= h($case['ambulance_hospital']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($case['ambulance_driver_name'])): ?>
            <div class="info-item">
                <span class="info-label">驾驶员</span>
                <span class="info-value"><?= h($case['ambulance_driver_name']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <span class="info-label">车辆当前状态</span>
                <span class="info-value">
                    <?php if (!empty($case['ambulance_status'])): ?>
                        <span class="status-tag <?= statusClass($case['ambulance_status']) ?> status-large"><?= statusText($case['ambulance_status']) ?></span>
                    <?php else: ?>
                        <span style="color:var(--muted);">车辆已移除</span>
                    <?php endif; ?>
                </span>
            </div>
            <?php if (!empty($case['ambulance_location'])): ?>
            <div class="info-item">
                <span class="info-label">车辆当前位置</span>
                <span class="info-value"><?= h($case['ambulance_location']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="panel case-detail-match">
    <div class="panel-head">
        <h2>状态匹配检查</h2>
        <span>合规校验 · 调度一致性</span>
    </div>
    <div class="match-check-detail">
        <div class="match-result-row">
            <?php if ($match['level'] === 'match'): ?>
                <div class="match-result-badge match">
                    <span class="match-icon">✓</span>
                    <span class="match-text"><?= matchLevelText($match['level']) ?></span>
                </div>
            <?php elseif ($match['level'] === 'warn'): ?>
                <div class="match-result-badge warn">
                    <span class="match-icon">⚡</span>
                    <span class="match-text"><?= matchLevelText($match['level']) ?></span>
                </div>
            <?php elseif ($match['level'] === 'error'): ?>
                <div class="match-result-badge error">
                    <span class="match-icon">⚠</span>
                    <span class="match-text"><?= matchLevelText($match['level']) ?></span>
                </div>
            <?php else: ?>
                <div class="match-result-badge none">
                    <span class="match-icon">—</span>
                    <span class="match-text"><?= matchLevelText($match['level']) ?></span>
                </div>
            <?php endif; ?>

            <div class="match-detail-reason">
                <?php if ($match['level'] !== 'none'): ?>
                    <p><strong>说明：</strong><?= h($match['reason']) ?></p>
                    <?php if (!empty($match['expected'])): ?>
                        <p class="muted"><strong>期望：</strong><?= h($match['expected']) ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="muted">事件待派车，暂无状态匹配信息</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="matrix-panel detail-matrix">
            <div class="matrix-title">状态匹配矩阵（参考）</div>
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
    </div>
</section>
<?php endif; ?>

<?php if ($has_ambulance): ?>
<section class="panel audit-panel case-detail-audit">
    <div class="panel-head">
        <h2>关联车辆状态变更记录</h2>
        <span>操作留痕 · 调度追溯</span>
    </div>
    <?php if (empty($audit_table_available)): ?>
        <div class="alerts-empty">
            <p class="muted">⚠ 审计功能待初始化</p>
        </div>
    <?php elseif (empty($status_audit_logs)): ?>
        <div class="alerts-empty muted">
            <p>暂无关联的车辆状态变更记录</p>
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
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php
$content = ob_get_clean();
$title = '事件详情 - ' . ($case['case_no'] ?? '') . ' - 救护车监管平台';
require __DIR__ . '/layout.php';
