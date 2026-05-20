-- Wahlplakate final schema (MySQL 8 / MariaDB 10+)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS wpl_images;
DROP TABLE IF EXISTS wpl_election_candidates;
DROP TABLE IF EXISTS wpl_election_parties;
DROP TABLE IF EXISTS wpl_localities;
DROP TABLE IF EXISTS wpl_districts;
DROP TABLE IF EXISTS wpl_candidates;
DROP TABLE IF EXISTS wpl_parties;
DROP TABLE IF EXISTS wpl_elections;
DROP TABLE IF EXISTS wpl_users;

CREATE TABLE wpl_users (
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	email VARCHAR(255) NOT NULL UNIQUE,
	password_hash VARCHAR(255) NOT NULL,
	role VARCHAR(32) NOT NULL DEFAULT 'user',
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wpl_elections (
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(255) NOT NULL,
	state_code VARCHAR(32) NULL,
	start_date DATE NULL,
	end_date DATE NULL,
	active TINYINT(1) NOT NULL DEFAULT 1,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wpl_parties (
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(255) NOT NULL,
	code VARCHAR(64) NOT NULL UNIQUE,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wpl_districts (
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	election_id BIGINT UNSIGNED NOT NULL,
	name VARCHAR(255) NOT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	UNIQUE KEY wpl_uq_districts_election_name (election_id, name),
	KEY wpl_ix_districts_election (election_id),
	CONSTRAINT wpl_fk_districts_election FOREIGN KEY (election_id) REFERENCES wpl_elections(id)
		ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wpl_localities (
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	district_id BIGINT UNSIGNED NOT NULL,
	name VARCHAR(255) NOT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	UNIQUE KEY wpl_uq_localities_district_name (district_id, name),
	KEY wpl_ix_localities_district (district_id),
		CONSTRAINT wpl_fk_localities_district FOREIGN KEY (district_id) REFERENCES wpl_districts(id)
		ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wpl_election_parties (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	election_id BIGINT UNSIGNED NOT NULL,
	party_id BIGINT UNSIGNED NOT NULL,
	ballot_label VARCHAR(255) NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
		UNIQUE KEY wpl_uq_election_party (election_id, party_id),
		KEY wpl_ix_ep_election (election_id),
		KEY wpl_ix_ep_party (party_id),
		CONSTRAINT wpl_fk_ep_election FOREIGN KEY (election_id) REFERENCES wpl_elections(id)
		ON UPDATE CASCADE ON DELETE CASCADE,
		CONSTRAINT wpl_fk_ep_party FOREIGN KEY (party_id) REFERENCES wpl_parties(id)
		ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wpl_election_candidates (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	election_id BIGINT UNSIGNED NOT NULL,
	election_party_id BIGINT UNSIGNED NOT NULL,
	candidate_code VARCHAR(128) NOT NULL,
	name VARCHAR(255) NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY wpl_uq_ec_candidate_code (candidate_code),
	UNIQUE KEY wpl_uq_ec_party_name (election_id, election_party_id, name),
	KEY wpl_ix_ec_election (election_id),
	KEY wpl_ix_ec_party (election_party_id),
		CONSTRAINT wpl_fk_ec_election FOREIGN KEY (election_id) REFERENCES wpl_elections(id)
		ON UPDATE CASCADE ON DELETE CASCADE,
		CONSTRAINT wpl_fk_ec_election_party FOREIGN KEY (election_party_id) REFERENCES wpl_election_parties(id)
		ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wpl_images (
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	election_id BIGINT UNSIGNED NOT NULL,
	election_party_id BIGINT UNSIGNED NOT NULL,
	election_candidate_id BIGINT UNSIGNED NULL,
	locality_id BIGINT UNSIGNED NOT NULL,
	file_path VARCHAR(1024) NOT NULL,
	original_filename VARCHAR(1024) NOT NULL,
	mime VARCHAR(128) NOT NULL,
	size_bytes BIGINT UNSIGNED NOT NULL,
	sha256 CHAR(64) NOT NULL UNIQUE,
	uploaded_by BIGINT UNSIGNED NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		KEY wpl_ix_images_election (election_id),
		KEY wpl_ix_images_party (election_party_id),
		KEY wpl_ix_images_candidate (election_candidate_id),
		KEY wpl_ix_images_locality (locality_id),
		KEY wpl_ix_images_uploaded_by (uploaded_by),
		CONSTRAINT wpl_fk_images_election FOREIGN KEY (election_id) REFERENCES wpl_elections(id)
		ON UPDATE CASCADE ON DELETE CASCADE,
		CONSTRAINT wpl_fk_images_election_party FOREIGN KEY (election_party_id) REFERENCES wpl_election_parties(id)
		ON UPDATE CASCADE ON DELETE CASCADE,
		CONSTRAINT wpl_fk_images_election_candidate FOREIGN KEY (election_candidate_id) REFERENCES wpl_election_candidates(id)
		ON UPDATE CASCADE ON DELETE SET NULL,
		CONSTRAINT wpl_fk_images_locality FOREIGN KEY (locality_id) REFERENCES wpl_localities(id)
		ON UPDATE CASCADE ON DELETE CASCADE,
		CONSTRAINT wpl_fk_images_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES wpl_users(id)
		ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;

