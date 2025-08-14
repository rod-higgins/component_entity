<?php

namespace Drupal\component_entity\Generator;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for generating React component scaffolds.
 */
class ReactGeneratorService {

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
   * Generates a React component scaffold.
   *
   * @param \Drupal\component_entity\Entity\ComponentTypeInterface $component_type
   *   The component type entity.
   * @param string $component_path
   *   The component directory path.
   * @param array $options
   *   Options for generation:
   *   - overwrite: Whether to overwrite existing files (default: FALSE)
   *   - typescript: Generate TypeScript component (default: FALSE)
   *   - style: Component style ('functional', 'class') (default: 'functional')
   *   - hooks: Include common hooks (default: TRUE)
   *   - css_modules: Use CSS modules (default: FALSE)
   *   - test_file: Generate test file (default: TRUE)
   *
   * @return array
   *   Result array with 'success' boolean and 'message' string.
   */
  public function generateReactComponent($component_type, $component_path, array $options = []) {
    $options += [
      'overwrite' => FALSE,
      'typescript' => FALSE,
      'style' => 'functional',
      'hooks' => TRUE,
      'css_modules' => FALSE,
      'test_file' => TRUE,
    ];

    $results = [];

    // Generate main component file.
    $component_result = $this->generateComponentFile($component_type, $component_path, $options);
    $results['component'] = $component_result;

    if (!$component_result['success']) {
      return $component_result;
    }

    // Generate CSS/SCSS file.
    $styles_result = $this->generateStylesFile($component_type, $component_path, $options);
    $results['styles'] = $styles_result;

    // Generate test file if requested.
    if ($options['test_file']) {
      $test_result = $this->generateTestFile($component_type, $component_path, $options);
      $results['test'] = $test_result;
    }

    // Generate index file for easier imports.
    $index_result = $this->generateIndexFile($component_type, $component_path, $options);
    $results['index'] = $index_result;

    // Generate Storybook story file.
    $story_result = $this->generateStoryFile($component_type, $component_path, $options);
    $results['story'] = $story_result;

    return [
      'success' => TRUE,
      'message' => 'Successfully generated React component scaffold',
      'files' => $results,
    ];
  }

  /**
   * Generates the main React component file.
   */
  protected function generateComponentFile($component_type, $component_path, array $options) {
    $component_name = $this->getComponentName($component_type->id());
    $extension = $options['typescript'] ? '.tsx' : '.jsx';
    $file_path = $component_path . '/' . $component_name . $extension;

    // Check if file exists.
    if (file_exists($file_path) && !$options['overwrite']) {
      return [
        'success' => FALSE,
        'message' => 'Component file already exists',
      ];
    }

    // Generate component content.
    $content = $options['typescript']
      ? $this->generateTypeScriptComponent($component_type, $component_name, $options)
      : $this->generateJavaScriptComponent($component_type, $component_name, $options);

    // Ensure directory exists.
    $this->fileSystem->prepareDirectory($component_path, FileSystemInterface::CREATE_DIRECTORY);

    // Write file.
    $result = file_put_contents($file_path, $content);

    if ($result !== FALSE) {
      $this->logger->info('Generated React component for @type at @path', [
        '@type' => $component_type->id(),
        '@path' => $file_path,
      ]);

      return [
        'success' => TRUE,
        'message' => 'Component file created',
        'path' => $file_path,
      ];
    }

    return [
      'success' => FALSE,
      'message' => 'Failed to write component file',
    ];
  }

