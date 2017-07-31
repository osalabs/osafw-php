-- (c) 2004-2013 oSa
-- FOR MySQL >4.x

-- CREATE DATABASE xxx;

SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION';
-- DROP TABLE IF EXISTS users;
CREATE TABLE IF NOT EXISTS users (
    id                  int unsigned NOT NULL auto_increment,
    access_level        int default 0,               /*General user access level, 0 - customer, 100-site admin*/

    email               varchar(128) NOT NULL default '',
    pwd                 varchar(32) NOT NULL default '',

    title               varchar(8) NOT NULL default '',
    fname               varchar(64) NOT NULL default '',
    lname               varchar(64) NOT NULL default '',
    midname             varchar(64) NOT NULL default '',
    sufname             varchar(64) NOT NULL default '',

    apt                 varchar(8) NOT NULL default '',
    address1            varchar(64) NOT NULL default '',
    address2            varchar(64) NOT NULL default '',
    address3            varchar(64) NOT NULL default '',
    city                varchar(64) NOT NULL default '',
    state               varchar(64) NOT NULL default '',
    zip                 varchar(16) NOT NULL default '',
    country             varchar(4) NOT NULL default '',

    notes               text,
    phone               varchar(16) NOT NULL default '',

    login_time          datetime,               /*Last login time */
    login_ip            char(15),                /*last remote ip*/
    login_host          varchar(128),          /*last login host*/

    status              tinyint default 0,    /*0-ok, 127-deleted*/
    add_time            timestamp default CURRENT_TIMESTAMP,
    add_user_id         int unsigned default 0,
    upd_time            timestamp,
    upd_user_id         int unsigned default 0,

    PRIMARY KEY (id)
) DEFAULT CHARSET=utf8;
insert into users (access_level, email, pwd, fname, lname, add_time)
VALUES (100, 'admin@admin.com', 'CHANGE_ME', 'Website', 'Admin', now());

/*user cookies (for permanent sessions)*/
-- DROP TABLE IF EXISTS user_cookie;
CREATE TABLE IF NOT EXISTS user_cookie (
    cookie_id           varchar(32) NOT NULL,      /*cookie id: time(secs)+rand(16)*/
    users_id            int unsigned NOT NULL,

    add_time            timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (cookie_id),
    KEY user_cookie_ind1 (users_id)
) DEFAULT CHARSET=utf8;


/*Site Settings - special table for misc site settings*/
-- DROP TABLE IF EXISTS settings;
CREATE TABLE IF NOT EXISTS settings (
    id                  int unsigned NOT NULL auto_increment,

    icat                varchar(64) NOT NULL DEFAULT '', /*settings category: ''-system, 'other' -site specific*/
    icode               varchar(64) NOT NULL DEFAULT '', /*settings internal code*/
    ivalue              text, /*value*/

    iname               varchar(64) NOT NULL DEFAULT '', /*settings visible name*/
    idesc               text,                    /*settings visible description*/
    input               tinyint NOT NULL default 0,       /*form input type: 0-input, 10-textarea, 20-select, 21-select multi, 30-checkbox, 40-radio, 50-date*/
    allowed_values      text,                    /*space-separated values, use &nbsp; for space, used for: select, select multi, checkbox, radio*/

    is_user_edit        tinyint DEFAULT 0,  /* if 1 - use can edit this value*/

    add_time            timestamp default CURRENT_TIMESTAMP,
    add_user_id         int unsigned default 0,
    upd_time            timestamp,
    upd_user_id         int unsigned default 0,

    PRIMARY KEY  (id),
    UNIQUE KEY (icode),
    KEY (icat)
) DEFAULT CHARSET=utf8;
INSERT INTO settings (is_user_edit, input, icat, icode, ivalue, iname, idesc) VALUES
(1, 10, '', 'test', 'novalue', 'test settings', 'description');

