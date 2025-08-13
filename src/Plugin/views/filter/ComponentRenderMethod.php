<?php

namespace Drupal\component_entity\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Filter handler for component render method.
 *
 * @ViewsFilter("component_render_method")
 */
class ComponentRenderMethod extends InOperator {

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueOptions = [
        'twig' => $this->t('Twig (Server-side)'),
        'react' => $this->t('React (Client-side)'),
      ];
    }
    
    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    
    $options['expose']['contains']['reduce'] = ['default' => TRUE];
    $options['show_description'] = ['default' => FALSE];
    
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    
    $form['show_description'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show render method descriptions'),
      '#description' => $this->t('Display additional information about each render method.'),
      '#default_value' => $this->options['show_description'],
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
    
    if ($this->options['show_description'] && isset($form[$identifier])) {
      $form[$identifier]['#options'] = [
        'All' => $this->t('- Any -'),
        'twig' => $this->t('Twig (Server-side, SEO-friendly)'),
        'react' => $this->t('React (Client-side, Interactive)'),
      ];
      
      $form[$identifier . '_description'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['description', 'render-method-description']],
        '#weight' => isset($form[$identifier]['#weight']) ? $form[$identifier]['#weight'] + 0.01 : 1,
      ];
      
      $form[$identifier . '_description']['twig'] = [
        '#markup' => '<div class="render-method-desc twig-desc">' . 
          $this->t('<strong>Twig:</strong> Server-rendered, SEO-friendly, fast initial load') . 
          '</div>',
      ];
      
      $form[$identifier . '_description']['react'] = [
        '#markup' => '<div class="render-method-desc react-desc">' . 
          $this->t('<strong>React:</strong> Client-rendered, interactive, dynamic updates') . 
          '</div>',
      ];
    }
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
    
    $methods = $this->getValueOptions();
    
    if (!is_array($this->value)) {
      return '';
    }
    
    $values = [];
    foreach ($this->value as $value) {
      if (isset($methods[$value])) {
        $values[] = $methods[$value];
      }
    }
    
    if (count($values) == 1) {
      return reset($values);
    }
    elseif (count($values) > 1) {
      return $this->t('Multiple (@count)', ['@count' => count($values)]);
    }
    
    return parent::adminSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // Cache this filter as it's based on configuration.
    return 3600;
  }

}