<?php

declare(strict_types=1);

namespace Drupal\varbase_recipes\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Config action to repoint a Canvas component tree onto another theme.
 *
 * A base recipe ships its Drupal Canvas content templates against the base
 * theme (Vartheme BS5), because a recipe's config files are written before any
 * site template theme exists. A site template with its own theme then has to
 * re-declare the whole component tree only to change the theme in every
 * component ID. This action does that repointing in one line.
 *
 * For every component in the tree whose ID is `sdc.<some theme>.<name>`, the
 * theme part is replaced with the target theme, provided that theme really has
 * a component with the same name. The component version is re-resolved to the
 * target component's active version, and any input that is not a prop of that
 * version is dropped — pinned versions and props go stale whenever a theme
 * changes, and a stale pin aborts the install.
 *
 * Components the target theme does not have (and non-SDC components, such as
 * blocks and views blocks) are left untouched.
 *
 * Example — follow whatever theme the site ended up with:
 * @code
 * config:
 *   actions:
 *     canvas.content_template.node.event.full:
 *       repointComponentTreeToTheme:
 *         theme: default
 * @endcode
 *
 * Example — name the theme explicitly:
 * @code
 * config:
 *   actions:
 *     canvas.content_template.node.event.text_card_medium:
 *       repointComponentTreeToTheme:
 *         theme: vartheme_bs5_educare
 * @endcode
 *
 * @internal
 *   This API is experimental.
 */
#[ConfigAction(
  id: 'repointComponentTreeToTheme',
  admin_label: new TranslatableMarkup('Repoint a Canvas component tree onto another theme'),
  entity_types: ['content_template', 'page_region', 'canvas_page'],
)]
final class RepointComponentTreeToTheme implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a RepointComponentTreeToTheme plugin.
   *
   * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
   *   The config manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory, used to read the site's default theme.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager, used to load Canvas component entities.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger for recording results.
   */
  public function __construct(
    private readonly ConfigManagerInterface $configManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get(ConfigManagerInterface::class),
      $container->get('config.factory'),
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
   *   An array with a 'theme' key: either a theme machine name, or 'default'
   *   to use the site's default theme.
   */
  public function apply(string $configName, mixed $value): void {
    assert(is_array($value) && isset($value['theme']) && is_string($value['theme']),
      'The value for repointComponentTreeToTheme must be an array with a string "theme" key.'
    );

    $theme = $value['theme'] === 'default'
      ? (string) $this->configFactory->get('system.theme')->get('default')
      : $value['theme'];

    $entity = $this->configManager->loadConfigEntityByName($configName);
    if ($entity === NULL) {
      $this->logger->warning('Could not load @config. Skipping repointComponentTreeToTheme action.', [
        '@config' => $configName,
      ]);
      return;
    }

    $tree = $entity->get('component_tree');
    if (!is_array($tree) || $tree === []) {
      return;
    }

    $components = $this->entityTypeManager->getStorage('component');
    $repointed = 0;

    foreach ($tree as $uuid => $item) {
      $id = $item['component_id'] ?? '';
      if (!str_starts_with($id, 'sdc.')) {
        continue;
      }
      [, , $name] = explode('.', $id, 3) + [NULL, NULL, NULL];
      if ($name === NULL) {
        continue;
      }

      $target_id = sprintf('sdc.%s.%s', $theme, $name);
      $target = $components->load($target_id);
      if ($target === NULL) {
        // The target theme does not provide this component: leave it alone, it
        // still renders through the theme it came from.
        continue;
      }

      $tree[$uuid]['component_id'] = $target_id;
      $tree[$uuid]['component_version'] = $target->getActiveVersion();

      // Drop inputs the target version does not declare as props, so that a
      // renamed or removed prop cannot abort the recipe.
      $props = array_keys($target->get('settings')['prop_field_definitions'] ?? []);
      if ($props !== [] && isset($tree[$uuid]['inputs']) && is_array($tree[$uuid]['inputs'])) {
        foreach (array_keys($tree[$uuid]['inputs']) as $prop) {
          if (!in_array($prop, $props, TRUE)) {
            unset($tree[$uuid]['inputs'][$prop]);
          }
        }
      }
      $repointed++;
    }

    if ($repointed === 0) {
      return;
    }

    $entity->set('component_tree', $tree)->save();

    $this->logger->info('Repointed @count components in @config onto the @theme theme.', [
      '@count' => $repointed,
      '@config' => $configName,
      '@theme' => $theme,
    ]);
  }

}
