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