/* upload categories */
-- DROP TABLE IF EXISTS att_categories;
CREATE TABLE IF NOT EXISTS att_categories (
    id                  int unsigned NOT NULL auto_increment,

    icode               varchar(64) NOT NULL DEFAULT '', /*to use from code*/
    iname               varchar(64) NOT NULL DEFAULT '',
    idesc               text,
    prio                int NOT NULL DEFAULT 0,     /* 0 is normal and lowest priority*/

    status              tinyint default 0,    /*0-ok, 1,2,3, - can be used for record status, 127-deleted*/
    add_time            timestamp default CURRENT_TIMESTAMP,
    add_user_id         int unsigned default 0,
    upd_time            timestamp,
    upd_user_id         int unsigned default 0,
    PRIMARY KEY  (id)
) DEFAULT CHARSET=utf8;
INSERT INTO att_categories (icode, iname) VALUES
('general', 'General images')
,('users', 'Member photos')
,('files', 'Files')
;

-- DROP TABLE IF EXISTS att;
CREATE TABLE IF NOT EXISTS att (
    id                  int unsigned NOT NULL auto_increment, /* files stored on disk under 0/0/0/id.dat */
    att_categories_id   int unsigned NULL,

    table_name          varchar(128) NOT NULL DEFAULT '',
    item_id             int NOT NULL DEFAULT 0,

    is_inline           tinyint DEFAULT 0, /* if uploaded with wysiwyg */
    is_image            tinyint DEFAULT 0, /* 1 if this is supported image */

    fname               varchar(255) NOT NULL DEFAULT '',              /*original file name*/
    fsize               int DEFAULT 0,                   /*file size*/
    ext                 varchar(16) NOT NULL DEFAULT '',                 /*extension*/
    iname               varchar(255) NOT NULL DEFAULT '',   /*attachment name*/

    status              tinyint default 0,    /*0-ok, 1,2,3, - can be used for record status, 127-deleted*/
    add_time            timestamp default CURRENT_TIMESTAMP,
    add_user_id         int unsigned default 0,
    upd_time            timestamp,
    upd_user_id         int unsigned default 0,
    PRIMARY KEY (id),
    FOREIGN KEY (att_categories_id) REFERENCES att_categories(id)
) DEFAULT CHARSET=utf8;
CREATE INDEX att_table_name ON att (table_name, item_id);

/* link att files to table items*/
-- DROP TABLE IF EXISTS att_table_link;
CREATE TABLE IF NOT EXISTS att_table_link (
    id                  int unsigned NOT NULL auto_increment,
    att_id              int unsigned NOT NULL,

    table_name          varchar(128) NOT NULL DEFAULT '',
    item_id             int NOT NULL,

    status              tinyint default 0,    /*0-ok, 1-under update*/
    add_time            timestamp default CURRENT_TIMESTAMP,
    add_user_id         int unsigned default 0,
    PRIMARY KEY (id),
    FOREIGN KEY (att_id) REFERENCES att(id)
) DEFAULT CHARSET=utf8;
CREATE UNIQUE INDEX att_table_link_UX ON att_table_link (table_name, item_id, att_id);

/*Static pages*/
-- DROP TABLE IF EXISTS spages;
CREATE TABLE IF NOT EXISTS spages (
    id                  int unsigned NOT NULL auto_increment,
    parent_id           int unsigned NOT NULL DEFAULT 0,  /*parent page id*/

    url                 varchar(255) NOT NULL DEFAULT '',      /*sub-url from parent page*/
    iname               varchar(64) NOT NULL DEFAULT '',       /*page name-title*/
    idesc               text,                          /*page contents, markdown*/
    head_att_id         int unsigned NULL,                               /*optional head banner image*/

    iname_tagline       varchar(64) NOT NULL DEFAULT '',       /*page tagline*/
    idesc_left          text,                          /*left sidebar content, markdown*/
    idesc_right         text,                          /*right sidebar content, markdown*/
    meta_keywords       varchar(255) NOT NULL DEFAULT '',      /*meta keywords*/
    meta_description    varchar(255) NOT NULL DEFAULT '',      /*meta description*/

    pub_time            datetime,                               /*publish date-time*/
    template            varchar(64),                           /*template to use, if not defined - default site template used*/
    prio                int NOT NULL DEFAULT 0,                 /* 0 is normal and lowest priority*/
    is_home             int DEFAULT 0,                          /* 1 is for home page (non-deletable page*/

    status              tinyint default 0,    /*0-ok, 1,2,3, - can be used for record status, 127-deleted*/
    add_time            timestamp default CURRENT_TIMESTAMP,
    add_user_id         int unsigned default 0,
    upd_time            timestamp,
    upd_user_id         int unsigned default 0,
    PRIMARY KEY (id),
    FOREIGN KEY (head_att_id) REFERENCES att(id)
) DEFAULT CHARSET=utf8;
CREATE INDEX spages_parent_id ON spages (parent_id, prio);
CREATE INDEX spages_url ON spages (url);

