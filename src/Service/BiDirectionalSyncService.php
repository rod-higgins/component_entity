<?php

namespace Drupal\component_entity\Service;

use Symfony\Component\Yaml\Yaml;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\component_entity\Generator\SdcGeneratorService;
use Drupal\component_entity\Generator\TemplateGeneratorService;
use Drupal\component_entity\Generator\ReactGeneratorService;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\field\Entity\FieldConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\component_entity\Event\BiDirectionalSyncEvent;

/**
 * Service for bi-directional synchronization between entities and SDC files.
 */
class BiDirectionalSyncService {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\component_entity\Generator\SdcGeneratorService
   */
  protected $sdcGenerator;

  /**
   * @var \Drupal\component_entity\Generator\TemplateGeneratorService
   */
  protected $templateGenerator;

  /**
   * @var \Drupal\component_entity\Generator\ReactGeneratorService
   */
  protected $reactGenerator;

  /**
   * @var \Drupal\component_entity\Service\FileSystemWriterService
   */
  protected $fileWriter;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    SdcGeneratorService $sdc_generator,
    TemplateGeneratorService $template_generator,
    ReactGeneratorService $react_generator,
    FileSystemWriterService $file_writer,
    ModuleHandlerInterface $module_handler,
    ThemeHandlerInterface $theme_handler,
    ConfigFactoryInterface $config_factory,
    LoggerInterface $logger,
    EventDispatcherInterface $event_dispatcher,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->sdcGenerator = $sdc_generator;
    $this->templateGenerator = $template_generator;
    $this->reactGenerator = $react_generator;
    $this->fileWriter = $file_writer;
    $this->moduleHandler = $module_handler;
    $this->themeHandler = $theme_handler;
    $this->configFactory = $config_factory;
    $this->logger = $logger;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * Handles when a new component type is created through the UI.
   *
   * @param \Drupal\component_entity\Entity\ComponentTypeInterface $component_type
   *   The component type entity.
   *
   * @return array
   *   Results array with status of each operation.
   */
  public function handleComponentTypeCreated($component_type) {
    $results = [
      'success' => TRUE,
      'operations' => [],
    ];

    // Get generation options from config or component type.
    $options = $this->getGenerationOptions($component_type);

    // Dispatch pre-sync event.
    $event = new BiDirectionalSyncEvent($component_type, 'create');
    $this->eventDispatcher->dispatch($event, BiDirectionalSyncEvent::PRE_SYNC);

    if ($event->isCancelled()) {
      return [
        'success' => FALSE,
        'message' => 'Sync cancelled by event subscriber.',
      ];
    }

    // Get component path.
    $component_path = $this->getComponentPath($component_type, $options);

    // 1. Generate component.yml
    if ($options['generate_yml']) {
      $yml_result = $this->generateComponentYml($component_type, $component_path, $options);
      $results['operations']['yml'] = $yml_result;
      if (!$yml_result['success']) {
        $results['success'] = FALSE;
      }
    }

    // 2. Generate Twig template
    if ($options['generate_twig'] && $component_type->isTwigEnabled()) {
      $twig_result = $this->generateTwigTemplate($component_type, $component_path, $options);
      $results['operations']['twig'] = $twig_result;
      if (!$twig_result['success']) {
        $results['success'] = FALSE;
      }
    }

    // 3. Generate React component
    if ($options['generate_react'] && $component_type->isReactEnabled()) {
      $react_result = $this->generateReactComponent($component_type, $component_path, $options);
      $results['operations']['react'] = $react_result;
      if (!$react_result['success']) {
        $results['success'] = FALSE;
      }
    }

    // 4. Generate CSS file
    if ($options['generate_css']) {
      $css_result = $this->generateCssFile($component_type, $component_path, $options);
      $results['operations']['css'] = $css_result;
      if (!$css_result['success']) {
        $results['success'] = FALSE;
      }
    }

    // 5. Update libraries.yml if React is enabled
    if ($component_type->isReactEnabled()) {
      $library_result = $this->updateLibrariesYml($component_type, $options);
      $results['operations']['library'] = $library_result;
    }

    // Dispatch post-sync event.
    $post_event = new BiDirectionalSyncEvent($component_type, 'create', $results);
    $this->eventDispatcher->dispatch($post_event, BiDirectionalSyncEvent::POST_SYNC);

    // Log the operation.
    $this->logger->info('Bi-directional sync completed for new component type: @type', [
      '@type' => $component_type->id(),
    ]);

    return $results;
  }

