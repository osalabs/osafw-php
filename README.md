# osafw-php
PHP web framework

## Features

- simple and straight in develpment/maintenance
- **MVC-like** code, data, templates are split
  - code consists of: controllers, models, framework core and optional 3rd party libs
  - uses [ParsePage template engine](https://github.com/osalabs/parsepage)
  - data stored by default in MySQL database [using db.php](https://github.com/osalabs/db.php)
- **RESTful** with some practical enhancements
  - `GET /Controller` - list view
  - `GET /Controller/ID` - one record view
  - `GET /Controller/ID/new` - one record new form 
  - `GET /Controller/ID/edit` - one record edit form 
  - `GET /Controller/ID/delete` - one record delete confirmation form 
  - `POST /Controller` - insert new record
  - `PUT /Controller` - update multiple records
  - `POST/PUT /Controller/ID` - update record
  - `POST/DELETE /Controller/ID` - delete record ($_POST should be empty)
  - `GET/POST /Controller/(Action)[/ID]` - call for arbitrary action from the controller
- integrated auth - simple flat access levels auth
- use of well-known 3rd party libraries
  - [jQuery](http://jquery.com)
  - [Twitter Bootstrap 3](http://getbootstrap.com)
  - [jQuery Form](https://github.com/malsup/form)
  - jGrowl
  - markdown libs
  - others... (TODO)
  
## Demo

TODO

## Installation

1. put contents of `/www` into your webserver's public html folder
2. edit `/www/php/config.site.php` (or `config.develop.php`)
3. create database from `/db/database.sql`
4. open site in your browser and login with credentials as defined in database.sql

Automated install via Composer - TBD

## Documentation

### debugging

Debugging is much easier with these 3 globally available functions:

1. `rw($var)` this function will work like var_dump and just dump variable structure and data to browser (with some formatting)

2. `rwe($var)` same as above, but immediately die to stop script

3. `logger($var)` this will dump variable to a log file, defined in config `site_error_log` param. Error log created automatically with first call to logger().

Check your error.log file. It should be one level up from /www by default.
Better to use `logger()` than `rw()` as it writes everything to file, not to browser/screen, keeping UI

`$SITE_CONFIG` (defined in config files) contains `IS_DEBUG` parameter. If it's false - `logger()` will not write anything. Usually you want to set `IS_DEBUG` to `false` on production site and `true` for development/test.
