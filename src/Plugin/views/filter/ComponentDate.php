<?php

namespace Drupal\component_entity\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime\Plugin\views\filter\Date;

/**
 * Filter handler for component date fields (created/changed).
 *
 * Provides user-friendly date filtering options.
 *
 * @ViewsFilter("component_date")
 */
class ComponentDate extends Date {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['value']['contains']['preset'] = ['default' => ''];
    $options['expose']['contains']['use_presets'] = ['default' => TRUE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Add preset options before the regular date fields.
    $form['value']['preset'] = [
      '#type' => 'select',
      '#title' => $this->t('Date preset'),
      '#options' => $this->getPresetOptions(),
      '#default_value' => $this->options['value']['preset'] ?? '',
      '#weight' => -10,
      '#description' => $this->t('Select a preset or choose "Custom" to specify exact dates.'),
    ];

    // Add states to show/hide custom date fields.
    $form['value']['value']['#states'] = [
      'visible' => [
        ':input[name="options[value][preset]"]' => ['value' => 'custom'],
      ],
    ];

    $form['value']['min']['#states'] = [
      'visible' => [
        ':input[name="options[value][preset]"]' => ['value' => 'custom'],
      ],
    ];

    $form['value']['max']['#states'] = [
      'visible' => [
        ':input[name="options[value][preset]"]' => ['value' => 'custom'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposeForm(&$form, FormStateInterface $form_state) {
    parent::buildExposeForm($form, $form_state);

    $form['expose']['use_presets'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Provide preset date options'),
      '#description' => $this->t('Show preset date ranges in exposed filter.'),
      '#default_value' => $this->options['expose']['use_presets'] ?? TRUE,
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
    if (empty($this->options['exposed'])) {
      return;
    }

    $identifier = $this->options['expose']['identifier'];

    if ($this->options['expose']['use_presets']) {
      $form[$identifier . '_preset'] = [
        '#type' => 'select',
        '#title' => $this->options['expose']['label'] ?? $this->t('Date'),
        '#options' => $this->getExposedPresetOptions(),
        '#default_value' => '',
        '#attributes' => [
          'class' => ['component-date-preset'],
          'data-date-filter' => $identifier,
        ],
      ];

      // Add custom date range fields (hidden by default).
      $form[$identifier . '_custom'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Custom date range'),
        '#attributes' => [
          'class' => ['component-date-custom'],
          'style' => 'display:none;',
        ],
      ];

      $form[$identifier . '_custom']['from'] = [
        '#type' => 'date',
        '#title' => $this->t('From'),
      ];

      $form[$identifier . '_custom']['to'] = [
        '#type' => 'date',
        '#title' => $this->t('To'),
      ];
    }
    else {
      parent::buildExposedForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    if (empty($this->options['exposed'])) {
      return TRUE;
    }

    $identifier = $this->options['expose']['identifier'];

    // Handle preset selection.
    if (isset($input[$identifier . '_preset'])) {
      $preset = $input[$identifier . '_preset'];

      if ($preset && $preset !== 'custom') {
        $range = $this->getPresetDateRange($preset);
        if ($range) {
          $this->value = [
            'min' => $range['min'],
            'max' => $range['max'],
            'value' => NULL,
            'type' => 'between',
          ];
          return TRUE;
        }
      }
      elseif ($preset === 'custom' && isset($input[$identifier . '_custom'])) {
        $custom = $input[$identifier . '_custom'];
        if (!empty($custom['from']) || !empty($custom['to'])) {
          $this->value = [
            'min' => $custom['from'] ?? NULL,
            'max' => $custom['to'] ?? NULL,
            'value' => NULL,
            'type' => 'between',
          ];
          return TRUE;
        }
      }
    }

    return parent::acceptExposedInput($input);
  }

  /**
   * Get preset date options for configuration.
   *
   * @return array
   *   Array of preset options.
   */
  protected function getPresetOptions() {
    return [
      '' => $this->t('- None -'),
      'custom' => $this->t('Custom date range'),
      'today' => $this->t('Today'),
      'yesterday' => $this->t('Yesterday'),
      'this_week' => $this->t('This week'),
      'last_week' => $this->t('Last week'),
      'this_month' => $this->t('This month'),
      'last_month' => $this->t('Last month'),
      'last_7_days' => $this->t('Last 7 days'),
      'last_30_days' => $this->t('Last 30 days'),
      'last_90_days' => $this->t('Last 90 days'),
      'this_year' => $this->t('This year'),
      'last_year' => $this->t('Last year'),
    ];
  }

  /**
   * Get preset options for exposed filter.
   *
   * @return array
   *   Array of exposed preset options.
   */
  protected function getExposedPresetOptions() {
    return [
      '' => $this->t('- Any time -'),
      'today' => $this->t('Today'),
      'yesterday' => $this->t('Yesterday'),
      'this_week' => $this->t('This week'),
      'last_week' => $this->t('Last week'),
      'this_month' => $this->t('This month'),
      'last_month' => $this->t('Last month'),
      'last_7_days' => $this->t('Last 7 days'),
      'last_30_days' => $this->t('Last 30 days'),
      'last_90_days' => $this->t('Last 90 days'),
      'this_year' => $this->t('This year'),
      'last_year' => $this->t('Last year'),
      'custom' => $this->t('Custom range...'),
    ];
  }

  /**
   * Get date range for a preset.
   *
   * @param string $preset
   *   The preset identifier.
   *
   * @return array|null
   *   Array with 'min' and 'max' dates or NULL.
   */
  protected function getPresetDateRange($preset) {
    $now = new \DateTime();
    $range = ['min' => NULL, 'max' => NULL];

    switch ($preset) {
      case 'today':
        $range['min'] = $now->format('Y-m-d 00:00:00');
        $range['max'] = $now->format('Y-m-d 23:59:59');
        break;

      case 'yesterday':
        $yesterday = clone $now;
        $yesterday->modify('-1 day');
        $range['min'] = $yesterday->format('Y-m-d 00:00:00');
        $range['max'] = $yesterday->format('Y-m-d 23:59:59');
        break;

      case 'this_week':
        $start_of_week = clone $now;
        $start_of_week->modify('this week monday');
        $range['min'] = $start_of_week->format('Y-m-d 00:00:00');
        $range['max'] = $now->format('Y-m-d 23:59:59');
        break;

      case 'last_week':
        $start_of_last_week = clone $now;
        $start_of_last_week->modify('last week monday');
        $end_of_last_week = clone $start_of_last_week;
        $end_of_last_week->modify('+6 days');
        $range['min'] = $start_of_last_week->format('Y-m-d 00:00:00');
        $range['max'] = $end_of_last_week->format('Y-m-d 23:59:59');
        break;

      case 'this_month':
        $range['min'] = $now->format('Y-m-01 00:00:00');
        $range['max'] = $now->format('Y-m-d 23:59:59');
        break;

      case 'last_month':
        $first_of_last_month = clone $now;
        $first_of_last_month->modify('first day of last month');
        $last_of_last_month = clone $now;
        $last_of_last_month->modify('last day of last month');
        $range['min'] = $first_of_last_month->format('Y-m-d 00:00:00');
        $range['max'] = $last_of_last_month->format('Y-m-d 23:59:59');
        break;

      case 'last_7_days':
        $seven_days_ago = clone $now;
        $seven_days_ago->modify('-7 days');
        $range['min'] = $seven_days_ago->format('Y-m-d 00:00:00');
        $range['max'] = $now->format('Y-m-d 23:59:59');
        break;

      case 'last_30_days':
        $thirty_days_ago = clone $now;
        $thirty_days_ago->modify('-30 days');
        $range['min'] = $thirty_days_ago->format('Y-m-d 00:00:00');
        $range['max'] = $now->format('Y-m-d 23:59:59');
        break;

      case 'last_90_days':
        $ninety_days_ago = clone $now;
        $ninety_days_ago->modify('-90 days');
        $range['min'] = $ninety_days_ago->format('Y-m-d 00:00:00');
        $range['max'] = $now->format('Y-m-d 23:59:59');
        break;

      case 'this_year':
        $range['min'] = $now->format('Y-01-01 00:00:00');
        $range['max'] = $now->format('Y-m-d 23:59:59');
        break;

      case 'last_year':
        $last_year = ((int) $now->format('Y')) - 1;
        $range['min'] = $last_year . '-01-01 00:00:00';
        $range['max'] = $last_year . '-12-31 23:59:59';
        break;

      default:
        return NULL;
    }

    return $range;
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    if (!empty($this->options['exposed'])) {
      return $this->t('exposed');
    }

    if (!empty($this->options['value']['preset']) && $this->options['value']['preset'] !== 'custom') {
      $presets = $this->getPresetOptions();
      $preset = $this->options['value']['preset'];
      if (isset($presets[$preset])) {
        return $presets[$preset];
      }
    }

    return parent::adminSummary();
  }

}
