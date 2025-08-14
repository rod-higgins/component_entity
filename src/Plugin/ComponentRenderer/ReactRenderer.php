<?php

namespace Drupal\component_entity\Plugin\ComponentRenderer;

use Drupal\component_entity\Entity\ComponentEntityInterface;
use Drupal\component_entity\Plugin\ComponentRendererBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Asset\AssetCollectionRendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a React renderer for components.
 *
 * @ComponentRenderer(
 *   id = "react",
 *   label = @Translation("React Renderer"),
 *   description = @Translation("Renders components using React with client-side hydration support."),
 *   method = "react",
 *   supports_ssr = TRUE,
 *   supports_hydration = TRUE,
 *   supports_progressive = TRUE,
 *   weight = 10,
 *   file_extensions = {"jsx", "tsx"},
 *   libraries = {
 *     "component_entity/react-renderer",
 *     "component_entity/react-components"
 *   },
 *   cache_contexts = {"session", "user"},
 *   enabled = TRUE,
 *   config_schema = {
 *     "hydration_method" = {
 *       "type" = "string",
 *       "label" = "Hydration Method",
 *       "default" = "full"
 *     },
 *     "lazy_load" = {
 *       "type" = "boolean",
 *       "label" = "Lazy Load",
 *       "default" = FALSE
 *     },
 *     "ssr_enabled" = {
 *       "type" = "boolean",
 *       "label" = "Server Side Rendering",
 *       "default" = FALSE
 *     }
 *   }
 * )
 */
class ReactRenderer extends ComponentRendererBase implements ContainerFactoryPluginInterface {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The asset resolver.
   *
   * @var \Drupal\Core\Asset\AssetResolverInterface
   */
  protected $assetResolver;

  /**
   * The JS collection renderer.
   *
   * @var \Drupal\Core\Asset\AssetCollectionRendererInterface
   */
  protected $jsCollectionRenderer;

  /**
   * The CSS collection renderer.
   *
   * @var \Drupal\Core\Asset\AssetCollectionRendererInterface
   */
  protected $cssCollectionRenderer;

  /**
   * Constructs a ReactRenderer object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Asset\AssetResolverInterface $asset_resolver
   *   The asset resolver.
   * @param \Drupal\Core\Asset\AssetCollectionRendererInterface $js_collection_renderer
   *   The JS collection renderer.
   * @param \Drupal\Core\Asset\AssetCollectionRendererInterface $css_collection_renderer
   *   The CSS collection renderer.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RendererInterface $renderer,
    AssetResolverInterface $asset_resolver,
    AssetCollectionRendererInterface $js_collection_renderer,
    AssetCollectionRendererInterface $css_collection_renderer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->renderer = $renderer;
    $this->assetResolver = $asset_resolver;
    $this->jsCollectionRenderer = $js_collection_renderer;
    $this->cssCollectionRenderer = $css_collection_renderer;
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
      $container->get('asset.resolver'),
      $container->get('asset.js.collection_renderer'),
      $container->get('asset.css.collection_renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render(ComponentEntityInterface $entity, array $context = []) {
    $component_id = 'react-component-' . $entity->uuid();
    $bundle = $entity->bundle();

    // Get React configuration.
    $react_config = $entity->getReactConfig();
    $hydration_method = $react_config['hydration'] ?? $this->configuration['hydration_method'] ?? 'full';
    $lazy_load = $react_config['lazy'] ?? $this->configuration['lazy_load'] ?? FALSE;
    $ssr_enabled = $react_config['ssr'] ?? $this->configuration['ssr_enabled'] ?? FALSE;

    // Extract props and slots from entity fields.
    $props = $this->extractProps($entity, $context);
    $slots = $this->extractSlots($entity, $context);

    // Build the render array.
    $build = [
      '#theme' => 'component_react',
      '#component_id' => $component_id,
      '#component_type' => $bundle,
      '#props' => $props,
      '#slots' => $slots,
      '#hydration_method' => $hydration_method,
      '#lazy_load' => $lazy_load,
      '#attached' => [
        'library' => [
          'component_entity/react-renderer',
        ],
        'drupalSettings' => [
          'componentEntity' => [
            'react' => [
              $component_id => [
                'componentType' => $bundle,
                'props' => $props,
                'slots' => $slots,
                'hydration' => $hydration_method,
                'lazy' => $lazy_load,
              ],
            ],
          ],
        ],
      ],
    ];

    // Add component-specific React library if exists.
    $library = "component_entity/react.$bundle";
    if ($this->libraryDiscovery->getLibraryByName('component_entity', "react.$bundle")) {
      $build['#attached']['library'][] = $library;
    }

    // If SSR is enabled and available, add server-rendered content.
    if ($ssr_enabled && $this->isNodeAvailable()) {
      $build['#ssr_content'] = $this->renderServerSide($entity, $props, $slots);
    }

    // Add fallback for no-JS scenarios when using context view_mode.
    if (!empty($context['progressive']) || !empty($react_config['progressive'])) {
      $build['#fallback'] = $this->renderFallback($entity, $context);
    }

    return $build;
  }

  /**
   * Extracts props from entity reference fields.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field item list.
   *
   * @return array|null
   *   Array of entity reference data or NULL.
   */
  protected function extractEntityReferenceProps($field) {
    if ($field->isEmpty()) {
      return NULL;
    }

    $values = [];
    foreach ($field as $item) {
      if ($entity = $item->entity) {
        $values[] = [
          'id' => $entity->id(),
          'uuid' => $entity->uuid(),
          'label' => $entity->label(),
          'type' => $entity->getEntityTypeId(),
          'bundle' => $entity->bundle(),
          'url' => $entity->toUrl('canonical')->toString(),
        ];
      }
    }

    // FIXED: Line 300 - Split long line that exceeds 80 characters.
    $storage = $field->getFieldDefinition()->getFieldStorageDefinition();
    return $storage->isMultiple() ? $values : $values[0] ?? NULL;
  }

