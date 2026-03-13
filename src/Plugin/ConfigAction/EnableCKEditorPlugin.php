<?php

declare(strict_types=1);

namespace Drupal\varbase_recipes\Plugin\ConfigAction;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginManagerInterface;
use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\editor\EditorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Config action to enable a CKEditor 5 plugin with its default configuration.
 *
 * Use this to initialise CKEditor 5 plugins that have no toolbar button and
 * therefore cannot be activated via the `addItemToToolbar` config action. The
 * action reads the plugin's `defaultConfiguration()`, optionally merges in
 * any overrides you supply, and writes the result into
 * `settings.plugins.<plugin_id>` on the editor config entity.
 *
 * If the plugin key already exists in the editor's plugin settings this action
 * is a no-op, so it is safe to apply more than once.
 *
 * Only configurable plugins (those implementing
 * `CKEditor5PluginConfigurableInterface`) are supported. For
 * non-configurable plugins you can pass a `config` array directly and it will
 * be stored as-is; no default configuration is fetched.
 *
 * Example recipe usage:
 * @code
 * config:
 *   actions:
 *     editor.editor.full_html:
 *       enableCKEditorPlugin:
 *         plugin_id: ckeditor5_paste_filter_pasteFilter
 *         config:
 *           enabled: true
 * @endcode
 *
 * @internal
 *   This API is experimental.
 */
#[ConfigAction(
  id: 'enableCKEditorPlugin',
  admin_label: new TranslatableMarkup('Enable a CKEditor 5 plugin with default configuration'),
  entity_types: ['editor'],
)]
final class EnableCKEditorPlugin implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs an EnableCKEditorPlugin plugin.
   *
   * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
   *   The config manager service.
   * @param \Drupal\ckeditor5\Plugin\CKEditor5PluginManagerInterface $pluginManager
   *   The CKEditor 5 plugin manager service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger for recording results.
   */
  public function __construct(
    private readonly ConfigManagerInterface $configManager,
    private readonly CKEditor5PluginManagerInterface $pluginManager,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get(ConfigManagerInterface::class),
      $container->get(CKEditor5PluginManagerInterface::class),
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
   *   - plugin_id (string, required): The CKEditor 5 plugin ID.
   *   - config (array, optional): Configuration overrides to merge on top of
   *     the plugin's default configuration.
   */
  public function apply(string $configName, mixed $value): void {
    assert(is_array($value) && isset($value['plugin_id']) && is_string($value['plugin_id']),
      'The value for enableCKEditorPlugin must be an array with a string "plugin_id" key.'
    );

    $pluginId = $value['plugin_id'];
    $configOverride = $value['config'] ?? [];
    assert(is_array($configOverride));

    $editor = $this->configManager->loadConfigEntityByName($configName);
    assert($editor instanceof EditorInterface);

    $settings = $editor->getSettings();

    // Skip if the plugin is already configured.
    if (array_key_exists($pluginId, $settings['plugins'])) {
      return;
    }

    $definitions = $this->pluginManager->getDefinitions();

    if (isset($definitions[$pluginId]) && $definitions[$pluginId]->isConfigurable()) {
      /** @var \Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface $plugin */
      $plugin = $this->pluginManager->getPlugin($pluginId, NULL);
      assert($plugin instanceof CKEditor5PluginConfigurableInterface);
      $pluginConfig = $plugin->defaultConfiguration();

      if ($configOverride) {
        $pluginConfig = array_replace_recursive($pluginConfig, $configOverride);
      }
    }
    else {
      $pluginConfig = $configOverride;
    }

    $settings['plugins'][$pluginId] = $pluginConfig;
    $editor->setSettings($settings)->save();

    $this->logger->info('Enabled CKEditor 5 plugin @plugin_id in @config.', [
      '@plugin_id' => $pluginId,
      '@config' => $configName,
    ]);
  }

}
