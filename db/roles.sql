-- tables for ROLE BASED ACCESS CONTROL (optional)
/*Resources*/
DROP TABLE IF EXISTS resources;
CREATE TABLE resources
(
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    icode        VARCHAR(64)  NOT NULL,
    iname        VARCHAR(255) NOT NULL DEFAULT '',
    idesc        TEXT,
    prio         INT UNSIGNED NOT NULL DEFAULT 0,
    status       TINYINT      NOT NULL DEFAULT 0,
    add_time     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    add_users_id INT UNSIGNED          DEFAULT 0,
    upd_time     DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id INT UNSIGNED          DEFAULT 0
) ENGINE = InnoDB;

/*Permissions*/
DROP TABLE IF EXISTS permissions;
CREATE TABLE permissions
(
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resources_id INT UNSIGNED,
    icode        VARCHAR(64)  NOT NULL,
    iname        VARCHAR(255) NOT NULL DEFAULT '',
    idesc        TEXT,
    prio         INT UNSIGNED NOT NULL DEFAULT 0,
    status       TINYINT      NOT NULL DEFAULT 0,
    add_time     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    add_users_id INT UNSIGNED          DEFAULT 0,
    upd_time     DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id INT UNSIGNED          DEFAULT 0,

    FOREIGN KEY (resources_id) REFERENCES resources (id)
) ENGINE = InnoDB;

/*Roles*/
DROP TABLE IF EXISTS roles;
CREATE TABLE roles
(
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    iname        VARCHAR(255) NOT NULL DEFAULT '',
    idesc        TEXT,
    prio         INT UNSIGNED NOT NULL DEFAULT 0,
    status       TINYINT      NOT NULL DEFAULT 0,
    add_time     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    add_users_id INT UNSIGNED          DEFAULT 0,
    upd_time     DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id INT UNSIGNED          DEFAULT 0
) ENGINE = InnoDB;

/*Assigned permissions for all roles and resource/permissions*/
DROP TABLE IF EXISTS roles_resources_permissions;
CREATE TABLE roles_resources_permissions
(
    roles_id       INT UNSIGNED NOT NULL,
    resources_id   INT UNSIGNED NOT NULL,
    permissions_id INT UNSIGNED NOT NULL,
    status         TINYINT      NOT NULL DEFAULT 0,
    add_time       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    add_users_id   INT UNSIGNED          DEFAULT 0,
    upd_time       DATETIME,
    upd_users_id   INT UNSIGNED          DEFAULT 0,

    PRIMARY KEY (roles_id, resources_id, permissions_id),
    FOREIGN KEY (roles_id) REFERENCES roles (id),
    FOREIGN KEY (resources_id) REFERENCES resources (id),
    FOREIGN KEY (permissions_id) REFERENCES permissions (id)
) ENGINE = InnoDB;

/*Roles for all users*/
DROP TABLE IF EXISTS users_roles;
CREATE TABLE users_roles
(
    users_id     INT UNSIGNED NOT NULL,
    roles_id     INT UNSIGNED NOT NULL,
    status       TINYINT      NOT NULL DEFAULT 0,
    add_time     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    add_users_id INT UNSIGNED          DEFAULT 0,
    upd_time     DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id INT UNSIGNED          DEFAULT 0,

    PRIMARY KEY (users_id, roles_id),
    FOREIGN KEY (users_id) REFERENCES users (id),
    FOREIGN KEY (roles_id) REFERENCES roles (id)
) ENGINE = InnoDB;


-- fill tables

insert into lookup_manager_tables (tname, iname, url, access_level)
VALUES ('permissions', 'Permissions', '', 100)
     , ('resources', 'Resources', '', 100)
     , ('roles', 'Roles', '/Admin/Roles', 100)
;

-- default Permissions
INSERT INTO permissions (icode, iname)
VALUES ('list', 'List'); -- IndexAction
INSERT INTO permissions (icode, iname)
VALUES ('view', 'View'); -- ShowAction, ShowFormAction
INSERT INTO permissions (icode, iname)
VALUES ('add', 'Add'); -- SaveAction(id=0)
INSERT INTO permissions (icode, iname)
VALUES ('edit', 'Edit'); -- SaveAction
INSERT INTO permissions (icode, iname)
VALUES ('del', 'Delete');
-- ShowDeleteAction, DeleteAction
-- INSERT INTO permissions (icode, iname) VALUES ('del_perm', 'Permanently Delete'); -- DeleteAction with permanent TODO do we need this?
UPDATE permissions
SET prio=id;
;

-- default Roles
INSERT INTO roles (iname)
VALUES ('Admin');
INSERT INTO roles (iname)
VALUES ('Manager');
INSERT INTO roles (iname)
VALUES ('Employee');
INSERT INTO roles (iname)
VALUES ('Customer Service');
INSERT INTO roles (iname)
VALUES ('Vendor');
INSERT INTO roles (iname)
VALUES ('Customer');
;

-- default Resources
INSERT INTO resources (icode, iname)
VALUES ('Main', 'Main Dashboard');
INSERT INTO resources (icode, iname)
VALUES ('AdminReports', 'Reports');
-- optional demo
INSERT INTO resources (icode, iname)
VALUES ('AdminDemos', 'Demo');
INSERT INTO resources (icode, iname)
VALUES ('AdminDemosDynamic', 'Demo Dynamic');
INSERT INTO resources (icode, iname)
VALUES ('AdminDemoDicts', 'Demo Dict');
-- optional demo end
INSERT INTO resources (icode, iname)
VALUES ('AdminSpages', 'Pages');
INSERT INTO resources (icode, iname)
VALUES ('AdminAtt', 'Manage Uploads');
INSERT INTO resources (icode, iname)
VALUES ('AdminLookupManager', 'Lookup Manager');
INSERT INTO resources (icode, iname)
VALUES ('AdminLookupManagerTables', 'Lookup Manager Table Definitions');
INSERT INTO resources (icode, iname)
VALUES ('AdminRoles', 'Manage Roles');
INSERT INTO resources (icode, iname)
VALUES ('AdminUsers', 'Manage Members');
INSERT INTO resources (icode, iname)
VALUES ('AdminSettings', 'Site Settings');
INSERT INTO resources (icode, iname)
VALUES ('MyViews', 'My Views');
INSERT INTO resources (icode, iname)
VALUES ('MyLists', 'My Lists');
INSERT INTO resources (icode, iname)
VALUES ('MySettings', 'My Profile');
INSERT INTO resources (icode, iname)
VALUES ('MyPassword', 'Change Password');
UPDATE resources
SET prio=id;
;
