-- update - rename add_user_id and upd_user_id to *users_id for consistency

ALTER TABLE users CHANGE COLUMN add_user_id add_users_id int unsigned default 0;
ALTER TABLE users CHANGE COLUMN upd_user_id upd_users_id int unsigned default 0;

ALTER TABLE settings CHANGE COLUMN add_user_id add_users_id int unsigned default 0;
ALTER TABLE settings CHANGE COLUMN upd_user_id upd_users_id int unsigned default 0;

ALTER TABLE att_categories CHANGE COLUMN add_user_id add_users_id int unsigned default 0;
ALTER TABLE att_categories CHANGE COLUMN upd_user_id upd_users_id int unsigned default 0;

ALTER TABLE att CHANGE COLUMN add_user_id add_users_id int unsigned default 0;
ALTER TABLE att CHANGE COLUMN upd_user_id upd_users_id int unsigned default 0;

ALTER TABLE att_table_link CHANGE COLUMN add_user_id add_users_id int unsigned default 0;

ALTER TABLE spages CHANGE COLUMN add_user_id add_users_id int unsigned default 0;
ALTER TABLE spages CHANGE COLUMN upd_user_id upd_users_id int unsigned default 0;

ALTER TABLE categories CHANGE COLUMN add_user_id add_users_id int unsigned default 0;
ALTER TABLE categories CHANGE COLUMN upd_user_id upd_users_id int unsigned default 0;

ALTER TABLE events CHANGE COLUMN add_user_id add_users_id int unsigned default 0;
ALTER TABLE events CHANGE COLUMN upd_user_id upd_users_id int unsigned default 0;

ALTER TABLE event_log CHANGE COLUMN add_user_id add_users_id int unsigned default 0;

ALTER TABLE demos CHANGE COLUMN add_user_id add_users_id int unsigned default 0;
ALTER TABLE demos CHANGE COLUMN upd_user_id upd_users_id int unsigned default 0;

ALTER TABLE demo_dicts CHANGE COLUMN add_user_id add_users_id int unsigned default 0;
ALTER TABLE demo_dicts CHANGE COLUMN upd_user_id upd_users_id int unsigned default 0;
