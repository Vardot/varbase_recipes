# `enableCKEditorPlugin` Config Action

## Overview

The `enableCKEditorPlugin` config action initialises a CKEditor 5 plugin in
an editor text format by writing its default configuration into
`settings.plugins.<plugin_id>` on the `editor.editor.*` config entity.

This is primarily useful for plugins that have **no toolbar button** and
therefore cannot be activated via the `addItemToToolbar` config action. A
common example is `ckeditor5_paste_filter_pasteFilter`, which controls how
pasted content is cleaned up but does not add a button to the toolbar.

If the plugin key already exists in the editor's plugin settings this action
is a **no-op**, so it is safe to apply more than once.

## Requirements

- The editor config entity must exist (e.g. `editor.editor.full_html`).
- The editor must use CKEditor 5.
- For configurable plugins the plugin class must implement
  `CKEditor5PluginConfigurableInterface`.

## Usage in a Recipe

```yaml
config:
  actions:
    editor.editor.full_html:
      enableCKEditorPlugin:
        plugin_id: ckeditor5_paste_filter_pasteFilter
        config:
          enabled: true
```

**Value:** An associative array with the following keys:

| Key | Type | Required | Description |
|---|---|---|---|
| `plugin_id` | string | Yes | The machine name of the CKEditor 5 plugin (e.g. `ckeditor5_paste_filter_pasteFilter`). |
| `config` | array | No | Configuration overrides to merge on top of the plugin's `defaultConfiguration()`. |

## What It Does

1. Loads the editor config entity identified by `$configName`.
2. Checks whether `settings.plugins.<plugin_id>` already exists — skips if so.
3. For configurable plugins: fetches `defaultConfiguration()` from the plugin
   instance and merges in any `config` overrides you provide.
4. For non-configurable plugins: stores the supplied `config` array as-is.
5. Saves the updated editor entity.

## Example — enable the Paste Filter on both editors

```yaml
config:
  actions:
    editor.editor.full_html:
      enableCKEditorPlugin:
        plugin_id: ckeditor5_paste_filter_pasteFilter
        config:
          enabled: true
    editor.editor.basic_html:
      enableCKEditorPlugin:
        plugin_id: ckeditor5_paste_filter_pasteFilter
        config:
          enabled: true
```

## Comparison with `addItemToToolbar`

| | `addItemToToolbar` | `enableCKEditorPlugin` |
|---|---|---|
| Adds a button to the toolbar | Yes | No |
| Configures the plugin | Only if the plugin has a toolbar button | Yes, for any plugin |
| Use when | You want a toolbar button (plugin config is optional) | You need a plugin active with no toolbar button |

## Notes

- This action only writes to `settings.plugins`; it does not add toolbar
  buttons. Use `addItemToToolbar` for that.
- Logging output is written to the `varbase_recipes` logger channel and can be
  viewed in Drupal's Recent Log Messages (`admin/reports/dblog`).
- This config action is provided by the `varbase_recipes` module and follows
  the [Drupal Recipes Config Actions API](https://project.pages.drupalcode.org/distributions_recipes/config_actions.html).
