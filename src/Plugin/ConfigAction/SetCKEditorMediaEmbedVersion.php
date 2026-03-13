<?php

declare(strict_types=1);

namespace Drupal\varbase_recipes\Plugin\ConfigAction;

use Drupal\ckeditor_media_embed\AssetManager;
use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Config action to set the CKEditor Media Embed plugins_version_installed.
 *
 * Detects the current CKEditor version and saves it as
 * plugins_version_installed in ckeditor_media_embed.settings. This is
 * equivalent to running:
 * @code
 * drush config-set ckeditor_media_embed.settings plugins_version_installed <version>
 * @endcode
 *
 * This is safe to run during Drupal installation (unlike the full
 * drush ckeditor_media_embed:install command, which calls
 * drupal_flush_all_caches() and fails during installation).
 *
 * The actual plugin library files (web/libraries/ckeditor5/plugins/) must
 * already be present on disk. Run the following command once after deployment
 * to download/update the files:
 * @code
 * drush ckeditor_media_embed:install
 * @endcode
 *
 * Example recipe usage:
 * @code
 * config:
 *   actions:
 *     ckeditor_media_embed.settings:
 *       setCKEditorMediaEmbedVersion: true
 * @endcode
 *
 * @internal
 *   This API is experimental.
 */
#[ConfigAction(
  id: 'setCKEditorMediaEmbedVersion',
  admin_label: new TranslatableMarkup('Set CKEditor Media Embed version'),
  entity_types: ['*'],
)]
final class SetCKEditorMediaEmbedVersion implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a SetCKEditorMediaEmbedVersion plugin.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\Asset\LibraryDiscoveryInterface $libraryDiscovery
   *   The library discovery service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger for recording results.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LibraryDiscoveryInterface $libraryDiscovery,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get(ConfigFactoryInterface::class),
      $container->get(LibraryDiscoveryInterface::class),
      $container->get('logger.channel.varbase_recipes'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * Detects the current CKEditor version and saves it as
   * plugins_version_installed in ckeditor_media_embed.settings. This is
   * equivalent to running:
   *   drush config-set ckeditor_media_embed.settings plugins_version_installed <version>
   *
   * @param string $configName
   *   The config object name this action is applied to (typically
   *   'ckeditor_media_embed.settings').
   * @param mixed $value
   *   Not used; pass TRUE or any truthy value to trigger the update.
   */
  public function apply(string $configName, mixed $value): void {
    $version = AssetManager::getCKEditorVersion($this->libraryDiscovery, $this->configFactory);

    $this->configFactory
      ->getEditable('ckeditor_media_embed.settings')
      ->set('plugins_version_installed', $version)
      ->save();

    $this->logger->info('Set ckeditor_media_embed.settings plugins_version_installed to @version.', [
      '@version' => $version,
    ]);
  }

}
