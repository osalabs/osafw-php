-- update - increase size for hashed passwords

ALTER TABLE users MODIFY pwd varchar(255) NOT NULL default '';
