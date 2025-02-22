-- public user id
ALTER TABLE users ADD COLUMN icode                   VARCHAR(64) CHARACTER SET utf8 NOT NULL after id;

-- add email change procedure columns
ALTER TABLE users
 ADD COLUMN email_new VARCHAR(255) default '' after mfa_added,
 ADD COLUMN email_token VARCHAR(255) default '' after email_new,
 ADD COLUMN email_token_time DATETIME after email_token,
 ADD COLUMN email_bounced   TINYINT NOT NULL DEFAULT 0 AFTER email_token_time,    -- 1 if email bounced
 ADD COLUMN is_marked_spam  TINYINT NOT NULL DEFAULT 0 AFTER email_bounced -- 1 if user marked as spam
;

-- virtual controllers
DROP TABLE IF EXISTS fwcontrollers;
CREATE TABLE fwcontrollers
(
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    icode             VARCHAR(128) NOT NULL DEFAULT '', -- controller class without Controller suffix: AdminDemos
    url               VARCHAR(128) NOT NULL DEFAULT '', -- controller url: /Admin/Demos
    iname             VARCHAR(128) NOT NULL DEFAULT '', -- human readable name
    idesc             TEXT,

    model             VARCHAR(255) NOT NULL DEFAULT '', -- model class name controller is based on
    is_lookup         TINYINT      NOT NULL DEFAULT 0,  -- 1 if this is lookup controller (show in Lookup Manager)
    igroup            NVARCHAR(64) NOT NULL DEFAULT '', -- group name, if set - tables grouped under same group name
    access_level      TINYINT      NOT NULL DEFAULT 0,  -- min view access level
    access_level_edit TINYINT      NOT NULL DEFAULT 0,  -- min edit access level

    config            TEXT,                             -- config.json - use/create if file not exists /template/admin/demos/config.json

    status            TINYINT      NOT NULL DEFAULT 0,  -- 0-ok, 10-inactive, 127-deleted
    add_time          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    add_users_id      INT UNSIGNED          DEFAULT 0,
    upd_time          DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id      INT UNSIGNED          DEFAULT 0,

    UNIQUE INDEX UX_fwcontrollers_icode (icode),
    UNIQUE INDEX UX_fwcontrollers_url (url)
) ENGINE = InnoDB;

-- drop deprecated table
DROP TABLE lookup_manager_tables;