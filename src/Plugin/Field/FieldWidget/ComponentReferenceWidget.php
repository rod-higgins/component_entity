<?php

namespace Drupal\component_entity\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\RendererInterface;

/**
 * Plugin implementation of the 'component_reference_autocomplete' widget.
 *
 * @FieldWidget(
 *   id = "component_reference_autocomplete",
 *   label = @Translation("Component reference autocomplete"),
 *   field_types = {
 *     "component_reference",
 *     "entity_reference"
 *   }
 * )
 */
class ComponentReferenceWidget extends EntityReferenceAutocompleteWidget implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a ComponentReferenceWidget.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    EntityTypeManagerInterface $entity_type_manager,
    EntityDisplayRepositoryInterface $entity_display_repository,
    RendererInterface $renderer,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityDisplayRepository = $entity_display_repository;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'show_preview' => TRUE,
      'preview_display_mode' => 'teaser',
      'allow_inline_editing' => FALSE,
      'show_component_type' => TRUE,
      'show_render_method' => FALSE,
      'allow_prop_override' => FALSE,
      'collapsible_preview' => TRUE,
      'show_duplicate_button' => FALSE,
      'show_edit_button' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['show_preview'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show component preview'),
      '#description' => $this->t('Display a preview of the selected component below the autocomplete field.'),
      '#default_value' => $this->getSetting('show_preview'),
    ];

    $elements['preview_display_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Preview display mode'),
      '#options' => $this->getDisplayModeOptions(),
      '#default_value' => $this->getSetting('preview_display_mode'),
      '#states' => [
        'visible' => [
          ':input[name*="show_preview"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $elements['allow_inline_editing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow inline editing'),
      '#description' => $this->t('Allow editing component content directly in this form.'),
      '#default_value' => $this->getSetting('allow_inline_editing'),
    ];

    $elements['show_component_type'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show component type'),
      '#description' => $this->t('Display the component type in the widget.'),
      '#default_value' => $this->getSetting('show_component_type'),
    ];

    $elements['show_render_method'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show render method'),
      '#description' => $this->t('Display the component render method (Twig/React).'),
      '#default_value' => $this->getSetting('show_render_method'),
    ];

    $elements['allow_prop_override'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow prop overrides'),
      '#description' => $this->t('Allow overriding component props for this specific reference.'),
      '#default_value' => $this->getSetting('allow_prop_override'),
    ];

    $elements['collapsible_preview'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Collapsible preview'),
      '#description' => $this->t('Make the component preview collapsible.'),
      '#default_value' => $this->getSetting('collapsible_preview'),
      '#states' => [
        'visible' => [
          ':input[name*="show_preview"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $elements['show_duplicate_button'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show duplicate button'),
      '#description' => $this->t('Show a button to duplicate the referenced component.'),
      '#default_value' => $this->getSetting('show_duplicate_button'),
    ];

    $elements['show_edit_button'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show edit button'),
      '#description' => $this->t('Show a button to edit the referenced component.'),
      '#default_value' => $this->getSetting('show_edit_button'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    if ($this->getSetting('show_preview')) {
      $summary[] = $this->t('Preview: @mode', [
        '@mode' => $this->getSetting('preview_display_mode'),
      ]);
    }

    if ($this->getSetting('allow_inline_editing')) {
      $summary[] = $this->t('Inline editing enabled');
    }

    if ($this->getSetting('allow_prop_override')) {
      $summary[] = $this->t('Prop overrides enabled');
    }

    $buttons = [];
    if ($this->getSetting('show_edit_button')) {
      $buttons[] = $this->t('Edit');
    }
    if ($this->getSetting('show_duplicate_button')) {
      $buttons[] = $this->t('Duplicate');
    }
    if (!empty($buttons)) {
      $summary[] = $this->t('Buttons: @buttons', [
        '@buttons' => implode(', ', $buttons),
      ]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $item = $items[$delta] ?? NULL;
    $referenced_entity = $item ? $item->entity : NULL;

    // Add wrapper for enhanced functionality.
    $wrapper_id = 'component-reference-' . $items->getName() . '-' . $delta;
    $element['#prefix'] = '<div id="' . $wrapper_id . '" class="component-reference-widget">';
    $element['#suffix'] = '</div>';

    // Add component type indicator.
    if ($referenced_entity && $this->getSetting('show_component_type')) {
      $element['component_type'] = [
        '#type' => 'item',
        '#title' => $this->t('Component type'),
        '#markup' => $referenced_entity->bundle(),
        '#weight' => -10,
      ];
    }

    // Add render method indicator.
    if ($referenced_entity && $this->getSetting('show_render_method')) {
      $render_method = $referenced_entity->get('render_method')->value ?? 'twig';
      $element['render_method'] = [
        '#type' => 'item',
        '#title' => $this->t('Render method'),
        '#markup' => ucfirst($render_method),
        '#weight' => -9,
      ];
    }

    // Add preview.
    if ($referenced_entity && $this->getSetting('show_preview')) {
      $element['preview'] = $this->buildPreview($referenced_entity, $wrapper_id);
    }

    // Add inline editing if enabled.
    if ($referenced_entity && $this->getSetting('allow_inline_editing')) {
      $element['inline_edit'] = $this->buildInlineEditForm($referenced_entity, $delta, $form_state);
    }

    // Add prop override fields if enabled.
    if ($this->getSetting('allow_prop_override')) {
      $element['prop_overrides'] = $this->buildPropOverrideForm($referenced_entity, $item, $delta);
    }

    // Add action buttons.
    $element['actions'] = $this->buildActionButtons($referenced_entity, $delta, $wrapper_id);

    // Attach library.
    $element['#attached']['library'][] = 'component_entity/component-reference';

    // Add AJAX callback for dynamic updates.
    $element['target_id']['#ajax'] = [
      'callback' => [$this, 'updatePreviewCallback'],
      'wrapper' => $wrapper_id,
      'event' => 'change',
    ];

    return $element;
  }

  /**
   * Builds the preview element for a component.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The component entity.
   * @param string $wrapper_id
   *   The wrapper element ID.
   *
   * @return array
   *   The preview render array.
   */
  protected function buildPreview($entity, $wrapper_id) {
    $display_mode = $this->getSetting('preview_display_mode');
    $view_builder = $this->entityTypeManager->getViewBuilder('component');
    $preview = $view_builder->view($entity, $display_mode);

    $element = [
      '#type' => 'container',
      '#weight' => 100,
      '#attributes' => [
        'class' => ['component-reference-preview'],
      ],
    ];

    if ($this->getSetting('collapsible_preview')) {
      $element['#type'] = 'details';
      $element['#title'] = $this->t('Component preview');
      $element['#open'] = FALSE;
    }

    $element['content'] = $preview;

    return $element;
  }

  /**
   * Builds the inline edit form for a component.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The component entity.
   * @param int $delta
   *   The field delta.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The inline edit form.
   */
  protected function buildInlineEditForm($entity, $delta, FormStateInterface $form_state) {
    $element = [
      '#type' => 'details',
      '#title' => $this->t('Edit component content'),
      '#open' => FALSE,
      '#weight' => 200,
      '#attributes' => [
        'class' => ['component-inline-edit'],
      ],
    ];

    // Get the component form display.
    $form_display = $this->entityDisplayRepository->getFormDisplay('component', $entity->bundle(), 'default');

    // Build a subset of the component form.
    $component_form = [];
    $form_display->buildForm($entity, $component_form, $form_state);

    // Remove system fields.
    unset($component_form['name']);
    unset($component_form['status']);
    unset($component_form['uid']);
    unset($component_form['created']);
    unset($component_form['changed']);
    unset($component_form['revision_log_message']);

    $element['fields'] = $component_form;

    return $element;
  }

  /**
   * Builds the prop override form.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The component entity.
   * @param \Drupal\Core\Field\FieldItemInterface|null $item
   *   The field item.
   * @param int $delta
   *   The field delta.
   *
   * @return array
   *   The prop override form.
   */
  protected function buildPropOverrideForm($entity, $item, $delta) {
    $element = [
      '#type' => 'details',
      '#title' => $this->t('Override component props'),
      '#open' => FALSE,
      '#weight' => 300,
      '#attributes' => [
        'class' => ['component-prop-overrides'],
      ],
    ];

    if (!$entity) {
      $element['#access'] = FALSE;
      return $element;
    }

    // Get existing overrides.
    $overrides = $item ? $item->getOverrideProps() : [];

    // Build form elements for each field that can be overridden.
    foreach ($entity->getFields() as $field_name => $field) {
      // Skip system fields.
      if (in_array($field_name, ['id', 'uuid', 'langcode', 'type', 'status', 'created', 'changed', 'uid'])) {
        continue;
      }

      $field_definition = $field->getFieldDefinition();
      $field_label = $field_definition->getLabel();

      $element[$field_name] = [
        '#type' => 'textfield',
        '#title' => $field_label,
        '#default_value' => $overrides[$field_name] ?? '',
        '#description' => $this->t('Override the @field value for this reference.', [
          '@field' => $field_label,
        ]),
      ];

      // Use appropriate widget based on field type.
      $field_type = $field_definition->getType();
      switch ($field_type) {
        case 'boolean':
          $element[$field_name]['#type'] = 'checkbox';
          $element[$field_name]['#default_value'] = $overrides[$field_name] ?? FALSE;
          break;

        case 'text_long':
        case 'string_long':
          $element[$field_name]['#type'] = 'textarea';
          $element[$field_name]['#rows'] = 3;
          break;

        case 'list_string':
          $allowed_values = $field_definition->getSetting('allowed_values');
          if ($allowed_values) {
            $element[$field_name]['#type'] = 'select';
            $element[$field_name]['#options'] = ['' => $this->t('- None -')] + $allowed_values;
          }
          break;
      }
    }

    return $element;
  }

  /**
   * Builds action buttons for the component reference.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The component entity.
   * @param int $delta
   *   The field delta.
   * @param string $wrapper_id
   *   The wrapper element ID.
   *
   * @return array
   *   The action buttons.
   */
  protected function buildActionButtons($entity, $delta, $wrapper_id) {
    $element = [
      '#type' => 'container',
      '#weight' => 400,
      '#attributes' => [
        'class' => ['component-reference-actions'],
      ],
    ];

    if (!$entity) {
      return $element;
    }

    // Edit button.
    if ($this->getSetting('show_edit_button') && $entity->access('update')) {
      $element['edit'] = [
        '#type' => 'link',
        '#title' => $this->t('Edit'),
        '#url' => $entity->toUrl('edit-form'),
        '#attributes' => [
          'class' => ['button', 'button--small'],
          'target' => '_blank',
        ],
      ];
    }

    // Duplicate button.
    if ($this->getSetting('show_duplicate_button') && $entity->access('create')) {
      $element['duplicate'] = [
        '#type' => 'submit',
        '#value' => $this->t('Duplicate'),
        '#name' => 'duplicate_' . $delta,
        '#submit' => [[$this, 'duplicateComponent']],
        '#ajax' => [
          'callback' => [$this, 'updatePreviewCallback'],
          'wrapper' => $wrapper_id,
        ],
        '#attributes' => [
          'class' => ['button', 'button--small'],
        ],
      ];
    }

    return $element;
  }

  /**
   * AJAX callback to update the preview.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The updated element.
   */
  public function updatePreviewCallback(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $element_parents = array_slice($triggering_element['#array_parents'], 0, -1);
    return NestedArray::getValue($form, $element_parents);
  }

  /**
   * Submit handler for duplicating a component.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function duplicateComponent(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $element_parents = array_slice($triggering_element['#array_parents'], 0, -2);
    $widget_element = NestedArray::getValue($form, $element_parents);

    // Get the referenced entity.
    $entity_id = $widget_element['target_id']['#value'];
    if ($entity_id) {
      $storage = $this->entityTypeManager->getStorage('component');
      $entity = $storage->load($entity_id);

      if ($entity) {
        // Duplicate the entity.
        $duplicate = $entity->createDuplicate();
        $duplicate->set('name', $entity->label() . ' (Copy)');
        $duplicate->save();

        // Update the form value to reference the duplicate.
        $form_state->setValueForElement($widget_element['target_id'], $duplicate->id());

        // Add a message.
        \Drupal::messenger()->addMessage($this->t('Component duplicated successfully.'));
      }
    }

    $form_state->setRebuild();
  }

  /**
   * Gets available display mode options.
   *
   * @return array
   *   Array of display mode options.
   */
  protected function getDisplayModeOptions() {
    $options = [];
    $display_modes = $this->entityDisplayRepository->getViewModes('component');

    foreach ($display_modes as $mode => $info) {
      $options[$mode] = $info['label'];
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $values = parent::massageFormValues($values, $form, $form_state);

    // Process prop overrides and other custom data.
    foreach ($values as &$value) {
      if (isset($value['prop_overrides'])) {
        // Filter out empty overrides.
        $value['override_props'] = array_filter($value['prop_overrides']);
        unset($value['prop_overrides']);
      }

      // Add any display settings.
      if (isset($value['preview_display_mode'])) {
        $value['display_settings']['display_mode'] = $value['preview_display_mode'];
        unset($value['preview_display_mode']);
      }
    }

    return $values;
  }

}
