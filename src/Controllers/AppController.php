<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
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
        
        View::render('admin', [
            'overview' => $this->repo->overview(),
            'ambulances' => $this->repo->ambulancesWithDispatchInfo(),
            'cases' => $this->repo->casesWithAmbulanceInfo(),
            'alerts' => $this->repo->alerts(),
            'open_alerts' => $this->repo->openAlerts(),
            'resolved_alerts' => $this->repo->resolvedAlerts(),
            'status_audit_logs' => $statusAuditLogs,
            'audit_table_available' => $auditTableAvailable,
            'user' => Auth::user(),
            'flash' => $this->getFlash(),
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
        $result = $this->repo->createCaseWithDispatch(
            [
                'patient_name' => trim($_POST['patient_name'] ?? '待确认'),
                'priority' => $_POST['priority'] ?? 'medium',
                'address' => trim($_POST['address'] ?? ''),
                'status' => $_POST['status'] ?? 'reported',
                'assigned_ambulance' => trim($_POST['assigned_ambulance'] ?? ''),
            ],
            (int)$user['id'],
            $user['name'],
            $user['role']
        );

        if (!$result['success']) {
            $this->setFlash('errors', $result['errors']);
        } else {
            $messages = ['事件 ' . $result['case_no'] . ' 创建成功'];
            if (!empty($result['warnings'])) {
                $this->setFlash('warnings', $result['warnings']);
            }
            $this->setFlash('success', $messages);
        }

        header('Location: /admin');
    }

    public function updateAmbulance(): void
    {
        Auth::requireAdmin();
        $user = Auth::user();
        $result = $this->repo->updateAmbulanceWithLinkage(
            (int) ($_POST['id'] ?? 0),
            $_POST['status'] ?? 'standby',
            trim($_POST['location'] ?? ''),
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
        $overview = $this->repo->overview();
        echo json_encode([
            'overview' => $overview,
            'ambulances' => $this->repo->ambulances(),
            'cases' => $this->repo->cases(),
            'alerts' => $this->repo->alerts(),
            'aggregations' => [
                'ambulance_by_status' => $overview['ambulance_status_breakdown'],
                'case_by_priority' => $overview['case_priority_breakdown'],
                'alert_by_status' => $overview['alert_status_breakdown'],
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    public function dispatchCheckApi(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $code = trim($_GET['code'] ?? '');
        if ($code === '') {
            echo json_encode(['available' => false, 'reason' => '请输入车辆编号'], JSON_UNESCAPED_UNICODE);
            return;
        }
        echo json_encode(
            $this->repo->ambulanceDispatchCheckApi($code),
            JSON_UNESCAPED_UNICODE
        );
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
        try {
            $auditTableAvailable = $this->repo->isAuditTableAvailable();
            if ($auditTableAvailable && $hasAmbulance) {
                $statusAuditLogs = $this->repo->recentStatusAuditLogsForCase($caseNo, 10);
            }
        } catch (\Throwable $e) {
            error_log('加载审计日志失败: ' . $e->getMessage());
        }

        View::render('case_detail', [
            'case' => $case,
            'match' => $match,
            'has_ambulance' => $hasAmbulance,
            'status_audit_logs' => $statusAuditLogs,
            'audit_table_available' => $auditTableAvailable,
            'user' => Auth::user(),
            'flash' => $this->getFlash(),
        ]);
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
