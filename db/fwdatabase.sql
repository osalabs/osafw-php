-- (c) 2004-2024 oSa
-- FOR MySQL 8.x

-- CREATE DATABASE xxx CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE xxx;

-- core framework tables only, create app-specific tables in database.sql

SET @OLD_SQL_MODE = @@SQL_MODE, SQL_MODE = '';

-- application entities lookup - autofilled on demand
DROP TABLE IF EXISTS fwentities;
CREATE TABLE fwentities
(
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    icode        VARCHAR(128) NOT NULL DEFAULT '', -- basically table name
    iname        VARCHAR(128) NOT NULL DEFAULT '', -- human readable name
    idesc        TEXT,

    status       TINYINT      NOT NULL DEFAULT 0,  -- 0-ok, 1-under upload, 127-deleted
    add_time     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    add_users_id INT UNSIGNED          DEFAULT 0,
    upd_time     DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id INT UNSIGNED          DEFAULT 0,

    UNIQUE INDEX UX_fwentities_icode (icode)
) ENGINE = InnoDB;

-- upload categories
DROP TABLE IF EXISTS att_categories;
CREATE TABLE att_categories
(
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    icode        VARCHAR(64)  NOT NULL DEFAULT '', -- to use from code
    iname        VARCHAR(64)  NOT NULL DEFAULT '',
    idesc        TEXT,
    prio         INT UNSIGNED NOT NULL DEFAULT 0,  -- 0-on insert, then =id, default order by prio asc,iname

    status       TINYINT      NOT NULL DEFAULT 0,  -- 0-ok, 127-deleted
    add_time     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    add_users_id INT UNSIGNED          DEFAULT 0,
    upd_time     DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id INT UNSIGNED          DEFAULT 0
) ENGINE = InnoDB;


-- attachments - file uploads
DROP TABLE IF EXISTS att;
CREATE TABLE att
(
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, /* files stored on disk under 0/0/0/id.dat */
    att_categories_id INT UNSIGNED,

    fwentities_id     INT UNSIGNED, -- related to entity (optional)
    item_id           INT UNSIGNED,

    is_s3             TINYINT               DEFAULT 0, /* 1 if file is in S3 - see config: $S3Bucket/$S3Root/att/att_id */
    is_inline         TINYINT               DEFAULT 0, /* if uploaded with wysiwyg */
    is_image          TINYINT               DEFAULT 0, /* 1 if this is supported image */

    fname             VARCHAR(255) NOT NULL DEFAULT '', /*original file name*/
    fsize             BIGINT                DEFAULT 0, /*file size*/
    ext               VARCHAR(16)  NOT NULL DEFAULT '', /*extension*/
    iname             VARCHAR(255) NOT NULL DEFAULT '', /*attachment name*/

    status            TINYINT      NOT NULL DEFAULT 0, /*0-ok, 1-under upload, 127-deleted*/
    add_time          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    add_users_id      INT UNSIGNED          DEFAULT 0,
    upd_time          DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id      INT UNSIGNED          DEFAULT 0,

    FOREIGN KEY (att_categories_id) REFERENCES att_categories (id),
    FOREIGN KEY (fwentities_id) REFERENCES fwentities (id),

    INDEX IX_att_categories (att_categories_id),
    INDEX IX_att_fwentities (fwentities_id, item_id)
) ENGINE = InnoDB;

-- junction to link multiple att files to multiple entity items
DROP TABLE IF EXISTS att_links;
CREATE TABLE att_links
(
    att_id        INT UNSIGNED NOT NULL,
    fwentities_id INT UNSIGNED NOT NULL, -- related to entity
    item_id       INT UNSIGNED NOT NULL,

    status        TINYINT      NOT NULL DEFAULT 0, /*0-ok, 1-under change, deleted instantly*/
    add_time      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    add_users_id  INT UNSIGNED          DEFAULT 0,

    FOREIGN KEY (att_id) REFERENCES att (id),
    FOREIGN KEY (fwentities_id) REFERENCES fwentities (id),

    UNIQUE INDEX UX_att_links (fwentities_id, item_id, att_id),
    INDEX IX_att_links_att (att_id, fwentities_id, item_id)
) ENGINE = InnoDB;


