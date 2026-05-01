-- SportsMIS SaaS – Full Database Schema
-- PHP 8.x / MySQL 8.x
-- Charset: utf8mb4 / Engine: InnoDB

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------
-- MASTER TABLES
-- -------------------------------------------------------

CREATE TABLE IF NOT EXISTS institution_types (
    id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    sort_order TINYINT UNSIGNED DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sports (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    description TEXT NULL,
    icon        VARCHAR(255) NULL,
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS staff_roles (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(50)  NOT NULL UNIQUE,
    description TEXT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS id_proof_types (
    id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS countries (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    iso2       CHAR(2)      NOT NULL UNIQUE,
    phone_code VARCHAR(10)  NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS states (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    country_id INT UNSIGNED NOT NULL,
    name       VARCHAR(100) NOT NULL,
    code       VARCHAR(10)  NULL,
    FOREIGN KEY (country_id) REFERENCES countries(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS districts (
    id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    state_id INT UNSIGNED NOT NULL,
    name     VARCHAR(100) NOT NULL,
    FOREIGN KEY (state_id) REFERENCES states(id)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- USERS  (all roles share this table)
-- -------------------------------------------------------

CREATE TABLE IF NOT EXISTS users (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email            VARCHAR(255) NOT NULL UNIQUE,
    password         VARCHAR(255) NOT NULL,
    role             ENUM('super_admin','institution_admin','athlete','staff') NOT NULL,
    status           ENUM('pending','active','suspended','rejected') NOT NULL DEFAULT 'pending',
    email_verified_at TIMESTAMP NULL,
    remember_token   VARCHAR(100) NULL,
    last_login_at    TIMESTAMP NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- INSTITUTION REGISTRATION (pending queue)
-- -------------------------------------------------------

CREATE TABLE IF NOT EXISTS institution_registrations (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    institution_name VARCHAR(255) NOT NULL,
    spoc_name        VARCHAR(255) NOT NULL,
    spoc_mobile      VARCHAR(20)  NOT NULL,
    email            VARCHAR(255) NOT NULL UNIQUE,
    address          TEXT         NOT NULL,
    status           ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    rejection_reason TEXT NULL,
    verified_at      TIMESTAMP NULL,
    verified_by      INT UNSIGNED NULL,
    user_id          INT UNSIGNED NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- INSTITUTIONS (approved)
-- -------------------------------------------------------

CREATE TABLE IF NOT EXISTS institutions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL UNIQUE,
    registration_id INT UNSIGNED NOT NULL,
    name            VARCHAR(255) NOT NULL,
    type_id         INT UNSIGNED NULL,
    logo            VARCHAR(500) NULL,
    reg_number      VARCHAR(100) NULL,
    reg_document    VARCHAR(500) NULL,
    address         TEXT NULL,
    email           VARCHAR(255) NULL,
    website         VARCHAR(255) NULL,
    affiliated_to   VARCHAR(255) NULL,
    spoc_name       VARCHAR(255) NULL,
    spoc_mobile     VARCHAR(20)  NULL,
    spoc_email      VARCHAR(255) NULL,
    validity_from   DATE NULL,
    validity_to     DATE NULL,
    profile_completed TINYINT(1) NOT NULL DEFAULT 0,
    status          ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    approved_by     INT UNSIGNED NULL,
    approved_at     TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)         REFERENCES users(id),
    FOREIGN KEY (registration_id) REFERENCES institution_registrations(id),
    FOREIGN KEY (type_id)         REFERENCES institution_types(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by)     REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- ATHLETE REGISTRATION (pending queue)
-- -------------------------------------------------------

CREATE TABLE IF NOT EXISTS athlete_registrations (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(255) NOT NULL,
    mobile        VARCHAR(20)  NOT NULL,
    email         VARCHAR(255) NOT NULL UNIQUE,
    gender        ENUM('male','female','other') NOT NULL,
    auth_provider ENUM('email','google') NOT NULL DEFAULT 'email',
    google_id     VARCHAR(100) NULL,
    status        ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    rejection_reason TEXT NULL,
    verified_at   TIMESTAMP NULL,
    verified_by   INT UNSIGNED NULL,
    user_id       INT UNSIGNED NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- ATHLETES (approved)
-- -------------------------------------------------------

CREATE TABLE IF NOT EXISTS athletes (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id               INT UNSIGNED NOT NULL UNIQUE,
    registration_id       INT UNSIGNED NOT NULL,
    name                  VARCHAR(255) NOT NULL,
    date_of_birth         DATE NULL,
    mobile                VARCHAR(20)  NULL,
    whatsapp_number       VARCHAR(20)  NULL,
    gender                ENUM('male','female','other') NOT NULL,
    weight                DECIMAL(5,2) NULL COMMENT 'kg',
    height                DECIMAL(5,2) NULL COMMENT 'cm',
    address               TEXT NULL,
    guardian_name         VARCHAR(255) NULL,
    id_proof_type_id      INT UNSIGNED NULL,
    id_proof_number       VARCHAR(100) NULL,
    id_proof_file         VARCHAR(500) NULL,
    passport_photo        VARCHAR(500) NULL,
    communication_address TEXT NULL,
    country_id            INT UNSIGNED NULL DEFAULT 1,
    state_id              INT UNSIGNED NULL,
    district_id           INT UNSIGNED NULL,
    nationality           VARCHAR(100) NULL DEFAULT 'Indian',
    profile_completed     TINYINT(1) NOT NULL DEFAULT 0,
    status                ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)          REFERENCES users(id),
    FOREIGN KEY (registration_id)  REFERENCES athlete_registrations(id),
    FOREIGN KEY (id_proof_type_id) REFERENCES id_proof_types(id) ON DELETE SET NULL,
    FOREIGN KEY (country_id)       REFERENCES countries(id) ON DELETE SET NULL,
    FOREIGN KEY (state_id)         REFERENCES states(id)    ON DELETE SET NULL,
    FOREIGN KEY (district_id)      REFERENCES districts(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS athlete_sports (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    athlete_id      INT UNSIGNED NOT NULL,
    sport_id        INT UNSIGNED NOT NULL,
    sport_specific_id VARCHAR(100) NULL COMMENT 'e.g. Shooter ID for shooting',
    licenses        TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_athlete_sport (athlete_id, sport_id),
    FOREIGN KEY (athlete_id) REFERENCES athletes(id) ON DELETE CASCADE,
    FOREIGN KEY (sport_id)   REFERENCES sports(id)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- EVENTS
-- -------------------------------------------------------

CREATE TABLE IF NOT EXISTS events (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    institution_id       INT UNSIGNED NOT NULL,
    name                 VARCHAR(255) NOT NULL,
    location             VARCHAR(500) NOT NULL,
    logo                 VARCHAR(500) NULL,
    reg_date_from        DATE NOT NULL,
    reg_date_to          DATE NOT NULL,
    event_date_from      DATE NOT NULL,
    event_date_to        DATE NOT NULL,
    latitude             DECIMAL(10,8) NULL,
    longitude            DECIMAL(11,8) NULL,
    bank_details         TEXT NULL,
    bank_qr_code         VARCHAR(500) NULL,
    contact_name         VARCHAR(255) NOT NULL,
    contact_designation  VARCHAR(100) NULL,
    contact_mobile       VARCHAR(20)  NOT NULL,
    contact_email        VARCHAR(255) NOT NULL,
    status               ENUM('draft','pending_approval','approved','rejected','completed','cancelled') NOT NULL DEFAULT 'draft',
    rejection_reason     TEXT NULL,
    approved_by          INT UNSIGNED NULL,
    approved_at          TIMESTAMP NULL,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (institution_id) REFERENCES institutions(id),
    FOREIGN KEY (approved_by)    REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS event_payment_modes (
    event_id INT UNSIGNED NOT NULL,
    mode     ENUM('manual','online') NOT NULL,
    PRIMARY KEY (event_id, mode),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS event_sports (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id   INT UNSIGNED NOT NULL,
    sport_id   INT UNSIGNED NOT NULL,
    category   VARCHAR(100) NULL,
    entry_fee  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    UNIQUE KEY uq_event_sport (event_id, sport_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (sport_id) REFERENCES sports(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS event_registrations (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id          INT UNSIGNED NOT NULL,
    athlete_id        INT UNSIGNED NOT NULL,
    sport_id          INT UNSIGNED NOT NULL,
    payment_mode      ENUM('manual','online') NULL,
    payment_status    ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    payment_reference VARCHAR(255) NULL,
    payment_amount    DECIMAL(10,2) NULL,
    payment_date      TIMESTAMP NULL,
    status            ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
    registered_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reg (event_id, athlete_id, sport_id),
    FOREIGN KEY (event_id)   REFERENCES events(id),
    FOREIGN KEY (athlete_id) REFERENCES athletes(id),
    FOREIGN KEY (sport_id)   REFERENCES sports(id)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- STAFF
-- -------------------------------------------------------

CREATE TABLE IF NOT EXISTS staff (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    institution_id INT UNSIGNED NOT NULL,
    user_id        INT UNSIGNED NOT NULL UNIQUE,
    name           VARCHAR(255) NOT NULL,
    mobile         VARCHAR(20)  NULL,
    status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (institution_id) REFERENCES institutions(id),
    FOREIGN KEY (user_id)        REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS staff_role_assignments (
    staff_id INT UNSIGNED NOT NULL,
    role_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (staff_id, role_id),
    FOREIGN KEY (staff_id) REFERENCES staff(id)       ON DELETE CASCADE,
    FOREIGN KEY (role_id)  REFERENCES staff_roles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- PASSWORD RESETS
-- -------------------------------------------------------

CREATE TABLE IF NOT EXISTS password_resets (
    email      VARCHAR(255) NOT NULL,
    token      VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (email),
    INDEX idx_token (token)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
