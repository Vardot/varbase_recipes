<?php

declare(strict_types=1);

namespace Drupal\varbase_recipes\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\editor\EditorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Config action to update plugin settings in CKEditor 5 plugin settings.
 *
 * Replaces the settings stored under `settings.plugins.<plugin_name>` on an
 * editor config entity. The update is only applied if the plugin key already
 * exists in the editor's plugin settings — if it is absent this action is a
 * no-op. Use `enableCKEditorPlugin` to initialise a plugin that is not yet
 * present.
 *
 * Example recipe usage:
 * @code
 * config:
 *   actions:
 *     editor.editor.full_html:
 *       updatePluginSettings:
 *         plugin_name: ckeditor5_paste_filter_pasteFilter
 *         plugin_settings:
 *           enabled: true
 * @endcode
 *
 * @internal
 *   This API is experimental.
 */
#[ConfigAction(
  id: 'updatePluginSettings',
  admin_label: new TranslatableMarkup('Update plugin settings in CKEditor 5 plugin settings'),
  entity_types: ['editor'],
)]
final class UpdatePluginSettings implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs an UpdatePluginSettings plugin.
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
   *   The editor config name (e.g. 'editor.editor.full_html').
   * @param mixed $value
   *   An associative array with the following keys:
   *   - plugin_name (string, required): The CKEditor 5 plugin key under
   *     settings.plugins (e.g. 'ckeditor5_paste_filter_pasteFilter').
   *   - plugin_settings (array, optional): The new settings to store.
   */
  public function apply(string $configName, mixed $value): void {
    assert(is_array($value) && isset($value['plugin_name']) && is_string($value['plugin_name']),
      'The value for updatePluginSettings must be an array with a string "plugin_name" key.'
    );

    $pluginName = $value['plugin_name'];
    $pluginSettings = $value['plugin_settings'] ?? [];
    assert(is_array($pluginSettings));

    $editor = $this->configManager->loadConfigEntityByName($configName);
    assert($editor instanceof EditorInterface);

    $settings = $editor->getSettings();

    // Only update if the plugin is already configured.
    if (!array_key_exists($pluginName, $settings['plugins'])) {
      return;
    }

    $settings['plugins'][$pluginName] = $pluginSettings;
    $editor->setSettings($settings)->save();

    $this->logger->info('Updated plugin settings for @plugin in @config.', [
      '@plugin' => $pluginName,
      '@config' => $configName,
    ]);
  }

}
