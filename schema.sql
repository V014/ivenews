CREATE DATABASE IF NOT EXISTS ivenews CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ivenews;

CREATE TABLE roles (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(40) NOT NULL UNIQUE,
    label VARCHAR(80) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE permissions (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL UNIQUE,
    description VARCHAR(180) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
    role_id TINYINT UNSIGNED NOT NULL,
    permission_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id TINYINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    failed_login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id),
    INDEX idx_users_role_active (role_id, is_active)
) ENGINE=InnoDB;

CREATE TABLE categories (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL DEFAULT '',
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE articles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id SMALLINT UNSIGNED NOT NULL,
    author_id INT UNSIGNED NOT NULL,
    title VARCHAR(220) NOT NULL,
    slug VARCHAR(240) NOT NULL UNIQUE,
    excerpt VARCHAR(500) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    image_path VARCHAR(500) NULL,
    image_alt VARCHAR(250) NOT NULL DEFAULT '',
    source_url VARCHAR(1000) NULL,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    published_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_articles_category FOREIGN KEY (category_id) REFERENCES categories(id),
    CONSTRAINT fk_articles_author FOREIGN KEY (author_id) REFERENCES users(id),
    INDEX idx_articles_feed (status, published_at),
    INDEX idx_articles_category_feed (category_id, status, published_at),
    INDEX idx_articles_author_status (author_id, status)
) ENGINE=InnoDB;

CREATE TABLE article_revisions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id INT UNSIGNED NOT NULL,
    editor_id INT UNSIGNED NOT NULL,
    title VARCHAR(220) NOT NULL,
    excerpt VARCHAR(500) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    status ENUM('draft', 'published', 'archived') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_revisions_article FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    CONSTRAINT fk_revisions_editor FOREIGN KEY (editor_id) REFERENCES users(id),
    INDEX idx_revisions_article_created (article_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(60) NOT NULL,
    entity_id INT UNSIGNED NULL,
    details JSON NOT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_entity (entity_type, entity_id, created_at),
    INDEX idx_audit_user_date (user_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    succeeded TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_email_time (email, attempted_at),
    INDEX idx_login_attempts_ip_time (ip_hash, attempted_at)
) ENGINE=InnoDB;

INSERT INTO roles (name, label) VALUES
    ('admin', 'Administrator'),
    ('editor', 'Editor'),
    ('author', 'Author');

INSERT INTO permissions (name, description) VALUES
    ('articles.create', 'Create new articles'),
    ('articles.edit_own', 'Edit own unpublished articles'),
    ('articles.manage', 'Review and edit any article'),
    ('articles.publish', 'Publish and archive articles'),
    ('categories.manage', 'Manage editorial categories'),
    ('users.manage', 'Create and manage staff accounts'),
    ('audit.view', 'Review editorial audit history');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.name = 'admin';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name = 'editor'
  AND p.name IN ('articles.create', 'articles.manage', 'articles.publish', 'categories.manage');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name = 'author'
  AND p.name IN ('articles.create', 'articles.edit_own');

INSERT INTO categories (name, slug, description, sort_order) VALUES
    ('Business', 'business', 'Markets, money and the ideas shaping Malawi’s economy.', 1),
    ('Technology', 'technology', 'The people and inventions changing how we live.', 2),
    ('Health', 'health', 'Research, wellbeing and public health.', 3),
    ('Culture', 'culture', 'People, places and the stories that connect us.', 4);
