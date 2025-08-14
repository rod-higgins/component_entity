<?php

namespace Drupal\component_entity;

use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Asset\AssetCollectionRendererInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Plugin\Component\ComponentPluginManager;
use Drupal\component_entity\Entity\ComponentEntityInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Handles React rendering for component entities.
 */
class ComponentReactRenderer {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The SDC plugin manager.
   *
   * @var \Drupal\Core\Plugin\Component\ComponentPluginManager
   */
  protected $componentManager;

  /**
   * The asset resolver.
   *
   * @var \Drupal\Core\Asset\AssetResolverInterface
   */
  protected $assetResolver;

  /**
   * The CSS collection renderer.
   *
   * @var \Drupal\Core\Asset\AssetCollectionRendererInterface
   */
  protected $cssCollectionRenderer;

  /**
   * The JS collection renderer.
   *
   * @var \Drupal\Core\Asset\AssetCollectionRendererInterface
   */
  protected $jsCollectionRenderer;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a ComponentReactRenderer object.
   */
  public function __construct(
    RendererInterface $renderer,
    ComponentPluginManager $component_manager,
    AssetResolverInterface $asset_resolver,
    AssetCollectionRendererInterface $css_collection_renderer,
    AssetCollectionRendererInterface $js_collection_renderer,
    RequestStack $request_stack,
  ) {
    $this->renderer = $renderer;
    $this->componentManager = $component_manager;
    $this->assetResolver = $asset_resolver;
    $this->cssCollectionRenderer = $css_collection_renderer;
    $this->jsCollectionRenderer = $js_collection_renderer;
    $this->requestStack = $request_stack;
  }

  /**
   * Renders a component entity using React.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param string $view_mode
   *   The view mode.
   *
   * @return array
   *   The render array for React rendering.
   */
  public function render(ComponentEntityInterface $entity, $view_mode = 'default') {
    $component_id = 'component-' . $entity->uuid();
    $bundle = $entity->bundle();
    $react_config = $entity->getReactConfig();

    // Extract props and slots.
    $props = $this->extractProps($entity);
    $slots = $this->extractSlots($entity);

    // Build the React wrapper.
    $build = [
      '#theme' => 'component_react_wrapper',
      '#component_id' => $component_id,
      '#component_type' => $bundle,
      '#entity_id' => $entity->id(),
      '#hydration_method' => $react_config['hydration'] ?? 'full',
      '#attributes' => [
        'class' => ['component-react-root'],
        'id' => $component_id,
        'data-component' => $bundle,
        'data-hydration' => $react_config['hydration'] ?? 'full',
      ],
      '#attached' => [
        'library' => [
          'component_entity/react-renderer',
        ],
        'drupalSettings' => [
          'componentEntity' => [
            'components' => [
              $component_id => [
                'type' => $bundle,
                'props' => $props,
                'slots' => $slots,
                'config' => $react_config,
                'entityId' => $entity->id(),
                'viewMode' => $view_mode,
              ],
            ],
          ],
        ],
      ],
      '#cache' => [
        'tags' => $entity->getCacheTags(),
        'contexts' => $entity->getCacheContexts(),
        'max-age' => $entity->getCacheMaxAge(),
      ],
    ];

    // Add component-specific library if it exists.
    $library = "component_entity/component.$bundle";
    if ($this->libraryExists($library)) {
      $build['#attached']['library'][] = $library;
    }

    // Handle SSR if configured.
    if (!empty($react_config['ssr'])) {
      $build['#ssr_content'] = $this->getServerSideRendered($entity, $props, $slots);
    }

    // Handle progressive enhancement.
    if (!empty($react_config['progressive'])) {
      $build['#fallback_content'] = $this->getTwigFallback($entity, $view_mode);
    }

    // Add loading placeholder based on configuration.
    if (!empty($react_config['show_loading'])) {
      $build['#loading_placeholder'] = TRUE;
      $build['#show_spinner'] = $react_config['show_spinner'] ?? TRUE;
    }

    return $build;
  }

