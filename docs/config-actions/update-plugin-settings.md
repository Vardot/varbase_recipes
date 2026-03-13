# `updatePluginSettings` Config Action

## Overview

The `updatePluginSettings` config action replaces the settings stored under
`settings.plugins.<plugin_name>` on a CKEditor 5 editor config entity.

The update is only applied if the plugin key **already exists** in the
editor's plugin settings. If the key is absent this action is a **no-op**.
Use [`enableCKEditorPlugin`](enable-ckeditor-plugin.md) to initialise a
plugin that is not yet present.

This is the recipe-friendly equivalent of patching the `Editor` entity to
call `updatePluginSettings()` (Drupal core issue
[#3431330](https://www.drupal.org/node/3431330)).

## Requirements

- The editor config entity must exist (e.g. `editor.editor.full_html`).
- The editor must use CKEditor 5.
- The plugin must already be present in `settings.plugins` (otherwise the
  action does nothing).

## Usage in a Recipe

```yaml
config:
  actions:
    editor.editor.full_html:
      updatePluginSettings:
        plugin_name: ckeditor5_paste_filter_pasteFilter
        plugin_settings:
          enabled: true
```

**Value:** An associative array with the following keys:

| Key | Type | Required | Description |
|---|---|---|---|
| `plugin_name` | string | Yes | The CKEditor 5 plugin key under `settings.plugins` (e.g. `ckeditor5_paste_filter_pasteFilter`). |
| `plugin_settings` | array | No | The new settings to store. Replaces the existing value entirely. |

## What It Does

1. Loads the editor config entity identified by `$configName`.
2. Checks whether `plugin_name` exists in `settings.plugins` — skips if not.
3. Replaces `settings.plugins.<plugin_name>` with the supplied
   `plugin_settings`.
4. Saves the updated editor entity.

## Comparison with related actions

| | `enableCKEditorPlugin` | `updatePluginSettings` |
|---|---|---|
| Applies when plugin is **absent** | Yes — initialises it | No — skips |
| Applies when plugin is **present** | No — skips | Yes — overwrites |
| Merges with existing settings | No | No — full replace |
| Use when | First-time setup of a plugin | Changing settings of a plugin that is already installed |

## Notes

- `plugin_settings` fully replaces the existing settings; it does not merge.
  If you only want to change one key, include all the other keys you want to
  keep in `plugin_settings`.
- Logging output is written to the `varbase_recipes` logger channel and can be
  viewed in Drupal's Recent Log Messages (`admin/reports/dblog`).
- This config action is provided by the `varbase_recipes` module and follows
  the [Drupal Recipes Config Actions API](https://project.pages.drupalcode.org/distributions_recipes/config_actions.html).
