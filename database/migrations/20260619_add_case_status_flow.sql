SET NAMES utf8mb4;

ALTER TABLE emergency_cases
    ADD COLUMN accepted_at TIMESTAMP NULL DEFAULT NULL AFTER dispatched_at,
    ADD COLUMN closed_at TIMESTAMP NULL DEFAULT NULL AFTER accepted_at;

CREATE TABLE IF NOT EXISTS case_status_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    case_no VARCHAR(40) NOT NULL,
    old_status VARCHAR(30) NOT NULL,
    new_status VARCHAR(30) NOT NULL,
    changed_by INT NOT NULL,
    operator_name VARCHAR(80) NOT NULL,
    operator_role VARCHAR(30) NOT NULL,
    transition_notes VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_case_id (case_id),
    INDEX idx_case_no (case_no),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (case_id) REFERENCES emergency_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id)
);
