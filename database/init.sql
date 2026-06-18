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
('dispatcher', '5fe2e402b51c5793b3c81c9758f698bb649c3fcbc1a6b8a11f8bd851a3245957', '急救调度员', 'dispatcher', 'enabled'),
('supervisor', '15e2b0d3c33891ebb0f1ef609ec419420c20e320ce94c65fbc8c3312448eb225', '监管中心主任', 'supervisor', 'enabled'),
('hospital_admin', '8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92', '第一人民医院管理员', 'hospital_admin', 'enabled'),
('doctor_zhang', '6b3a55e0261b0304143f805a24924d0c1c44524821305f316a8a2a39e7f6a7e1', '张医生', 'doctor', 'enabled'),
('auditor', '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8', '审计专员', 'auditor', 'disabled');

INSERT INTO ambulances (code, plate_no, hospital, driver_name, status, location) VALUES
('AMB-001', '沪A·12001', '第一人民医院', '张师傅', 'dispatching', '人民大道与康复路交叉口'),
('AMB-002', '沪A·12002', '中心医院', '李师傅', 'standby', '中心医院急诊楼'),
('AMB-003', '沪A·12003', '妇幼保健院', '王师傅', 'transporting', '高架南线入口'),
('AMB-004', '沪A·12004', '第三人民医院', '赵师傅', 'on_scene', '滨江社区卫生服务站'),
('AMB-005', '沪A·12005', '中医医院', '陈师傅', 'maintenance', '车辆保障中心'),
('AMB-006', '沪A·12006', '第一人民医院', '刘师傅', 'standby', '第一人民医院急诊楼'),
('AMB-007', '沪A·12007', '中心医院', '周师傅', 'standby', '中心医院南停车场'),
('AMB-008', '沪A·12008', '儿童医院', '吴师傅', 'standby', '儿童医院急诊入口'),
('AMB-009', '沪A·12009', '第二人民医院', '郑师傅', 'standby', '第二人民医院急救站'),
('AMB-010', '沪A·12010', '第一人民医院', '孙师傅', 'dispatching', '中山东路与解放路交叉口'),
('AMB-011', '沪A·12011', '第二人民医院', '钱师傅', 'dispatching', '环城高速东出口'),
('AMB-012', '沪A·12012', '中心医院', '冯师傅', 'on_scene', '阳光花园小区 12 栋'),
('AMB-013', '沪A·12013', '第三人民医院', '蒋师傅', 'on_scene', '科技园区 A 座'),
('AMB-014', '沪A·12014', '第一人民医院', '韩师傅', 'on_scene', '长途汽车总站'),
('AMB-015', '沪A·12015', '中心医院', '杨师傅', 'transporting', '内环高架西段'),
('AMB-016', '沪A·12016', '妇幼保健院', '朱师傅', 'transporting', '长江大道中段'),
('AMB-017', '沪A·12017', '第二人民医院', '秦师傅', 'transporting', '医院快速通道'),
('AMB-018', '沪A·12018', '中医医院', '许师傅', 'maintenance', '城南维修站'),
('AMB-019', '沪A·12019', '儿童医院', '何师傅', 'maintenance', '车辆保障中心'),
('AMB-020', '沪A·12020', '第三人民医院', '吕师傅', 'standby', '第三人民医院急救楼');

INSERT INTO emergency_cases (case_no, patient_name, priority, address, status, assigned_ambulance, dispatch_vehicle_status, dispatched_at, created_at) VALUES
('CASE202606110001', '刘先生', 'high', '人民大道 188 号', 'accepted', 'AMB-001', 'dispatching', DATE_SUB(NOW(), INTERVAL 15 MINUTE), NOW()),
('CASE202606110002', '周女士', 'medium', '滨江花园 6 栋', 'reported', 'AMB-004', 'standby', DATE_SUB(NOW(), INTERVAL 5 MINUTE), NOW()),
('CASE202606110003', '赵先生', 'low', '中心医院门诊大厅', 'closed', 'AMB-002', 'dispatching', DATE_SUB(NOW(), INTERVAL 2 HOUR), NOW()),
('CASE202606110004', '吴奶奶', 'high', '阳光花园 12 栋 3 单元', 'reported', 'AMB-012', 'standby', DATE_SUB(NOW(), INTERVAL 8 MINUTE), NOW()),
('CASE202606110005', '孙先生', 'high', '科技园区 A 座 5 楼', 'accepted', 'AMB-013', 'on_scene', DATE_SUB(NOW(), INTERVAL 22 MINUTE), NOW()),
('CASE202606110006', '郑女士', 'medium', '长途汽车总站售票厅', 'accepted', 'AMB-014', 'dispatching', DATE_SUB(NOW(), INTERVAL 35 MINUTE), NOW()),
('CASE202606110007', '王先生', 'medium', '内环高架西段 158 号', 'accepted', 'AMB-015', 'transporting', DATE_SUB(NOW(), INTERVAL 45 MINUTE), NOW()),
('CASE202606110008', '冯宝宝', 'high', '长江大道中段 88 号', 'accepted', 'AMB-016', 'transporting', DATE_SUB(NOW(), INTERVAL 18 MINUTE), NOW()),
('CASE202606110009', '陈先生', 'low', '城南路 256 号', 'reported', NULL, NULL, NULL, NOW()),
('CASE202606110010', '褚女士', 'medium', '解放路 100 号', 'closed', 'AMB-006', 'standby', DATE_SUB(NOW(), INTERVAL 3 HOUR), NOW()),
('CASE202606110011', '卫先生', 'high', '环城高速东出口', 'reported', 'AMB-011', 'dispatching', DATE_SUB(NOW(), INTERVAL 12 MINUTE), NOW()),
('CASE202606110012', '蒋奶奶', 'low', '医院快速通道入口', 'closed', 'AMB-017', 'standby', DATE_SUB(NOW(), INTERVAL 4 HOUR), NOW()),
('CASE202606110013', '沈先生', 'medium', '儿童医院急诊科', 'closed', NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY)),
('CASE202606110014', '韩女士', 'high', '中山东路与解放路交叉口', 'accepted', 'AMB-010', 'standby', DATE_SUB(NOW(), INTERVAL 25 MINUTE), NOW()),
('CASE202606110015', '杨先生', 'medium', '城南维修站附近', 'reported', NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 30 MINUTE));