  /**
   * Extracts props from entity fields.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   *
   * @return array
   *   Array of props for React component.
   */
  protected function extractProps(ComponentEntityInterface $entity) {
    $props = [];

    foreach ($entity->getFields() as $field_name => $field) {
      // Skip internal fields.
      if (strpos($field_name, 'field_') !== 0) {
        continue;
      }

      // Skip slot fields.
      if (strpos($field_name, '_slot') !== FALSE) {
        continue;
      }

      $prop_name = str_replace('field_', '', $field_name);
      $field_type = $field->getFieldDefinition()->getType();

      // Handle different field types.
      switch ($field_type) {
        case 'json':
          $value = $field->value;
          $props[$prop_name] = json_decode($value, TRUE);
          break;

        case 'boolean':
          $props[$prop_name] = (bool) $field->value;
          break;

        case 'integer':
          $props[$prop_name] = (int) $field->value;
          break;

        case 'decimal':
        case 'float':
          $props[$prop_name] = (float) $field->value;
          break;

        case 'entity_reference':
        case 'entity_reference_revisions':
          $props[$prop_name] = $this->extractEntityReferenceProps($field);
          break;

        case 'image':
          $props[$prop_name] = $this->extractImageProps($field);
          break;

        case 'link':
          $props[$prop_name] = $this->extractLinkProps($field);
          break;

        case 'list_string':
        case 'list_integer':
        case 'list_float':
          $props[$prop_name] = $field->value;
          break;

        case 'text':
        case 'text_long':
        case 'text_with_summary':
          $props[$prop_name] = $this->extractTextProps($field);
          break;

        default:
          // Default to simple value extraction.
          if (!$field->isEmpty()) {
            $props[$prop_name] = $field->value;
          }
          break;
      }
    }

    return $props;
  }

  /**
   * Extracts slots from entity fields.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   *
   * @return array
   *   Array of rendered slot content.
   */
  protected function extractSlots(ComponentEntityInterface $entity) {
    $slots = [];

    foreach ($entity->getFields() as $field_name => $field) {
      // Only process slot fields.
      if (strpos($field_name, 'field_') !== 0 || strpos($field_name, '_slot') === FALSE) {
        continue;
      }

      $slot_name = str_replace(['field_', '_slot'], '', $field_name);

      if (!$field->isEmpty()) {
        // Render the field to HTML.
        $rendered = $field->view();
        $slots[$slot_name] = $this->renderer->renderRoot($rendered);
      }
    }

    return $slots;
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
    foreach ($field as $delta => $item) {
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

    return $field->getFieldDefinition()->getFieldStorageDefinition()->isMultiple() ? $values : $values[0] ?? NULL;
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
    foreach ($field as $delta => $item) {
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

    return $field->getFieldDefinition()->getFieldStorageDefinition()->isMultiple() ? $values : $values[0] ?? NULL;
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
    foreach ($field as $delta => $item) {
      $values[] = [
        'url' => $item->getUrl()->toString(),
        'title' => $item->title,
        'options' => $item->options,
      ];
    }

    return $field->getFieldDefinition()->getFieldStorageDefinition()->isMultiple() ? $values : $values[0] ?? NULL;
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
    foreach ($field as $delta => $item) {
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

    return $field->getFieldDefinition()->getFieldStorageDefinition()->isMultiple() ? $values : $values[0] ?? NULL;
  }

  /**
   * Gets server-side rendered content.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param array $props
   *   Component props.
   * @param array $slots
   *   Component slots.
   *
   * @return string
   *   Server-side rendered HTML.
   */
  protected function getServerSideRendered(ComponentEntityInterface $entity, array $props, array $slots) {
    // This would integrate with a Node.js SSR service.
    // For now, return empty string.
    return '';
  }

  /**
   * Gets Twig fallback content.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param string $view_mode
   *   The view mode.
   *
   * @return array
   *   Twig-rendered fallback content.
   */
  protected function getTwigFallback(ComponentEntityInterface $entity, $view_mode) {
    // Get the SDC component ID.
    $sdc_id = $this->getSdcId($entity->bundle());

    if (!$sdc_id) {
      return [];
    }

    // Build using SDC.
    return [
      '#type' => 'component',
      '#component' => $sdc_id,
      '#props' => $this->extractProps($entity),
      '#slots' => $this->extractSlotsForTwig($entity),
    ];
  }

  /**
   * Extracts slots for Twig rendering.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   *
   * @return array
   *   Array of slot render arrays.
   */
  protected function extractSlotsForTwig(ComponentEntityInterface $entity) {
    $slots = [];

    foreach ($entity->getFields() as $field_name => $field) {
      if (strpos($field_name, 'field_') !== 0 || strpos($field_name, '_slot') === FALSE) {
        continue;
      }

      $slot_name = str_replace(['field_', '_slot'], '', $field_name);

      if (!$field->isEmpty()) {
        $slots[$slot_name] = $field->view();
      }
    }

    return $slots;
  }

  /**
   * Gets the SDC component ID for a bundle.
   *
   * @param string $bundle
   *   The component bundle.
   *
   * @return string|null
   *   The SDC component ID or NULL.
   */
  protected function getSdcId($bundle) {
    $component_type = \Drupal::entityTypeManager()
      ->getStorage('component_type')
      ->load($bundle);

    return $component_type ? $component_type->getSdcId() : NULL;
  }

  /**
   * Checks if a library exists.
   *
   * @param string $library
   *   The library name.
   *
   * @return bool
   *   TRUE if library exists.
   */
  protected function libraryExists($library) {
    [$extension, $name] = explode('/', $library);
    $libraries = \Drupal::service('library.discovery')->getLibrariesByExtension($extension);
    return isset($libraries[$name]);
  }

}
