-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Värd: db
-- Tid vid skapande: 13 feb 2026 kl 22:48
-- Serverversion: 11.4.4-MariaDB-ubu2404
-- PHP-version: 8.2.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Databas: `todoboard`
--

-- --------------------------------------------------------

--
-- Tabellstruktur `board_shares`
--

CREATE TABLE `board_shares` (
  `id` int(11) NOT NULL,
  `board_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `shared_by_user_id` int(11) NOT NULL,
  `access_level` enum('read','write') NOT NULL DEFAULT 'read',
  `status` enum('pending','accepted','declined') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Tabellstruktur `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('board_invite','board_accept','board_decline') NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data`)),
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Tabellstruktur `shared_item_images`
--

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


-- --------------------------------------------------------

--
-- Tabellstruktur `sticky_categories`
--

CREATE TABLE `sticky_categories` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#000000'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur `sticky_notes`
--

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

-- --------------------------------------------------------

--
-- Tabellstruktur `tm_board`
--

CREATE TABLE `tm_board` (
  `id` int(11) NOT NULL,
  `user_id` mediumint(9) NOT NULL,
  `team_id` mediumint(9) DEFAULT NULL,
  `tm_last_update` datetime NOT NULL DEFAULT current_timestamp(),
  `tm_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur `tm_column`
--

CREATE TABLE `tm_column` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `column_order` smallint(6) DEFAULT NULL,
  `column_task_order` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=Date created, 1=Last modified, 2=Priority',
  `column_name` varchar(50) NOT NULL,
  `column_max_display_tasks` tinyint(1) NOT NULL DEFAULT 2,
  `column_flag` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = resolved flag'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tabellstruktur `tm_label`
--

CREATE TABLE `tm_label` (
  `id` int(11) NOT NULL,
  `board_id` int(11) NOT NULL,
  `label_color` varchar(20) NOT NULL,
  `label_name` varchar(50) NOT NULL,
  `is_favorite` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellstruktur `tm_task`
--

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

-- --------------------------------------------------------

--
-- Tabellstruktur `tm_task_label_rel`
--

CREATE TABLE `tm_task_label_rel` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `label_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Tabellstruktur `user`
--

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


-- --------------------------------------------------------

--
-- Tabellstruktur `user_session`
--

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


--
-- Index för dumpade tabeller
--

--
-- Index för tabell `board_shares`
--
ALTER TABLE `board_shares`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_share` (`board_id`,`user_id`),
  ADD KEY `board_user_idx` (`board_id`,`user_id`,`status`);

--
-- Index för tabell `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_notifications_idx` (`user_id`,`is_read`,`created_at`),
  ADD KEY `notifications_type_idx` (`type`,`created_at`);

--
-- Index för tabell `shared_item_images`
--
ALTER TABLE `shared_item_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `item_type` (`item_type`);

--
-- Index för tabell `sticky_categories`
--
ALTER TABLE `sticky_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index för tabell `sticky_notes`
--
ALTER TABLE `sticky_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `category_id` (`category_id`);
ALTER TABLE `sticky_notes` ADD FULLTEXT KEY `title` (`title`,`content`);

--
-- Index för tabell `tm_board`
--
ALTER TABLE `tm_board`
  ADD PRIMARY KEY (`id`);

--
-- Index för tabell `tm_column`
--
ALTER TABLE `tm_column`
  ADD PRIMARY KEY (`id`);

--
-- Index för tabell `tm_label`
--
ALTER TABLE `tm_label`
  ADD PRIMARY KEY (`id`),
  ADD KEY `board_id` (`board_id`,`label_name`);
ALTER TABLE `tm_label` ADD FULLTEXT KEY `label_name` (`label_name`);

--
-- Index för tabell `tm_task`
--
ALTER TABLE `tm_task`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `todo_list_id` (`board_id`,`column_id`);

--
-- Index för tabell `tm_task_label_rel`
--
ALTER TABLE `tm_task_label_rel`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `task_id` (`task_id`,`label_id`);

--
-- Index för tabell `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`user_username`),
  ADD KEY `user_email` (`user_email`);

--
-- Index för tabell `user_session`
--
ALTER TABLE `user_session`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session` (`session`);

--
-- AUTO_INCREMENT för dumpade tabeller
--

--
-- AUTO_INCREMENT för tabell `board_shares`
--
ALTER TABLE `board_shares`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT för tabell `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT för tabell `shared_item_images`
--
ALTER TABLE `shared_item_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT för tabell `sticky_categories`
--
ALTER TABLE `sticky_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT för tabell `sticky_notes`
--
ALTER TABLE `sticky_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;

--
-- AUTO_INCREMENT för tabell `tm_board`
--
ALTER TABLE `tm_board`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=138;

--
-- AUTO_INCREMENT för tabell `tm_column`
--
ALTER TABLE `tm_column`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=195;

--
-- AUTO_INCREMENT för tabell `tm_label`
--
ALTER TABLE `tm_label`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT för tabell `tm_task`
--
ALTER TABLE `tm_task`
  MODIFY `task_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1465;

--
-- AUTO_INCREMENT för tabell `tm_task_label_rel`
--
ALTER TABLE `tm_task_label_rel`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3459;

--
-- AUTO_INCREMENT för tabell `user`
--
ALTER TABLE `user`
  MODIFY `user_id` mediumint(8) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3833;

--
-- AUTO_INCREMENT för tabell `user_session`
--
ALTER TABLE `user_session`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=218;

--
-- Restriktioner för dumpade tabeller
--

--
-- Restriktioner för tabell `board_shares`
--
ALTER TABLE `board_shares`
  ADD CONSTRAINT `board_shares_ibfk_1` FOREIGN KEY (`board_id`) REFERENCES `tm_board` (`id`) ON DELETE CASCADE;

--
-- Restriktioner för tabell `sticky_notes`
--
ALTER TABLE `sticky_notes`
  ADD CONSTRAINT `sticky_notes_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `sticky_categories` (`id`) ON DELETE CASCADE;
COMMIT;
