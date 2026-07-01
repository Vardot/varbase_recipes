# Varbase Recipes

**Varbase Recipes** extends the [Project Browser](https://www.drupal.org/project/project_browser)
with a dedicated **"Varbase recipes"** tab and provides custom
[Drupal Recipes Config Action](https://project.pages.drupalcode.org/distributions_recipes/config_actions.html)
plugins that automate post-install setup steps for [Varbase](https://www.drupal.org/project/varbase) sites.

It is best used with the [Varbase](https://www.drupal.org/project/varbase) distribution,
but can also be installed on any Drupal 11 site — even with the Minimal or Standard profile.

---

## Browse and Install Varbase Recipes

Once installed, a new **"Varbase recipes"** tab appears in the Project Browser
(*Administration → Extend → Browse projects*). It lists all available Varbase
base recipes with one-click **Install** and **Reapply** actions — no Drush or
Composer commands needed.

![Browse projects — Varbase recipes tab, page 1, showing Varbase Admin Base, API Base, Auth Base, Blog Base, Content Base, Demo Content, Development Base, Editor Base, Internationalization Base, Media Assets, Media Base, and Page Base recipes with Installed and Install badges.](https://www.drupal.org/files/issues/2026-03-12/Browse-projects-My-Varbase-site-03-13-2026_01_42_AM.png)

![Browse projects — Varbase recipes tab, page 2, showing Varbase Performance Base, Security Base, SEO Base, Starter, Users Base, Webform Base, and Workflow Base recipes — all marked as Installed.](https://www.drupal.org/files/issues/2026-03-13/Browse-projects-My-Varbase-site-03-13-2026_01_43_AM.png)

---

## Available Varbase Recipes

| Recipe | Description |
|---|---|
| **Varbase Admin Base** | Default installed modules, configs and permissions for Varbase admin experience. |
| **Varbase API Base** | JSON:API with authentication, authorization, and OpenAPI documentation. |
| **Varbase Auth Base** | Social Single Sign-On with default social authentication modules and configurations. |
| **Varbase Blog Base** | Blog post content type, listing page, and related configuration. |
| **Varbase Content Base** | Foundational content structure: content types, taxonomy vocabularies, block content, menu system, path aliases, and more. |
| **Varbase Demo Content** | Sample pages that demonstrate key Varbase features. |
| **Varbase Development Base** | Development environment modules and configurations. Disable in production. |
| **Varbase Editor Base** | Default installed modules, configs and permissions for Varbase CKEditor 5 rich text editing capabilities. |
| **Varbase Internationalization Base** | Internationalization, languages, and translation support for multilingual sites. |
| **Varbase Media Assets** | Default demo media assets for Varbase Media Types. |
| **Varbase Media Base** | Core media modules with default Varbase configurations including media library, focal point, and media types. |
| **Varbase Page Base** | Page content type with SEO fields, editorial workflow, and menu configuration. |
| **Varbase Performance Base** | Default performance optimizations: page caching, asset aggregation, image optimization, lazy loading, and speed enhancements. |
| **Varbase Security Base** | Default security configurations: password policy, username enumeration prevention, security kit, CAPTCHA, honeypot, anti-bot, and flood control. |
| **Varbase SEO Base** | Default installed modules, configs and permissions for Varbase Search Engine Optimization (SEO) core features and settings. |
| **Varbase Starter** | A starter site template recipe for Varbase, providing a modern recipe-first approach to initializing Varbase sites. |
| **Varbase Users Base** | Default Varbase user roles and user management configurations, including role definitions, account settings, and the modules needed to support user management. |
| **Varbase Webform Base** | Default installed webform modules, configurations, and permissions for Varbase webform experience. |
| **Varbase Workflow Base** | Advanced editorial and publishing workflow with content moderation, built on top of the basic Workflow Integration in Drupal CMS. |

---

## Custom Config Actions

Varbase Recipes provides custom
[Config Action](https://project.pages.drupalcode.org/distributions_recipes/config_actions.html)
plugins for use in recipe YAML files. These automate post-install setup steps
that are unsafe to run with standard Drush commands during Drupal installation.

| Plugin ID | Description |
|---|---|
| [`setCKEditorMediaEmbedVersion`](config-actions/set-ckeditor-media-embed-version.md) | Detects the current CKEditor version and saves it as `plugins_version_installed` in `ckeditor_media_embed.settings`. |
| [`mergeAllowedHtml`](config-actions/merge-allowed-html.md) | Merges allowed HTML tags and attributes into an existing text format's `filter_html` allowed-HTML setting without replacing what is already there. |
| [`enableCKEditorPlugin`](config-actions/enable-ckeditor-plugin.md) | Initialises a CKEditor 5 plugin with its default configuration in an editor text format. Useful for plugins that have no toolbar button. |
| [`addButtonPluginIntoActiveToolbar`](config-actions/add-button-plugin-into-active-toolbar.md) | Adds a toolbar button at a specific position and optionally registers plugin settings in one action. |
| [`updatePluginSettings`](config-actions/update-plugin-settings.md) | Replaces the settings of an already-configured CKEditor 5 plugin in an editor text format. |
| [`setEntityReferenceHandler`](config-actions/set-entity-reference-handler.md) | Updates the entity reference selection handler (and optional handler settings) on a `field_config`, replacing the `handler` while merging into `handler_settings`. |
| [`setAiContextItemsDefaultScope`](config-actions/set-ai-context-items-default-scope.md) | Assigns a scope (Global, Use Case, Tag, Site Section, Entity Bundle, Target Entity, Language) to `ai_context_item` content entities created by a recipe's content step — works around the fact that Drupal core's default-content importer cannot write to `map` fields. |

---

## Requirements

- [Project Browser](https://www.drupal.org/project/project_browser) module
- Drupal ~11.4.0

## Install with Composer

```bash
composer require drupal/varbase_recipes
```

---

## Resources

- [Varbase on Drupal.org](https://www.drupal.org/project/varbase)
- [Varbase Recipes on Drupal.org](https://www.drupal.org/project/varbase_recipes)
- [Issue queue](https://www.drupal.org/project/issues/varbase_recipes)
- [Varbase Documentation](https://docs.varbase.vardot.com)
- [Varbase Slack](http://varbase.slack.com)

Sponsored and developed by [Vardot](https://www.drupal.org/vardot).
