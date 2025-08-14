<?php

namespace Drupal\component_entity\EventSubscriber;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\component_entity\Event\BiDirectionalSyncEvent;
use Drupal\component_entity\Event\FileWriteEvent;
use Drupal\component_entity\Event\ComponentSyncEvent;
use Drupal\Core\Messenger\MessengerInterface;
use Psr\Log\LoggerInterface;

/**
 * Event subscriber for sync operations.
 */
class SyncEventSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructor.
   */
  public function __construct(MessengerInterface $messenger, LoggerInterface $logger) {
    $this->messenger = $messenger;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      BiDirectionalSyncEvent::PRE_SYNC => 'onPreSync',
      BiDirectionalSyncEvent::POST_SYNC => 'onPostSync',
      ComponentSyncEvent::COMPONENT_SYNCED => 'onComponentSynced',
    ];
  }

  /**
   * Handles pre-sync events.
   *
   * @param \Drupal\component_entity\Event\BiDirectionalSyncEvent $event
   *   The sync event.
   */
  public function onPreSync(BiDirectionalSyncEvent $event) {
    $component_type = $event->getComponentType();
    $operation = $event->getOperation();

    // Log the sync start.
    $this->logger->info('Starting @operation sync for component type: @type', [
      '@operation' => $operation,
      '@type' => $component_type->id(),
    ]);

    // Check if sync should be cancelled (example validation)
    if ($this->shouldCancelSync($component_type)) {
      $event->cancel();
      $this->messenger->addError(t('Sync cancelled for @type due to validation errors.', [
        '@type' => $component_type->label(),
      ]));
      return;
    }

    // Notify user of sync start.
    $this->messenger->addStatus(t('Starting @operation sync for @type...', [
      '@operation' => $operation,
      '@type' => $component_type->label(),
    ]));
  }

  /**
   * Handles post-sync events.
   *
   * @param \Drupal\component_entity\Event\BiDirectionalSyncEvent $event
   *   The sync event.
   */
  public function onPostSync(BiDirectionalSyncEvent $event) {
    $component_type = $event->getComponentType();
    $results = $event->getResults();

    // Log the sync completion.
    $this->logger->info('Completed sync for component type: @type', [
      '@type' => $component_type->id(),
    ]);

    // Process results and notify user.
    if ($results['success']) {
      $this->messenger->addStatus(t('Successfully synced @type component.', [
        '@type' => $component_type->label(),
      ]));

      // Report on individual operations.
      if (isset($results['operations'])) {
        foreach ($results['operations'] as $op => $result) {
          if ($result['success']) {
            $this->messenger->addStatus(t('✓ @op completed successfully.', [
              '@op' => ucfirst($op),
            ]));
          }
          else {
            $this->messenger->addWarning(t('⚠ @op failed: @message', [
              '@op' => ucfirst($op),
              '@message' => $result['message'],
            ]));
          }
        }
      }
    }
    else {
      $this->messenger->addError(t('Failed to sync @type component.', [
        '@type' => $component_type->label(),
      ]));
    }

    // Clear caches if configured.
    $config = \Drupal::config('component_entity.settings');
    if ($config->get('cache.clear_on_sync')) {
      $this->clearRelevantCaches($component_type);
    }
  }

  /**
   * Handles component synced events.
   *
   * @param \Drupal\component_entity\Event\ComponentSyncEvent $event
   *   The component sync event.
   */
  public function onComponentSynced(ComponentSyncEvent $event) {
    $component_id = $event->get('component_id');
    $is_new = $event->get('is_new', FALSE);

    if ($is_new) {
      $this->logger->info('New component synchronized: @id', [
        '@id' => $component_id,
      ]);
    }
    else {
      $this->logger->info('Component updated: @id', [
        '@id' => $component_id,
      ]);
    }
  }

  /**
   * Determines if sync should be cancelled.
   *
   * @param \Drupal\component_entity\Entity\ComponentTypeInterface $component_type
   *   The component type.
   *
   * @return bool
   *   TRUE if sync should be cancelled.
   */
  protected function shouldCancelSync($component_type) {
    // Example validation logic
    // Could check for:
    // - File system permissions
    // - Component naming conflicts
    // - Missing dependencies
    // - User permissions.
    // For now, always allow sync.
    return FALSE;
  }

  /**
   * Clears relevant caches after sync.
   *
   * @param \Drupal\component_entity\Entity\ComponentTypeInterface $component_type
   *   The component type.
   */
  protected function clearRelevantCaches($component_type) {
    // Clear render cache.
    \Drupal::service('cache.render')->invalidateAll();

    // Clear discovery caches.
    \Drupal::service('plugin.manager.sdc')->clearCachedDefinitions();

    // Clear theme registry.
    \Drupal::service('theme.registry')->reset();

    $this->messenger->addStatus(t('Caches cleared after sync.'));
  }

}

