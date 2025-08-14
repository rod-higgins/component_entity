<?php

namespace Drupal\component_entity\Plugin\ComponentRenderer;

use Drupal\component_entity\Entity\ComponentEntityInterface;
use Drupal\component_entity\Plugin\ComponentRendererBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Plugin\Component\ComponentPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Template\TwigEnvironment;

/**
 * Provides a server-side renderer for components.
 *
 * @ComponentRenderer(
 *   id = "server_side",
 *   label = @Translation("Server-Side Renderer"),
 *   description = @Translation("Renders components entirely on the server with optimized caching."),
 *   method = "server",
 *   supports_ssr = TRUE,
 *   supports_hydration = FALSE,
 *   supports_progressive = TRUE,
 *   weight = 5,
 *   file_extensions = {"twig", "html"},
 *   libraries = {
 *     "component_entity/server-renderer"
 *   },
 *   cache_contexts = {"theme", "languages", "user.permissions"},
 *   enabled = TRUE,
 *   config_schema = {
 *     "cache_strategy" = {
 *       "type" = "string",
 *       "label" = "Cache Strategy",
 *       "default" = "aggressive"
 *     },
 *     "inline_critical_css" = {
 *       "type" = "boolean",
 *       "label" = "Inline Critical CSS",
 *       "default" = TRUE
 *     },
 *     "optimize_output" = {
 *       "type" = "boolean",
 *       "label" = "Optimize HTML Output",
 *       "default" = TRUE
 *     }
 *   }
 * )
 */
class ServerSideRenderer extends ComponentRendererBase implements ContainerFactoryPluginInterface {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The SDC component plugin manager.
   *
   * @var \Drupal\Core\Plugin\Component\ComponentPluginManager
   */
  protected $componentManager;

  /**
   * The Twig environment.
   *
   * @var \Drupal\Core\Template\TwigEnvironment
   */
  protected $twig;

  /**
   * Constructs a ServerSideRenderer object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Plugin\Component\ComponentPluginManager $component_manager
   *   The SDC component plugin manager.
   * @param \Drupal\Core\Template\TwigEnvironment $twig
   *   The Twig environment.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RendererInterface $renderer,
    ComponentPluginManager $component_manager,
    TwigEnvironment $twig,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->renderer = $renderer;
    $this->componentManager = $component_manager;
    $this->twig = $twig;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('renderer'),
      $container->get('plugin.manager.sdc'),
      $container->get('twig')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render(ComponentEntityInterface $entity, array $context = []) {
    // FIXED: Removed unused $bundle variable (was line 121)
    $view_mode = $context['view_mode'] ?? 'default';

    // Get cache strategy.
    $cache_strategy = $this->configuration['cache_strategy'] ?? 'aggressive';
    $inline_css = $this->configuration['inline_critical_css'] ?? TRUE;
    $optimize = $this->configuration['optimize_output'] ?? TRUE;

    // Try to get the SDC component.
    // FIXED: Changed from getSDCComponentId.
    $sdc_id = $this->getSdcComponentId($entity);

    if ($sdc_id && $this->componentManager->hasDefinition($sdc_id)) {
      // Use SDC component rendering.
      $build = $this->renderSdcComponent($entity, $sdc_id, $context);
    }
    else {
      // Fall back to standard entity rendering.
      $build = $this->renderStandardEntity($entity, $view_mode, $context);
    }

    // Apply server-side optimizations.
    $build = $this->applyOptimizations($build, $optimize, $inline_css);

    // Set cache metadata based on strategy.
    $build['#cache'] = $this->getCacheMetadata($entity, $cache_strategy);

    return $build;
  }

  /**
   * Renders using SDC component.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param string $sdc_id
   *   The SDC component ID.
   * @param array $context
   *   The render context.
   *
   * @return array
   *   The render array.
   */
  protected function renderSdcComponent(ComponentEntityInterface $entity, $sdc_id, array $context) {
    // Get component props and slots.
    $props = $this->extractComponentProps($entity, $context);
    $slots = $this->extractComponentSlots($entity, $context);

    // Build the SDC render array.
    $build = [
      '#type' => 'component',
      '#component' => $sdc_id,
      '#props' => $props,
      '#slots' => $slots,
    ];

    // Add wrapper for styling and identification.
    $build['#prefix'] = sprintf(
      '<div class="component-entity component-entity--%s component-entity--%s" data-component-id="%s">',
      $entity->bundle(),
      $entity->id(),
      $entity->uuid()
    );
    $build['#suffix'] = '</div>';

    return $build;
  }

