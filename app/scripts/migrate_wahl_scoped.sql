SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS election_parties (
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
		ON UPDATE CASCADE ON DELETE RESTRICT,
	CONSTRAINT fk_ep_party FOREIGN KEY (party_id) REFERENCES parties(id)
		ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS election_candidates (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	election_id BIGINT UNSIGNED NOT NULL,
	election_party_id BIGINT UNSIGNED NULL,
	candidate_code VARCHAR(128) NOT NULL,
	name VARCHAR(255) NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_ec_candidate_code (candidate_code),
	KEY ix_ec_election (election_id),
	KEY ix_ec_party (election_party_id),
	CONSTRAINT fk_ec_election FOREIGN KEY (election_id) REFERENCES elections(id)
		ON UPDATE CASCADE ON DELETE RESTRICT,
	CONSTRAINT fk_ec_election_party FOREIGN KEY (election_party_id) REFERENCES election_parties(id)
		ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE districts
	ADD COLUMN IF NOT EXISTS election_id BIGINT UNSIGNED NULL;

ALTER TABLE districts
	ADD CONSTRAINT fk_districts_election FOREIGN KEY (election_id) REFERENCES elections(id)
	ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE districts
	ADD UNIQUE KEY uq_districts_election_name (election_id, name);

ALTER TABLE images
	ADD COLUMN IF NOT EXISTS election_id BIGINT UNSIGNED NULL,
	ADD COLUMN IF NOT EXISTS election_party_id BIGINT UNSIGNED NULL,
	ADD COLUMN IF NOT EXISTS election_candidate_id BIGINT UNSIGNED NULL,
	ADD COLUMN IF NOT EXISTS locality_id BIGINT UNSIGNED NULL;

SET FOREIGN_KEY_CHECKS=1;