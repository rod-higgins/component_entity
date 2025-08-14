<?php

namespace Drupal\component_entity\Generator;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Symfony\Component\Yaml\Yaml;
use Psr\Log\LoggerInterface;

/**
 * Service for generating SDC component files from entity definitions.
 */
class SdcGeneratorService {

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The theme handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructor.
   */
  public function __construct(
    EntityFieldManagerInterface $entity_field_manager,
    EntityTypeManagerInterface $entity_type_manager,
    FileSystemInterface $file_system,
    ModuleHandlerInterface $module_handler,
    ThemeHandlerInterface $theme_handler,
    LoggerInterface $logger,
  ) {
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
    $this->moduleHandler = $module_handler;
    $this->themeHandler = $theme_handler;
    $this->logger = $logger;
  }

  /**
   * Generates SDC component.yml from a component type entity.
   *
   * @param \Drupal\component_entity\Entity\ComponentTypeInterface $component_type
   *   The component type entity.
   * @param array $options
   *   Options for generation:
   *   - target: 'module' or 'theme' (default: 'module')
   *   - name: Module or theme name (default: 'component_entity')
   *   - overwrite: Whether to overwrite existing files (default: FALSE)
   *
   * @return array
   *   Result array with 'success' boolean and 'message' string.
   */
  public function generateComponentYml($component_type, array $options = []) {
    $options += [
      'target' => 'module',
      'name' => 'component_entity',
      'overwrite' => FALSE,
    ];

    try {
      // Get the component directory path.
      $component_path = $this->getComponentPath($component_type, $options);

      // Check if file exists and overwrite is false.
      $yml_file = $component_path . '/' . $component_type->id() . '.component.yml';
      if (file_exists($yml_file) && !$options['overwrite']) {
        return [
          'success' => FALSE,
          'message' => 'Component.yml already exists. Use overwrite option to replace.',
        ];
      }

      // Build the component definition.
      $definition = $this->buildComponentDefinition($component_type);

      // Convert to YAML.
      $yaml_content = Yaml::dump($definition, 4, 2);

      // Add header comment.
      $header = "# Auto-generated SDC component definition\n";
      $header .= "# Generated from component type: " . $component_type->id() . "\n";
      $header .= "# Generated on: " . date('Y-m-d H:i:s') . "\n\n";
      $yaml_content = $header . $yaml_content;

      // Ensure directory exists.
      $this->fileSystem->prepareDirectory($component_path, FileSystemInterface::CREATE_DIRECTORY);

      // Write the file.
      $result = file_put_contents($yml_file, $yaml_content);

      if ($result !== FALSE) {
        $this->logger->info('Generated component.yml for @type at @path', [
          '@type' => $component_type->id(),
          '@path' => $yml_file,
        ]);

        return [
          'success' => TRUE,
          'message' => 'Successfully generated component.yml',
          'path' => $yml_file,
        ];
      }

      return [
        'success' => FALSE,
        'message' => 'Failed to write component.yml file',
      ];

    }
    catch (\Exception $e) {
      $this->logger->error('Error generating component.yml: @error', [
        '@error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'message' => 'Error: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Builds the component definition array from entity fields.
   *
   * @param \Drupal\component_entity\Entity\ComponentTypeInterface $component_type
   *   The component type entity.
   *
   * @return array
   *   The component definition array.
   */
  protected function buildComponentDefinition($component_type) {
    $definition = [
      'name' => $component_type->label(),
      'description' => $component_type->get('description') ?: '',
      'status' => 'stable',
    ];

    // Build props from fields.
    $props = $this->buildPropsFromFields($component_type);
    if (!empty($props)) {
      $definition['props'] = $props;
    }

    // Build slots from slot fields.
    $slots = $this->buildSlotsFromFields($component_type);
    if (!empty($slots)) {
      $definition['slots'] = $slots;
    }

    // Add rendering configuration.
    $rendering_config = $component_type->getRenderingConfiguration();
    if ($rendering_config) {
      $definition['rendering'] = [
        'twig' => $rendering_config['twig_enabled'] ?? TRUE,
        'react' => $rendering_config['react_enabled'] ?? FALSE,
        'default' => $rendering_config['default_method'] ?? 'twig',
      ];

      if (!empty($rendering_config['react_library'])) {
        $definition['rendering']['react_library'] = $rendering_config['react_library'];
      }
    }

    // Add metadata.
    $definition['metadata'] = [
      'categories' => $this->getComponentCategories($component_type),
      'entity_bundle' => $component_type->id(),
      'auto_generated' => TRUE,
      'generator_version' => '1.0',
    ];

    // Add schema version.
    $definition['$schema'] = 'https://drupal.org/schema/sdc/1.0';

    return $definition;
  }

  /**
   * Builds props definition from entity fields.
   *
   * @param \Drupal\component_entity\Entity\ComponentTypeInterface $component_type
   *   The component type entity.
   *
   * @return array
   *   The props definition.
   */
  protected function buildPropsFromFields($component_type) {
    $props = [];
    $fields = $this->entityFieldManager->getFieldDefinitions('component', $component_type->id());

    foreach ($fields as $field_definition) {
      // Skip base fields and slot fields.
      if ($this->shouldSkipField($field_definition)) {
        continue;
      }

      $prop_name = $this->getPropName($field_definition);
      $props[$prop_name] = $this->buildPropSchema($field_definition);
    }

    return $props;
  }

  /**
   * Builds a single prop schema from a field definition.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   *
   * @return array
   *   The prop schema.
   */
  protected function buildPropSchema(FieldDefinitionInterface $field_definition) {
    $schema = [
      'title' => $field_definition->getLabel(),
      'description' => $field_definition->getDescription() ?: '',
    ];

    // Get type from third-party settings or field type.
    $sdc_type = $field_definition->getThirdPartySetting('component_entity', 'sdc_prop_type');
    if (!$sdc_type) {
      $sdc_type = $this->mapFieldTypeToSdcType($field_definition->getType());
    }
    $schema['type'] = $sdc_type;

    // Add required flag.
    $is_required = $field_definition->getThirdPartySetting('component_entity', 'sdc_required')
      ?? $field_definition->isRequired();
    if ($is_required) {
      $schema['required'] = TRUE;
    }

    // Add default value.
    $default_value = $field_definition->getThirdPartySetting('component_entity', 'sdc_default');
    if (!$default_value) {
      $default_value = $field_definition->getDefaultValueLiteral();
    }
    if ($default_value) {
      $schema['default'] = $this->formatDefaultValue($default_value, $sdc_type);
    }

    // Add constraints based on field settings.
    $this->addFieldConstraints($schema, $field_definition);

    return $schema;
  }

  /**
   * Maps Drupal field types to SDC property types.
   *
   * @param string $field_type
   *   The Drupal field type.
   *
   * @return string
   *   The SDC property type.
   */
  protected function mapFieldTypeToSdcType($field_type) {
    $mapping = [
      'string' => 'string',
      'string_long' => 'string',
      'text' => 'string',
      'text_long' => 'string',
      'text_with_summary' => 'string',
      'integer' => 'number',
      'decimal' => 'number',
      'float' => 'number',
      'boolean' => 'boolean',
      'email' => 'string',
      'telephone' => 'string',
      'uri' => 'string',
      'link' => 'object',
      'datetime' => 'string',
      'timestamp' => 'number',
      'entity_reference' => 'object',
      'entity_reference_revisions' => 'object',
      'image' => 'object',
      'file' => 'object',
      'list_string' => 'string',
      'list_integer' => 'number',
      'list_float' => 'number',
      'map' => 'object',
      'json' => 'object',
    ];

    return $mapping[$field_type] ?? 'string';
  }

  /**
   * Builds slots definition from entity fields.
   *
   * @param \Drupal\component_entity\Entity\ComponentTypeInterface $component_type
   *   The component type entity.
   *
   * @return array
   *   The slots definition.
   */
  protected function buildSlotsFromFields($component_type) {
    $slots = [];
    $fields = $this->entityFieldManager->getFieldDefinitions('component', $component_type->id());

    foreach ($fields as $field_name => $field_definition) {
      // Check if this is a slot field.
      if (strpos($field_name, '_slot') !== FALSE ||
          $field_definition->getThirdPartySetting('component_entity', 'is_slot', FALSE)) {

        $slot_name = str_replace(['field_', '_slot'], '', $field_name);
        $slots[$slot_name] = [
          'title' => $field_definition->getLabel(),
          'description' => $field_definition->getDescription() ?: '',
          'required' => $field_definition->isRequired(),
        ];
      }
    }

    return $slots;
  }

  /**
   * Gets the component directory path.
   *
   * @param \Drupal\component_entity\Entity\ComponentTypeInterface $component_type
   *   The component type entity.
   * @param array $options
   *   Generation options.
   *
   * @return string
   *   The component directory path.
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
   * Determines if a field should be skipped for prop generation.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   *
   * @return bool
   *   TRUE if the field should be skipped.
   */
  protected function shouldSkipField(FieldDefinitionInterface $field_definition) {
    // Skip base fields.
    $base_fields = [
      'id', 'uuid', 'vid', 'type', 'langcode', 'status',
      'created', 'changed', 'uid', 'title', 'revision_timestamp',
      'revision_uid', 'revision_log', 'render_method', 'react_config',
    ];

    if (in_array($field_definition->getName(), $base_fields)) {
      return TRUE;
    }

    // Skip computed fields.
    if ($field_definition->isComputed()) {
      return TRUE;
    }

    // Skip slot fields (handled separately).
    if (strpos($field_definition->getName(), '_slot') !== FALSE) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Gets the prop name for a field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   *
   * @return string
   *   The prop name.
   */
  protected function getPropName(FieldDefinitionInterface $field_definition) {
    // Check for custom React prop name.
    $custom_name = $field_definition->getThirdPartySetting('component_entity', 'react_prop_name');
    if ($custom_name) {
      return $custom_name;
    }

    // Remove 'field_' prefix.
    $field_name = $field_definition->getName();
    if (strpos($field_name, 'field_') === 0) {
      return substr($field_name, 6);
    }

    return $field_name;
  }

  /**
   * Formats a default value for SDC.
   *
   * @param mixed $value
   *   The default value.
   * @param string $type
   *   The SDC type.
   *
   * @return mixed
   *   The formatted value.
   */
  protected function formatDefaultValue($value, $type) {
    if (is_array($value) && isset($value[0]['value'])) {
      $value = $value[0]['value'];
    }

    switch ($type) {
      case 'number':
        return is_numeric($value) ? (float) $value : 0;

      case 'boolean':
        return (bool) $value;

      case 'object':
      case 'array':
        return is_string($value) ? json_decode($value, TRUE) : $value;

      default:
        return (string) $value;
    }
  }

  /**
   * Adds field constraints to the prop schema.
   *
   * @param array &$schema
   *   The prop schema array.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   */
  protected function addFieldConstraints(&$schema, FieldDefinitionInterface $field_definition) {
    $field_settings = $field_definition->getSettings();

    // Add enum for list fields.
    if (in_array($field_definition->getType(), ['list_string', 'list_integer', 'list_float'])) {
      if (!empty($field_settings['allowed_values'])) {
        $schema['enum'] = array_keys($field_settings['allowed_values']);
      }
    }

    // Add max length for string fields.
    if (isset($field_settings['max_length'])) {
      $schema['maxLength'] = $field_settings['max_length'];
    }

    // Add min/max for numeric fields.
    if (isset($field_settings['min'])) {
      $schema['minimum'] = $field_settings['min'];
    }
    if (isset($field_settings['max'])) {
      $schema['maximum'] = $field_settings['max'];
    }

    // Add format for specific field types.
    switch ($field_definition->getType()) {
      case 'email':
        $schema['format'] = 'email';
        break;

      case 'uri':
      case 'link':
        $schema['format'] = 'uri';
        break;

      case 'datetime':
        $schema['format'] = 'date-time';
        break;
    }
  }

  /**
   * Gets component categories.
   *
   * @param \Drupal\component_entity\Entity\ComponentTypeInterface $component_type
   *   The component type entity.
   *
   * @return array
   *   Array of category names.
   */
  protected function getComponentCategories($component_type) {
    // This could be extended to read from a taxonomy or config.
    $categories = ['Components'];

    // Add category based on component type.
    if (strpos($component_type->id(), 'hero') !== FALSE) {
      $categories[] = 'Heroes';
    }
    elseif (strpos($component_type->id(), 'card') !== FALSE) {
      $categories[] = 'Cards';
    }
    elseif (strpos($component_type->id(), 'cta') !== FALSE) {
      $categories[] = 'Call to Action';
    }

    return $categories;
  }

}
