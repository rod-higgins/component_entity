<?php

namespace Drupal\component_entity\Plugin\views\filter;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter handler for component types.
 *
 * @ViewsFilter("component_type")
 */
class ComponentType extends InOperator implements ContainerFactoryPluginInterface {

  /**
   * The component type entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $componentTypeStorage;

  /**
   * Constructs a ComponentType filter handler.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $component_type_storage
   *   The component type entity storage.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityStorageInterface $component_type_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->componentTypeStorage = $component_type_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('component_type')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueOptions = [];
      
      // Load all component types.
      $component_types = $this->componentTypeStorage->loadMultiple();
      
      foreach ($component_types as $type) {
        $this->valueOptions[$type->id()] = $type->label();
      }
      
      // Sort alphabetically.
      asort($this->valueOptions);
    }
    
    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    
    $options['expose']['contains']['reduce'] = ['default' => TRUE];
    $options['show_sdc_info'] = ['default' => FALSE];
    
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    
    $form['show_sdc_info'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show SDC component info'),
      '#description' => $this->t('Display the associated SDC component ID with each type.'),
      '#default_value' => $this->options['show_sdc_info'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    if ($this->isAGroup()) {
      return $this->t('grouped');
    }
    if (!empty($this->options['exposed'])) {
      return $this->t('exposed');
    }
    
    $types = $this->getValueOptions();
    
    if (!is_array($this->value)) {
      return '';
    }
    
    if (count($this->value) == 1) {
      $value = reset($this->value);
      if (isset($types[$value])) {
        return $types[$value];
      }
    }
    elseif (count($this->value) > 1) {
      $values = [];
      foreach ($this->value as $value) {
        if (isset($types[$value])) {
          $values[] = $types[$value];
        }
      }
      return implode(', ', $values);
    }
    
    return parent::adminSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function validate() {
    parent::validate();
    
    // Check if selected component types exist.
    if (!empty($this->value) && is_array($this->value)) {
      $component_types = $this->componentTypeStorage->loadMultiple($this->value);
      $missing = array_diff($this->value, array_keys($component_types));
      
      if (!empty($missing)) {
        $this->broken = TRUE;
      }
    }
  }

}