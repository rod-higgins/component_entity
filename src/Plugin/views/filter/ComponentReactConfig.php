<?php

namespace Drupal\component_entity\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filter handler for component React configuration.
 *
 * @ViewsFilter("component_react_config")
 */
class ComponentReactConfig extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['value'] = [
      'contains' => [
        'hydration' => ['default' => ''],
        'ssr' => ['default' => ''],
        'progressive' => ['default' => ''],
      ],
    ];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['value']['hydration'] = [
      '#type' => 'select',
      '#title' => $this->t('Hydration method'),
      '#options' => [
        '' => $this->t('- Any -'),
        'full' => $this->t('Full hydration'),
        'partial' => $this->t('Partial hydration'),
        'none' => $this->t('No hydration'),
      ],
      '#default_value' => $this->value['hydration'] ?? '',
      '#description' => $this->t('Filter by React hydration method.'),
    ];

    $form['value']['ssr'] = [
      '#type' => 'select',
      '#title' => $this->t('Server-side rendering'),
      '#options' => [
        '' => $this->t('- Any -'),
        '1' => $this->t('Enabled'),
        '0' => $this->t('Disabled'),
      ],
      '#default_value' => $this->value['ssr'] ?? '',
      '#description' => $this->t('Filter by SSR configuration.'),
    ];

    $form['value']['progressive'] = [
      '#type' => 'select',
      '#title' => $this->t('Progressive enhancement'),
      '#options' => [
        '' => $this->t('- Any -'),
        '1' => $this->t('Enabled'),
        '0' => $this->t('Disabled'),
      ],
      '#default_value' => $this->value['progressive'] ?? '',
      '#description' => $this->t('Filter by progressive enhancement setting.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposedForm(&$form, FormStateInterface $form_state) {
    if (empty($this->options['exposed'])) {
      return;
    }

    $identifier = $this->options['expose']['identifier'];

    $form[$identifier . '_hydration'] = [
      '#type' => 'select',
      '#title' => $this->t('React Hydration'),
      '#options' => [
        '' => $this->t('- Any -'),
        'full' => $this->t('Full'),
        'partial' => $this->t('Partial'),
        'none' => $this->t('None'),
      ],
      '#default_value' => '',
    ];

    $form[$identifier . '_settings'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('React Settings'),
      '#options' => [
        'ssr' => $this->t('Server-side rendering'),
        'progressive' => $this->t('Progressive enhancement'),
      ],
      '#default_value' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    if (empty($this->options['exposed'])) {
      return TRUE;
    }

    $identifier = $this->options['expose']['identifier'];

    // Process hydration method.
    if (isset($input[$identifier . '_hydration']) && $input[$identifier . '_hydration'] !== '') {
      $this->value['hydration'] = $input[$identifier . '_hydration'];
    }

    // Process settings checkboxes.
    if (isset($input[$identifier . '_settings'])) {
      $settings = $input[$identifier . '_settings'];
      $this->value['ssr'] = !empty($settings['ssr']) ? '1' : '';
      $this->value['progressive'] = !empty($settings['progressive']) ? '1' : '';
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    $field = "$this->tableAlias.$this->realField";

    // Filter by hydration method.
    if (!empty($this->value['hydration'])) {
      $this->query->addWhere(
        $this->options['group'],
        $field,
        '%"hydration":"' . $this->value['hydration'] . '"%',
        'LIKE'
      );
    }

    // Filter by SSR setting.
    if ($this->value['ssr'] !== '') {
      $pattern = $this->value['ssr'] === '1'
        ? '%"ssr":true%'
        : '%"ssr":false%';

      $this->query->addWhere(
        $this->options['group'],
        $field,
        $pattern,
        'LIKE'
      );
    }

    // Filter by progressive enhancement.
    if ($this->value['progressive'] !== '') {
      $pattern = $this->value['progressive'] === '1'
        ? '%"progressive":true%'
        : '%"progressive":false%';

      $this->query->addWhere(
        $this->options['group'],
        $field,
        $pattern,
        'LIKE'
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    if (!empty($this->options['exposed'])) {
      return $this->t('exposed');
    }

    $summary = [];

    if (!empty($this->value['hydration'])) {
      $summary[] = $this->t('Hydration: @method', ['@method' => $this->value['hydration']]);
    }

    if ($this->value['ssr'] === '1') {
      $summary[] = $this->t('SSR enabled');
    }
    elseif ($this->value['ssr'] === '0') {
      $summary[] = $this->t('SSR disabled');
    }

    if ($this->value['progressive'] === '1') {
      $summary[] = $this->t('Progressive enabled');
    }
    elseif ($this->value['progressive'] === '0') {
      $summary[] = $this->t('Progressive disabled');
    }

    return !empty($summary) ? implode(', ', $summary) : $this->t('No filters');
  }

  /**
   * {@inheritdoc}
   */
  public function canExpose() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isExposed() {
    return !empty($this->options['exposed']);
  }

}
