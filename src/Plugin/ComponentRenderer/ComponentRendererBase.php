<?php

namespace Drupal\component_entity\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\component_entity\Entity\ComponentEntityInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Base class for Component Renderer plugins.
 */
abstract class ComponentRendererBase extends PluginBase implements ComponentRendererInterface, CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   */
  abstract public function render(ComponentEntityInterface $entity, array $context = []);

  /**
   * {@inheritdoc}
   */
  public function supportsSsr() {
    return $this->getPluginDefinition()['supports_ssr'] ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsHydration() {
    return $this->getPluginDefinition()['supports_hydration'] ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsProgressive() {
    return $this->getPluginDefinition()['supports_progressive'] ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->getPluginDefinition()['weight'] ?? 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getFileExtensions() {
    return $this->getPluginDefinition()['file_extensions'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getRequiredLibraries() {
    return $this->getPluginDefinition()['libraries'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled() {
    return $this->getPluginDefinition()['enabled'] ?? TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->getPluginDefinition()['label'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->getPluginDefinition()['description'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getMethod() {
    return $this->getPluginDefinition()['method'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfiguration(array $configuration) {
    $schema = $this->getConfigurationSchema();

    foreach ($schema as $key => $definition) {
      // Check required fields.
      if (isset($definition['required']) && $definition['required']) {
        if (!isset($configuration[$key])) {
          return FALSE;
        }
      }

      // Check types.
      if (isset($configuration[$key]) && isset($definition['type'])) {
        $type = $definition['type'];
        $value = $configuration[$key];

        switch ($type) {
          case 'string':
            if (!is_string($value)) {
              return FALSE;
            }
            break;

          case 'boolean':
            if (!is_bool($value)) {
              return FALSE;
            }
            break;

          case 'integer':
            if (!is_int($value)) {
              return FALSE;
            }
            break;

          case 'array':
            if (!is_array($value)) {
              return FALSE;
            }
            break;
        }
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationSchema() {
    return $this->getPluginDefinition()['config_schema'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(ComponentEntityInterface $entity, array &$build) {
    // Allow plugins to modify the build before rendering.
  }

  /**
   * {@inheritdoc}
   */
  public function postRender(ComponentEntityInterface $entity, array &$build) {
    // Allow plugins to modify the build after rendering.
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = $this->getPluginDefinition()['cache_contexts'] ?? [];

    // Always add theme context for component rendering.
    if (!in_array('theme', $contexts)) {
      $contexts[] = 'theme';
    }

    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(ComponentEntityInterface $entity) {
    $tags = $entity->getCacheTags();

    // Add renderer-specific cache tag.
    $tags[] = 'component_renderer:' . $this->getPluginId();

    // Add bundle-specific tag.
    $tags[] = 'component:' . $entity->bundle();

    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(ComponentEntityInterface $entity) {
    // Default to the entity's cache max age.
    return $entity->getCacheMaxAge();
  }

  /**
   * Gets the default configuration for this plugin.
   *
   * @return array
   *   The default configuration.
   */
  public function defaultConfiguration() {
    $defaults = [];
    $schema = $this->getConfigurationSchema();

    foreach ($schema as $key => $definition) {
      if (isset($definition['default'])) {
        $defaults[$key] = $definition['default'];
      }
    }

    return $defaults;
  }

  /**
   * Gets the current configuration.
   *
   * @return array
   *   The current configuration.
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * Sets the configuration.
   *
   * @param array $configuration
   *   The configuration to set.
   *
   * @return $this
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
    return $this;
  }

}
