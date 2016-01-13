/*Demo table*/
DROP TABLE IF EXISTS demos;
CREATE TABLE demos (
    id                  int unsigned NOT NULL auto_increment,
    parent_id           int unsigned NOT NULL default 0,           /*parent id - combo selection from SQL*/
    demo_dicts_id       int unsigned NOT NULL default 0,           /* demo dictionary link*/

    iname               varchar(64) NOT NULL default '',      /*string value for names*/
    idesc               text,                                 /*large text value*/

    email               varchar(128) NOT NULL default '',     /*string value for unique field, such as email*/

    fint                int NOT NULL default 0,       /*accept only int*/
    ffloat              float NOT NULL default 0,   /*accept float digital values*/

    dict_link_auto_id   int NOT NULL default 0,           /*index of autocomplete field - linked to demo_dicts*/
    dict_link_multi     varchar(255) NOT NULL DEFAULT '',    /*multiple select values, link to demo_dicts*/

    fcombo              int unsigned NOT NULL default 0,     /*index of combo selection*/
    fradio              int unsigned NOT NULL default 0,     /*index of radio selection*/
    fyesno              tinyint unsigned NOT NULL default 0,     /*yes/no field 0 - NO, 1 - YES*/
    is_checkbox         tinyint unsigned NOT NULL default 0,     /*checkbox field 0 - NO, 1 - YES*/

    fdate_combo date,                  /*date field with 3 combos editing*/
    fdate_pop date,                    /*date field with popup editing*/
    fdatetime datetime,                /*date+time field*/
    ftime int unsigned NOT NULL default 0,      /*time field - we always store time as seconds from start of the day [0-86400]*/

    att_id              int unsigned NULL,    /*optional attached image*/

    status              tinyint default 0,    /*0-ok, 127-deleted*/
    add_time            timestamp default CURRENT_TIMESTAMP,
    add_user_id         int unsigned default 0,
    upd_time            timestamp,
    upd_user_id         int unsigned default 0,

    PRIMARY KEY (id),
    UNIQUE KEY (email)
) DEFAULT CHARSET=utf8;


/*Demo Dictionary table*/
DROP TABLE IF EXISTS demo_dicts;
CREATE TABLE demo_dicts (
    id                  int unsigned NOT NULL auto_increment,

    iname               varchar(64) NOT NULL default '',      /*string value for names*/
    idesc               text,                                 /*large text value*/

    status              tinyint default 0,    /*0-ok, 127-deleted*/
    add_time            timestamp default CURRENT_TIMESTAMP,
    add_user_id         int unsigned default 0,
    upd_time            timestamp,
    upd_user_id         int unsigned default 0,

    PRIMARY KEY (id),
    UNIQUE KEY (iname)
) DEFAULT CHARSET=utf8;
INSERT INTO demo_dicts (iname, idesc, add_time) VALUES
('test1', 'test1 description', now())
,('test2', 'test2 description', now())
,('test3', 'test3 description', now())
;
