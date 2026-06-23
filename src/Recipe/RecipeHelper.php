<?php

declare(strict_types=1);

namespace Drupal\varbase_recipes\Recipe;

use Drupal\Component\Serialization\Yaml as SerializationYaml;
use Drupal\Core\Recipe\Recipe;
use Symfony\Component\Yaml\Yaml;

/**
 * General-purpose helper methods for working with Drupal Recipes.
 *
 * Provides static utilities for loading, creating, and dynamically modifying
 * recipe data at runtime — for example inside hook_update_N() or hook_install()
 * implementations that need to apply a recipe with a position-aware toolbar
 * change.
 *
 * CKEditor 5 toolbar helpers support both the core `addItemToToolbar` config
 * action and the `addButtonPluginIntoActiveToolbar` config action provided by
 * this module.
 */
class RecipeHelper {

  /**
   * Returns the raw YAML content of a recipe.yml file as a string.
   *
   * Accepts either a path to the recipe directory or a direct path to the
   * recipe.yml file.
   *
   * @param string $recipePath
   *   Path to the recipe directory or to the recipe.yml file.
   *
   * @return string
   *   The raw YAML string.
   */
  public static function getRecipeString(string $recipePath): string {
    return file_get_contents(self::resolveRecipePath($recipePath));
  }

  /**
   * Parses a recipe.yml file and returns it as a PHP array.
   *
   * Accepts either a path to the recipe directory or a direct path to the
   * recipe.yml file.
   *
   * @param string $recipePath
   *   Path to the recipe directory or to the recipe.yml file.
   *
   * @return array<mixed>
   *   The parsed recipe data.
   */
  public static function getRecipeData(string $recipePath): array {
    return (array) Yaml::parse(file_get_contents(self::resolveRecipePath($recipePath)));
  }

  /**
   * Creates a Recipe object from a YAML string or a data array.
   *
   * Writes the recipe to a temporary directory and calls
   * Recipe::createFromDirectory(). Useful when you need to build a recipe
   * programmatically (e.g. to inject a runtime toolbar position) before
   * passing it to RecipeRunner::processRecipe().
   *
   * @param string|array<mixed> $data
   *   Recipe contents as a raw YAML string or as a PHP array. Arrays are
   *   encoded to YAML automatically.
   * @param string|null $machine_name
   *   Optional machine name used as the temporary directory name. When NULL
   *   a unique name is generated automatically.
   *
   * @return \Drupal\Core\Recipe\Recipe
   *   The Recipe object ready to be passed to RecipeRunner::processRecipe().
   */
  public static function createRecipe(string|array $data, ?string $machine_name = NULL): Recipe {
    if (is_array($data)) {
      $data = SerializationYaml::encode($data);
    }

    $temp_recipes_dir = \Drupal::service('file_system')->getTempDirectory() . '/recipes';
    $dir = $machine_name === NULL
      ? uniqid($temp_recipes_dir . '/')
      : $temp_recipes_dir . '/' . $machine_name;

    mkdir($dir, recursive: TRUE);
    file_put_contents($dir . '/recipe.yml', $data);

    return Recipe::createFromDirectory($dir);
  }

  // ---------------------------------------------------------------------------
  // CKEditor 5 toolbar helpers
  // ---------------------------------------------------------------------------

  /**
   * Returns the current toolbar position of an item in a CKEditor 5 editor.
   *
   * @param string $editorConfigName
   *   The editor config name (e.g. 'editor.editor.full_html').
   * @param string $itemName
   *   The toolbar item name (e.g. 'bold', 'fullScreen', '|').
   *
   * @return int
   *   Zero-based index of the item in the toolbar, or -1 if not found.
   */
  public static function getToolbarItemPosition(string $editorConfigName, string $itemName): int {
    $items = \Drupal::service('config.factory')
      ->getEditable($editorConfigName)
      ->get('settings.toolbar.items');

    if (!isset($items) || !is_array($items)) {
      return -1;
    }

    foreach ($items as $index => $item) {
      if ($item === $itemName) {
        return $index;
      }
    }

    return -1;
  }

  /**
   * Returns whether a toolbar item exists in a CKEditor 5 editor.
   *
   * @param string $editorConfigName
   *   The editor config name (e.g. 'editor.editor.full_html').
   * @param string $itemName
   *   The toolbar item name.
   *
   * @return bool
   *   TRUE if the item is present in the toolbar.
   */
  public static function toolbarItemExists(string $editorConfigName, string $itemName): bool {
    return self::getToolbarItemPosition($editorConfigName, $itemName) !== -1;
  }

  /**
   * Sets the `position` key for an `addItemToToolbar` action in recipe data.
   *
   * Used together with getToolbarItemPosition() to insert an item relative to
   * another item already in the toolbar. Has no effect when $position is -1
   * (append behaviour).
   *
   * @param array<mixed> $recipeData
   *   The recipe data array (modified in place).
   * @param string $editorConfigName
   *   The editor config name the action targets.
   * @param int $position
   *   The desired toolbar position. Pass -1 to leave the recipe unchanged
   *   (append at end).
   */
  public static function setAddItemPosition(array &$recipeData, string $editorConfigName, int $position): void {
    if ($position !== -1) {
      $recipeData['config']['actions'][$editorConfigName]['addItemToToolbar']['position'] = $position;
    }
  }

  /**
   * Sets the `button_index` key for an addButtonPluginIntoActiveToolbar action.
   *
   * Used together with getToolbarItemPosition() to insert a button relative to
   * another item already in the toolbar. Has no effect when $position is -1
   * (append behaviour).
   *
   * @param array<mixed> $recipeData
   *   The recipe data array (modified in place).
   * @param string $editorConfigName
   *   The editor config name the action targets.
   * @param int $position
   *   The desired toolbar position. Pass -1 to leave the recipe unchanged
   *   (append at end).
   */
  public static function setButtonIndex(array &$recipeData, string $editorConfigName, int $position): void {
    if ($position !== -1) {
      $recipeData['config']['actions'][$editorConfigName]['addButtonPluginIntoActiveToolbar']['button_index'] = $position;
    }
  }

  /**
   * Checks whether an editor's CKEditor 5 block styles match a given array.
   *
   * Useful in update hooks to determine whether a style update has already
   * been applied before re-running a recipe.
   *
   * @param string $editorConfigName
   *   The editor config name (e.g. 'editor.editor.full_html').
   * @param array<mixed> $blockStyles
   *   The expected styles array
   *   (settings.plugins.ckeditor5_style.styles format).
   *
   * @return bool
   *   TRUE if the current styles exactly match $blockStyles (i.e. the update
   *   has not yet been applied).
   */
  public static function blockStylesUnchanged(string $editorConfigName, array $blockStyles): bool {
    $current = \Drupal::service('config.factory')
      ->getEditable($editorConfigName)
      ->get('settings.plugins.ckeditor5_style.styles');

    return $blockStyles === $current;
  }

  // ---------------------------------------------------------------------------
  // Internal helpers
  // ---------------------------------------------------------------------------

  /**
   * Resolves a recipe path to the actual recipe.yml file path.
   *
   * @param string $recipePath
   *   Path to a recipe directory or directly to a recipe.yml file.
   *
   * @return string
   *   Absolute path to the recipe.yml file.
   */
  private static function resolveRecipePath(string $recipePath): string {
    if (str_ends_with($recipePath, '/recipe.yml')) {
      return $recipePath;
    }

    return rtrim($recipePath, '/') . '/recipe.yml';
  }

}
