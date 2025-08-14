<?php

namespace Drupal\component_entity\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Component Renderer plugin annotation object.
 *
 * Plugin namespace: Plugin\ComponentRenderer.
 *
 * @see \Drupal\component_entity\Plugin\ComponentRendererManager
 * @see plugin_api
 *
 * @Annotation
 */
class ComponentRenderer extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the renderer.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A brief description of the renderer.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * The rendering method type (e.g., 'twig', 'react', 'vue').
   *
   * @var string
   */
  public $method;

  /**
   * Whether this renderer supports server-side rendering.
   *
   * @var bool
   */
  public $supports_ssr = FALSE;

  /**
   * Whether this renderer supports client-side hydration.
   *
   * @var bool
   */
  public $supports_hydration = FALSE;

  /**
   * Whether this renderer supports progressive enhancement.
   *
   * @var bool
   */
  public $supports_progressive = FALSE;

  /**
   * Priority weight for this renderer.
   *
   * Higher values mean higher priority when multiple renderers are available.
   *
   * @var int
   */
  public $weight = 0;

  /**
   * File extensions this renderer can handle.
   *
   * @var array
   */
  public $file_extensions = [];

  /**
   * Required libraries for this renderer.
   *
   * @var array
   */
  public $libraries = [];

  /**
   * Cache contexts required by this renderer.
   *
   * @var array
   */
  public $cache_contexts = [];

  /**
   * Whether this renderer is enabled by default.
   *
   * @var bool
   */
  public $enabled = TRUE;

  /**
   * Configuration schema for this renderer.
   *
   * @var array
   */
  public $config_schema = [];

}
