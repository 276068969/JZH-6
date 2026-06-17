<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class DashboardRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function overview(): array
    {
        return [
            'ambulances_total' => (int) $this->db->query('SELECT COUNT(*) FROM ambulances')->fetchColumn(),
            'ambulances_online' => (int) $this->db->query('SELECT COUNT(*) FROM ambulances WHERE status IN ("standby", "dispatching", "on_scene", "transporting")')->fetchColumn(),
            'active_cases' => (int) $this->db->query('SELECT COUNT(*) FROM emergency_cases WHERE status != "closed"')->fetchColumn(),
            'alerts' => (int) $this->db->query('SELECT COUNT(*) FROM alerts WHERE status = "open"')->fetchColumn(),
        ];
    }

    public function ambulances(): array
    {
        return $this->db->query('SELECT * FROM ambulances ORDER BY FIELD(status, "dispatching", "on_scene", "transporting", "standby", "maintenance"), code')->fetchAll();
    }

    public function cases(): array
    {
        return $this->db->query('SELECT * FROM emergency_cases ORDER BY created_at DESC LIMIT 12')->fetchAll();
    }

    public function alerts(): array
    {
        $sql = 'SELECT a.*, u.name as handler_name 
                FROM alerts a 
                LEFT JOIN users u ON a.handled_by = u.id 
                ORDER BY a.created_at DESC LIMIT 12';
        return $this->db->query($sql)->fetchAll();
    }

    public function openAlerts(): array
    {
        $sql = 'SELECT a.*, u.name as handler_name 
                FROM alerts a 
                LEFT JOIN users u ON a.handled_by = u.id 
                WHERE a.status = "open" 
                ORDER BY a.created_at DESC';
        return $this->db->query($sql)->fetchAll();
    }

    public function resolvedAlerts(): array
    {
        $sql = 'SELECT a.*, u.name as handler_name 
                FROM alerts a 
                LEFT JOIN users u ON a.handled_by = u.id 
                WHERE a.status = "resolved" 
                ORDER BY a.handled_at DESC LIMIT 10';
        return $this->db->query($sql)->fetchAll();
    }

    public function handleAlert(int $id, string $handlingNotes, int $handledBy): array
    {
        $stmt = $this->db->prepare('SELECT * FROM alerts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $alert = $stmt->fetch();

        if (!$alert) {
            return ['success' => false, 'errors' => ['告警不存在']];
        }

        if ($alert['status'] === 'resolved') {
            return ['success' => false, 'errors' => ['该告警已处理，无需重复处置']];
        }

        $notes = trim($handlingNotes);
        if ($notes === '') {
            return ['success' => false, 'errors' => ['请填写处置说明']];
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'UPDATE alerts 
                 SET status = "resolved", 
                     handling_notes = :notes, 
                     handled_by = :handled_by, 
                     handled_at = NOW() 
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'notes' => $notes,
                'handled_by' => $handledBy,
            ]);

            $this->db->commit();
            return [
                'success' => true,
                'alert' => $alert,
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return ['success' => false, 'errors' => ['处置失败：' . $e->getMessage()]];
        }
    }

    private function generateCaseNo(): string
    {
        $prefix = 'CASE' . date('Ymd');

        $stmt = $this->db->prepare(
            'SELECT MAX(case_no) FROM emergency_cases WHERE case_no LIKE :prefix FOR UPDATE'
        );
        $stmt->execute(['prefix' => $prefix . '%']);
        $maxCaseNo = $stmt->fetchColumn();

        if ($maxCaseNo && str_starts_with($maxCaseNo, $prefix)) {
            $seq = (int) substr($maxCaseNo, strlen($prefix)) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function createCase(array $data): void
    {
        $this->db->beginTransaction();
        try {
            $caseNo = $this->generateCaseNo();
            $stmt = $this->db->prepare(
                'INSERT INTO emergency_cases (case_no, patient_name, priority, address, status, assigned_ambulance, created_at)
                 VALUES (:case_no, :patient_name, :priority, :address, :status, :assigned_ambulance, NOW())'
            );
            $stmt->execute([
                'case_no' => $caseNo,
                'patient_name' => $data['patient_name'],
                'priority' => $data['priority'],
                'address' => $data['address'],
                'status' => $data['status'],
                'assigned_ambulance' => $data['assigned_ambulance'] ?: null,
            ]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateAmbulance(int $id, string $status, string $location): void
    {
        $stmt = $this->db->prepare('UPDATE ambulances SET status = :status, location = :location, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'status' => $status,
            'location' => $location,
        ]);
    }

    public function ambulancesWithDispatchInfo(): array
    {
        $sql = 'SELECT a.*, 
                       (SELECT ec.case_no FROM emergency_cases ec 
                        WHERE ec.assigned_ambulance = a.code AND ec.status != "closed" 
                        ORDER BY ec.created_at DESC LIMIT 1) as active_case_no,
                       (SELECT ec.status FROM emergency_cases ec 
                        WHERE ec.assigned_ambulance = a.code AND ec.status != "closed" 
                        ORDER BY ec.created_at DESC LIMIT 1) as active_case_status
                FROM ambulances a 
                ORDER BY FIELD(a.status, "dispatching", "on_scene", "transporting", "standby", "maintenance"), a.code';
        return $this->db->query($sql)->fetchAll();
    }

    public function casesWithAmbulanceInfo(): array
    {
        $sql = 'SELECT ec.*, 
                       a.hospital as ambulance_hospital, 
                       a.status as ambulance_status,
                       ec.dispatch_vehicle_status as dispatch_vehicle_status,
                       ec.dispatched_at as dispatched_at
                FROM emergency_cases ec 
                LEFT JOIN ambulances a ON ec.assigned_ambulance = a.code
                ORDER BY ec.created_at DESC LIMIT 12';
        return $this->db->query($sql)->fetchAll();
    }

    public function isAmbulanceAvailable(string $code): array
    {
        $stmt = $this->db->prepare('SELECT * FROM ambulances WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $ambulance = $stmt->fetch();

        if (!$ambulance) {
            return ['available' => false, 'reason' => '车辆编号不存在'];
        }

        $unavailableStatuses = ['maintenance'];
        if (in_array($ambulance['status'], $unavailableStatuses, true)) {
            return ['available' => false, 'reason' => '车辆状态：' . statusText($ambulance['status']) . '，不可派车'];
        }

        $stmt = $this->db->prepare('SELECT ec.* FROM emergency_cases ec 
                                     WHERE ec.assigned_ambulance = :code AND ec.status != "closed" 
                                     ORDER BY ec.created_at DESC LIMIT 1');
        $stmt->execute(['code' => $code]);
        $activeCase = $stmt->fetch();

        if ($activeCase) {
            return [
                'available' => false,
                'reason' => '车辆已被事件 ' . $activeCase['case_no'] . ' 占用（' . statusText($activeCase['status']) . '）',
                'active_case' => $activeCase,
            ];
        }

        if ($ambulance['status'] !== 'standby') {
            return [
                'available' => true,
                'warning' => '车辆当前状态：' . statusText($ambulance['status']) . '，派车后状态将更新为出车',
                'ambulance' => $ambulance,
            ];
        }

        return ['available' => true, 'ambulance' => $ambulance];
    }

    public function createCaseWithDispatch(array $data): array
    {
        $warnings = [];
        $errors = [];
        $dispatchSnapshot = null;
        $dispatchInfo = [];

        if (!empty($data['assigned_ambulance'])) {
            $check = $this->isAmbulanceAvailable($data['assigned_ambulance']);
            if (!$check['available']) {
                $errors[] = $check['reason'];
            }
            if (isset($check['warning'])) {
                $warnings[] = $check['warning'];
            }
            if (isset($check['ambulance'])) {
                $dispatchSnapshot = $check['ambulance']['status'];
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $matrix = dispatchStatusMatrix();
        $rule = $matrix[$data['status']] ?? null;
        $targetVehicleStatus = $rule ? $rule['ideal'] : 'dispatching';

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO emergency_cases 
                 (case_no, patient_name, priority, address, status, assigned_ambulance, 
                  dispatch_vehicle_status, dispatched_at, created_at)
                 VALUES 
                 (:case_no, :patient_name, :priority, :address, :status, :assigned_ambulance,
                  :dispatch_vehicle_status, :dispatched_at, NOW())'
            );
            $caseNo = $this->generateCaseNo();
            $hasAmbulance = !empty($data['assigned_ambulance']);
            $stmt->execute([
                'case_no' => $caseNo,
                'patient_name' => $data['patient_name'],
                'priority' => $data['priority'],
                'address' => $data['address'],
                'status' => $data['status'],
                'assigned_ambulance' => $hasAmbulance ? $data['assigned_ambulance'] : null,
                'dispatch_vehicle_status' => $hasAmbulance ? $dispatchSnapshot : null,
                'dispatched_at' => $hasAmbulance ? date('Y-m-d H:i:s') : null,
            ]);

            if ($hasAmbulance && $data['status'] !== 'closed') {
                $stmt = $this->db->prepare('UPDATE ambulances SET status = :target_status, updated_at = NOW() WHERE code = :code');
                $stmt->execute([
                    'code' => $data['assigned_ambulance'],
                    'target_status' => $targetVehicleStatus,
                ]);

                $dispatchInfo = [
                    'vehicle_code' => $data['assigned_ambulance'],
                    'snapshot_status' => $dispatchSnapshot,
                    'target_status' => $targetVehicleStatus,
                    'case_status' => $data['status'],
                ];

                if ($rule) {
                    if (in_array($targetVehicleStatus, $rule['expected'], true)) {
                        $warnings[] = '派车成功：按规则「' . $rule['description'] . '」，车辆状态已自动设为「' . statusText($targetVehicleStatus) . '」';
                    } else {
                        $warnings[] = '派车提示：按规则「' . $rule['description'] . '」，车辆理想状态为「' . statusText($rule['ideal']) . '」，当前已设为「' . statusText($targetVehicleStatus) . '」';
                    }
                }
            }

            $this->db->commit();
            return [
                'success' => true,
                'case_no' => $caseNo,
                'warnings' => $warnings,
                'dispatch_info' => $dispatchInfo,
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return ['success' => false, 'errors' => ['保存失败：' . $e->getMessage()]];
        }
    }

    public function updateAmbulanceWithLinkage(int $id, string $status, string $location): array
    {
        $warnings = [];

        $stmt = $this->db->prepare('SELECT * FROM ambulances WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $ambulance = $stmt->fetch();
        if (!$ambulance) {
            return ['success' => false, 'errors' => ['车辆不存在']];
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('UPDATE ambulances SET status = :status, location = :location, updated_at = NOW() WHERE id = :id');
            $stmt->execute([
                'id' => $id,
                'status' => $status,
                'location' => $location,
            ]);

            if ($status === 'standby') {
                $stmt = $this->db->prepare('SELECT * FROM emergency_cases 
                                             WHERE assigned_ambulance = :code AND status != "closed" 
                                             ORDER BY created_at DESC LIMIT 1');
                $stmt->execute(['code' => $ambulance['code']]);
                $activeCase = $stmt->fetch();
                if ($activeCase) {
                    $warnings[] = '提示：车辆设为待命，但事件 ' . $activeCase['case_no'] . ' 尚未关闭，请确认是否需要结案。';
                }
            }

            if ($status === 'maintenance') {
                $stmt = $this->db->prepare('SELECT * FROM emergency_cases 
                                             WHERE assigned_ambulance = :code AND status != "closed" 
                                             ORDER BY created_at DESC LIMIT 1');
                $stmt->execute(['code' => $ambulance['code']]);
                $activeCase = $stmt->fetch();
                if ($activeCase) {
                    $warnings[] = '警告：车辆设为检修，但仍有关联进行中事件 ' . $activeCase['case_no'] . '，请重新调度。';
                }
            }

            $this->db->commit();
            return ['success' => true, 'warnings' => $warnings];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return ['success' => false, 'errors' => ['更新失败：' . $e->getMessage()]];
        }
    }

    public function ambulanceDispatchCheckApi(string $code): array
    {
        return $this->isAmbulanceAvailable($code);
    }
}
