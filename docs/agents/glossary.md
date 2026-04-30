# Glossary

- `FwController` - base controller for web/admin flows.
- `FwAdminController` - standard list/form admin controller base.
- `FwApiController` - API controller base with auth, CORS/header, and JSON response conventions.
- `FwModel` - base model with CRUD helpers, standard status fields, JSON filtering, caching, and event logging hooks.
- `ParsePage` - template engine used by osafw-php templates.
- `config.json` - dynamic-controller template config file that defines model, list, show, and showform behavior.
- `_json` - controller return key used by the framework to serialize an API payload.
- `icode` - public/code-style identifier field used by framework models alongside numeric `id`.
- `list_filter` - controller state initialized from query-string filters and used for list/search/pagination behavior.