  /**
   * Handles when a component type is updated through the UI.
   *
   * @param \Drupal\component_entity\Entity\ComponentTypeInterface $component_type
   *   The component type entity.
   *
   * @return array
   *   Results array with status of each operation.
   */
  public function handleComponentTypeUpdated($component_type) {
    $results = [
      'success' => TRUE,
      'operations' => [],
    ];

    $options = $this->getGenerationOptions($component_type);
    // Allow overwriting for updates.
    $options['overwrite'] = TRUE;

    // Dispatch pre-sync event.
    $event = new BiDirectionalSyncEvent($component_type, 'update');
    $this->eventDispatcher->dispatch($event, BiDirectionalSyncEvent::PRE_SYNC);

    if ($event->isCancelled()) {
      return [
        'success' => FALSE,
        'message' => 'Sync cancelled by event subscriber.',
      ];
    }

    $component_path = $this->getComponentPath($component_type, $options);

    // Update component.yml.
    if ($options['generate_yml']) {
      $yml_result = $this->generateComponentYml($component_type, $component_path, $options);
      $results['operations']['yml'] = $yml_result;
    }

    // Optionally regenerate templates if structure changed significantly.
    if ($options['regenerate_templates']) {
      if ($component_type->isTwigEnabled()) {
        $twig_result = $this->generateTwigTemplate($component_type, $component_path, $options);
        $results['operations']['twig'] = $twig_result;
      }

      if ($component_type->isReactEnabled()) {
        $react_result = $this->generateReactComponent($component_type, $component_path, $options);
        $results['operations']['react'] = $react_result;
      }
    }

    // Dispatch post-sync event.
    $post_event = new BiDirectionalSyncEvent($component_type, 'update', $results);
    $this->eventDispatcher->dispatch($post_event, BiDirectionalSyncEvent::POST_SYNC);

    return $results;
  }

  /**
   * Handles when a field is added to a component type.
   *
   * @param \Drupal\field\Entity\FieldConfig $field
   *   The field configuration.
   * @param \Drupal\component_entity\Entity\ComponentTypeInterface $component_type
   *   The component type entity.
   *
   * @return array
   *   Results array.
   */
  public function handleFieldAdded(FieldConfig $field, $component_type) {
    $options = $this->getGenerationOptions($component_type);
    $component_path = $this->getComponentPath($component_type, $options);

    $results = [];

    // Update component.yml with new field.
    $options['overwrite'] = TRUE;
    $yml_result = $this->generateComponentYml($component_type, $component_path, $options);
    $results['yml'] = $yml_result;

    // Optionally update templates to include new field.
    if ($options['update_templates_on_field_change']) {
      $results['templates'] = $this->updateTemplatesForField($field, $component_type, 'add');
    }

    // Clear caches.
    $this->clearComponentCaches($component_type);

    return $results;
  }

  /**
   * Handles when a field is updated on a component type.
   *
   * @param \Drupal\field\Entity\FieldConfig $field
   *   The field configuration.
   * @param \Drupal\component_entity\Entity\ComponentTypeInterface $component_type
   *   The component type entity.
   *
   * @return array
   *   Results array.
   */
  public function handleFieldUpdated(FieldConfig $field, $component_type) {
    $options = $this->getGenerationOptions($component_type);
    $component_path = $this->getComponentPath($component_type, $options);

    $results = [];

    // Update component.yml.
    $options['overwrite'] = TRUE;
    $yml_result = $this->generateComponentYml($component_type, $component_path, $options);
    $results['yml'] = $yml_result;

    // Optionally update templates.
    if ($options['update_templates_on_field_change']) {
      $results['templates'] = $this->updateTemplatesForField($field, $component_type, 'update');
    }

    return $results;
  }

