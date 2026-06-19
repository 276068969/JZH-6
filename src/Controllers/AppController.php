<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\ErrorResponse;
use App\Core\View;
use App\Models\DashboardRepository;

final class AppController
{
    private DashboardRepository $repo;

    public function __construct()
    {
        $this->repo = new DashboardRepository();
    }

    public function home(): void
    {
        View::render('home', [
            'overview' => $this->repo->overview(),
            'ambulances' => $this->repo->ambulancesWithDispatchInfo(),
            'cases' => $this->repo->cases(),
            'alerts' => $this->repo->alerts(),
            'user' => Auth::user(),
        ]);
    }

    public function loginForm(string $error = ''): void
    {
        $reason = trim($_GET['reason'] ?? '');
        if ($reason !== '' && $error === '') {
            $error = $reason;
        }
        View::render('login', ['error' => $error]);
    }

    public function login(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        $errors = [];
        if ($username === '') {
            $errors[] = '请输入账号';
        }
        if ($password === '') {
            $errors[] = '请输入密码';
        }

        if (!empty($errors)) {
            $this->loginForm(implode('；', $errors));
            return;
        }

        if (Auth::attempt($username, $password)) {
            header('Location: /admin');
            exit;
        }

        $this->loginForm('账号或密码错误');
    }

    public function logout(): void
    {
        Auth::logout();
        header('Location: /');
    }

    public function admin(): void
    {
        Auth::requireAdmin();
        
        $statusAuditLogs = [];
        $auditTableAvailable = false;
        try {
            $auditTableAvailable = $this->repo->isAuditTableAvailable();
            $statusAuditLogs = $this->repo->recentStatusAuditLogs(20);
        } catch (\Throwable $e) {
            error_log('加载审计日志失败: ' . $e->getMessage());
        }
        
        $lastCreatedCase = $_SESSION['_last_created_case'] ?? null;
        unset($_SESSION['_last_created_case']);

        $lastStatusChangedCase = $_SESSION['_last_status_changed_case'] ?? null;
        $lastStatusChangeInfo = $_SESSION['_last_status_change_info'] ?? null;
        unset($_SESSION['_last_status_changed_case'], $_SESSION['_last_status_change_info']);

        $cases = $this->repo->casesWithAmbulanceInfo();
        $caseStatusActions = [];
        foreach ($cases as $c) {
            $caseStatusActions[$c['case_no']] = $this->repo->getNextStatusActions($c['status']);
        }

        View::render('admin', [
            'overview' => $this->repo->overview(),
            'ambulances' => $this->repo->ambulancesWithDispatchInfo(),
            'cases' => $cases,
            'case_status_actions' => $caseStatusActions,
            'alerts' => $this->repo->alerts(),
            'open_alerts' => $this->repo->openAlerts(),
            'resolved_alerts' => $this->repo->resolvedAlerts(),
            'status_audit_logs' => $statusAuditLogs,
            'audit_table_available' => $auditTableAvailable,
            'user' => Auth::user(),
            'flash' => $this->getFlash(),
            'last_created_case' => $lastCreatedCase,
            'last_status_changed_case' => $lastStatusChangedCase,
            'last_status_change_info' => $lastStatusChangeInfo,
        ]);
    }

    public function handleAlert(): void
    {
        Auth::requireAdmin();
        $alertId = (int) ($_POST['alert_id'] ?? 0);
        $handlingNotes = trim($_POST['handling_notes'] ?? '');
        $user = Auth::user();

        $result = $this->repo->handleAlert($alertId, $handlingNotes, (int) $user['id']);

        if (!$result['success']) {
            $this->setFlash('errors', $result['errors']);
        } else {
            $messages = ['告警「' . $result['alert']['title'] . '」已处置关闭'];
            $this->setFlash('success', $messages);
        }

        header('Location: /admin');
    }

