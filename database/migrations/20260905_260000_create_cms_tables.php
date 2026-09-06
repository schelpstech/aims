<?php

declare(strict_types=1);

use App\Database\Migration;

return new class implements Migration {
 public function up(\PDO$db):void{
  $db->exec(<<<'SQL'
  CREATE TABLE pages (
   id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,title VARCHAR(220) NOT NULL,slug VARCHAR(220) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,summary VARCHAR(500) NULL,body_html MEDIUMTEXT NOT NULL,meta_title VARCHAR(255) NULL,meta_description VARCHAR(500) NULL,status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',published_at DATETIME(6) NULL,created_by_user_id BIGINT UNSIGNED NOT NULL,updated_by_user_id BIGINT UNSIGNED NOT NULL,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),PRIMARY KEY(id),UNIQUE KEY uq_pages_public_id(public_id),UNIQUE KEY uq_pages_slug(slug),KEY idx_pages_public(status,published_at),CONSTRAINT fk_pages_creator FOREIGN KEY(created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,CONSTRAINT fk_pages_updater FOREIGN KEY(updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,CONSTRAINT chk_pages_status CHECK(status IN ('draft','published','archived')),CONSTRAINT chk_pages_publication CHECK((status='published' AND published_at IS NOT NULL) OR status<>'published')
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  SQL);
  $db->exec(<<<'SQL'
  CREATE TABLE posts (
   id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,post_type VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,title VARCHAR(220) NOT NULL,slug VARCHAR(220) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,excerpt VARCHAR(500) NULL,body_html MEDIUMTEXT NOT NULL,featured_media_id BIGINT UNSIGNED NULL,meta_title VARCHAR(255) NULL,meta_description VARCHAR(500) NULL,status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',published_at DATETIME(6) NULL,created_by_user_id BIGINT UNSIGNED NOT NULL,updated_by_user_id BIGINT UNSIGNED NOT NULL,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),PRIMARY KEY(id),UNIQUE KEY uq_posts_public_id(public_id),UNIQUE KEY uq_posts_type_slug(post_type,slug),KEY idx_posts_public(post_type,status,published_at),KEY idx_posts_featured_media(featured_media_id),CONSTRAINT fk_posts_creator FOREIGN KEY(created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,CONSTRAINT fk_posts_updater FOREIGN KEY(updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,CONSTRAINT chk_posts_type CHECK(post_type IN ('news','announcement','resource')),CONSTRAINT chk_posts_status CHECK(status IN ('draft','published','archived')),CONSTRAINT chk_posts_publication CHECK((status='published' AND published_at IS NOT NULL) OR status<>'published')
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  SQL);
  $db->exec(<<<'SQL'
  CREATE TABLE media (
   id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,original_name VARCHAR(255) NOT NULL,storage_path VARCHAR(500) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,mime_type VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,size_bytes BIGINT UNSIGNED NOT NULL,sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,alt_text VARCHAR(255) NULL,status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',uploaded_by_user_id BIGINT UNSIGNED NOT NULL,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),PRIMARY KEY(id),UNIQUE KEY uq_media_public_id(public_id),UNIQUE KEY uq_media_storage_path(storage_path),KEY idx_media_public(status,mime_type,created_at),KEY idx_media_digest(sha256),CONSTRAINT fk_media_uploader FOREIGN KEY(uploaded_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,CONSTRAINT chk_media_status CHECK(status IN ('draft','published','archived'))
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  SQL);
  $db->exec('ALTER TABLE posts ADD CONSTRAINT fk_posts_featured_media FOREIGN KEY(featured_media_id) REFERENCES media(id) ON DELETE SET NULL');
  $db->exec(<<<'SQL'
  CREATE TABLE faqs (
   id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,question VARCHAR(500) NOT NULL,answer_text TEXT NOT NULL,display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',published_at DATETIME(6) NULL,created_by_user_id BIGINT UNSIGNED NOT NULL,updated_by_user_id BIGINT UNSIGNED NOT NULL,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),PRIMARY KEY(id),UNIQUE KEY uq_faqs_public_id(public_id),KEY idx_faqs_public(status,display_order,published_at),CONSTRAINT fk_faqs_creator FOREIGN KEY(created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,CONSTRAINT fk_faqs_updater FOREIGN KEY(updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,CONSTRAINT chk_faqs_status CHECK(status IN ('draft','published','archived')),CONSTRAINT chk_faqs_publication CHECK((status='published' AND published_at IS NOT NULL) OR status<>'published')
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  SQL);
  $db->exec(<<<'SQL'
  CREATE TABLE downloads (
   id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,title VARCHAR(220) NOT NULL,slug VARCHAR(220) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,description VARCHAR(1000) NULL,media_id BIGINT UNSIGNED NOT NULL,download_count BIGINT UNSIGNED NOT NULL DEFAULT 0,status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',published_at DATETIME(6) NULL,created_by_user_id BIGINT UNSIGNED NOT NULL,updated_by_user_id BIGINT UNSIGNED NOT NULL,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),PRIMARY KEY(id),UNIQUE KEY uq_downloads_public_id(public_id),UNIQUE KEY uq_downloads_slug(slug),KEY idx_downloads_public(status,published_at),KEY idx_downloads_media(media_id),CONSTRAINT fk_downloads_media FOREIGN KEY(media_id) REFERENCES media(id) ON DELETE RESTRICT,CONSTRAINT fk_downloads_creator FOREIGN KEY(created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,CONSTRAINT fk_downloads_updater FOREIGN KEY(updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,CONSTRAINT chk_downloads_status CHECK(status IN ('draft','published','archived')),CONSTRAINT chk_downloads_publication CHECK((status='published' AND published_at IS NOT NULL) OR status<>'published')
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  SQL);
 }
 public function down(\PDO$db):void{$db->exec('DROP TABLE downloads');$db->exec('DROP TABLE faqs');$db->exec('DROP TABLE posts');$db->exec('DROP TABLE media');$db->exec('DROP TABLE pages');}
};
