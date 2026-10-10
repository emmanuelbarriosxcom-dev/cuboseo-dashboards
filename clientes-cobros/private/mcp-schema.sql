-- Additive OAuth storage for the optional, read-only MCP connector.
-- Run only as a reviewed migration on the app's dedicated database.
CREATE TABLE IF NOT EXISTS cubo_oauth_clients (
 client_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 client_name VARCHAR(100) NOT NULL,
 redirect_uris LONGTEXT NOT NULL,
 grant_types VARCHAR(120) NOT NULL,
 disabled_at DATETIME NULL,
 created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cubo_oauth_codes (
 code_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 client_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 user_id INT UNSIGNED NOT NULL,
 redirect_uri VARCHAR(500) NOT NULL,
 resource VARCHAR(500) NOT NULL,
 scopes VARCHAR(200) NOT NULL,
 code_challenge VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 password_stamp CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at DATETIME NOT NULL,
 expires_at DATETIME NOT NULL,
 consumed_at DATETIME NULL,
 KEY oauth_code_expiry (expires_at),
 KEY oauth_code_user (user_id, client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cubo_oauth_tokens (
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 family_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 token_type ENUM('access','refresh') NOT NULL,
 client_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 user_id INT UNSIGNED NOT NULL,
 resource VARCHAR(500) NOT NULL,
 scopes VARCHAR(200) NOT NULL,
 password_stamp CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at DATETIME NOT NULL,
 expires_at DATETIME NOT NULL,
 revoked_at DATETIME NULL,
 KEY oauth_token_lookup (client_id, user_id, token_type),
 KEY oauth_token_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
