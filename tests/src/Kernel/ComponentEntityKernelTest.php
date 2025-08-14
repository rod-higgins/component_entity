<?php

namespace Drupal\Tests\component_entity\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\component_entity\Entity\ComponentEntity;
use Drupal\component_entity\Entity\ComponentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests the ComponentEntity entity.
 *
 * @group component_entity
 * @coversDefaultClass \Drupal\component_entity\Entity\ComponentEntity
 */
class ComponentEntityKernelTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'component_entity',
    'field',
    'text',
    'user',
    'system',
  ];

  /**
   * The component type.
   *
   * @var \Drupal\component_entity\Entity\ComponentTypeInterface
   */
  protected $componentType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install entity schemas.
    $this->installEntitySchema('component');
    $this->installEntitySchema('user');

    // Install config.
    $this->installConfig(['component_entity']);

    // Create a component type.
    $this->componentType = ComponentType::create([
      'id' => 'test_component',
      'label' => 'Test Component',
      'description' => 'A test component type',
      'sdc_id' => 'test:test_component',
      'rendering' => [
        'twig_enabled' => TRUE,
        'react_enabled' => FALSE,
        'default_method' => 'twig',
      ],
    ]);
    $this->componentType->save();

    // Add a custom field to the component type.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_test_text',
      'entity_type' => 'component',
      'type' => 'text',
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'test_component',
      'label' => 'Test Text Field',
      'required' => TRUE,
    ]);
    $field->save();
  }

  /**
   * Tests creating a component entity.
   *
   * @covers ::create
   * @covers ::save
   */
  public function testCreateComponentEntity() {
    $component = ComponentEntity::create([
      'type' => 'test_component',
      'name' => 'Test Component Instance',
      'field_test_text' => 'Test content',
    ]);

    $this->assertInstanceOf(ComponentEntity::class, $component);
    $this->assertEquals('test_component', $component->bundle());
    $this->assertEquals('Test Component Instance', $component->getName());

    $component->save();
    $this->assertNotNull($component->id());
  }

  /**
   * Tests loading a component entity.
   *
   * @covers ::load
   */
  public function testLoadComponentEntity() {
    $component = ComponentEntity::create([
      'type' => 'test_component',
      'name' => 'Test Component',
      'field_test_text' => 'Test content',
    ]);
    $component->save();

    $loaded = ComponentEntity::load($component->id());
    $this->assertInstanceOf(ComponentEntity::class, $loaded);
    $this->assertEquals($component->id(), $loaded->id());
    $this->assertEquals('Test Component', $loaded->getName());
  }

  /**
   * Tests updating a component entity.
   *
   * @covers ::save
   */
  public function testUpdateComponentEntity() {
    $component = ComponentEntity::create([
      'type' => 'test_component',
      'name' => 'Original Name',
      'field_test_text' => 'Original content',
    ]);
    $component->save();

    $component->setName('Updated Name');
    $component->set('field_test_text', 'Updated content');
    $component->save();

    $loaded = ComponentEntity::load($component->id());
    $this->assertEquals('Updated Name', $loaded->getName());
    $this->assertEquals('Updated content', $loaded->get('field_test_text')->value);
  }

  /**
   * Tests deleting a component entity.
   *
   * @covers ::delete
   */
  public function testDeleteComponentEntity() {
    $component = ComponentEntity::create([
      'type' => 'test_component',
      'name' => 'Test Component',
      'field_test_text' => 'Test content',
    ]);
    $component->save();
    $id = $component->id();

    $component->delete();
    $loaded = ComponentEntity::load($id);
    $this->assertNull($loaded);
  }

  /**
   * Tests render method functionality.
   *
   * @covers ::getRenderMethod
   * @covers ::setRenderMethod
   */
  public function testRenderMethod() {
    $component = ComponentEntity::create([
      'type' => 'test_component',
      'name' => 'Test Component',
      'field_test_text' => 'Test content',
    ]);

    // Test default render method.
    $this->assertEquals('twig', $component->getRenderMethod());

    // Test setting render method.
    $component->setRenderMethod('react');
    $this->assertEquals('react', $component->getRenderMethod());

    // Test invalid render method.
    $this->expectException(\InvalidArgumentException::class);
    $component->setRenderMethod('invalid');
  }

  /**
   * Tests React configuration.
   *
   * @covers ::getReactConfig
   * @covers ::setReactConfig
   */
  public function testReactConfiguration() {
    $component = ComponentEntity::create([
      'type' => 'test_component',
      'name' => 'Test Component',
      'field_test_text' => 'Test content',
    ]);

    // Test default React config.
    $default_config = $component->getReactConfig();
    $this->assertIsArray($default_config);
    $this->assertArrayHasKey('hydration', $default_config);

    // Test setting React config.
    $config = [
      'hydration' => 'partial',
      'progressive' => TRUE,
      'lazy' => TRUE,
      'ssr' => FALSE,
    ];
    $component->setReactConfig($config);

    $saved_config = $component->getReactConfig();
    $this->assertEquals('partial', $saved_config['hydration']);
    $this->assertTrue($saved_config['progressive']);
  }

  /**
   * Tests component type relationship.
   *
   * @covers ::getComponentType
   */
  public function testComponentTypeRelationship() {
    $component = ComponentEntity::create([
      'type' => 'test_component',
      'name' => 'Test Component',
      'field_test_text' => 'Test content',
    ]);

    $type = $component->getComponentType();
    $this->assertInstanceOf(ComponentType::class, $type);
    $this->assertEquals('test_component', $type->id());
    $this->assertEquals('Test Component', $type->label());
  }

  /**
   * Tests field validation.
   */
  public function testFieldValidation() {
    // Test creating without required field.
    $component = ComponentEntity::create([
      'type' => 'test_component',
      'name' => 'Test Component',
      // Missing required field_test_text.
    ]);

    $violations = $component->validate();
    $this->assertGreaterThan(0, $violations->count());

    // Check that the violation is for the required field.
    $found_required_violation = FALSE;
    foreach ($violations as $violation) {
      if ($violation->getPropertyPath() === 'field_test_text') {
        $found_required_violation = TRUE;
        break;
      }
    }
    $this->assertTrue($found_required_violation);
  }

  /**
   * Tests entity revisions.
   *
   * @covers ::setNewRevision
   * @covers ::getRevisionId
   */
  public function testEntityRevisions() {
    $component = ComponentEntity::create([
      'type' => 'test_component',
      'name' => 'Test Component',
      'field_test_text' => 'Original content',
    ]);
    $component->save();
    $original_revision = $component->getRevisionId();

    // Create a new revision.
    $component->setNewRevision(TRUE);
    $component->setRevisionLogMessage('Updated content');
    $component->set('field_test_text', 'Updated content');
    $component->save();

    $new_revision = $component->getRevisionId();
    $this->assertNotEquals($original_revision, $new_revision);
  }

  /**
   * Tests entity publishing status.
   *
   * @covers ::isPublished
   * @covers ::setPublished
   * @covers ::setUnpublished
   */
  public function testPublishingStatus() {
    $component = ComponentEntity::create([
      'type' => 'test_component',
      'name' => 'Test Component',
      'field_test_text' => 'Test content',
    ]);

    // Test default published status.
    $this->assertTrue($component->isPublished());

    // Test unpublishing.
    $component->setUnpublished();
    $this->assertFalse($component->isPublished());

    // Test publishing.
    $component->setPublished();
    $this->assertTrue($component->isPublished());
  }

}