DROP TABLE IF EXISTS users;
CREATE TABLE users
(
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    email          VARCHAR(128) NOT NULL DEFAULT '',
    pwd            VARCHAR(255) NOT NULL DEFAULT '',            -- hashed password
    access_level   TINYINT      NOT NULL,                       -- 0 - visitor, 1 - usual user, 80 - moderator, 100 - admin
    is_readonly    TINYINT      NOT NULL DEFAULT 0,             -- 1 if user is readonly

    fname          VARCHAR(32)  NOT NULL DEFAULT '',
    lname          VARCHAR(32)  NOT NULL DEFAULT '',
    iname          VARCHAR(128) AS (CONCAT(fname, ' ', lname)), -- calculated column

    title          VARCHAR(128) NOT NULL DEFAULT '',

    address1       VARCHAR(128) NOT NULL DEFAULT '',
    address2       VARCHAR(64)  NOT NULL DEFAULT '',
    city           VARCHAR(64)  NOT NULL DEFAULT '',
    state          VARCHAR(4)   NOT NULL DEFAULT '',
    zip            VARCHAR(16)  NOT NULL DEFAULT '',
    phone          VARCHAR(16)  NOT NULL DEFAULT '',

    lang           VARCHAR(16)  NOT NULL DEFAULT 'en',          -- user interface language
    ui_theme       TINYINT      NOT NULL DEFAULT 0,             -- 0--default theme
    ui_mode        TINYINT      NOT NULL DEFAULT 0,             -- 0--auto, 10-light, 20-dark

    idesc          TEXT,
    att_id         INT UNSIGNED,                                -- avatar

    login_time     DATETIME,
    pwd_reset      VARCHAR(255) NULL,
    pwd_reset_time DATETIME     NULL,
    mfa_secret     VARCHAR(64),                                 -- mfa secret code, if empty - no mfa for the user configured
    mfa_recovery   VARCHAR(1024),                               -- mfa recovery hashed codes, space-separated
    mfa_added      DATETIME,                                    -- last datetime when mfa setup or resynced

    status         TINYINT      NOT NULL DEFAULT 0, /*0-ok, 127-deleted*/
    add_time       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    add_users_id   INT UNSIGNED          DEFAULT 0,
    upd_time       DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id   INT UNSIGNED          DEFAULT 0,

    FOREIGN KEY (att_id) REFERENCES att (id),

    UNIQUE INDEX UX_users_email (email)
) ENGINE = InnoDB;

INSERT INTO users (fname, lname, email, pwd, access_level)
VALUES ('Website', 'Admin', 'admin@admin.com', 'CHANGE_ME', 100);

/*user cookies (for permanent sessions)*/
DROP TABLE IF EXISTS users_cookies;
CREATE TABLE users_cookies
(
    cookie_id VARCHAR(32)  NOT NULL PRIMARY KEY, /*cookie id: time(secs)+rand(16)*/
    users_id  INT UNSIGNED NOT NULL,

    add_time  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (users_id) REFERENCES users (id)
) ENGINE = InnoDB;


