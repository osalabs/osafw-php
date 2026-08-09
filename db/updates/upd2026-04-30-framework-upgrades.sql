-- Framework schema updates imported from downstream application hardening.

ALTER TABLE activity_logs
    MODIFY payload LONGTEXT;

ALTER TABLE users
    MODIFY fname VARCHAR(127) NOT NULL DEFAULT '',
    MODIFY lname VARCHAR(127) NOT NULL DEFAULT '',
    MODIFY iname VARCHAR(255) AS (CONCAT(fname, ' ', lname));

DROP TABLE IF EXISTS locks;
CREATE TABLE locks
(
    icode        VARCHAR(255) NOT NULL,
    environment VARCHAR(16)  NOT NULL DEFAULT '',
    item_id     INT UNSIGNED NOT NULL DEFAULT 0,

    host         VARCHAR(255) NOT NULL DEFAULT '',
    script       VARCHAR(255) NOT NULL DEFAULT '',
    pid          INT UNSIGNED NOT NULL DEFAULT 0,
    expires      INT UNSIGNED NOT NULL DEFAULT 0,

    add_time     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    add_users_id INT UNSIGNED          DEFAULT 0,
    upd_time     DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id INT UNSIGNED          DEFAULT 0,

    PRIMARY KEY (icode, environment, item_id)
) ENGINE = InnoDB;
