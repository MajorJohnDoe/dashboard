SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


CREATE TABLE `shared_item_images` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `item_type` varchar(20) NOT NULL,
  `image_name` varchar(255) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `upload_type` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `file_size` int(10) UNSIGNED DEFAULT NULL COMMENT 'File size in kilobytes (KB)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `sticky_categories` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#000000'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `sticky_categories` (`id`, `user_id`, `title`, `color`) VALUES
(13, 3831, 'Ideas', '#c4b3e6'),
(14, 3831, 'Shopping list', '#b2e4e5'),
(15, 3831, 'Unraid', '#b3e6bd');


CREATE TABLE `sticky_notes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `content` mediumtext NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `sticky_notes` (`id`, `user_id`, `category_id`, `title`, `content`, `file_path`, `is_pinned`, `created_at`, `updated_at`) VALUES
(109, 3831, 13, 'Using Card Labels to Categorize Kanban Cards', '<p class=\"whitespace-pre-wrap break-words\">When creating a Kanban board, it\'s crucial to begin by identifying the various types of work items, or \"Kanban cards,\" that will populate your board. While it may seem intuitive to jump straight into designing the board layout, taking the time to define your card types first offers several advantages:</p>\n<ol class=\"-mt-1 list-decimal space-y-2 pl-8\">\n<li class=\"whitespace-normal break-words\"><strong>Improved Efficiency:</strong> By establishing clear categories upfront, you can design a board structure that accurately reflects your workflow.</li>\n<li class=\"whitespace-normal break-words\"><strong>Team Alignment:</strong> Discussing work item types as a team ensures everyone has a shared understanding of the different tasks you manage.</li>\n<li class=\"whitespace-normal break-words\"><strong>Standardized Nomenclature:</strong> This process naturally leads to the creation of a common language for describing work, enhancing communication across the team.</li>\n<li class=\"whitespace-normal break-words\"><strong>Better Visualization:</strong> Well-defined card types make it easier to quickly grasp the nature of work at each stage of your process.</li>\n<li class=\"whitespace-normal break-words\"><strong>Facilitates Analysis:</strong> Clear categorization allows for more meaningful metrics and insights about your workflow over time.</li>\n</ol>\n<p class=\"whitespace-pre-wrap break-words\">Remember, your Kanban card types should reflect the unique aspects of your team\'s work. Common examples might include \"Bug Fix,\" \"Feature Development,\" \"Customer Request,\" or \"Maintenance Task.\"</p>\n<p class=\"whitespace-pre-wrap break-words\">By prioritizing this step, you lay a strong foundation for a Kanban system that accurately represents your team\'s work and promotes more effective project management.</p>', NULL, 0, '2024-09-24 10:44:15', '2024-09-24 10:44:15');


CREATE TABLE `tm_board` (
  `id` int(11) NOT NULL,
  `user_id` mediumint(9) NOT NULL,
  `team_id` mediumint(9) DEFAULT NULL,
  `tm_last_update` datetime NOT NULL DEFAULT current_timestamp(),
  `tm_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `tm_board` (`id`, `user_id`, `team_id`, `tm_last_update`, `tm_name`) VALUES
(133, 3831, NULL, '2024-09-24 10:07:50', 'Work');


CREATE TABLE `tm_column` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `column_order` smallint(6) DEFAULT NULL,
  `column_task_order` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=Date created, 1=Last modified, 2=Priority',
  `column_name` varchar(50) NOT NULL,
  `column_max_display_tasks` tinyint(1) NOT NULL DEFAULT 2,
  `column_flag` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = resolved flag'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `tm_column` (`id`, `parent_id`, `column_order`, `column_task_order`, `column_name`, `column_max_display_tasks`, `column_flag`) VALUES
(186, 133, 60, 2, 'To Do\'s', 2, 0),
(187, 133, 60, 2, 'In progress', 2, 0),
(188, 133, 60, 2, 'Review', 2, 0),
(189, 133, 60, 1, 'Done', 2, 1);