/*Site Settings - special table for misc site settings*/
DROP TABLE IF EXISTS settings;
CREATE TABLE settings
(
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    icat           VARCHAR(64) NOT NULL DEFAULT '', /* settings category: ''-system, 'other' -site specific */
    icode          VARCHAR(64) NOT NULL DEFAULT '', /* settings internal code */
    ivalue         TEXT        NOT NULL DEFAULT '', /* value */

    iname          VARCHAR(64) NOT NULL DEFAULT '', /* settings visible name */
    idesc          TEXT, /* settings visible description */
    input          TINYINT     NOT NULL default 0, /* form input type: 0-input, 10-textarea, 20-select, 21-select multi, 30-checkbox, 40-radio, 50-date */
    allowed_values TEXT, /* space-separated values, use for space, used for: select, select multi, checkbox, radio */

    is_user_edit   TINYINT              DEFAULT 0, /* if 1 - user can edit this value */

    add_time       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    add_users_id   INT UNSIGNED         DEFAULT 0,
    upd_time       DATETIME    NULL ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id   INT UNSIGNED         DEFAULT 0,

    UNIQUE INDEX UX_settings_icode (icode),
    INDEX IX_settings_icat (icat)
) ENGINE = InnoDB;
INSERT INTO settings (is_user_edit, input, icat, icode, ivalue, iname, idesc)
VALUES (1, 10, '', 'test', 'novalue', 'test settings', 'description');

/*Static pages*/
DROP TABLE IF EXISTS spages;
CREATE TABLE spages
(
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id        INT UNSIGNED NOT NULL DEFAULT 0, /*parent page id*/

    url              VARCHAR(255) NOT NULL DEFAULT '', /*sub-url from parent page*/
    iname            VARCHAR(64)  NOT NULL DEFAULT '', /*page name-title*/
    idesc            TEXT, /*page contents, markdown*/
    head_att_id      INT UNSIGNED, /*optional head banner image*/

    idesc_left       TEXT, /*left sidebar content, markdown*/
    idesc_right      TEXT, /*right sidebar content, markdown*/
    meta_keywords    VARCHAR(255) NOT NULL DEFAULT '', /*meta keywords*/
    meta_description VARCHAR(255) NOT NULL DEFAULT '', /*meta description*/

    pub_time         DATETIME, /*publish date-time*/
    template         VARCHAR(64), /*template to use, if not defined - default site template used*/
    prio             INT UNSIGNED NOT NULL DEFAULT 0, /*0-on insert, then =id, default order by prio asc,iname*/
    is_home          INT UNSIGNED          DEFAULT 0, /* 1 is for home page (non-deletable page)*/
    redirect_url     VARCHAR(255) NOT NULL DEFAULT '', /*if set - redirect to this url instead displaying page*/

    custom_css       TEXT, /*custom page css*/
    custom_js        TEXT, /*custom page js*/

    status           TINYINT      NOT NULL DEFAULT 0, /*0-ok, 10-not published, 127-deleted*/
    add_time         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    add_users_id     INT UNSIGNED          DEFAULT 0,
    upd_time         DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id     INT UNSIGNED          DEFAULT 0,

    FOREIGN KEY (head_att_id) REFERENCES att (id),

    INDEX IX_spages_parent_id (parent_id, prio),
    INDEX IX_spages_url (url)
) ENGINE = InnoDB;

-- TRUNCATE TABLE spages; -- This line can be uncommented to clear the table if needed.
INSERT INTO spages (parent_id, url, iname)
VALUES (0, '', 'Home'), -- 1
       (0, 'test-page', 'Test page'); -- 2

UPDATE spages
SET is_home=1
WHERE id = 1;

-- Logs types
DROP TABLE IF EXISTS log_types;
CREATE TABLE log_types
(
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    itype        TINYINT      NOT NULL DEFAULT 0,  -- 0-system, 10-user selectable
    icode        VARCHAR(64)  NOT NULL DEFAULT '', -- added/updated/deleted /comment /simulate/login_fail/login/logoff/chpwd

    iname        VARCHAR(255) NOT NULL DEFAULT '',
    idesc        TEXT,
    prio         INT UNSIGNED NOT NULL DEFAULT 0,  -- 0-on insert, then =id, default order by prio asc,iname

    status       TINYINT      NOT NULL DEFAULT 0,  -- 0-ok, 1-under upload, 127-deleted
    add_time     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    add_users_id INT UNSIGNED          DEFAULT 0,
    upd_time     DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id INT UNSIGNED          DEFAULT 0,

    INDEX IX_log_types_icode (icode)
) ENGINE = InnoDB;

