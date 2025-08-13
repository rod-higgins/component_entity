<?php

namespace Drupal\component_entity\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\StringFilter;

/**
 * Filter handler for component name.
 *
 * @ViewsFilter("component_name")
 */
class ComponentName extends StringFilter {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    
    $options['expose']['contains']['placeholder'] = ['default' => ''];
    $options['expose']['contains']['autocomplete'] = ['default' => FALSE];
    $options['case_sensitive'] = ['default' => FALSE];
    
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    
    $form['case_sensitive'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Case sensitive'),
      '#description' => $this->t('Make the search case sensitive.'),
      '#default_value' => $this->options['case_sensitive'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposeForm(&$form, FormStateInterface $form_state) {
    parent::buildExposeForm($form, $form_state);
    
    $form['expose']['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder'),
      '#description' => $this->t('Placeholder text for the exposed filter.'),
      '#default_value' => $this->options['expose']['placeholder'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="options[expose][exposed]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    
    $form['expose']['autocomplete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable autocomplete'),
      '#description' => $this->t('Enable autocomplete suggestions for component names.'),
      '#default_value' => $this->options['expose']['autocomplete'] ?? FALSE,
      '#states' => [
        'visible' => [
          ':input[name="options[expose][exposed]"]' => ['checked' => TRUE],
        ],
      ],
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
    
    if (isset($form[$identifier])) {
      // Add placeholder if configured.
      if (!empty($this->options['expose']['placeholder'])) {
        $form[$identifier]['#placeholder'] = $this->options['expose']['placeholder'];
      }
      
      // Add autocomplete if enabled.
      if (!empty($this->options['expose']['autocomplete'])) {
        $form[$identifier]['#autocomplete_route_name'] = 'component_entity.autocomplete.name';
        $form[$identifier]['#autocomplete_route_parameters'] = [
          'type' => 'all',
        ];
      }
      
      // Make it a search input type.
      $form[$identifier]['#type'] = 'search';
      $form[$identifier]['#size'] = 30;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function operators() {
    $operators = parent::operators();
    
    // Add additional operators specific to component names.
    $operators['word'] = [
      'title' => $this->t('Contains any word'),
      'short' => $this->t('has word'),
      'method' => 'opContainsWord',
      'values' => 1,
    ];
    
    $operators['allwords'] = [
      'title' => $this->t('Contains all words'),
      'short' => $this->t('has all'),
      'method' => 'opContainsAllWords',
      'values' => 1,
    ];
    
    return $operators;
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    if (!empty($this->options['exposed'])) {
      return $this->t('exposed');
    }
    
    $output = parent::adminSummary();
    
    if ($this->options['case_sensitive']) {
      $output .= ' ' . $this->t('(case sensitive)');
    }
    
    return $output;
  }

  /**
   * Operator callback for "contains any word".
   */
  protected function opContainsWord($field) {
    $value = $this->getValue();
    
    if (empty($value)) {
      return;
    }
    
    $words = preg_split('/\s+/', $value);
    $or_condition = $this->query->getConnection()->condition('OR');
    
    foreach ($words as $word) {
      if (strlen($word) > 0) {
        $operator = $this->options['case_sensitive'] ? 'LIKE BINARY' : 'LIKE';
        $or_condition->condition($field, '%' . $this->query->getConnection()->escapeLike($word) . '%', $operator);
      }
    }
    
    $this->query->addWhere($this->options['group'], $or_condition);
  }

  /**
   * Operator callback for "contains all words".
   */
  protected function opContainsAllWords($field) {
    $value = $this->getValue();
    
    if (empty($value)) {
      return;
    }
    
    $words = preg_split('/\s+/', $value);
    $and_condition = $this->query->getConnection()->condition('AND');
    
    foreach ($words as $word) {
      if (strlen($word) > 0) {
        $operator = $this->options['case_sensitive'] ? 'LIKE BINARY' : 'LIKE';
        $and_condition->condition($field, '%' . $this->query->getConnection()->escapeLike($word) . '%', $operator);
      }
    }
    
    $this->query->addWhere($this->options['group'], $and_condition);
  }

}