CREATE TABLE `tm_label` (
  `id` int(11) NOT NULL,
  `board_id` int(11) NOT NULL,
  `label_color` varchar(20) NOT NULL,
  `label_name` varchar(50) NOT NULL,
  `is_favorite` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `tm_label` (`id`, `board_id`, `label_color`, `label_name`, `is_favorite`) VALUES
(93, 133, '#f1cff5', 'Bug', 0),
(94, 133, '#cff5e1', 'Coding', 0),
(95, 133, '#f5cfe6', 'Accounting', 0),
(96, 133, '#f5e5cf', 'Research', 0),
(97, 133, '#f5cfcf', 'Review', 0),
(98, 133, '#ededed', 'Feature', 0),
(99, 133, '#cfe1f5', 'Design', 0);


CREATE TABLE `tm_task` (
  `task_id` int(11) NOT NULL,
  `board_id` int(11) NOT NULL,
  `column_id` int(11) DEFAULT NULL,
  `task_title` varchar(100) NOT NULL,
  `task_desc` mediumtext NOT NULL,
  `task_checklist` text DEFAULT NULL,
  `task_priority` tinyint(1) NOT NULL DEFAULT 0,
  `task_created` datetime NOT NULL,
  `task_modified` datetime DEFAULT NULL,
  `task_resolved_date` datetime DEFAULT NULL COMMENT 'If column has "resolved flag" set datetime'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `tm_task` (`task_id`, `board_id`, `column_id`, `task_title`, `task_desc`, `task_checklist`, `task_priority`, `task_created`, `task_modified`, `task_resolved_date`) VALUES
(723, 133, 186, 'Add search bar to labels', '', NULL, 4, '2024-09-24 10:16:30', '2024-09-24 10:18:22', NULL),
(725, 133, 186, 'New tasks are appearing at the bottom of the feed', '', NULL, 2, '2024-09-24 10:18:55', '2024-09-24 10:18:55', NULL),
(726, 133, 186, 'Add border to images', '', NULL, 0, '2024-09-24 10:19:12', '2024-09-24 10:19:12', NULL),
(727, 133, 189, 'Refactor core.js', '', NULL, 0, '2024-09-24 10:19:45', '2024-09-24 10:19:45', '2024-09-24 10:19:48'),
(728, 133, 188, 'Create a account settings modal', '', NULL, 0, '2024-09-24 10:21:22', '2024-09-24 10:21:22', NULL),
(729, 133, 187, 'Fix padding on modals and sticky notes', '', NULL, 3, '2024-09-24 10:21:57', '2024-09-24 10:21:57', NULL),
(730, 133, 186, 'Create a upload function for profile pictures', '', NULL, 0, '2024-09-24 10:42:36', '2024-09-24 10:42:36', NULL),
(731, 133, 188, 'Look up what features a typical Kanboard is using', '', '[{\"description\":\"Tasks\",\"status\":\"complete\"},{\"description\":\"Columns\",\"status\":\"complete\"},{\"description\":\"And so on..\",\"status\":\"incomplete\"}]', 4, '2024-09-24 10:46:13', '2024-09-24 10:46:58', NULL);


CREATE TABLE `tm_task_label_rel` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `label_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `tm_task_label_rel` (`id`, `task_id`, `label_id`) VALUES
(2075, 723, 94),
(2076, 725, 93),
(2077, 726, 99),
(2078, 727, 94),
(2079, 728, 94),
(2080, 728, 99),
(2082, 729, 94),
(2081, 729, 99),
(2083, 730, 94),
(2087, 731, 96);


CREATE TABLE `user` (
  `user_id` mediumint(8) NOT NULL,
  `user_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = regular user, 2 = administrator',
  `user_username` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `user_password` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `user_email` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `user_reg_date` int(11) NOT NULL,
  `active_task_board` int(11) DEFAULT NULL,
  `gpt_api_key` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `user_avatar` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `user` (`user_id`, `user_type`, `user_username`, `user_password`, `user_email`, `user_reg_date`, `active_task_board`, `gpt_api_key`, `user_avatar`) VALUES
(3831, 0, 'johndoe', '07b4cfca1db57eb8a3b85a64f2034036e38a8130389879c4aa3661fc3e38a1d64257019613b222446d4b71dfec6a6e49ecd929d7ab63089924fb8a547d4c26c9', '', 1692205924, 133, '', '');


CREATE TABLE `user_session` (
  `id` int(11) NOT NULL,
  `session` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `token` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `userid` int(11) DEFAULT NULL,
  `sess_start` datetime DEFAULT NULL,
  `sess_expire` datetime DEFAULT NULL,
  `last_activity` datetime DEFAULT NULL,
  `ip` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `user_agent` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `user_session` (`id`, `session`, `token`, `userid`, `sess_start`, `sess_expire`, `last_activity`, `ip`, `user_agent`) VALUES
(157, 'eeea4ed271e3234d583416a7713490d6', 'a7049ff9caa4bd834b661f69759e7d496236a18384c676d44b02500df4298dbb', 3831, '2024-09-24 10:06:33', '2024-10-24 10:06:33', '2024-09-24 10:06:33', '172.55.0.3', NULL);


ALTER TABLE `shared_item_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `item_type` (`item_type`);

ALTER TABLE `sticky_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);


ALTER TABLE `sticky_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `category_id` (`category_id`);
ALTER TABLE `sticky_notes` ADD FULLTEXT KEY `title` (`title`,`content`);

ALTER TABLE `tm_board`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `tm_column`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `tm_label`
  ADD PRIMARY KEY (`id`),
  ADD KEY `board_id` (`board_id`,`label_name`);
ALTER TABLE `tm_label` ADD FULLTEXT KEY `label_name` (`label_name`);

ALTER TABLE `tm_task`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `todo_list_id` (`board_id`,`column_id`);

ALTER TABLE `tm_task_label_rel`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `task_id` (`task_id`,`label_id`);

ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`user_username`),
  ADD KEY `user_email` (`user_email`);

ALTER TABLE `user_session`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session` (`session`);

ALTER TABLE `shared_item_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

ALTER TABLE `sticky_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

ALTER TABLE `sticky_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=110;

ALTER TABLE `tm_board`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=134;

ALTER TABLE `tm_column`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=190;

ALTER TABLE `tm_label`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

ALTER TABLE `tm_task`
  MODIFY `task_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=732;

ALTER TABLE `tm_task_label_rel`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2088;

ALTER TABLE `user`
  MODIFY `user_id` mediumint(8) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3832;

ALTER TABLE `user_session`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=158;

ALTER TABLE `sticky_notes`
  ADD CONSTRAINT `sticky_notes_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `sticky_categories` (`id`) ON DELETE CASCADE;
COMMIT;