<?php

declare(strict_types=1);

namespace Drupal\varbase_recipes\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\ViewEntityInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Config action to point a view's component style at another theme.
 *
 * A view rendered through the components views style names the theme in its
 * style options, e.g. `vartheme_bs5:views-view-grid`, and a view whose exposed
 * filters render through a component names it again in its exposed form
 * options. A site template with its own theme has to change that in every
 * display. This action rewrites the theme part of both
 * `style.options.component_id` and `exposed_form.options.component_id` on every
 * display that has one, leaving the component name (`views-view-grid`,
 * `views-exposed-filters`) alone.
 *
 * Example:
 * @code
 * config:
 *   actions:
 *     views.view.events:
 *       setViewsComponentStyleTheme:
 *         theme: default
 * @endcode
 *
 * @internal
 *   This API is experimental.
 */
#[ConfigAction(
  id: 'setViewsComponentStyleTheme',
  admin_label: new TranslatableMarkup('Point a view component style at another theme'),
  entity_types: ['view'],
)]
final class SetViewsComponentStyleTheme implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a SetViewsComponentStyleTheme plugin.
   *
   * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
   *   The config manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory, used to read the site's default theme.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger for recording results.
   */
  public function __construct(
    private readonly ConfigManagerInterface $configManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get(ConfigManagerInterface::class),
      $container->get('config.factory'),
      $container->get('logger.channel.varbase_recipes'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param string $configName
   *   The view config name, e.g. 'views.view.events'.
   * @param mixed $value
   *   An array with a 'theme' key: either a theme machine name, or 'default'
   *   to use the site's default theme.
   */
  public function apply(string $configName, mixed $value): void {
    assert(is_array($value) && isset($value['theme']) && is_string($value['theme']),
      'The value for setViewsComponentStyleTheme must be an array with a string "theme" key.'
    );

    $theme = $value['theme'] === 'default'
      ? (string) $this->configFactory->get('system.theme')->get('default')
      : $value['theme'];

    $view = $this->configManager->loadConfigEntityByName($configName);
    if (!$view instanceof ViewEntityInterface) {
      $this->logger->warning('Could not load view @config. Skipping setViewsComponentStyleTheme action.', [
        '@config' => $configName,
      ]);
      return;
    }

    $displays = $view->get('display');
    $changed = 0;

    foreach ($displays as $id => $display) {
      foreach (['style', 'exposed_form'] as $plugin) {
        $component = $display['display_options'][$plugin]['options']['component_id'] ?? NULL;
        if (!is_string($component) || !str_contains($component, ':')) {
          continue;
        }
        [, $name] = explode(':', $component, 2);
        $target = $theme . ':' . $name;
        if ($target === $component) {
          continue;
        }
        $displays[$id]['display_options'][$plugin]['options']['component_id'] = $target;
        $changed++;
      }
    }

    if ($changed === 0) {
      return;
    }

    $view->set('display', $displays)->save();

    $this->logger->info('Pointed @count view components in @config at the @theme theme.', [
      '@count' => $changed,
      '@config' => $configName,
      '@theme' => $theme,
    ]);
  }

}