  /**
   * Renders using standard entity view builder.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param string $view_mode
   *   The view mode.
   * @param array $context
   *   The render context.
   *
   * @return array
   *   The render array.
   */
  protected function renderStandardEntity(ComponentEntityInterface $entity, $view_mode, array $context) {
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('component');
    $build = $view_builder->view($entity, $view_mode);

    // Add any additional context.
    if (!empty($context['additional_fields'])) {
      foreach ($context['additional_fields'] as $field_name => $field_value) {
        $build[$field_name] = $field_value;
      }
    }

    return $build;
  }

  /**
   * Applies server-side optimizations to the render array.
   *
   * @param array $build
   *   The render array.
   * @param bool $optimize
   *   Whether to optimize output.
   * @param bool $inline_css
   *   Whether to inline critical CSS.
   *
   * @return array
   *   The optimized render array.
   */
  protected function applyOptimizations(array $build, $optimize, $inline_css) {
    if ($optimize) {
      // Add optimization flags for the theme layer.
      $build['#attributes']['data-optimized'] = 'true';

      // Enable HTML minification.
      $build['#post_render'][] = [$this, 'minifyHtml'];
    }

    if ($inline_css) {
      // Extract and inline critical CSS.
      $build['#attached']['html_head'][] = [
        [
          '#type' => 'html_tag',
          '#tag' => 'style',
          '#value' => $this->extractCriticalCss($build),
          '#attributes' => ['data-critical' => 'true'],
        ],
        'critical_css_' . $build['#cache']['keys'][0] ?? 'component',
      ];
    }

    // Add resource hints for better performance.
    $this->addResourceHints($build);

    return $build;
  }

  /**
   * Gets the SDC component ID for the entity.
   *
   * FIXED: Renamed from getSDCComponentId to getSdcComponentId (line 265)
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   *
   * @return string|null
   *   The SDC component ID or NULL if not found.
   */
  protected function getSdcComponentId(ComponentEntityInterface $entity) {
    $component_type = $entity->getComponentType();
    if ($component_type) {
      return $component_type->get('sdc_id');
    }
    return NULL;
  }

  /**
   * Extracts component props from the entity.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param array $context
   *   The render context.
   *
   * @return array
   *   The props array.
   */
  protected function extractComponentProps(ComponentEntityInterface $entity, array $context) {
    $props = [];

    // Extract field values as props.
    foreach ($entity->getFields() as $field_name => $field) {
      if (strpos($field_name, 'field_') === 0 && !$this->isSlotField($field_name)) {
        $prop_name = str_replace('field_', '', $field_name);
        $value = $field->getValue();

        if (!empty($value)) {
          // Process the value based on field type.
          $props[$prop_name] = $this->processFieldValue($field, $value);
        }
      }
    }

    // Merge with context props.
    if (!empty($context['props'])) {
      $props = array_merge($props, $context['props']);
    }

    return $props;
  }

  /**
   * Extracts component slots from the entity.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param array $context
   *   The render context.
   *
   * @return array
   *   The slots array.
   */
  protected function extractComponentSlots(ComponentEntityInterface $entity, array $context) {
    $slots = [];

    // Extract slot fields.
    foreach ($entity->getFields() as $field_name => $field) {
      if ($this->isSlotField($field_name)) {
        $slot_name = str_replace('field_slot_', '', $field_name);
        $value = $field->getValue();

        if (!empty($value)) {
          // Render the field for the slot.
          $slots[$slot_name] = $field->view();
        }
      }
    }

    // Merge with context slots.
    if (!empty($context['slots'])) {
      $slots = array_merge($slots, $context['slots']);
    }

    return $slots;
  }

  /**
   * Checks if a field is a slot field.
   *
   * @param string $field_name
   *   The field name.
   *
   * @return bool
   *   TRUE if it's a slot field, FALSE otherwise.
   */
  protected function isSlotField($field_name) {
    return strpos($field_name, 'field_slot_') === 0;
  }