  /**
   * Generates JavaScript React component content.
   */
  protected function generateJavaScriptComponent($component_type, $component_name, array $options) {
    $fields = $this->entityFieldManager->getFieldDefinitions('component', $component_type->id());
    $props = $this->buildPropsDefinition($fields);
    $slots = $this->buildSlotsDefinition($fields);

    $content = "/**\n";
    $content .= " * " . $component_type->label() . " Component\n";
    $content .= " * \n";
    $content .= " * Auto-generated React component scaffold\n";
    $content .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
    $content .= " */\n\n";

    // Imports.
    $content .= "import React";
    if ($options['hooks']) {
      $content .= ", { useState, useEffect, useCallback, useMemo }";
    }
    $content .= " from 'react';\n";
    $content .= "import PropTypes from 'prop-types';\n";

    // CSS import.
    if ($options['css_modules']) {
      $content .= "import styles from './" . $component_name . ".module.css';\n";
    }
    else {
      $content .= "import './" . $component_name . ".css';\n";
    }

    $content .= "\n";

    // Component.
    if ($options['style'] === 'functional') {
      $content .= $this->generateFunctionalComponent($component_name, $props, $slots, $options);
    }
    else {
      $content .= $this->generateClassComponent($component_name, $props, $slots, $options);
    }

    // PropTypes.
    $content .= "\n" . $component_name . ".propTypes = {\n";
    foreach ($props as $prop_name => $prop_info) {
      $content .= "  " . $prop_name . ": PropTypes." . $this->getPropType($prop_info['type']);
      if ($prop_info['required']) {
        $content .= ".isRequired";
      }
      $content .= ",\n";
    }
    if (!empty($slots)) {
      $content .= "  slots: PropTypes.shape({\n";
      foreach ($slots as $slot_name => $slot_info) {
        $content .= "    " . $slot_name . ": PropTypes.node";
        if ($slot_info['required']) {
          $content .= ".isRequired";
        }
        $content .= ",\n";
      }
      $content .= "  }),\n";
    }
    $content .= "  className: PropTypes.string,\n";
    $content .= "  children: PropTypes.node,\n";
    $content .= "};\n\n";

    // Default props.
    $content .= $component_name . ".defaultProps = {\n";
    foreach ($props as $prop_name => $prop_info) {
      if (isset($prop_info['default'])) {
        $content .= "  " . $prop_name . ": " . $this->formatDefaultValue($prop_info['default'], $prop_info['type']) . ",\n";
      }
    }
    $content .= "  slots: {},\n";
    $content .= "  className: '',\n";
    $content .= "};\n\n";

    $content .= "export default " . $component_name . ";\n";

    return $content;
  }

  /**
   * Generates TypeScript React component content.
   */
  protected function generateTypeScriptComponent($component_type, $component_name, array $options) {
    $fields = $this->entityFieldManager->getFieldDefinitions('component', $component_type->id());
    $props = $this->buildPropsDefinition($fields);
    $slots = $this->buildSlotsDefinition($fields);

    $content = "/**\n";
    $content .= " * " . $component_type->label() . " Component\n";
    $content .= " * \n";
    $content .= " * Auto-generated TypeScript React component scaffold\n";
    $content .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
    $content .= " */\n\n";

    // Imports.
    $content .= "import React";
    if ($options['hooks']) {
      $content .= ", { useState, useEffect, useCallback, useMemo, FC }";
    }
    else {
      $content .= ", { FC }";
    }
    $content .= " from 'react';\n";

    // CSS import.
    if ($options['css_modules']) {
      $content .= "import styles from './" . $component_name . ".module.css';\n";
    }
    else {
      $content .= "import './" . $component_name . ".css';\n";
    }

    $content .= "\n";

    // Interface definition.
    $content .= "interface " . $component_name . "Props {\n";
    foreach ($props as $prop_name => $prop_info) {
      $content .= "  " . $prop_name;
      if (!$prop_info['required']) {
        $content .= "?";
      }
      $content .= ": " . $this->getTypeScriptType($prop_info['type']) . ";\n";
    }
    if (!empty($slots)) {
      $content .= "  slots?: {\n";
      foreach ($slots as $slot_name => $slot_info) {
        $content .= "    " . $slot_name;
        if (!$slot_info['required']) {
          $content .= "?";
        }
        $content .= ": React.ReactNode;\n";
      }
      $content .= "  };\n";
    }
    $content .= "  className?: string;\n";
    $content .= "  children?: React.ReactNode;\n";
    $content .= "}\n\n";

    // Component.
    $content .= "const " . $component_name . ": FC<" . $component_name . "Props> = ({\n";
    // List props with defaults.
    foreach ($props as $prop_name => $prop_info) {
      $content .= "  " . $prop_name;
      if (isset($prop_info['default'])) {
        $content .= " = " . $this->formatDefaultValue($prop_info['default'], $prop_info['type']);
      }
      $content .= ",\n";
    }
    $content .= "  slots = {},\n";
    $content .= "  className = '',\n";
    $content .= "  children,\n";
    $content .= "}) => {\n";

    if ($options['hooks']) {
      $content .= "  // State hooks\n";
      $content .= "  const [isLoading, setIsLoading] = useState(false);\n";
      $content .= "  const [error, setError] = useState<string | null>(null);\n\n";

      $content .= "  // Effect hooks\n";
      $content .= "  useEffect(() => {\n";
      $content .= "    // Component mount/update logic\n";
      $content .= "  }, []);\n\n";
    }

    $content .= "  const baseClass = '" . str_replace('_', '-', $component_type->id()) . "';\n";
    $content .= "  const classes = `${baseClass} ${className}`.trim();\n\n";

    $content .= "  return (\n";
    $content .= "    <div className={classes}>\n";

    // Add basic structure.
    foreach ($props as $prop_name => $prop_info) {
      if ($prop_name === 'title') {
        $content .= "      {" . $prop_name . " && <h2 className={`${baseClass}__title`}>{" . $prop_name . "}</h2>}\n";
      }
      elseif ($prop_info['type'] === 'string') {
        $content .= "      {" . $prop_name . " && <div className={`${baseClass}__" . $prop_name . "`}>{" . $prop_name . "}</div>}\n";
      }
    }

    // Add slots.
    foreach ($slots as $slot_name => $slot_info) {
      $content .= "      {slots." . $slot_name . " && (\n";
      $content .= "        <div className={`${baseClass}__" . $slot_name . "`}>\n";
      $content .= "          {slots." . $slot_name . "}\n";
      $content .= "        </div>\n";
      $content .= "      )}\n";
    }

    $content .= "      {children}\n";
    $content .= "    </div>\n";
    $content .= "  );\n";
    $content .= "};\n\n";

    $content .= "export default " . $component_name . ";\n";

    return $content;
  }

