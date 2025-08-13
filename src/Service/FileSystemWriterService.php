<?php

namespace Drupal\component_entity\Service;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\component_entity\Event\FileWriteEvent;

/**
 * Service for safe file system operations with permissions and validation.
 */
class FileSystemWriterService {

  /**
   * @var FileSystemInterface
   */
  protected $fileSystem;

  /**
   * @var ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * @var ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * @var ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var AccountProxyInterface
   */
  protected $currentUser;

  /**
   * @var LoggerInterface
   */
  protected $logger;

  /**
   * @var EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Allowed file extensions for component files.
   */
  const ALLOWED_EXTENSIONS = [
    'yml', 'yaml', 'twig', 'html', 'js', 'jsx', 
    'ts', 'tsx', 'css', 'scss', 'json', 'md'
  ];

  /**
   * Dangerous patterns to check in file content.
   */
  const DANGEROUS_PATTERNS = [
    '/\<\?php/i',
    '/eval\s*\(/i',
    '/exec\s*\(/i',
    '/system\s*\(/i',
    '/passthru\s*\(/i',
    '/shell_exec\s*\(/i',
    '/\$_GET\[/i',
    '/\$_POST\[/i',
    '/\$_REQUEST\[/i',
    '/\$_SESSION\[/i',
    '/\$_COOKIE\[/i',
    '/\$_FILES\[/i',
    '/\$_SERVER\[/i',
  ];

  /**
   * Constructor.
   */
  public function __construct(
    FileSystemInterface $file_system,
    ModuleHandlerInterface $module_handler,
    ThemeHandlerInterface $theme_handler,
    ConfigFactoryInterface $config_factory,
    AccountProxyInterface $current_user,
    LoggerInterface $logger,
    EventDispatcherInterface $event_dispatcher
  ) {
    $this->fileSystem = $file_system;
    $this->moduleHandler = $module_handler;
    $this->themeHandler = $theme_handler;
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
    $this->logger = $logger;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * Writes a file with safety checks and permissions validation.
   *
   * @param string $file_path
   *   The file path to write.
   * @param string $content
   *   The content to write.
   * @param array $options
   *   Options array:
   *   - overwrite: Whether to overwrite existing files (default: FALSE)
   *   - backup: Create backup of existing file (default: TRUE)
   *   - validate: Validate content for security (default: TRUE)
   *   - permissions: File permissions (default: 0644)
   *
   * @return array
   *   Result array with 'success' boolean and 'message' string.
   */
  public function writeFile($file_path, $content, array $options = []) {
    $options += [
      'overwrite' => FALSE,
      'backup' => TRUE,
      'validate' => TRUE,
      'permissions' => 0644,
    ];

    try {
      // Step 1: Permission check
      if (!$this->hasWritePermission()) {
        return [
          'success' => FALSE,
          'message' => 'User does not have permission to write component files.',
        ];
      }

      // Step 2: Path validation
      $validation = $this->validatePath($file_path);
      if (!$validation['valid']) {
        return [
          'success' => FALSE,
          'message' => $validation['message'],
        ];
      }

      // Step 3: Extension check
      $extension = pathinfo($file_path, PATHINFO_EXTENSION);
      if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
        return [
          'success' => FALSE,
          'message' => 'File extension not allowed: ' . $extension,
        ];
      }

      // Step 4: Content validation
      if ($options['validate']) {
        $content_validation = $this->validateContent($content, $extension);
        if (!$content_validation['valid']) {
          return [
            'success' => FALSE,
            'message' => $content_validation['message'],
          ];
        }
      }

      // Step 5: Check if file exists
      if (file_exists($file_path)) {
        if (!$options['overwrite']) {
          return [
            'success' => FALSE,
            'message' => 'File already exists and overwrite is disabled.',
          ];
        }

        // Create backup if requested
        if ($options['backup']) {
          $backup_result = $this->createBackup($file_path);
          if (!$backup_result['success']) {
            return $backup_result;
          }
        }
      }

      // Step 6: Ensure directory exists
      $directory = dirname($file_path);
      if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY)) {
        return [
          'success' => FALSE,
          'message' => 'Could not create directory: ' . $directory,
        ];
      }

      // Step 7: Write the file
      $result = file_put_contents($file_path, $content);
      
      if ($result === FALSE) {
        return [
          'success' => FALSE,
          'message' => 'Failed to write file.',
        ];
      }

      // Step 8: Set permissions
      chmod($file_path, $options['permissions']);

      // Step 9: Log the operation
      $this->logger->info('File written: @path by user @uid', [
        '@path' => $file_path,
        '@uid' => $this->currentUser->id(),
      ]);

      // Step 10: Dispatch event
      $event = new FileWriteEvent($file_path, $content, $options);
      $this->eventDispatcher->dispatch($event, FileWriteEvent::FILE_WRITTEN);

