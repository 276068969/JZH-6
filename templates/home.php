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
        <div class="alerts-refresh-info">
            <span>最近 12 条</span>
            <span class="refresh-indicator" id="cases-refresh-indicator" title="每 30 秒自动刷新">
                <span class="refresh-dot"></span>
                <span id="cases-refresh-time">实时同步中</span>
            </span>
        </div>
    </div>
    <table class="case-table-front">
        <thead>
            <tr>
                <th class="col-case-no">编号</th>
                <th class="col-priority">级别</th>
                <th class="col-address">地址</th>
                <th class="col-status">状态</th>
                <th class="col-ambulance">车辆</th>
            </tr>
        </thead>
        <tbody id="cases-tbody">
        <?php foreach ($cases as $case): ?>
            <?php
                $isClosed = $case['status'] === 'closed';
                $hasAmbulance = !empty($case['assigned_ambulance']);
            ?>
            <tr class="case-row case-status-<?= h($case['status']) ?><?= $isClosed ? ' case-row-closed' : '' ?>">
                <td class="col-case-no">
                    <span class="case-no-text"><?= h($case['case_no']) ?></span>
                </td>
                <td class="col-priority">
                    <span class="priority-badge priority-<?= h($case['priority']) ?>">
                        <span class="priority-dot"></span>
                        <?= priorityText($case['priority']) ?>级
                    </span>
                </td>
                <td class="col-address">
                    <span class="address-text" title="<?= h($case['address'] ?? '') ?>">
                        <?= h($case['address'] ?? '—') ?>
                    </span>
                </td>
                <td class="col-status">
                    <span class="status-tag <?= statusClass($case['status']) ?>">
                        <?= statusText($case['status']) ?>
                    </span>
                </td>
                <td class="col-ambulance">
                    <?php if ($isClosed): ?>
                        <span class="ambulance-info ambulance-closed">
                            <span class="ambulance-icon">✓</span>
                            <span class="ambulance-text">已结案</span>
                        </span>
                    <?php elseif ($hasAmbulance): ?>
                        <span class="ambulance-info ambulance-assigned">
                            <span class="ambulance-icon">🚑</span>
                            <span class="ambulance-code"><?= h($case['assigned_ambulance']) ?></span>
                        </span>
                    <?php else: ?>
                        <span class="ambulance-info ambulance-pending">
                            <span class="ambulance-icon">⏳</span>
                            <span class="ambulance-text">待派车</span>
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>风险告警</h2>
        <div class="alerts-refresh-info">
            <span>设备、响应、轨迹异常</span>
            <span class="refresh-indicator" id="refresh-indicator" title="每 30 秒自动刷新">
                <span class="refresh-dot"></span>
                <span id="refresh-time">实时同步中</span>
            </span>
        </div>
    </div>
    <div class="alerts front-alerts" id="front-alerts">
        <?php foreach ($alerts as $alert): ?>
            <?php $isOpen = $alert['status'] === 'open'; ?>
            <article class="front-alert <?= $isOpen ? 'alert-status-open' : 'alert-status-resolved' ?>" data-alert-id="<?= (int) $alert['id'] ?>">
                <div class="front-alert-head">
                    <b class="front-alert-title"><?= h($alert['title']) ?></b>
                    <span class="status-tag <?= statusClass($alert['status']) ?>"><?= statusText($alert['status']) ?></span>
                </div>
                <p class="front-alert-desc"><?= h($alert['description']) ?></p>
                <div class="front-alert-footer">
                    <span class="alert-created"><?= h($alert['created_at']) ?></span>
                    <?php if (!$isOpen && !empty($alert['handler_name'])): ?>
                        <span class="alert-handled-by">处置人：<?= h($alert['handler_name']) ?></span>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<script>
