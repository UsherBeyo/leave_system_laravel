-- Leave request attachments enhancement
CREATE TABLE IF NOT EXISTS `leave_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `leave_request_id` int(11) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `document_type` varchar(100) DEFAULT 'supporting_document',
  `uploaded_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_leave_attachments_leave_request_id` (`leave_request_id`),
  KEY `idx_leave_attachments_uploaded_by_user_id` (`uploaded_by_user_id`),
  CONSTRAINT `fk_leave_attachments_leave_request` FOREIGN KEY (`leave_request_id`) REFERENCES `leave_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_leave_attachments_uploaded_by_user` FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
