# `addButtonPluginIntoActiveToolbar` Config Action

## Overview

The `addButtonPluginIntoActiveToolbar` config action adds a button to a
CKEditor 5 editor's active toolbar and optionally registers the associated
plugin settings at the same time. If the button already exists in the toolbar
the action is a **no-op** (idempotent).

This is the recipe-friendly equivalent of patching the `Editor` entity to
call `addButtonPluginIntoActiveToolbar()` (Drupal core issue
[#3431330](https://www.drupal.org/node/3431330)).

## Requirements

- The editor config entity must exist (e.g. `editor.editor.full_html`).
- The editor must use CKEditor 5.

## Usage in a Recipe

```yaml
config:
  actions:
    editor.editor.full_html:
      addButtonPluginIntoActiveToolbar:
        button_name: myButton
        button_index: 0
        plugin_name: my_module_myPlugin
        plugin_settings:
          enabled: true
          option_a: foo
```

**Value:** An associative array with the following keys:

| Key | Type | Required | Default | Description |
|---|---|---|---|---|
| `button_name` | string | Yes | — | The toolbar button / item name (e.g. `fullScreen`, `findAndReplace`). |
| `button_index` | int | No | `-1` | Position to insert at. `-1` = append at the end, `0` = prepend at the beginning, positive integer = insert at that index. |
| `plugin_name` | string | No | `''` | CKEditor 5 plugin key to register under `settings.plugins`. Omit or leave empty to skip plugin registration. |
| `plugin_settings` | array | No | `[]` | Settings to store under `plugin_name`. Supports deeply nested arrays. |

## What It Does

1. Loads the editor config entity identified by `$configName`.
2. Checks whether `button_name` is already present in the toolbar — skips if so.
3. Inserts the button at the requested position (append, prepend, or index).
4. If `plugin_name` is provided, writes `plugin_settings` into
   `settings.plugins.<plugin_name>`.
5. Saves the updated editor entity.

## Real-world example — Varbase AI Editor Assistant

The `varbase_ai_editor_assistant` recipe uses this action to add the AI
Assistant button to any CKEditor 5 text format. It uses recipe
[input variables](https://www.drupal.org/docs/drupal-apis/recipe-api/recipe-input-data)
so the target editor and toolbar position can be chosen at install time:

```yaml
# recipe.yml
input:
  ckeditor_machine_name:
    data_type: string
    description: 'The machine name of the CKEditor 5 editor to configure with AI enhancements.'
    prompt:
      method: ask
      arguments:
        question: 'Enter the machine name of the CKEditor 5 text editor you want to configure with AI capabilities:'
    default:
      source: value
      value: 'full_html'
  ckeditor_index:
    data_type: string
    description: 'The position index for the AI Assistant button in the editor toolbar.'
    prompt:
      method: ask
      arguments:
        question: 'Specify the position index for the AI Assistant button:'
    default:
      source: value
      value: '0'

config:
  strict: false
  import:
    ai_ckeditor: '*'
  actions:
    editor.editor.${ckeditor_machine_name}:
      addButtonPluginIntoActiveToolbar:
        button_name: aickeditor
        button_index: ${ckeditor_index}
        plugin_name: ai_ckeditor_ai
        plugin_settings:
          dialog:
            autoresize: 'min-width: 600px'
            height: '750'
            width: '900'
            dialog_class: ai-ckeditor-modal
          plugins:
            ai_automators_ckeditor:
              workflows: {  }
              enabled: true
            ai_ckeditor_completion:
              provider: openai__gpt-4o
              enabled: true
            ai_ckeditor_help:
              enabled: false
            ai_ckeditor_reformat_html:
              provider: openai__gpt-4o
              enabled: true
            ai_ckeditor_spellfix:
              provider: openai__gpt-4o
              enabled: true
            ai_ckeditor_summarize:
              provider: openai__gpt-4o
              enabled: true
            ai_ckeditor_tone:
              enabled: false
            ai_ckeditor_translate:
              enabled: false
```

## Real-world example 2 — Add OpenAI to Rich and Simple Editors

These two standalone recipes append the OpenAI button to `full_html` (Rich
Editor) and insert it at position 1 in `basic_html` (Simple Editor):

```yaml
# add-openai-to-rich-editor/recipe.yml
name: Add OpenAI to Rich Editor
description: A recipe to manage default OpenAI button and plugin settings for the Rich Editor with CKEditor 5
type: optional
config:
  actions:
    editor.editor.full_html:
      addButtonPluginIntoActiveToolbar:
        button_name: openai
        plugin_name: openai_ckeditor_openai
        plugin_settings:
          completion:
            enabled: true
            model: gpt-4
            temperature: 0.2
            max_tokens: 512
```

```yaml
# add-openai-to-simple-editor/recipe.yml
name: Add OpenAI to Simple Editor
description: A recipe to manage default OpenAI button and plugin settings for the Simple Editor with CKEditor 5.
type: optional
config:
  actions:
    editor.editor.basic_html:
      addButtonPluginIntoActiveToolbar:
        button_name: openai
        button_index: 1
        plugin_name: openai_ckeditor_openai
        plugin_settings:
          completion:
            enabled: true
            model: gpt-4
            temperature: 0.2
            max_tokens: 512
```

Key differences between the two:
- `full_html` omits `button_index` — the button is appended at the end.
- `basic_html` sets `button_index: 1` — the button is inserted at position 1.

---

## Position examples

```yaml
# Append at the end (default)
addButtonPluginIntoActiveToolbar:
  button_name: fullScreen

# Prepend at the beginning
addButtonPluginIntoActiveToolbar:
  button_name: fullScreen
  button_index: 0

# Insert at position 3
addButtonPluginIntoActiveToolbar:
  button_name: fullScreen
  button_index: 3
```

## Comparison with `addItemToToolbar`

| | `addItemToToolbar` | `addButtonPluginIntoActiveToolbar` |
|---|---|---|
| Add button at specific position | Yes (via `position`) | Yes (via `button_index`) |
| Auto-configure plugin with defaults | Yes (for configurable plugins) | No — you supply `plugin_settings` directly |
| Register arbitrary plugin settings | No | Yes (including deeply nested arrays) |
| Works with recipe input variables | Yes | Yes |
| Skip if button already present | Yes | Yes |
| Use when | The plugin has a standard `defaultConfiguration()` | You need full control over plugin settings |

## Notes

- This action is safe to apply more than once — it will not add a duplicate
  button.
- `plugin_settings` fully replaces any existing value under `plugin_name`.
  Use [`updatePluginSettings`](update-plugin-settings.md) if the plugin is
  already present and you only want to overwrite its settings.
- Logging output is written to the `varbase_recipes` logger channel and can be
  viewed in Drupal's Recent Log Messages (`admin/reports/dblog`).
- This config action is provided by the `varbase_recipes` module and follows
  the [Drupal Recipes Config Actions API](https://project.pages.drupalcode.org/distributions_recipes/config_actions.html).
