<?php

declare(strict_types=1);

namespace Drupal\Tests\varbase_recipes\Kernel;

use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\ContentTemplate;
use Drupal\Core\Config\Action\ConfigActionManager;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\node\Entity\NodeType;

/**
 * Tests the repointComponentTreeToTheme config action.
 *
 * The two test themes both provide a test-card component, but the alternative
 * theme does not declare the subtitle prop — so repointing has to swap the
 * component ID, re-resolve the version, and drop the input that is no longer a
 * prop.
 *
 * @group varbase_recipes
 */
#[RunTestsInSeparateProcesses]
class RepointComponentTreeToThemeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'file',
    'image',
    'media',
    'node',
    'canvas',
    'project_browser',
    'varbase_recipes',
  ];

  /**
   * The theme the templates are authored against.
   */
  protected const SOURCE_THEME = 'varbase_recipes_test_theme';

  /**
   * The theme a site template would repoint them onto.
   */
  protected const TARGET_THEME = 'varbase_recipes_test_theme_alt';

  /**
   * The config action manager.
   */
  protected ConfigActionManager $configActionManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'node']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    $this->container->get('theme_installer')->install([
      static::SOURCE_THEME,
      static::TARGET_THEME,
    ]);
    // Canvas creates a Component config entity per SDC of every installed
    // theme, but only when it is told to.
    $this->container->get('Drupal\canvas\ComponentSource\ComponentSourceManager')->generateComponents();

    NodeType::create(['type' => 'event', 'name' => 'Event'])->save();

    $this->configActionManager = $this->container->get('plugin.manager.config_action');
  }

  /**
   * Returns the component ID of a test-card in the given theme.
   */
  protected function cardId(string $theme): string {
    return sprintf('sdc.%s.test-card', $theme);
  }

  /**
   * Creates a content template authored against the source theme.
   */
  protected function createTemplate(): ContentTemplate {
    $source = Component::load($this->cardId(static::SOURCE_THEME));
    $this->assertNotNull($source, 'The source theme provides a test-card component.');
    $block = Component::load('block.system_branding_block');
    $this->assertNotNull($block, 'The site branding block is available as a component.');

    $template = ContentTemplate::create([
      'id' => 'node.event.full',
      'content_entity_type_id' => 'node',
      'content_entity_type_bundle' => 'event',
      'content_entity_type_view_mode' => 'full',
      'component_tree' => [
        'a1b2c3d4-0001-4a01-9a01-000000000001' => [
          'uuid' => 'a1b2c3d4-0001-4a01-9a01-000000000001',
          'component_id' => $this->cardId(static::SOURCE_THEME),
          'component_version' => $source->getActiveVersion(),
          'inputs' => [
            'heading' => 'Open Day',
            'subtitle' => 'Come and see the campus',
          ],
        ],
        // A block component: the action must not touch it.
        'a1b2c3d4-0001-4a01-9a01-000000000002' => [
          'uuid' => 'a1b2c3d4-0001-4a01-9a01-000000000002',
          'component_id' => 'block.system_branding_block',
          'component_version' => $block->getActiveVersion(),
          'inputs' => [
            'label' => 'Site branding',
            'label_display' => 'visible',
            'use_site_logo' => TRUE,
            'use_site_name' => TRUE,
            'use_site_slogan' => FALSE,
          ],
        ],
      ],
    ]);
    $template->save();
    return $template;
  }

  /**
   * The tree is repointed, re-versioned, and stale inputs are dropped.
   */
  public function testTreeIsRepointedOntoTheTargetTheme(): void {
    $this->createTemplate();
    $target = Component::load($this->cardId(static::TARGET_THEME));
    $this->assertNotNull($target, 'The target theme provides a test-card component.');

    $this->configActionManager->applyAction(
      'repointComponentTreeToTheme',
      'canvas.content_template.node.event.full',
      ['theme' => static::TARGET_THEME],
    );

    $tree = ContentTemplate::load('node.event.full')->get('component_tree');
    $card = $tree['a1b2c3d4-0001-4a01-9a01-000000000001'];

    $this->assertSame($this->cardId(static::TARGET_THEME), $card['component_id']);
    $this->assertSame($target->getActiveVersion(), $card['component_version']);
    // The heading is a prop of the target component, so it survives.
    $this->assertSame('Open Day', $card['inputs']['heading']);
    // The subtitle is not, so it is dropped rather than aborting the recipe.
    $this->assertArrayNotHasKey('subtitle', $card['inputs']);

    // A non-SDC component is left exactly as it was.
    $block = $tree['a1b2c3d4-0001-4a01-9a01-000000000002'];
    $this->assertSame('block.system_branding_block', $block['component_id']);
    $this->assertSame(Component::load('block.system_branding_block')->getActiveVersion(), $block['component_version']);
  }

  /**
   * The 'default' keyword repoints onto the site's default theme.
   */
  public function testDefaultKeywordUsesTheDefaultTheme(): void {
    $this->config('system.theme')->set('default', static::TARGET_THEME)->save();
    $this->createTemplate();

    $this->configActionManager->applyAction(
      'repointComponentTreeToTheme',
      'canvas.content_template.node.event.full',
      ['theme' => 'default'],
    );

    $tree = ContentTemplate::load('node.event.full')->get('component_tree');
    $this->assertSame(
      $this->cardId(static::TARGET_THEME),
      $tree['a1b2c3d4-0001-4a01-9a01-000000000001']['component_id'],
    );
  }

  /**
   * A component the target theme does not have is left untouched.
   */
  public function testComponentMissingFromTheTargetThemeIsLeftAlone(): void {
    $only_here = Component::load('sdc.' . static::SOURCE_THEME . '.only-here');
    $this->assertNotNull($only_here, 'The source theme provides a component the target theme lacks.');

    $template = $this->createTemplate();
    $tree = $template->get('component_tree');
    $tree['a1b2c3d4-0001-4a01-9a01-000000000003'] = [
      'uuid' => 'a1b2c3d4-0001-4a01-9a01-000000000003',
      'component_id' => $only_here->id(),
      'component_version' => $only_here->getActiveVersion(),
      'inputs' => ['text' => 'Only in the source theme'],
    ];
    $template->set('component_tree', $tree)->save();

    $this->configActionManager->applyAction(
      'repointComponentTreeToTheme',
      'canvas.content_template.node.event.full',
      ['theme' => static::TARGET_THEME],
    );

    $after = ContentTemplate::load('node.event.full')->get('component_tree');
    // The card exists in both themes, so it moves.
    $this->assertSame($this->cardId(static::TARGET_THEME), $after['a1b2c3d4-0001-4a01-9a01-000000000001']['component_id']);
    // This one does not exist in the target theme, so it stays where it was.
    $this->assertSame($only_here->id(), $after['a1b2c3d4-0001-4a01-9a01-000000000003']['component_id']);
    $this->assertSame($only_here->getActiveVersion(), $after['a1b2c3d4-0001-4a01-9a01-000000000003']['component_version']);
    $this->assertSame('Only in the source theme', $after['a1b2c3d4-0001-4a01-9a01-000000000003']['inputs']['text']);
  }

}
