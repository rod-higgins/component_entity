<?php

namespace Drupal\component_entity\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\Component\ComponentPluginManager;
use Drupal\component_entity\ComponentCacheManager;
use Drupal\component_entity\ComponentSyncService;
use Drush\Commands\DrushCommands;
use Drush\Attributes\Command;
use Drush\Attributes\Argument;
use Drush\Attributes\Option;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;

/**
 * Drush commands for Component Entity module.
 */
class ComponentEntityCommands extends DrushCommands {

  /**
   * The component sync service.
   *
   * @var \Drupal\component_entity\ComponentSyncService
   */
  protected $syncService;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The SDC plugin manager.
   *
   * @var \Drupal\Core\Plugin\Component\ComponentPluginManager
   */
  protected $componentPluginManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * The component cache manager.
   *
   * @var \Drupal\component_entity\ComponentCacheManager
   */
  protected $cacheManager;

  /**
   * Constructs a ComponentEntityCommands object.
   *
   * @param \Drupal\component_entity\ComponentSyncService $sync_service
   *   The component sync service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Plugin\Component\ComponentPluginManager $component_plugin_manager
   *   The SDC plugin manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list.
   * @param \Drupal\component_entity\ComponentCacheManager $cache_manager
   *   The component cache manager.
   */
  public function __construct(
    ComponentSyncService $sync_service,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    ComponentPluginManager $component_plugin_manager,
    EntityFieldManagerInterface $entity_field_manager,
    ModuleExtensionList $module_extension_list,
    ComponentCacheManager $cache_manager,
  ) {
    parent::__construct();
    $this->syncService = $sync_service;
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
    $this->componentPluginManager = $component_plugin_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->moduleExtensionList = $module_extension_list;
    $this->cacheManager = $cache_manager;
  }

