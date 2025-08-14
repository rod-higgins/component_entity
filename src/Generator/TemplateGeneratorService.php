<?php

namespace Drupal\component_entity\Generator;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\File\FileSystemInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for generating Twig templates from component definitions.
 */
class TemplateGeneratorService {

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

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
    FileSystemInterface $file_system,
    LoggerInterface $logger,
  ) {
    $this->entityFieldManager = $entity_field_manager;
    $this->fileSystem = $file_system;
    $this->logger = $logger;
  }

  /**
   * Generates a Twig template for a component type.
   *
   * @param \Drupal\component_entity\Entity\ComponentTypeInterface $component_type
   *   The component type entity.
   * @param string $component_path
   *   The component directory path.
   * @param array $options
   *   Options for generation:
   *   - overwrite: Whether to overwrite existing files (default: FALSE)
   *   - style: Template style ('minimal', 'bootstrap', 'bem') (default: 'bem')
   *   - include_debug: Include debug comments (default: TRUE)
   *
   * @return array
   *   Result array with 'success' boolean and 'message' string.
   */
  public function generateTwigTemplate($component_type, $component_path, array $options = []) {
    $options += [
      'overwrite' => FALSE,
      'style' => 'bem',
      'include_debug' => TRUE,
    ];

    try {
      $template_file = $component_path . '/' . $component_type->id() . '.html.twig';

      // Check if file exists and overwrite is false.
      if (file_exists($template_file) && !$options['overwrite']) {
        return [
          'success' => FALSE,
          'message' => 'Template already exists. Use overwrite option to replace.',
        ];
      }

      // Generate template content.
      $content = $this->generateTemplateContent($component_type, $options);

      // Ensure directory exists.
      $this->fileSystem->prepareDirectory($component_path, FileSystemInterface::CREATE_DIRECTORY);

      // Write the file.
      $result = file_put_contents($template_file, $content);

      if ($result !== FALSE) {
        $this->logger->info('Generated Twig template for @type at @path', [
          '@type' => $component_type->id(),
          '@path' => $template_file,
        ]);

        return [
          'success' => TRUE,
          'message' => 'Successfully generated Twig template',
          'path' => $template_file,
        ];
      }

      return [
        'success' => FALSE,
        'message' => 'Failed to write template file',
      ];

    }
    catch (\Exception $e) {
      $this->logger->error('Error generating Twig template: @error', [
        '@error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'message' => 'Error: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Generates the template content.
   *
   * @param \Drupal\component_entity\Entity\ComponentTypeInterface $component_type
   *   The component type entity.
   * @param array $options
   *   Generation options.
   *
   * @return string
   *   The template content.
   */
  protected function generateTemplateContent($component_type, array $options) {
    $bundle = $component_type->id();
    // Removed unused $label variable.
    $fields = $this->entityFieldManager->getFieldDefinitions('component', $bundle);

    // Start building the template.
    $content = $this->generateTemplateHeader($component_type, $options);

    // Generate CSS class based on style.
    $base_class = $this->getBaseClass($bundle, $options['style']);

    // Build the main container.
    $content .= $this->generateContainerOpen($base_class, $options);

    // Generate field sections.
    $content .= $this->generateFieldSections($fields, $base_class, $options);

    // Generate slot sections.
    $content .= $this->generateSlotSections($fields, $base_class, $options);

    // Close the main container.
    $content .= $this->generateContainerClose($options);

    // Add footer comments if debug is enabled.
    if ($options['include_debug']) {
      $content .= $this->generateTemplateFooter($component_type);
    }

    return $content;
  }

  /**
   * Generates the template header.
   */
  protected function generateTemplateHeader($component_type, array $options) {
    $header = "{#\n";
    $header .= " * @file\n";
    $header .= " * Template for " . $component_type->label() . " component.\n";
    $header .= " *\n";
    $header .= " * Available variables:\n";

    // List available variables.
    $fields = $this->entityFieldManager->getFieldDefinitions('component', $component_type->id());
    foreach ($fields as $field_definition) {
      if ($this->isTemplateField($field_definition)) {
        $prop_name = $this->getPropVariableName($field_definition);
        $header .= " * - " . $prop_name . ": " . $field_definition->getLabel() . "\n";
      }
    }

    $header .= " * - attributes: HTML attributes for the container element.\n";
    $header .= " * - title_attributes: HTML attributes for the title.\n";
    $header .= " * - content_attributes: HTML attributes for the content.\n";
    $header .= " *\n";
    $header .= " * @see template_preprocess_component_entity()\n";
    $header .= " *\n";
    $header .= " * @ingroup themeable\n";
    $header .= " #}\n\n";

    // Add namespace if using BEM style.
    if ($options['style'] === 'bem') {
      $header .= "{% set classes = [\n";
      $header .= "  '" . $this->getBaseClass($component_type->id(), 'bem') . "',\n";
      $header .= "  view_mode ? '" . $this->getBaseClass($component_type->id(), 'bem') . "--' ~ view_mode|clean_class,\n";
      $header .= "] %}\n\n";
    }

    return $header;
  }

  /**
   * Generates the container opening tag.
   */
  protected function generateContainerOpen($base_class, array $options) {
    $content = "";

    switch ($options['style']) {
      case 'bem':
        $content .= "<div{{ attributes.addClass(classes) }}>\n";
        break;

      case 'bootstrap':
        $content .= "<div{{ attributes.addClass('component', '" . $base_class . "', 'container') }}>\n";
        break;

      default:
        $content .= "<div{{ attributes.addClass('" . $base_class . "') }}>\n";
    }

    return $content;
  }

  /**
   * Generates field sections in the template.
   */
  protected function generateFieldSections($fields, $base_class, array $options) {
    $content = "";
    $indent = "  ";

    foreach ($fields as $current_field_name => $field_definition) {
      if (!$this->isTemplateField($field_definition)) {
        continue;
      }

      $prop_name = $this->getPropVariableName($field_definition);
      $element_class = $this->getElementClass($base_class, $current_field_name, $options['style']);

      // Generate field output based on field type.
      $field_type = $field_definition->getType();

      switch ($field_type) {
        case 'string':
        case 'string_long':
          if ($current_field_name === 'field_title' || $current_field_name === 'title') {
            $content .= $indent . "{% if " . $prop_name . " %}\n";
            $content .= $indent . "  <h2{{ title_attributes.addClass('" . $element_class . "') }}>{{ " . $prop_name . " }}</h2>\n";
            $content .= $indent . "{% endif %}\n\n";
          }
          else {
            $content .= $indent . "{% if " . $prop_name . " %}\n";
            $content .= $indent . "  <div class=\"" . $element_class . "\">{{ " . $prop_name . " }}</div>\n";
            $content .= $indent . "{% endif %}\n\n";
          }
          break;

        case 'text':
        case 'text_long':
        case 'text_with_summary':
          $content .= $indent . "{% if " . $prop_name . " %}\n";
          $content .= $indent . "  <div class=\"" . $element_class . "\">\n";
          $content .= $indent . "    {{ " . $prop_name . "|raw }}\n";
          $content .= $indent . "  </div>\n";
          $content .= $indent . "{% endif %}\n\n";
          break;

        case 'image':
          $content .= $indent . "{% if " . $prop_name . " %}\n";
          $content .= $indent . "  <div class=\"" . $element_class . "\">\n";
          $content .= $indent . "    {{ " . $prop_name . " }}\n";
          $content .= $indent . "  </div>\n";
          $content .= $indent . "{% endif %}\n\n";
          break;

        case 'link':
          $content .= $indent . "{% if " . $prop_name . " %}\n";
          $content .= $indent . "  <div class=\"" . $element_class . "\">\n";
          $content .= $indent . "    <a href=\"{{ " . $prop_name . ".uri }}\"";
          if ($options['style'] === 'bem') {
            $content .= " class=\"" . $base_class . "__link\"";
          }
          $content .= ">{{ " . $prop_name . ".title }}</a>\n";
          $content .= $indent . "  </div>\n";
          $content .= $indent . "{% endif %}\n\n";
          break;

        case 'entity_reference':
          $content .= $indent . "{% if " . $prop_name . " %}\n";
          $content .= $indent . "  <div class=\"" . $element_class . "\">\n";
          $content .= $indent . "    {{ " . $prop_name . " }}\n";
          $content .= $indent . "  </div>\n";
          $content .= $indent . "{% endif %}\n\n";
          break;

        case 'boolean':
          $content .= $indent . "{% if " . $prop_name . " %}\n";
          $content .= $indent . "  <div class=\"" . $element_class . " " . $element_class . "--active\"></div>\n";
          $content .= $indent . "{% endif %}\n\n";
          break;

        case 'list_string':
        case 'list_integer':
          $content .= $indent . "{% if " . $prop_name . " %}\n";
          $content .= $indent . "  <div class=\"" . $element_class . " " . $element_class . "--{{ " . $prop_name . "|clean_class }}\">{{ " . $prop_name . " }}</div>\n";
          $content .= $indent . "{% endif %}\n\n";
          break;

        default:
          $content .= $indent . "{% if " . $prop_name . " %}\n";
          $content .= $indent . "  <div class=\"" . $element_class . "\">{{ " . $prop_name . " }}</div>\n";
          $content .= $indent . "{% endif %}\n\n";
      }
    }

    return $content;
  }

  /**
   * Generates slot sections in the template.
   */
  protected function generateSlotSections($fields, $base_class, array $options) {
    $content = "";
    $indent = "  ";

    foreach ($fields as $field_definition) {
      if (!$this->isSlotField($field_definition)) {
        continue;
      }

      $slot_name = $this->getSlotName($field_definition);
      $element_class = $this->getElementClass($base_class, 'slot-' . $slot_name, $options['style']);

      $content .= $indent . "{# Slot: " . $field_definition->getLabel() . " #}\n";
      $content .= $indent . "{% block " . $slot_name . " %}\n";
      $content .= $indent . "  {% if slots." . $slot_name . " %}\n";
      $content .= $indent . "    <div class=\"" . $element_class . "\">\n";
      $content .= $indent . "      {{ slots." . $slot_name . " }}\n";
      $content .= $indent . "    </div>\n";
      $content .= $indent . "  {% endif %}\n";
      $content .= $indent . "{% endblock %}\n\n";
    }

    return $content;
  }

  /**
   * Generates the container closing tag.
   */
  protected function generateContainerClose(array $options) {
    return "</div>\n";
  }

  /**
   * Generates the template footer.
   */
  protected function generateTemplateFooter($component_type) {
    $footer = "\n{# End of " . $component_type->label() . " component #}\n";
    return $footer;
  }

  /**
   * Gets the base CSS class for the component.
   */
  protected function getBaseClass($bundle, $style) {
    $class = str_replace('_', '-', $bundle);

    switch ($style) {
      case 'bem':
        return 'c-' . $class;

      case 'bootstrap':
        return 'component-' . $class;

      default:
        return $class;
    }
  }

  /**
   * Gets the CSS class for an element.
   */
  protected function getElementClass($base_class, $element, $style) {
    $element = str_replace(['field_', '_'], ['', '-'], $element);

    switch ($style) {
      case 'bem':
        return $base_class . '__' . $element;

      case 'bootstrap':
        return $base_class . '-' . $element;

      default:
        return $element;
    }
  }

  /**
   * Determines if a field should be included in the template.
   */
  protected function isTemplateField(FieldDefinitionInterface $field_definition) {
    // Skip base fields.
    $skip_fields = [
      'id', 'uuid', 'vid', 'type', 'langcode', 'status',
      'created', 'changed', 'uid', 'revision_timestamp',
      'revision_uid', 'revision_log', 'render_method', 'react_config',
    ];

    if (in_array($field_definition->getName(), $skip_fields)) {
      return FALSE;
    }

    // Skip computed fields.
    if ($field_definition->isComputed()) {
      return FALSE;
    }

    // Skip slot fields (handled separately).
    if ($this->isSlotField($field_definition)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Determines if a field is a slot field.
   */
  protected function isSlotField(FieldDefinitionInterface $field_definition) {
    return strpos($field_definition->getName(), '_slot') !== FALSE ||
           $field_definition->getThirdPartySetting('component_entity', 'is_slot', FALSE);
  }

  /**
   * Gets the prop variable name for a field.
   */
  protected function getPropVariableName(FieldDefinitionInterface $field_definition) {
    $field_name = $field_definition->getName();

    // Remove 'field_' prefix.
    if (strpos($field_name, 'field_') === 0) {
      return substr($field_name, 6);
    }

    return $field_name;
  }

  /**
   * Gets the slot name for a field.
   */
  protected function getSlotName(FieldDefinitionInterface $field_definition) {
    $field_name = $field_definition->getName();
    return str_replace(['field_', '_slot'], '', $field_name);
  }

}
