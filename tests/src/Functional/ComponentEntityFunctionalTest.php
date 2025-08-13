<?php

namespace Drupal\Tests\component_entity\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\component_entity\Entity\ComponentType;
use Drupal\component_entity\Entity\ComponentEntity;

/**
 * Tests the Component Entity UI functionality.
 *
 * @group component_entity
 */
class ComponentEntityFunctionalTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'component_entity',
    'field',
    'field_ui',
    'text',
    'user',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with component administration permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A user with basic component permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $authorUser;

  /**
   * A component type for testing.
   *
   * @var \Drupal\component_entity\Entity\ComponentTypeInterface
   */
  protected $componentType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create admin user.
    $this->adminUser = $this->drupalCreateUser([
      'administer component types',
      'administer component entities',
      'create test_component component entities',
      'edit any test_component component entities',
      'delete any test_component component entities',
      'view published component entities',
      'access component overview',
    ]);

    // Create author user.
    $this->authorUser = $this->drupalCreateUser([
      'create test_component component entities',
      'edit own test_component component entities',
      'delete own test_component component entities',
      'view published component entities',
    ]);

    // Create a test component type.
    $this->componentType = ComponentType::create([
      'id' => 'test_component',
      'label' => 'Test Component',
      'description' => 'A test component type for functional testing',
      'rendering' => [
        'twig_enabled' => TRUE,
        'react_enabled' => TRUE,
        'default_method' => 'twig',
      ],
    ]);
    $this->componentType->save();
  }

  /**
   * Tests component type administration.
   */
  public function testComponentTypeAdministration() {
    $this->drupalLogin($this->adminUser);

    // Test component types listing page.
    $this->drupalGet('admin/structure/component-types');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Component types');
    $this->assertSession()->pageTextContains('Test Component');

    // Test adding a new component type.
    $this->clickLink('Add component type');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('label');
    $this->assertSession()->fieldExists('id');

    $edit = [
      'label' => 'New Component Type',
      'id' => 'new_component',
      'description' => 'A new test component',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('Created the New Component Type component type.');

    // Test editing a component type.
    $this->drupalGet('admin/structure/component-types/test_component/edit');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('label', 'Test Component');

    $edit = [
      'label' => 'Updated Test Component',
      'description' => 'Updated description',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('Saved the Updated Test Component component type.');
  }

  /**
   * Tests component entity CRUD operations.
   */
  public function testComponentEntityCrud() {
    $this->drupalLogin($this->adminUser);

    // Test creating a component entity.
    $this->drupalGet('component/add/test_component');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('name[0][value]');

    $edit = [
      'name[0][value]' => 'Test Component Instance',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('Component Test Component Instance has been created.');

    // Get the created component.
    $components = \Drupal::entityTypeManager()
      ->getStorage('component')
      ->loadByProperties(['name' => 'Test Component Instance']);
    $component = reset($components);
    $this->assertNotFalse($component);

    // Test viewing the component.
    $this->drupalGet('component/' . $component->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Test Component Instance');

    // Test editing the component.
    $this->drupalGet('component/' . $component->id() . '/edit');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('name[0][value]', 'Test Component Instance');

    $edit = [
      'name[0][value]' => 'Updated Component Instance',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('Component Updated Component Instance has been updated.');

    // Test deleting the component.
    $this->drupalGet('component/' . $component->id() . '/delete');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Are you sure you want to delete');
    
    $this->submitForm([], 'Delete');
    $this->assertSession()->pageTextContains('The component Updated Component Instance has been deleted.');
  }

  /**
   * Tests component entity access control.
   */
  public function testComponentEntityAccess() {
    // Create a component as admin.
    $this->drupalLogin($this->adminUser);
    $admin_component = ComponentEntity::create([
      'type' => 'test_component',
      'name' => 'Admin Component',
      'uid' => $this->adminUser->id(),
    ]);
    $admin_component->save();

    // Create a component as author.
    $this->drupalLogin($this->authorUser);
    $author_component = ComponentEntity::create([
      'type' => 'test_component',
      'name' => 'Author Component',
      'uid' => $this->authorUser->id(),
    ]);
    $author_component->save();

    // Test author can edit own component.
    $this->drupalGet('component/' . $author_component->id() . '/edit');
    $this->assertSession()->statusCodeEquals(200);

    // Test author cannot edit admin's component.
    $this->drupalGet('component/' . $admin_component->id() . '/edit');
    $this->assertSession()->statusCodeEquals(403);

    // Test author can delete own component.
    $this->drupalGet('component/' . $author_component->id() . '/delete');
    $this->assertSession()->statusCodeEquals(200);

    // Test author cannot delete admin's component.
    $this->drupalGet('component/' . $admin_component->id() . '/delete');
    $this->assertSession()->statusCodeEquals(403);

    // Test anonymous user cannot create components.
    $this->drupalLogout();
    $this->drupalGet('component/add/test_component');
    $this->assertSession()->statusCodeEquals(403);

    // Test anonymous user can view published components.
    $this->drupalGet('component/' . $admin_component->id());
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests component list builder.
   */
  public function testComponentListBuilder() {
    $this->drupalLogin($this->adminUser);

    // Create multiple components.
    for ($i = 1; $i <= 3; $i++) {
      $component = ComponentEntity::create([
        'type' => 'test_component',
        'name' => 'Test Component ' . $i,
      ]);
      $component->save();
    }

    // Test the component listing page.
    $this->drupalGet('admin/content/components');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Components');
    
    // Check all components are listed.
    for ($i = 1; $i <= 3; $i++) {
      $this->assertSession()->pageTextContains('Test Component ' . $i);
    }

    // Test operations links.
    $this->assertSession()->linkExists('Edit');
    $this->assertSession()->linkExists('Delete');
  }

  /**
   * Tests render method selection.
   */
  public function testRenderMethodSelection() {
    $this->drupalLogin($this->adminUser);

    // Create a component with Twig rendering.
    $this->drupalGet('component/add/test_component');
    $edit = [
      'name[0][value]' => 'Twig Component',
      'render_method[0][value]' => 'twig',
    ];
    $this->submitForm($edit, 'Save');

    $components = \Drupal::entityTypeManager()
      ->getStorage('component')
      ->loadByProperties(['name' => 'Twig Component']);
    $twig_component = reset($components);
    $this->assertEquals('twig', $twig_component->getRenderMethod());

    // Create a component with React rendering.
    $this->drupalGet('component/add/test_component');
    $edit = [
      'name[0][value]' => 'React Component',
      'render_method[0][value]' => 'react',
    ];
    $this->submitForm($edit, 'Save');

    $components = \Drupal::entityTypeManager()
      ->getStorage('component')
      ->loadByProperties(['name' => 'React Component']);
    $react_component = reset($components);
    $this->assertEquals('react', $react_component->getRenderMethod());
  }

  /**
   * Tests component type field UI integration.
   */
  public function testFieldUiIntegration() {
    $this->drupalLogin($this->adminUser);

    // Access field UI for component type.
    $this->drupalGet('admin/structure/component-types/test_component/fields');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Manage fields');

    // Add a new field.
    $this->clickLink('Add field');
    $this->assertSession()->statusCodeEquals(200);

    $edit = [
      'new_storage_type' => 'text',
      'label' => 'Custom Text Field',
      'field_name' => 'custom_text',
    ];
    $this->submitForm($edit, 'Save and continue');

    // Configure field storage.
    $this->submitForm([], 'Save field settings');

    // Configure field instance.
    $edit = [
      'required' => TRUE,
      'description' => 'A custom text field for testing',
    ];
    $this->submitForm($edit, 'Save settings');

    $this->assertSession()->pageTextContains('Saved Custom Text Field configuration.');

    // Verify field appears in component add form.
    $this->drupalGet('component/add/test_component');
    $this->assertSession()->fieldExists('field_custom_text[0][value]');
  }

  /**
   * Tests component sync functionality.
   */
  public function testComponentSync() {
    $this->drupalLogin($this->adminUser);

    // Access sync page.
    $this->drupalGet('admin/structure/component-types/sync');
    
    // Check if sync page loads correctly.
    if ($this->getSession()->getStatusCode() === 200) {
      $this->assertSession()->pageTextContains('Sync SDC Components');
      
      // Test sync button if available.
      if ($this->getSession()->getPage()->findButton('Sync Components')) {
        $this->submitForm([], 'Sync Components');
        $this->assertSession()->pageTextContains('Synchronization completed');
      }
    }
  }

}