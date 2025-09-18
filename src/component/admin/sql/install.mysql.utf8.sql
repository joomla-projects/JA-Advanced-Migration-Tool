--
-- @package     Joomla.Administrator
-- @subpackage  com_cmsmigrator
-- @copyright   Copyright (C) 2025 Open Source Matters, Inc.
-- @license     GNU General Public License version 2 or later; see LICENSE.txt
--

CREATE TABLE IF NOT EXISTS `#__cmsmigrator_articles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `alias` varchar(255) NOT NULL DEFAULT '',
  `content` mediumtext NOT NULL,
  `state` tinyint(3) NOT NULL DEFAULT 0,
  `catid` int(11) NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  `created_by` int(11) NOT NULL DEFAULT 0,
  `publish_up` datetime NULL DEFAULT NULL,
  `access` int(11) NOT NULL DEFAULT 1,
  `language` char(7) NOT NULL DEFAULT '*',
  `ordering` int(11) NOT NULL DEFAULT 0,
  `params` text NOT NULL DEFAULT '{}',
  PRIMARY KEY (`id`),
  KEY `idx_state` (`state`),
  KEY `idx_catid` (`catid`),
  KEY `idx_createdby` (`created_by`),
  KEY `idx_language` (`language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci; 