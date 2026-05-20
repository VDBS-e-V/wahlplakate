-- Wahlplakate final schema (MySQL 8 / MariaDB 10+)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS images;
DROP TABLE IF EXISTS election_candidates;
DROP TABLE IF EXISTS election_parties;
DROP TABLE IF EXISTS localities;
DROP TABLE IF EXISTS districts;
DROP TABLE IF EXISTS candidates;
DROP TABLE IF EXISTS parties;
DROP TABLE IF EXISTS elections;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	email VARCHAR(255) NOT NULL UNIQUE,
	password_hash VARCHAR(255) NOT NULL,
	role VARCHAR(32) NOT NULL DEFAULT 'user',
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE elections (
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(255) NOT NULL,
	state_code VARCHAR(32) NULL,
	start_date DATE NULL,
	end_date DATE NULL,
	active TINYINT(1) NOT NULL DEFAULT 1,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE parties (
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(255) NOT NULL,
	code VARCHAR(64) NOT NULL UNIQUE,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE districts (
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	election_id BIGINT UNSIGNED NOT NULL,
	name VARCHAR(255) NOT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	UNIQUE KEY uq_districts_election_name (election_id, name),
	KEY ix_districts_election (election_id),
	CONSTRAINT fk_districts_election FOREIGN KEY (election_id) REFERENCES elections(id)
		ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE localities (
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	district_id BIGINT UNSIGNED NOT NULL,
	name VARCHAR(255) NOT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	UNIQUE KEY uq_localities_district_name (district_id, name),
	KEY ix_localities_district (district_id),
	CONSTRAINT fk_localities_district FOREIGN KEY (district_id) REFERENCES districts(id)
		ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE election_parties (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	election_id BIGINT UNSIGNED NOT NULL,
	party_id BIGINT UNSIGNED NOT NULL,
	ballot_label VARCHAR(255) NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_election_party (election_id, party_id),
	KEY ix_ep_election (election_id),
	KEY ix_ep_party (party_id),
	CONSTRAINT fk_ep_election FOREIGN KEY (election_id) REFERENCES elections(id)
		ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT fk_ep_party FOREIGN KEY (party_id) REFERENCES parties(id)
		ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE election_candidates (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	election_id BIGINT UNSIGNED NOT NULL,
	election_party_id BIGINT UNSIGNED NOT NULL,
	candidate_code VARCHAR(128) NOT NULL,
	name VARCHAR(255) NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_ec_candidate_code (candidate_code),
	UNIQUE KEY uq_ec_party_name (election_id, election_party_id, name),
	KEY ix_ec_election (election_id),
	KEY ix_ec_party (election_party_id),
	CONSTRAINT fk_ec_election FOREIGN KEY (election_id) REFERENCES elections(id)
		ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT fk_ec_election_party FOREIGN KEY (election_party_id) REFERENCES election_parties(id)
		ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE images (
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
	KEY ix_images_election (election_id),
	KEY ix_images_party (election_party_id),
	KEY ix_images_candidate (election_candidate_id),
	KEY ix_images_locality (locality_id),
	KEY ix_images_uploaded_by (uploaded_by),
	CONSTRAINT fk_images_election FOREIGN KEY (election_id) REFERENCES elections(id)
		ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT fk_images_election_party FOREIGN KEY (election_party_id) REFERENCES election_parties(id)
		ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT fk_images_election_candidate FOREIGN KEY (election_candidate_id) REFERENCES election_candidates(id)
		ON UPDATE CASCADE ON DELETE SET NULL,
	CONSTRAINT fk_images_locality FOREIGN KEY (locality_id) REFERENCES localities(id)
		ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT fk_images_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id)
		ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;

