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
 * Config action to add a button plugin and settings into the active toolbar.
 *
 * Adds a button to the active CKEditor 5 toolbar and optionally registers
 * the associated plugin settings at the same time. The button is not added
 * if it already exists (idempotent).
 *
 * Position behaviour:
 * - Omit `button_index` (or set to `-1`) to append at the end (default).
 * - Set `button_index` to `0` to prepend at the beginning.
 * - Set `button_index` to any positive integer to insert at that position.
 *
 * Example — append a button and register plugin settings:
 * @code
 * config:
 *   actions:
 *     editor.editor.full_html:
 *       addButtonPluginIntoActiveToolbar:
 *         button_name: myButton
 *         plugin_name: my_module_myPlugin
 *         plugin_settings:
 *           enabled: true
 *           option_a: foo
 * @endcode
 *
 * Example — insert at a specific position with no extra plugin settings:
 * @code
 * config:
 *   actions:
 *     editor.editor.full_html:
 *       addButtonPluginIntoActiveToolbar:
 *         button_name: myButton
 *         button_index: 3
 * @endcode
 *
 * @internal
 *   This API is experimental.
 */
#[ConfigAction(
  id: 'addButtonPluginIntoActiveToolbar',
  admin_label: new TranslatableMarkup('Add a button plugin and settings into the Active toolbar'),
  entity_types: ['editor'],
)]
final class AddButtonPluginIntoActiveToolbar implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs an AddButtonPluginIntoActiveToolbar plugin.
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
   *   - button_name (string, required): The toolbar button / item name.
   *   - button_index (int, optional): Position to insert at. -1 = append
   *     (default), 0 = prepend, positive integer = specific index.
   *   - plugin_name (string, optional): CKEditor 5 plugin key to register in
   *     settings.plugins. Omit or leave empty to skip plugin registration.
   *   - plugin_settings (array, optional): Settings to store under plugin_name.
   */
  public function apply(string $configName, mixed $value): void {
    assert(is_array($value) && isset($value['button_name']) && is_string($value['button_name']),
      'The value for addButtonPluginIntoActiveToolbar must be an array with a string "button_name" key.'
    );

    $buttonName = $value['button_name'];
    $buttonIndex = (int) ($value['button_index'] ?? -1);
    $pluginName = (string) ($value['plugin_name'] ?? '');
    $pluginSettings = $value['plugin_settings'] ?? [];
    assert(is_array($pluginSettings));

    $editor = $this->configManager->loadConfigEntityByName($configName);
    if (!$editor instanceof EditorInterface) {
      // The targeted editor does not exist on this site (wrong machine name,
      // or a recipe input that was never collected). Skip with a warning so
      // one missing editor cannot abort the whole recipe apply.
      $this->logger->warning('Skipped adding button @button: editor config @config does not exist.', [
        '@button' => $buttonName,
        '@config' => $configName,
      ]);
      return;
    }

    $settings = $editor->getSettings();

    // Skip if the button is already in the toolbar.
    if (in_array($buttonName, $settings['toolbar']['items'])) {
      return;
    }

    if ($buttonIndex === -1) {
      $settings['toolbar']['items'][] = $buttonName;
    }
    elseif ($buttonIndex === 0) {
      array_unshift($settings['toolbar']['items'], $buttonName);
    }
    else {
      array_splice($settings['toolbar']['items'], $buttonIndex, 0, $buttonName);
    }

    if ($pluginName !== '') {
      $settings['plugins'][$pluginName] = $pluginSettings;
    }

    $editor->setSettings($settings)->save();

    $this->logger->info('Added button @button to toolbar of @config.', [
      '@button' => $buttonName,
      '@config' => $configName,
    ]);
  }

}
