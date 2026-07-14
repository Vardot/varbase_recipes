<?php

declare(strict_types=1);

namespace Drupal\varbase_recipes\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Config action to set a Canvas component tree, but only if it can render.
 *
 * A base recipe (Varbase Events Base, Varbase News Base) ships Drupal Canvas
 * templates built from the base theme's components, but must not install a
 * theme itself — that is the site template's job. The two do not line up:
 * Canvas only generates a theme's Component config entities on
 * RecipeAppliedEvent, so while a base recipe runs, the components of a theme
 * that a later recipe installs do not exist yet. A plain `simpleConfigUpdate`
 * on the tree is validated and aborts the whole site install with "The
 * 'canvas.component.sdc.<theme>.<name>' config does not exist."
 *
 * This action sets the tree only when every component it names already exists,
 * and does nothing otherwise. A site template that ships its own theme —
 * Educare, say — installs that theme and then declares its own template for the
 * same view mode, so skipping here costs nothing: the site template's action
 * runs later and wins. On a site whose theme is already installed (Varbase
 * Starter), the tree is written as normal.
 *
 * Example:
 * @code
 * config:
 *   actions:
 *     canvas.content_template.node.event.full:
 *       setComponentTreeIfComponentsExist:
 *         component_tree:
 *           some-uuid:
 *             uuid: some-uuid
 *             component_id: sdc.vartheme_bs5.section
 *             component_version: 4171719f53c41b9d
 *             inputs: {}
 * @endcode
 *
 * @internal
 *   This API is experimental.
 */
#[ConfigAction(
  id: 'setComponentTreeIfComponentsExist',
  admin_label: new TranslatableMarkup('Set a Canvas component tree if its components exist'),
  entity_types: ['content_template', 'page_region', 'canvas_page'],
)]
final class SetComponentTreeIfComponentsExist implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a SetComponentTreeIfComponentsExist plugin.
   *
   * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
   *   The config manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager, used to load Canvas component entities.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger for recording results.
   */
  public function __construct(
    private readonly ConfigManagerInterface $configManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * The Drupal Canvas service that generates Component config entities.
   *
   * Referenced by name: Drupal Canvas is not a dependency of this module, it is
   * a dependency of the recipes that use this action.
   */
  private const COMPONENT_SOURCE_MANAGER = 'Drupal\\canvas\\ComponentSource\\ComponentSourceManager';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get(ConfigManagerInterface::class),
      $container->get('entity_type.manager'),
      $container->get('logger.channel.varbase_recipes'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param string $configName
   *   The Canvas config entity name, e.g.
   *   'canvas.content_template.node.event.full'.
   * @param mixed $value
   *   An array with a 'component_tree' key: the tree to set, keyed by UUID.
   */
  public function apply(string $configName, mixed $value): void {
    assert(is_array($value) && isset($value['component_tree']) && is_array($value['component_tree']),
      'The value for setComponentTreeIfComponentsExist must be an array with a "component_tree" key.'
    );

    $entity = $this->configManager->loadConfigEntityByName($configName);
    if ($entity === NULL) {
      $this->logger->warning('Could not load @config. Skipping setComponentTreeIfComponentsExist action.', [
        '@config' => $configName,
      ]);
      return;
    }

    // Canvas generates Component config entities on RecipeAppliedEvent, so the
    // components of a view or a theme that this very recipe just created do not
    // exist yet. Generate what is installed right now, then judge.
    if (\Drupal::hasService(self::COMPONENT_SOURCE_MANAGER)) {
      \Drupal::service(self::COMPONENT_SOURCE_MANAGER)->generateComponents();
    }

    $components = $this->entityTypeManager->getStorage('component');
    $missing = [];
    foreach ($value['component_tree'] as $item) {
      $id = $item['component_id'] ?? NULL;
      if (is_string($id) && $components->load($id) === NULL) {
        $missing[$id] = $id;
      }
    }

    if ($missing) {
      $this->logger->info('Left @config alone: the site does not (yet) have @components. A site template that installs its own theme is expected to set this template itself.', [
        '@config' => $configName,
        '@components' => implode(', ', $missing),
      ]);
      return;
    }

    $entity->set('component_tree', $value['component_tree'])->save();

    $this->logger->info('Set the component tree of @config: @count components.', [
      '@config' => $configName,
      '@count' => count($value['component_tree']),
    ]);
  }

}
