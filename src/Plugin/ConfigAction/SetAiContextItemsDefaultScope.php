<?php

declare(strict_types=1);

namespace Drupal\varbase_recipes\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Recipe\RecipeAppliedEvent;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Config action to apply a default scope to imported AI Context items.
 *
 * The `scope` field on `ai_context_item` is a map field with arbitrary keys
 * (`global`, `use_case`, `tag`, `entity_bundle`, `site_section`,
 * `target_entity`, `language`). Drupal core's DefaultContent\Importer cannot
 * set values on map fields with arbitrary keys, and `drush content:export`
 * normalises map fields to `{}` — so the scope cannot be shipped through
 * `content/ai_context_item/*.yml` files. Every recipe-imported item ends up
 * with an empty scope unless something fixes it after the import.
 *
 * This action does exactly that, while remaining inside the recipe action
 * model. The action is attached to one of the `ai_context.scope_settings.*`
 * config objects (one config object per scope plugin, shipped by the
 * `ai_context` module). The scope plugin id is derived from the config name,
 * and the action value lists the values to assign for that scope plugin.
 *
 * Because Drupal recipes run config actions before the content step, the
 * action's `apply()` method registers an in-process listener on
 * `RecipeAppliedEvent`. The listener fires after the recipe's content step
 * completes, loads every `ai_context_item` whose scope is still empty and
 * writes `[scope_id => values]` via `setScopeValues()`. Items that already
 * carry a scope are left untouched, so re-applying the recipe is a no-op.
 *
 * Example — apply Global scope to all imported items:
 * @code
 * config:
 *   actions:
 *     ai_context.scope_settings.global:
 *       setAiContextItemsDefaultScope:
 *         - global
 * @endcode
 *
 * Example — apply Use Case scope (Writing Words, Working In Canvas):
 * @code
 * config:
 *   actions:
 *     ai_context.scope_settings.use_case:
 *       setAiContextItemsDefaultScope:
 *         - writing_words
 *         - working_in_canvas
 * @endcode
 *
 * Multiple actions can be combined across scope plugins by listing several
 * `ai_context.scope_settings.*` entries — the action is idempotent across
 * the same set of items.
 *
 * @internal
 *   This API is experimental.
 */
#[ConfigAction(
  id: 'setAiContextItemsDefaultScope',
  admin_label: new TranslatableMarkup('Set default scope on imported AI Context items'),
)]
final class SetAiContextItemsDefaultScope implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Prefix of the config names this action attaches to.
   */
  private const SCOPE_CONFIG_PREFIX = 'ai_context.scope_settings.';

  /**
   * Constructs a SetAiContextItemsDefaultScope plugin.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher used to register the deferred listener.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger for recording results.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher'),
      $container->get('logger.channel.varbase_recipes'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param string $configName
   *   The scope_settings config name — for example
   *   `ai_context.scope_settings.global`. The scope plugin id is taken from
   *   the segment after the prefix.
   * @param mixed $value
   *   The list of scope values to apply for the parsed scope plugin id, for
   *   example `['global']` or `['writing_words', 'working_in_canvas']`.
   */
  public function apply(string $configName, mixed $value): void {
    if (!str_starts_with($configName, self::SCOPE_CONFIG_PREFIX)) {
      $this->logger->warning('setAiContextItemsDefaultScope expects a config name starting with @prefix, got @name.', [
        '@prefix' => self::SCOPE_CONFIG_PREFIX,
        '@name' => $configName,
      ]);
      return;
    }
    $scope_id = substr($configName, strlen(self::SCOPE_CONFIG_PREFIX));
    if ($scope_id === '') {
      $this->logger->warning('setAiContextItemsDefaultScope received empty scope id from @name.', [
        '@name' => $configName,
      ]);
      return;
    }

    $values = array_values((array) $value);
    if ($values === []) {
      $this->logger->warning('setAiContextItemsDefaultScope received empty value for scope @scope.', [
        '@scope' => $scope_id,
      ]);
      return;
    }

    $entityTypeManager = $this->entityTypeManager;
    $logger = $this->logger;

    // Defer the work until after content import has finished.
    $this->eventDispatcher->addListener(
      RecipeAppliedEvent::class,
      static function (RecipeAppliedEvent $event) use ($scope_id, $values, $entityTypeManager, $logger): void {
        if (!$entityTypeManager->hasDefinition('ai_context_item')) {
          return;
        }
        $storage = $entityTypeManager->getStorage('ai_context_item');
        $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
        if (!$ids) {
          return;
        }
        foreach ($storage->loadMultiple($ids) as $item) {
          $current = $item->get('scope')->getValue()[0] ?? [];
          if (!empty($current)) {
            continue;
          }
          $item->setScopeValues($scope_id, $values);
          $item->save();
          $logger->info('Applied @scope scope (@values) to AI Context item @label.', [
            '@scope' => $scope_id,
            '@values' => implode(', ', $values),
            '@label' => $item->label(),
          ]);
        }
      }
    );
  }

}
