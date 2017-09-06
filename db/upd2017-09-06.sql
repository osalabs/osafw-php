-- update - rename events tables to more system-like name

RENAME TABLE events TO fwevents;
RENAME TABLE event_log TO fwevents_log;