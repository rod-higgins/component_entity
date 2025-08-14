<?php

namespace Drupal\component_entity\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\component_entity\Entity\ComponentEntityInterface;

/**
 * Event fired when a component is rendered.
 *
 * This event allows other modules to alter the render array
 * or add additional processing before or after rendering.
 */
class ComponentRenderEvent extends Event {

  /**
   * Event fired before a component is rendered.
   *
   * @var string
   */
  const PRE_RENDER = 'component_entity.pre_render';

  /**
   * Event fired after a component is rendered.
   *
   * @var string
   */
  const POST_RENDER = 'component_entity.post_render';

  /**
   * Event fired when render method is determined.
   *
   * @var string
   */
  const RENDER_METHOD_ALTER = 'component_entity.render_method_alter';

  /**
   * The component entity being rendered.
   *
   * @var \Drupal\component_entity\Entity\ComponentEntityInterface
   */
  protected $component;

  /**
   * The render array.
   *
   * @var array
   */
  protected $renderArray;

  /**
   * The view mode.
   *
   * @var string
   */
  protected $viewMode;

  /**
   * The render method (twig or react).
   *
   * @var string
   */
  protected $renderMethod;

  /**
   * Additional context data.
   *
   * @var array
   */
  protected $context;

  /**
   * Constructs a ComponentRenderEvent object.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $component
   *   The component entity being rendered.
   * @param array $render_array
   *   The render array.
   * @param string $view_mode
   *   The view mode.
   * @param string $render_method
   *   The render method.
   * @param array $context
   *   Additional context data.
   */
  public function __construct(ComponentEntityInterface $component, array $render_array, $view_mode = 'default', $render_method = 'twig', array $context = []) {
    $this->component = $component;
    $this->renderArray = $render_array;
    $this->viewMode = $view_mode;
    $this->renderMethod = $render_method;
    $this->context = $context;
  }

  /**
   * Gets the component entity.
   *
   * @return \Drupal\component_entity\Entity\ComponentEntityInterface
   *   The component entity.
   */
  public function getComponent() {
    return $this->component;
  }

  /**
   * Gets the render array.
   *
   * @return array
   *   The render array.
   */
  public function getRenderArray() {
    return $this->renderArray;
  }

  /**
   * Sets the render array.
   *
   * @param array $render_array
   *   The render array.
   *
   * @return $this
   */
  public function setRenderArray(array $render_array) {
    $this->renderArray = $render_array;
    return $this;
  }

  /**
   * Gets the view mode.
   *
   * @return string
   *   The view mode.
   */
  public function getViewMode() {
    return $this->viewMode;
  }

  /**
   * Gets the render method.
   *
   * @return string
   *   The render method (twig or react).
   */
  public function getRenderMethod() {
    return $this->renderMethod;
  }

  /**
   * Sets the render method.
   *
   * @param string $render_method
   *   The render method.
   *
   * @return $this
   */
  public function setRenderMethod($render_method) {
    if (!in_array($render_method, ['twig', 'react'])) {
      throw new \InvalidArgumentException('Render method must be either "twig" or "react".');
    }
    $this->renderMethod = $render_method;
    return $this;
  }

  /**
   * Gets the context data.
   *
   * @return array
   *   The context data.
   */
  public function getContext() {
    return $this->context;
  }

  /**
   * Sets a context value.
   *
   * @param string $key
   *   The context key.
   * @param mixed $value
   *   The context value.
   *
   * @return $this
   */
  public function setContextValue($key, $value) {
    $this->context[$key] = $value;
    return $this;
  }

  /**
   * Gets a specific context value.
   *
   * @param string $key
   *   The context key.
   * @param mixed $default
   *   Default value if key doesn't exist.
   *
   * @return mixed
   *   The context value.
   */
  public function getContextValue($key, $default = NULL) {
    return $this->context[$key] ?? $default;
  }

  /**
   * Checks if the component is being rendered for preview.
   *
   * @return bool
   *   TRUE if this is a preview render.
   */
  public function isPreview() {
    return $this->getContextValue('preview', FALSE);
  }

  /**
   * Gets the component bundle.
   *
   * @return string
   *   The component bundle.
   */
  public function getComponentBundle() {
    return $this->component->bundle();
  }

  /**
   * Gets the component ID.
   *
   * @return int|string|null
   *   The component ID.
   */
  public function getComponentId() {
    return $this->component->id();
  }

}