      return [
        'success' => TRUE,
        'message' => 'File written successfully.',
        'path' => $file_path,
        'bytes' => $result,
      ];

    } catch (\Exception $e) {
      $this->logger->error('Error writing file: @error', [
        '@error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'message' => 'Error: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Deletes a file with safety checks.
   *
   * @param string $file_path
   *   The file path to delete.
   * @param bool $backup
   *   Whether to create a backup before deletion.
   *
   * @return array
   *   Result array with 'success' boolean and 'message' string.
   */
  public function deleteFile($file_path, $backup = TRUE) {
    // Permission check
    if (!$this->hasWritePermission()) {
      return [
        'success' => FALSE,
        'message' => 'User does not have permission to delete component files.',
      ];
    }

    // Path validation
    $validation = $this->validatePath($file_path);
    if (!$validation['valid']) {
      return [
        'success' => FALSE,
        'message' => $validation['message'],
      ];
    }

    // Check if file exists
    if (!file_exists($file_path)) {
      return [
        'success' => FALSE,
        'message' => 'File does not exist.',
      ];
    }

    // Create backup if requested
    if ($backup) {
      $backup_result = $this->createBackup($file_path);
      if (!$backup_result['success']) {
        return $backup_result;
      }
    }

    // Delete the file
    if (unlink($file_path)) {
      $this->logger->info('File deleted: @path by user @uid', [
        '@path' => $file_path,
        '@uid' => $this->currentUser->id(),
      ]);

      return [
        'success' => TRUE,
        'message' => 'File deleted successfully.',
      ];
    }

    return [
      'success' => FALSE,
      'message' => 'Failed to delete file.',
    ];
  }

  /**
   * Creates a backup of a file.
   *
   * @param string $file_path
   *   The file path to backup.
   *
   * @return array
   *   Result array with 'success' boolean and 'message' string.
   */
  protected function createBackup($file_path) {
    $backup_dir = $this->getBackupDirectory();
    
    if (!$this->fileSystem->prepareDirectory($backup_dir, FileSystemInterface::CREATE_DIRECTORY)) {
      return [
        'success' => FALSE,
        'message' => 'Could not create backup directory.',
      ];
    }

    $filename = basename($file_path);
    $timestamp = date('Y-m-d_H-i-s');
    $backup_path = $backup_dir . '/' . $filename . '.' . $timestamp . '.bak';

    if (copy($file_path, $backup_path)) {
      $this->logger->info('Backup created: @backup from @original', [
        '@backup' => $backup_path,
        '@original' => $file_path,
      ]);

      return [
        'success' => TRUE,
        'message' => 'Backup created successfully.',
        'backup_path' => $backup_path,
      ];
    }

    return [
      'success' => FALSE,
      'message' => 'Failed to create backup.',
    ];
  }

  /**
   * Validates a file path for safety.
   *
   * @param string $file_path
   *   The file path to validate.
   *
   * @return array
   *   Array with 'valid' boolean and 'message' string.
   */
  protected function validatePath($file_path) {
    // Check for directory traversal
    if (strpos($file_path, '..') !== FALSE) {
      return [
        'valid' => FALSE,
        'message' => 'Path contains directory traversal.',
      ];
    }

    // Get real path
    $real_path = realpath(dirname($file_path));
    if ($real_path === FALSE) {
      $real_path = dirname($file_path);
    }

    // Check if path is within allowed directories
    $allowed_paths = $this->getAllowedPaths();
    $is_allowed = FALSE;

    foreach ($allowed_paths as $allowed_path) {
      if (strpos($real_path, $allowed_path) === 0) {
        $is_allowed = TRUE;
        break;
      }
    }

    if (!$is_allowed) {
      return [
        'valid' => FALSE,
        'message' => 'Path is outside allowed directories.',
      ];
    }

    // Check for sensitive files
    $basename = basename($file_path);
    $sensitive_files = [
      '.htaccess', '.htpasswd', 'settings.php', 'settings.local.php',
      'services.yml', 'composer.json', 'composer.lock',
    ];

    if (in_array($basename, $sensitive_files)) {
      return [
        'valid' => FALSE,
        'message' => 'Cannot modify sensitive files.',
      ];
    }

    return [
      'valid' => TRUE,
      'message' => 'Path is valid.',
    ];
  }

  /**
   * Validates file content for security.
   *
   * @param string $content
   *   The content to validate.
   * @param string $extension
   *   The file extension.
   *
   * @return array
   *   Array with 'valid' boolean and 'message' string.
   */
  protected function validateContent($content, $extension) {
    // Check for PHP code in non-PHP files
    if (!in_array($extension, ['php', 'module', 'inc'])) {
      foreach (self::DANGEROUS_PATTERNS as $pattern) {
        if (preg_match($pattern, $content)) {
          return [
            'valid' => FALSE,
            'message' => 'Content contains potentially dangerous code.',
          ];
        }
      }
    }

    // Validate YAML files
    if (in_array($extension, ['yml', 'yaml'])) {
      try {
        \Symfony\Component\Yaml\Yaml::parse($content);
      } catch (\Exception $e) {
        return [
          'valid' => FALSE,
          'message' => 'Invalid YAML: ' . $e->getMessage(),
        ];
      }
    }

    // Validate JSON files
    if ($extension === 'json') {
      json_decode($content);
      if (json_last_error() !== JSON_ERROR_NONE) {
        return [
          'valid' => FALSE,
          'message' => 'Invalid JSON: ' . json_last_error_msg(),
        ];
      }
    }

    // Check file size
    $max_size = $this->getMaxFileSize();
    if (strlen($content) > $max_size) {
      return [
        'valid' => FALSE,
        'message' => 'Content exceeds maximum file size.',
      ];
    }

    return [
      'valid' => TRUE,
      'message' => 'Content is valid.',
    ];
  }

  /**
   * Checks if the current user has write permission.
   *
   * @return bool
   *   TRUE if the user has permission, FALSE otherwise.
   */
  protected function hasWritePermission() {
    return $this->currentUser->hasPermission('administer component types') ||
           $this->currentUser->hasPermission('manage component files');
  }

  /**
   * Gets allowed paths for file operations.
   *
   * @return array
   *   Array of allowed paths.
   */
  protected function getAllowedPaths() {
    $paths = [];
    
    // Add module paths
    $modules = $this->configFactory->get('component_entity.settings')
      ->get('allowed_modules') ?: ['component_entity'];
    
    foreach ($modules as $module_name) {
      if ($this->moduleHandler->moduleExists($module_name)) {
        $module = $this->moduleHandler->getModule($module_name);
        $paths[] = realpath($module->getPath() . '/components');
      }
    }

    // Add theme paths
    $themes = $this->configFactory->get('component_entity.settings')
      ->get('allowed_themes') ?: [];
    
    foreach ($themes as $theme_name) {
      if ($this->themeHandler->themeExists($theme_name)) {
        $theme = $this->themeHandler->getTheme($theme_name);
        $paths[] = realpath($theme->getPath() . '/components');
      }
    }

    // Add custom paths
    $custom_paths = $this->configFactory->get('component_entity.settings')
      ->get('custom_paths') ?: [];
    
    foreach ($custom_paths as $path) {
      if (is_dir($path)) {
        $paths[] = realpath($path);
      }
    }

    return array_filter($paths);
  }

  /**
   * Gets the backup directory.
   *
   * @return string
   *   The backup directory path.
   */
  protected function getBackupDirectory() {
    $config = $this->configFactory->get('component_entity.settings');
    $backup_dir = $config->get('backup_directory');
    
    if (!$backup_dir) {
      $backup_dir = 'private://component_backups';
    }
    
    return $backup_dir;
  }

  /**
   * Gets the maximum allowed file size.
   *
   * @return int
   *   The maximum file size in bytes.
   */
  protected function getMaxFileSize() {
    $config = $this->configFactory->get('component_entity.settings');
    $max_size = $config->get('max_file_size');
    
    if (!$max_size) {
      $max_size = 1048576; // 1MB default
    }
    
    return $max_size;
  }

  /**
   * Restores a file from backup.
   *
   * @param string $backup_path
   *   The backup file path.
   * @param string $restore_path
   *   The path to restore to.
   *
   * @return array
   *   Result array with 'success' boolean and 'message' string.
   */
  public function restoreFromBackup($backup_path, $restore_path) {
    // Permission check
    if (!$this->hasWritePermission()) {
      return [
        'success' => FALSE,
        'message' => 'User does not have permission to restore files.',
      ];
    }

    // Validate backup exists
    if (!file_exists($backup_path)) {
      return [
        'success' => FALSE,
        'message' => 'Backup file does not exist.',
      ];
    }

    // Validate restore path
    $validation = $this->validatePath($restore_path);
    if (!$validation['valid']) {
      return [
        'success' => FALSE,
        'message' => $validation['message'],
      ];
    }

    // Perform restore
    if (copy($backup_path, $restore_path)) {
      $this->logger->info('File restored from backup: @restore from @backup', [
        '@restore' => $restore_path,
        '@backup' => $backup_path,
      ]);

      return [
        'success' => TRUE,
        'message' => 'File restored successfully.',
      ];
    }

    return [
      'success' => FALSE,
      'message' => 'Failed to restore file.',
    ];
  }

  /**
   * Lists available backups for a file.
   *
   * @param string $original_file
   *   The original file path.
   *
   * @return array
   *   Array of backup file paths.
   */
  public function listBackups($original_file) {
    $backup_dir = $this->getBackupDirectory();
    $filename = basename($original_file);
    $pattern = $backup_dir . '/' . $filename . '.*.bak';
    
    $backups = glob($pattern);
    
    // Sort by modification time (newest first)
    usort($backups, function($a, $b) {
      return filemtime($b) - filemtime($a);
    });
    
    return $backups;
  }
}