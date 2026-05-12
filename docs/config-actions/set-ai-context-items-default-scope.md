# `setAiContextItemsDefaultScope` Config Action

## Overview

The `setAiContextItemsDefaultScope` config action assigns a scope (Global, Use
Case, Tag, Site Section, Entity Bundle, Target Entity, Language) to every
`ai_context_item` content entity created by a recipe's content step.

It exists to work around two structural limitations in Drupal:

- `\Drupal\Core\DefaultContent\Importer::setFieldValues()` cannot write
  values into a `map` base field with arbitrary keys — and the `scope` field
  on `ai_context_item` (provided by the
  [AI Context](https://www.drupal.org/project/ai_context) module) is exactly
  that kind of field. Any non-empty `scope` in
  `content/ai_context_item/*.yml` makes the importer throw
  *"Property &lt;key&gt; is unknown."*.
- `drush content:export ai_context_item N` normalises the `scope` field back
  to `scope: - {  }` regardless of what the entity actually stores, so the
  scope cannot be round-tripped through default-content YAML files either.

As a result, recipes that ship starter AI Context items would otherwise land
with empty scopes, and operators would have to set scope by hand on each
item after applying the recipe. `setAiContextItemsDefaultScope` fills that
gap directly inside the recipe.

## How it works

1. The action is attached to one of the `ai_context.scope_settings.*` config
   objects shipped by the `ai_context` module — for example
   `ai_context.scope_settings.global` or `ai_context.scope_settings.use_case`.
   The **scope plugin id is parsed from the config name** (segment after
   `ai_context.scope_settings.`).
2. Because Drupal recipes run config actions **before** the content step, the
   action's `apply()` registers an in-process listener on
   `\Drupal\Core\Recipe\RecipeAppliedEvent`.
3. After the recipe's content step completes, the listener loads every
   `ai_context_item` whose scope is still empty and writes
   `[scope_id => values]` via
   `\Drupal\ai_context\Entity\AiContextItem::setScopeValues()`.
4. Items that already carry a scope are **skipped**, so the action is
   idempotent — re-applying the recipe, or hand-editing an item after recipe
   apply, is safe.

## Requirements

- The `ai_context` module must be installed (the action is a no-op
  otherwise — it checks `EntityTypeManager::hasDefinition('ai_context_item')`).
- The target `ai_context.scope_settings.<scope_id>` config must exist; the
  `ai_context` module ships one per scope plugin
  (`global`, `use_case`, `language`, `tag`, `site_section`, `entity_bundle`,
  `target_entity`).

## Usage in a Recipe

### Apply Global scope to all imported items

```yaml
config:
  actions:
    ai_context.scope_settings.global:
      setAiContextItemsDefaultScope:
        - global
```

### Apply a Use Case scope

```yaml
config:
  actions:
    ai_context.scope_settings.use_case:
      setAiContextItemsDefaultScope:
        - writing_words
        - working_in_canvas
```

### Apply scopes from multiple plugins in one recipe

```yaml
config:
  actions:
    ai_context.scope_settings.global:
      setAiContextItemsDefaultScope:
        - global
    ai_context.scope_settings.entity_bundle:
      setAiContextItemsDefaultScope:
        - 'node:blog'
        - 'node:page'
```

Each entry sets a single scope plugin id. The listener writes
`setScopeValues($scope_id, $values)` per matching item, so combining
plugins on the same items is supported.

**Value:** A list of scope values valid for the parsed scope plugin id. The
valid values for each plugin are defined by the scope plugin itself — see
*Admin → AI → Context → Settings* on a running site.

## What It Does

1. Validates that the target config name starts with
   `ai_context.scope_settings.` and that the value is a non-empty list.
2. Registers a one-shot `RecipeAppliedEvent` listener on the event
   dispatcher.
3. After the recipe's content step:
   - Loads all `ai_context_item` entities.
   - Skips items whose `scope` field already has a value.
   - Calls `setScopeValues($scope_id, $values)` and saves each remaining
     item.

## Example — Varbase AI Context recipe

The [Varbase AI Context](https://www.drupal.org/project/varbase_ai_context)
recipe ships three starter context items (Brand & Identity, Editorial Rules,
Safety Guardrails). Its `recipe.yml` uses this action to assign Global scope
to all three so they are visible to every Varbase AI agent immediately after
recipe apply:

```yaml
config:
  strict: false
  import:
    ai_context: '*'
  actions:
    ai_context.scope_settings.global:
      setAiContextItemsDefaultScope:
        - global
```

After `drush recipe ../recipes/varbase_ai_context/`, every imported item
shows up at `/admin/ai/context/items` with Global checked under **Scope**.

## Comparison with content YAML and `drush content:export`

| | `content/ai_context_item/*.yml` | `drush content:export` | `setAiContextItemsDefaultScope` |
|---|---|---|---|
| Can ship a scope | No — Importer throws on map field keys | No — exporter dumps `{}` | Yes |
| Pure YAML in recipe | Yes (but broken) | N/A | Yes |
| Idempotent | N/A | N/A | Yes (skips items with non-empty scope) |
| Works for any scope plugin | N/A | N/A | Yes (`global`, `use_case`, `tag`, `entity_bundle`, `site_section`, `target_entity`, `language`) |

## Notes

- The action skips items whose scope is non-empty, so user-edited scopes are
  preserved on recipe re-apply.
- Logging output is written to the `varbase_recipes` logger channel and can
  be viewed in Drupal's Recent Log Messages (`admin/reports/dblog`).
- This config action is provided by the `varbase_recipes` module and follows
  the [Drupal Recipes Config Actions API](https://project.pages.drupalcode.org/distributions_recipes/config_actions.html).
