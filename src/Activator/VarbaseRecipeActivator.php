<?php

declare(strict_types=1);

namespace Drupal\varbase_recipes\Activator;

use Drupal\Core\Recipe\RecipePreExistingConfigException;
use Drupal\project_browser\Activator\ActivationStatus;
use Drupal\project_browser\Activator\InstructionsInterface;
use Drupal\project_browser\Activator\RecipeActivator;
use Drupal\project_browser\Activator\TasksInterface;
use Drupal\project_browser\ProjectBrowser\Project;
use Drupal\project_browser\ProjectType;

/**
 * Activator for varbase_* recipes, wrapping RecipeActivator.
 *
 * Delegates all activation logic to the core RecipeActivator but catches
 * RecipePreExistingConfigException in getTasks() so that recipes applied
 * during Varbase profile installation do not cause 500 errors when the
 * Project Browser API renders the recipe list.
 *
 * @internal
 */
final class VarbaseRecipeActivator implements TasksInterface, InstructionsInterface {

  public function __construct(
    private readonly RecipeActivator $recipeActivator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function supports(Project $project): bool {
    return $project->type === ProjectType::Recipe
      && str_starts_with($project->machineName, 'varbase_');
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(Project $project): ActivationStatus {
    return $this->recipeActivator->getStatus($project);
  }

  /**
   * {@inheritdoc}
   */
  public function activate(Project $project): ?array {
    return $this->recipeActivator->activate($project);
  }

  /**
   * {@inheritdoc}
   */
  public function getTasks(Project $project, ?string $source_id = NULL): array {
    try {
      return $this->recipeActivator->getTasks($project, $source_id);
    }
    catch (RecipePreExistingConfigException) {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getInstructions(Project $project, ?string $source_id = NULL): string {
    return $this->recipeActivator->getInstructions($project, $source_id);
  }

}