  /**
   * Handles when a field is deleted from a component type.
   *
   * @param \Drupal\field\Entity\FieldConfig $field
   *   The field configuration.
   * @param \Drupal\component_entity\Entity\ComponentTypeInterface $component_type
   *   The component type entity.
   *
   * @return array
   *   Results array.
   */
  public function handleFieldDeleted(FieldConfig $field, $component_type) {
    $options = $this->getGenerationOptions($component_type);
    $component_path = $this->getComponentPath($component_type, $options);

    $results = [];

    // Update component.yml to remove field.
    $options['overwrite'] = TRUE;
    $yml_result = $this->generateComponentYml($component_type, $component_path, $options);
    $results['yml'] = $yml_result;

    // Optionally update templates to remove field references.
    if ($options['update_templates_on_field_change']) {
      $results['templates'] = $this->updateTemplatesForField($field, $component_type, 'delete');
    }

    return $results;
  }

  /**
   * Checks and syncs a component type if needed.
   *
   * @param \Drupal\component_entity\Entity\ComponentTypeInterface $component_type
   *   The component type entity.
   *
   * @return array
   *   Results array.
   */
  public function checkAndSyncComponentType($component_type) {
    $options = $this->getGenerationOptions($component_type);
    $component_path = $this->getComponentPath($component_type, $options);

    $needs_sync = FALSE;
    $sync_reasons = [];

    // Check if component.yml exists.
    $yml_file = $component_path . '/' . $component_type->id() . '.component.yml';
    if (!file_exists($yml_file)) {
      $needs_sync = TRUE;
      $sync_reasons[] = 'component.yml missing';
    }

    // Check if Twig template exists.
    if ($component_type->isTwigEnabled()) {
      $twig_file = $component_path . '/' . $component_type->id() . '.html.twig';
      if (!file_exists($twig_file)) {
        $needs_sync = TRUE;
        $sync_reasons[] = 'Twig template missing';
      }
    }

    // Check if React component exists.
    if ($component_type->isReactEnabled()) {
      $react_files = [
        $component_path . '/' . $this->getComponentName($component_type->id()) . '.jsx',
        $component_path . '/' . $this->getComponentName($component_type->id()) . '.tsx',
      ];

      $react_exists = FALSE;
      foreach ($react_files as $react_file) {
        if (file_exists($react_file)) {
          $react_exists = TRUE;
          break;
        }
      }

      if (!$react_exists) {
        $needs_sync = TRUE;
        $sync_reasons[] = 'React component missing';
      }
    }

    if ($needs_sync) {
      $this->logger->info('Component type @type needs sync: @reasons', [
        '@type' => $component_type->id(),
        '@reasons' => implode(', ', $sync_reasons),
      ]);

      return $this->handleComponentTypeUpdated($component_type);
    }

    return [
      'success' => TRUE,
      'message' => 'Component type is in sync',
    ];
  }

  /**
   * Helper methods.
   */

  /**
   * Generates component.yml file.
   */
  protected function generateComponentYml($component_type, $component_path, $options) {
    $yml_result = $this->sdcGenerator->generateComponentYml($component_type, $options);

    if ($yml_result['success'] && isset($yml_result['path'])) {
      // Read generated content.
      $content = file_get_contents($yml_result['path']);

      // Write using safe file writer.
      $write_result = $this->fileWriter->writeFile(
        $yml_result['path'],
        $content,
        ['overwrite' => $options['overwrite'] ?? FALSE]
      );

      return $write_result;
    }

    return $yml_result;
  }

  /**
   * Generates Twig template.
   */
  protected function generateTwigTemplate($component_type, $component_path, $options) {
    return $this->templateGenerator->generateTwigTemplate($component_type, $component_path, $options);
  }

  /**
   * Generates React component.
   */
  protected function generateReactComponent($component_type, $component_path, $options) {
    return $this->reactGenerator->generateReactComponent($component_type, $component_path, $options);
  }

  /**
   * Generates CSS file.
   */
  protected function generateCssFile($component_type, $component_path, $options) {
    $component_name = str_replace('_', '-', $component_type->id());
    $css_file = $component_path . '/' . $component_type->id() . '.css';

    $content = "/**\n";
    $content .= " * Styles for " . $component_type->label() . "\n";
    $content .= " */\n\n";
    $content .= "." . $component_name . " {\n";
    $content .= "  /* Component styles */\n";
    $content .= "}\n";

    return $this->fileWriter->writeFile($css_file, $content, [
      'overwrite' => $options['overwrite'] ?? FALSE,
    ]);
  }

