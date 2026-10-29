-- 003: blog posts. All DATETIME columns are UTC (connections run with time_zone '+00:00').
-- status: draft | scheduled (goes live at publish_at) | published.

CREATE TABLE IF NOT EXISTS posts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  title VARCHAR(255) NOT NULL,
  meta_title VARCHAR(255) NULL,
  description VARCHAR(1000) NULL,
  body_html MEDIUMTEXT NOT NULL,
  image VARCHAR(255) NULL,
  author VARCHAR(120) NOT NULL DEFAULT 'Dr. Isaiah Davis',
  status ENUM('draft','scheduled','published') NOT NULL DEFAULT 'draft',
  publish_at DATETIME NULL,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  legacy TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY posts_slug (slug),
  KEY posts_status (status, publish_at),
  KEY posts_published (published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
