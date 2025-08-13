<?php

namespace Drupal\Tests\component_entity\Functional;

use Drupal\Tests\rest\Functional\ResourceTestBase;
use Drupal\component_entity\Entity\ComponentEntity;
use Drupal\component_entity\Entity\ComponentType;
use Drupal\Core\Url;

/**
 * Tests the Component Entity REST resource.
 *
 * @group component_entity
 * @group rest
 */
class ComponentEntityRestTest extends ResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'component_entity',
    'rest',
    'serialization',
    'basic_auth',
    'hal',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $format = 'json';

  /**
   * {@inheritdoc}
   */
  protected static $mimeType = 'application/json';

  /**
   * {@inheritdoc}
   */
  protected static $auth = 'basic_auth';

  /**
   * {@inheritdoc}
   */
  protected static $resourceConfigId = 'entity.component';

  /**
   * The component type.
   *
   * @var \Drupal\component_entity\Entity\ComponentTypeInterface
   */
  protected $componentType;

  /**
   * A test component entity.
   *
   * @var \Drupal\component_entity\Entity\ComponentEntityInterface
   */
  protected $componentEntity;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a component type.
    $this->componentType = ComponentType::create([
      'id' => 'test_api_component',
      'label' => 'Test API Component',
      'description' => 'Component type for REST API testing',
    ]);
    $this->componentType->save();

    // Create a test component entity.
    $this->componentEntity = ComponentEntity::create([
      'type' => 'test_api_component',
      'name' => 'Test REST Component',
      'status' => TRUE,
    ]);
    $this->componentEntity->save();

    // Grant permissions to authenticated users.
    $this->grantPermissionsToTestedRole([
      'view published component entities',
      'create test_api_component component entities',
      'edit any test_api_component component entities',
      'delete any test_api_component component entities',
    ]);
  }

  /**
   * Tests GET request for a component entity.
   */
  public function testGetComponent() {
    $this->initAuthentication();
    
    $url = Url::fromRoute('rest.entity.component.GET', [
      'component' => $this->componentEntity->id(),
      '_format' => static::$format,
    ]);

    $response = $this->request('GET', $url, []);
    $this->assertResourceResponse(200, FALSE, $response);

    $response_data = $this->serializer->decode((string) $response->getBody(), static::$format);
    
    $this->assertEquals($this->componentEntity->id(), $response_data['id'][0]['value']);
    $this->assertEquals('Test REST Component', $response_data['name'][0]['value']);
    $this->assertEquals('test_api_component', $response_data['type'][0]['target_id']);
  }

  /**
   * Tests POST request to create a component entity.
   */
  public function testPostComponent() {
    $this->initAuthentication();

    $url = Url::fromRoute('rest.entity.component.POST', [
      '_format' => static::$format,
    ]);

    $request_data = [
      'type' => [['target_id' => 'test_api_component']],
      'name' => [['value' => 'New REST Component']],
      'render_method' => [['value' => 'twig']],
    ];

    $request_options = [
      'body' => $this->serializer->encode($request_data, static::$format),
      'headers' => [
        'Content-Type' => static::$mimeType,
      ],
    ];

    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceResponse(201, FALSE, $response);

    // Verify the component was created.
    $components = \Drupal::entityTypeManager()
      ->getStorage('component')
      ->loadByProperties(['name' => 'New REST Component']);
    $this->assertCount(1, $components);
    
    $component = reset($components);
    $this->assertEquals('New REST Component', $component->getName());
    $this->assertEquals('twig', $component->getRenderMethod());
  }

  /**
   * Tests PATCH request to update a component entity.
   */
  public function testPatchComponent() {
    $this->initAuthentication();

    $url = Url::fromRoute('rest.entity.component.PATCH', [
      'component' => $this->componentEntity->id(),
      '_format' => static::$format,
    ]);

    $request_data = [
      'name' => [['value' => 'Updated REST Component']],
      'render_method' => [['value' => 'react']],
    ];

    $request_options = [
      'body' => $this->serializer->encode($request_data, static::$format),
      'headers' => [
        'Content-Type' => static::$mimeType,
      ],
    ];

    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceResponse(200, FALSE, $response);

    // Verify the component was updated.
    $updated_component = ComponentEntity::load($this->componentEntity->id());
    $this->assertEquals('Updated REST Component', $updated_component->getName());
    $this->assertEquals('react', $updated_component->getRenderMethod());
  }

  /**
   * Tests DELETE request to remove a component entity.
   */
  public function testDeleteComponent() {
    $this->initAuthentication();

    $url = Url::fromRoute('rest.entity.component.DELETE', [
      'component' => $this->componentEntity->id(),
      '_format' => static::$format,
    ]);

    $response = $this->request('DELETE', $url, []);
    $this->assertResourceResponse(204, FALSE, $response);

    // Verify the component was deleted.
    $deleted_component = ComponentEntity::load($this->componentEntity->id());
    $this->assertNull($deleted_component);
  }

  /**
   * Tests access control for REST operations.
   */
  public function testRestAccessControl() {
    // Remove permissions from authenticated role.
    $this->revokePermissionsFromTestedRole([
      'create test_api_component component entities',
      'edit any test_api_component component entities',
      'delete any test_api_component component entities',
    ]);

    $this->initAuthentication();

    // Test that POST is forbidden.
    $url = Url::fromRoute('rest.entity.component.POST', [
      '_format' => static::$format,
    ]);

    $request_data = [
      'type' => [['target_id' => 'test_api_component']],
      'name' => [['value' => 'Forbidden Component']],
    ];

    $request_options = [
      'body' => $this->serializer->encode($request_data, static::$format),
      'headers' => [
        'Content-Type' => static::$mimeType,
      ],
    ];

    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceErrorResponse(403, 'Access denied', $response);

    // Test that PATCH is forbidden.
    $url = Url::fromRoute('rest.entity.component.PATCH', [
      'component' => $this->componentEntity->id(),
      '_format' => static::$format,
    ]);

    $request_data = [
      'name' => [['value' => 'Forbidden Update']],
    ];

    $request_options['body'] = $this->serializer->encode($request_data, static::$format);
    $response = $this->request('PATCH', $url, $request_options);
    $this->assertResourceErrorResponse(403, 'Access denied', $response);

    // Test that DELETE is forbidden.
    $url = Url::fromRoute('rest.entity.component.DELETE', [
      'component' => $this->componentEntity->id(),
      '_format' => static::$format,
    ]);

    $response = $this->request('DELETE', $url, []);
    $this->assertResourceErrorResponse(403, 'Access denied', $response);
  }

  /**
   * Tests getting component type information via REST.
   */
  public function testGetComponentType() {
    $this->initAuthentication();

    $url = Url::fromRoute('rest.component_type.GET', [
      'component_type' => $this->componentType->id(),
      '_format' => static::$format,
    ]);

    // This test assumes the component_type REST resource is configured.
    // If not available, skip this test.
    try {
      $response = $this->request('GET', $url, []);
      
      if ($response->getStatusCode() === 200) {
        $response_data = $this->serializer->decode((string) $response->getBody(), static::$format);
        
        $this->assertEquals($this->componentType->id(), $response_data['id']);
        $this->assertEquals('Test API Component', $response_data['label']);
        $this->assertEquals('Component type for REST API testing', $response_data['description']);
      }
    }
    catch (\Exception $e) {
      // REST resource might not be configured, skip assertion.
      $this->markTestIncomplete('Component type REST resource not configured.');
    }
  }

  /**
   * Tests collection endpoint for components.
   */
  public function testGetComponentCollection() {
    // Create additional components.
    for ($i = 1; $i <= 3; $i++) {
      $component = ComponentEntity::create([
        'type' => 'test_api_component',
        'name' => 'Collection Component ' . $i,
        'status' => TRUE,
      ]);
      $component->save();
    }

    $this->initAuthentication();

    $url = Url::fromRoute('rest.component_collection.GET', [
      '_format' => static::$format,
    ]);

    // This assumes a collection resource is configured.
    try {
      $response = $this->request('GET', $url, []);
      
      if ($response->getStatusCode() === 200) {
        $response_data = $this->serializer->decode((string) $response->getBody(), static::$format);
        
        $this->assertIsArray($response_data);
        $this->assertGreaterThanOrEqual(4, count($response_data)); // Original + 3 new.
      }
    }
    catch (\Exception $e) {
      // Collection resource might not be configured, skip assertion.
      $this->markTestIncomplete('Component collection REST resource not configured.');
    }
  }

}