<?php

namespace Drupal\component_entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\component_entity\Entity\ComponentEntityInterface;

/**
 * Manages caching for component entities.
 */
class ComponentCacheManager {

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a ComponentCacheManager object.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    CacheBackendInterface $cache_backend,
    CacheTagsInvalidatorInterface $cache_tags_invalidator,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->cacheBackend = $cache_backend;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Gets cache metadata for a component entity.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   The cache metadata.
   */
  public function getCacheMetadata(ComponentEntityInterface $entity) {
    $metadata = new CacheableMetadata();
    
    // Add base entity cache tags.
    $metadata->addCacheTags($entity->getCacheTags());
    
    // Add bundle-specific cache tag.
    $metadata->addCacheTags(['component_type:' . $entity->bundle()]);
    
    // Add render-method-specific cache tag.
    $render_method = $entity->getRenderMethod();
    $metadata->addCacheTags(['component_render:' . $render_method]);
    
    // Add standard cache contexts.
    $metadata->addCacheContexts(['user.permissions']);
    
    // React components might need different caching.
    if ($render_method === 'react') {
      // Add session context for React state.
      $metadata->addCacheContexts(['session']);
      
      // Check if component has interactive features.
      $react_config = $entity->getReactConfig();
      if (!empty($react_config['progressive']) || $react_config['hydration'] === 'partial') {
        // For progressive enhancement or partial hydration, vary by browser.
        $metadata->addCacheContexts(['headers:User-Agent']);
      }
    }
    
    // Add field-specific cache metadata.
    foreach ($entity->getFields() as $field_name => $field) {
      if ($field->getFieldDefinition()->isDisplayConfigurable('view')) {
        $field_metadata = CacheableMetadata::createFromRenderArray($field->view());
        $metadata = $metadata->merge($field_metadata);
      }
    }
    
    // Allow other modules to alter cache metadata.
    \Drupal::moduleHandler()->alter('component_entity_cache_metadata', $metadata, $entity);
    
    return $metadata;
  }

  /**
   * Caches rendered component output.
   *
   * @param string $cid
   *   The cache ID.
   * @param array $render_array
   *   The render array to cache.
   * @param \Drupal\Core\Cache\CacheableMetadata $metadata
   *   The cache metadata.
   * @param int $expire
   *   The cache expiration time.
   */
  public function cacheRenderedComponent($cid, array $render_array, CacheableMetadata $metadata, $expire = Cache::PERMANENT) {
    $this->cacheBackend->set($cid, $render_array, $expire, $metadata->getCacheTags());
  }

  /**
   * Gets cached rendered component.
   *
   * @param string $cid
   *   The cache ID.
   *
   * @return array|false
   *   The cached render array or FALSE if not found.
   */
  public function getCachedComponent($cid) {
    $cache = $this->cacheBackend->get($cid);
    return $cache ? $cache->data : FALSE;
  }

  /**
   * Generates a cache ID for a component.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param string $view_mode
   *   The view mode.
   * @param string $langcode
   *   The language code.
   *
   * @return string
   *   The cache ID.
   */
  public function generateCacheId(ComponentEntityInterface $entity, $view_mode = 'default', $langcode = NULL) {
    $langcode = $langcode ?: $entity->language()->getId();
    $render_method = $entity->getRenderMethod();
    
    $parts = [
      'component',
      $entity->id(),
      $entity->getRevisionId(),
      $view_mode,
      $langcode,
      $render_method,
    ];
    
    // Add React-specific cache key parts.
    if ($render_method === 'react') {
      $react_config = $entity->getReactConfig();
      $parts[] = $react_config['hydration'] ?? 'full';
      $parts[] = !empty($react_config['progressive']) ? 'prog' : 'std';
    }
    
    return implode(':', $parts);
  }

