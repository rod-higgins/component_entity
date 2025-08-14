<?php

namespace Drupal\component_entity\Plugin\views\area;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\area\AreaPluginBase;

/**
 * Provides bulk operations for component entities in Views.
 *
 * @ViewsArea("component_bulk_operations")
 */
class ComponentBulkOperations extends AreaPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['operations'] = [
      'default' => [
        'publish' => 'publish',
        'unpublish' => 'unpublish',
        'delete' => 'delete',
      ],
    ];

    $options['batch_size'] = ['default' => 50];
    $options['display_selection_info'] = ['default' => TRUE];
    $options['select_all_pages'] = ['default' => TRUE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['operations'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Available operations'),
      '#options' => $this->getAvailableOperations(),
      '#default_value' => $this->options['operations'],
      '#description' => $this->t('Select which bulk operations to make available.'),
    ];

    $form['batch_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Batch size'),
      '#default_value' => $this->options['batch_size'],
      '#min' => 1,
      '#max' => 500,
      '#description' => $this->t('Number of items to process per batch operation.'),
    ];

    $form['display_selection_info'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display selection information'),
      '#default_value' => $this->options['display_selection_info'],
      '#description' => $this->t('Show count of selected items and selection controls.'),
    ];

    $form['select_all_pages'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow selecting all items across pages'),
      '#default_value' => $this->options['select_all_pages'],
      '#description' => $this->t('Enable selecting all results, not just visible page.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    if ($empty && !$this->options['empty']) {
      return [];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['component-bulk-operations'],
      ],
    ];

    // Add selection controls.
    if ($this->options['display_selection_info']) {
      $build['selection_info'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['bulk-operations-selection-info'],
        ],
        'select_all' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Select all items on this page'),
          '#attributes' => [
            'class' => ['select-all-checkbox'],
            'data-bulk-operations-select-all' => 'page',
          ],
        ],
      ];

      if ($this->options['select_all_pages']) {
        $build['selection_info']['select_all_pages'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['select-all-pages-container'],
            'style' => 'display:none;',
          ],
          '#markup' => $this->t(
            '<a href="#" data-bulk-operations-select-all="all">Select all @count items across all pages</a>',
            ['@count' => $this->view->total_rows ?? 0]
          ),
        ];
      }

      $build['selection_info']['selected_count'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => [
          'class' => ['selected-count'],
          'data-bulk-operations-selected-count' => '0',
        ],
        '#value' => $this->t('0 items selected'),
      ];
    }

    // Add operations form.
    $build['operations'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['bulk-operations-form'],
      ],
    ];

    $enabled_operations = array_filter($this->options['operations']);
    $available_operations = $this->getAvailableOperations();

    $operation_options = [];
    foreach ($enabled_operations as $key => $value) {
      if (isset($available_operations[$key])) {
        $operation_options[$key] = $available_operations[$key];
      }
    }

    $build['operations']['operation'] = [
      '#type' => 'select',
      '#title' => $this->t('With selected'),
      '#options' => ['' => $this->t('- Choose operation -')] + $operation_options,
      '#attributes' => [
        'class' => ['bulk-operations-select'],
        'data-bulk-operations-action' => 'true',
      ],
    ];

    $build['operations']['execute'] = [
      '#type' => 'button',
      '#value' => $this->t('Apply'),
      '#attributes' => [
        'class' => ['bulk-operations-submit'],
        'data-bulk-operations-execute' => 'true',
        'disabled' => 'disabled',
      ],
    ];

    // Add JavaScript.
    $build['#attached']['library'][] = 'component_entity/bulk-operations';
    $build['#attached']['drupalSettings']['componentBulkOperations'] = [
      'viewId' => $this->view->id(),
      'displayId' => $this->view->current_display,
      'batchSize' => $this->options['batch_size'],
    ];

    return $build;
  }

  /**
   * Get available bulk operations.
   *
   * @return array
   *   Array of operation labels keyed by operation ID.
   */
  protected function getAvailableOperations() {
    return [
      'publish' => $this->t('Publish'),
      'unpublish' => $this->t('Unpublish'),
      'delete' => $this->t('Delete'),
      'change_render_method_twig' => $this->t('Change render method to Twig'),
      'change_render_method_react' => $this->t('Change render method to React'),
      'export' => $this->t('Export'),
      'duplicate' => $this->t('Duplicate'),
      'sync_from_sdc' => $this->t('Sync from SDC'),
      'clear_cache' => $this->t('Clear cache'),
      'change_owner' => $this->t('Change owner'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validate() {
    $errors = parent::validate();

    // Ensure at least one operation is selected.
    $enabled_operations = array_filter($this->options['operations']);
    if (empty($enabled_operations)) {
      $errors[] = $this->t('At least one bulk operation must be enabled.');
    }

    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    $enabled_operations = array_filter($this->options['operations']);
    $count = count($enabled_operations);

    return $this->formatPlural(
      $count,
      '@count operation',
      '@count operations',
      ['@count' => $count]
    );
  }

}
