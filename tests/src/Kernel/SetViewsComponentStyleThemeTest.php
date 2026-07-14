<?php

declare(strict_types=1);

namespace Drupal\Tests\varbase_recipes\Kernel;

use Drupal\Core\Config\Action\ConfigActionManager;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\views\Entity\View;

/**
 * Tests the setViewsComponentStyleTheme config action.
 *
 * @group varbase_recipes
 */
#[RunTestsInSeparateProcesses]
class SetViewsComponentStyleThemeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'views',
    'varbase_components',
    'project_browser',
    'varbase_recipes',
  ];

  /**
   * {@inheritdoc}
   *
   * The component_id option is written by the Varbase Components views style,
   * which declares no config schema for it, so a view carrying that option
   * cannot pass the strict schema check.
   *
   * @todo Remove once Varbase Components declares a schema for
   *   views.style.components_views_style.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * The config action manager.
   */
  protected ConfigActionManager $configActionManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->configActionManager = $this->container->get('plugin.manager.config_action');
  }

  /**
   * Creates a view whose displays render through a component style.
   */
  protected function createView(): View {
    $view = View::create([
      'id' => 'test_listing',
      'label' => 'Test listing',
      'base_table' => 'node_field_data',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_plugin' => 'default',
          'display_title' => 'Default',
          'display_options' => [
            'style' => [
              'type' => 'components_views_style',
              'options' => ['component_id' => 'vartheme_bs5:views-view-grid'],
            ],
          ],
        ],
        'block_1' => [
          'id' => 'block_1',
          'display_plugin' => 'block',
          'display_title' => 'Block',
          'display_options' => [
            'style' => [
              'type' => 'components_views_style',
              'options' => ['component_id' => 'vartheme_bs5:views-view-grid'],
            ],
          ],
        ],
        'feed_1' => [
          'id' => 'feed_1',
          'display_plugin' => 'feed',
          'display_title' => 'Feed',
          'display_options' => [
            'style' => ['type' => 'rss'],
          ],
        ],
      ],
    ]);
    $view->save();
    return $view;
  }

  /**
   * The theme part of the component style is replaced on every display.
   */
  public function testNamedThemeIsAppliedToEveryDisplay(): void {
    $this->createView();

    $this->configActionManager->applyAction(
      'setViewsComponentStyleTheme',
      'views.view.test_listing',
      ['theme' => 'vartheme_bs5_educare'],
    );

    $displays = View::load('test_listing')->get('display');
    $this->assertSame('vartheme_bs5_educare:views-view-grid', $displays['default']['display_options']['style']['options']['component_id']);
    $this->assertSame('vartheme_bs5_educare:views-view-grid', $displays['block_1']['display_options']['style']['options']['component_id']);
    // A display that does not render through a component is left alone.
    $this->assertSame('rss', $displays['feed_1']['display_options']['style']['type']);
    $this->assertArrayNotHasKey('options', $displays['feed_1']['display_options']['style']);
  }

  /**
   * The 'default' keyword resolves to the site's default theme.
   */
  public function testDefaultKeywordUsesTheDefaultTheme(): void {
    $this->config('system.theme')->set('default', 'olivero')->save();
    $this->createView();

    $this->configActionManager->applyAction(
      'setViewsComponentStyleTheme',
      'views.view.test_listing',
      ['theme' => 'default'],
    );

    $displays = View::load('test_listing')->get('display');
    $this->assertSame('olivero:views-view-grid', $displays['default']['display_options']['style']['options']['component_id']);
  }

  /**
   * Applying the theme a view already uses changes nothing.
   */
  public function testApplyingTheSameThemeIsANoOp(): void {
    $view = $this->createView();
    $before = $view->get('display');

    $this->configActionManager->applyAction(
      'setViewsComponentStyleTheme',
      'views.view.test_listing',
      ['theme' => 'vartheme_bs5'],
    );

    $this->assertSame($before, View::load('test_listing')->get('display'));
  }

}
