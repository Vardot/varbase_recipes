# `mergeAllowedHtml` Config Action

## Overview

The `mergeAllowedHtml` config action merges additional allowed HTML tags and
attributes into the existing `filters.filter_html.settings.allowed_html` value
of a text format config object.

Unlike `simpleConfigUpdate` (which **replaces** the entire value),
`mergeAllowedHtml` **adds** tags/attributes to the existing list without
removing what is already there. This makes it safe to use in recipes that
extend a text format without knowing—or needing to know—what tags are already
allowed.

## Merge rules

- Duplicate tags are deduplicated.
- For a tag that already exists, its **attributes are merged** with the new
  ones:
  - `class` values are combined (deduplicated).
  - All other attribute values are overridden by the new value.
- A tag that exists without attributes but is also provided with attributes
  keeps the version with attributes.

Supports custom XML tags such as `drupal-media` and `drupal-entity`.

## Requirements

- The text format config must exist (e.g. `filter.format.full_html`).
- The `filter_html` filter must be present in the text format for the merge to
  have any effect.

## Usage in a Recipe

```yaml
config:
  actions:
    filter.format.full_html:
      mergeAllowedHtml: '<drupal-media data-media-width> <figure class>'
```

**Value:** A string of HTML tags (and optional attributes) to merge into the
text format's existing allowed HTML. The format is the same as Drupal's
"Allowed HTML tags" setting.

## What It Does

1. Reads the current `filters.filter_html.settings.allowed_html` value from
   the text format config.
2. Merges the supplied string into the existing value according to the merge
   rules above.
3. Writes the merged value back to config and saves.

## Example — add resize-media tags to the Rich Editor

```yaml
# In your recipe.yml
config:
  actions:
    filter.format.full_html:
      mergeAllowedHtml: >-
        <drupal-media data-media-width data-entity-type data-entity-uuid
        alt data-view-mode data-caption data-align>
        <figure class>
```

## Comparison with `simpleConfigUpdate`

| | `simpleConfigUpdate` | `mergeAllowedHtml` |
|---|---|---|
| Effect on existing tags | **Replaces** the entire list | **Adds** to the existing list |
| Safe to apply multiple times | Only if the full desired value is supplied | Yes — idempotent per tag |
| Use when | You own the entire allowed-HTML list | You want to extend someone else's list |

## Notes

- The action is safe to call even if the `filter_html` filter value is empty;
  in that case the supplied string becomes the new value.
- Logging output is written to the `varbase_recipes` logger channel and can be
  viewed in Drupal's Recent Log Messages (`admin/reports/dblog`).
- This config action is provided by the `varbase_recipes` module and follows
  the [Drupal Recipes Config Actions API](https://project.pages.drupalcode.org/distributions_recipes/config_actions.html).
