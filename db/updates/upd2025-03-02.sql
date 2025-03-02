-- track framework database updates
CREATE TABLE fwupdates
(
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    iname        VARCHAR(255) NOT NULL DEFAULT '', -- filename from db/updates folder
    idesc        TEXT,                             -- file content

    applied_time DATETIME,                         -- applied date-time
    last_error   TEXT,                             -- last error message

    status       TINYINT      NOT NULL DEFAULT 0,  -- 0(new), 10(inactive/skip), 20-failed, 30-applied, 127-deleted
    add_time     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    add_users_id INT UNSIGNED          DEFAULT 0,
    upd_time     DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id INT UNSIGNED          DEFAULT 0,

    UNIQUE INDEX UX_fwupdates_iname (iname)
) ENGINE = InnoDB;

INSERT INTO fwcontrollers (igroup, icode, url, iname, model, access_level)
VALUES ('System', 'AdminFwUpdates', '/Admin/FwUpdates', 'FW Updates', 'FwUpdates', 100);