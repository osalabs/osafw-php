-- demo tables, use for reference/development, remove when not required

-- Demo Dictionary table
DROP TABLE IF EXISTS demo_dicts;
CREATE TABLE demo_dicts
(
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    iname        VARCHAR(64)  NOT NULL DEFAULT '',
    idesc        TEXT,
    prio         INT UNSIGNED NOT NULL DEFAULT 0, -- 0-on insert, then =id, default order by prio asc,iname

    status       TINYINT      NOT NULL DEFAULT 0, -- 0-ok, 1-under upload, 127-deleted
    add_time     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    add_users_id INT UNSIGNED          DEFAULT 0,
    upd_time     DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id INT UNSIGNED          DEFAULT 0
) ENGINE = InnoDB;

INSERT INTO demo_dicts (iname, idesc)
VALUES ('test1', 'test1 description');
INSERT INTO demo_dicts (iname, idesc)
VALUES ('test2', 'test2 description');
INSERT INTO demo_dicts (iname, idesc)
VALUES ('test3', 'test3 description');

-- Demo table
DROP TABLE IF EXISTS demos;
CREATE TABLE demos
(
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    parent_id         INT UNSIGNED NOT NULL DEFAULT 0,                 -- parent id - combo selection from SQL
    demo_dicts_id     INT UNSIGNED,                                    -- demo dictionary link

    iname             VARCHAR(64)  NOT NULL DEFAULT '',                -- string value for names
    idesc             TEXT,                                            -- large text value

    email             VARCHAR(128) NOT NULL DEFAULT '',                -- string value for unique field, such as email

    fint              INT UNSIGNED NOT NULL DEFAULT 0,                 -- accept only INT
    ffloat            FLOAT        NOT NULL DEFAULT 0,                 -- accept float digital values

    dict_link_auto_id INT UNSIGNED NOT NULL DEFAULT 0,                 -- index of autocomplete field - linked to demo_dicts
    dict_link_multi   VARCHAR(255) NOT NULL DEFAULT '',                -- multiple select values, link to demo_dicts

    fcombo            INT UNSIGNED NOT NULL DEFAULT 0,                 -- index of combo selection
    fradio            INT UNSIGNED NOT NULL DEFAULT 0,                 -- index of radio selection
    fyesno            BIT          NOT NULL DEFAULT 0,                 -- yes/no field 0 - NO, 1 - YES
    is_checkbox       TINYINT      NOT NULL DEFAULT 0,                 -- checkbox field 0 - not set, 1 - set

    fdate_combo       DATE,                                            -- date field with 3 combos editing
    fdate_pop         DATE,                                            -- date field with popup editing
    fdatetime         DATETIME,                                        -- date+time field
    ftime             INT UNSIGNED NOT NULL DEFAULT 0,                 -- time field - we always store time as seconds from start of the day [0-86400]

    att_id            INT UNSIGNED,                                    -- optional attached image

    status            TINYINT      NOT NULL DEFAULT 0,                 -- 0-ok, 127-deleted
    add_time          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP, -- date record added
    add_users_id      INT UNSIGNED          DEFAULT 0,                 -- user added record
    upd_time          DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id      INT UNSIGNED          DEFAULT 0,

    FOREIGN KEY (demo_dicts_id) REFERENCES demo_dicts (id),
    FOREIGN KEY (att_id) REFERENCES att (id),

    UNIQUE INDEX UX_demos_email (email),
    INDEX IX_demos_demo_dicts_id (demo_dicts_id),
    INDEX IX_demos_dict_link_auto_id (dict_link_auto_id)
) ENGINE = InnoDB;

-- junction table
DROP TABLE IF EXISTS demos_demo_dicts;
CREATE TABLE demos_demo_dicts
(
    demos_id      INT UNSIGNED,
    demo_dicts_id INT UNSIGNED,

    status        TINYINT  NOT NULL DEFAULT 0, -- 0-ok, 1-under change, deleted instantly
    add_time      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    add_users_id  INT UNSIGNED      DEFAULT 0,
    upd_time      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id  INT UNSIGNED      DEFAULT 0,

    FOREIGN KEY (demos_id) REFERENCES demos (id),
    FOREIGN KEY (demo_dicts_id) REFERENCES demo_dicts (id),

    INDEX IX_demos_demo_dicts_demos_id (demos_id, demo_dicts_id),
    INDEX IX_demos_demo_dicts_demo_dicts_id (demo_dicts_id, demos_id)
) ENGINE = InnoDB;

-- subtable for demo items
DROP TABLE IF EXISTS demos_items;
CREATE TABLE demos_items
(
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    demos_id      INT UNSIGNED NOT NULL,            -- main record link
    demo_dicts_id INT UNSIGNED,                     -- item lookup

    iname         VARCHAR(64)  NOT NULL DEFAULT '', -- string value for names
    idesc         TEXT,                             -- large text value
    is_checkbox   TINYINT      NOT NULL DEFAULT 0,  -- checkbox field 0 - not set, 1 - set

    status        TINYINT      NOT NULL DEFAULT 0,  -- 0-ok, 1-under change, deleted instantly
    add_time      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    add_users_id  INT UNSIGNED          DEFAULT 0,
    upd_time      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id  INT UNSIGNED          DEFAULT 0,

    FOREIGN KEY (demos_id) REFERENCES demos (id),
    FOREIGN KEY (demo_dicts_id) REFERENCES demo_dicts (id),

    INDEX IX_demos_items_demos_id (demos_id),
    INDEX IX_demos_items_demo_dicts_id (demo_dicts_id, demos_id)
) ENGINE = InnoDB;
