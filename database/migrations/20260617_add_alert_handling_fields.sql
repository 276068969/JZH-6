-- ============================================================
-- 迁移：为 alerts 表新增处置相关字段
-- 日期：2026-06-17
-- 说明：支持告警处置闭环，记录处置说明、处置人、处置时间
-- 幂等：已存在的字段不会重复添加
-- ============================================================

SET NAMES utf8mb4;

DELIMITER $$

DROP PROCEDURE IF EXISTS add_column_if_not_exists$$

CREATE PROCEDURE add_column_if_not_exists(
    IN p_table_name VARCHAR(64),
    IN p_column_name VARCHAR(64),
    IN p_column_def TEXT
)
BEGIN
    DECLARE column_exists INT DEFAULT 0;

    SELECT COUNT(*) INTO column_exists
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table_name
      AND COLUMN_NAME = p_column_name;

    IF column_exists = 0 THEN
        SET @sql = CONCAT(
            'ALTER TABLE `', p_table_name, '` ',
            'ADD COLUMN `', p_column_name, '` ', p_column_def
        );
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('✅ 已添加列: ', p_table_name, '.', p_column_name) AS migration_result;
    ELSE
        SELECT CONCAT('ℹ️  列已存在，跳过: ', p_table_name, '.', p_column_name) AS migration_result;
    END IF;
END$$

DROP PROCEDURE IF EXISTS add_fk_if_not_exists$$

CREATE PROCEDURE add_fk_if_not_exists(
    IN p_table_name VARCHAR(64),
    IN p_constraint_name VARCHAR(64),
    IN p_fk_def TEXT
)
BEGIN
    DECLARE fk_exists INT DEFAULT 0;

    SELECT COUNT(*) INTO fk_exists
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table_name
      AND CONSTRAINT_NAME = p_constraint_name
      AND CONSTRAINT_TYPE = 'FOREIGN KEY';

    IF fk_exists = 0 THEN
        SET @sql = CONCAT(
            'ALTER TABLE `', p_table_name, '` ',
            'ADD CONSTRAINT `', p_constraint_name, '` ', p_fk_def
        );
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('✅ 已添加外键: ', p_constraint_name) AS migration_result;
    ELSE
        SELECT CONCAT('ℹ️  外键已存在，跳过: ', p_constraint_name) AS migration_result;
    END IF;
END$$

DELIMITER ;

-- 1. 新增处置说明字段
CALL add_column_if_not_exists('alerts', 'handling_notes', 'TEXT NULL');

-- 2. 新增处置人字段
CALL add_column_if_not_exists('alerts', 'handled_by', 'INT NULL');

-- 3. 新增处置时间字段
CALL add_column_if_not_exists('alerts', 'handled_at', 'TIMESTAMP NULL DEFAULT NULL');

-- 4. 新增外键约束（handled_by -> users.id）
CALL add_fk_if_not_exists('alerts', 'fk_alerts_handled_by',
    'FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL');

-- 清理存储过程
DROP PROCEDURE IF EXISTS add_column_if_not_exists;
DROP PROCEDURE IF EXISTS add_fk_if_not_exists;

SELECT '🎉 alerts 表迁移完成（handling_notes、handled_by、handled_at 字段均已就绪）' AS migration_summary;
