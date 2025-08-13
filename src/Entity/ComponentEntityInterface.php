<?php

namespace Drupal\component_entity\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Component entities.
 */
interface ComponentEntityInterface extends ContentEntityInterface, EntityChangedInterface, EntityPublishedInterface, EntityOwnerInterface, RevisionableInterface, RevisionLogInterface {

  /**
   * Gets the Component name.
   *
   * @return string
   *   Name of the Component.
   */
  public function getName();

  /**
   * Sets the Component name.
   *
   * @param string $name
   *   The Component name.
   *
   * @return \Drupal\component_entity\Entity\ComponentEntityInterface
   *   The called Component entity.
   */
  public function setName($name);

  /**
   * Gets the render method for this component.
   *
   * @return string
   *   The render method ('twig' or 'react').
   */
  public function getRenderMethod();

  /**
   * Sets the render method for this component.
   *
   * @param string $method
   *   The render method ('twig' or 'react').
   *
   * @return \Drupal\component_entity\Entity\ComponentEntityInterface
   *   The called Component entity.
   *
   * @throws \InvalidArgumentException
   *   If the render method is not valid.
   */
  public function setRenderMethod($method);

  /**
   * Gets the React configuration for this component.
   *
   * @return array
   *   The React configuration array with keys:
   *   - hydration: The hydration method ('full', 'partial', or 'none').
   *   - progressive: Whether to use progressive enhancement.
   *   - lazy: Whether to lazy load the component.
   *   - ssr: Whether to use server-side rendering.
   */
  public function getReactConfig();

  /**
   * Sets the React configuration for this component.
   *
   * @param array $config
   *   The React configuration array.
   *
   * @return \Drupal\component_entity\Entity\ComponentEntityInterface
   *   The called Component entity.
   */
  public function setReactConfig(array $config);

  /**
   * Gets the component type (bundle) entity.
   *
   * @return \Drupal\component_entity\Entity\ComponentTypeInterface|null
   *   The component type entity, or NULL if not found.
   */
  public function getComponentType();

  /**
   * Checks if this component supports React rendering.
   *
   * @return bool
   *   TRUE if React rendering is supported, FALSE otherwise.
   */
  public function hasReactSupport();

  /**
   * Checks if this component supports Twig rendering.
   *
   * @return bool
   *   TRUE if Twig rendering is supported, FALSE otherwise.
   */
  public function hasTwigSupport();

  /**
   * Gets the Component creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Component.
   */
  public function getCreatedTime();

  /**
   * Sets the Component creation timestamp.
   *
   * @param int $timestamp
   *   The Component creation timestamp.
   *
   * @return \Drupal\component_entity\Entity\ComponentEntityInterface
   *   The called Component entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the component props extracted from entity fields.
   *
   * @return array
   *   Array of props with field values mapped to prop names.
   */
  public function getProps();

  /**
   * Gets the component slots extracted from entity fields.
   *
   * @return array
   *   Array of slot fields keyed by slot name.
   */
  public function getSlots();

}