  /**
   * Updates libraries.yml file.
   */
  protected function updateLibrariesYml($component_type, $options) {
    $module_name = $options['name'] ?? 'component_entity';
    $module = $this->moduleHandler->getModule($module_name);
    $libraries_file = $module->getPath() . '/' . $module_name . '.libraries.yml';

    // Read existing libraries.yml.
    $libraries = [];
    if (file_exists($libraries_file)) {
      $content = file_get_contents($libraries_file);
      $libraries = Yaml::parse($content) ?: [];
    }

    // Add component library.
    $component_key = 'component.' . $component_type->id();
    $component_name = $this->getComponentName($component_type->id());

    $libraries[$component_key] = [
      'version' => '1.x',
      'js' => [
        'dist/js/' . $component_name . '.js' => [],
      ],
      'css' => [
        'component' => [
          'components/' . $component_type->id() . '/' . $component_type->id() . '.css' => [],
        ],
      ],
      'dependencies' => [
        'core/react',
        'core/react-dom',
        'component_entity/react-renderer',
      ],
    ];

    // Convert back to YAML.
    $yaml_content = Yaml::dump($libraries, 4, 2);

    return $this->fileWriter->writeFile($libraries_file, $yaml_content, [
      'overwrite' => TRUE,
      'backup' => TRUE,
    ]);
  }

  /**
   * Updates templates when fields change.
   */
  protected function updateTemplatesForField($field, $component_type, $operation) {
    // This would intelligently update existing templates
    // For now, just log the intention.
    $this->logger->info('Template update needed for field @field (@operation) on @type', [
      '@field' => $field->getName(),
      '@operation' => $operation,
      '@type' => $component_type->id(),
    ]);

    return [
      'success' => TRUE,
      'message' => 'Template update logged',
    ];
  }

  /**
   * Gets generation options from config.
   */
  protected function getGenerationOptions($component_type) {
    $config = $this->configFactory->get('component_entity.settings');

    $options = [
      'target' => $config->get('generation_target') ?: 'module',
      'name' => $config->get('generation_name') ?: 'component_entity',
      'overwrite' => $config->get('overwrite_files') ?: FALSE,
      'generate_yml' => $config->get('generate_yml') !== FALSE,
      'generate_twig' => $config->get('generate_twig') !== FALSE,
      'generate_react' => $config->get('generate_react') !== FALSE,
      'generate_css' => $config->get('generate_css') !== FALSE,
      'typescript' => $config->get('use_typescript') ?: FALSE,
      'css_modules' => $config->get('use_css_modules') ?: FALSE,
      'update_templates_on_field_change' => $config->get('update_templates_on_field_change') ?: FALSE,
      'regenerate_templates' => $config->get('regenerate_templates') ?: FALSE,
      'style' => $config->get('template_style') ?: 'bem',
    ];

    // Override with component-specific settings if available.
    if ($component_type->getSetting('generation_options')) {
      $options = array_merge($options, $component_type->getSetting('generation_options'));
    }

    return $options;
  }

  /**
   * Gets the component directory path.
   */
  protected function getComponentPath($component_type, array $options) {
    if ($options['target'] === 'theme') {
      $theme = $this->themeHandler->getTheme($options['name']);
      $base_path = $theme->getPath();
    }
    else {
      $module = $this->moduleHandler->getModule($options['name']);
      $base_path = $module->getPath();
    }

    return $base_path . '/components/' . $component_type->id();
  }

  /**
   * Gets the component name in PascalCase.
   */
  protected function getComponentName($bundle) {
    return str_replace(' ', '', ucwords(str_replace('_', ' ', $bundle)));
  }

  /**
   * Clears component-related caches.
   */
  protected function clearComponentCaches($component_type) {
    // Clear render cache.
    \Drupal::service('cache.render')->invalidateAll();

    // Clear discovery caches.
    \Drupal::service('plugin.manager.sdc')->clearCachedDefinitions();

    // Clear theme registry.
    \Drupal::service('theme.registry')->reset();

    $this->logger->info('Cleared caches for component type: @type', [
      '@type' => $component_type->id(),
    ]);
  }

}
