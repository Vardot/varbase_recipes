<?php

declare(strict_types=1);

namespace Drupal\varbase_recipes\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field\FieldConfigInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Config action to update the entity reference selection handler on a field.
 *
 * Updates the `handler` key in the field settings, and optionally merges
 * new values into `handler_settings`. Existing handler settings not included
 * in the action value are preserved.
 *
 * Example — switch from ECA handler to default taxonomy handler:
 * @code
 * config:
 *   actions:
 *     field.field.node.blog.field_tags:
 *       setEntityReferenceHandler:
 *         handler: 'default:taxonomy_term'
 * @endcode
 *
 * Example — switch handler and override specific handler settings:
 * @code
 * config:
 *   actions:
 *     field.field.node.${content_type}.${field_name}:
 *       setEntityReferenceHandler:
 *         handler: 'default:taxonomy_term'
 *         handler_settings:
 *           auto_create: true
 * @endcode
 *
 * @internal
 *   This API is experimental.
 */
#[ConfigAction(
  id: 'setEntityReferenceHandler',
  admin_label: new TranslatableMarkup('Set entity reference selection handler on a field config'),
  entity_types: ['field_config'],
)]
final class SetEntityReferenceHandler implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a SetEntityReferenceHandler plugin.
   *
   * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
   *   The config manager service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger for recording results.
   */
  public function __construct(
    private readonly ConfigManagerInterface $configManager,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get(ConfigManagerInterface::class),
      $container->get('logger.channel.varbase_recipes'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param string $configName
   *   The field config name (e.g. 'field.field.node.blog.field_tags').
   * @param mixed $value
   *   An associative array with the following keys:
   *   - handler (string, required): The entity reference selection handler
   *     plugin ID to set (e.g. 'default:taxonomy_term').
   *   - handler_settings (array, optional): Settings to merge into the
   *     existing handler_settings. Existing keys not listed here are kept.
   */
  public function apply(string $configName, mixed $value): void {
    assert(is_array($value) && isset($value['handler']) && is_string($value['handler']),
      'The value for setEntityReferenceHandler must be an array with a string "handler" key.'
    );

    $field = $this->configManager->loadConfigEntityByName($configName);
    if (!$field instanceof FieldConfigInterface) {
      $this->logger->warning('Could not load field config entity for @config. Skipping setEntityReferenceHandler action.', [
        '@config' => $configName,
      ]);
      return;
    }

    $settings = $field->getSettings();
    $previousHandler = $settings['handler'] ?? '';

    $settings['handler'] = $value['handler'];

    if (isset($value['handler_settings']) && is_array($value['handler_settings'])) {
      $settings['handler_settings'] = array_merge(
        $settings['handler_settings'] ?? [],
        $value['handler_settings']
      );
    }

    $field->setSettings($settings)->save();

    $this->logger->info('Updated entity reference handler for @config from @from to @to.', [
      '@config' => $configName,
      '@from' => $previousHandler,
      '@to' => $value['handler'],
    ]);
  }

}
