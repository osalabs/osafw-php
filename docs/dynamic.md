# Dynamic Controllers

`FwDynamicController` is the framework's config-driven path for admin CRUD screens. It is best for conventional management screens: list rows, show one record, edit one record, and render fields consistently.

## When to Use It

Use `FwDynamicController` when:

- the screen is mostly field configuration,
- list/search/sort behavior is conventional,
- show and edit screens can be described as field arrays,
- shared admin/list/form templates cover most markup.

Prefer a manual `FwAdminController` when the screen is workflow-heavy or the save flow is more custom than configuration can express.

## Minimal Controller

```php
<?php

class AdminDemosDynamicController extends FwDynamicController {
    const int access_level = Users::ACL_MANAGER;

    public FwModel|Demos $model;
    public string $model_name = 'Demos';
    public string $base_url = '/Admin/DemosDynamic';

    public function __construct() {
        parent::__construct();

        $this->loadControllerConfig();
        $this->model_related = DemoDicts::i();
    }
}
```

Set `base_url`, set the model, load config, and add explicit overrides only when needed.

## Configuration Location

The controller loads `config.json` from the template base directory:

```text
template/admin/demosdynamic/config.json
```

That file is the main contract for list behavior, save fields, validation, show fields, showform fields, tabs, and custom rendering.

## Core Config Keys

| Key | Purpose |
| --- | --- |
| `model` | Model class name |
| `required_fields` | Required field list |
| `save_fields` | Allowed fields to save |
| `save_fields_checkboxes` | Checkbox defaults when absent from request |
| `save_fields_nullable` | Fields normalized to `NULL` when empty |
| `form_new_defaults` | Defaults for new-record forms |
| `is_dynamic_index` | Turn on config-driven list rendering |
| `search_fields` | Fields used by text search |
| `list_sortdef` | Default sort |
| `list_sortmap` | Map UI sort names to SQL fields |
| `list_view` | Custom SQL view/table source for list |
| `view_list_defaults` | Default visible columns |
| `view_list_map` | Column label mapping |
| `view_list_custom` | Columns rendered by custom templates |
| `related_field_name` | Parent/child relation field |
| `is_userlists` | Turn on user-list support |
| `is_dynamic_show` | Turn on config-driven show screen |
| `is_dynamic_showform` | Turn on config-driven edit/new screen |
| `show_fields` | Field layout for view screen |
| `showform_fields` | Field layout for edit screen |
| `form_tabs` | Optional tab definitions |
| `route_return` | Optional return target override |
| `header_links` | Optional Vue list-header links |

## Field Layout

Structural types:

- `row`
- `row_end`
- `col`
- `col_end`

Common show-field types:

- `plaintext`
- `plaintext_json`
- `plaintext_url`
- `plaintext_link`
- `plaintext_autocomplete`
- `noescape`
- `markdown`
- `float`
- `date`
- `date_long`
- `checkbox`
- `multi`
- `multi_prio`
- `att`
- `att_links`
- `att_files`
- `subtable`
- `added`
- `updated`

Common showform-field types:

- `input`
- `textarea`
- `select`
- `radio`
- `yesno`
- `cb`
- `email`
- `number`
- `password`
- `time`
- `date_popup`
- `datetime_popup`
- `autocomplete`
- `multicb`
- `multicb_prio`
- `att_edit`
- `att_links_edit`
- `att_files_edit`
- `subtable_edit`

The shared markup lives under:

- `template/common/form/show/`
- `template/common/form/showform/`
- `template/common/vue/`

## Minimal Config Example

```json
{
  "model": "Demos",
  "save_fields": ["iname", "demo_dicts_id", "status"],
  "required_fields": "iname",
  "search_fields": "!id iname",
  "list_sortdef": "iname asc",

  "is_dynamic_index": true,
  "view_list_defaults": "iname demo_dicts_id status",
  "view_list_map": {
    "iname": "Title",
    "demo_dicts_id": "Dictionary",
    "status": "Status"
  },

  "is_dynamic_show": true,
  "show_fields": [
    { "field": "iname", "label": "Title", "type": "plaintext" },
    { "field": "status", "label": "Status", "type": "plaintext", "lookup_tpl": "/common/sel/status.sel" }
  ],

  "is_dynamic_showform": true,
  "showform_fields": [
    { "field": "iname", "label": "Title", "type": "input", "required": true },
    { "field": "demo_dicts_id", "label": "Dictionary", "type": "select", "lookup_model": "DemoDicts", "is_option0": true },
    { "field": "status", "label": "Status", "type": "select", "lookup_tpl": "/common/sel/status.sel" }
  ]
}
```

## Validation

The framework handles:

- required-field validation from `required_fields`,
- required inference from `showform_fields` when `required` is true,
- simple validators declared via `validate`,
- the standard save/update flow in `SaveAction()`.

Simple validation codes include `exists`, `isemail`, `isphone`, `isdate`, and `isfloat`.

## Custom Rendering

Mark a field as custom when a standard shared template does not fit:

```json
{
  "is_custom": true,
  "field": "some_custom_field"
}
```

Then handle it in the screen template while leaving standard fields on shared rendering:

```html
<~fields repeat inline>
  <~/common/form/show/one_field unless="is_custom">
  <~custom_fields inline if="is_custom">
    <~custom_field ifeq="field" value="some_custom_field">
      Custom markup
    </~custom_field>
  </~custom_fields>
</~fields>
```
