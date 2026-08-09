# Templates and ParsePage

The framework renders HTML through ParsePage templates under `template/`. The template layer is intentionally lightweight: it handles composition, repetition, small conditionals, and output formatting. Business rules belong in controllers and models.

## Mental Model

1. Controllers decide what data the screen needs.
2. Models prepare the data.
3. ParsePage merges that data into HTML fragments.
4. A layout wraps the screen output into the final page shell.

## Folder Structure

A standard admin screen usually looks like this:

```text
template/admin/<screen>/
  config.json
  index/
    main.html
    title.html
    load_script.html
  show/
    main.html
    form.html
  showform/
    main.html
    form.html
```

Shared building blocks live under:

- `template/common/list/`
- `template/common/form/`
- `template/common/vue/`
- `template/layout/`

## ParsePage Blocks

ParsePage conditions do not work on plain HTML tags.

Wrong:

```html
<div class="row" if="items">...</div>
```

Correct:

```html
<~items_block if="items" inline>
  <div class="row">...</div>
</~items_block>
```

If the condition is false, the whole ParsePage block is removed before HTML is sent.

## Output

```html
<h1><~title></h1>
<a href="<~item[url]>"><~item[iname]></a>
<~GLOBAL[SITE_NAME]>
<~SESSION[user_name]>
```

Use global/session roots sparingly. In most screens, the controller should pass data directly.

## Includes and Repeats

```html
<~/common/list/empty>
<~/layout/sidebar>
```

```html
<~rows repeat inline>
  <tr>
    <td><~repeat.iteration></td>
    <td><~iname></td>
  </tr>
</~rows>
```

Repeat metadata includes `repeat.index`, `repeat.iteration`, `repeat.first`, `repeat.last`, `repeat.total`, `repeat.even`, `repeat.odd`, and `repeat.key`.

## Conditions

Supported comparison attributes include:

- `if`
- `unless`
- `ifeq`
- `ifne`
- `ifgt`
- `iflt`
- `ifge`
- `ifle`

Use exact comparisons when needed:

```html
<~active_cl ifeq="GLOBAL[controller]" value="AdminDemos" inline>active</~active_cl>
```

Truthiness matters: a non-empty string such as `"false"` still counts as true.

## Select Helpers

```html
<select name="item[status]" class="form-select">
  <~/common/sel/status.sel select="i[status]">
</select>
```

```html
<~/common/sel/status.sel selvalue="i[status]">
```

## Output Modifiers

Common modifiers implemented in `php/fw/ParsePage.php` include:

- `date`
- `number_format`
- `currency`
- `string_format`
- `sprintf`
- `htmlescape`
- `truncate`
- `strip_tags`
- `trim`
- `nl2br`
- `count`
- `lower`
- `upper`
- `default`
- `urlencode`
- `url`
- `json`
- `noparse`

Examples:

```html
<~amount currency="USD">
<~idate date="short">
<~payload json>
<~GLOBAL[request_url] urlencode>
<~website url>
```

## Common Fragments

These shared fragments are intended for reuse:

- `template/common/list/form_list.html`
- `template/common/list/thead.html`
- `template/common/list/tbody.html`
- `template/common/list/pagination.html`
- `template/common/form/show/`
- `template/common/form/showform/`
- `template/common/form/tabs.html`
- `template/common/vue/`

Recent generic hooks:

- `form_list_hidden_fields` injects extra hidden fields into shared list forms.
- `tbody_row_attrs` appends row-level attributes to shared list rows.
- `plaintext_json` renders show values in a monospace block.
- `plaintext_url` renders a clickable URL.
- Vue list headers support `header_links` entries with `url`, `label`, and optional Bootstrap icon class.

When a screen-specific block needs JavaScript, prefer `load_script.html` or the relevant Vue component include over large inline scripts in `main.html`.
