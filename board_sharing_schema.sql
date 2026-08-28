-- Create board shares table for handling board access permissions
CREATE TABLE IF NOT EXISTS `board_shares` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `board_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `shared_by_user_id` int(11) NOT NULL,
  `access_level` ENUM('read', 'write') NOT NULL DEFAULT 'read',
  `status` ENUM('pending', 'accepted', 'declined') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_share` (`board_id`, `user_id`),
  FOREIGN KEY (`board_id`) REFERENCES `tm_board`(`id`) ON DELETE CASCADE,
  KEY `board_user_idx` (`board_id`, `user_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create notifications table
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` ENUM('board_invite', 'board_accept', 'board_decline') NOT NULL,
  `data` JSON NOT NULL,
  `is_read` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_notifications_idx` (`user_id`, `is_read`, `created_at`),
  KEY `notifications_type_idx` (`type`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update notifications table to support board_access_changed type
ALTER TABLE `notifications` 
MODIFY COLUMN `type` ENUM('board_invite', 'board_accept', 'board_decline', 'board_access_changed') NOT NULL;

-- Add chat notification types
ALTER TABLE `notifications` 
MODIFY COLUMN `type` ENUM(
    'board_invite', 
    'board_accept', 
    'board_decline', 
    'board_access_changed',
    'chat_message',
    'chat_mention',
    'chat_room_invite',
    'task_due_soon',
    'task_overdue',
    'task_assigned',
    'task_completed',
    'task_comment'
) NOT NULL;