  /**
   * Generates a functional component.
   */
  protected function generateFunctionalComponent($component_name, $props, $slots, $options) {
    $content = "const " . $component_name . " = ({\n";

    // List all props.
    foreach ($props as $prop_name => $prop_info) {
      $content .= "  " . $prop_name . ",\n";
    }
    $content .= "  slots,\n";
    $content .= "  className,\n";
    $content .= "  children,\n";
    $content .= "}) => {\n";

    if ($options['hooks']) {
      $content .= "  // State management\n";
      $content .= "  const [isLoading, setIsLoading] = useState(false);\n";
      $content .= "  const [error, setError] = useState(null);\n\n";

      $content .= "  // Side effects\n";
      $content .= "  useEffect(() => {\n";
      $content .= "    // Component mount/update logic here\n";
      $content .= "  }, []);\n\n";

      $content .= "  // Callbacks\n";
      $content .= "  const handleClick = useCallback(() => {\n";
      $content .= "    // Handle click events\n";
      $content .= "  }, []);\n\n";
    }

    $content .= "  const baseClass = '" . str_replace('_', '-', $component_name) . "';\n";
    if ($options['css_modules']) {
      $content .= "  const rootClass = `${styles.root} ${className || ''}`.trim();\n\n";
    }
    else {
      $content .= "  const rootClass = `${baseClass} ${className || ''}`.trim();\n\n";
    }

    $content .= "  return (\n";
    $content .= "    <div className={rootClass}>\n";

    // Add basic structure for each prop.
    foreach ($props as $prop_name => $prop_info) {
      if ($prop_name === 'title' || $prop_name === 'heading') {
        $content .= "      {" . $prop_name . " && (\n";
        $content .= "        <h2 className={";
        $content .= $options['css_modules'] ? "styles.title" : "`${baseClass}__title`";
        $content .= "}>\n";
        $content .= "          {" . $prop_name . "}\n";
        $content .= "        </h2>\n";
        $content .= "      )}\n";
      }
      elseif ($prop_info['type'] === 'string' || $prop_info['type'] === 'text') {
        $content .= "      {" . $prop_name . " && (\n";
        $content .= "        <div className={";
        $content .= $options['css_modules'] ? "styles." . $prop_name : "`${baseClass}__" . $prop_name . "`";
        $content .= "}>\n";
        $content .= "          {" . $prop_name . "}\n";
        $content .= "        </div>\n";
        $content .= "      )}\n";
      }
    }

    // Add slots.
    if (!empty($slots)) {
      foreach ($slots as $slot_name => $slot_info) {
        $content .= "      {slots && slots." . $slot_name . " && (\n";
        $content .= "        <div className={";
        $content .= $options['css_modules'] ? "styles.slot" . ucfirst($slot_name) : "`${baseClass}__slot-" . $slot_name . "`";
        $content .= "}\n";
        $content .= "          dangerouslySetInnerHTML={{ __html: slots." . $slot_name . " }}\n";
        $content .= "        />\n";
        $content .= "      )}\n";
      }
    }

    $content .= "      {children}\n";
    $content .= "    </div>\n";
    $content .= "  );\n";
    $content .= "};\n";

    return $content;
  }