INSERT INTO alerts (title, description, status, handling_notes, handled_by, handled_at, created_at) VALUES
('响应时间超阈值', 'AMB-001 到达现场预计超过监管阈值，请调度复核路线。', 'open', NULL, NULL, NULL, NOW()),
('设备离线', 'AMB-005 心电监护设备超过 10 分钟未上报数据。', 'open', NULL, NULL, NULL, NOW()),
('轨迹偏离', 'AMB-003 当前轨迹偏离推荐转运路线。', 'resolved', '已联系驾驶员确认，因前方交通事故临时改道，已重新规划最优路线。', 2, DATE_SUB(NOW(), INTERVAL 8 MINUTE), DATE_SUB(NOW(), INTERVAL 25 MINUTE)),
('状态严重不一致', 'CASE202606110014 事件已受理，但 AMB-010 车辆仍处于待命状态，可能存在派车遗漏。', 'open', NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 10 MINUTE)),
('高优先级事件未派车', 'CASE202606110009 为低优先级，暂无风险。CASE202606110015 已上报 30 分钟未派车，请调度关注。', 'open', NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 12 MINUTE)),
('响应时间超阈值', 'AMB-011 前往环城高速东出口预计超时 8 分钟，请协调附近备勤车辆。', 'open', NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 5 MINUTE)),
('设备通信异常', 'AMB-018 GPS 定位数据中断超过 15 分钟，车辆当前位置不明。', 'open', NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 18 MINUTE)),
('转运超时预警', 'AMB-015 转运时间已超过 45 分钟，请确认患者状态及预计到达时间。', 'open', NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 3 MINUTE)),
('车辆检修超期', 'AMB-005 计划检修时间已超过 24 小时，请确认检修进度及预计归队时间。', 'resolved', '已联系维修站，车辆故障已排除，正在进行最后检测，预计 1 小时内归队。', 4, DATE_SUB(NOW(), INTERVAL 30 MINUTE), DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('驾驶员疲劳预警', 'AMB-003 驾驶员连续执勤已达 10 小时，请注意安排换班休息。', 'resolved', '已协调驾驶员换班，王师傅已前往最近休息点休整，由刘师傅接替。', 3, DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 3 HOUR)),
('急救耗材不足', 'AMB-014 急救箱内 AED 电极片库存不足，请及时补充。', 'resolved', '已通知物资科备货，将于下次车辆回院时补充更换。', 4, DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 5 HOUR)),
('状态有偏差预警', 'CASE202606110006 事件已受理，AMB-014 仍处于出车状态，建议确认是否已到达现场。', 'open', NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 8 MINUTE)),
('未处理事件积压', '当前有 3 起高优先级事件处置时间超过 30 分钟，请加强调度力量。', 'open', NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 2 MINUTE)),
('车辆未按规定回库', 'AMB-019 检修完成后未按规定返回车辆保障中心，请确认车辆位置。', 'resolved', '车辆在返回途中遇交通管制，已改道绕行，预计 20 分钟内到达。', 2, DATE_SUB(NOW(), INTERVAL 45 MINUTE), DATE_SUB(NOW(), INTERVAL 90 MINUTE)),
('跨院转运审批待办', 'CASE202606110008 申请跨院转运至上级医院，请监管主任审批。', 'open', NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 6 MINUTE));

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
