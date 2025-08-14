<?php

namespace Drupal\component_entity\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Cache\Cache;

/**
 * Plugin implementation of the 'component_reference_rendered' formatter.
 *
 * @FieldFormatter(
 *   id = "component_reference_rendered",
 *   label = @Translation("Rendered component"),
 *   field_types = {
 *     "component_reference",
 *     "entity_reference"
 *   }
 * )
 */
class ComponentReferenceFormatter extends EntityReferenceFormatterBase implements ContainerFactoryPluginInterface {

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
   * Constructs a ComponentReferenceFormatter.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
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
    $label,
    $view_mode,
    array $third_party_settings,
    EntityTypeManagerInterface $entity_type_manager,
    EntityDisplayRepositoryInterface $entity_display_repository,
    RendererInterface $renderer,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
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
      $configuration['label'],
      $configuration['view_mode'],
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
      'view_mode' => 'default',
      'render_method_override' => '',
      'wrapper_element' => 'div',
      'wrapper_classes' => '',
      'add_component_classes' => TRUE,
      'enable_lazy_loading' => FALSE,
      'enable_progressive_enhancement' => FALSE,
      'cache_mode' => 'default',
      'show_placeholder' => FALSE,
      'placeholder_text' => 'Loading component...',
      'apply_prop_overrides' => TRUE,
      'merge_wrapper_attributes' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('View mode'),
      '#options' => $this->getViewModeOptions(),
      '#default_value' => $this->getSetting('view_mode'),
      '#description' => $this->t('The view mode to use when rendering components.'),
    ];

    $elements['render_method_override'] = [
      '#type' => 'select',
      '#title' => $this->t('Render method override'),
      '#options' => [
        '' => $this->t('- Use component default -'),
        'twig' => $this->t('Twig (Server-side)'),
        'react' => $this->t('React (Client-side)'),
      ],
      '#default_value' => $this->getSetting('render_method_override'),
      '#description' => $this->t('Override the render method for all components.'),
    ];

    $elements['wrapper_element'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Wrapper element'),
      '#default_value' => $this->getSetting('wrapper_element'),
      '#description' => $this->t('HTML element to wrap each component (e.g., div, section, article).'),
      '#size' => 10,
    ];

    $elements['wrapper_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Wrapper classes'),
      '#default_value' => $this->getSetting('wrapper_classes'),
      '#description' => $this->t('CSS classes to add to the wrapper element. Separate multiple classes with spaces.'),
    ];

    $elements['add_component_classes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add component type classes'),
      '#default_value' => $this->getSetting('add_component_classes'),
      '#description' => $this->t('Automatically add CSS classes based on component type and ID.'),
    ];

    $elements['enable_lazy_loading'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable lazy loading'),
      '#default_value' => $this->getSetting('enable_lazy_loading'),
      '#description' => $this->t('Load components only when they become visible in the viewport.'),
    ];

    $elements['enable_progressive_enhancement'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable progressive enhancement'),
      '#default_value' => $this->getSetting('enable_progressive_enhancement'),
      '#description' => $this->t('Enhance server-rendered components with client-side functionality.'),
    ];

    $elements['cache_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Cache mode'),
      '#options' => [
        'default' => $this->t('Default'),
        'per_user' => $this->t('Per user'),
        'per_role' => $this->t('Per role'),
        'none' => $this->t('No caching'),
      ],
      '#default_value' => $this->getSetting('cache_mode'),
      '#description' => $this->t('How to cache rendered components.'),
    ];

    $elements['show_placeholder'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show loading placeholder'),
      '#default_value' => $this->getSetting('show_placeholder'),
      '#description' => $this->t('Display a placeholder while components are loading.'),
      '#states' => [
        'visible' => [
          ':input[name*="enable_lazy_loading"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $elements['placeholder_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder text'),
      '#default_value' => $this->getSetting('placeholder_text'),
      '#states' => [
        'visible' => [
          ':input[name*="show_placeholder"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $elements['apply_prop_overrides'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Apply prop overrides'),
      '#default_value' => $this->getSetting('apply_prop_overrides'),
      '#description' => $this->t('Apply any prop overrides defined on the reference.'),
    ];

    $elements['merge_wrapper_attributes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Merge wrapper attributes'),
      '#default_value' => $this->getSetting('merge_wrapper_attributes'),
      '#description' => $this->t('Merge wrapper attributes from the reference with the formatter settings.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $view_mode = $this->getSetting('view_mode');
    $summary[] = $this->t('View mode: @mode', ['@mode' => $view_mode]);

    if ($render_method = $this->getSetting('render_method_override')) {
      $summary[] = $this->t('Render method: @method', ['@method' => ucfirst($render_method)]);
    }

    if ($wrapper = $this->getSetting('wrapper_element')) {
      $classes = $this->getSetting('wrapper_classes');
      $summary[] = $this->t('Wrapper: @element @classes', [
        '@element' => '<' . $wrapper . '>',
        '@classes' => $classes ? '(' . $classes . ')' : '',
      ]);
    }

    $features = [];
    if ($this->getSetting('enable_lazy_loading')) {
      $features[] = $this->t('Lazy loading');
    }
    if ($this->getSetting('enable_progressive_enhancement')) {
      $features[] = $this->t('Progressive enhancement');
    }
    if ($this->getSetting('apply_prop_overrides')) {
      $features[] = $this->t('Prop overrides');
    }
    if (!empty($features)) {
      $summary[] = $this->t('Features: @features', [
        '@features' => implode(', ', $features),
      ]);
    }

    $cache_mode = $this->getSetting('cache_mode');
    if ($cache_mode !== 'default') {
      $summary[] = $this->t('Cache: @mode', ['@mode' => $cache_mode]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $entities = $this->getEntitiesToView($items, $langcode);

    // Return early if there are no entities to display.
    if (empty($entities)) {
      return $elements;
    }

    $view_mode = $this->getSetting('view_mode');
    $render_method_override = $this->getSetting('render_method_override');
    $view_builder = $this->entityTypeManager->getViewBuilder('component');

    foreach ($entities as $delta => $entity) {
      // Skip if not a component entity.
      if ($entity->getEntityTypeId() !== 'component') {
        continue;
      }

      // Build the component render array.
      $build = $view_builder->view($entity, $view_mode);

      // Apply render method override if set.
      if ($render_method_override) {
        $build['#render_method'] = $render_method_override;
      }

      // Apply prop overrides if enabled.
      if ($this->getSetting('apply_prop_overrides') && isset($items[$delta])) {
        $this->applyPropOverrides($build, $items[$delta]);
      }

      // Wrap the component.
      $elements[$delta] = $this->wrapComponent($build, $entity, $items[$delta] ?? NULL);

      // Add lazy loading if enabled.
      if ($this->getSetting('enable_lazy_loading')) {
        $this->addLazyLoading($elements[$delta], $entity, $delta);
      }

      // Add progressive enhancement if enabled.
      if ($this->getSetting('enable_progressive_enhancement')) {
        $this->addProgressiveEnhancement($elements[$delta], $entity);
      }

      // Apply cache settings.
      $this->applyCacheSettings($elements[$delta], $entity);
    }

    // Attach necessary libraries.
    if (!empty($elements)) {
      $elements['#attached']['library'][] = 'component_entity/component-reference';

      if ($this->getSetting('enable_lazy_loading')) {
        $elements['#attached']['library'][] = 'component_entity/lazy-loading';
      }

      if ($this->getSetting('enable_progressive_enhancement')) {
        $elements['#attached']['library'][] = 'component_entity/progressive-enhancement';
      }
    }

    return $elements;
  }

  /**
   * Wraps a component with configured wrapper element and attributes.
   *
   * @param array $build
   *   The component render array.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The component entity.
   * @param \Drupal\Core\Field\FieldItemInterface|null $item
   *   The field item.
   *
   * @return array
   *   The wrapped render array.
   */
  protected function wrapComponent(array $build, $entity, $item = NULL) {
    $wrapper_element = $this->getSetting('wrapper_element') ?: 'div';
    $wrapper_classes = array_filter(explode(' ', $this->getSetting('wrapper_classes')));

    // Add component type classes if enabled.
    if ($this->getSetting('add_component_classes')) {
      $wrapper_classes[] = 'component';
      $wrapper_classes[] = 'component--' . $entity->bundle();
      $wrapper_classes[] = 'component--' . $entity->id();
    }

    // Build wrapper attributes.
    $attributes = [
      'class' => $wrapper_classes,
      'data-component-id' => $entity->id(),
      'data-component-type' => $entity->bundle(),
      'data-component-uuid' => $entity->uuid(),
    ];

    // Merge wrapper attributes from the field item if enabled.
    if ($this->getSetting('merge_wrapper_attributes') && $item) {
      $item_attributes = $item->getWrapperAttributes();
      if (!empty($item_attributes)) {
        $attributes = array_merge_recursive($attributes, $item_attributes);
      }
    }

    // Handle render method data attribute.
    $render_method = $build['#render_method'] ?? $entity->get('render_method')->value ?? 'twig';
    $attributes['data-render-method'] = $render_method;

    return [
      '#type' => 'html_tag',
      '#tag' => $wrapper_element,
      '#attributes' => $attributes,
      '#value' => $build,
    ];
  }

  /**
   * Applies prop overrides to the component build.
   *
   * @param array &$build
   *   The component render array.
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The field item.
   */
  protected function applyPropOverrides(array &$build, $item) {
    $overrides = $item->getOverrideProps();
    if (empty($overrides)) {
      return;
    }

    // Apply overrides to the build context.
    if (!isset($build['#context'])) {
      $build['#context'] = [];
    }

    $build['#context']['prop_overrides'] = $overrides;

    // For SDC components, merge into props.
    if (isset($build['#props'])) {
      $build['#props'] = array_merge($build['#props'], $overrides);
    }

    // For React components, merge into data.
    if (isset($build['#react_data'])) {
      $build['#react_data'] = array_merge($build['#react_data'], $overrides);
    }
  }

  /**
   * Adds lazy loading functionality to the component.
   *
   * @param array &$element
   *   The render element.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The component entity.
   * @param int $delta
   *   The field delta.
   */
  protected function addLazyLoading(array &$element, $entity, $delta) {
    // Add lazy loading attributes.
    $element['#attributes']['data-lazy-load'] = 'true';
    $element['#attributes']['data-component-path'] = $entity->toUrl('canonical')->toString();

    // Add placeholder if enabled.
    if ($this->getSetting('show_placeholder')) {
      $placeholder_text = $this->getSetting('placeholder_text');
      $element['#prefix'] = '<div class="component-placeholder" data-delta="' . $delta . '">' .
        $placeholder_text . '</div>';
      $element['#attributes']['style'] = 'display: none;';
    }

    // Add Intersection Observer configuration.
    $element['#attached']['drupalSettings']['componentEntity']['lazyLoad'][$delta] = [
      'threshold' => 0.1,
      'rootMargin' => '50px',
    ];
  }

  /**
   * Adds progressive enhancement to the component.
   *
   * @param array &$element
   *   The render element.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The component entity.
   */
  protected function addProgressiveEnhancement(array &$element, $entity) {
    // Add progressive enhancement attributes.
    $element['#attributes']['data-progressive-enhance'] = 'true';

    // Determine the component's React configuration if applicable.
    if ($entity->hasField('react_config')) {
      $react_config = $entity->get('react_config')->getValue();
      if (!empty($react_config)) {
        $element['#attributes']['data-react-config'] = json_encode($react_config);
      }
    }

    // Add enhancement settings.
    $element['#attached']['drupalSettings']['componentEntity']['progressiveEnhance'][$entity->id()] = [
      'componentType' => $entity->bundle(),
      'renderMethod' => $entity->get('render_method')->value ?? 'twig',
      'hydration' => $react_config['hydration'] ?? 'full',
    ];
  }

  /**
   * Applies cache settings to the element.
   *
   * @param array &$element
   *   The render element.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The component entity.
   */
  protected function applyCacheSettings(array &$element, $entity) {
    $cache_mode = $this->getSetting('cache_mode');

    switch ($cache_mode) {
      case 'none':
        $element['#cache']['max-age'] = 0;
        break;

      case 'per_user':
        $element['#cache']['contexts'][] = 'user';
        break;

      case 'per_role':
        $element['#cache']['contexts'][] = 'user.roles';
        break;

      default:
        // Use default caching.
        $element['#cache']['tags'] = Cache::mergeTags(
          $element['#cache']['tags'] ?? [],
          $entity->getCacheTags()
        );
        break;
    }
  }

  /**
   * Gets available view mode options.
   *
   * @return array
   *   Array of view mode options.
   */
  protected function getViewModeOptions() {
    $options = [];
    $view_modes = $this->entityDisplayRepository->getViewModes('component');

    foreach ($view_modes as $mode => $info) {
      $options[$mode] = $info['label'];
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    // This formatter is only applicable to fields
    // that reference component entities.
    $target_type = $field_definition->getFieldStorageDefinition()->getSetting('target_type');
    return $target_type === 'component';
  }

}
