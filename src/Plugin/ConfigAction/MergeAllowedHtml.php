<?php

declare(strict_types=1);

namespace Drupal\varbase_recipes\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Config action to merge allowed HTML tags into a text format's filter settings.
 *
 * This action merges additional allowed HTML tags and attributes into the
 * existing `filters.filter_html.settings.allowed_html` value of a text format
 * config object. Unlike `simpleConfigUpdate` (which replaces the entire value),
 * this action ADDS tags/attributes to the existing list without removing what
 * is already there.
 *
 * Duplicate tags are deduplicated; for tags that appear in both the existing
 * and the new value, their attributes are merged (class values are combined;
 * other attributes are replaced by the new value).
 *
 * Example recipe usage:
 * @code
 * config:
 *   actions:
 *     filter.format.full_html:
 *       mergeAllowedHtml: '<drupal-media data-media-width> <figure class>'
 * @endcode
 *
 * @internal
 *   This API is experimental.
 */
#[ConfigAction(
  id: 'mergeAllowedHtml',
  admin_label: new TranslatableMarkup('Merge allowed HTML tags into a text format'),
  entity_types: ['filter_format'],
)]
final class MergeAllowedHtml implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a MergeAllowedHtml plugin.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger for recording results.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get(ConfigFactoryInterface::class),
      $container->get('logger.channel.varbase_recipes'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * Merges the given allowed HTML string into the existing
   * `filters.filter_html.settings.allowed_html` value of the text format
   * config identified by $configName.
   *
   * @param string $configName
   *   The filter format config name (e.g. 'filter.format.full_html').
   * @param mixed $value
   *   A string of allowed HTML tags and attributes to merge into the existing
   *   value (e.g. '<drupal-media data-media-width> <figure class>').
   */
  public function apply(string $configName, mixed $value): void {
    assert(is_string($value), 'The value for mergeAllowedHtml must be a string of allowed HTML tags.');

    $config = $this->configFactory->getEditable($configName);
    $existing = (string) ($config->get('filters.filter_html.settings.allowed_html') ?? '');

    $merged = $existing !== '' ? $this->mergeAllowedHtml($existing, $value) : $value;

    $config->set('filters.filter_html.settings.allowed_html', $merged)->save();

    $this->logger->info('Merged allowed HTML tags into @config.', ['@config' => $configName]);
  }

  /**
   * Merges two allowed HTML strings.
   *
   * Combines two strings of allowed HTML tags and attributes, ensuring:
   * - Duplicate tags are eliminated.
   * - Tags with attributes are merged if they appear in both strings with
   *   different attributes.
   * - If a tag appears without attributes but exists with attributes, the
   *   version with attributes is preferred.
   *
   * Supports custom XML tags such as `drupal-*`.
   *
   * @param string $allowedHtml1
   *   The first (existing) string of allowed HTML tags.
   * @param string $allowedHtml2
   *   The second (new) string of allowed HTML tags to merge in.
   *
   * @return string
   *   The merged allowed HTML string.
   */
  private function mergeAllowedHtml(string $allowedHtml1, string $allowedHtml2): string {
    $mergedString = $allowedHtml1 . ' ' . $allowedHtml2;
    preg_match_all('/<[\w-]+(\s+[^>]+)?>/', $mergedString, $matches);

    $uniqueTags = [];
    foreach ($matches[0] as $tag) {
      preg_match('/<([\w-]+)(\s+[^>]*)?>/', $tag, $tagParts);
      if (!isset($tagParts[1])) {
        continue;
      }

      $tagName = $tagParts[1];
      $attributes = isset($tagParts[2]) ? trim($tagParts[2]) : '';

      if (isset($uniqueTags[$tagName])) {
        preg_match('/<[^>]+\s([^>]+)>/', $uniqueTags[$tagName], $existingParts);
        $existingAttributes = $existingParts[1] ?? '';

        if ($attributes) {
          $combined = $this->mergeAttributes($existingAttributes, $attributes);
          $uniqueTags[$tagName] = "<{$tagName} {$combined}>";
        }
      }
      else {
        $uniqueTags[$tagName] = $tag;
      }
    }

    return implode(' ', $uniqueTags);
  }

  /**
   * Merges two sets of HTML tag attributes.
   *
   * Class attributes are combined (deduplicated); other attributes are
   * overridden by the values from the second set.
   *
   * @param string $attributes1
   *   The existing attributes string.
   * @param string $attributes2
   *   The new attributes string to merge in.
   *
   * @return string
   *   The combined attributes string.
   */
  private function mergeAttributes(string $attributes1, string $attributes2): string {
    $attr1 = $this->parseAttributes($attributes1);
    $attr2 = $this->parseAttributes($attributes2);

    foreach ($attr2 as $key => $value) {
      if ($key === 'class') {
        $merged = array_unique(array_merge(
          explode(' ', $attr1['class'] ?? ''),
          explode(' ', $value),
        ));
        $attr1['class'] = trim(implode(' ', $merged));
      }
      else {
        $attr1[$key] = $value;
      }
    }

    $result = [];
    foreach ($attr1 as $key => $value) {
      $result[] = "{$key}=\"{$value}\"";
    }

    return implode(' ', $result);
  }

  /**
   * Parses an HTML attributes string into a key-value array.
   *
   * @param string $attributes
   *   The attributes string (e.g. 'class="foo bar" id="baz"').
   *
   * @return array<string, string>
   *   An associative array of attribute names and their values.
   */
  private function parseAttributes(string $attributes): array {
    $result = [];
    preg_match_all('/(\w+)=("[^"]*"|\'[^\']*\')/', $attributes, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
      $result[$match[1]] = trim($match[2], '"\'');
    }

    return $result;
  }

}
