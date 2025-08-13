<?php

namespace Drupal\component_entity\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\BooleanOperator;

/**
 * Filter handler for component status.
 *
 * @ViewsFilter("component_status")
 */
class ComponentStatus extends BooleanOperator {

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueOptions = [
        1 => $this->t('Published'),
        0 => $this->t('Unpublished'),
      ];
    }
    
    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    
    $options['value']['default'] = 1;
    $options['show_status_icon'] = ['default' => FALSE];
    
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    
    $form['show_status_icon'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show status icon in exposed filter'),
      '#description' => $this->t('Display an icon next to the status options.'),
      '#default_value' => $this->options['show_status_icon'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposedForm(&$form, FormStateInterface $form_state) {
    parent::buildExposedForm($form, $form_state);
    
    if (empty($this->options['exposed'])) {
      return;
    }
    
    $identifier = $this->options['expose']['identifier'];
    
    if ($this->options['show_status_icon'] && isset($form[$identifier])) {
      $form[$identifier]['#options'] = [
        'All' => $this->t('- Any -'),
        1 => $this->t('✓ Published'),
        0 => $this->t('✗ Unpublished'),
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    if (!empty($this->options['exposed'])) {
      return $this->t('exposed');
    }
    
    $value = $this->value[0] ?? NULL;
    
    if ($value === '1' || $value === 1) {
      return $this->t('Published');
    }
    elseif ($value === '0' || $value === 0) {
      return $this->t('Unpublished');
    }
    
    return parent::adminSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    $rc = parent::acceptExposedInput($input);
    
    // If the "All" option is selected, treat it as no filter.
    if ($rc) {
      $identifier = $this->options['expose']['identifier'];
      if (isset($input[$identifier]) && $input[$identifier] === 'All') {
        return FALSE;
      }
    }
    
    return $rc;
  }

}