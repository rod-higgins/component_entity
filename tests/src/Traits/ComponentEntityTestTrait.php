<?php

namespace Drupal\Tests\component_entity\Traits;

use Drupal\component_entity\Entity\ComponentEntity;
use Drupal\component_entity\Entity\ComponentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Provides helper methods for Component Entity tests.
 */
trait ComponentEntityTestTrait {

  /**
   * Creates a test component type with basic fields.
   *
   * @param string $id
   *   The component type ID.
   * @param string $label
   *   The component type label.
   * @param array $rendering
   *   Rendering configuration.
   *
   * @return \Drupal\component_entity\Entity\ComponentTypeInterface
   *   The created component type.
   */
  protected function createTestComponentType($id = 'test_component', $label = 'Test Component', array $rendering = []) {
    $rendering = $rendering + [
      'twig_enabled' => TRUE,
      'react_enabled' => FALSE,
      'default_method' => 'twig',
    ];

    $component_type = ComponentType::create([
      'id' => $id,
      'label' => $label,
      'description' => 'Test component type for testing',
      'sdc_id' => 'test:' . $id,
      'rendering' => $rendering,
    ]);
    $component_type->save();

    return $component_type;
  }

  /**
   * Creates a test component entity.
   *
   * @param string $bundle
   *   The component bundle.
   * @param array $values
   *   Additional values for the component.
   *
   * @return \Drupal\component_entity\Entity\ComponentEntityInterface
   *   The created component entity.
   */
  protected function createTestComponent($bundle = 'test_component', array $values = []) {
    $values += [
      'type' => $bundle,
      'name' => 'Test Component ' . $this->randomMachineName(),
      'status' => TRUE,
    ];

    $component = ComponentEntity::create($values);
    $component->save();

    return $component;
  }

  /**
   * Adds a field to a component type.
   *
   * @param string $field_name
   *   The field name.
   * @param string $field_type
   *   The field type.
   * @param string $bundle
   *   The component bundle.
   * @param array $settings
   *   Field settings.
   * @param bool $required
   *   Whether the field is required.
   *
   * @return \Drupal\field\Entity\FieldConfig
   *   The created field config.
   */
  protected function addFieldToComponentType($field_name, $field_type, $bundle, array $settings = [], $required = FALSE) {
    // Create field storage if it doesn't exist.
    $field_storage = FieldStorageConfig::loadByName('component', $field_name);
    if (!$field_storage) {
      $field_storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'component',
        'type' => $field_type,
        'settings' => $settings,
      ]);
      $field_storage->save();
    }

    // Create field instance.
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'label' => ucfirst(str_replace('_', ' ', $field_name)),
      'required' => $required,
    ]);
    $field->save();

    return $field;
  }

  /**
   * Asserts that a component renders correctly.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $component
   *   The component to render.
   * @param string $view_mode
   *   The view mode to use.
   * @param array $expected_elements
   *   Expected elements in the render array.
   */
  protected function assertComponentRenders($component, $view_mode = 'default', array $expected_elements = []) {
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('component');
    $build = $view_builder->view($component, $view_mode);

    $this->assertIsArray($build);
    $this->assertArrayHasKey('#component', $build);
    $this->assertEquals($component->id(), $build['#component']->id());

    foreach ($expected_elements as $key) {
      $this->assertArrayHasKey($key, $build);
    }

    // Render and check output.
    $output = \Drupal::service('renderer')->renderRoot($build);
    $this->assertNotEmpty($output);
  }

  /**
   * Creates multiple test components.
   *
   * @param int $count
   *   Number of components to create.
   * @param string $bundle
   *   The component bundle.
   * @param array $base_values
   *   Base values for all components.
   *
   * @return array
   *   Array of created components.
   */
  protected function createMultipleTestComponents($count = 5, $bundle = 'test_component', array $base_values = []) {
    $components = [];
    
    for ($i = 0; $i < $count; $i++) {
      $values = $base_values + [
        'name' => 'Test Component ' . ($i + 1),
      ];
      $components[] = $this->createTestComponent($bundle, $values);
    }

    return $components;
  }

  /**
   * Asserts component access permissions.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $component
   *   The component to check.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param array $operations
   *   Operations to check (view, update, delete).
   * @param array $expected
   *   Expected access results keyed by operation.
   */
  protected function assertComponentAccess($component, $account, array $operations, array $expected) {
    foreach ($operations as $operation) {
      $access = $component->access($operation, $account);
      $expected_access = $expected[$operation] ?? FALSE;
      
      $this->assertEquals(
        $expected_access,
        $access,
        sprintf('User %s should %s access to %s the component.',
          $account->id() ?: 'anonymous',
          $expected_access ? 'have' : 'not have',
          $operation
        )
      );
    }
  }

  /**
   * Creates a user with component permissions.
   *
   * @param array $permissions
   *   Array of permissions.
   * @param string $bundle
   *   Optional bundle-specific permissions.
   *
   * @return \Drupal\user\UserInterface
   *   The created user.
   */
  protected function createComponentUser(array $permissions = [], $bundle = NULL) {
    $default_permissions = ['view published component entities'];
    
    if ($bundle) {
      $bundle_permissions = [
        "create $bundle component entities",
        "edit own $bundle component entities",
        "delete own $bundle component entities",
      ];
      $permissions = array_merge($default_permissions, $bundle_permissions, $permissions);
    } else {
      $permissions = array_merge($default_permissions, $permissions);
    }

    return $this->createUser($permissions);
  }

}