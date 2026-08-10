[![Varbase](https://raw.githubusercontent.com/Vardot/varbase/11.0.x/images/varbase-logo.png)](https://www.drupal.org/project/varbase)

# Varbase Recipes
[![pipeline status](https://git.drupalcode.org/project/varbase_recipes/badges/1.0.x/pipeline.svg)](https://git.drupalcode.org/project/varbase_recipes/-/pipelines)
[![Varbase Recipes](https://img.shields.io/badge/Varbase%20Recipes-1.0.0--beta4-0d6efc?labelColor=001d38&style=flat-square)](https://git.drupalcode.org/project/varbase_recipes/-/pipelines?ref=1.0.0-beta4)
[![Automated Functional Testing](https://git.drupalcode.org/project/varbase_project/badges/11.0.x/pipeline.svg)](https://git.drupalcode.org/project/varbase_project/-/pipelines)

Provides general custom config action plugins. Manages custom optional recipes
for Drupal root projects with Varbase recipes.

## Overview

The Varbase Recipes module extends the
[Drupal Recipes Config Actions API](https://project.pages.drupalcode.org/distributions_recipes/config_actions.html)
with custom plugins that automate post-install setup steps, such as downloading
third-party library files. It also provides an activator for `varbase_*`
recipes in the Project Browser.

## Use With [Varbase](https://www.drupal.org/project/varbase) Distribution

This module is best used with [Varbase](https://www.drupal.org/project/varbase)
distribution. It can also be installed with any Drupal 11 site, even with the
Minimal or Standard profile. Using it with Varbase gives you way much more cool
stuff!

## Requirements

- [Project Browser](https://www.drupal.org/project/project_browser) module.
- Drupal ~11.4.0

## Custom Config Actions

For full documentation of the custom config action plugins provided by this
module, visit:
[https://project.pages.drupalcode.org/varbase_recipes](https://project.pages.drupalcode.org/varbase_recipes)

## Project Resources

For a full description of the module, visit the
[project page](https://www.drupal.org/project/varbase_recipes).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/varbase_recipes).

## [Varbase documentation](https://docs.varbase.vardot.com)

Check out Varbase documentation for more details.

Join Our Slack Team for Feedback and Support
http://varbase.slack.com

## Install with Composer

To install the most recent stable release run:

```bash
composer require drupal/varbase_recipes
```

---

This module is sponsored and developed by [Vardot](https://www.drupal.org/vardot).
