<?php

namespace Drupal\component_entity\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'component_reference' field type.
 *
 * @FieldType(
 *   id = "component_reference",
 *   label = @Translation("Component reference"),
 *   description = @Translation("An entity reference field for component entities with enhanced features"),
 *   default_widget = "component_reference_autocomplete",
 *   default_formatter = "component_reference_rendered",
 *   list_class = "\Drupal\Core\Field\EntityReferenceFieldItemList",
 *   category = @Translation("Component Entity")
 * )
 */
class ComponentReferenceItem extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'target_type' => 'component',
      'allow_inline_editing' => FALSE,
      'render_method_override' => '',
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'handler' => 'default:component',
      'handler_settings' => [
        'target_bundles' => NULL,
        'sort' => [
          'field' => 'name',
          'direction' => 'ASC',
        ],
        'auto_create' => FALSE,
        'auto_create_bundle' => NULL,
      ],
      'allow_inline_editing' => FALSE,
      'render_method_override' => '',
      'display_mode' => 'default',
      'allow_duplication' => FALSE,
      'show_preview' => TRUE,
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    // Add custom properties for component-specific data.
    $properties['display_settings'] = DataDefinition::create('map')
      ->setLabel(new TranslatableMarkup('Display settings'))
      ->setDescription(new TranslatableMarkup('Display settings for this component reference'));

    $properties['override_props'] = DataDefinition::create('map')
      ->setLabel(new TranslatableMarkup('Override props'))
      ->setDescription(new TranslatableMarkup('Props to override for this specific reference'));

    $properties['wrapper_attributes'] = DataDefinition::create('map')
      ->setLabel(new TranslatableMarkup('Wrapper attributes'))
      ->setDescription(new TranslatableMarkup('HTML attributes for the component wrapper'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);

    // Add columns for component-specific data.
    $schema['columns']['display_settings'] = [
      'type' => 'blob',
      'size' => 'normal',
      'serialize' => TRUE,
      'description' => 'Serialized display settings for the component reference.',
    ];

    $schema['columns']['override_props'] = [
      'type' => 'blob',
      'size' => 'normal',
      'serialize' => TRUE,
      'description' => 'Serialized prop overrides for the component reference.',
    ];

    $schema['columns']['wrapper_attributes'] = [
      'type' => 'blob',
      'size' => 'normal',
      'serialize' => TRUE,
      'description' => 'Serialized wrapper attributes for the component reference.',
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = parent::storageSettingsForm($form, $form_state, $has_data);

    // Lock target type to component entities.
    $element['target_type']['#default_value'] = 'component';
    $element['target_type']['#disabled'] = TRUE;
    $element['target_type']['#description'] = $this->t('Component references can only target component entities.');

    $element['allow_inline_editing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow inline editing'),
      '#description' => $this->t('Allow editing of component content inline within the referencing entity form.'),
      '#default_value' => $this->getSetting('allow_inline_editing'),
      '#disabled' => $has_data,
    ];

    $element['render_method_override'] = [
      '#type' => 'select',
      '#title' => $this->t('Render method override'),
      '#description' => $this->t('Override the render method for all referenced components.'),
      '#options' => [
        '' => $this->t('- Use component default -'),
        'twig' => $this->t('Twig (Server-side)'),
        'react' => $this->t('React (Client-side)'),
      ],
      '#default_value' => $this->getSetting('render_method_override'),
      '#disabled' => $has_data,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::fieldSettingsForm($form, $form_state);

    // Component-specific settings.
    $form['allow_inline_editing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow inline editing'),
      '#description' => $this->t('Allow editing of component content inline within the referencing entity form.'),
      '#default_value' => $this->getSetting('allow_inline_editing'),
      '#weight' => 10,
    ];

    $form['render_method_override'] = [
      '#type' => 'select',
      '#title' => $this->t('Render method override'),
      '#description' => $this->t('Override the render method for referenced components.'),
      '#options' => [
        '' => $this->t('- Use component default -'),
        'twig' => $this->t('Twig (Server-side)'),
        'react' => $this->t('React (Client-side)'),
      ],
      '#default_value' => $this->getSetting('render_method_override'),
      '#weight' => 11,
    ];

    $form['display_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Display mode'),
      '#description' => $this->t('The display mode to use when rendering referenced components.'),
      '#options' => $this->getDisplayModeOptions(),
      '#default_value' => $this->getSetting('display_mode'),
      '#weight' => 12,
    ];

    $form['allow_duplication'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow duplication'),
      '#description' => $this->t('Allow users to duplicate referenced components.'),
      '#default_value' => $this->getSetting('allow_duplication'),
      '#weight' => 13,
    ];

    $form['show_preview'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show preview'),
      '#description' => $this->t('Show a preview of the component in the widget.'),
      '#default_value' => $this->getSetting('show_preview'),
      '#weight' => 14,
    ];

    // Modify handler settings for component entities.
    if (isset($form['handler']['handler_settings']['target_bundles'])) {
      $form['handler']['handler_settings']['target_bundles']['#title'] = $this->t('Component types');
      $form['handler']['handler_settings']['target_bundles']['#description'] = $this->t('Select which component types can be referenced. Leave empty to allow all types.');
    }

    return $form;
  }

  /**
   * Gets available display mode options.
   *
   * @return array
   *   Array of display mode options.
   */
  protected function getDisplayModeOptions() {
    $options = ['default' => $this->t('Default')];
    
    $display_modes = \Drupal::service('entity_display.repository')
      ->getViewModeOptionsByBundle('component', NULL);
    
    foreach ($display_modes as $mode => $label) {
      if ($mode !== 'default') {
        $options[$mode] = $label;
      }
    }
    
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $values = parent::getValue();
    
    // Ensure custom properties are properly initialized.
    if (!isset($values['display_settings'])) {
      $values['display_settings'] = [];
    }
    if (!isset($values['override_props'])) {
      $values['override_props'] = [];
    }
    if (!isset($values['wrapper_attributes'])) {
      $values['wrapper_attributes'] = [];
    }
    
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();
    
    // Validate that the referenced entity is a component.
    if ($this->entity && $this->entity->getEntityTypeId() !== 'component') {
      throw new \InvalidArgumentException('Component reference fields can only reference component entities.');
    }
    
    // Apply any render method override.
    if ($this->entity && $render_method = $this->getSetting('render_method_override')) {
      $this->entity->set('render_method', $render_method);
    }
  }

  /**
   * Gets the display settings for this reference.
   *
   * @return array
   *   The display settings.
   */
  public function getDisplaySettings() {
    return $this->get('display_settings')->getValue() ?: [];
  }

  /**
   * Sets the display settings for this reference.
   *
   * @param array $settings
   *   The display settings.
   *
   * @return $this
   */
  public function setDisplaySettings(array $settings) {
    $this->set('display_settings', $settings);
    return $this;
  }

  /**
   * Gets the prop overrides for this reference.
   *
   * @return array
   *   The prop overrides.
   */
  public function getOverrideProps() {
    return $this->get('override_props')->getValue() ?: [];
  }

  /**
   * Sets the prop overrides for this reference.
   *
   * @param array $props
   *   The prop overrides.
   *
   * @return $this
   */
  public function setOverrideProps(array $props) {
    $this->set('override_props', $props);
    return $this;
  }

  /**
   * Gets the wrapper attributes for this reference.
   *
   * @return array
   *   The wrapper attributes.
   */
  public function getWrapperAttributes() {
    return $this->get('wrapper_attributes')->getValue() ?: [];
  }

  /**
   * Sets the wrapper attributes for this reference.
   *
   * @param array $attributes
   *   The wrapper attributes.
   *
   * @return $this
   */
  public function setWrapperAttributes(array $attributes) {
    $this->set('wrapper_attributes', $attributes);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldStorageDefinitionInterface $field_definition) {
    $values = parent::generateSampleValue($field_definition);
    
    // Add sample component-specific data.
    $values['display_settings'] = [
      'theme' => 'default',
      'variant' => 'primary',
    ];
    
    $values['override_props'] = [
      'title' => 'Sample Component Title',
      'description' => 'This is a sample component description.',
    ];
    
    $values['wrapper_attributes'] = [
      'class' => ['component-wrapper', 'sample-component'],
      'data-component' => 'sample',
    ];
    
    return $values;
  }

}