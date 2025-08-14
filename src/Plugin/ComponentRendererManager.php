<?php

namespace Drupal\component_entity\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\component_entity\Entity\ComponentEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Component Renderer plugin manager.
 */
class ComponentRendererManager extends DefaultPluginManager {

  /**
   * The cache tags invalidator service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

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
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator service.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, CacheTagsInvalidatorInterface $cache_tags_invalidator) {
    parent::__construct(
      'Plugin/ComponentRenderer',
      $namespaces,
      $module_handler,
      'Drupal\component_entity\Plugin\ComponentRendererInterface',
      'Drupal\component_entity\Annotation\ComponentRenderer'
    );

    $this->alterInfo('component_renderer_info');
    $this->setCacheBackend($cache_backend, 'component_renderer_plugins');
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('container.namespaces'),
      $container->get('cache.discovery'),
      $container->get('module_handler'),
      $container->get('cache_tags.invalidator')
    );
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
      if (($definition['method'] ?? '') === $method) {
        if ($definition['enabled'] ?? TRUE) {
          return $this->createInstance($plugin_id);
        }
      }
    }

    return NULL;
  }

  /**
   * Gets the default renderer (highest weighted enabled renderer).
   *
   * @return \Drupal\component_entity\Plugin\ComponentRendererInterface|null
   *   The default renderer plugin instance, or NULL if none found.
   */
  public function getDefaultRenderer() {
    $definitions = $this->getDefinitions();

    // Sort by weight.
    uasort($definitions, function ($a, $b) {
      $a_weight = $a['weight'] ?? 0;
      $b_weight = $b['weight'] ?? 0;
      return $a_weight <=> $b_weight;
    });

    foreach ($definitions as $plugin_id => $definition) {
      if ($definition['enabled'] ?? TRUE) {
        return $this->createInstance($plugin_id);
      }
    }

    return NULL;
  }

  /**
   * Gets all enabled renderers.
   *
   * @return \Drupal\component_entity\Plugin\ComponentRendererInterface[]
   *   Array of enabled renderer plugin instances.
   */
  public function getEnabledRenderers() {
    $renderers = [];
    $definitions = $this->getDefinitions();

    foreach ($definitions as $plugin_id => $definition) {
      if ($definition['enabled'] ?? TRUE) {
        $renderers[$plugin_id] = $this->createInstance($plugin_id);
      }
    }

    return $renderers;
  }

  /**
   * Gets renderers that support a specific feature.
   *
   * @param string $feature
   *   The feature to check (e.g., 'supports_ssr', 'supports_hydration').
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

    // Also clear any renderer-specific caches using dependency injection.
    $this->cacheTagsInvalidator->invalidateTags(['component_renderer:definitions']);
  }

}