    public function createCase(): void
    {
        Auth::requireAdmin();
        $user = Auth::user();

        $patientName = trim($_POST['patient_name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        $status = $_POST['status'] ?? 'reported';
        $assignedAmbulance = trim($_POST['assigned_ambulance'] ?? '');

        $errors = [];
        if ($patientName === '') {
            $errors[] = '请填写患者/呼叫人姓名';
        }
        if ($address === '') {
            $errors[] = '请填写事件地址';
        }

        $validPriorities = ['high', 'medium', 'low'];
        if (!in_array($priority, $validPriorities, true)) {
            $errors[] = '无效的优先级';
        }

        $validStatuses = ['reported', 'accepted'];
        if (!in_array($status, $validStatuses, true)) {
            $errors[] = '无效的事件状态';
        }

        if (!empty($errors)) {
            $this->setFlash('errors', $errors);
            header('Location: /admin');
            return;
        }

        $result = $this->repo->createCaseWithDispatch(
            [
                'patient_name' => $patientName,
                'priority' => $priority,
                'address' => $address,
                'status' => $status,
                'assigned_ambulance' => $assignedAmbulance,
            ],
            (int)$user['id'],
            $user['name'],
            $user['role']
        );

        if (!$result['success']) {
            $this->setFlash('errors', $result['errors']);
        } else {
            $dispatchInfo = $result['dispatch_info'] ?? [];
            $message = '事件 ' . $result['case_no'] . ' 创建成功';
            if (!empty($dispatchInfo['vehicle_code'])) {
                $message .= '，已派车 ' . $dispatchInfo['vehicle_code'];
            }
            if (!empty($result['warnings'])) {
                $this->setFlash('warnings', $result['warnings']);
            }
            $this->setFlash('success', [$message]);
            $_SESSION['_last_created_case'] = $result['case_no'];
        }

        header('Location: /admin');
    }

    public function updateAmbulance(): void
    {
        Auth::requireAdmin();
        $user = Auth::user();

        $id = (int) ($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'standby';
        $location = trim($_POST['location'] ?? '');

        $errors = [];
        if ($id <= 0) {
            $errors[] = '请选择有效的车辆';
        }

        $validStatuses = ['standby', 'dispatching', 'on_scene', 'transporting', 'maintenance'];
        if (!in_array($status, $validStatuses, true)) {
            $errors[] = '无效的车辆状态';
        }

        if ($status === 'standby' && $location === '') {
            $errors[] = '待命车辆必须填写当前位置';
        }

        if (!empty($errors)) {
            $this->setFlash('errors', $errors);
            header('Location: /admin');
            return;
        }

        $result = $this->repo->updateAmbulanceWithLinkage(
            $id,
            $status,
            $location,
            (int)$user['id'],
            $user['name'],
            $user['role']
        );

        if (!$result['success']) {
            $this->setFlash('errors', $result['errors']);
        } else {
            $this->setFlash('success', ['车辆状态更新成功']);
            if (!empty($result['warnings'])) {
                $this->setFlash('warnings', $result['warnings']);
            }
        }

        header('Location: /admin');
    }

    public function overviewApi(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $overview = $this->repo->overview();
            $ambulances = $this->repo->ambulances();
            $cases = $this->repo->cases();
            $alerts = $this->repo->alerts();
            $priorityStats = $this->repo->casePriorityStatistics();

            echo json_encode([
                'success' => true,
                'overview' => $overview,
                'ambulances' => $ambulances,
                'cases' => $cases,
                'alerts' => $alerts,
                'aggregations' => [
                    'ambulance_by_status' => $overview['ambulance_status_breakdown'],
                    'case_by_priority' => $overview['case_priority_breakdown'],
                    'alert_by_status' => $overview['alert_status_breakdown'],
                    'case_priority_statistics' => $priorityStats,
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('api/overview 异常: ' . $e->getMessage());
            ErrorResponse::databaseError('获取概览数据失败，请稍后重试');
        }
    }

    public function casePriorityStatsApi(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $stats = $this->repo->casePriorityStatistics();

            echo json_encode([
                'success' => true,
                'data' => $stats,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('api/cases/priority-stats 异常: ' . $e->getMessage());
            ErrorResponse::databaseError('获取事件优先级统计失败，请稍后重试');
        }
    }

    public function dispatchCheckApi(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $code = trim($_GET['code'] ?? '');
            if ($code === '') {
                echo json_encode([
                    'success' => true,
                    'available' => false,
                    'reason' => '请输入车辆编号'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $result = $this->repo->ambulanceDispatchCheckApi($code);
            echo json_encode(
                array_merge(['success' => true], $result),
                JSON_UNESCAPED_UNICODE
            );
        } catch (\Throwable $e) {
            error_log('dispatchCheckApi 异常: ' . $e->getMessage());
            ErrorResponse::databaseError('查询车辆状态失败，请稍后重试');
        }
    }

    public function updateAmbulanceApi(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!is_array($input)) {
                $input = $_POST;
            }

            $id = (int) ($input['id'] ?? 0);
            $status = $input['status'] ?? 'standby';
            $location = trim($input['location'] ?? '');

            if ($id <= 0) {
                ErrorResponse::invalidParams(['无效的车辆ID'], '无效的车辆ID');
                return;
            }

            $validStatuses = ['standby', 'dispatching', 'on_scene', 'transporting', 'maintenance'];
            if (!in_array($status, $validStatuses, true)) {
                ErrorResponse::invalidParams(['无效的车辆状态'], '无效的车辆状态');
                return;
            }

            if ($status === 'standby' && $location === '') {
                ErrorResponse::validationError(['待命车辆必须填写当前位置'], '数据校验失败');
                return;
            }

            $user = Auth::user();
            if (!$user) {
                ErrorResponse::unauthorized();
                return;
            }

            if ($user['role'] !== 'admin') {
                ErrorResponse::forbidden();
                return;
            }

            $result = $this->repo->updateAmbulanceWithLinkage(
                $id,
                $status,
                $location,
                (int)$user['id'],
                $user['name'],
                $user['role']
            );

            if (!$result['success']) {
                ErrorResponse::validationError($result['errors']);
                return;
            }

            echo json_encode([
                'success' => true,
                'message' => '车辆状态更新成功',
                'warnings' => $result['warnings'] ?? [],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('updateAmbulanceApi 异常: ' . $e->getMessage());
            ErrorResponse::databaseError('车辆更新失败，请稍后重试');
        }
    }

    public function caseDetail(string $caseNo): void
    {
        Auth::requireAdmin();

        $case = $this->repo->findCaseByCaseNo($caseNo);

        if (!$case) {
            http_response_code(404);
            echo '事件不存在';
            return;
        }

        $hasAmbulance = !empty($case['assigned_ambulance']);
        $match = checkCaseVehicleStatusMatch(
            $case['status'],
            $case['ambulance_status'] ?? null,
            $hasAmbulance
        );

        $statusAuditLogs = [];
        $auditTableAvailable = false;
        $caseAuditLogs = [];
        $caseAuditAvailable = false;
        try {
            $auditTableAvailable = $this->repo->isAuditTableAvailable();
            if ($auditTableAvailable && $hasAmbulance) {
                $statusAuditLogs = $this->repo->recentStatusAuditLogsForCase($caseNo, 10);
            }
            $caseAuditAvailable = $this->repo->isCaseAuditTableAvailable();
            if ($caseAuditAvailable) {
                $caseAuditLogs = $this->repo->recentCaseStatusAuditLogsForCase($caseNo, 20);
            }
        } catch (\Throwable $e) {
            error_log('加载审计日志失败: ' . $e->getMessage());
        }

        $nextStatusActions = $this->repo->getNextStatusActions($case['status']);

        View::render('case_detail', [
            'case' => $case,
            'match' => $match,
            'has_ambulance' => $hasAmbulance,
            'status_audit_logs' => $statusAuditLogs,
            'audit_table_available' => $auditTableAvailable,
            'case_audit_logs' => $caseAuditLogs,
            'case_audit_available' => $caseAuditAvailable,
            'next_status_actions' => $nextStatusActions,
            'user' => Auth::user(),
            'flash' => $this->getFlash(),
        ]);
    }

    public function ambulanceProfile(): void
    {
        Auth::requireAdmin();

        $statusAuditLogs = [];
        $auditTableAvailable = false;
        try {
            $auditTableAvailable = $this->repo->isAuditTableAvailable();
            $statusAuditLogs = $this->repo->recentStatusAuditLogs(20);
        } catch (\Throwable $e) {
            error_log('加载审计日志失败: ' . $e->getMessage());
        }

        View::render('admin', [
            'overview' => $this->repo->overview(),
            'ambulances' => $this->repo->ambulancesWithDispatchInfo(),
            'ambulance_profiles' => $this->repo->allAmbulancesForProfile(),
            'cases' => $this->repo->casesWithAmbulanceInfo(),
            'alerts' => $this->repo->alerts(),
            'open_alerts' => $this->repo->openAlerts(),
            'resolved_alerts' => $this->repo->resolvedAlerts(),
            'status_audit_logs' => $statusAuditLogs,
            'audit_table_available' => $auditTableAvailable,
            'user' => Auth::user(),
            'flash' => $this->getFlash(),
            'last_created_case' => null,
            'active_tab' => 'profile',
        ]);
    }

    public function createAmbulance(): void
    {
        Auth::requireAdmin();
        $user = Auth::user();

        $result = $this->repo->createAmbulance(
            [
                'code' => trim($_POST['code'] ?? ''),
                'plate_no' => trim($_POST['plate_no'] ?? ''),
                'hospital' => trim($_POST['hospital'] ?? ''),
                'driver_name' => trim($_POST['driver_name'] ?? ''),
                'status' => $_POST['status'] ?? 'standby',
                'location' => trim($_POST['location'] ?? ''),
            ],
            (int)$user['id'],
            $user['name'],
            $user['role']
        );

        if (!$result['success']) {
            $this->setFlash('errors', $result['errors']);
        } else {
            $this->setFlash('success', ['救护车档案 ' . $result['code'] . ' 创建成功']);
        }

        header('Location: /admin/ambulances/profile');
    }

    public function updateAmbulanceProfileAction(): void
    {
        Auth::requireAdmin();
        $user = Auth::user();

        $id = (int)($_POST['id'] ?? 0);
        $result = $this->repo->updateAmbulanceProfile(
            $id,
            [
                'code' => trim($_POST['code'] ?? ''),
                'plate_no' => trim($_POST['plate_no'] ?? ''),
                'hospital' => trim($_POST['hospital'] ?? ''),
                'driver_name' => trim($_POST['driver_name'] ?? ''),
                'status' => $_POST['status'] ?? 'standby',
                'location' => trim($_POST['location'] ?? ''),
            ],
            (int)$user['id'],
            $user['name'],
            $user['role']
        );

        if (!$result['success']) {
            $this->setFlash('errors', $result['errors']);
        } else {
            $messages = ['救护车档案更新成功'];
            if (!empty($result['warnings'])) {
                $this->setFlash('warnings', $result['warnings']);
            }
            $this->setFlash('success', $messages);
        }

        header('Location: /admin/ambulances/profile');
    }

    public function deleteAmbulance(): void
    {
        Auth::requireAdmin();
        $user = Auth::user();

        $id = (int)($_POST['id'] ?? 0);
        $result = $this->repo->deleteAmbulance(
            $id,
            (int)$user['id'],
            $user['name'],
            $user['role']
        );

        if (!$result['success']) {
            $this->setFlash('errors', $result['errors']);
        } else {
            $this->setFlash('success', ['救护车 ' . $result['code'] . ' 已删除']);
        }

        header('Location: /admin/ambulances/profile');
    }

    public function transitionCaseStatus(): void
    {
        Auth::requireAdmin();
        $user = Auth::user();

        $caseNo = trim($_POST['case_no'] ?? '');
        $newStatus = $_POST['new_status'] ?? '';
        $notes = trim($_POST['transition_notes'] ?? '');
        $redirectTo = trim($_POST['redirect_to'] ?? '');

        $errors = [];
        if ($caseNo === '') {
            $errors[] = '无效的事件编号';
        }
        if ($newStatus === '') {
            $errors[] = '请指定目标状态';
        }

        if (!empty($errors)) {
            $this->setFlash('errors', $errors);
            $redirect = $redirectTo !== '' ? $redirectTo : '/admin';
            header('Location: ' . $redirect);
            return;
        }

        $result = $this->repo->updateCaseStatus(
            $caseNo,
            $newStatus,
            (int)$user['id'],
            $user['name'],
            $user['role'],
            $notes === '' ? null : $notes
        );

        if (!$result['success']) {
            $this->setFlash('errors', $result['errors']);
        } else {
            $this->setFlash('success', [$result['message']]);
            if (!empty($result['warnings'])) {
                $this->setFlash('warnings', $result['warnings']);
            }
            $_SESSION['_last_status_changed_case'] = $caseNo;
            $_SESSION['_last_status_change_info'] = [
                'old' => $result['old_status'],
                'new' => $result['new_status'],
            ];
        }

        if ($redirectTo !== '') {
            header('Location: ' . $redirectTo);
        } else {
            header('Location: /admin');
        }
    }

    public function caseStatusTransitionsApi(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        Auth::requireAdmin();

        try {
            $caseNo = trim($_GET['case_no'] ?? '');
            if ($caseNo === '') {
                echo json_encode([
                    'success' => false,
                    'errors' => ['请指定事件编号'],
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $case = $this->repo->findCaseByCaseNo($caseNo);
            if (!$case) {
                echo json_encode([
                    'success' => false,
                    'errors' => ['事件不存在'],
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $actions = $this->repo->getNextStatusActions($case['status']);

            echo json_encode([
                'success' => true,
                'case_no' => $caseNo,
                'current_status' => $case['status'],
                'current_label' => statusText($case['status']),
                'actions' => $actions,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('caseStatusTransitionsApi 异常: ' . $e->getMessage());
            ErrorResponse::databaseError('获取状态流转信息失败，请稍后重试');
        }
    }

    private function setFlash(string $type, array $messages): void
    {
        $_SESSION['_flash'][$type] = $messages;
    }

    private function getFlash(): array
    {
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flash;
    }
}
