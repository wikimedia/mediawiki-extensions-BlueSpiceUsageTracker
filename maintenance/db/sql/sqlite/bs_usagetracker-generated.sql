-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: extensions/BlueSpiceUsageTracker/maintenance/db/sql/bs_usagetracker.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/bs_usagetracker (
  ut_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  ut_identifier VARCHAR(255) DEFAULT '' NOT NULL,
  ut_count INTEGER UNSIGNED DEFAULT 0 NOT NULL,
  ut_type VARCHAR(255) DEFAULT '' NOT NULL,
  ut_timestamp BLOB DEFAULT '' NOT NULL
);
