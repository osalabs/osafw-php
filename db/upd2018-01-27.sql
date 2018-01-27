-- update - increase size of passwords

ALTER TABLE users MODIFY pwd varchar(64) NOT NULL default '';