  /**
   * Extracts props from image fields.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field item list.
   *
   * @return array|null
   *   Array of image data or NULL.
   */
  protected function extractImageProps($field) {
    if ($field->isEmpty()) {
      return NULL;
    }

    $values = [];
    foreach ($field as $item) {
      if ($file = $item->entity) {
        $values[] = [
          'url' => $file->createFileUrl(),
          'alt' => $item->alt,
          'title' => $item->title,
          'width' => $item->width,
          'height' => $item->height,
        ];
      }
    }

    // Split long line for better readability.
    $storage = $field->getFieldDefinition()->getFieldStorageDefinition();
    return $storage->isMultiple() ? $values : $values[0] ?? NULL;
  }

  /**
   * Extracts props from link fields.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field item list.
   *
   * @return array|null
   *   Array of link data or NULL.
   */
  protected function extractLinkProps($field) {
    if ($field->isEmpty()) {
      return NULL;
    }

    $values = [];
    foreach ($field as $item) {
      $values[] = [
        'url' => $item->getUrl()->toString(),
        'title' => $item->title,
        'options' => $item->options,
      ];
    }

    // Split long line for better readability.
    $storage = $field->getFieldDefinition()->getFieldStorageDefinition();
    return $storage->isMultiple() ? $values : $values[0] ?? NULL;
  }

