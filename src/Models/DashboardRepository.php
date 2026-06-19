<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class DashboardRepository
{
    private ?PDO $db = null;

    private function db(): PDO
    {
        if ($this->db === null) {
            $this->db = Database::connection();
        }
        return $this->db;
    }

    public function overview(): array
    {
        return [
            'ambulances_total' => (int) $this->db()->query('SELECT COUNT(*) FROM ambulances')->fetchColumn(),
            'ambulances_online' => (int) $this->db()->query('SELECT COUNT(*) FROM ambulances WHERE status IN ("standby", "dispatching", "on_scene", "transporting")')->fetchColumn(),
            'active_cases' => (int) $this->db()->query('SELECT COUNT(*) FROM emergency_cases WHERE status != "closed"')->fetchColumn(),
            'alerts' => (int) $this->db()->query('SELECT COUNT(*) FROM alerts WHERE status = "open"')->fetchColumn(),
            'ambulance_status_breakdown' => $this->ambulanceStatusBreakdown(),
            'case_priority_breakdown' => $this->casePriorityBreakdown(),
            'alert_status_breakdown' => $this->alertStatusBreakdown(),
        ];
    }

    public function ambulanceStatusBreakdown(): array
    {
        $sql = 'SELECT status, COUNT(*) as count FROM ambulances GROUP BY status';
        $rows = $this->db()->query($sql)->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $result[$row['status']] = (int) $row['count'];
        }
        $allStatuses = ['standby', 'dispatching', 'on_scene', 'transporting', 'maintenance'];
        foreach ($allStatuses as $status) {
            if (!isset($result[$status])) {
                $result[$status] = 0;
            }
        }
        return $result;
    }

    public function casePriorityBreakdown(): array
    {
        $sql = 'SELECT priority, COUNT(*) as count FROM emergency_cases GROUP BY priority';
        $rows = $this->db()->query($sql)->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $result[$row['priority']] = (int) $row['count'];
        }
        $allPriorities = ['high', 'medium', 'low'];
        foreach ($allPriorities as $priority) {
            if (!isset($result[$priority])) {
                $result[$priority] = 0;
            }
        }
        return $result;
    }

    public function alertStatusBreakdown(): array
    {
        $sql = 'SELECT status, COUNT(*) as count FROM alerts GROUP BY status';
        $rows = $this->db()->query($sql)->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $result[$row['status']] = (int) $row['count'];
        }
        $allStatuses = ['open', 'resolved'];
        foreach ($allStatuses as $status) {
            if (!isset($result[$status])) {
                $result[$status] = 0;
            }
        }
        return $result;
    }

    public function ambulances(): array
    {
        return $this->db()->query('SELECT * FROM ambulances ORDER BY FIELD(status, "dispatching", "on_scene", "transporting", "standby", "maintenance"), code')->fetchAll();
    }

    public function cases(): array
    {
        return $this->db()->query('SELECT * FROM emergency_cases ORDER BY created_at DESC LIMIT 12')->fetchAll();
    }

    public function alerts(): array
    {
        $sql = 'SELECT a.*, u.name as handler_name 
                FROM alerts a 
                LEFT JOIN users u ON a.handled_by = u.id 
                ORDER BY a.created_at DESC LIMIT 12';
        return $this->db()->query($sql)->fetchAll();
    }

    public function openAlerts(): array
    {
        $sql = 'SELECT a.*, u.name as handler_name 
                FROM alerts a 
                LEFT JOIN users u ON a.handled_by = u.id 
                WHERE a.status = "open" 
                ORDER BY a.created_at DESC';
        return $this->db()->query($sql)->fetchAll();
    }

    public function resolvedAlerts(): array
    {
        $sql = 'SELECT a.*, u.name as handler_name 
                FROM alerts a 
                LEFT JOIN users u ON a.handled_by = u.id 
                WHERE a.status = "resolved" 
                ORDER BY a.handled_at DESC LIMIT 10';
        return $this->db()->query($sql)->fetchAll();
    }

    public function handleAlert(int $id, string $handlingNotes, int $handledBy): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM alerts WHERE id = :id LIMIT 1');
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

        $this->db()->beginTransaction();
        try {
            $stmt = $this->db()->prepare(
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

            $this->db()->commit();
            return [
                'success' => true,
                'alert' => $alert,
            ];
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            error_log('处置告警失败: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['告警处置失败，请稍后重试']];
        }
    }

    private function generateCaseNo(): string
    {
        $prefix = 'CASE' . date('Ymd');

        $stmt = $this->db()->prepare(
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
        $this->db()->beginTransaction();
        try {
            $caseNo = $this->generateCaseNo();
            $stmt = $this->db()->prepare(
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
            $this->db()->commit();
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            throw $e;
        }
    }

    public function updateAmbulance(int $id, string $status, string $location): void
    {
        $stmt = $this->db()->prepare('UPDATE ambulances SET status = :status, location = :location, updated_at = NOW() WHERE id = :id');
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
        return $this->db()->query($sql)->fetchAll();
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
        return $this->db()->query($sql)->fetchAll();
    }

    public function findCaseByCaseNo(string $caseNo): ?array
    {
        $sql = 'SELECT ec.*, 
                       a.hospital as ambulance_hospital, 
                       a.status as ambulance_status,
                       a.plate_no as ambulance_plate_no,
                       a.driver_name as ambulance_driver_name,
                       a.location as ambulance_location
                FROM emergency_cases ec 
                LEFT JOIN ambulances a ON ec.assigned_ambulance = a.code
                WHERE ec.case_no = :case_no 
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['case_no' => $caseNo]);
        $case = $stmt->fetch();
        return $case ?: null;
    }

    public function isAmbulanceAvailable(string $code): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM ambulances WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $ambulance = $stmt->fetch();

        if (!$ambulance) {
            return ['available' => false, 'reason' => '车辆编号不存在'];
        }

        $unavailableStatuses = ['maintenance'];
        if (in_array($ambulance['status'], $unavailableStatuses, true)) {
            return ['available' => false, 'reason' => '车辆状态：' . statusText($ambulance['status']) . '，不可派车'];
        }

        $stmt = $this->db()->prepare('SELECT ec.* FROM emergency_cases ec 
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

    public function ambulanceDispatchCheckApi(string $code): array
    {
        return $this->isAmbulanceAvailable($code);
    }

    private function isAuditTableExists(): bool
    {
        try {
            $stmt = $this->db()->query(
                "SELECT COUNT(*) FROM information_schema.tables 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'ambulance_status_audit'"
            );
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function logAmbulanceStatusChange(
        int $ambulanceId,
        string $ambulanceCode,
        string $oldStatus,
        string $newStatus,
        ?string $oldLocation,
        ?string $newLocation,
        int $changedBy,
        string $operatorName,
        string $operatorRole,
        string $changeType = 'manual',
        ?string $relatedCaseNo = null
    ): void {
        try {
            if (!$this->isAuditTableExists()) {
                return;
            }

            $stmt = $this->db()->prepare(
                'INSERT INTO ambulance_status_audit 
                 (ambulance_id, ambulance_code, old_status, new_status, 
                  old_location, new_location, changed_by, operator_name, 
                  operator_role, change_type, related_case_no, created_at)
                 VALUES 
                 (:ambulance_id, :ambulance_code, :old_status, :new_status,
                  :old_location, :new_location, :changed_by, :operator_name,
                  :operator_role, :change_type, :related_case_no, NOW())'
            );
            $stmt->execute([
                'ambulance_id' => $ambulanceId,
                'ambulance_code' => $ambulanceCode,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'old_location' => $oldLocation,
                'new_location' => $newLocation,
                'changed_by' => $changedBy,
                'operator_name' => $operatorName,
                'operator_role' => $operatorRole,
                'change_type' => $changeType,
                'related_case_no' => $relatedCaseNo,
            ]);
        } catch (\Throwable $e) {
            error_log('记录审计日志失败: ' . $e->getMessage());
        }
    }

    public function isAuditTableAvailable(): bool
    {
        return $this->isAuditTableExists();
    }

    public function recentStatusAuditLogs(int $limit = 20): array
    {
        try {
            if (!$this->isAuditTableExists()) {
                return [];
            }

            $sql = 'SELECT * FROM ambulance_status_audit 
                     ORDER BY created_at DESC 
                     LIMIT :limit';
            $stmt = $this->db()->prepare($sql);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('查询审计日志失败: ' . $e->getMessage());
            return [];
        }
    }

    public function recentStatusAuditLogsForCase(string $caseNo, int $limit = 10): array
    {
        try {
            if (!$this->isAuditTableExists()) {
                return [];
            }

            $sql = 'SELECT * FROM ambulance_status_audit 
                     WHERE related_case_no = :case_no 
                     ORDER BY created_at DESC 
                     LIMIT :limit';
            $stmt = $this->db()->prepare($sql);
            $stmt->bindValue('case_no', $caseNo);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('查询事件审计日志失败: ' . $e->getMessage());
            return [];
        }
    }

    public function validateAmbulanceStatusChange(string $oldStatus, string $newStatus, string $location): array
    {
        $errors = [];
        $validStatuses = ['standby', 'dispatching', 'on_scene', 'transporting', 'maintenance'];

        if (!in_array($newStatus, $validStatuses, true)) {
            $errors[] = '无效的车辆状态：' . $newStatus;
        }

        if ($oldStatus === 'maintenance' && in_array($newStatus, ['dispatching', 'on_scene', 'transporting'], true)) {
            $errors[] = '检修车辆不能直接更新为「' . statusText($newStatus) . '」状态，请先设为待命';
        }

        if ($newStatus === 'standby' && trim($location) === '') {
            $errors[] = '待命车辆必须填写当前位置';
        }

        return $errors;
    }

    public function updateAmbulanceWithLinkage(
        int $id,
        string $status,
        string $location,
        int $changedBy,
        string $operatorName,
        string $operatorRole
    ): array {
        $warnings = [];

        $stmt = $this->db()->prepare('SELECT * FROM ambulances WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $ambulance = $stmt->fetch();
        if (!$ambulance) {
            return ['success' => false, 'errors' => ['车辆不存在']];
        }

        $oldStatus = $ambulance['status'];
        $oldLocation = $ambulance['location'];

        $validationErrors = $this->validateAmbulanceStatusChange($oldStatus, $status, $location);
        if (!empty($validationErrors)) {
            return ['success' => false, 'errors' => $validationErrors];
        }

        $this->db()->beginTransaction();
        try {
            $stmt = $this->db()->prepare('UPDATE ambulances SET status = :status, location = :location, updated_at = NOW() WHERE id = :id');
            $stmt->execute([
                'id' => $id,
                'status' => $status,
                'location' => $location,
            ]);

            if ($oldStatus !== $status || $oldLocation !== $location) {
                $this->logAmbulanceStatusChange(
                    $id,
                    $ambulance['code'],
                    $oldStatus,
                    $status,
                    $oldLocation,
                    $location,
                    $changedBy,
                    $operatorName,
                    $operatorRole,
                    'manual'
                );
            }

            if ($status === 'standby') {
                $stmt = $this->db()->prepare('SELECT * FROM emergency_cases 
                                             WHERE assigned_ambulance = :code AND status != "closed" 
                                             ORDER BY created_at DESC LIMIT 1');
                $stmt->execute(['code' => $ambulance['code']]);
                $activeCase = $stmt->fetch();
                if ($activeCase) {
                    $warnings[] = '提示：车辆设为待命，但事件 ' . $activeCase['case_no'] . ' 尚未关闭，请确认是否需要结案。';
                }
            }

            if ($status === 'maintenance') {
                $stmt = $this->db()->prepare('SELECT * FROM emergency_cases 
                                             WHERE assigned_ambulance = :code AND status != "closed" 
                                             ORDER BY created_at DESC LIMIT 1');
                $stmt->execute(['code' => $ambulance['code']]);
                $activeCase = $stmt->fetch();
                if ($activeCase) {
                    $warnings[] = '警告：车辆设为检修，但仍有关联进行中事件 ' . $activeCase['case_no'] . '，请重新调度。';
                }
            }

            $this->db()->commit();
            return ['success' => true, 'warnings' => $warnings];
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            error_log('更新车辆失败: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['车辆更新失败，请稍后重试']];
        }
    }

    public function createCaseWithDispatch(array $data, int $changedBy, string $operatorName, string $operatorRole): array
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

        $this->db()->beginTransaction();
        try {
            $stmt = $this->db()->prepare(
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
                $stmt = $this->db()->prepare('SELECT * FROM ambulances WHERE code = :code LIMIT 1');
                $stmt->execute(['code' => $data['assigned_ambulance']]);
                $ambulance = $stmt->fetch();

                $stmt = $this->db()->prepare('UPDATE ambulances SET status = :target_status, updated_at = NOW() WHERE code = :code');
                $stmt->execute([
                    'code' => $data['assigned_ambulance'],
                    'target_status' => $targetVehicleStatus,
                ]);

                if ($ambulance && $ambulance['status'] !== $targetVehicleStatus) {
                    $this->logAmbulanceStatusChange(
                        (int)$ambulance['id'],
                        $ambulance['code'],
                        $ambulance['status'],
                        $targetVehicleStatus,
                        $ambulance['location'],
                        $ambulance['location'],
                        $changedBy,
                        $operatorName,
                        $operatorRole,
                        'dispatch',
                        $caseNo
                    );
                }

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

            $this->db()->commit();
            return [
                'success' => true,
                'case_no' => $caseNo,
                'warnings' => $warnings,
                'dispatch_info' => $dispatchInfo,
            ];
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            error_log('创建事件失败: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['事件保存失败，请稍后重试']];
        }
    }

    public function getAmbulanceById(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM ambulances WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $ambulance = $stmt->fetch();
        return $ambulance ?: null;
    }

    public function getAmbulanceByCode(string $code): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM ambulances WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $ambulance = $stmt->fetch();
        return $ambulance ?: null;
    }

    public function validateAmbulanceProfile(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        $code = trim($data['code'] ?? '');
        $plateNo = trim($data['plate_no'] ?? '');
        $hospital = trim($data['hospital'] ?? '');
        $driverName = trim($data['driver_name'] ?? '');
        $status = $data['status'] ?? '';
        $location = trim($data['location'] ?? '');

        if ($code === '') {
            $errors[] = '请填写车辆编号';
        } elseif (strlen($code) > 30) {
            $errors[] = '车辆编号不能超过 30 个字符';
        } else {
            $existing = $this->getAmbulanceByCode($code);
            if ($existing && (!$excludeId || (int)$existing['id'] !== $excludeId)) {
                $errors[] = '车辆编号已存在，请更换';
            }
        }

        if ($plateNo === '') {
            $errors[] = '请填写车牌号';
        } elseif (strlen($plateNo) > 30) {
            $errors[] = '车牌号不能超过 30 个字符';
        }

        if ($hospital === '') {
            $errors[] = '请填写所属医院';
        } elseif (strlen($hospital) > 120) {
            $errors[] = '所属医院名称不能超过 120 个字符';
        }

        if ($driverName === '') {
            $errors[] = '请填写驾驶员姓名';
        } elseif (strlen($driverName) > 50) {
            $errors[] = '驾驶员姓名不能超过 50 个字符';
        }

        $validStatuses = ['standby', 'dispatching', 'on_scene', 'transporting', 'maintenance'];
        if (!in_array($status, $validStatuses, true)) {
            $errors[] = '请选择有效的初始状态';
        }

        if ($status === 'standby' && $location === '') {
            $errors[] = '待命车辆必须填写当前位置';
        }

        return $errors;
    }

    public function createAmbulance(array $data, int $createdBy, string $operatorName, string $operatorRole): array
    {
        $errors = $this->validateAmbulanceProfile($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $this->db()->beginTransaction();
        try {
            $stmt = $this->db()->prepare(
                'INSERT INTO ambulances (code, plate_no, hospital, driver_name, status, location, updated_at)
                 VALUES (:code, :plate_no, :hospital, :driver_name, :status, :location, NOW())'
            );
            $stmt->execute([
                'code' => trim($data['code']),
                'plate_no' => trim($data['plate_no']),
                'hospital' => trim($data['hospital']),
                'driver_name' => trim($data['driver_name']),
                'status' => $data['status'],
                'location' => trim($data['location'] ?? ''),
            ]);

            $ambulanceId = (int)$this->db()->lastInsertId();

            $this->logAmbulanceStatusChange(
                $ambulanceId,
                trim($data['code']),
                '',
                $data['status'],
                null,
                trim($data['location'] ?? ''),
                $createdBy,
                $operatorName,
                $operatorRole,
                'create'
            );

            $this->db()->commit();
            return [
                'success' => true,
                'id' => $ambulanceId,
                'code' => trim($data['code']),
            ];
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            error_log('创建救护车档案失败: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['救护车档案创建失败，请稍后重试']];
        }
    }

    public function updateAmbulanceProfile(int $id, array $data, int $updatedBy, string $operatorName, string $operatorRole): array
    {
        $ambulance = $this->getAmbulanceById($id);
        if (!$ambulance) {
            return ['success' => false, 'errors' => ['车辆不存在']];
        }

        $errors = $this->validateAmbulanceProfile($data, $id);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $oldStatus = $ambulance['status'];
        $oldLocation = $ambulance['location'];
        $newStatus = $data['status'];
        $newLocation = trim($data['location'] ?? '');

        $statusChanged = $oldStatus !== $newStatus;
        $locationChanged = $oldLocation !== $newLocation;

        if (!$statusChanged && !$locationChanged
            && $ambulance['code'] === trim($data['code'])
            && $ambulance['plate_no'] === trim($data['plate_no'])
            && $ambulance['hospital'] === trim($data['hospital'])
            && $ambulance['driver_name'] === trim($data['driver_name'])
        ) {
            return ['success' => true, 'warnings' => ['未检测到变更']];
        }

        $this->db()->beginTransaction();
        try {
            $stmt = $this->db()->prepare(
                'UPDATE ambulances 
                 SET code = :code, 
                     plate_no = :plate_no, 
                     hospital = :hospital, 
                     driver_name = :driver_name,
                     status = :status, 
                     location = :location,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'code' => trim($data['code']),
                'plate_no' => trim($data['plate_no']),
                'hospital' => trim($data['hospital']),
                'driver_name' => trim($data['driver_name']),
                'status' => $newStatus,
                'location' => $newLocation,
            ]);

            if ($statusChanged || $locationChanged) {
                $this->logAmbulanceStatusChange(
                    $id,
                    trim($data['code']),
                    $oldStatus,
                    $newStatus,
                    $oldLocation,
                    $newLocation,
                    $updatedBy,
                    $operatorName,
                    $operatorRole,
                    'profile'
                );
            }

            $warnings = [];
            if ($newStatus === 'standby') {
                $stmt = $this->db()->prepare('SELECT * FROM emergency_cases 
                                             WHERE assigned_ambulance = :code AND status != "closed" 
                                             ORDER BY created_at DESC LIMIT 1');
                $stmt->execute(['code' => trim($data['code'])]);
                $activeCase = $stmt->fetch();
                if ($activeCase) {
                    $warnings[] = '提示：车辆设为待命，但事件 ' . $activeCase['case_no'] . ' 尚未关闭，请确认是否需要结案。';
                }
            }

            if ($newStatus === 'maintenance') {
                $stmt = $this->db()->prepare('SELECT * FROM emergency_cases 
                                             WHERE assigned_ambulance = :code AND status != "closed" 
                                             ORDER BY created_at DESC LIMIT 1');
                $stmt->execute(['code' => trim($data['code'])]);
                $activeCase = $stmt->fetch();
                if ($activeCase) {
                    $warnings[] = '警告：车辆设为检修，但仍有关联进行中事件 ' . $activeCase['case_no'] . '，请重新调度。';
                }
            }

            $this->db()->commit();
            return [
                'success' => true,
                'warnings' => $warnings,
            ];
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            error_log('更新救护车档案失败: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['救护车档案更新失败，请稍后重试']];
        }
    }

    public function deleteAmbulance(int $id, int $deletedBy, string $operatorName, string $operatorRole): array
    {
        $ambulance = $this->getAmbulanceById($id);
        if (!$ambulance) {
            return ['success' => false, 'errors' => ['车辆不存在']];
        }

        $stmt = $this->db()->prepare('SELECT COUNT(*) FROM emergency_cases WHERE assigned_ambulance = :code AND status != "closed"');
        $stmt->execute(['code' => $ambulance['code']]);
        $activeCaseCount = (int)$stmt->fetchColumn();
        if ($activeCaseCount > 0) {
            return ['success' => false, 'errors' => ['该车辆有关联进行中的事件，无法删除']];
        }

        $this->db()->beginTransaction();
        try {
            $stmt = $this->db()->prepare('DELETE FROM ambulances WHERE id = :id');
            $stmt->execute(['id' => $id]);

            $this->db()->commit();
            return ['success' => true, 'code' => $ambulance['code']];
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            error_log('删除救护车档案失败: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['救护车档案删除失败，请稍后重试']];
        }
    }

    public function allAmbulancesForProfile(): array
    {
        return $this->db()->query('SELECT * FROM ambulances ORDER BY code')->fetchAll();
    }

    public function casePriorityStatistics(): array
    {
        $total = (int) $this->db()->query('SELECT COUNT(*) FROM emergency_cases')->fetchColumn();
        $openTotal = (int) $this->db()->query('SELECT COUNT(*) FROM emergency_cases WHERE status != "closed"')->fetchColumn();

        $sql = 'SELECT priority, COUNT(*) as count FROM emergency_cases GROUP BY priority';
        $rows = $this->db()->query($sql)->fetchAll();
        $byPriority = [];
        foreach ($rows as $row) {
            $byPriority[$row['priority']] = (int) $row['count'];
        }

        $sqlOpen = 'SELECT priority, COUNT(*) as count FROM emergency_cases WHERE status != "closed" GROUP BY priority';
        $rowsOpen = $this->db()->query($sqlOpen)->fetchAll();
        $openByPriority = [];
        foreach ($rowsOpen as $row) {
            $openByPriority[$row['priority']] = (int) $row['count'];
        }

        $allPriorities = ['high', 'medium', 'low'];
        foreach ($allPriorities as $priority) {
            if (!isset($byPriority[$priority])) {
                $byPriority[$priority] = 0;
            }
            if (!isset($openByPriority[$priority])) {
                $openByPriority[$priority] = 0;
            }
        }

        $openRatio = $total > 0 ? round(($openTotal / $total) * 100, 2) : 0.0;

        $openRatioByPriority = [];
        foreach ($allPriorities as $priority) {
            $openRatioByPriority[$priority] = $byPriority[$priority] > 0
                ? round(($openByPriority[$priority] / $byPriority[$priority]) * 100, 2)
                : 0.0;
        }

        return [
            'total' => $total,
            'open_total' => $openTotal,
            'closed_total' => $total - $openTotal,
            'open_ratio' => $openRatio,
            'by_priority' => [
                'high' => $byPriority['high'],
                'medium' => $byPriority['medium'],
                'low' => $byPriority['low'],
            ],
            'open_by_priority' => [
                'high' => $openByPriority['high'],
                'medium' => $openByPriority['medium'],
                'low' => $openByPriority['low'],
            ],
            'open_ratio_by_priority' => [
                'high' => $openRatioByPriority['high'],
                'medium' => $openRatioByPriority['medium'],
                'low' => $openRatioByPriority['low'],
            ],
        ];
    }
}
