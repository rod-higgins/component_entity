<?php

namespace Drupal\component_entity\Plugin\views\sort;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\sort\Date;

/**
 * Sort handler for component changed timestamp.
 *
 * @ViewsSort("component_changed")
 */
class ComponentChanged extends Date {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['granularity'] = ['default' => 'second'];
    $options['relative_date'] = ['default' => FALSE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['granularity'] = [
      '#type' => 'select',
      '#title' => $this->t('Granularity'),
      '#options' => [
        'second' => $this->t('Second'),
        'minute' => $this->t('Minute'),
        'hour' => $this->t('Hour'),
        'day' => $this->t('Day'),
        'month' => $this->t('Month'),
        'year' => $this->t('Year'),
      ],
      '#default_value' => $this->options['granularity'],
      '#description' => $this->t('The granularity of the date sort.'),
    ];

    $form['relative_date'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Sort by relative date'),
      '#description' => $this->t('Sort by how recently the component was changed relative to now.'),
      '#default_value' => $this->options['relative_date'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    if ($this->options['relative_date']) {
      // Sort by time difference from now.
      $formula = "ABS(" . REQUEST_TIME . " - $this->tableAlias.$this->realField)";
      $this->query->addOrderBy(NULL, $formula, $this->options['order'], $this->tableAlias . '_' . $this->field . '_relative');
    }
    else {
      // Apply granularity if specified.
      $field = "$this->tableAlias.$this->realField";

      switch ($this->options['granularity']) {
        case 'year':
          $formula = "YEAR(FROM_UNIXTIME($field))";
          break;

        case 'month':
          $formula = "DATE_FORMAT(FROM_UNIXTIME($field), '%Y%m')";
          break;

        case 'day':
          $formula = "DATE(FROM_UNIXTIME($field))";
          break;

        case 'hour':
          $formula = "DATE_FORMAT(FROM_UNIXTIME($field), '%Y%m%d%H')";
          break;

        case 'minute':
          $formula = "DATE_FORMAT(FROM_UNIXTIME($field), '%Y%m%d%H%i')";
          break;

        case 'second':
        default:
          $formula = $field;
          break;
      }

      $this->query->addOrderBy(NULL, $formula, $this->options['order'], $this->tableAlias . '_' . $this->field . '_granular');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    $summary = parent::adminSummary();

    if ($this->options['relative_date']) {
      $summary .= ' ' . $this->t('(relative)');
    }
    elseif ($this->options['granularity'] !== 'second') {
      $summary .= ' ' . $this->t('(@granularity)', ['@granularity' => $this->options['granularity']]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // If using relative date sorting, cache needs to be time-sensitive.
    if ($this->options['relative_date']) {
      // 1 minute cache for relative date sorting.
      return 60;
    }

    return parent::getCacheMaxAge();
  }

}
