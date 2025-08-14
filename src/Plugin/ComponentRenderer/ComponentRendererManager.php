<?php

namespace Drupal\component_entity\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\component_entity\Entity\ComponentEntityInterface;

/**
 * Provides the Component Renderer plugin manager.
 */
class ComponentRendererManager extends DefaultPluginManager {

  /**
   * Constructs a new ComponentRendererManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/ComponentRenderer',
      $namespaces,
      $module_handler,
      'Drupal\component_entity\Plugin\ComponentRendererInterface',
      'Drupal\component_entity\Annotation\ComponentRenderer'
    );

    $this->alterInfo('component_renderer_info');
    $this->setCacheBackend($cache_backend, 'component_renderer_plugins');
  }

  /**
   * Gets the appropriate renderer for a component entity.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param string|null $preferred_method
   *   Optional preferred rendering method.
   *
   * @return \Drupal\component_entity\Plugin\ComponentRendererInterface|null
   *   The renderer plugin instance, or NULL if none found.
   */
  public function getRenderer(ComponentEntityInterface $entity, $preferred_method = NULL) {
    // If a preferred method is specified, try to use it.
    if ($preferred_method) {
      $renderer = $this->getRendererByMethod($preferred_method);
      if ($renderer && $renderer->isEnabled()) {
        return $renderer;
      }
    }

    // Get the entity's configured render method.
    $render_method = $entity->getRenderMethod();
    if ($render_method) {
      $renderer = $this->getRendererByMethod($render_method);
      if ($renderer && $renderer->isEnabled()) {
        return $renderer;
      }
    }

    // Fall back to the highest weighted enabled renderer.
    return $this->getDefaultRenderer();
  }

  /**
   * Gets a renderer by its method type.
   *
   * @param string $method
   *   The rendering method (e.g., 'twig', 'react').
   *
   * @return \Drupal\component_entity\Plugin\ComponentRendererInterface|null
   *   The renderer plugin instance, or NULL if not found.
   */
  public function getRendererByMethod($method) {
    $definitions = $this->getDefinitions();

    foreach ($definitions as $plugin_id => $definition) {
      if (isset($definition['method']) && $definition['method'] === $method) {
        return $this->createInstance($plugin_id);
      }
    }

    return NULL;
  }

  /**
   * Gets the default renderer based on weight.
   *
   * @return \Drupal\component_entity\Plugin\ComponentRendererInterface|null
   *   The default renderer plugin instance, or NULL if none found.
   */
  public function getDefaultRenderer() {
    $definitions = $this->getDefinitions();

    // Sort by weight (higher weight = higher priority).
    uasort($definitions, function ($a, $b) {
      $weight_a = $a['weight'] ?? 0;
      $weight_b = $b['weight'] ?? 0;
      return $weight_b - $weight_a;
    });

    // Return the first enabled renderer.
    foreach ($definitions as $plugin_id => $definition) {
      if ($definition['enabled'] ?? TRUE) {
        return $this->createInstance($plugin_id);
      }
    }

    return NULL;
  }

  /**
   * Gets all available renderers.
   *
   * @param bool $only_enabled
   *   Whether to return only enabled renderers.
   *
   * @return \Drupal\component_entity\Plugin\ComponentRendererInterface[]
   *   Array of renderer plugin instances.
   */
  public function getAllRenderers($only_enabled = TRUE) {
    $renderers = [];
    $definitions = $this->getDefinitions();

    foreach ($definitions as $plugin_id => $definition) {
      if (!$only_enabled || ($definition['enabled'] ?? TRUE)) {
        $renderers[$plugin_id] = $this->createInstance($plugin_id);
      }
    }

    return $renderers;
  }

  /**
   * Gets renderers that support a specific feature.
   *
   * @param string $feature
   *   The feature to check for (e.g., 'ssr', 'hydration', 'progressive').
   *
   * @return \Drupal\component_entity\Plugin\ComponentRendererInterface[]
   *   Array of renderer plugin instances that support the feature.
   */
  public function getRenderersWithFeature($feature) {
    $renderers = [];
    $definitions = $this->getDefinitions();

    $feature_key = 'supports_' . $feature;

    foreach ($definitions as $plugin_id => $definition) {
      if (isset($definition[$feature_key]) && $definition[$feature_key]) {
        if ($definition['enabled'] ?? TRUE) {
          $renderers[$plugin_id] = $this->createInstance($plugin_id);
        }
      }
    }

    return $renderers;
  }

  /**
   * Gets renderers that can handle a specific file extension.
   *
   * @param string $extension
   *   The file extension (without dot).
   *
   * @return \Drupal\component_entity\Plugin\ComponentRendererInterface[]
   *   Array of renderer plugin instances that can handle the extension.
   */
  public function getRenderersForExtension($extension) {
    $renderers = [];
    $definitions = $this->getDefinitions();

    foreach ($definitions as $plugin_id => $definition) {
      if (isset($definition['file_extensions']) &&
          in_array($extension, $definition['file_extensions'])) {
        if ($definition['enabled'] ?? TRUE) {
          $renderers[$plugin_id] = $this->createInstance($plugin_id);
        }
      }
    }

    return $renderers;
  }

  /**
   * Gets renderer options for form select elements.
   *
   * @param bool $only_enabled
   *   Whether to include only enabled renderers.
   *
   * @return array
   *   Array of options keyed by method with labels as values.
   */
  public function getRendererOptions($only_enabled = TRUE) {
    $options = [];
    $definitions = $this->getDefinitions();

    foreach ($definitions as $plugin_id => $definition) {
      if (!$only_enabled || ($definition['enabled'] ?? TRUE)) {
        $method = $definition['method'] ?? $plugin_id;
        $label = $definition['label'] ?? $plugin_id;
        $options[$method] = $label;
      }
    }

    return $options;
  }

  /**
   * Validates if a renderer can handle a component entity.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param string $renderer_id
   *   The renderer plugin ID.
   *
   * @return bool
   *   TRUE if the renderer can handle the entity, FALSE otherwise.
   */
  public function canHandle(ComponentEntityInterface $entity, $renderer_id) {
    if (!$this->hasDefinition($renderer_id)) {
      return FALSE;
    }

    $renderer = $this->createInstance($renderer_id);

    // Check if renderer is enabled.
    if (!$renderer->isEnabled()) {
      return FALSE;
    }

    // Additional validation can be added here.
    // For example, checking if the component has required fields
    // or if the SDC component exists for the entity.
    return TRUE;
  }

  /**
   * Clears the renderer plugin cache.
   */
  public function clearCachedDefinitions() {
    parent::clearCachedDefinitions();

    // Also clear any renderer-specific caches.
    \Drupal::cache()->invalidate('component_renderer:definitions');
  }

}