  /**
   * Processes field value based on field type.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field object.
   * @param array $value
   *   The field value.
   *
   * @return mixed
   *   The processed value.
   */
  protected function processFieldValue($field, array $value) {
    // For single-value fields, return the value directly.
    if (count($value) === 1) {
      $item = reset($value);

      // Extract the main property value.
      if (isset($item['value'])) {
        return $item['value'];
      }
      elseif (isset($item['target_id'])) {
        // For entity reference fields, load the entity.
        $entity = \Drupal::entityTypeManager()
          ->getStorage($field->getSetting('target_type'))
          ->load($item['target_id']);
        return $entity;
      }

      return $item;
    }

    // For multi-value fields, return the array.
    // FIXED: Removed unused bound variable $field (was line 388)
    return array_map(function ($item) {
      if (isset($item['value'])) {
        return $item['value'];
      }
      elseif (isset($item['target_id'])) {
        return $item['target_id'];
      }
      return $item;
    }, $value);
  }

  /**
   * Gets cache metadata based on strategy.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param string $strategy
   *   The cache strategy.
   *
   * @return array
   *   The cache metadata array.
   */
  protected function getCacheMetadata(ComponentEntityInterface $entity, $strategy) {
    $cache = [
      'keys' => ['component', $entity->bundle(), $entity->id()],
      'contexts' => $this->getCacheContexts(),
      'tags' => $this->getCacheTags($entity),
      'max-age' => $this->getCacheMaxAge($entity),
    ];

    // Adjust based on strategy.
    switch ($strategy) {
      case 'aggressive':
        // Cache for longer periods.
        // 24 hours.
        $cache['max-age'] = 86400;
        break;

      case 'moderate':
        // Standard caching.
        // 1 hour.
        $cache['max-age'] = 3600;
        break;

      case 'minimal':
        // Minimal caching.
        // 5 minutes.
        $cache['max-age'] = 300;
        break;

      case 'none':
        // No caching.
        $cache['max-age'] = 0;
        break;
    }

    return $cache;
  }

  /**
   * Gets cache contexts.
   *
   * @return array
   *   The cache contexts.
   */
  protected function getCacheContexts() {
    return [
      'theme',
      'languages:language_interface',
      'user.permissions',
      'route',
    ];
  }

  /**
   * Gets cache tags for the entity.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   *
   * @return array
   *   The cache tags.
   */
  protected function getCacheTags(ComponentEntityInterface $entity) {
    $tags = $entity->getCacheTags();
    $tags[] = 'config:component_entity.settings';
    $tags[] = 'component_type:' . $entity->bundle();

    return $tags;
  }

  /**
   * Gets cache max age for the entity.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   *
   * @return int
   *   The cache max age.
   */
  protected function getCacheMaxAge(ComponentEntityInterface $entity) {
    // Default to 1 hour.
    return 3600;
  }

  /**
   * Minifies HTML output.
   *
   * @param string $markup
   *   The HTML markup.
   *
   * @return string
   *   The minified HTML.
   */
  public function minifyHtml($markup) {
    // Remove comments (except IE conditionals).
    $markup = preg_replace('/<!--(?!\[if).*?-->/s', '', $markup);

    // Remove unnecessary whitespace.
    $markup = preg_replace('/\s+/', ' ', $markup);
    $markup = preg_replace('/>\s+</', '><', $markup);

    return trim($markup);
  }

  /**
   * Extracts critical CSS for inline embedding.
   *
   * @param array $build
   *   The render array.
   *
   * @return string
   *   The critical CSS.
   */
  protected function extractCriticalCss(array $build) {
    // This would normally extract critical CSS from the component.
    // For now, return empty string.
    return '';
  }

  /**
   * Adds resource hints for performance.
   *
   * @param array &$build
   *   The render array.
   */
  protected function addResourceHints(array &$build) {
    // Add preconnect hints for external resources.
    if (!empty($build['#attached']['html_head_link'])) {
      $build['#attached']['html_head_link'][] = [
        [
          'rel' => 'preconnect',
          'href' => 'https://fonts.googleapis.com',
        ],
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function supportsSsr() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsHydration() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsProgressive() {
    return TRUE;
  }

}
