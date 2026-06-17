SET NAMES utf8mb4;

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
