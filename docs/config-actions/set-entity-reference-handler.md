# `setEntityReferenceHandler` Config Action

## Overview

The `setEntityReferenceHandler` config action updates the entity reference
selection handler on an existing `field_config` entity and optionally merges
new values into its `handler_settings`.

Unlike `simpleConfigUpdate` (which **replaces** the entire settings array),
`setEntityReferenceHandler` **merges** handler settings — existing keys not
included in the action value are preserved.

## Requirements

- The field config must exist (e.g. `field.field.node.blog.field_tags`).
- The field must be an entity reference field (`field_config` entity type).

## Usage in a Recipe

```yaml
config:
  actions:
    field.field.node.blog.field_tags:
      setEntityReferenceHandler:
        handler: 'default:taxonomy_term'
```

**Value:** An associative array with the following keys:

| Key | Type | Required | Description |
|---|---|---|---|
| `handler` | string | Yes | The entity reference selection plugin ID to set (e.g. `default:taxonomy_term`, `default:node`). |
| `handler_settings` | array | No | Settings to merge into the existing `handler_settings`. Existing keys not listed here are kept. |

## What It Does

1. Loads the field config entity identified by the config name.
2. Replaces the `handler` value with the one supplied.
3. If `handler_settings` is provided, merges it into the existing
   `handler_settings` (existing keys not mentioned are preserved).
4. Saves the field config.
5. Logs the previous and new handler to the `varbase_recipes` logger channel.

## Examples

### Switch handler only

```yaml
config:
  actions:
    field.field.node.blog.field_tags:
      setEntityReferenceHandler:
        handler: 'default:taxonomy_term'
```

### Switch handler and override specific handler settings

```yaml
config:
  actions:
    field.field.node.article.field_tags:
      setEntityReferenceHandler:
        handler: 'default:taxonomy_term'
        handler_settings:
          auto_create: true
```

### Using recipe input placeholders

```yaml
config:
  actions:
    field.field.node.${content_type}.${field_name}:
      setEntityReferenceHandler:
        handler: 'default:taxonomy_term'
        handler_settings:
          auto_create: true
```

## Comparison with `simpleConfigUpdate`

| | `simpleConfigUpdate` | `setEntityReferenceHandler` |
|---|---|---|
| Effect on `handler` | Replaces the entire settings array | Sets only the `handler` key |
| Effect on `handler_settings` | Replaces the entire settings array | **Merges** — existing keys are kept |
| Safe to apply multiple times | Only if the full desired value is supplied | Yes — idempotent |
| Use when | You own the entire field settings | You want to change the handler without touching other settings |

## Notes

- If the field config entity cannot be loaded, the action is skipped and a
  warning is logged. No exception is thrown.
- Logging output is written to the `varbase_recipes` logger channel and can be
  viewed in Drupal's Recent Log Messages (`admin/reports/dblog`).
- This config action is provided by the `varbase_recipes` module and follows
  the [Drupal Recipes Config Actions API](https://project.pages.drupalcode.org/distributions_recipes/config_actions.html).
