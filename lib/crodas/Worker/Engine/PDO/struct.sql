CREATE TABLE `tasks` (
  `task_id` int(11) NOT NULL AUTO_INCREMENT,
  `task_type` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `task_payload` longtext COLLATE utf8_unicode_ci,
  `task_status` int(11) DEFAULT '1',
  `task_handle` varchar(20) COLLATE utf8_unicode_ci DEFAULT '',
  PRIMARY KEY (`task_id`),
  KEY `IDX_50586597AA29A40A40A9E1CF` (`task_handle`,`task_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
