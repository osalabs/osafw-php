# CRUD Patterns

The framework expects most business entities to be managed through `FwModel`. The model layer gives you a standard place for CRUD rules, audit fields, soft delete behavior, and entity-specific helpers without forcing every screen to hand-write SQL.

## Default Model Shape

```php
<?php

class DemoDicts extends FwModel {

    public function __construct() {
        parent::__construct();

        $this->table_name = 'demo_dicts';
    }

}
```

That is enough for a large amount of basic CRUD behavior.

## Model Properties

| Property | Purpose |
| --- | --- |
| `table_name` | Physical table name |
| `field_id` | Primary key column, usually `id` |
| `field_icode` | External/code identifier column, usually `icode` |
| `field_iname` | Human-readable name column, usually `iname` |
| `field_status` | Lifecycle column used for soft delete and active/deleted filtering |
| `field_prio` | Ordering column when the entity has explicit priority |
| add/update audit fields | Timestamp and user columns updated automatically when configured |

Most modules keep the defaults and only override what differs.

## Read Helpers

| Method | Typical use |
| --- | --- |
| `one($id)` | Load one row by numeric id |
| `oneField($id, $field)` | Load one scalar field |
| `oneByIcode($icode)` | Load by external code |
| `oneByIcodeOrFail($icode)` | Load by external code and fail if missing |
| `oneByIname($iname)` | Load by display name |
| `idByIcode($icode)` | Resolve a numeric id from an external code |
| `listByWhere($where, $orderBy = null, ...)` | Load rows by filters |
| `listMulti($ids)` | Load many rows by ids |
| `ilist(...)` | Read data shaped for select/dropdown lists |
| `getCount($where = [])` | Count rows |

`one()` and `oneByIcode()` use request-scoped caching, so repeated reads for the same row are cheap inside one request.

## Write Helpers

| Method | Typical use |
| --- | --- |
| `add($fields)` | Insert one row |
| `update($id, $fields)` | Update one row |
| `delete($id, $isPermanent = false)` | Delete or soft-delete one row |
| `deleteMulti($ids, $isPermanent = false)` | Delete many rows |
| `deleteWithPermanentCheck($id)` | Admin delete that respects permanent-delete rules |

If the model has `field_status`, `FwModel::delete()` normally soft deletes by setting status to `127`.

## Controller Save Flow

For standard admin screens, controller save flow is usually:

1. Read posted data.
2. Validate it.
3. Filter to allowed save fields.
4. Call the model to add or update.
5. Redirect back to the correct screen.

The base controllers provide helpers such as `getSaveFields()`, `Validate()`, `modelAddOrUpdate()`, and `afterSave()`.

```php
public function SaveAction($form_id): ?array {
    $this->route_onerror = fw::ACTION_SHOW_FORM;

    $id   = intval($form_id);
    $item = reqh('item');

    $this->Validate($id, $item);

    $itemdb = $this->getSaveFields($id, $item);
    $id     = $this->modelAddOrUpdate($id, $itemdb);

    return $this->afterSave(true, $id, $id == 0);
}
```

Consistency across modules is intentional. If the workflow is conventional, use the helpers instead of rebuilding the save path.

## Practical Guidance

- Keep controller save logic orchestration-only; move reusable rules into the model.
- Add model methods for real concepts: lifecycle rules, relation syncing, derived fields, custom lookup helpers, or JSON projection.
- Use `$this->db` directly for aggregation-heavy, join-heavy, batch-oriented, or operational queries.
- Do not create shallow wrappers that only forward to another helper.
- Prefer direct field access for guaranteed columns instead of defensive defaults everywhere.
- For schema changes, update `db/fwdatabase.sql` and add a dated script under `db/updates/`.

## Related Docs

- [docs/db.md](db.md) for lower-level DB wrapper usage.
- [docs/dynamic.md](dynamic.md) for config-driven admin CRUD screens.
