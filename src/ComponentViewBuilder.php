<?php

namespace Drupal\component_entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\component_entity\Entity\ComponentEntityInterface;

/**
 * View builder for component entities with dual rendering support.
 */
class ComponentViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildComponents(array &$build, array $entities, array $displays, $view_mode) {
    parent::buildComponents($build, $entities, $displays, $view_mode);

    foreach ($entities as $id => $entity) {
      if (!$entity instanceof ComponentEntityInterface) {
        continue;
      }

      // Determine render method.
      $render_method = $entity->getRenderMethod();

      // Build the component based on render method.
      if ($render_method === 'react') {
        $this->buildReactComponent($build[$id], $entity, $displays[$entity->bundle()], $view_mode);
      }
      else {
        $this->buildTwigComponent($build[$id], $entity, $displays[$entity->bundle()], $view_mode);
      }

      // Add cache metadata.
      $this->addCacheMetadata($build[$id], $entity);
    }
  }

  /**
   * Builds a component for Twig rendering (standard SDC).
   *
   * @param array &$build
   *   The build array.
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   The entity view display.
   * @param string $view_mode
   *   The view mode.
   */
  protected function buildTwigComponent(array &$build, ComponentEntityInterface $entity, EntityViewDisplayInterface $display, $view_mode) {
    $sdc_id = $this->getSdcId($entity->bundle());

    if (!$sdc_id) {
      $build['#markup'] = $this->t('SDC component not found for bundle @bundle', [
        '@bundle' => $entity->bundle(),
      ]);
      return;
    }

    // Extract props and slots.
    $props = $this->extractProps($entity, $display);
    $slots = $this->extractSlots($entity, $display);

    // Build the SDC component render array.
    $component_build = [
      '#type' => 'component',
      '#component' => $sdc_id,
      '#props' => $props,
      '#slots' => $slots,
      '#attributes' => [
        'class' => [
          'component',
          'component--' . str_replace('_', '-', $entity->bundle()),
          'component--view-mode-' . str_replace('_', '-', $view_mode),
        ],
        'data-component-id' => $entity->id(),
        'data-component-uuid' => $entity->uuid(),
      ],
    ];

    // Merge with existing build.
    $build = array_merge($build, $component_build);

    // Add component-specific libraries.
    $this->attachComponentLibraries($build, $entity);
  }

  /**
   * Builds a component for React rendering.
   *
   * @param array &$build
   *   The build array.
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   The entity view display.
   * @param string $view_mode
   *   The view mode.
   */
  protected function buildReactComponent(array &$build, ComponentEntityInterface $entity, EntityViewDisplayInterface $display, $view_mode) {
    // Use the React renderer service.
    $react_renderer = \Drupal::service('component_entity.react_renderer');
    $react_build = $react_renderer->render($entity, $view_mode);

    // Merge with existing build.
    $build = array_merge($build, $react_build);

    // Ensure React libraries are attached.
    $build['#attached']['library'][] = 'component_entity/react-renderer';

    // Add component-specific React library if exists.
    $bundle = $entity->bundle();
    $component_library = "component_entity/component.$bundle";
    if ($this->libraryExists($component_library)) {
      $build['#attached']['library'][] = $component_library;
    }
  }

  /**
   * Extracts props from entity fields.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   The entity view display.
   *
   * @return array
   *   Array of props for the component.
   */
  protected function extractProps(ComponentEntityInterface $entity, EntityViewDisplayInterface $display) {
    $props = [];

    foreach ($entity->getFields() as $field_name => $field) {
      // Skip non-field properties.
      if (strpos($field_name, 'field_') !== 0) {
        continue;
      }

      // Skip slot fields.
      if (strpos($field_name, '_slot') !== FALSE) {
        continue;
      }

      // Skip hidden fields.
      if (!$display->getComponent($field_name)) {
        continue;
      }

      // Convert field name to prop name.
      $prop_name = str_replace('field_', '', $field_name);

      // Get field value based on type.
      $field_type = $field->getFieldDefinition()->getType();

      switch ($field_type) {
        case 'json':
          $props[$prop_name] = json_decode($field->value, TRUE);
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
          $props[$prop_name] = $this->extractEntityReferenceValue($field);
          break;

        case 'image':
          $props[$prop_name] = $this->extractImageValue($field);
          break;

        case 'link':
          $props[$prop_name] = $this->extractLinkValue($field);
          break;

        case 'text':
        case 'text_long':
        case 'text_with_summary':
          $props[$prop_name] = $this->extractTextValue($field);
          break;

        default:
          // Simple value extraction.
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
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   The entity view display.
   *
   * @return array
   *   Array of slots for the component.
   */
  protected function extractSlots(ComponentEntityInterface $entity, EntityViewDisplayInterface $display) {
    $slots = [];

    foreach ($entity->getFields() as $field_name => $field) {
      // Only process slot fields.
      if (strpos($field_name, 'field_') !== 0 || strpos($field_name, '_slot') === FALSE) {
        continue;
      }

      // Skip hidden fields.
      if (!$display->getComponent($field_name)) {
        continue;
      }

      // Extract slot name.
      $slot_name = str_replace(['field_', '_slot'], '', $field_name);

      if (!$field->isEmpty()) {
        // Build the field view.
        $field_build = $field->view($display->getComponent($field_name));

        // Check field type for special handling.
        $field_type = $field->getFieldDefinition()->getType();

        if ($field_type === 'entity_reference' || $field_type === 'entity_reference_revisions') {
          // For entity references, render the referenced entities.
          $slots[$slot_name] = $field_build;
        }
        else {
          // For other fields, wrap in appropriate markup.
          $slots[$slot_name] = [
            '#theme' => 'field',
            '#field_name' => $field_name,
            '#field_type' => $field_type,
            '#label_display' => 'hidden',
            '#items' => $field,
            '#formatter' => $display->getComponent($field_name)['type'] ?? 'default',
            0 => $field_build,
          ];
        }
      }
    }

    return $slots;
  }

  /**
   * Extracts value from entity reference field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field item list.
   *
   * @return mixed
   *   The extracted value.
   */
  protected function extractEntityReferenceValue($field) {
    if ($field->isEmpty()) {
      return NULL;
    }

    $values = [];
    foreach ($field as $item) {
      if ($entity = $item->entity) {
        // Return basic entity data.
        $values[] = [
          'id' => $entity->id(),
          'label' => $entity->label(),
          'type' => $entity->getEntityTypeId(),
          'bundle' => $entity->bundle(),
        ];
      }
    }

    return $field->getFieldDefinition()->getFieldStorageDefinition()->isMultiple()
      ? $values
      : ($values[0] ?? NULL);
  }

  /**
   * Extracts value from image field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field item list.
   *
   * @return mixed
   *   The extracted value.
   */
  protected function extractImageValue($field) {
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

    return $field->getFieldDefinition()->getFieldStorageDefinition()->isMultiple()
      ? $values
      : ($values[0] ?? NULL);
  }

  /**
   * Extracts value from link field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field item list.
   *
   * @return mixed
   *   The extracted value.
   */
  protected function extractLinkValue($field) {
    if ($field->isEmpty()) {
      return NULL;
    }

    $values = [];
    foreach ($field as $item) {
      $values[] = [
        'url' => $item->getUrl()->toString(),
        'title' => $item->title,
      ];
    }

    return $field->getFieldDefinition()->getFieldStorageDefinition()->isMultiple()
      ? $values
      : ($values[0] ?? NULL);
  }

  /**
   * Extracts value from text field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field item list.
   *
   * @return mixed
   *   The extracted value.
   */
  protected function extractTextValue($field) {
    if ($field->isEmpty()) {
      return NULL;
    }

    $values = [];
    foreach ($field as $item) {
      // For formatted text, return processed value.
      if (isset($item->format)) {
        $build = [
          '#type' => 'processed_text',
          '#text' => $item->value,
          '#format' => $item->format,
        ];
        $values[] = \Drupal::service('renderer')->renderRoot($build);
      }
      else {
        $values[] = $item->value;
      }
    }

    return $field->getFieldDefinition()->getFieldStorageDefinition()->isMultiple()
      ? $values
      : ($values[0] ?? NULL);
  }

  /**
   * Gets the SDC component ID for a bundle.
   *
   * @param string $bundle
   *   The component bundle.
   *
   * @return string|null
   *   The SDC component ID or NULL if not found.
   */
  protected function getSdcId($bundle) {
    $component_type = \Drupal::entityTypeManager()
      ->getStorage('component_type')
      ->load($bundle);

    return $component_type ? $component_type->get('sdc_id') : NULL;
  }

  /**
   * Adds cache metadata to the build.
   *
   * @param array &$build
   *   The build array.
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   */
  protected function addCacheMetadata(array &$build, ComponentEntityInterface $entity) {
    // Use cache manager to get comprehensive cache metadata.
    $cache_manager = \Drupal::service('component_entity.cache_manager');
    $cache_metadata = $cache_manager->getCacheMetadata($entity);

    // Apply cache metadata to build.
    $cache_metadata->applyTo($build);

    // Add render method specific cache context.
    $build['#cache']['contexts'][] = 'component_render_method';
  }

  /**
   * Attaches component-specific libraries.
   *
   * @param array &$build
   *   The build array.
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   */
  protected function attachComponentLibraries(array &$build, ComponentEntityInterface $entity) {
    $bundle = $entity->bundle();

    // Attach CSS if exists.
    $css_library = "component_entity/component.$bundle.css";
    if ($this->libraryExists($css_library)) {
      $build['#attached']['library'][] = $css_library;
    }

    // Attach JavaScript if exists (for Twig components with JS).
    $js_library = "component_entity/component.$bundle.js";
    if ($this->libraryExists($js_library)) {
      $build['#attached']['library'][] = $js_library;
    }
  }

  /**
   * Checks if a library exists.
   *
   * @param string $library
   *   The library name in format "extension/name".
   *
   * @return bool
   *   TRUE if the library exists, FALSE otherwise.
   */
  protected function libraryExists($library) {
    [$extension, $name] = explode('/', $library);
    $libraries = \Drupal::service('library.discovery')->getLibrariesByExtension($extension);
    return isset($libraries[$name]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getBuildDefaults(EntityInterface $entity, $view_mode) {
    $defaults = parent::getBuildDefaults($entity, $view_mode);

    // Add component-specific theme suggestions.
    $defaults['#theme'] = 'component_entity';

    // Add render method as a theme hook suggestion.
    if ($entity instanceof ComponentEntityInterface) {
      $render_method = $entity->getRenderMethod();
      $bundle = $entity->bundle();

      $defaults['#theme'] = [
        'component_entity__' . $bundle . '__' . $view_mode . '__' . $render_method,
        'component_entity__' . $bundle . '__' . $render_method,
        'component_entity__' . $view_mode . '__' . $render_method,
        'component_entity__' . $bundle . '__' . $view_mode,
        'component_entity__' . $bundle,
        'component_entity__' . $render_method,
        'component_entity',
      ];
    }

    return $defaults;
  }

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $entity, $view_mode = 'full', $langcode = NULL) {
    $build = parent::view($entity, $view_mode, $langcode);

    // Add contextual links.
    if ($entity instanceof ComponentEntityInterface && $entity->access('update')) {
      $build['#contextual_links']['component'] = [
        'route_parameters' => ['component' => $entity->id()],
        'metadata' => ['changed' => $entity->getChangedTime()],
      ];
    }

    return $build;
  }

}
