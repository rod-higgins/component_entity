<?php

namespace Drupal\component_entity\EventSubscriber;

use Drupal\component_entity\ComponentSyncService;
use Drupal\component_entity\Event\ComponentSyncEvent;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for component sync events.
 *
 * Handles various sync-related events and performs actions like
 * logging, cache clearing, and additional processing.
 */
class ComponentSyncSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The component sync service.
   *
   * @var \Drupal\component_entity\ComponentSyncService
   */
  protected $syncService;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a ComponentSyncSubscriber object.
   *
   * @param \Drupal\component_entity\ComponentSyncService $sync_service
   *   The component sync service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(ComponentSyncService $sync_service, LoggerChannelFactoryInterface $logger_factory) {
    $this->syncService = $sync_service;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ComponentSyncEvent::SYNC_START => 'onSyncStart',
      ComponentSyncEvent::SYNC_COMPLETE => 'onSyncComplete',
      ComponentSyncEvent::COMPONENT_SYNCED => 'onComponentSynced',
      ComponentSyncEvent::SYNC_ERROR => 'onSyncError',
      ComponentSyncEvent::PRE_CREATE_TYPE => 'onPreCreateType',
      ComponentSyncEvent::POST_CREATE_TYPE => 'onPostCreateType',
      ComponentSyncEvent::PRE_SYNC_FIELDS => 'onPreSyncFields',
      ComponentSyncEvent::POST_SYNC_FIELDS => 'onPostSyncFields',
    ];
  }

  /**
   * Handles the sync start event.
   *
   * @param \Drupal\component_entity\Event\ComponentSyncEvent $event
   *   The sync event.
   */
  public function onSyncStart(ComponentSyncEvent $event) {
    $logger = $this->loggerFactory->get('component_entity');
    $logger->info('Component sync started.');
    
    // Store sync start time in state.
    \Drupal::state()->set('component_entity.sync_start_time', time());
    
    // Clear relevant caches.
    $this->clearCaches();
  }

  /**
   * Handles the sync complete event.
   *
   * @param \Drupal\component_entity\Event\ComponentSyncEvent $event
   *   The sync event.
   */
  public function onSyncComplete(ComponentSyncEvent $event) {
    $logger = $this->loggerFactory->get('component_entity');
    
    // Calculate sync duration.
    $start_time = \Drupal::state()->get('component_entity.sync_start_time', time());
    $duration = time() - $start_time;
    
    // Get statistics.
    $stats = $event->getStatistics();
    
    // Log completion with statistics.
    $logger->info('Component sync completed in @duration seconds. @summary', [
      '@duration' => $duration,
      '@summary' => $event->getSummary(),
    ]);
    
    // Store last sync time and results.
    \Drupal::state()->set('component_entity.last_sync', time());
    \Drupal::state()->set('component_entity.last_sync_results', $event->getResults());
    
    // Clear caches if components were created or updated.
    if ($stats['created'] > 0 || $stats['updated'] > 0) {
      $this->clearCaches();
      
      // Clear router cache if new bundles were created.
      if ($stats['created'] > 0) {
        \Drupal::service('router.builder')->rebuild();
      }
    }
    
    // Send notification if there were errors.
    if ($event->hasErrors()) {
      $this->notifyErrors($event);
    }
  }

  /**
   * Handles the component synced event.
   *
   * @param \Drupal\component_entity\Event\ComponentSyncEvent $event
   *   The sync event.
   */
  public function onComponentSynced(ComponentSyncEvent $event) {
    $logger = $this->loggerFactory->get('component_entity');
    
    $component_id = $event->getComponentId();
    $bundle = $event->getBundle();
    $is_new = $event->isNew();
    
    $logger->info('Component @component synced to bundle @bundle (@operation)', [
      '@component' => $component_id,
      '@bundle' => $bundle,
      '@operation' => $is_new ? 'created' : 'updated',
    ]);
    
    // Clear specific caches for this component.
    $cache_tags = [
      'component_type:' . $bundle,
      'component_list',
    ];
    \Drupal::service('cache_tags.invalidator')->invalidateTags($cache_tags);
    
    // If this is a new component type, ensure Field UI routes are available.
    if ($is_new) {
      $this->ensureFieldUiRoutes($bundle);
    }
  }

  /**
   * Handles sync errors.
   *
   * @param \Drupal\component_entity\Event\ComponentSyncEvent $event
   *   The sync event.
   */
  public function onSyncError(ComponentSyncEvent $event) {
    $logger = $this->loggerFactory->get('component_entity');
    
    $errors = $event->getErrors();
    foreach ($errors as $component_id => $error_data) {
      $logger->error('Error syncing component @component: @message', [
        '@component' => $component_id,
        '@message' => $error_data['message'],
      ] + $error_data['context']);
    }
    
    // Check if we should stop the sync.
    $max_errors = \Drupal::config('component_entity.settings')->get('sync_max_errors') ?? 10;
    if (count($errors) >= $max_errors) {
      $event->stopSync();
      $logger->error('Stopping sync due to too many errors (@count errors)', [
        '@count' => count($errors),
      ]);
    }
  }

  /**
   * Handles pre-create type event.
   *
   * @param \Drupal\component_entity\Event\ComponentSyncEvent $event
   *   The sync event.
   */
  public function onPreCreateType(ComponentSyncEvent $event) {
    $component_id = $event->getComponentId();
    $bundle = $event->getBundle();
    
    // Validate bundle name.
    if (!$this->isValidBundleName($bundle)) {
      $event->addError($component_id, 'Invalid bundle name: @bundle', ['@bundle' => $bundle]);
      $event->stopSync();
      return;
    }
    
    // Check for naming conflicts.
    if ($this->hasNamingConflict($bundle)) {
      $event->addError($component_id, 'Bundle name conflict: @bundle', ['@bundle' => $bundle]);
      $event->stopSync();
    }
  }

  /**
   * Handles post-create type event.
   *
   * @param \Drupal\component_entity\Event\ComponentSyncEvent $event
   *   The sync event.
   */
  public function onPostCreateType(ComponentSyncEvent $event) {
    $bundle = $event->getBundle();
    
    // Create default view modes.
    $this->createDefaultViewModes($bundle);
    
    // Set up default permissions.
    $this->setupDefaultPermissions($bundle);
  }

  /**
   * Handles pre-sync fields event.
   *
   * @param \Drupal\component_entity\Event\ComponentSyncEvent $event
   *   The sync event.
   */
  public function onPreSyncFields(ComponentSyncEvent $event) {
    $bundle = $event->getBundle();
    
    // Backup existing field configuration.
    $this->backupFieldConfiguration($bundle);
  }

  /**
   * Handles post-sync fields event.
   *
   * @param \Drupal\component_entity\Event\ComponentSyncEvent $event
   *   The sync event.
   */
  public function onPostSyncFields(ComponentSyncEvent $event) {
    $bundle = $event->getBundle();
    
    // Update form and view displays.
    $this->updateDisplays($bundle);
    
    // Clear field-related caches.
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * Clears relevant caches.
   */
  protected function clearCaches() {
    // Clear entity type definitions.
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    
    // Clear field definitions.
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
    
    // Clear plugin caches.
    \Drupal::service('plugin.manager.sdc')->clearCachedDefinitions();
    
    // Invalidate component-related cache tags.
    $cache_tags = [
      'component_list',
      'component_type_list',
      'config:field_storage_config_list',
    ];
    \Drupal::service('cache_tags.invalidator')->invalidateTags($cache_tags);
  }

  /**
   * Sends error notifications.
   *
   * @param \Drupal\component_entity\Event\ComponentSyncEvent $event
   *   The sync event.
   */
  protected function notifyErrors(ComponentSyncEvent $event) {
    $errors = $event->getErrors();
    $count = count($errors);
    
    // Log critical if many errors.
    if ($count > 5) {
      $logger = $this->loggerFactory->get('component_entity');
      $logger->critical('@count components failed to sync. Check logs for details.', [
        '@count' => $count,
      ]);
    }
    
    // Send email notification if configured.
    $config = \Drupal::config('component_entity.settings');
    if ($config->get('sync_error_notification')) {
      $this->sendErrorNotification($errors);
    }
  }

  /**
   * Sends error notification email.
   *
   * @param array $errors
   *   Array of errors.
   */
  protected function sendErrorNotification(array $errors) {
    $mail_manager = \Drupal::service('plugin.manager.mail');
    $module = 'component_entity';
    $key = 'sync_errors';
    $to = \Drupal::config('system.site')->get('mail');
    $params = [
      'errors' => $errors,
      'count' => count($errors),
    ];
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    
    $mail_manager->mail($module, $key, $to, $langcode, $params);
  }

  /**
   * Validates a bundle name.
   *
   * @param string $bundle
   *   The bundle name.
   *
   * @return bool
   *   TRUE if valid.
   */
  protected function isValidBundleName($bundle) {
    // Must be lowercase alphanumeric with underscores.
    if (!preg_match('/^[a-z0-9_]+$/', $bundle)) {
      return FALSE;
    }
    
    // Must not exceed 32 characters.
    if (strlen($bundle) > 32) {
      return FALSE;
    }
    
    // Must not start with a number.
    if (is_numeric($bundle[0])) {
      return FALSE;
    }
    
    // Must not be a reserved word.
    $reserved = ['id', 'uuid', 'type', 'status', 'created', 'changed', 'uid'];
    if (in_array($bundle, $reserved)) {
      return FALSE;
    }
    
    return TRUE;
  }

  /**
   * Checks for naming conflicts.
   *
   * @param string $bundle
   *   The bundle name.
   *
   * @return bool
   *   TRUE if there's a conflict.
   */
  protected function hasNamingConflict($bundle) {
    // Check if another entity type uses this bundle.
    $entity_types = \Drupal::entityTypeManager()->getDefinitions();
    foreach ($entity_types as $entity_type) {
      if ($entity_type->getBundleEntityType()) {
        $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type->id());
        if (isset($bundles[$bundle])) {
          return TRUE;
        }
      }
    }
    
    return FALSE;
  }

  /**
   * Ensures Field UI routes are available for a bundle.
   *
   * @param string $bundle
   *   The bundle name.
   */
  protected function ensureFieldUiRoutes($bundle) {
    // Trigger route rebuild if Field UI is enabled.
    if (\Drupal::moduleHandler()->moduleExists('field_ui')) {
      \Drupal::service('router.builder')->setRebuildNeeded();
    }
  }

  /**
   * Creates default view modes for a bundle.
   *
   * @param string $bundle
   *   The bundle name.
   */
  protected function createDefaultViewModes($bundle) {
    // This would typically create view mode configurations.
    // For now, just ensure default displays exist.
    $display_repository = \Drupal::service('entity_display.repository');
    
    // Ensure default form display exists.
    $display_repository->getFormDisplay('component', $bundle, 'default');
    
    // Ensure default view display exists.
    $display_repository->getViewDisplay('component', $bundle, 'default');
  }

  /**
   * Sets up default permissions for a bundle.
   *
   * @param string $bundle
   *   The bundle name.
   */
  protected function setupDefaultPermissions($bundle) {
    // This would typically set up role permissions.
    // For now, just log that it should be done.
    $logger = $this->loggerFactory->get('component_entity');
    $logger->info('Default permissions should be configured for bundle @bundle', [
      '@bundle' => $bundle,
    ]);
  }

  /**
   * Backs up field configuration for a bundle.
   *
   * @param string $bundle
   *   The bundle name.
   */
  protected function backupFieldConfiguration($bundle) {
    // Store current field configuration in state for potential rollback.
    $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('component', $bundle);
    \Drupal::state()->set('component_entity.field_backup.' . $bundle, $fields);
  }

  /**
   * Updates form and view displays for a bundle.
   *
   * @param string $bundle
   *   The bundle name.
   */
  protected function updateDisplays($bundle) {
    // Ensure displays are properly configured.
    $display_repository = \Drupal::service('entity_display.repository');
    
    // Update form display.
    $form_display = $display_repository->getFormDisplay('component', $bundle, 'default');
    $form_display->save();
    
    // Update view display.
    $view_display = $display_repository->getViewDisplay('component', $bundle, 'default');
    $view_display->save();
  }

}