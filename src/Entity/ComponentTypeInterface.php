<?php

namespace Drupal\component_entity\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityDescriptionInterface;

/**
 * Provides an interface for defining Component type entities.
 */
interface ComponentTypeInterface extends ConfigEntityInterface, EntityDescriptionInterface {

  /**
   * Gets the SDC component ID associated with this type.
   *
   * @return string|null
   *   The SDC component ID, or NULL if not set.
   */
  public function getSdcId();

  /**
   * Sets the SDC component ID.
   *
   * @param string $sdc_id
   *   The SDC component ID.
   *
   * @return \Drupal\component_entity\Entity\ComponentTypeInterface
   *   The called component type entity.
   */
  public function setSdcId($sdc_id);

  /**
   * Gets the rendering configuration.
   *
   * @return array
   *   The rendering configuration with keys:
   *   - twig_enabled: Whether Twig rendering is enabled.
   *   - react_enabled: Whether React rendering is enabled.
   *   - default_method: The default render method.
   *   - react_library: The React library to use.
   */
  public function getRenderingConfiguration();

  /**
   * Sets the rendering configuration.
   *
   * @param array $configuration
   *   The rendering configuration.
   *
   * @return \Drupal\component_entity\Entity\ComponentTypeInterface
   *   The called component type entity.
   */
  public function setRenderingConfiguration(array $configuration);

  /**
   * Checks if Twig rendering is enabled.
   *
   * @return bool
   *   TRUE if Twig rendering is enabled, FALSE otherwise.
   */
  public function isTwigEnabled();

  /**
   * Checks if React rendering is enabled.
   *
   * @return bool
   *   TRUE if React rendering is enabled, FALSE otherwise.
   */
  public function isReactEnabled();

  /**
   * Gets the default render method.
   *
   * @return string
   *   The default render method ('twig' or 'react').
   */
  public function getDefaultRenderMethod();

  /**
   * Sets the default render method.
   *
   * @param string $method
   *   The render method ('twig' or 'react').
   *
   * @return \Drupal\component_entity\Entity\ComponentTypeInterface
   *   The called component type entity.
   *
   * @throws \InvalidArgumentException
   *   If the render method is not valid.
   */
  public function setDefaultRenderMethod($method);

  /**
   * Checks if this component type supports dual rendering.
   *
   * @return bool
   *   TRUE if both Twig and React are enabled, FALSE otherwise.
   */
  public function hasDualRenderSupport();

  /**
   * Gets the React library for this component type.
   *
   * @return string|null
   *   The React library name, or NULL if not set.
   */
  public function getReactLibrary();

  /**
   * Sets the React library for this component type.
   *
   * @param string $library
   *   The React library name.
   *
   * @return \Drupal\component_entity\Entity\ComponentTypeInterface
   *   The called component type entity.
   */
  public function setReactLibrary($library);

  /**
   * Gets the checksum for tracking SDC component changes.
   *
   * @return string|null
   *   The checksum, or NULL if not set.
   */
  public function getChecksum();

  /**
   * Sets the checksum for tracking SDC component changes.
   *
   * @param string $checksum
   *   The checksum.
   *
   * @return \Drupal\component_entity\Entity\ComponentTypeInterface
   *   The called component type entity.
   */
  public function setChecksum($checksum);

  /**
   * Gets all settings for this component type.
   *
   * @return array
   *   Array of settings.
   */
  public function getSettings();

  /**
   * Gets a specific setting value.
   *
   * @param string $key
   *   The setting key.
   * @param mixed $default
   *   The default value if setting doesn't exist.
   *
   * @return mixed
   *   The setting value.
   */
  public function getSetting($key, $default = NULL);

  /**
   * Sets all settings for this component type.
   *
   * @param array $settings
   *   Array of settings.
   *
   * @return \Drupal\component_entity\Entity\ComponentTypeInterface
   *   The called component type entity.
   */
  public function setSettings(array $settings);

  /**
   * Sets a specific setting value.
   *
   * @param string $key
   *   The setting key.
   * @param mixed $value
   *   The setting value.
   *
   * @return \Drupal\component_entity\Entity\ComponentTypeInterface
   *   The called component type entity.
   */
  public function setSetting($key, $value);

  /**
   * Checks if this component type has theme variations.
   *
   * @return bool
   *   TRUE if theme variations are enabled, FALSE otherwise.
   */
  public function hasThemeVariations();

  /**
   * Gets available render methods for this component type.
   *
   * @return array
   *   Array of available render methods keyed by method name.
   */
  public function getAvailableRenderMethods();

  /**
   * Gets the SDC component definition.
   *
   * @return object|null
   *   The SDC component definition, or NULL if not found.
   */
  public function getComponentDefinition();

  /**
   * Syncs this component type with its SDC definition.
   *
   * @return bool
   *   TRUE if sync was successful, FALSE otherwise.
   */
  public function syncFromSdc();

  /**
   * Checks if this component type is locked (has existing components).
   *
   * @return bool
   *   TRUE if locked, FALSE otherwise.
   */
  public function isLocked();

}