/**
 * Event subscriber for file operations.
 */
class FileEventSubscriber implements EventSubscriberInterface {

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructor.
   */
  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      FileWriteEvent::FILE_WRITTEN => 'onFileWritten',
      FileWriteEvent::FILE_DELETED => 'onFileDeleted',
    ];
  }

  /**
   * Handles file written events.
   *
   * @param \Drupal\component_entity\Event\FileWriteEvent $event
   *   The file write event.
   */
  public function onFileWritten(FileWriteEvent $event) {
    $file_path = $event->getFilePath();
    $options = $event->getOptions();

    // Log file operation if configured.
    $config = \Drupal::config('component_entity.settings');
    if ($config->get('logging.log_file_operations')) {
      $this->logger->info('File written: @path', [
        '@path' => $file_path,
      ]);

      if ($config->get('logging.verbose_logging')) {
        $this->logger->debug('File write details: @details', [
          '@details' => json_encode($options),
        ]);
      }
    }

    // Trigger additional processing.
    $this->processWrittenFile($file_path, $event->getContent());
  }

  /**
   * Handles file deleted events.
   *
   * @param \Drupal\component_entity\Event\FileWriteEvent $event
   *   The file write event.
   */
  public function onFileDeleted(FileWriteEvent $event) {
    $file_path = $event->getFilePath();

    $this->logger->warning('File deleted: @path', [
      '@path' => $file_path,
    ]);

    // Could trigger cleanup operations here.
    $this->cleanupAfterDeletion($file_path);
  }

  /**
   * Processes a written file.
   *
   * @param string $file_path
   *   The file path.
   * @param string $content
   *   The file content.
   */
  protected function processWrittenFile($file_path, $content) {
    $extension = pathinfo($file_path, PATHINFO_EXTENSION);

    switch ($extension) {
      case 'jsx':
      case 'tsx':
        // Could trigger React build process.
        $this->triggerReactBuild($file_path);
        break;

      case 'scss':
        // Could trigger SCSS compilation.
        $this->triggerScssCompilation($file_path);
        break;

      case 'yml':
      case 'yaml':
        // Could validate YAML structure.
        $this->validateYamlFile($file_path, $content);
        break;
    }
  }

  /**
   * Cleans up after file deletion.
   *
   * @param string $file_path
   *   The deleted file path.
   */
  protected function cleanupAfterDeletion($file_path) {
    // Remove any generated files associated with the deleted file.
    $base_name = pathinfo($file_path, PATHINFO_FILENAME);
    $directory = dirname($file_path);

    // Example: Remove compiled JS if JSX was deleted.
    if (substr($file_path, -4) === '.jsx') {
      $compiled_path = $directory . '/dist/' . $base_name . '.js';
      if (file_exists($compiled_path)) {
        unlink($compiled_path);
        $this->logger->info('Removed compiled file: @path', [
          '@path' => $compiled_path,
        ]);
      }
    }
  }

  /**
   * Triggers React build process.
   *
   * @param string $file_path
   *   The React component file path.
   */
  protected function triggerReactBuild($file_path) {
    // This would integrate with your build system
    // For example, trigger webpack or other build tools.
    $this->logger->info('React build triggered for: @path', [
      '@path' => $file_path,
    ]);
  }

  /**
   * Triggers SCSS compilation.
   *
   * @param string $file_path
   *   The SCSS file path.
   */
  protected function triggerScssCompilation($file_path) {
    // This would integrate with your SCSS compiler.
    $this->logger->info('SCSS compilation triggered for: @path', [
      '@path' => $file_path,
    ]);
  }

  /**
   * Validates a YAML file.
   *
   * @param string $file_path
   *   The YAML file path.
   * @param string $content
   *   The file content.
   */
  protected function validateYamlFile($file_path, $content) {
    try {
      $parsed = Yaml::parse($content);
      $this->logger->info('YAML file validated: @path', [
        '@path' => $file_path,
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Invalid YAML in @path: @error', [
        '@path' => $file_path,
        '@error' => $e->getMessage(),
      ]);
    }
  }

}