  /**
   * Invalidates component caches.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   */
  public function invalidateComponentCache(ComponentEntityInterface $entity) {
    $tags = [
      'component:' . $entity->id(),
      'component_list',
      'component_type:' . $entity->bundle(),
    ];
    
    $this->cacheTagsInvalidator->invalidateTags($tags);
  }

  /**
   * Invalidates all component caches for a specific bundle.
   *
   * @param string $bundle
   *   The component bundle.
   */
  public function invalidateBundleCache($bundle) {
    $tags = [
      'component_type:' . $bundle,
      'component_list:' . $bundle,
    ];
    
    $this->cacheTagsInvalidator->invalidateTags($tags);
  }

  /**
   * Invalidates caches for a specific render method.
   *
   * @param string $render_method
   *   The render method ('twig' or 'react').
   */
  public function invalidateRenderMethodCache($render_method) {
    $tags = ['component_render:' . $render_method];
    $this->cacheTagsInvalidator->invalidateTags($tags);
  }

  /**
   * Gets cache contexts for a component based on its configuration.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   *
   * @return array
   *   Array of cache contexts.
   */
  public function getCacheContexts(ComponentEntityInterface $entity) {
    $contexts = ['user.permissions'];
    
    // Add language context if translatable.
    if ($entity->isTranslatable()) {
      $contexts[] = 'languages:language_content';
    }
    
    // Add theme context if component has theme-specific variations.
    $component_type = $this->entityTypeManager
      ->getStorage('component_type')
      ->load($entity->bundle());
    
    if ($component_type && $component_type->hasThemeVariations()) {
      $contexts[] = 'theme';
    }
    
    // Add route context for components that vary by page.
    if ($this->componentVariesByRoute($entity)) {
      $contexts[] = 'route';
    }
    
    return $contexts;
  }

  /**
   * Determines if a component varies by route.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   *
   * @return bool
   *   TRUE if component varies by route.
   */
  protected function componentVariesByRoute(ComponentEntityInterface $entity) {
    // Check if component has contextual fields.
    foreach ($entity->getFields() as $field_name => $field) {
      $field_type = $field->getFieldDefinition()->getType();
      
      // Entity reference fields might pull contextual data.
      if (in_array($field_type, ['entity_reference', 'entity_reference_revisions'])) {
        $settings = $field->getFieldDefinition()->getSettings();
        if (!empty($settings['handler_settings']['contextual'])) {
          return TRUE;
        }
      }
    }
    
    return FALSE;
  }

  /**
   * Warms the cache for a component.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param array $view_modes
   *   Array of view modes to warm.
   */
  public function warmCache(ComponentEntityInterface $entity, array $view_modes = ['default', 'full', 'teaser']) {
    $view_builder = $this->entityTypeManager->getViewBuilder('component');
    
    foreach ($view_modes as $view_mode) {
      // Build the render array.
      $build = $view_builder->view($entity, $view_mode);
      
      // Generate cache ID.
      $cid = $this->generateCacheId($entity, $view_mode);
      
      // Get cache metadata.
      $metadata = $this->getCacheMetadata($entity);
      
      // Cache the rendered output.
      $this->cacheRenderedComponent($cid, $build, $metadata);
    }
  }

  /**
   * Clears all component caches.
   */
  public function clearAllComponentCaches() {
    $this->cacheTagsInvalidator->invalidateTags(['component_list']);
    $this->cacheBackend->deleteAll();
  }

  /**
   * Gets cache statistics for monitoring.
   *
   * @return array
   *   Array of cache statistics.
   */
  public function getCacheStatistics() {
    $stats = [
      'total_components' => 0,
      'cached_components' => 0,
      'cache_hit_rate' => 0,
      'by_render_method' => [
        'twig' => 0,
        'react' => 0,
      ],
    ];
    
    // This would typically integrate with a monitoring system.
    // For now, return basic stats.
    
    $component_storage = $this->entityTypeManager->getStorage('component');
    $stats['total_components'] = $component_storage->getQuery()->count()->execute();
    
    return $stats;
  }

}