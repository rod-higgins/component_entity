<?php

namespace Drupal\component_entity\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\EntityField;
use Drupal\views\ResultRow;

/**
 * Field handler for component render method.
 *
 * @ViewsField("component_render_method")
 */
class ComponentRenderMethod extends EntityField {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['display_as_badge'] = ['default' => FALSE];
    $options['display_with_icon'] = ['default' => FALSE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['display_as_badge'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display as badge'),
      '#description' => $this->t('Display the render method as a styled badge.'),
      '#default_value' => $this->options['display_as_badge'],
    ];

    $form['display_with_icon'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display with icon'),
      '#description' => $this->t('Include an icon with the render method label.'),
      '#default_value' => $this->options['display_with_icon'],
      '#states' => [
        'visible' => [
          ':input[name="options[display_as_badge]"]' => ['checked' => TRUE],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);

    if (empty($value)) {
      return '';
    }

    // Get the render method label.
    $render_methods = [
      'twig' => $this->t('Twig'),
      'react' => $this->t('React'),
    ];

    $label = $render_methods[$value] ?? $value;

    // Return plain text if no special display options.
    if (!$this->options['display_as_badge']) {
      return $label;
    }

    // Build badge display.
    $classes = [
      'component-render-method',
      'component-render-method--' . $value,
    ];

    if ($this->options['display_as_badge']) {
      $classes[] = 'badge';
    }

    $build = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#attributes' => [
        'class' => $classes,
      ],
      '#value' => $label,
    ];

    // Add icon if requested.
    if ($this->options['display_with_icon']) {
      $icons = [
        'twig' => '🌿',
        'react' => '⚛️',
      ];

      if (isset($icons[$value])) {
        $build['#value'] = $icons[$value] . ' ' . $label;
      }
    }

    // Add library for styling.
    $build['#attached']['library'][] = 'component_entity/admin';

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function clickSort($order) {
    // Ensure field is added.
    $this->ensureMyTable();

    // Add orderby for the render method field.
    $this->query->addOrderBy($this->tableAlias, $this->realField, $order);
  }

}