  /**
   * Generates styles file.
   */
  protected function generateStylesFile($component_type, $component_path, array $options) {
    $component_name = $this->getComponentName($component_type->id());
    $extension = $options['css_modules'] ? '.module.css' : '.css';
    $file_path = $component_path . '/' . $component_name . $extension;

    if (file_exists($file_path) && !$options['overwrite']) {
      return [
        'success' => FALSE,
        'message' => 'Styles file already exists',
      ];
    }

    $base_class = str_replace('_', '-', $component_type->id());
    $fields = $this->entityFieldManager->getFieldDefinitions('component', $component_type->id());

    $content = "/**\n";
    $content .= " * Styles for " . $component_type->label() . " Component\n";
    $content .= " */\n\n";

    if ($options['css_modules']) {
      $content .= ".root {\n";
    }
    else {
      $content .= "." . $base_class . " {\n";
    }
    $content .= "  /* Container styles */\n";
    $content .= "  position: relative;\n";
    $content .= "  padding: 1rem;\n";
    $content .= "}\n\n";

    // Add styles for common elements.
    foreach ($fields as $field_name => $field_definition) {
      if ($this->shouldIncludeInStyles($field_definition)) {
        $element_name = str_replace(['field_', '_'], ['', '-'], $field_name);

        if ($options['css_modules']) {
          $content .= "." . $element_name . " {\n";
        }
        else {
          $content .= "." . $base_class . "__" . $element_name . " {\n";
        }

        if ($field_name === 'field_title' || $field_name === 'title') {
          $content .= "  font-size: 2rem;\n";
          $content .= "  font-weight: bold;\n";
          $content .= "  margin-bottom: 1rem;\n";
        }
        else {
          $content .= "  /* Styles for " . $field_definition->getLabel() . " */\n";
          $content .= "  margin-bottom: 0.5rem;\n";
        }

        $content .= "}\n\n";
      }
    }

    // Write file.
    $result = file_put_contents($file_path, $content);

    return [
      'success' => $result !== FALSE,
      'message' => $result !== FALSE ? 'Styles file created' : 'Failed to create styles file',
      'path' => $file_path,
    ];
  }

  /**
   * Generates test file.
   */
  protected function generateTestFile($component_type, $component_path, array $options) {
    $component_name = $this->getComponentName($component_type->id());
    $file_path = $component_path . '/' . $component_name . '.test.js';

    if (file_exists($file_path) && !$options['overwrite']) {
      return [
        'success' => FALSE,
        'message' => 'Test file already exists',
      ];
    }

    $content = "import React from 'react';\n";
    $content .= "import { render, screen } from '@testing-library/react';\n";
    $content .= "import '@testing-library/jest-dom';\n";
    $content .= "import " . $component_name . " from './" . $component_name . "';\n\n";

    $content .= "describe('" . $component_name . "', () => {\n";
    $content .= "  it('renders without crashing', () => {\n";
    $content .= "    render(<" . $component_name . " />);\n";
    $content .= "  });\n\n";

    $content .= "  it('displays title when provided', () => {\n";
    $content .= "    render(<" . $component_name . " title=\"Test Title\" />);\n";
    $content .= "    expect(screen.getByText('Test Title')).toBeInTheDocument();\n";
    $content .= "  });\n\n";

    $content .= "  it('applies custom className', () => {\n";
    $content .= "    const { container } = render(<" . $component_name . " className=\"custom-class\" />);\n";
    $content .= "    expect(container.firstChild).toHaveClass('custom-class');\n";
    $content .= "  });\n";
    $content .= "});\n";

    $result = file_put_contents($file_path, $content);

    return [
      'success' => $result !== FALSE,
      'message' => $result !== FALSE ? 'Test file created' : 'Failed to create test file',
      'path' => $file_path,
    ];
  }