-- Activity Logs
DROP TABLE IF EXISTS activity_logs;
CREATE TABLE activity_logs
(
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reply_id      INT UNSIGNED NULL,                               -- for hierarchy, if needed
    log_types_id  INT UNSIGNED NOT NULL,                           -- log type
    fwentities_id INT UNSIGNED NOT NULL,                           -- related to entity
    item_id       INT UNSIGNED NULL,                               -- related item id in the entity table

    idate         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP, -- default now, but can be different
    users_id      INT UNSIGNED NULL,                               -- default logged user, but can be different if adding "on behalf of"
    idesc         TEXT,
    payload       TEXT,                                            -- serialized/json - arbitrary payload

    status        TINYINT      NOT NULL DEFAULT 0,                 -- 0-active, 10-inactive/hidden, 20-draft, 127-deleted
    add_time      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    add_users_id  INT UNSIGNED          DEFAULT 0,
    upd_time      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id  INT UNSIGNED          DEFAULT 0,

    FOREIGN KEY (log_types_id) REFERENCES log_types (id),
    FOREIGN KEY (fwentities_id) REFERENCES fwentities (id),
    FOREIGN KEY (users_id) REFERENCES users (id),

    INDEX IX_activity_logs_reply_id (reply_id),
    INDEX IX_activity_logs_log_types_id (log_types_id),
    INDEX IX_activity_logs_fwentities_id (fwentities_id),
    INDEX IX_activity_logs_item_id (item_id),
    INDEX IX_activity_logs_idate (idate),
    INDEX IX_activity_logs_users_id (users_id)
) ENGINE = InnoDB;

-- Lookup Manager Tables
DROP TABLE IF EXISTS lookup_manager_tables;
CREATE TABLE lookup_manager_tables
(
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    tname          VARCHAR(255) NOT NULL DEFAULT '', /* table name */
    iname          VARCHAR(255) NOT NULL DEFAULT '', /* human table name */
    idesc          TEXT, /* table internal description */

    is_one_form    TINYINT      NOT NULL DEFAULT 0, /* 1 - lookup table contains one row, use form view */
    is_custom_form TINYINT      NOT NULL DEFAULT 0, /* 1 - use custom form template, named by lowercase(tname) */
    header_text    TEXT, /* text to show in header when editing table */
    footer_text    TEXT, /* text to show in footer when editing table */
    column_id      VARCHAR(255), /* table id column, if empty - use id */

    list_columns   TEXT, /* comma-separated field list to display on list view, if defined - no table edit mode available */
    columns        TEXT, /* comma-separated field list to display, if empty - all fields displayed */
    column_names   TEXT, /* comma-separated column list of column names, if empty - use field name */
    column_types   TEXT, /* comma-separated column list of column types/lookups (" "-string(default),readonly,textarea,checkbox,tname.IDfield:INAMEfield-lookup table), if empty - use standard input[text] */
    column_groups  TEXT, /* comma-separated column list of groups column related to, if empty - don't include column in group */
    url            VARCHAR(255) NOT NULL DEFAULT '', /* if defined - redirected to this URL instead of LookupManager forms */

    access_level   TINYINT, /* min access level, if NULL - use Lookup Manager's acl */

    status         TINYINT      NOT NULL DEFAULT 0, /* 0-ok, 127-deleted */
    add_time       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP, /* date record added */
    add_users_id   INT UNSIGNED          DEFAULT 0, /* user added record */
    upd_time       DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id   INT UNSIGNED          DEFAULT 0,

    UNIQUE INDEX UX_lookup_manager_tables_tname (tname)
) ENGINE = InnoDB;

