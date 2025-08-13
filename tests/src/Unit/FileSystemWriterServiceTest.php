<?php

namespace Drupal\Tests\component_entity\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\component_entity\Service\FileSystemWriterService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests the FileSystemWriterService.
 *
 * @group component_entity
 * @coversDefaultClass \Drupal\component_entity\Service\FileSystemWriterService
 */
class FileSystemWriterServiceTest extends UnitTestCase {

  /**
   * The file system writer service.
   *
   * @var \Drupal\component_entity\Service\FileSystemWriterService
   */
  protected $fileSystemWriter;

  /**
   * Mock file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $fileSystem;

  /**
   * Mock config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * Mock current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $currentUser;

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

    $this->fileSystem = $this->createMock(FileSystemInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->currentUser = $this->createMock(AccountProxyInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);
    
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $themeHandler = $this->createMock(ThemeHandlerInterface::class);
    $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

    $this->fileSystemWriter = new FileSystemWriterService(
      $this->fileSystem,
      $this->configFactory,
      $moduleHandler,
      $themeHandler,
      $this->currentUser,
      $this->logger,
      $eventDispatcher
    );
  }

  /**
   * Tests writing a file with valid permissions.
   *
   * @covers ::writeFile
   */
  public function testWriteFileWithValidPermissions() {
    // Mock user has permission.
    $this->currentUser->expects($this->once())
      ->method('hasPermission')
      ->willReturn(TRUE);

    // Mock file system prepareDirectory.
    $this->fileSystem->expects($this->once())
      ->method('prepareDirectory')
      ->willReturn(TRUE);

    $result = $this->fileSystemWriter->writeFile(
      '/valid/path/test.txt',
      'Test content'
    );

    $this->assertIsArray($result);
    $this->assertArrayHasKey('success', $result);
  }

  /**
   * Tests writing a file without permissions.
   *
   * @covers ::writeFile
   */
  public function testWriteFileWithoutPermissions() {
    // Mock user does not have permission.
    $this->currentUser->expects($this->once())
      ->method('hasPermission')
      ->willReturn(FALSE);

    $result = $this->fileSystemWriter->writeFile(
      '/valid/path/test.txt',
      'Test content'
    );

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('permission', $result['message']);
  }

  /**
   * Tests path validation with dangerous paths.
   *
   * @covers ::validatePath
   * @dataProvider dangerousPathProvider
   */
  public function testValidatePathWithDangerousPaths($path) {
    $method = new \ReflectionMethod($this->fileSystemWriter, 'validatePath');
    $method->setAccessible(TRUE);

    $result = $method->invoke($this->fileSystemWriter, $path);
    
    $this->assertFalse($result['valid']);
    $this->assertStringContainsString('dangerous', strtolower($result['message']));
  }

  /**
   * Provides dangerous paths for testing.
   */
  public function dangerousPathProvider() {
    return [
      ['../../../etc/passwd'],
      ['sites/default/settings.php'],
      ['/etc/shadow'],
      ['../../config/sync'],
      ['sites/default/files/../settings.php'],
    ];
  }

  /**
   * Tests content validation for different file types.
   *
   * @covers ::validateContent
   * @dataProvider contentValidationProvider
   */
  public function testValidateContent($content, $extension, $expected) {
    $method = new \ReflectionMethod($this->fileSystemWriter, 'validateContent');
    $method->setAccessible(TRUE);

    $result = $method->invoke($this->fileSystemWriter, $content, $extension);
    
    $this->assertEquals($expected, $result['valid']);
  }

  /**
   * Provides content validation test cases.
   */
  public function contentValidationProvider() {
    return [
      // Valid JSON.
      ['{"key": "value"}', 'json', TRUE],
      // Invalid JSON.
      ['invalid json', 'json', FALSE],
      // Valid YAML.
      ['key: value', 'yml', TRUE],
      // PHP code in non-PHP file.
      ['<?php echo "test"; ?>', 'txt', FALSE],
      // Valid PHP in PHP file.
      ['<?php echo "test"; ?>', 'php', TRUE],
    ];
  }

  /**
   * Tests backup creation.
   *
   * @covers ::createBackup
   */
  public function testCreateBackup() {
    $method = new \ReflectionMethod($this->fileSystemWriter, 'createBackup');
    $method->setAccessible(TRUE);

    $this->fileSystem->expects($this->once())
      ->method('copy')
      ->willReturn(TRUE);

    $result = $method->invoke($this->fileSystemWriter, '/path/to/file.txt');
    
    $this->assertTrue($result['success']);
  }

}