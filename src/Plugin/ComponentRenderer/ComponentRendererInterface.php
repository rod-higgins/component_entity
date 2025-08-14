<?php

namespace Drupal\component_entity\Plugin;

use Drupal\component_entity\Entity\ComponentEntityInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for Component Renderer plugins.
 */
interface ComponentRendererInterface extends PluginInspectionInterface {

  /**
   * Renders a component entity.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity to render.
   * @param array $context
   *   Additional context for rendering, including:
   *   - view_mode: The view mode to use.
   *   - langcode: The language code.
   *   - additional_props: Additional properties to pass.
   *   - slots: Slot content to pass.
   *   - variables: Additional variables for templates.
   *
   * @return array
   *   The render array.
   */
  public function render(ComponentEntityInterface $entity, array $context = []);

  /**
   * Checks if this renderer supports server-side rendering.
   *
   * @return bool
   *   TRUE if SSR is supported, FALSE otherwise.
   */
  public function supportsSsr();

  /**
   * Checks if this renderer supports client-side hydration.
   *
   * @return bool
   *   TRUE if hydration is supported, FALSE otherwise.
   */
  public function supportsHydration();

  /**
   * Checks if this renderer supports progressive enhancement.
   *
   * @return bool
   *   TRUE if progressive enhancement is supported, FALSE otherwise.
   */
  public function supportsProgressive();

  /**
   * Gets the weight of this renderer.
   *
   * @return int
   *   The weight value. Higher values mean higher priority.
   */
  public function getWeight();

  /**
   * Gets the file extensions this renderer can handle.
   *
   * @return array
   *   Array of file extensions (without dots).
   */
  public function getFileExtensions();

  /**
   * Gets the required libraries for this renderer.
   *
   * @return array
   *   Array of library names.
   */
  public function getRequiredLibraries();

  /**
   * Checks if this renderer is enabled.
   *
   * @return bool
   *   TRUE if enabled, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Gets the human-readable label of this renderer.
   *
   * @return string
   *   The label.
   */
  public function getLabel();

  /**
   * Gets the description of this renderer.
   *
   * @return string
   *   The description.
   */
  public function getDescription();

  /**
   * Gets the rendering method type.
   *
   * @return string
   *   The method (e.g., 'twig', 'react', 'vue').
   */
  public function getMethod();

  /**
   * Validates configuration for this renderer.
   *
   * @param array $configuration
   *   The configuration to validate.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  public function validateConfiguration(array $configuration);

  /**
   * Gets the configuration schema for this renderer.
   *
   * @return array
   *   The configuration schema definition.
   */
  public function getConfigurationSchema();

  /**
   * Pre-render hook for modifying the build array.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param array $build
   *   The render array to modify.
   */
  public function preRender(ComponentEntityInterface $entity, array &$build);

  /**
   * Post-render hook for modifying the build array.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param array $build
   *   The render array to modify.
   */
  public function postRender(ComponentEntityInterface $entity, array &$build);

  /**
   * Gets the cache contexts for this renderer.
   *
   * @return array
   *   Array of cache contexts.
   */
  public function getCacheContexts();

  /**
   * Gets the cache tags for a rendered entity.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   *
   * @return array
   *   Array of cache tags.
   */
  public function getCacheTags(ComponentEntityInterface $entity);

  /**
   * Gets the cache max age for a rendered entity.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   *
   * @return int
   *   The cache max age in seconds.
   */
  public function getCacheMaxAge(ComponentEntityInterface $entity);

}
