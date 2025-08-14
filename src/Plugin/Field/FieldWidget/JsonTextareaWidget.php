<?php

namespace Drupal\component_entity\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'json_textarea' widget.
 *
 * @FieldWidget(
 *   id = "json_textarea",
 *   label = @Translation("JSON textarea"),
 *   field_types = {
 *     "json"
 *   }
 * )
 */
class JsonTextareaWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'rows' => 10,
      'placeholder' => '',
      'syntax_highlighting' => TRUE,
      'validate_on_blur' => TRUE,
      'code_mirror' => FALSE,
      'show_format_buttons' => TRUE,
      'collapsible' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['rows'] = [
      '#type' => 'number',
      '#title' => $this->t('Rows'),
      '#default_value' => $this->getSetting('rows'),
      '#min' => 1,
      '#required' => TRUE,
    ];

    $elements['placeholder'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Placeholder'),
      '#default_value' => $this->getSetting('placeholder'),
      '#description' => $this->t('Text that will be shown inside the field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
      '#rows' => 3,
    ];

    $elements['syntax_highlighting'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable syntax highlighting'),
      '#default_value' => $this->getSetting('syntax_highlighting'),
      '#description' => $this->t('Highlight JSON syntax for better readability.'),
    ];

    $elements['validate_on_blur'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Validate on blur'),
      '#default_value' => $this->getSetting('validate_on_blur'),
      '#description' => $this->t('Validate JSON format when the field loses focus.'),
    ];

    $elements['code_mirror'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use CodeMirror editor'),
      '#default_value' => $this->getSetting('code_mirror'),
      '#description' => $this->t('Use CodeMirror for enhanced JSON editing experience.'),
    ];

    $elements['show_format_buttons'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show format buttons'),
      '#default_value' => $this->getSetting('show_format_buttons'),
      '#description' => $this->t('Show buttons to format and validate JSON.'),
    ];

    $elements['collapsible'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Collapsible'),
      '#default_value' => $this->getSetting('collapsible'),
      '#description' => $this->t('Make the field collapsible to save space.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $rows = $this->getSetting('rows');
    $summary[] = $this->t('Number of rows: @rows', ['@rows' => $rows]);

    if ($this->getSetting('syntax_highlighting')) {
      $summary[] = $this->t('Syntax highlighting enabled');
    }

    if ($this->getSetting('code_mirror')) {
      $summary[] = $this->t('CodeMirror editor enabled');
    }

    if ($this->getSetting('validate_on_blur')) {
      $summary[] = $this->t('Validate on blur');
    }

    if ($this->getSetting('show_format_buttons')) {
      $summary[] = $this->t('Format buttons shown');
    }

    if (!empty($this->getSetting('placeholder'))) {
      $summary[] = $this->t('Placeholder: @placeholder', [
        '@placeholder' => substr($this->getSetting('placeholder'), 0, 50) . '...',
      ]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $value = $items[$delta]->value ?? '';

    // Pretty print the JSON if it's valid.
    if (!empty($value)) {
      $decoded = json_decode($value, TRUE);
      if (json_last_error() === JSON_ERROR_NONE) {
        $value = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      }
    }

    $element['value'] = $element + [
      '#type' => 'textarea',
      '#default_value' => $value,
      '#rows' => $this->getSetting('rows'),
      '#placeholder' => $this->getSetting('placeholder'),
      '#attributes' => [
        'class' => ['json-textarea'],
        'data-json-validate' => $this->getSetting('validate_on_blur') ? 'true' : 'false',
      ],
    ];

    // Add wrapper for enhanced functionality.
    if ($this->getSetting('show_format_buttons') || $this->getSetting('code_mirror')) {
      $wrapper_id = 'json-widget-' . $items->getName() . '-' . $delta;

      $element['value']['#prefix'] = '<div id="' . $wrapper_id . '" class="json-widget-wrapper">';
      $element['value']['#suffix'] = '</div>';

      // Add format buttons.
      if ($this->getSetting('show_format_buttons')) {
        $element['value']['#prefix'] .= $this->buildFormatButtons($wrapper_id);
      }

      // Add validation message area.
      $element['value']['#suffix'] = '<div class="json-validation-message"></div>' . $element['value']['#suffix'];
    }

    // Make collapsible if enabled.
    if ($this->getSetting('collapsible')) {
      $element['#type'] = 'details';
      $element['#title'] = $element['#title'] ?? $this->t('JSON Data');
      $element['#open'] = !empty($value);
    }

    // Attach library for enhanced functionality.
    if ($this->getSetting('syntax_highlighting') || $this->getSetting('code_mirror') || $this->getSetting('show_format_buttons')) {
      $element['#attached']['library'][] = 'component_entity/json-widget';

      if ($this->getSetting('code_mirror')) {
        $element['#attached']['library'][] = 'component_entity/codemirror';
        $element['value']['#attributes']['data-codemirror'] = 'json';
      }
    }

    // Add AJAX validation if enabled.
    if ($this->getSetting('validate_on_blur')) {
      $element['value']['#ajax'] = [
        'callback' => [$this, 'validateJsonCallback'],
        'event' => 'blur',
        'wrapper' => $wrapper_id ?? 'json-widget-wrapper',
        'progress' => [
          'type' => 'none',
        ],
      ];
    }

    return $element;
  }

  /**
   * Builds format buttons for JSON manipulation.
   *
   * @param string $wrapper_id
   *   The wrapper element ID.
   *
   * @return string
   *   HTML for format buttons.
   */
  protected function buildFormatButtons($wrapper_id) {
    $buttons = '<div class="json-format-buttons">';

    $buttons .= '<button type="button" class="button button--small json-format-btn" data-target="' . $wrapper_id . '">';
    $buttons .= $this->t('Format JSON');
    $buttons .= '</button>';

    $buttons .= '<button type="button" class="button button--small json-minify-btn" data-target="' . $wrapper_id . '">';
    $buttons .= $this->t('Minify');
    $buttons .= '</button>';

    $buttons .= '<button type="button" class="button button--small json-validate-btn" data-target="' . $wrapper_id . '">';
    $buttons .= $this->t('Validate');
    $buttons .= '</button>';

    $buttons .= '<button type="button" class="button button--small json-clear-btn" data-target="' . $wrapper_id . '">';
    $buttons .= $this->t('Clear');
    $buttons .= '</button>';

    $buttons .= '</div>';

    return $buttons;
  }

  /**
   * AJAX callback for JSON validation.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The element to update.
   */
  public function validateJsonCallback(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $element = $triggering_element;

    // Validate the JSON.
    $value = $triggering_element['#value'];
    if (!empty($value)) {
      $decoded = json_decode($value, TRUE);
      if (json_last_error() !== JSON_ERROR_NONE) {
        $element['#attributes']['class'][] = 'error';
        $element['#suffix'] = '<div class="json-validation-error">' .
          $this->t('Invalid JSON: @error', ['@error' => json_last_error_msg()]) .
          '</div>';
      }
      else {
        $element['#attributes']['class'][] = 'valid';
        $element['#suffix'] = '<div class="json-validation-success">' .
          $this->t('Valid JSON') .
          '</div>';
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as &$value) {
      if (isset($value['value'])) {
        // Clean and validate JSON.
        $json = $value['value'];
        if (!empty($json)) {
          // Try to decode and re-encode to normalize.
          $decoded = json_decode($json, TRUE);
          if (json_last_error() === JSON_ERROR_NONE) {
            // Re-encode with consistent formatting.
            $value['value'] = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
          }
        }
      }
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $violation, array $form, FormStateInterface $form_state) {
    return $element['value'];
  }

}