  /**
   * Generates index file for easier imports.
   */
  protected function generateIndexFile($component_type, $component_path, array $options) {
    $component_name = $this->getComponentName($component_type->id());
    $file_path = $component_path . '/index.js';

    if (file_exists($file_path) && !$options['overwrite']) {
      return [
        'success' => FALSE,
        'message' => 'Index file already exists',
      ];
    }

    $content = "export { default } from './" . $component_name . "';\n";

    $result = file_put_contents($file_path, $content);

    return [
      'success' => $result !== FALSE,
      'message' => $result !== FALSE ? 'Index file created' : 'Failed to create index file',
      'path' => $file_path,
    ];
  }

  /**
   * Generates Storybook story file.
   */
  protected function generateStoryFile($component_type, $component_path, array $options) {
    $component_name = $this->getComponentName($component_type->id());
    $file_path = $component_path . '/' . $component_name . '.stories.js';

    if (file_exists($file_path) && !$options['overwrite']) {
      return [
        'success' => FALSE,
        'message' => 'Story file already exists',
      ];
    }

    $content = "import React from 'react';\n";
    $content .= "import " . $component_name . " from './" . $component_name . "';\n\n";

    $content .= "export default {\n";
    $content .= "  title: 'Components/" . $component_type->label() . "',\n";
    $content .= "  component: " . $component_name . ",\n";
    $content .= "  argTypes: {\n";
    $content .= "    // Define controls for Storybook\n";
    $content .= "  },\n";
    $content .= "};\n\n";

    $content .= "const Template = (args) => <" . $component_name . " {...args} />;\n\n";

    $content .= "export const Default = Template.bind({});\n";
    $content .= "Default.args = {\n";
    $content .= "  // Default props\n";
    $content .= "};\n\n";

    $content .= "export const WithContent = Template.bind({});\n";
    $content .= "WithContent.args = {\n";
    $content .= "  title: 'Example Title',\n";
    $content .= "  // Add more example props\n";
    $content .= "};\n";

    $result = file_put_contents($file_path, $content);

    return [
      'success' => $result !== FALSE,
      'message' => $result !== FALSE ? 'Story file created' : 'Failed to create story file',
      'path' => $file_path,
    ];
  }

  /**
   * Gets the PropTypes type for a given field type.
   *
   * @param string $type
   *   The field type.
   *
   * @return string
   *   The PropTypes type string.
   */
  protected function getPropType($type) {
    switch ($type) {
      case 'string':
      case 'text':
        return 'string';

      case 'number':
        return 'number';

      case 'boolean':
        return 'bool';

      case 'object':
        return 'object';

      case 'array':
        return 'array';

      default:
        return 'any';
    }
  }

  /**
   * Gets the TypeScript type for a given field type.
   *
   * @param string $type
   *   The field type.
   *
   * @return string
   *   The TypeScript type string.
   */
  protected function getTypeScriptType($type) {
    switch ($type) {
      case 'string':
      case 'text':
        return 'string';

      case 'number':
        return 'number';

      case 'boolean':
        return 'boolean';

      case 'object':
        return 'Record<string, any>';

      case 'array':
        return 'any[]';

      default:
        return 'any';
    }
  }

  /**
   * Formats a default value for use in JavaScript/TypeScript.
   *
   * @param mixed $value
   *   The default value.
   * @param string $type
   *   The field type.
   *
   * @return string
   *   The formatted default value.
   */
  protected function formatDefaultValue($value, $type) {
    if ($type === 'string' || $type === 'text') {
      return "'" . addslashes($value) . "'";
    }
    if ($type === 'boolean') {
      return $value ? 'true' : 'false';
    }
    if ($type === 'number') {
      return is_numeric($value) ? $value : 0;
    }
    if ($type === 'object') {
      return '{}';
    }
    if ($type === 'array') {
      return '[]';
    }
    return 'null';
  }

  /**
   * Determines if a field should be included in styles.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   *
   * @return bool
   *   TRUE if the field should be included in styles.
   */
  protected function shouldIncludeInStyles($field_definition) {
    return $this->isComponentProp($field_definition) || $this->isSlotField($field_definition);
  }

