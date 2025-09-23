--
-- @package     Joomla.Administrator
-- @subpackage  com_cmsmigrator
-- @copyright   Copyright (C) 2025 Open Source Matters, Inc.
-- @license     GNU General Public License version 2 or later; see LICENSE.txt
--

CREATE TABLE IF NOT EXISTS "#__cmsmigrator_articles" (
  "id" SERIAL NOT NULL,
  "title" VARCHAR(255) NOT NULL DEFAULT '',
  "alias" VARCHAR(255) NOT NULL DEFAULT '',
  "content" TEXT NOT NULL,
  "state" SMALLINT NOT NULL DEFAULT 0,
  "catid" INTEGER NOT NULL DEFAULT 0,
  "created" TIMESTAMP NOT NULL,
  "created_by" INTEGER NOT NULL DEFAULT 0,
  "publish_up" TIMESTAMP NULL DEFAULT NULL,
  "access" INTEGER NOT NULL DEFAULT 1,
  "language" CHAR(7) NOT NULL DEFAULT '*',
  "ordering" INTEGER NOT NULL DEFAULT 0,
  "params" TEXT NOT NULL DEFAULT '{}',
  PRIMARY KEY ("id")
);

CREATE INDEX IF NOT EXISTS "idx_cmsmigrator_articles_state" ON "#__cmsmigrator_articles" ("state");
CREATE INDEX IF NOT EXISTS "idx_cmsmigrator_articles_catid" ON "#__cmsmigrator_articles" ("catid");
CREATE INDEX IF NOT EXISTS "idx_cmsmigrator_articles_createdby" ON "#__cmsmigrator_articles" ("created_by");
CREATE INDEX IF NOT EXISTS "idx_cmsmigrator_articles_language" ON "#__cmsmigrator_articles" ("language");