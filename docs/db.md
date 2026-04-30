# Database Access

This framework uses the `DB` wrapper in `php/fw/db.php` for MySQL access. The goal is straightforward data access: common CRUD operations are concise, advanced queries are still possible, and existing code can mix table-based helpers with raw SQL where that is clearer.

## Choosing the Layer

- Start with `FwModel` methods for normal entity reads and writes.
- Use `$this->db` inside models and controllers for joins, aggregation, or batch work.
- Use procedural helpers such as `db_row`, `db_array`, and `db_exec` only in older code that already follows that style.

The framework wires a `DB` instance into `FwModel` and controller base classes, so most code uses `$this->db`.

## Common Reads

| Method | Use when | Return |
| --- | --- | --- |
| `row($table, $where, $orderBy = null)` | Load one row by simple filters | `array` |
| `rowp($sql, $params = null)` | Load one row with raw SQL | `array` |
| `arr($table, $where = null, $orderBy = null, $limit = null, $fields = '*')` | Load rows by simple filters | `array[]` |
| `arrp($sql, $params = null)` | Load rows with raw SQL | `array[]` |
| `value($table, $where, $field = null, $orderBy = null)` | Load one scalar | `string|null` |
| `valuep($sql, $params = null)` | Load one scalar from raw SQL | `string|null` |
| `col($table, $where, $field = null, $orderBy = null)` | Load one column | `array` |
| `colp($sql, $params = null)` | Load one column from raw SQL | `array` |

Use the `...p` methods when you need raw SQL plus parameters. Use table helpers when the query is still simple enough to express as table, where, and order.

## Common Writes

| Method | Use when |
| --- | --- |
| `insert($table, $fields, $options = [])` | Insert one row |
| `update($table, $fields, $where)` | Update rows |
| `updatep($sql, $params = null)` | Update with raw SQL |
| `delete($table, $where, $orderBy = null, $limit = null)` | Delete rows directly |
| `upsert($table, $fields, $where)` | Update if a row exists, otherwise insert |
| `exec($sql, $params = null)` | Execute non-select SQL |
| `execMultipleSQL($sql)` | Run trusted local schema scripts |

`FwModel::delete()` often performs soft delete semantics. Direct `DB::delete()` is lower-level and should be used intentionally.

## Parameters

Named placeholders use `@name` or `:name`:

```php
$rows = $this->db->arrp(
    'SELECT * FROM users WHERE status=@status AND email LIKE @email',
    [
        'status' => FwModel::STATUS_ACTIVE,
        'email'  => '%@example.test',
    ]
);
```

Array parameters expand for `IN (...)` filters:

```php
$rows = $this->db->arrp(
    'SELECT * FROM users WHERE id IN (@ids)',
    ['ids' => [10, 20, 30]]
);
```

For table helpers, prefer operator helpers:

```php
$rows = $this->db->arr('users', [
    'id'     => $this->db->opIN([10, 20, 30]),
    'status' => $this->db->opNOT(FwModel::STATUS_DELETED),
], 'id desc');
```

## Operator Helpers

| Helper | Meaning |
| --- | --- |
| `opEQ($value)` | equality |
| `opNOT($value)` | not equal |
| `opLT($value)`, `opLE($value)` | less than, less or equal |
| `opGT($value)`, `opGE($value)` | greater than, greater or equal |
| `opLIKE($value)`, `opNOTLIKE($value)` | `LIKE`, `NOT LIKE` |
| `opIN($values)`, `opNOTIN($values)` | `IN`, `NOT IN` |
| `opBETWEEN([$from, $to])` | `BETWEEN` |
| `opISNULL()`, `opISNOTNULL()` | null checks |

## Transactions

Use explicit transactions when a workflow updates multiple tables or must keep side effects consistent.

```php
$this->db->transaction();

try {
    $this->db->insert('example_parent', $parentFields);
    $this->db->insert('example_child', $childFields);
    $this->db->commit();
} catch (Throwable $e) {
    $this->db->rollback();
    throw $e;
}
```

The wrapper includes reconnect logic and deadlock retries. That helps with resilience, but it does not replace correct transaction boundaries.

## Caveats

- `qid()` is for one SQL identifier. Do not pass dotted expressions or full SQL fragments to it.
- `execMultipleSQL()` is for trusted local scripts such as schema/bootstrap work, not user input.
- Prefer `arrp()` or `valuep()` over string-concatenated SQL.
- If the query is entity CRUD, prefer `FwModel` first so audit fields, caching, and soft delete stay consistent.

## Related Docs

- [docs/crud.md](crud.md) for model-level CRUD patterns.
- [docs/dynamic.md](dynamic.md) for config-driven admin CRUD screens.
