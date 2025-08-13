<?php

namespace Drupal\Tests\component_entity\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\component_entity\Service\BiDirectionalSyncService;
use Drupal\component_entity\Service\FileSystemWriterService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests the BiDirectionalSyncService.
 *
 * @group component_entity
 * @coversDefaultClass \Drupal\component_entity\Service\BiDirectionalSyncService
 */
class BiDirectionalSyncServiceTest extends UnitTestCase {

  /**
   * The sync service.
   *
   * @var \Drupal\component_entity\Service\BiDirectionalSyncService
   */
  protected $syncService;

  /**
   * Mock entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * Mock file writer.
   *
   * @var \Drupal\component_entity\Service\FileSystemWriterService|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $fileWriter;

  /**
   * Mock config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * Mock logger.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->fileWriter = $this->createMock(FileSystemWriterService::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);
    
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);

    $this->syncService = new BiDirectionalSyncService(
      $this->entityTypeManager,
      $this->fileWriter,
      $moduleHandler,
      $this->configFactory,
      $this->logger
    );
  }

  /**
   * Tests sync from entity to files.
   *
   * @covers ::syncEntityToFiles
   */
  public function testSyncEntityToFiles() {
    // Mock component type entity.
    $componentType = $this->createMock('Drupal\component_entity\Entity\ComponentTypeInterface');
    $componentType->expects($this->any())
      ->method('id')
      ->willReturn('test_component');
    $componentType->expects($this->any())
      ->method('label')
      ->willReturn('Test Component');
    $componentType->expects($this->any())
      ->method('getDescription')
      ->willReturn('Test description');

    // Mock file writer success.
    $this->fileWriter->expects($this->any())
      ->method('writeFile')
      ->willReturn(['success' => TRUE]);

    $result = $this->syncService->syncEntityToFiles($componentType);
    
    $this->assertIsArray($result);
    $this->assertTrue($result['success']);
  }

  /**
   * Tests getting component name transformation.
   *
   * @covers ::getComponentName
   * @dataProvider componentNameProvider
   */
  public function testGetComponentName($input, $expected) {
    $method = new \ReflectionMethod($this->syncService, 'getComponentName');
    $method->setAccessible(TRUE);

    $result = $method->invoke($this->syncService, $input);
    $this->assertEquals($expected, $result);
  }

  /**
   * Provides test cases for component name transformation.
   */
  public function componentNameProvider() {
    return [
      ['hero_banner', 'HeroBanner'],
      ['simple_card', 'SimpleCard'],
      ['my_complex_component', 'MyComplexComponent'],
      ['test', 'Test'],
      ['test_123_component', 'Test123Component'],
    ];
  }

  /**
   * Tests generation options retrieval.
   *
   * @covers ::getGenerationOptions
   */
  public function testGetGenerationOptions() {
    $config = $this->createMock('Drupal\Core\Config\ImmutableConfig');
    $config->expects($this->any())
      ->method('get')
      ->willReturnMap([
        ['generation_target', 'module'],
        ['generation_name', 'test_module'],
        ['overwrite_files', FALSE],
        ['generate_twig', TRUE],
        ['generate_react', TRUE],
      ]);

    $this->configFactory->expects($this->once())
      ->method('get')
      ->with('component_entity.settings')
      ->willReturn($config);

    $method = new \ReflectionMethod($this->syncService, 'getGenerationOptions');
    $method->setAccessible(TRUE);

    $options = $method->invoke($this->syncService, null);
    
    $this->assertIsArray($options);
    $this->assertEquals('module', $options['target']);
    $this->assertEquals('test_module', $options['name']);
    $this->assertFalse($options['overwrite']);
    $this->assertTrue($options['twig']);
    $this->assertTrue($options['react']);
  }

  /**
   * Tests YAML file generation.
   *
   * @covers ::generateYamlFile
   */
  public function testGenerateYamlFile() {
    $componentType = $this->createMock('Drupal\component_entity\Entity\ComponentTypeInterface');
    $componentType->expects($this->any())
      ->method('id')
      ->willReturn('test_component');
    $componentType->expects($this->any())
      ->method('label')
      ->willReturn('Test Component');
    $componentType->expects($this->any())
      ->method('getDescription')
      ->willReturn('Test description');

    $this->fileWriter->expects($this->once())
      ->method('writeFile')
      ->with(
        $this->stringContains('test_component.component.yml'),
        $this->stringContains('name: Test Component')
      )
      ->willReturn(['success' => TRUE]);

    $method = new \ReflectionMethod($this->syncService, 'generateYamlFile');
    $method->setAccessible(TRUE);

    $result = $method->invoke(
      $this->syncService,
      $componentType,
      '/test/path',
      ['overwrite' => FALSE]
    );

    $this->assertTrue($result['success']);
  }

  /**
   * Tests Twig template generation.
   *
   * @covers ::generateTwigTemplate
   */
  public function testGenerateTwigTemplate() {
    $componentType = $this->createMock('Drupal\component_entity\Entity\ComponentTypeInterface');
    $componentType->expects($this->any())
      ->method('id')
      ->willReturn('test_component');
    $componentType->expects($this->any())
      ->method('label')
      ->willReturn('Test Component');

    $this->fileWriter->expects($this->once())
      ->method('writeFile')
      ->with(
        $this->stringContains('test_component.html.twig'),
        $this->stringContains('test-component')
      )
      ->willReturn(['success' => TRUE]);

    $method = new \ReflectionMethod($this->syncService, 'generateTwigTemplate');
    $method->setAccessible(TRUE);

    $result = $method->invoke(
      $this->syncService,
      $componentType,
      '/test/path',
      ['overwrite' => FALSE]
    );

    $this->assertTrue($result['success']);
  }

  /**
   * Tests React component generation.
   *
   * @covers ::generateReactComponent
   */
  public function testGenerateReactComponent() {
    $componentType = $this->createMock('Drupal\component_entity\Entity\ComponentTypeInterface');
    $componentType->expects($this->any())
      ->method('id')
      ->willReturn('test_component');

    $this->fileWriter->expects($this->once())
      ->method('writeFile')
      ->with(
        $this->stringContains('test_component.jsx'),
        $this->stringContains('TestComponent')
      )
      ->willReturn(['success' => TRUE]);

    $method = new \ReflectionMethod($this->syncService, 'generateReactComponent');
    $method->setAccessible(TRUE);

    $result = $method->invoke(
      $this->syncService,
      $componentType,
      '/test/path',
      ['overwrite' => FALSE]
    );

    $this->assertTrue($result['success']);
  }

}