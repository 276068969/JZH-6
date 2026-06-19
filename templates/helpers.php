<?php

if (!function_exists('h')) {
    function h(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('statusText')) {
    function statusText(string $status): string
    {
        return [
            'standby' => '待命',
            'dispatching' => '出车',
            'on_scene' => '现场处置',
            'transporting' => '转运中',
            'maintenance' => '检修',
            'reported' => '已上报',
            'accepted' => '已受理',
            'closed' => '已关闭',
            'open' => '未处理',
            'resolved' => '已处理',
        ][$status] ?? $status;
    }
}

if (!function_exists('priorityText')) {
    function priorityText(string $priority): string
    {
        return [
            'high' => '高',
            'medium' => '中',
            'low' => '低',
        ][$priority] ?? $priority;
    }
}

if (!function_exists('statusClass')) {
    function statusClass(string $status): string
    {
        return [
            'standby' => 'status-standby',
            'dispatching' => 'status-dispatching',
            'on_scene' => 'status-onscene',
            'transporting' => 'status-transporting',
            'maintenance' => 'status-maintenance',
            'reported' => 'status-reported',
            'accepted' => 'status-accepted',
            'closed' => 'status-closed',
            'open' => 'status-open',
            'resolved' => 'status-resolved',
        ][$status] ?? '';
    }
}

if (!function_exists('isAmbulanceDispatchable')) {
    function isAmbulanceDispatchable(array $ambulance): bool
    {
        if ($ambulance['status'] === 'maintenance') {
            return false;
        }
        if (!empty($ambulance['active_case_no'])) {
            return false;
        }
        return true;
    }
}

if (!function_exists('ambulanceOptionLabel')) {
    function ambulanceOptionLabel(array $ambulance): string
    {
        $label = $ambulance['code'] . ' · ' . $ambulance['hospital'] . ' · ' . statusText($ambulance['status']);
        if (!empty($ambulance['active_case_no'])) {
            $label .= ' · 占用中(' . $ambulance['active_case_no'] . ')';
        } elseif ($ambulance['status'] === 'standby') {
            $label .= ' · 可派车';
        } elseif ($ambulance['status'] === 'maintenance') {
            $label .= ' · 不可派';
        }
        return $label;
    }
}

if (!function_exists('dispatchStatusMatrix')) {
    function dispatchStatusMatrix(): array
    {
        return [
            'reported' => [
                'label' => '已上报',
                'expected' => ['dispatching'],
                'allowed' => ['dispatching', 'on_scene', 'transporting'],
                'ideal' => 'dispatching',
                'description' => '事件已上报派车，车辆应处于「出车」状态',
            ],
            'accepted' => [
                'label' => '已受理',
                'expected' => ['on_scene', 'transporting'],
                'allowed' => ['dispatching', 'on_scene', 'transporting'],
                'ideal' => 'on_scene',
                'description' => '事件已受理，车辆应处于「现场处置」或「转运中」',
            ],
            'closed' => [
                'label' => '已关闭',
                'expected' => ['standby', 'maintenance'],
                'allowed' => ['standby', 'maintenance'],
                'ideal' => 'standby',
                'description' => '事件已关闭，车辆应回到「待命」或「检修」',
            ],
        ];
    }
}

if (!function_exists('checkCaseVehicleStatusMatch')) {
    function checkCaseVehicleStatusMatch(string $caseStatus, ?string $vehicleStatus, bool $hasAmbulance): array
    {
        if (!$hasAmbulance || $vehicleStatus === null) {
            if ($caseStatus === 'closed') {
                return ['level' => 'match', 'reason' => '已关闭事件无派车', 'expected' => ''];
            }
            return ['level' => 'none', 'reason' => '待派车', 'expected' => ''];
        }

        $matrix = dispatchStatusMatrix();
        $rule = $matrix[$caseStatus] ?? null;

        if (!$rule) {
            return ['level' => 'none', 'reason' => '未知事件状态', 'expected' => ''];
        }

        if (in_array($vehicleStatus, $rule['expected'], true)) {
            return [
                'level' => 'match',
                'reason' => '状态匹配',
                'expected' => $rule['description'],
            ];
        }

        if (in_array($vehicleStatus, $rule['allowed'], true)) {
            return [
                'level' => 'warn',
                'reason' => '状态有偏差（' . statusText($vehicleStatus) . '），但在合理范围内',
                'expected' => $rule['description'] . '，期望 ' . statusText($rule['ideal']),
            ];
        }

        return [
            'level' => 'error',
            'reason' => '状态严重不一致：事件' . $rule['label'] . ' 但车辆' . statusText($vehicleStatus),
            'expected' => $rule['description'],
        ];
    }
}

if (!function_exists('matchLevelClass')) {
    function matchLevelClass(string $level): string
    {
        return [
            'match' => 'match-level-match',
            'warn' => 'match-level-warn',
            'error' => 'match-level-error',
            'none' => 'match-level-none',
        ][$level] ?? '';
    }
}

if (!function_exists('matchLevelText')) {
    function matchLevelText(string $level): string
    {
        return [
            'match' => '状态匹配',
            'warn' => '状态有偏差',
            'error' => '状态不一致',
            'none' => '待派车',
        ][$level] ?? '';
    }
}

if (!function_exists('alertTypeConfig')) {
    function alertTypeConfig(): array
    {
        return [
            'device_offline' => [
                'label' => '设备离线',
                'icon' => '📡',
                'class' => 'alert-type-device-offline',
                'priority' => 1,
            ],
            'response_timeout' => [
                'label' => '响应超时',
                'icon' => '⏱',
                'class' => 'alert-type-response-timeout',
                'priority' => 2,
            ],
            'status_mismatch' => [
                'label' => '状态异常',
                'icon' => '⚠',
                'class' => 'alert-type-status-mismatch',
                'priority' => 3,
            ],
            'pending_dispatch' => [
                'label' => '待派车',
                'icon' => '🚑',
                'class' => 'alert-type-pending-dispatch',
                'priority' => 4,
            ],
            'trajectory_deviation' => [
                'label' => '轨迹偏离',
                'icon' => '🧭',
                'class' => 'alert-type-trajectory',
                'priority' => 5,
            ],
            'maintenance' => [
                'label' => '检修提醒',
                'icon' => '🔧',
                'class' => 'alert-type-maintenance',
                'priority' => 6,
            ],
            'driver_fatigue' => [
                'label' => '疲劳预警',
                'icon' => '😴',
                'class' => 'alert-type-fatigue',
                'priority' => 7,
            ],
            'supply_shortage' => [
                'label' => '耗材不足',
                'icon' => '📦',
                'class' => 'alert-type-supply',
                'priority' => 8,
            ],
            'approval_pending' => [
                'label' => '待审批',
                'icon' => '📋',
                'class' => 'alert-type-approval',
                'priority' => 9,
            ],
            'default' => [
                'label' => '风险告警',
                'icon' => '🔔',
                'class' => 'alert-type-default',
                'priority' => 99,
            ],
        ];
    }
}

if (!function_exists('classifyAlert')) {
    function classifyAlert(string $title, string $description = ''): string
    {
        $text = $title . ' ' . $description;

        if (preg_match('/设备离线|GPS.*中断|定位数据中断|设备通信异常|数据.*未上报/', $text)) {
            return 'device_offline';
        }
        if (preg_match('/响应时间超阈值|响应超时|转运超时|超时预警/', $text)) {
            return 'response_timeout';
        }
        if (preg_match('/状态严重不一致|状态有偏差|状态不一致/', $text)) {
            return 'status_mismatch';
        }
        if (preg_match('/未派车|派车遗漏|事件积压/', $text)) {
            return 'pending_dispatch';
        }
        if (preg_match('/轨迹偏离/', $text)) {
            return 'trajectory_deviation';
        }
        if (preg_match('/检修超期|检修.*超|车辆未按规定|未按规定回库/', $text)) {
            return 'maintenance';
        }
        if (preg_match('/疲劳|连续执勤/', $text)) {
            return 'driver_fatigue';
        }
        if (preg_match('/耗材不足|库存不足/', $text)) {
            return 'supply_shortage';
        }
        if (preg_match('/审批待办|待审批/', $text)) {
            return 'approval_pending';
        }

        return 'default';
    }
}

if (!function_exists('alertTypeInfo')) {
    function alertTypeInfo(string $title, string $description = ''): array
    {
        $type = classifyAlert($title, $description);
        $config = alertTypeConfig();
        return $config[$type] ?? $config['default'];
    }
}
