# PHP web framework optimized for building Business Applications

Created as simplified and lightweight alternative to other PHP frameworks

![image](https://user-images.githubusercontent.com/1141095/75820467-0200b380-5d62-11ea-9340-e0942b460eb1.png)

## Features
- simple and straightforward in development and maintenance
- MVC-like
  - code, data, templates are split
  - code consists of: controllers, models, framework core and optional 3rd party libs
  - uses [ParsePage template engine](https://github.com/osalabs/parsepage)
  - data stored by default in MySQL database [using db.php](https://github.com/osalabs/db.php)
- RESTful with some practical enhancements
- integrated auth - simple flat access levels auth
- UI based on [Bootstrap 5](http://getbootstrap.com) with minimal custom CSS and themes support - it's easy to customzie or apply your own theme
- use of well-known 3rd party libraries: [jQuery](http://jquery.com), [jQuery Form](https://github.com/malsup/form), jGrowl, markdown libs, etc...

## Documentation

### Development/Deployment
1. put contents of `/www` into your webserver's public html folder
2. edit `/www/php/config.site.php` (or `config.localhost.php` for development)
3. create database from `/db/fwdatabase.sql`, `/db/lookups.sql` and others (if needed)
4. open site in your browser and login with credentials as defined in fwdatabase.sql
5. review log in `/logs/osafw.log`

### Directory structure
```
/db                  - initial fwdatabase.sql script and update sql scripts
/logs/osafw.log      - application log (ensure to enable write rights to /logs dir for webserver)
/www                 - application public root folder
  /php               - all the PHP code is here
    /controllers     - your controllers
    /fw              - framework core libs
    /models          - your models
    /vendor          - composer libs
    /config.*.php    - settings for db connection, mail, logging...
  /template          - all the html templates
  /upload            - upload dir for public files
  /assets            - your web frontend assets
    /css
    /fonts
    /img
    /js
  /favicon.ico       - change to your favicon!
  /robots.txt        - default robots.txt (empty)
```

### REST mappings
Controllers automatically directly mapped to URLs, so developer doesn't need to write routing rules:

  - `GET /Controller` - list view `IndexAction()`
  - `GET /Controller/ID` - one record view `ShowAction()`
  - `GET /Controller/new` - one record new form `ShowFormAction()`
  - `GET /Controller/ID/edit` - one record edit form `ShowFormAction()`
  - `GET /Controller/ID/delete` - one record delete confirmation form `ShowDeleteAction()`
  - `POST /Controller` - insert new record `SaveAction()`
  - `PUT /Controller` - update multiple records `SaveMultiAction()`
  - `POST/PUT /Controller/ID` - update record `SaveAction()`
  - `POST/DELETE /Controller/ID` - delete record (POST body should be empty) `DeleteAction()`
  - `GET/POST /Controller/(Something)[/ID]` - call for arbitrary action from the controller `SomethingAction()`

For example `GET /Products` will call `ProductsController.IndexAction()`
And this will cause rendering templates from `/www/template/products/index`

### Request Flow

highlighted as bold is where you could place your code.

- `FW.run()`
  - **`FwHooks.initRequest()`** - place code here which need to be run on request start
- `fw.dispatch()` - performs REST urls matching and calls controller/action, if no controller found calls `HomeController.NotFoundAction()`, if no requested action found in controller - calls controller action defined in contoller's `route_default_action` (either "index" or "show")
  - `fw._auth()`  - check if user can access requested controller/action, also performs basic CSRF validation
  - `fw.call_controller()`
    - **`SomeController.init()`** - place code here which need to be run every time request comes to this controller
    - **`SomeController.SomeAction()`** - your code for particular action
      - **`SomeModel.someMethod()`** - controllers may call model's methods, place most of your business logic in models
- `fw.Finalize()`

#### Examples:
- GET /Admin/Users
  - `FwHooks.initRequest()`
  - `AdminUsers.init()`
  - `AdminUsers.IndexAction()`
  - then ParsePage parses templates from `/template/admin/users/index/`

- GET /Admin/Users/123/edit
  - `FwHooks.initRequest()`
  - `AdminUsers.init()`
  - `AdminUsers.ShowFormAction(123)`
    - `Users.one(123)`
  - then ParsePage parses templates from `/template/admin/users/showform/`

- POST /Admin/Users/123
  - `FwHooks.initRequest()`
  - `AdminUsers.init()`
  - `AdminUsers.SaveAction(123)`
    - `Users.update(123)`
  - `fw.redirect("/Admin/Users/123/edit")` //redirect back to edit screen after db updated

- GET /Admin/Users/(Custom)/123?param1=1&param2=ABC - controller's custom action (non-standard REST)
  - `FwHooks.initRequest()`
  - `AdminUsers.init()`
  - `AdminUsers.CustomAction(123)` - here you can get params using `reqi("param1") -> 1` and `reqs("params") -> "ABC"`
  - then ParsePage parses templates from `/template/admin/users/custom/` unless you redirect somewhere else

- POST /Admin/Users/(Custom)/123 with posted params `param1=1` and `param2=ABC`
  - `FwHooks.initRequest()`
  - `AdminUsers.init()`
  - `AdminUsers.CustomAction(123)` - here you can still get params using `reqi("param1") -> 1` and `reqs("params") -> "ABC"`
  - then ParsePage parses templates from `/template/admin/users/custom/` unless you redirect somewhere else

#### Flow in IndexAction

Frequently asked details about flow for the `IndexAction()` (in controllers inherited from `FwAdminController` and `FwDynamicController`):

1. `initFilter()` - initializes `this.list_filter` from query string filter params `&f[xxx]=...`, note, filters remembered in session
1. `setListSorting()` - initializes `this.list_orderby` based on `list_filter("sortby")` and `list_filter("sortdir")`, also uses `this.list_sortdef` and `this.list_sortmap` which can be set in controller's `init()` or in `config.json`
1. `setListSearch()` - initializes `this.list_where` based on `list_filter("s")` and `this.search_fields`
1. `setListSearchStatus()` - add to `this.list_where` filtering  by `status` field if such field defined in the controller's model
1. `getListRows()` - query database and save rows to `this.list_rows` (only current page based on `this.list_filter("pagenum")` and `this.list_filter("pagesize")`). Also sets `this.list_count` to total rows matched by filters and `this.list_pager` for pagination if there are more than one page. Uses `this.list_view`, `this.list_where`, `this.list_orderby`

You could either override these particular methods or whole `IndexAction()` in your specific controller.

The following controller fields used above can be defined in controller's `init()` or in `config.json`:
- `this.list_sortdef` - default list sorting in format: "sort_name[ asc|desc]"
- `this.list_sortmap` - mapping for sort names (from `list_filter["sortby"]`) to actual db fields, Hashtable `sort_name => db_field_name`
- `this.search_fields` - search fields, space-separated
- `this.list_view` - table/view to use in `getListRows()`, if empty model's `table_name` used

### fw.config

Application configuration available via `fw.config->[SettingName]`.
Most of the global settings defined in `config.*.php`. But there are several caclulated settings:

|SettingName|Description|Example|
|-------|-----------|-------|
|hostname|set from server variable HTTP_HOST|osalabs.com|
|ROOT_DOMAIN|protocol+hostname|https://osalabs.com|
|ROOT_URL|part of the url if Application installed under sub-url|/suburl if App installed under osalabs.com/suburl|
|site_root|physical application path to the root of public directory|C:\inetpub\somesite\www|
|template|physical path to the root of templates directory|C:\inetpub\somesite\www\template|
|log|physical path to application log file|C:\inetpub\somesite\logs\osafw.log|
|tmp|physical path to the system tmp directory|C:\Windows\Temp|

### config.json

In `FwDynamicController` controller behaviour defined by `/template/CONTROLLER/config.json`. Sample file can be fount at `/template/admin/demosdynamic/config.json`
This config file allows to define/override several properties of the `FwController` (for example: as `model`, `save_fields`, `search_fields`, `list_view`,...) as well as define configuration of Show (`show_fields`) and ShowForm (`showform_fields`)  screens. Note `is_dynamic_show` and `is_dynamic_showform` should be set to true accordingly.
There are samples for the one `show_fields` or `showform_fields` element:

```json
  //minimal setup to display the field value
  {
      "type": "plaintext",
      "field": "iname",
      "label": "Title"
  },
```
Renders:
```html
<div class="form-row">
  <label class="col-form-label">Title</label>
  <div class="col">
    <p class="form-control-plaintext">FIELD_VALUE</p>
  </div>
</div>
```

```json
  //more complex - displays dropdown with values from lookup model
  {
      "type": "select",
      "field": "demo_dicts_id",
      "label": "DemoDicts",
      "lookup_model": "DemoDicts",
      "is_option0": true,
      "class_contents": "col-md-3",
      "class_control": "on-refresh"
  },
```
Renders:
```html
<div class="form-row">
  <label class="col-form-label">DemoDicts</label>
  <div class="col-md-3">
    <select id="demo_dicts_id" name="item[demo_dicts_id]" class="form-control on-refresh">
      <option value="0">- select -</option>
      ... select options from lookup here...
    </select>
  </div>
</div>
```

|Field name|Description|Example|
|---|---|---|
|type|required, Element type, see values in table below|select - renders as `<select>` html|
|field|Field name from database.table or arbitrary name for non-db block|demo_dicts_id - in case of select id value won't be displayed, but used to select active list element|
|label|Label text|Demo Dictionary|
|lookup_model|Model name where to read lookup values|DemoDicts|
|is_option0|only for "select" type, if true - includes `<option value="0">option0_title</option>`|false(default),true|
|is_option_empty|only for "select" type, if true - includes `<option value="">option0_title</option>`|false(default),true|
|option0_title|only for "select" type for is_option0 or is_option_empty option title|"- select -"(default)|
|required|make field required (both client and server-side validation), for `showform_fields` only|false(default),true|
|maxlength|set input's maxlength attribute, for `showform_fields` only|10|
|max|set input type="number" max attribute, for `showform_fields` only|999|
|min|set input type="number" min attribute, for `showform_fields` only|0|
|step|set input type="number" step attribute, for `showform_fields` only|0.1|
|placeholder|set input's maxlength attribute, for `showform_fields` only|"Enter value here"|
|autocomplete_url|type="autocomplete". Input will get data from `autocomplete_url?q=%QUERY` where %QUERY will be replaced with input value, for `showform_fields` only|/Admin/SomeLookup/(Autocompete)|
|is_inline|type `radio` or `yesno`. If true - place all options in one line, for `showform_fields` only|true(default),false|
|rows|set textarea rows attribute, for `showform_fields` only|5|
|class|Class(es) added to the wrapping `div.form-row` |mb-2 - add bottom margin under the control block|
|attrs|Arbitrary html attributes for the wrapping `div.form-row`|data-something="123"|
|class_label|Class(es) added to the `label.col-form-label` |col-md-3(default) - set label width|
|class_contents|Class(es) added to the `div` that wraps input control |col(default) - set control width|
|class_control|Class(es) added to the input control to change appearance/behaviour|"on-refresh" - forms refreshes(re-submits) when input changed|
|attrs_control|Arbitrary html attributes for the input control|data-something="123"|
|help_text|Help text displayed as muted text under control block|"Minimum 8 letters and digits required"|
|admin_url|For type="plaintext_link", controller url, final URL will be: "<~admin_url>/<~lookup_id>"|/Admin/SomeController|
|lookup_id|to use with admin_url, if link to specific ID required|123|
|att_category|For type="att_edit", att category new upload will be related to|"general"(default)|
|validate|Simple validation codes: exists, isemail, isphone, isdate, isfloat|"exists isemail" - input value validated if such value already exists, validate if value is an email|

##### type values

|Type|Description|
|---|---|
|_available for both show_fields and showform_fields_||
|plaintext|Plain text|
|plaintext_link|Plain text with a link to "admin_url"|
|markdown|Markdown text (server-side rendered)|
|noescape|Value without htmlescape|
|float|Value formatted with 2 decimal digits|
|checkbox|Read-only checkbox (checked if value equal to true value)|
|date|Date in default format - M/d/yyyy|
|date_long|Date in logn forma - M/d/yyyy hh:mm:ss|
|multi|Multi-selection list with checkboxes (read-only)|
|att|Block for displaying one attachment/file|
|att_links|Block for displaying multiple attachments/files|
|added|Added on date/user block|
|updated|Updated on date/user block|
|_available only showform_fields_||
|group_id|ID with Submit/Cancel buttons block|
|group_id_addnew|ID with Submit/Submit and Add New/Cancel buttons block|
|select|select with options html block|
|input|input type="text" html block|
|textarea|textaread html block|
|email|input type="email" html block|
|number|input type="number" html block|
|autocomplete|input type="text" with autocomplete using "autocomplete_url"|
|multicb|Multi-selection list with checkboxes|
|radio|radio options block|
|yesno|radio options block with Yes(1)/No(2) only|
|cb|single checkbox block|
|date_popup|date selection input with popup calendar block|
|att_edit|Block for selection/upload one attachment/file|
|att_links_edit|Block for selection/upload multiple attachments/files|

### How to Debug

Main and recommended approach - use `logger()` function, which is globally available.
Examples: `logger("some string to log", var_to_dump)`, `logger("WARN", "warning message")`
All logged messages and var content (complex objects will be dumped wit structure when possible) written on debug console as well as to log file (default `/logs/osafw.log`)
You could configure log level in your `config.*.php` - search "LOG_LEVEL"

Another debug functions that might be helpful are:
1. `rw($var)` this function will work like var_dump and just dump variable structure and data to browser (with some formatting)
2. `rwe($var)` same as above, but immediately die to stop script

### Best Practices / Recommendations
- naming conventions:
  - table name: `user_lists` (lowercase, underscore delimiters is optional)
  - model name: `UserLists` (UpperCamelCase)
  - controller name: `UserListsController` or `AdminUserListsController` (UpperCamelCase with "Controller" suffix)
  - template path: `/template/userlists`
- keep all paths without trailing slash, use beginning slash where necessary
- db updates:
  - first, make changes in `/db/fwdatabase.sql` - this file is used to create db from scratch
  - then create a file `/db/updates/updYYYY-MM-DD[-123].sql` with all the CREATE, ALTER, UPDATE... - this will allow to apply just this update to existing database instances
- use `fw.route_redirect()` if you got request to one Controller.Action, but need to continue processing in another Controller.Action
  - for example, if for a logged user you need to show detailed data and always skip list view - in the `IndexAction()` just use `fw.routeRedirect("ShowForm")`
- uploads
  - save all public-readable uploads under `/www/upload` (default, see "UPLOAD_DIR" in `config.*.php`)
  - for non-public uploads use `/upload`
  - or `S3` model and upload to the cloud
- put all validation code into controller's `Validate()`. See usage example in `AdminDemosController`
- use `logger()` and review `/logs/osafw.log` if you stuck
  - make sure you have "LOG_LEVEL" set to "DEBUG" in your `config.*.php`

### How to quickly create a Report
- all reports accessed via `AdminReportsController`
  - `IndexAction` - shows a list of all available reports (basically renders static html template with a link to specific reports)
  - `ShowAction` - based on passed report code calls related Report model
- base report model is `FwReports`, major methods (you may override in the specific report):
  - `getReportFilters()` - set data for the report filters
  - `getReportData()` - returns report data, usually based on some sql query (see Sample report)
- `ReportSample` model (in `\www\php\models\Reports` folder) is a sample report implementation, that can be used as a template to build custom reports
- basic steps to create a new report:
  - copy `\www\php\models\Reports\Sample.php` to `\www\php\models\Reports\Cool.php` (to create Cool report)
  - edit `Cool.php` and rename "Sample" to "Cool"
  - modify `getReportFilters()` to match your report filters
  - modify `getReportData()` to edit sql query and related post-processing
  - copy templates folder `\www\template\reports\sample` to `\www\template\reports\cool`
  - edit templates:
    - `title.html` - report title
    - `list_filter.html` - for filters
    - `report_html.html` - for report table/layout/appearance
  - add link to a new report to `\www\template\reports\index\main.html`