--TRUNCATE TABLE spages;
INSERT INTO spages (parent_id, url, iname) VALUES
(0,'','Home') -- 1
,(1,'test','Test sub-home page') -- 2
;
update spages set is_home=1 where id=1;


/*categories*/
-- DROP TABLE IF EXISTS categories;
CREATE TABLE IF NOT EXISTS categories (
    id                  int unsigned NOT NULL auto_increment,
    parent_id           int unsigned NOT NULL default 0,

    iname               varchar(128) NOT NULL default '',
    idesc               text,
    prio                tinyint unsigned NOT NULL default 0,/* 0 is normal and lowest priority*/

    status              tinyint default 0,        /*0-ok, 127-deleted*/
    add_time            timestamp DEFAULT CURRENT_TIMESTAMP,                 /*date record added*/
    add_user_id         int unsigned default 0,            /*user added record*/
    upd_time            timestamp,                 /*date record updated*/
    upd_user_id         int unsigned default 0,            /*user added record*/

    PRIMARY KEY (id),
    KEY (parent_id),
    KEY (prio, iname)
) DEFAULT CHARSET=utf8;
INSERT INTO categories (iname) VALUES
('category1')
,('category2')
,('category3')
;

/*event types for log*/
-- DROP TABLE IF EXISTS events;
CREATE TABLE IF NOT EXISTS events (
    id                  int unsigned NOT NULL auto_increment,
    icode               varchar(64) NOT NULL default '',

    iname               varchar(255) NOT NULL default '',
    idesc               text,

    status              tinyint default 0,        /*0-ok, 127-deleted*/
    add_time            timestamp DEFAULT CURRENT_TIMESTAMP,                 /*date record added*/
    add_user_id         int unsigned default 0,            /*user added record*/
    upd_time            timestamp,                 /*date record updated*/
    upd_user_id         int unsigned default 0,            /*user added record*/

    PRIMARY KEY (id),
    KEY (icode)
) DEFAULT CHARSET=utf8;
INSERT INTO events (icode, iname) VALUES ('login',    'User login');
INSERT INTO events (icode, iname) VALUES ('logoff',   'User logoff');
INSERT INTO events (icode, iname) VALUES ('chpwd',    'User changed login/pwd');
INSERT INTO events (icode, iname) VALUES ('users_add',    'New user added');
INSERT INTO events (icode, iname) VALUES ('users_upd',    'User updated');
INSERT INTO events (icode, iname) VALUES ('users_del',    'User deleted');

/* log of all user-initiated events */
-- DROP TABLE IF EXISTS event_log;
CREATE TABLE IF NOT EXISTS event_log (
    id                  bigint unsigned NOT NULL auto_increment,
    events_id           int unsigned NOT NULL DEFAULT 0,           /* event type */

    item_id             int NOT NULL DEFAULT 0,           /*related id*/
    item_id2            int NOT NULL DEFAULT 0,           /*related id (if another)*/

    iname               varchar(255) NOT NULL DEFAULT '', /*short description of what's happened or additional data*/

    records_affected    int NOT NULL DEFAULT 0,

    fields              text,       /*serialized json with related fields data (for history) in form {fieldname: data, fieldname: data}*/

    add_time            timestamp DEFAULT CURRENT_TIMESTAMP,                 /*date record added*/
    add_user_id         int unsigned default 0,            /*user added record*/

    PRIMARY KEY (id),
    KEY (events_id),
    KEY (add_user_id),
    KEY (add_time)
) DEFAULT CHARSET=utf8;
