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
            'ambulances' => $this->repo->ambulances(),
            'cases' => $this->repo->cases(),
            'alerts' => $this->repo->alerts(),
            'user' => Auth::user(),
        ]);
    }

    public function loginForm(string $error = ''): void
    {
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
        Auth::requireLogin();
        View::render('admin', [
            'overview' => $this->repo->overview(),
            'ambulances' => $this->repo->ambulancesWithDispatchInfo(),
            'cases' => $this->repo->casesWithAmbulanceInfo(),
            'alerts' => $this->repo->alerts(),
            'user' => Auth::user(),
            'flash' => $this->getFlash(),
        ]);
    }

    public function createCase(): void
    {
        Auth::requireLogin();
        $result = $this->repo->createCaseWithDispatch([
            'patient_name' => trim($_POST['patient_name'] ?? '待确认'),
            'priority' => $_POST['priority'] ?? 'medium',
            'address' => trim($_POST['address'] ?? ''),
            'status' => $_POST['status'] ?? 'reported',
            'assigned_ambulance' => trim($_POST['assigned_ambulance'] ?? ''),
        ]);

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
        Auth::requireLogin();
        $result = $this->repo->updateAmbulanceWithLinkage(
            (int) ($_POST['id'] ?? 0),
            $_POST['status'] ?? 'standby',
            trim($_POST['location'] ?? '')
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
        echo json_encode([
            'overview' => $this->repo->overview(),
            'ambulances' => $this->repo->ambulances(),
            'cases' => $this->repo->cases(),
            'alerts' => $this->repo->alerts(),
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