(function() {
    var metricsSection = document.querySelector('.metrics');
    var alertsContainer = document.getElementById('front-alerts');
    var refreshIndicator = document.getElementById('refresh-indicator');
    var refreshTime = document.getElementById('refresh-time');
    var casesTbody = document.getElementById('cases-tbody');
    var casesRefreshIndicator = document.getElementById('cases-refresh-indicator');
    var casesRefreshTime = document.getElementById('cases-refresh-time');
    var statusTextMap = {
        'standby': '待命',
        'dispatching': '出车',
        'on_scene': '现场处置',
        'transporting': '转运中',
        'maintenance': '检修',
        'reported': '已上报',
        'accepted': '已受理',
        'closed': '已关闭',
        'open': '未处理',
        'resolved': '已处理'
    };
    var statusClassMap = {
        'open': 'status-open',
        'resolved': 'status-resolved'
    };
    var caseStatusClassMap = {
        'reported': 'status-reported',
        'accepted': 'status-accepted',
        'closed': 'status-closed'
    };
    var priorityTextMap = {
        'high': '高',
        'medium': '中',
        'low': '低'
    };

    var knownCaseNos = {};
    (function initKnownCases() {
        if (!casesTbody) return;
        var rows = casesTbody.querySelectorAll('tr');
        rows.forEach(function(row) {
            var noEl = row.querySelector('.col-case-no .case-no-text');
            if (noEl) {
                knownCaseNos[noEl.textContent.trim()] = true;
            }
        });
    })();

    function updateMetric(index, count, highlightColor) {
        if (!metricsSection) return;
        var metricsDivs = metricsSection.querySelectorAll('div');
        var div = metricsDivs[index];
        if (!div) return;
        var strongEl = div.querySelector('strong');
        if (!strongEl) return;
        var oldCount = parseInt(strongEl.textContent, 10);
        if (oldCount !== count) {
            strongEl.textContent = count;
            if (highlightColor) {
                div.style.transition = 'background-color 0.3s';
                div.style.backgroundColor = highlightColor;
                setTimeout(function() {
                    div.style.backgroundColor = '';
                }, 1500);
            }
        }
    }

    function renderAlerts(alerts) {
        if (!alertsContainer) return;
        alertsContainer.innerHTML = '';
        alerts.forEach(function(alert) {
            var isOpen = alert.status === 'open';
            var article = document.createElement('article');
            article.className = 'front-alert ' + (isOpen ? 'alert-status-open' : 'alert-status-resolved');
            article.setAttribute('data-alert-id', alert.id);

            var head = document.createElement('div');
            head.className = 'front-alert-head';

            var title = document.createElement('b');
            title.className = 'front-alert-title';
            title.textContent = alert.title;

            var status = document.createElement('span');
            status.className = 'status-tag ' + (statusClassMap[alert.status] || '');
            status.textContent = statusTextMap[alert.status] || alert.status;

            head.appendChild(title);
            head.appendChild(status);

            var desc = document.createElement('p');
            desc.className = 'front-alert-desc';
            desc.textContent = alert.description;

            var footer = document.createElement('div');
            footer.className = 'front-alert-footer';

            var created = document.createElement('span');
            created.className = 'alert-created';
            created.textContent = alert.created_at;
            footer.appendChild(created);

            if (!isOpen && alert.handler_name) {
                var handledBy = document.createElement('span');
                handledBy.className = 'alert-handled-by';
                handledBy.textContent = '处置人：' + alert.handler_name;
                footer.appendChild(handledBy);
            }

            article.appendChild(head);
            article.appendChild(desc);
            article.appendChild(footer);
            alertsContainer.appendChild(article);
        });
    }

    function renderCases(cases) {
        if (!casesTbody) return;
        casesTbody.innerHTML = '';
        cases.forEach(function(c) {
            var isClosed = c.status === 'closed';
            var hasAmbulance = !!c.assigned_ambulance;
            var isNew = !knownCaseNos[c.case_no];
            knownCaseNos[c.case_no] = true;

            var tr = document.createElement('tr');
            tr.className = 'case-row case-status-' + c.status +
                (isClosed ? ' case-row-closed' : '') +
                (isNew ? ' case-row-fresh' : '');

            var tdNo = document.createElement('td');
            tdNo.className = 'col-case-no';
            var noSpan = document.createElement('span');
            noSpan.className = 'case-no-text';
            noSpan.textContent = c.case_no;
            tdNo.appendChild(noSpan);

            var tdPri = document.createElement('td');
            tdPri.className = 'col-priority';
            var priSpan = document.createElement('span');
            priSpan.className = 'priority-badge priority-' + c.priority;
            var priDot = document.createElement('span');
            priDot.className = 'priority-dot';
            priSpan.appendChild(priDot);
            priSpan.appendChild(document.createTextNode((priorityTextMap[c.priority] || c.priority) + '级'));
            tdPri.appendChild(priSpan);

            var tdAddr = document.createElement('td');
            tdAddr.className = 'col-address';
            var addrSpan = document.createElement('span');
            addrSpan.className = 'address-text';
            var addr = c.address || '—';
            addrSpan.textContent = addr;
            addrSpan.setAttribute('title', addr);
            tdAddr.appendChild(addrSpan);

            var tdStatus = document.createElement('td');
            tdStatus.className = 'col-status';
            var stSpan = document.createElement('span');
            stSpan.className = 'status-tag ' + (caseStatusClassMap[c.status] || '');
            stSpan.textContent = statusTextMap[c.status] || c.status;
            tdStatus.appendChild(stSpan);

            var tdAmb = document.createElement('td');
            tdAmb.className = 'col-ambulance';
            var ambInfo = document.createElement('span');
            if (isClosed) {
                ambInfo.className = 'ambulance-info ambulance-closed';
                var i1 = document.createElement('span');
                i1.className = 'ambulance-icon';
                i1.textContent = '✓';
                var t1 = document.createElement('span');
                t1.className = 'ambulance-text';
                t1.textContent = '已结案';
                ambInfo.appendChild(i1);
                ambInfo.appendChild(t1);
            } else if (hasAmbulance) {
                ambInfo.className = 'ambulance-info ambulance-assigned';
                var i2 = document.createElement('span');
                i2.className = 'ambulance-icon';
                i2.textContent = '🚑';
                var code = document.createElement('span');
                code.className = 'ambulance-code';
                code.textContent = c.assigned_ambulance;
                ambInfo.appendChild(i2);
                ambInfo.appendChild(code);
            } else {
                ambInfo.className = 'ambulance-info ambulance-pending';
                var i3 = document.createElement('span');
                i3.className = 'ambulance-icon';
                i3.textContent = '⏳';
                var t3 = document.createElement('span');
                t3.className = 'ambulance-text';
                t3.textContent = '待派车';
                ambInfo.appendChild(i3);
                ambInfo.appendChild(t3);
            }
            tdAmb.appendChild(ambInfo);

            tr.appendChild(tdNo);
            tr.appendChild(tdPri);
            tr.appendChild(tdAddr);
            tr.appendChild(tdStatus);
            tr.appendChild(tdAmb);
            casesTbody.appendChild(tr);
        });
    }

    function formatNow() {
        var now = new Date();
        return now.getHours().toString().padStart(2, '0') + ':' +
               now.getMinutes().toString().padStart(2, '0') + ':' +
               now.getSeconds().toString().padStart(2, '0');
    }

    function refreshData() {
        if (refreshIndicator) {
            refreshIndicator.classList.add('refreshing');
        }
        if (casesRefreshIndicator) {
            casesRefreshIndicator.classList.add('refreshing');
        }
        fetch('/api/overview', { cache: 'no-store' })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.overview) {
                    if (typeof data.overview.ambulances_total !== 'undefined') {
                        updateMetric(0, parseInt(data.overview.ambulances_total, 10), '');
                    }
                    if (typeof data.overview.ambulances_online !== 'undefined') {
                        updateMetric(1, parseInt(data.overview.ambulances_online, 10), '');
                    }
                    if (typeof data.overview.active_cases !== 'undefined') {
                        updateMetric(2, parseInt(data.overview.active_cases, 10), '#dbeafe');
                    }
                    if (typeof data.overview.alerts !== 'undefined') {
                        updateMetric(3, parseInt(data.overview.alerts, 10), '#fef3c7');
                    }
                }
                if (data.alerts) {
                    renderAlerts(data.alerts);
                }
                if (data.cases) {
                    renderCases(data.cases);
                }
                var timeStr = '更新于 ' + formatNow();
                if (refreshTime) {
                    refreshTime.textContent = timeStr;
                }
                if (casesRefreshTime) {
                    casesRefreshTime.textContent = timeStr;
                }
            })
            .catch(function(err) {
                console.error('刷新数据失败:', err);
                if (refreshTime) {
                    refreshTime.textContent = '刷新失败';
                }
                if (casesRefreshTime) {
                    casesRefreshTime.textContent = '刷新失败';
                }
            })
            .finally(function() {
                if (refreshIndicator) {
                    setTimeout(function() {
                        refreshIndicator.classList.remove('refreshing');
                    }, 500);
                }
                if (casesRefreshIndicator) {
                    setTimeout(function() {
                        casesRefreshIndicator.classList.remove('refreshing');
                    }, 500);
                }
            });
    }

    setInterval(refreshData, 30000);
})();
</script>
<?php
$content = ob_get_clean();
$title = '前台态势 - 救护车监管平台';
require __DIR__ . '/layout.php';
