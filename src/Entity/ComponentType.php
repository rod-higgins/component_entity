<?php

namespace Drupal\component_entity\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the Component type entity.
 *
 * @ConfigEntityType(
 *   id = "component_type",
 *   label = @Translation("Component type"),
 *   label_collection = @Translation("Component types"),
 *   label_singular = @Translation("component type"),
 *   label_plural = @Translation("component types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count component type",
 *     plural = "@count component types",
 *   ),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\component_entity\Controller\ComponentTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\component_entity\Form\ComponentTypeForm",
 *       "edit" = "Drupal\component_entity\Form\ComponentTypeForm",
 *       "delete" = "Drupal\component_entity\Form\ComponentTypeDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "component_type",
 *   admin_permission = "administer component types",
 *   bundle_of = "component",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "sdc_id",
 *     "rendering",
 *     "checksum",
 *     "settings",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/component-types/{component_type}",
 *     "add-form" = "/admin/structure/component-types/add",
 *     "edit-form" = "/admin/structure/component-types/{component_type}/edit",
 *     "delete-form" = "/admin/structure/component-types/{component_type}/delete",
 *     "collection" = "/admin/structure/component-types",
 *   },
 * )
 */
class ComponentType extends ConfigEntityBundleBase implements ComponentTypeInterface {

  /**
   * The Component type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Component type label.
   *
   * @var string
   */
  protected $label;

  /**
   * The Component type description.
   *
   * @var string
   */
  protected $description;

  /**
   * The SDC component ID this type is synced with.
   *
   * @var string
   */
  protected $sdc_id;

  /**
   * The rendering configuration.
   *
   * @var array
   */
  protected $rendering = [
    'twig_enabled' => TRUE,
    'react_enabled' => FALSE,
    'default_method' => 'twig',
    'react_library' => NULL,
  ];

  /**
   * Checksum for tracking SDC component changes.
   *
   * @var string
   */
  protected $checksum;

