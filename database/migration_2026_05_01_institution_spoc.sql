-- Migration: add SPOC + contact fields to institutions
-- Run this against existing databases (new installs already get these via schema.sql).
-- Each ALTER is independent so a "Duplicate column" error on a re-run is safe to ignore.

ALTER TABLE institutions ADD COLUMN email         VARCHAR(255) NULL AFTER address;
ALTER TABLE institutions ADD COLUMN website       VARCHAR(255) NULL AFTER email;
ALTER TABLE institutions ADD COLUMN affiliated_to VARCHAR(255) NULL AFTER website;
ALTER TABLE institutions ADD COLUMN spoc_name     VARCHAR(255) NULL AFTER affiliated_to;
ALTER TABLE institutions ADD COLUMN spoc_mobile   VARCHAR(20)  NULL AFTER spoc_name;
ALTER TABLE institutions ADD COLUMN spoc_email    VARCHAR(255) NULL AFTER spoc_mobile;