-- User Custom Views
DROP TABLE IF EXISTS user_views;
CREATE TABLE user_views
(
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    icode        VARCHAR(128) NOT NULL,            -- related screen url, ex: "/Admin/Demos"
    fields       TEXT,                             -- comma-separated list of fields to display, order kept

    iname        VARCHAR(255) NOT NULL DEFAULT '', -- if empty - it's a "default" view
    is_system    TINYINT      NOT NULL DEFAULT 0,  -- 1 - system - visible for all
    is_shared    TINYINT      NOT NULL DEFAULT 0,  -- 1 if shared/published
    density      VARCHAR(16)  NOT NULL DEFAULT '', -- list table density class: table-sm(or empty - default), table-dense, table-normal

    status       TINYINT      NOT NULL DEFAULT 0,
    add_time     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    add_users_id INT UNSIGNED          DEFAULT 0,  -- related user
    upd_time     DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id INT UNSIGNED          DEFAULT 0,

    UNIQUE INDEX UX_user_views (add_users_id, icode, iname)
) ENGINE = InnoDB;

-- User Lists
DROP TABLE IF EXISTS user_lists;
CREATE TABLE user_lists
(
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity       VARCHAR(128) NOT NULL,           -- usually table name or base_url, ex: 'demos' or /Admin/Demos

    iname        VARCHAR(255) NOT NULL,
    idesc        TEXT,                            -- description

    status       TINYINT      NOT NULL DEFAULT 0,
    add_time     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    add_users_id INT UNSIGNED          DEFAULT 0, -- related owner user
    upd_time     DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id INT UNSIGNED          DEFAULT 0,

    INDEX IX_user_lists (add_users_id, entity)
) ENGINE = InnoDB;

-- Items linked to user lists
DROP TABLE IF EXISTS user_lists_items;
CREATE TABLE user_lists_items
(
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_lists_id INT UNSIGNED NOT NULL,
    item_id       INT UNSIGNED NOT NULL,           -- related item id, example demos.id

    status        TINYINT      NOT NULL DEFAULT 0,
    add_time      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    add_users_id  INT UNSIGNED          DEFAULT 0, -- related owner user
    upd_time      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id  INT UNSIGNED          DEFAULT 0,

    FOREIGN KEY (user_lists_id) REFERENCES user_lists (id),

    UNIQUE INDEX UX_user_lists_items (user_lists_id, item_id)
) ENGINE = InnoDB;

-- Custom menu items for sidebar
DROP TABLE IF EXISTS menu_items;
CREATE TABLE menu_items
(
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    iname        VARCHAR(64)  NOT NULL DEFAULT '',
    url          VARCHAR(255) NOT NULL DEFAULT '', -- menu url
    icon         VARCHAR(64)  NOT NULL DEFAULT '', -- menu icon
    controller   VARCHAR(255) NOT NULL DEFAULT '', -- controller class name for UI highlighting
    access_level TINYINT      NOT NULL DEFAULT 0,  -- min access level

    status       TINYINT      NOT NULL DEFAULT 0, /*0-ok, 10-hidden, 127-deleted*/
    add_time     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    add_users_id INT UNSIGNED          DEFAULT 0,
    upd_time     DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id INT UNSIGNED          DEFAULT 0
) ENGINE = InnoDB;
-- INSERT INTO menu_items (iname, url, icon, controller) VALUES ('Test Menu Item', '/Admin/Demos', 'list-ul', 'AdminDemos');

-- User Filters
DROP TABLE IF EXISTS user_filters;
CREATE TABLE user_filters
(
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    icode        VARCHAR(128) NOT NULL,           -- related screen, ex: GLOBAL[controller.action]

    iname        VARCHAR(255) NOT NULL,
    idesc        TEXT,                            -- json with filter data
    is_system    TINYINT      NOT NULL DEFAULT 0, -- 1 - system - visible for all
    is_shared    TINYINT      NOT NULL DEFAULT 0, -- 1 if shared/published

    status       TINYINT               DEFAULT 0,
    add_time     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    add_users_id INT UNSIGNED          DEFAULT 0, -- related owner user
    upd_time     DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id INT UNSIGNED          DEFAULT 0
) ENGINE = InnoDB;

-- run roles.sql if roles support required and also uncomment #define isRoles in Users model

-- after this file - run lookups.sql
