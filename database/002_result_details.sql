ALTER TABLE results
 ADD COLUMN owner_hash CHAR(64) NULL,
 ADD COLUMN client_ip VARCHAR(45) NULL,
 ADD COLUMN isp VARCHAR(200) NULL,
 ADD COLUMN server_name VARCHAR(120) NULL,
 ADD COLUMN server_city VARCHAR(120) NULL,
 ADD COLUMN connection_mode VARCHAR(10) NULL,
 ADD INDEX idx_owner_date (owner_hash,tested_at);
