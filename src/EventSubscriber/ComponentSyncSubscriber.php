<?php

namespace Drupal\component_entity\EventSubscriber;

use Drupal\component_entity\ComponentSyncService;
use Drupal\component_entity\Event\ComponentSyncEvent;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Display\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Plugin\ComponentPluginManager;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\State\StateInterface;
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
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * The router builder.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routerBuilder;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\Display\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The SDC plugin manager.
   *
   * @var \Drupal\Core\Plugin\ComponentPluginManager
   */
  protected $componentPluginManager;

  /**
   * Constructs a ComponentSyncSubscriber object.
   *
   * @param \Drupal\component_entity\ComponentSyncService $sync_service
   *   The component sync service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $router_builder
   *   The router builder.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\Display\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Plugin\ComponentPluginManager $component_plugin_manager
   *   The SDC plugin manager.
   */
  public function __construct(
    ComponentSyncService $sync_service,
    LoggerChannelFactoryInterface $logger_factory,
    StateInterface $state,
    EntityFieldManagerInterface $entity_field_manager,
    EntityTypeManagerInterface $entity_type_manager,
    CacheTagsInvalidatorInterface $cache_tags_invalidator,
    RouteBuilderInterface $router_builder,
    ConfigFactoryInterface $config_factory,
    EntityDisplayRepositoryInterface $entity_display_repository,
    ModuleHandlerInterface $module_handler,
    MailManagerInterface $mail_manager,
    AccountProxyInterface $current_user,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    ComponentPluginManager $component_plugin_manager,
  ) {
    $this->syncService = $sync_service;
    $this->loggerFactory = $logger_factory;
    $this->state = $state;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
    $this->routerBuilder = $router_builder;
    $this->configFactory = $config_factory;
    $this->entityDisplayRepository = $entity_display_repository;
    $this->moduleHandler = $module_handler;
    $this->mailManager = $mail_manager;
    $this->currentUser = $current_user;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->componentPluginManager = $component_plugin_manager;
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
    $this->state->set('component_entity.sync_start_time', time());

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
    $start_time = $this->state->get('component_entity.sync_start_time', time());
    $duration = time() - $start_time;

    // Get statistics.
    $stats = $event->getStatistics();

    // Log completion with statistics.
    $logger->info('Component sync completed in @duration seconds. @summary', [
      '@duration' => $duration,
      '@summary' => $event->getSummary(),
    ]);

    // Store last sync time and results.
    $this->state->set('component_entity.last_sync', time());
    $this->state->set('component_entity.last_sync_results', $event->getResults());

    // Clear caches if components were created or updated.
    if ($stats['created'] > 0 || $stats['updated'] > 0) {
      $this->clearCaches();

      // Clear router cache if new bundles were created.
      if ($stats['created'] > 0) {
        $this->routerBuilder->rebuild();
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
    $this->cacheTagsInvalidator->invalidateTags($cache_tags);

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
    $config = $this->configFactory->get('component_entity.settings');
    $max_errors = $config->get('sync_max_errors') ?? 10;
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
    $this->entityFieldManager->clearCachedFieldDefinitions();
  }

  /**
   * Clears relevant caches.
   */
  protected function clearCaches() {
    // Clear entity type definitions.
    $this->entityTypeManager->clearCachedDefinitions();

    // Clear field definitions.
    $this->entityFieldManager->clearCachedFieldDefinitions();

    // Clear plugin caches.
    $this->componentPluginManager->clearCachedDefinitions();

    // Invalidate component-related cache tags.
    $cache_tags = [
      'component_list',
      'component_type_list',
      'config:field_storage_config_list',
    ];
    $this->cacheTagsInvalidator->invalidateTags($cache_tags);
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
    $config = $this->configFactory->get('component_entity.settings');
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
    $module = 'component_entity';
    $key = 'sync_errors';
    $site_config = $this->configFactory->get('system.site');
    $to = $site_config->get('mail');
    $params = [
      'errors' => $errors,
      'count' => count($errors),
    ];
    $langcode = $this->currentUser->getPreferredLangcode();

    $this->mailManager->mail($module, $key, $to, $langcode, $params);
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
    $entity_types = $this->entityTypeManager->getDefinitions();
    foreach ($entity_types as $entity_type) {
      if ($entity_type->getBundleEntityType()) {
        $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type->id());
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
    if ($this->moduleHandler->moduleExists('field_ui')) {
      $this->routerBuilder->setRebuildNeeded();
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
    // Ensure default form display exists.
    $this->entityDisplayRepository->getFormDisplay('component', $bundle, 'default');

    // Ensure default view display exists.
    $this->entityDisplayRepository->getViewDisplay('component', $bundle, 'default');
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
    $fields = $this->entityFieldManager->getFieldDefinitions('component', $bundle);
    $this->state->set('component_entity.field_backup.' . $bundle, $fields);
  }

  /**
   * Updates form and view displays for a bundle.
   *
   * @param string $bundle
   *   The bundle name.
   */
  protected function updateDisplays($bundle) {
    // Ensure displays are properly configured.
    // Update form display.
    $form_display = $this->entityDisplayRepository->getFormDisplay('component', $bundle, 'default');
    $form_display->save();

    // Update view display.
    $view_display = $this->entityDisplayRepository->getViewDisplay('component', $bundle, 'default');
    $view_display->save();
  }

}