  /**
   * Builds props definition from field definitions.
   *
   * @param array $fields
   *   The field definitions.
   *
   * @return array
   *   The props definition array.
   */
  protected function buildPropsDefinition(array $fields) {
    $props = [];

    foreach ($fields as $field_name => $field_definition) {
      if ($this->isComponentProp($field_definition)) {
        $props[$field_name] = [
          'type' => $this->getFieldType($field_definition),
          'required' => $field_definition->isRequired(),
          'default' => $field_definition->getDefaultValue(),
        ];
      }
    }

    return $props;
  }

  /**
   * Builds slots definition from field definitions.
   *
   * @param array $fields
   *   The field definitions.
   *
   * @return array
   *   The slots definition array.
   */
  protected function buildSlotsDefinition(array $fields) {
    $slots = [];

    foreach ($fields as $field_name => $field_definition) {
      if ($this->isSlotField($field_definition)) {
        $slots[$field_name] = [
          'required' => $field_definition->isRequired(),
        ];
      }
    }

    return $slots;
  }

  /**
   * Determines if a field is a component prop.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   *
   * @return bool
   *   TRUE if the field is a component prop.
   */
  protected function isComponentProp($field_definition) {
    // Implementation depends on your field configuration
    // This is a placeholder implementation.
    return !in_array($field_definition->getName(), ['id', 'uuid', 'revision_id', 'langcode']);
  }

  /**
   * Determines if a field is a slot field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   *
   * @return bool
   *   TRUE if the field is a slot field.
   */
  protected function isSlotField($field_definition) {
    // Check if field type is entity reference or similar.
    return $field_definition->getType() === 'entity_reference';
  }

  /**
   * Gets the JavaScript/TypeScript type for a field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   *
   * @return string
   *   The field type string.
   */
  protected function getFieldType($field_definition) {
    $field_type = $field_definition->getType();

    switch ($field_type) {
      case 'string':
      case 'string_long':
      case 'text':
      case 'text_long':
      case 'text_with_summary':
        return 'string';

      case 'integer':
      case 'decimal':
      case 'float':
        return 'number';

      case 'boolean':
        return 'boolean';

      case 'entity_reference':
      case 'entity_reference_revisions':
        return 'object';

      case 'list_string':
      case 'list_integer':
      case 'list_float':
        return 'array';

      default:
        return 'any';
    }
  }

  /**
   * Gets the component name from the component type ID.
   *
   * @param string $component_type_id
   *   The component type ID.
   *
   * @return string
   *   The component name in PascalCase.
   */
  protected function getComponentName($component_type_id) {
    // Convert snake_case to PascalCase.
    $parts = explode('_', $component_type_id);
    $parts = array_map('ucfirst', $parts);
    return implode('', $parts);
  }

  /**
   * Converts a Drupal field name to a React prop name.
   *
   * Removes the 'field_' prefix from field names to create cleaner
   * prop names for React components.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition object.
   *
   * @return string
   *   The cleaned prop name without 'field_' prefix.
   */
  protected function getPropName($field_definition) {
    $field_name = $field_definition->getName();
    if (strpos($field_name, 'field_') === 0) {
      return substr($field_name, 6);
    }
    return $field_name;
  }

  /**
   * Maps Drupal field types to JavaScript/TypeScript types.
   *
   * Converts Drupal field type machine names to their corresponding
   * JavaScript or TypeScript type strings for use in React components.
   *
   * @param string $field_type
   *   The Drupal field type machine name.
   *
   * @return string
   *   The corresponding JavaScript/TypeScript type ('string', 'number',
   *   'boolean', 'object', or 'any' as fallback).
   */
  protected function mapFieldTypeToJsType($field_type) {
    $mapping = [
      'string' => 'string',
      'string_long' => 'string',
      'text' => 'string',
      'text_long' => 'string',
      'integer' => 'number',
      'decimal' => 'number',
      'float' => 'number',
      'boolean' => 'boolean',
      'entity_reference' => 'object',
      'image' => 'object',
      'file' => 'object',
      'link' => 'object',
      'datetime' => 'string',
      'list_string' => 'string',
      'list_integer' => 'number',
    ];

    return $mapping[$field_type] ?? 'any';
  }

}
