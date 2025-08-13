<?php

namespace Drupal\Tests\component_entity\Kernel\Views;

use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Tests\ViewTestData;
use Drupal\views\Views;
use Drupal\component_entity\Entity\ComponentEntity;
use Drupal\component_entity\Entity\ComponentType;

/**
 * Tests the Component Date Views filter plugin.
 *
 * @group component_entity
 * @group views
 */
class ComponentDateFilterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'component_entity',
    'component_entity_test_views',
    'views',
    'user',
    'field',
    'text',
    'system',
  ];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_component_date_filter'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install entity schemas.
    $this->installEntitySchema('component');
    $this->installEntitySchema('user');
    
    // Install views configuration.
    $this->installConfig(['views', 'component_entity']);

    // Create test component type.
    $component_type = ComponentType::create([
      'id' => 'test_date_component',
      'label' => 'Test Date Component',
    ]);
    $component_type->save();

    // Create test components with different dates.
    $this->createTestComponents();

    // Import test views.
    ViewTestData::createTestViews(get_class($this), ['component_entity_test_views']);
  }

  /**
   * Creates test component entities with various dates.
   */
  protected function createTestComponents() {
    $dates = [
      'today' => time(),
      'yesterday' => strtotime('-1 day'),
      'last_week' => strtotime('-1 week'),
      'last_month' => strtotime('-1 month'),
      'last_year' => strtotime('-1 year'),
    ];

    foreach ($dates as $key => $timestamp) {
      $component = ComponentEntity::create([
        'type' => 'test_date_component',
        'name' => 'Component ' . $key,
        'created' => $timestamp,
        'changed' => $timestamp,
      ]);
      $component->save();
    }
  }

  /**
   * Tests the date filter with preset values.
   */
  public function testDateFilterPresets() {
    $view = Views::getView('test_component_date_filter');
    $this->assertNotNull($view);

    // Test 'today' preset.
    $view->setDisplay();
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'created' => [
        'id' => 'created',
        'table' => 'component_field_data',
        'field' => 'created',
        'plugin_id' => 'component_date',
        'value' => [
          'preset' => 'today',
        ],
      ],
    ]);
    $view->execute();
    $this->assertCount(1, $view->result, 'Today preset returns 1 component');

    // Test 'last_7_days' preset.
    $view->destroy();
    $view->setDisplay();
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'created' => [
        'id' => 'created',
        'table' => 'component_field_data',
        'field' => 'created',
        'plugin_id' => 'component_date',
        'value' => [
          'preset' => 'last_7_days',
        ],
      ],
    ]);
    $view->execute();
    $this->assertCount(2, $view->result, 'Last 7 days preset returns 2 components');

    // Test 'this_month' preset.
    $view->destroy();
    $view->setDisplay();
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'created' => [
        'id' => 'created',
        'table' => 'component_field_data',
        'field' => 'created',
        'plugin_id' => 'component_date',
        'value' => [
          'preset' => 'this_month',
        ],
      ],
    ]);
    $view->execute();
    $this->assertGreaterThanOrEqual(1, count($view->result), 'This month preset returns at least 1 component');

    // Test 'last_year' preset.
    $view->destroy();
    $view->setDisplay();
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'created' => [
        'id' => 'created',
        'table' => 'component_field_data',
        'field' => 'created',
        'plugin_id' => 'component_date',
        'value' => [
          'preset' => 'last_year',
        ],
      ],
    ]);
    $view->execute();
    $this->assertCount(1, $view->result, 'Last year preset returns 1 component');
  }

  /**
   * Tests the date filter with custom date range.
   */
  public function testDateFilterCustomRange() {
    $view = Views::getView('test_component_date_filter');
    
    // Test custom date range.
    $view->setDisplay();
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'created' => [
        'id' => 'created',
        'table' => 'component_field_data',
        'field' => 'created',
        'plugin_id' => 'component_date',
        'value' => [
          'preset' => 'custom',
          'min' => date('Y-m-d', strtotime('-3 days')),
          'max' => date('Y-m-d', strtotime('today')),
        ],
      ],
    ]);
    $view->execute();
    
    $this->assertCount(2, $view->result, 'Custom range returns correct components');
  }

  /**
   * Tests relative date calculations.
   */
  public function testRelativeDateCalculations() {
    // Get the filter plugin directly.
    $view = Views::getView('test_component_date_filter');
    $view->initHandlers();
    
    $filter = $view->filter['created'] ?? null;
    if (!$filter) {
      $this->markTestSkipped('Filter not found in view');
      return;
    }

    // Test relative date calculations via reflection.
    $method = new \ReflectionMethod($filter, 'calculateDateRange');
    $method->setAccessible(TRUE);

    // Test 'yesterday'.
    $range = $method->invoke($filter, 'yesterday');
    $this->assertNotNull($range);
    $this->assertEquals(date('Y-m-d', strtotime('yesterday')), date('Y-m-d', strtotime($range['min'])));

    // Test 'last_30_days'.
    $range = $method->invoke($filter, 'last_30_days');
    $this->assertNotNull($range);
    $this->assertEquals(date('Y-m-d', strtotime('-30 days')), date('Y-m-d', strtotime($range['min'])));
    $this->assertEquals(date('Y-m-d', strtotime('today')), date('Y-m-d', strtotime($range['max'])));

    // Test 'this_week'.
    $range = $method->invoke($filter, 'this_week');
    $this->assertNotNull($range);
    $expected_start = date('Y-m-d', strtotime('monday this week'));
    $this->assertEquals($expected_start, date('Y-m-d', strtotime($range['min'])));

    // Test 'this_year'.
    $range = $method->invoke($filter, 'this_year');
    $this->assertNotNull($range);
    $this->assertEquals(date('Y') . '-01-01', date('Y-m-d', strtotime($range['min'])));
    $this->assertEquals(date('Y') . '-12-31', date('Y-m-d', strtotime($range['max'])));
  }

  /**
   * Tests filter validation.
   */
  public function testFilterValidation() {
    $view = Views::getView('test_component_date_filter');
    $view->setDisplay();
    
    // Test invalid preset value.
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'created' => [
        'id' => 'created',
        'table' => 'component_field_data',
        'field' => 'created',
        'plugin_id' => 'component_date',
        'value' => [
          'preset' => 'invalid_preset',
        ],
      ],
    ]);
    
    $view->execute();
    // Should not throw error, but return all results.
    $this->assertGreaterThan(0, count($view->result));

    // Test custom range with invalid dates.
    $view->destroy();
    $view->setDisplay();
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'created' => [
        'id' => 'created',
        'table' => 'component_field_data',
        'field' => 'created',
        'plugin_id' => 'component_date',
        'value' => [
          'preset' => 'custom',
          'min' => 'invalid_date',
          'max' => 'another_invalid_date',
        ],
      ],
    ]);
    
    $view->execute();
    // Should handle gracefully and return results.
    $this->assertIsArray($view->result);
  }

}