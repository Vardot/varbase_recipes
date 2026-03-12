<?php

declare(strict_types=1);

namespace Drupal\varbase_recipes\Plugin\ProjectBrowserSource;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\project_browser\Attribute\ProjectBrowserSource;
use Drupal\project_browser\Plugin\ProjectBrowserSource\Recipes as CoreRecipesSource;
use Drupal\project_browser\Plugin\ProjectBrowserSourceBase;
use Drupal\project_browser\ProjectBrowser\Filter\TextFilter;
use Drupal\project_browser\ProjectBrowser\Project;
use Drupal\project_browser\ProjectBrowser\ProjectsResultsPage;
use Drupal\project_browser\ProjectType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;

/**
 * Project Browser source that surfaces varbase_* recipes.
 */
#[ProjectBrowserSource(
  id: 'varbase_recipes',
  label: new TranslatableMarkup('Varbase recipes'),
  description: new TranslatableMarkup('Recipes prefixed with "varbase_" available in this codebase.'),
  local_task: ['weight' => 0],
)]
final class VarbaseRecipes extends ProjectBrowserSourceBase {

  use StringTranslationTrait;

  public function __construct(
    private readonly FileSystemInterface $fileSystem,
    private readonly CacheBackendInterface $cacheBin,
    private readonly ModuleExtensionList $moduleList,
    private readonly string $appRoot,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    mixed ...$arguments,
  ) {
    parent::__construct(...$arguments);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    assert(is_string($container->getParameter('app.root')));
    return new static(
      $container->get(FileSystemInterface::class),
      $container->get('cache.project_browser'),
      $container->get(ModuleExtensionList::class),
      $container->getParameter('app.root'),
      $container->get('file_url_generator'),
      ...array_slice(func_get_args(), 1),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFilterDefinitions(): array {
    return [
      'search' => new TextFilter('', $this->t('Search')),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getProjects(array $query = []): ProjectsResultsPage {
    $cache_id = $this->getPluginId();
    if ($cached = $this->cacheBin->get($cache_id)) {
      $projects = $cached->data;
    }
    else {
      $projects = [];

      $logo_url = $this->moduleList->getPath('project_browser') . '/images/recipe-logo.svg';
      $logo_url = 'base:' . $this->fileUrlGenerator->generateString($logo_url);

      foreach ($this->getFinder() as $file) {
        $path = $file->getPath();
        $machine_name = basename($path);
        if (!str_starts_with($machine_name, 'varbase_')) {
          continue;
        }

        [$package_name, $homepage] = $this->getPackageMetadata($path);
        $recipe = Yaml::decode($file->getContents()) ?? [];
        $title = $recipe['name'] ?? $machine_name;
        $description = $recipe['description'] ?? NULL;

        $projects[] = new Project(
          logo: Url::fromUri($logo_url),
          isCompatible: TRUE,
          machineName: $machine_name,
          body: $description ? ['summary' => $this->t($description)] : [],
          title: $this->t($title),
          packageName: $package_name,
          url: $homepage ? Url::fromUri($homepage) : NULL,
          type: ProjectType::Recipe,
        );
      }

      usort($projects, static fn(Project $a, Project $b): int => strcasecmp((string) $a->title, (string) $b->title));
      $this->cacheBin->set($cache_id, $projects);
    }

    if (!empty($query['machine_name'])) {
      $projects = array_filter($projects, static fn(Project $project): bool => $project->machineName === $query['machine_name']);
    }

    if (!empty($query['search'])) {
      $projects = array_filter($projects, static fn(Project $project): bool => stripos((string) $project->title, $query['search']) !== FALSE);
    }

    $total = count($projects);

    if (array_key_exists('page', $query) && !empty($query['limit'])) {
      $projects = array_chunk($projects, $query['limit'])[$query['page']] ?? [];
    }

    return $this->createResultsPage($projects, $total);
  }

  /**
   * Finder configured to locate recipe.yml files.
   */
  private function getFinder(): Finder {
    $search_in = [];

    $recipes_dir = CoreRecipesSource::getRecipesPath();
    if ($recipes_dir) {
      if (basename($recipes_dir) === '{$name}') {
        $recipes_dir = dirname($recipes_dir);
      }
      $resolved = $this->fileSystem->realpath($recipes_dir);
      if (is_string($resolved)) {
        $search_in[] = $resolved;
      }
    }

    $project_recipes = $this->fileSystem->realpath($this->appRoot . '/../recipes');
    if (is_string($project_recipes)) {
      $search_in[] = $project_recipes;
    }

    if (!$search_in) {
      $search_in[] = $this->appRoot;
    }

    return Finder::create()
      ->files()
      ->in(array_unique($search_in))
      ->depth(1)
      ->followLinks()
      ->name('recipe.yml');
  }

  /**
   * Read Composer metadata for a recipe directory.
   */
  private function getPackageMetadata(string $recipe_path): array {
    $package_name = 'varbase/unknown';
    $homepage = NULL;
    $composer = $recipe_path . '/composer.json';
    if (file_exists($composer)) {
      $package = file_get_contents($composer);
      assert(is_string($package));
      $package = Json::decode($package);
      $package_name = $package['name'] ?? $package_name;
      $homepage = $package['homepage'] ?? NULL;
    }

    return [$package_name, $homepage];
  }

}