  /**
   * Synchronize SDC components with component entity types.
   *
   * @command component:sync
   * @aliases csync, component-sync
   * @option force Force sync even if components haven't changed
   * @option dry-run Show what would be synced without making changes
   * @usage component:sync
   *   Sync all SDC components with component entity types.
   * @usage component:sync --force
   *   Force re-sync of all components.
   */
  #[Command(
    name: 'component:sync',
    aliases: ['csync', 'component-sync']
  )]
  #[Option(
    name: 'force',
    description: 'Force sync even if components haven\'t changed'
  )]
  #[Option(
    name: 'dry-run',
    description: 'Show what would be synced without making changes'
  )]
  public function sync($options = ['force' => FALSE, 'dry-run' => FALSE]) {
    $force = $options['force'];
    $dry_run = $options['dry-run'];

    $this->io()->title('Component Entity Sync');

    if ($dry_run) {
      $this->io()->warning('DRY RUN MODE - No changes will be made');
    }

    // Perform sync.
    if (!$dry_run) {
      $results = $this->syncService->syncComponents($force);

      // Display results.
      if (!empty($results['created'])) {
        $this->io()->success(sprintf('Created %d new component types:', count($results['created'])));
        foreach ($results['created'] as $component) {
          $this->io()->writeln('  - ' . $component);
        }
      }

      if (!empty($results['updated'])) {
        $this->io()->success(sprintf('Updated %d component types:', count($results['updated'])));
        foreach ($results['updated'] as $component) {
          $this->io()->writeln('  - ' . $component);
        }
      }

      if (!empty($results['skipped'])) {
        $this->io()->note(sprintf('Skipped %d unchanged components', count($results['skipped'])));
      }

      if (!empty($results['errors'])) {
        $this->io()->error(sprintf('Failed to sync %d components:', count($results['errors'])));
        foreach ($results['errors'] as $component) {
          $this->io()->writeln('  - ' . $component);
        }
      }

      $this->io()->success('Component sync completed');
    }
    else {
      // Dry run - just show what would be synced.
      $components = $this->componentPluginManager->getAllComponents();
      $this->io()->writeln(sprintf('Found %d SDC components', count($components)));

      foreach ($components as $id => $component) {
        $this->io()->writeln('  - ' . $id . ' (' . ($component->metadata->name ?? 'No name') . ')');
      }
    }
  }

  /**
   * List all component types.
   *
   * @command component:list-types
   * @aliases clt, component-types
   * @field-labels
   *   id: ID
   *   label: Label
   *   sdc_id: SDC ID
   *   count: Components
   *   render_methods: Render Methods
   * @default-fields id,label,count,render_methods
   * @usage component:list-types
   *   List all component types.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   A table of component types with their details.
   */
  #[Command(
    name: 'component:list-types',
    aliases: ['clt', 'component-types']
  )]
  public function listTypes() {
    $types = $this->entityTypeManager
      ->getStorage('component_type')
      ->loadMultiple();

    $rows = [];
    foreach ($types as $type) {
      $component_count = $this->entityTypeManager
        ->getStorage('component')
        ->getQuery()
        ->condition('type', $type->id())
        ->accessCheck(FALSE)
        ->count()
        ->execute();

      $render_methods = [];
      $rendering = $type->get('rendering') ?? [];
      if (!empty($rendering['twig_enabled'])) {
        $render_methods[] = 'Twig';
      }
      if (!empty($rendering['react_enabled'])) {
        $render_methods[] = 'React';
      }

      $rows[] = [
        'id' => $type->id(),
        'label' => $type->label(),
        'sdc_id' => $type->get('sdc_id') ?? 'N/A',
        'count' => $component_count,
        'render_methods' => implode(', ', $render_methods),
      ];
    }

    return new RowsOfFields($rows);
  }

  /**
   * List component entities.
   *
   * @command component:list
   * @aliases cl, components
   * @option type Filter by component type
   * @option render-method Filter by render method (twig/react)
   * @option limit Limit number of results
   * @field-labels
   *   id: ID
   *   name: Name
   *   type: Type
   *   render_method: Render Method
   *   created: Created
   *   changed: Updated
   * @default-fields id,name,type,render_method
   * @usage component:list
   *   List all components.
   * @usage component:list --type=hero_banner
   *   List all hero banner components.
   * @usage component:list --render-method=react
   *   List all React-rendered components.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   A table of components with their details.
   */
  #[Command(
    name: 'component:list',
    aliases: ['cl', 'components']
  )]
  #[Option(
    name: 'type',
    description: 'Filter by component type'
  )]
  #[Option(
    name: 'render-method',
    description: 'Filter by render method (twig/react)'
  )]
  #[Option(
    name: 'limit',
    description: 'Limit number of results'
  )]
  public function listComponents($options = ['type' => NULL, 'render-method' => NULL, 'limit' => 50]) {
    $query = $this->entityTypeManager
      ->getStorage('component')
      ->getQuery()
      ->accessCheck(FALSE);

    if ($options['type']) {
      $query->condition('type', $options['type']);
    }

    if ($options['render-method']) {
      $query->condition('render_method', $options['render-method']);
    }

    if ($options['limit']) {
      $query->range(0, $options['limit']);
    }

    $query->sort('changed', 'DESC');

    $ids = $query->execute();
    $components = $this->entityTypeManager
      ->getStorage('component')
      ->loadMultiple($ids);

    $rows = [];
    foreach ($components as $component) {
      $rows[] = [
        'id' => $component->id(),
        'name' => $component->label(),
        'type' => $component->bundle(),
        'render_method' => $component->getRenderMethod(),
        'created' => date('Y-m-d H:i', $component->getCreatedTime()),
        'changed' => date('Y-m-d H:i', $component->getChangedTime()),
      ];
    }

    return new RowsOfFields($rows);
  }

  /**
   * Export a component to JSON.
   *
   * @command component:export
   * @aliases cexp
   * @argument id The component entity ID
   * @option include-fields Include field configuration
   * @usage component:export 123
   *   Export component with ID 123 to JSON.
   */
  #[Command(
    name: 'component:export',
    aliases: ['cexp']
  )]
  #[Argument(
    name: 'id',
    description: 'The component entity ID'
  )]
  #[Option(
    name: 'include-fields',
    description: 'Include field configuration'
  )]
  public function export($id, $options = ['include-fields' => FALSE]) {
    $component = $this->entityTypeManager
      ->getStorage('component')
      ->load($id);

    if (!$component) {
      $this->io()->error(sprintf('Component with ID %d not found', $id));
      return 1;
    }

    $export = [
      'id' => $component->id(),
      'uuid' => $component->uuid(),
      'type' => $component->bundle(),
      'name' => $component->label(),
      'render_method' => $component->getRenderMethod(),
      'react_config' => $component->getReactConfig(),
      'created' => $component->getCreatedTime(),
      'changed' => $component->getChangedTime(),
      'fields' => [],
    ];

    // Export field values.
    foreach ($component->getFields() as $field_name => $field) {
      if (strpos($field_name, 'field_') === 0 && !$field->isEmpty()) {
        $export['fields'][$field_name] = $field->getValue();
      }
    }

    // Include field configuration if requested.
    if ($options['include-fields']) {
      $export['field_config'] = [];
      $fields = $this->entityFieldManager
        ->getFieldDefinitions('component', $component->bundle());

      foreach ($fields as $field_name => $field_definition) {
        if (strpos($field_name, 'field_') === 0) {
          $export['field_config'][$field_name] = [
            'type' => $field_definition->getType(),
            'label' => $field_definition->getLabel(),
            'required' => $field_definition->isRequired(),
            'cardinality' => $field_definition->getFieldStorageDefinition()->getCardinality(),
          ];
        }
      }
    }

    $this->io()->writeln(json_encode($export, JSON_PRETTY_PRINT));

    return 0;
  }

  /**
   * Build React components.
   *
   * @command component:build
   * @aliases cbuild
   * @option watch Watch for changes and rebuild
   * @option production Build for production
   * @usage component:build
   *   Build all React components.
   * @usage component:build --watch
   *   Watch and rebuild React components on change.
   */
  #[Command(
    name: 'component:build',
    aliases: ['cbuild']
  )]
  #[Option(
    name: 'watch',
    description: 'Watch for changes and rebuild'
  )]
  #[Option(
    name: 'production',
    description: 'Build for production'
  )]
  public function build($options = ['watch' => FALSE, 'production' => FALSE]) {
    $module_path = $this->moduleExtensionList->getPath('component_entity');

    $this->io()->title('Building React Components');

    // Check if npm is installed.
    $npm_check = shell_exec('npm --version 2>&1');
    if (!$npm_check) {
      $this->io()->error('npm is not installed. Please install Node.js and npm.');
      return 1;
    }

    // Change to module directory.
    chdir($module_path);

    // Install dependencies if needed.
    if (!file_exists('node_modules')) {
      $this->io()->section('Installing dependencies...');
      $this->io()->writeln(shell_exec('npm install 2>&1'));
    }

    // Build command.
    $command = $options['watch'] ? 'npm run watch' : 'npm run build';

    if ($options['production']) {
      $command = 'npm run build:production';
    }

    $this->io()->section('Building components...');

    if ($options['watch']) {
      $this->io()->note('Watching for changes. Press Ctrl+C to stop.');
    }

    // Execute build.
    passthru($command, $return_code);

    if ($return_code === 0) {
      $this->io()->success('Build completed successfully');
    }
    else {
      $this->io()->error('Build failed');
    }

    return $return_code;
  }

  /**
   * Clear component caches.
   *
   * @command component:cache-clear
   * @aliases ccc
   * @option type Clear caches for specific component type
   * @usage component:cache-clear
   *   Clear all component caches.
   * @usage component:cache-clear --type=hero_banner
   *   Clear caches for hero_banner components.
   */
  #[Command(
    name: 'component:cache-clear',
    aliases: ['ccc']
  )]
  #[Option(
    name: 'type',
    description: 'Clear caches for specific component type'
  )]
  public function cacheClear($options = ['type' => NULL]) {
    if ($options['type']) {
      $this->cacheManager->invalidateBundleCache($options['type']);
      $this->io()->success(sprintf('Cleared caches for component type: %s', $options['type']));
    }
    else {
      $this->cacheManager->clearAllComponentCaches();
      $this->io()->success('Cleared all component caches');
    }

    return 0;
  }

  /**
   * Create a new component entity.
   *
   * @command component:create
   * @aliases cc
   * @argument type The component type machine name
   * @argument name The component name/label
   * @option render-method The render method (twig/react)
   * @option data JSON data for component fields
   * @usage component:create hero_banner "Homepage Hero"
   *   Create a new hero banner component.
   * @usage component:create card "Product Card" --render-method=react
   *   Create a new card component with React rendering.
   */
  #[Command(
    name: 'component:create',
    aliases: ['cc']
  )]
  #[Argument(
    name: 'type',
    description: 'The component type machine name'
  )]
  #[Argument(
    name: 'name',
    description: 'The component name/label'
  )]
  #[Option(
    name: 'render-method',
    description: 'The render method (twig/react)'
  )]
  #[Option(
    name: 'data',
    description: 'JSON data for component fields'
  )]
  public function create($type, $name, $options = ['render-method' => 'twig', 'data' => NULL]) {
    // Check if component type exists.
    $component_type = $this->entityTypeManager
      ->getStorage('component_type')
      ->load($type);

    if (!$component_type) {
      $this->io()->error(sprintf('Component type "%s" does not exist', $type));
      return 1;
    }

    // Create component entity.
    $values = [
      'type' => $type,
      'name' => $name,
      'render_method' => $options['render-method'],
    ];

    // Parse additional field data if provided.
    if ($options['data']) {
      $data = json_decode($options['data'], TRUE);
      if (json_last_error() !== JSON_ERROR_NONE) {
        $this->io()->error('Invalid JSON data provided');
        return 1;
      }

      foreach ($data as $field => $value) {
        $values['field_' . $field] = $value;
      }
    }

    $component = $this->entityTypeManager
      ->getStorage('component')
      ->create($values);

    $component->save();

    $this->io()->success(sprintf('Created component "%s" (ID: %d)', $name, $component->id()));

    return 0;
  }

  /**
   * Delete a component entity.
   *
   * @command component:delete
   * @aliases cd
   * @argument id The component entity ID
   * @option force Skip confirmation
   * @usage component:delete 123
   *   Delete component with ID 123.
   */
  #[Command(
    name: 'component:delete',
    aliases: ['cd']
  )]
  #[Argument(
    name: 'id',
    description: 'The component entity ID'
  )]
  #[Option(
    name: 'force',
    description: 'Skip confirmation'
  )]
  public function delete($id, $options = ['force' => FALSE]) {
    $component = $this->entityTypeManager
      ->getStorage('component')
      ->load($id);

    if (!$component) {
      $this->io()->error(sprintf('Component with ID %d not found', $id));
      return 1;
    }

    if (!$options['force']) {
      $confirm = $this->io()->confirm(
        sprintf('Are you sure you want to delete component "%s" (ID: %d)?',
          $component->label(),
          $component->id()
        )
      );

      if (!$confirm) {
        $this->io()->note('Delete cancelled');
        return 0;
      }
    }

    $component->delete();
    $this->io()->success(sprintf('Deleted component ID %d', $id));

    return 0;
  }

}