  /**
   * Additional settings for the component type.
   *
   * @var array
   */
  protected $settings = [];

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSdcId() {
    return $this->sdc_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setSdcId($sdc_id) {
    $this->sdc_id = $sdc_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderingConfiguration() {
    return $this->rendering ?? [
      'twig_enabled' => TRUE,
      'react_enabled' => FALSE,
      'default_method' => 'twig',
      'react_library' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setRenderingConfiguration(array $configuration) {
    $this->rendering = $configuration + $this->getRenderingConfiguration();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isTwigEnabled() {
    $config = $this->getRenderingConfiguration();
    return !empty($config['twig_enabled']);
  }

  /**
   * {@inheritdoc}
   */
  public function isReactEnabled() {
    $config = $this->getRenderingConfiguration();
    return !empty($config['react_enabled']);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultRenderMethod() {
    $config = $this->getRenderingConfiguration();
    return $config['default_method'] ?? 'twig';
  }

  /**
   * {@inheritdoc}
   */
  public function setDefaultRenderMethod($method) {
    if (!in_array($method, ['twig', 'react'])) {
      throw new \InvalidArgumentException('Invalid render method. Must be "twig" or "react".');
    }

    $config = $this->getRenderingConfiguration();
    $config['default_method'] = $method;
    $this->setRenderingConfiguration($config);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasDualRenderSupport() {
    return $this->isTwigEnabled() && $this->isReactEnabled();
  }

  /**
   * {@inheritdoc}
   */
  public function getReactLibrary() {
    $config = $this->getRenderingConfiguration();
    return $config['react_library'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setReactLibrary($library) {
    $config = $this->getRenderingConfiguration();
    $config['react_library'] = $library;
    $this->setRenderingConfiguration($config);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getChecksum() {
    return $this->checksum;
  }

  /**
   * {@inheritdoc}
   */
  public function setChecksum($checksum) {
    $this->checksum = $checksum;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings() {
    return $this->settings ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSetting($key, $default = NULL) {
    $settings = $this->getSettings();
    return $settings[$key] ?? $default;
  }

  /**
   * {@inheritdoc}
   */
  public function setSettings(array $settings) {
    $this->settings = $settings;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setSetting($key, $value) {
    $this->settings[$key] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasThemeVariations() {
    return $this->getSetting('theme_variations', FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableRenderMethods() {
    $methods = [];

    if ($this->isTwigEnabled()) {
      $methods['twig'] = t('Twig (Server-side)');
    }

    if ($this->isReactEnabled()) {
      $methods['react'] = t('React (Client-side)');
    }

    return $methods;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Validate that at least one render method is enabled.
    if (!$this->isTwigEnabled() && !$this->isReactEnabled()) {
      throw new \InvalidArgumentException('At least one render method (Twig or React) must be enabled.');
    }

    // Validate default render method.
    $default = $this->getDefaultRenderMethod();
    if ($default === 'twig' && !$this->isTwigEnabled()) {
      throw new \InvalidArgumentException('Cannot set Twig as default when Twig rendering is disabled.');
    }
    if ($default === 'react' && !$this->isReactEnabled()) {
      throw new \InvalidArgumentException('Cannot set React as default when React rendering is disabled.');
    }

    // Auto-detect React library if not set.
    if ($this->isReactEnabled() && !$this->getReactLibrary()) {
      $library = $this->detectReactLibrary();
      if ($library) {
        $this->setReactLibrary($library);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    if ($update) {
      // Clear component cache for this bundle.
      \Drupal::service('component_entity.cache_manager')->invalidateBundleCache($this->id());

      // Clear field definitions cache.
      \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postDelete(EntityStorageInterface $storage) {
    parent::postDelete($storage);

    // Delete all components of this type.
    $component_storage = \Drupal::entityTypeManager()->getStorage('component');
    $components = $component_storage->loadByProperties(['type' => $this->id()]);
    $component_storage->delete($components);

    // Clear caches.
    \Drupal::service('component_entity.cache_manager')->clearAllComponentCaches();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    // Add dependency on the module providing the SDC component.
    if ($this->sdc_id) {
      $parts = explode(':', $this->sdc_id);
      if (count($parts) > 1) {
        $provider = $parts[0];
        if (\Drupal::moduleHandler()->moduleExists($provider)) {
          $this->addDependency('module', $provider);
        }
      }
    }

    return $this;
  }

  /**
   * Detects the React library for this component type.
   *
   * @return string|null
   *   The library name or NULL if not found.
   */
  protected function detectReactLibrary() {
    // Check if a compiled React component exists.
    $module_path = \Drupal::service('extension.list.module')->getPath('component_entity');
    $js_file = $module_path . '/dist/js/' . $this->id() . '.component.js';

    if (file_exists($js_file)) {
      return 'component_entity/component.' . $this->id();
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getComponentDefinition() {
    if (!$this->sdc_id) {
      return NULL;
    }

    try {
      $component_manager = \Drupal::service('plugin.manager.sdc');
      return $component_manager->getDefinition($this->sdc_id);
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function syncFromSdc() {
    $definition = $this->getComponentDefinition();
    if (!$definition) {
      return FALSE;
    }

    // Update description.
    if (isset($definition->metadata->description)) {
      $this->setDescription($definition->metadata->description);
    }

    // Update checksum.
    $checksum = md5(serialize($definition->metadata));
    $this->setChecksum($checksum);

    // Check for React component.
    $module_path = \Drupal::service('extension.list.module')->getPath('component_entity');
    $jsx_file = $module_path . '/components/' . $this->id() . '/' . $this->id() . '.jsx';
    $tsx_file = $module_path . '/components/' . $this->id() . '/' . $this->id() . '.tsx';

    if (file_exists($jsx_file) || file_exists($tsx_file)) {
      $config = $this->getRenderingConfiguration();
      $config['react_enabled'] = TRUE;
      $this->setRenderingConfiguration($config);
    }

    $this->save();

    // Trigger field sync.
    \Drupal::service('component_entity.sync')->syncComponentFields($this->id(), $definition);

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isLocked() {
    // Check if this component type is in use.
    $count = \Drupal::entityQuery('component')
      ->condition('type', $this->id())
      ->count()
      ->execute();

    return $count > 0;
  }

}
