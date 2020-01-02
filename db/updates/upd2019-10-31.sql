-- Custom menu items for sidebar

CREATE TABLE menu_items (
    id                  int unsigned NOT NULL auto_increment,

    iname               varchar(64) NOT NULL default '',
    url                 varchar(255) NOT NULL default '',  -- menu url
    icon                varchar(64) NOT NULL default '',   -- menu icon
    controller          varchar(255) NOT NULL default '',  -- controller name for highlighting
    access_level        tinyint NOT NULL default 0,         -- min access level

    status              tinyint default 0,    /*0-ok, 127-deleted*/
    add_time            timestamp default CURRENT_TIMESTAMP,
    add_users_id        int unsigned default 0,
    upd_time            timestamp,
    upd_users_id        int unsigned default 0,

    PRIMARY KEY (id)
) DEFAULT CHARSET=utf8mb4;
