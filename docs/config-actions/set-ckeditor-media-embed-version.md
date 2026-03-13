# `setCKEditorMediaEmbedVersion` Config Action

## Overview

The `setCKEditorMediaEmbedVersion` config action detects the current CKEditor
version in use on the site and saves it as `plugins_version_installed` in
`ckeditor_media_embed.settings`.

This is the safe, recipe-friendly equivalent of:

```bash
drush config-set ckeditor_media_embed.settings plugins_version_installed <version>
```

It is designed to be called during Drupal installation (unlike
`drush ckeditor_media_embed:install`, which calls `drupal_flush_all_caches()`
and fails during installation).

## Requirements

- Module `ckeditor_media_embed` must be installed.
- The CKEditor plugin library files must already be present on disk at
  `web/libraries/ckeditor5/plugins/`. Run the following once after deployment
  to download them:

  ```bash
  drush ckeditor_media_embed:install
  ```

## Usage in a Recipe

Apply this action to the `ckeditor_media_embed.settings` config object:

```yaml
config:
  actions:
    ckeditor_media_embed.settings:
      setCKEditorMediaEmbedVersion: true
```

**Value:** The action value is not used; any truthy value (e.g., `true`)
triggers the update.

## What It Does

1. Determines the CKEditor version in use on the site via
   `AssetManager::getCKEditorVersion()`.
2. Saves the detected version as `plugins_version_installed` to
   `ckeditor_media_embed.settings`.

## Example — Varbase Starter Recipe

The `varbase_starter` recipe uses this action to register the CKEditor Media
Embed plugin version after installation:

```yaml
# recipes/varbase_starter/recipe.yml
config:
  actions:
    ckeditor_media_embed.settings:
      setCKEditorMediaEmbedVersion: true
```

## Comparison with Drush Command

| Step | Drush command | Config action |
|---|---|---|
| Detect CKEditor version | `drush ckeditor_media_embed:install` | `setCKEditorMediaEmbedVersion: true` |
| Download plugin files from NPM | Done | **Not done** — files must already exist |
| Save `plugins_version_installed` to config | Done automatically | Done automatically |
| Flush caches | Done automatically | **Not done** — safe during installation |

## Notes

- This action does **not** download or copy plugin files. It only writes the
  version to configuration.
- It is safe to call during Drupal installation because it does not flush caches.
- Logging output is written to the `varbase_recipes` logger channel and can be
  viewed in Drupal's Recent Log Messages (`admin/reports/dblog`).
- This config action is provided by the `varbase_recipes` module and follows
  the [Drupal Recipes Config Actions API](https://project.pages.drupalcode.org/distributions_recipes/config_actions.html).
