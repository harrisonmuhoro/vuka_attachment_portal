CREATE TABLE IF NOT EXISTS submission_cleanup_queue (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    file_path TEXT NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_error TEXT NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cleanup_ready (completed_at, available_at),
    INDEX idx_cleanup_submission (submission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
