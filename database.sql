/*
 * Alive.SYS (活着么) - Database Structure
 * Developer: Slice
 */

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for users
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL COMMENT '用户称呼',
  `password` varchar(255) NOT NULL COMMENT '加密密码',
  `email` varchar(100) DEFAULT NULL COMMENT '紧急联系邮箱',
  `role` enum('user','admin') DEFAULT 'user' COMMENT '角色权限',
  `last_check_in` datetime DEFAULT NULL COMMENT '最后报平安时间',
  `status` enum('alive','warning','dead') DEFAULT 'alive' COMMENT '当前状态',
  `warning_count` int(11) DEFAULT 0 COMMENT '已发送警告次数',
  `last_notified_at` datetime DEFAULT NULL COMMENT '上次发送警告时间',
  `ip_address` varchar(50) DEFAULT NULL COMMENT '注册IP地址',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '注册时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户信息表';

-- ----------------------------
-- Table structure for settings
-- ----------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `key_name` varchar(50) NOT NULL,
  `value` text,
  `desc` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统配置表';

-- ----------------------------
-- Records of settings (默认配置)
-- ----------------------------
BEGIN;
INSERT INTO `settings` (`key_name`, `value`, `desc`) VALUES
('site_title', 'Alive.SYS', '网站名称'),
('check_interval', '24', '检测间隔(小时)'),
('smtp_host', 'smtp.qq.com', 'SMTP服务器'),
('smtp_port', '465', 'SMTP端口'),
('smtp_user', '', '发件人邮箱'),
('smtp_pass', '', '邮箱授权码');
COMMIT;

SET FOREIGN_KEY_CHECKS = 1;
