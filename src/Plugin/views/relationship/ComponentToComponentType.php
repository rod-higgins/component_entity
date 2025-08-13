<?php

namespace Drupal\component_entity\Plugin\views\relationship;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\relationship\EntityReverse;

/**
 * Relationship handler for component to component type.
 *
 * @ViewsRelationship("component_to_component_type")
 */
class ComponentToComponentType extends EntityReverse {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    
    $options['component_types'] = ['default' => []];
    $options['include_sdc_info'] = ['default' => FALSE];
    
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    
    // Load all component types.
    $component_types = \Drupal::entityTypeManager()
      ->getStorage('component_type')
      ->loadMultiple();
    
    $type_options = [];
    foreach ($component_types as $type) {
      $type_options[$type->id()] = $type->label();
    }
    
    $form['component_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Component types'),
      '#description' => $this->t('Select which component types to include in the relationship. Leave empty to include all.'),
      '#options' => $type_options,
      '#default_value' => $this->options['component_types'],
    ];
    
    $form['include_sdc_info'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include SDC component information'),
      '#description' => $this->t('Make SDC component metadata available through this relationship.'),
      '#default_value' => $this->options['include_sdc_info'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    parent::query();
    
    // Filter by selected component types if specified.
    if (!empty($this->options['component_types'])) {
      $selected_types = array_filter($this->options['component_types']);
      if (!empty($selected_types)) {
        $this->query->addWhere(
          $this->options['group'],
          "$this->tableAlias.type",
          $selected_types,
          'IN'
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    $summary = parent::adminSummary();
    
    if (!empty($this->options['component_types'])) {
      $selected_types = array_filter($this->options['component_types']);
      if (!empty($selected_types)) {
        $count = count($selected_types);
        $summary .= ' ' . $this->formatPlural($count, '(@count type)', '(@count types)');
      }
    }
    
    if ($this->options['include_sdc_info']) {
      $summary .= ' ' . $this->t('+SDC');
    }
    
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    
    // Add dependencies on selected component types.
    if (!empty($this->options['component_types'])) {
      $selected_types = array_filter($this->options['component_types']);
      foreach ($selected_types as $type_id) {
        $type = \Drupal::entityTypeManager()
          ->getStorage('component_type')
          ->load($type_id);
        if ($type) {
          $dependencies['config'][] = $type->getConfigDependencyName();
        }
      }
    }
    
    return $dependencies;
  }

}