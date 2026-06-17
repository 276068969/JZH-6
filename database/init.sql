SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(80) NOT NULL,
    role VARCHAR(30) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'enabled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ambulances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    plate_no VARCHAR(30) NOT NULL,
    hospital VARCHAR(120) NOT NULL,
    driver_name VARCHAR(50) NOT NULL,
    status VARCHAR(30) NOT NULL,
    location VARCHAR(160) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS emergency_cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_no VARCHAR(40) NOT NULL UNIQUE,
    patient_name VARCHAR(80) NOT NULL,
    priority VARCHAR(20) NOT NULL,
    address VARCHAR(180) NOT NULL,
    status VARCHAR(30) NOT NULL,
    assigned_ambulance VARCHAR(30) NULL,
    dispatch_vehicle_status VARCHAR(30) NULL,
    dispatched_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(120) NOT NULL,
    description VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    handling_notes TEXT NULL,
    handled_by INT NULL,
    handled_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO users (username, password_hash, name, role, status) VALUES
('admin', '240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9', '平台管理员', 'admin', 'enabled'),
('dispatcher', '5fe2e402b51c5793b3c81c9758f698bb649c3fcbc1a6b8a11f8bd851a3245957', '急救调度员', 'dispatcher', 'enabled');

INSERT INTO ambulances (code, plate_no, hospital, driver_name, status, location) VALUES
('AMB-001', '沪A·12001', '第一人民医院', '张师傅', 'dispatching', '人民大道与康复路交叉口'),
('AMB-002', '沪A·12002', '中心医院', '李师傅', 'standby', '中心医院急诊楼'),
('AMB-003', '沪A·12003', '妇幼保健院', '王师傅', 'transporting', '高架南线入口'),
('AMB-004', '沪A·12004', '第三人民医院', '赵师傅', 'on_scene', '滨江社区卫生服务站'),
('AMB-005', '沪A·12005', '中医医院', '陈师傅', 'maintenance', '车辆保障中心');

INSERT INTO emergency_cases (case_no, patient_name, priority, address, status, assigned_ambulance, dispatch_vehicle_status, dispatched_at, created_at) VALUES
('CASE202606110001', '刘先生', 'high', '人民大道 188 号', 'accepted', 'AMB-001', 'dispatching', DATE_SUB(NOW(), INTERVAL 15 MINUTE), NOW()),
('CASE202606110002', '周女士', 'medium', '滨江花园 6 栋', 'reported', 'AMB-004', 'standby', DATE_SUB(NOW(), INTERVAL 5 MINUTE), NOW()),
('CASE202606110003', '赵先生', 'low', '中心医院门诊大厅', 'closed', 'AMB-002', 'dispatching', DATE_SUB(NOW(), INTERVAL 2 HOUR), NOW());

INSERT INTO alerts (title, description, status) VALUES
('响应时间超阈值', 'AMB-001 到达现场预计超过监管阈值，请调度复核路线。', 'open'),
('设备离线', 'AMB-005 心电监护设备超过 10 分钟未上报数据。', 'open'),
('轨迹偏离', 'AMB-003 当前轨迹偏离推荐转运路线。', 'resolved');

CREATE TABLE IF NOT EXISTS ambulance_status_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ambulance_id INT NOT NULL,
    ambulance_code VARCHAR(30) NOT NULL,
    old_status VARCHAR(30) NOT NULL,
    new_status VARCHAR(30) NOT NULL,
    old_location VARCHAR(160) NULL,
    new_location VARCHAR(160) NULL,
    changed_by INT NOT NULL,
    operator_name VARCHAR(80) NOT NULL,
    operator_role VARCHAR(30) NOT NULL,
    change_type VARCHAR(20) NOT NULL DEFAULT 'manual',
    related_case_no VARCHAR(40) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ambulance_id (ambulance_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (ambulance_id) REFERENCES ambulances(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id)
);
