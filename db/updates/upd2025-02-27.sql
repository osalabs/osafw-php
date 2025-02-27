-- oauth scopes support
ALTER TABLE users ADD COLUMN oauth_scopes TEXT after id;

