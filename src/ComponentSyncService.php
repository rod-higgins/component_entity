<?php

namespace Drupal\component_entity;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\Component\ComponentPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\component_entity\Event\ComponentSyncEvent;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Service for synchronizing SDC components with component entity types.
 */
class ComponentSyncService {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The SDC plugin manager.
   *
   * @var \Drupal\Core\Plugin\Component\ComponentPluginManager
   */
  protected $componentManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a ComponentSyncService object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ComponentPluginManager $component_manager,
    LoggerChannelFactoryInterface $logger_factory,
    MessengerInterface $messenger,
    EventDispatcherInterface $event_dispatcher,
    ConfigFactoryInterface $config_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->componentManager = $component_manager;
    $this->loggerFactory = $logger_factory;
    $this->messenger = $messenger;
    $this->eventDispatcher = $event_dispatcher;
    $this->configFactory = $config_factory;
  }

  /**
   * Synchronizes all SDC components with component entity types.
   *
   * @param bool $force
   *   Whether to force sync even if component hasn't changed.
   *
   * @return array
   *   Array with sync results.
   */
  public function syncComponents($force = FALSE) {
    $results = [
      'created' => [],
      'updated' => [],
      'skipped' => [],
      'errors' => [],
    ];
    
    $logger = $this->loggerFactory->get('component_entity');
    
    // Get all SDC components.
    $components = $this->componentManager->getAllComponents();
    
    foreach ($components as $component_id => $component) {
      try {
        $result = $this->syncComponent($component_id, $component, $force);
        
        if ($result['status'] === 'created') {
          $results['created'][] = $component_id;
          $logger->info('Created component type for @component', ['@component' => $component_id]);
        }
        elseif ($result['status'] === 'updated') {
          $results['updated'][] = $component_id;
          $logger->info('Updated component type for @component', ['@component' => $component_id]);
        }
        else {
          $results['skipped'][] = $component_id;
        }
      }
      catch (\Exception $e) {
        $results['errors'][] = $component_id;
        $logger->error('Error syncing component @component: @message', [
          '@component' => $component_id,
          '@message' => $e->getMessage(),
        ]);
      }
    }
    
    // Dispatch sync complete event.
    $event = new ComponentSyncEvent($results);
    $this->eventDispatcher->dispatch($event, ComponentSyncEvent::SYNC_COMPLETE);
    
    // Display messages.
    if (!empty($results['created'])) {
      $this->messenger->addStatus($this->t('Created @count new component types.', [
        '@count' => count($results['created']),
      ]));
    }
    
    if (!empty($results['updated'])) {
      $this->messenger->addStatus($this->t('Updated @count component types.', [
        '@count' => count($results['updated']),
      ]));
    }
    
    if (!empty($results['errors'])) {
      $this->messenger->addError($this->t('Failed to sync @count components. Check logs for details.', [
        '@count' => count($results['errors']),
      ]));
    }
    
    return $results;
  }

  /**
   * Synchronizes a single SDC component.
   *
   * @param string $component_id
   *   The SDC component ID.
   * @param object $component
   *   The SDC component definition.
   * @param bool $force
   *   Whether to force sync.
   *
   * @return array
   *   Sync result with 'status' key.
   */
  protected function syncComponent($component_id, $component, $force = FALSE) {
    // Generate machine name for the bundle.
    $bundle = $this->generateBundleName($component_id);
    
    // Load or create component type.
    $component_type_storage = $this->entityTypeManager->getStorage('component_type');
    $component_type = $component_type_storage->load($bundle);
    
    $is_new = FALSE;
    if (!$component_type) {
      $component_type = $component_type_storage->create([
        'id' => $bundle,
        'label' => $component->metadata->name ?? $component_id,
      ]);
      $is_new = TRUE;
    }
    
    // Check if component needs updating.
    if (!$is_new && !$force && !$this->componentNeedsUpdate($component_type, $component)) {
      return ['status' => 'skipped'];
    }
    
    // Update component type with SDC metadata.
    $component_type->set('sdc_id', $component_id);
    $component_type->set('description', $component->metadata->description ?? '');
    
    // Set rendering configuration.
    $rendering_config = [
      'twig_enabled' => TRUE,
      'react_enabled' => FALSE,
      'default_method' => 'twig',
    ];
    
    // Check for React component.
    if ($this->hasReactComponent($component_id)) {
      $rendering_config['react_enabled'] = TRUE;
    }
    
    $component_type->set('rendering', $rendering_config);
    
    // Save component type.
    $component_type->save();
    
    // Sync fields based on props.
    $this->syncComponentFields($bundle, $component);
    
    // Sync slots as fields.
    $this->syncComponentSlots($bundle, $component);
    
    // Dispatch event.
    $event = new ComponentSyncEvent([
      'component_id' => $component_id,
      'bundle' => $bundle,
      'is_new' => $is_new,
    ]);
    $this->eventDispatcher->dispatch($event, ComponentSyncEvent::COMPONENT_SYNCED);
    
    return ['status' => $is_new ? 'created' : 'updated'];
  }

  /**
   * Syncs component fields based on SDC props.
   *
   * @param string $bundle
   *   The component bundle.
   * @param object $component
   *   The SDC component definition.
   */
  protected function syncComponentFields($bundle, $component) {
    if (!isset($component->metadata->props)) {
      return;
    }
    
    foreach ($component->metadata->props as $prop_name => $prop_schema) {
      $field_name = 'field_' . $prop_name;
      
      // Skip if field already exists.
      $field = FieldConfig::loadByName('component', $bundle, $field_name);
      if ($field) {
        continue;
      }
      
      // Create field storage if it doesn't exist.
      $field_storage = FieldStorageConfig::loadByName('component', $field_name);
      if (!$field_storage) {
        $field_storage = $this->createFieldStorage($field_name, $prop_schema);
      }
      
      // Create field instance.
      $this->createFieldInstance($bundle, $field_name, $prop_name, $prop_schema);
    }
  }

  /**
   * Syncs component slots as fields.
   *
   * @param string $bundle
   *   The component bundle.
   * @param object $component
   *   The SDC component definition.
   */
  protected function syncComponentSlots($bundle, $component) {
    if (!isset($component->metadata->slots)) {
      return;
    }
    
    foreach ($component->metadata->slots as $slot_name => $slot_schema) {
      $field_name = 'field_' . $slot_name . '_slot';
      
      // Skip if field already exists.
      $field = FieldConfig::loadByName('component', $bundle, $field_name);
      if ($field) {
        continue;
      }
      
      // Create field storage for slot (usually text_long or entity_reference).
      $field_storage = FieldStorageConfig::loadByName('component', $field_name);
      if (!$field_storage) {
        $field_storage = FieldStorageConfig::create([
          'field_name' => $field_name,
          'entity_type' => 'component',
          'type' => 'text_long',
          'cardinality' => 1,
        ]);
        $field_storage->save();
      }
      
      // Create field instance.
      $field = FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'component',
        'bundle' => $bundle,
        'label' => $slot_schema->title ?? ucfirst(str_replace('_', ' ', $slot_name)),
        'description' => $slot_schema->description ?? '',
        'required' => $slot_schema->required ?? FALSE,
      ]);
      $field->save();
    }
  }

  /**
   * Creates field storage based on prop schema.
   *
   * @param string $field_name
   *   The field name.
   * @param object $prop_schema
   *   The prop schema.
   *
   * @return \Drupal\field\Entity\FieldStorageConfig
   *   The created field storage.
   */
  protected function createFieldStorage($field_name, $prop_schema) {
    $field_type = $this->mapPropTypeToFieldType($prop_schema);
    
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'component',
      'type' => $field_type,
      'cardinality' => $this->getFieldCardinality($prop_schema),
    ]);
    
    // Add field settings based on type.
    if ($field_type === 'list_string' && isset($prop_schema->enum)) {
      $allowed_values = [];
      foreach ($prop_schema->enum as $value) {
        $allowed_values[$value] = $value;
      }
      $field_storage->setSetting('allowed_values', $allowed_values);
    }
    
    $field_storage->save();
    return $field_storage;
  }

  /**
   * Creates field instance for a component bundle.
   *
   * @param string $bundle
   *   The component bundle.
   * @param string $field_name
   *   The field name.
   * @param string $prop_name
   *   The prop name.
   * @param object $prop_schema
   *   The prop schema.
   */
  protected function createFieldInstance($bundle, $field_name, $prop_name, $prop_schema) {
    $field = FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'component',
      'bundle' => $bundle,
      'label' => $prop_schema->title ?? ucfirst(str_replace('_', ' ', $prop_name)),
      'description' => $prop_schema->description ?? '',
      'required' => $prop_schema->required ?? FALSE,
      'default_value' => isset($prop_schema->default) ? [$prop_schema->default] : [],
    ]);
    
    // Add field settings based on type.
    if (isset($prop_schema->minLength)) {
      $field->setSetting('min_length', $prop_schema->minLength);
    }
    if (isset($prop_schema->maxLength)) {
      $field->setSetting('max_length', $prop_schema->maxLength);
    }
    
    $field->save();
    
    // Configure form display.
    $this->configureFormDisplay($bundle, $field_name, $prop_schema);
    
    // Configure view display.
    $this->configureViewDisplay($bundle, $field_name, $prop_schema);
  }

  /**
   * Maps SDC prop type to Drupal field type.
   *
   * @param object $prop_schema
   *   The prop schema.
   *
   * @return string
   *   The Drupal field type.
   */
  protected function mapPropTypeToFieldType($prop_schema) {
    $type = $prop_schema->type ?? 'string';
    
    // Check for enum first (list field).
    if (isset($prop_schema->enum)) {
      return 'list_string';
    }
    
    // Map based on type.
    switch ($type) {
      case 'boolean':
        return 'boolean';
        
      case 'integer':
        return 'integer';
        
      case 'number':
        return 'decimal';
        
      case 'object':
      case 'array':
        return 'json';
        
      case 'string':
      default:
        // Check for format hints.
        if (isset($prop_schema->format)) {
          switch ($prop_schema->format) {
            case 'email':
              return 'email';
              
            case 'uri':
            case 'url':
              return 'link';
              
            case 'date':
            case 'date-time':
              return 'datetime';
              
            case 'color':
              return 'string';
          }
        }
        
        // Check for long text.
        if (isset($prop_schema->maxLength) && $prop_schema->maxLength > 255) {
          return 'text_long';
        }
        
        return 'string';
    }
  }

  /**
   * Gets field cardinality based on prop schema.
   *
   * @param object $prop_schema
   *   The prop schema.
   *
   * @return int
   *   The field cardinality.
   */
  protected function getFieldCardinality($prop_schema) {
    if ($prop_schema->type === 'array') {
      return isset($prop_schema->maxItems) ? $prop_schema->maxItems : -1;
    }
    return 1;
  }

  /**
   * Configures form display for a field.
   *
   * @param string $bundle
   *   The component bundle.
   * @param string $field_name
   *   The field name.
   * @param object $prop_schema
   *   The prop schema.
   */
  protected function configureFormDisplay($bundle, $field_name, $prop_schema) {
    $form_display = \Drupal::service('entity_display.repository')
      ->getFormDisplay('component', $bundle, 'default');
    
    $widget_type = 'string_textfield';
    $widget_settings = [];
    
    // Determine widget based on field type and schema.
    $field_config = FieldConfig::loadByName('component', $bundle, $field_name);
    if ($field_config) {
      $field_type = $field_config->getType();
      
      switch ($field_type) {
        case 'boolean':
          $widget_type = 'boolean_checkbox';
          break;
          
        case 'text_long':
          $widget_type = 'text_textarea';
          $widget_settings['rows'] = 5;
          break;
          
        case 'list_string':
          $widget_type = isset($prop_schema->enum) && count($prop_schema->enum) <= 5 
            ? 'options_buttons' 
            : 'options_select';
          break;
          
        case 'json':
          $widget_type = 'json_textarea';
          break;
      }
    }
    
    $form_display->setComponent($field_name, [
      'type' => $widget_type,
      'settings' => $widget_settings,
      'weight' => $this->getFieldWeight($field_name),
    ]);
    
    $form_display->save();
  }

  /**
   * Configures view display for a field.
   *
   * @param string $bundle
   *   The component bundle.
   * @param string $field_name
   *   The field name.
   * @param object $prop_schema
   *   The prop schema.
   */
  protected function configureViewDisplay($bundle, $field_name, $prop_schema) {
    $view_display = \Drupal::service('entity_display.repository')
      ->getViewDisplay('component', $bundle, 'default');
    
    // Hide all fields by default (they're passed to the component).
    $view_display->removeComponent($field_name);
    $view_display->save();
  }

  /**
   * Gets field weight based on field name.
   *
   * @param string $field_name
   *   The field name.
   *
   * @return int
   *   The field weight.
   */
  protected function getFieldWeight($field_name) {
    static $weight = 0;
    return $weight++;
  }

  /**
   * Generates a bundle name from component ID.
   *
   * @param string $component_id
   *   The SDC component ID.
   *
   * @return string
   *   The bundle machine name.
   */
  protected function generateBundleName($component_id) {
    // Remove namespace prefix if present.
    $parts = explode(':', $component_id);
    $name = end($parts);
    
    // Convert to machine name.
    $name = preg_replace('/[^a-z0-9_]+/', '_', strtolower($name));
    
    // Ensure it doesn't exceed 32 characters.
    if (strlen($name) > 32) {
      $name = substr($name, 0, 32);
    }
    
    return $name;
  }

  /**
   * Checks if a component needs updating.
   *
   * @param \Drupal\Core\Entity\EntityInterface $component_type
   *   The component type entity.
   * @param object $component
   *   The SDC component definition.
   *
   * @return bool
   *   TRUE if component needs update.
   */
  protected function componentNeedsUpdate($component_type, $component) {
    // Compare checksums or timestamps.
    $stored_checksum = $component_type->get('checksum');
    $current_checksum = $this->calculateComponentChecksum($component);
    
    return $stored_checksum !== $current_checksum;
  }

  /**
   * Calculates a checksum for a component.
   *
   * @param object $component
   *   The SDC component definition.
   *
   * @return string
   *   The checksum.
   */
  protected function calculateComponentChecksum($component) {
    return md5(serialize($component->metadata));
  }

  /**
   * Checks if a React component exists for the SDC component.
   *
   * @param string $component_id
   *   The SDC component ID.
   *
   * @return bool
   *   TRUE if React component exists.
   */
  protected function hasReactComponent($component_id) {
    $module_path = \Drupal::service('extension.list.module')
      ->getPath('component_entity');
    
    $bundle = $this->generateBundleName($component_id);
    $jsx_file = $module_path . '/components/' . $bundle . '/' . $bundle . '.jsx';
    $tsx_file = $module_path . '/components/' . $bundle . '/' . $bundle . '.tsx';
    
    return file_exists($jsx_file) || file_exists($tsx_file);
  }

  /**
   * Gets sync status information.
   *
   * @return array
   *   Array with sync status data.
   */
  public function getSyncStatus() {
    $status = [
      'synced_count' => 0,
      'errors' => [],
      'last_sync' => NULL,
    ];
    
    // Get count of synced component types.
    $component_types = $this->entityTypeManager
      ->getStorage('component_type')
      ->loadMultiple();
    
    foreach ($component_types as $component_type) {
      if ($component_type->get('sdc_id')) {
        $status['synced_count']++;
      }
    }
    
    // Get last sync time from state.
    $status['last_sync'] = \Drupal::state()->get('component_entity.last_sync');
    
    return $status;
  }

}