  /**
   * Extracts props from text fields.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field item list.
   *
   * @return string|array|null
   *   Processed text value(s) or NULL.
   */
  protected function extractTextProps($field) {
    if ($field->isEmpty()) {
      return NULL;
    }

    $values = [];
    foreach ($field as $item) {
      // Check if field has format.
      if (isset($item->format)) {
        // Process through text format.
        $build = [
          '#type' => 'processed_text',
          '#text' => $item->value,
          '#format' => $item->format,
        ];
        $values[] = $this->renderer->renderRoot($build);
      }
      else {
        $values[] = $item->value;
      }
    }

    // Split long line for better readability.
    $storage = $field->getFieldDefinition()->getFieldStorageDefinition();
    return $storage->isMultiple() ? $values : $values[0] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsSsr() {
    // Check if Node.js is available for SSR.
    return $this->getPluginDefinition()['supports_ssr'] && $this->isNodeAvailable();
  }

  /**
   * {@inheritdoc}
   */
  public function supportsHydration() {
    return $this->getPluginDefinition()['supports_hydration'];
  }

  /**
   * {@inheritdoc}
   */
  public function supportsProgressive() {
    return $this->getPluginDefinition()['supports_progressive'];
  }

  /**
   * Extracts props from the component entity.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param array $context
   *   The render context.
   *
   * @return array
   *   The props array.
   */
  protected function extractProps(ComponentEntityInterface $entity, array $context) {
    $props = [];

    // Get all field values except internal fields.
    foreach ($entity->getFields() as $field_name => $field) {
      if (!in_array($field_name, $this->getInternalFields())) {
        $value = $field->getValue();
        if (!empty($value)) {
          $props[$field_name] = $this->processFieldValue($value, $field);
        }
      }
    }

    // Add context data as props.
    if (!empty($context['additional_props'])) {
      $props = array_merge($props, $context['additional_props']);
    }

    return $props;
  }

  /**
   * Extracts slots from the component entity.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param array $context
   *   The render context.
   *
   * @return array
   *   The slots array.
   */
  protected function extractSlots(ComponentEntityInterface $entity, array $context) {
    $slots = [];

    // Look for fields that represent slots (e.g., field_slot_*).
    foreach ($entity->getFields() as $field_name => $field) {
      if (strpos($field_name, 'field_slot_') === 0) {
        $slot_name = str_replace('field_slot_', '', $field_name);
        $value = $field->getValue();
        if (!empty($value)) {
          $slots[$slot_name] = $this->renderSlotContent($value, $field);
        }
      }
    }

    // Add context slots.
    if (!empty($context['slots'])) {
      $slots = array_merge($slots, $context['slots']);
    }

    return $slots;
  }

  /**
   * Renders server-side content for SSR.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param array $props
   *   The component props.
   * @param array $slots
   *   The component slots.
   *
   * @return string
   *   The server-rendered HTML.
   */
  protected function renderServerSide(ComponentEntityInterface $entity, array $props, array $slots) {
    // This would typically call a Node.js service
    // to render React on the server.
    // For now, return empty string as placeholder.
    return '';
  }

  /**
   * Renders fallback content for no-JS scenarios.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param array $context
   *   The render context.
   *
   * @return array
   *   The fallback render array.
   */
  protected function renderFallback(ComponentEntityInterface $entity, array $context) {
    // Try to render with Twig as fallback.
    $fallback = [
      '#markup' => '<div class="component-fallback">' . $entity->label() . '</div>',
    ];

    return $fallback;
  }

  /**
   * Checks if Node.js is available for SSR.
   *
   * @return bool
   *   TRUE if Node.js is available, FALSE otherwise.
   */
  protected function isNodeAvailable() {
    // Check if Node.js executable is available.
    $output = [];
    $return_var = 0;
    exec('which node 2>/dev/null', $output, $return_var);
    return $return_var === 0;
  }

  /**
   * Gets the list of internal fields to exclude from props.
   *
   * @return array
   *   Array of internal field names.
   */
  protected function getInternalFields() {
    return [
      'id',
      'uuid',
      'vid',
      'type',
      'langcode',
      'status',
      'created',
      'changed',
      'uid',
      'name',
      'render_method',
      'react_config',
    ];
  }

  /**
   * Processes field values for React props.
   *
   * @param array $value
   *   The field value.
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field object.
   *
   * @return mixed
   *   The processed value.
   */
  protected function processFieldValue(array $value, $field) {
    // For single-value fields, return the value directly.
    if (count($value) === 1) {
      return reset($value);
    }

    return $value;
  }

  /**
   * Renders slot content.
   *
   * @param array $value
   *   The slot field value.
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field object.
   *
   * @return string
   *   The rendered slot content.
   */
  protected function renderSlotContent(array $value, $field) {
    // Render the field value as HTML.
    $render_array = $field->view();
    return $this->renderer->renderRoot($render_array);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = parent::getCacheContexts();
    // For React state.
    $contexts[] = 'session';
    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(ComponentEntityInterface $entity) {
    $tags = parent::getCacheTags($entity);
    $tags[] = 'component_render:react';
    return $tags;
  }

}
