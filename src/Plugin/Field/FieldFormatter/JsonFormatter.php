<?php

namespace Drupal\component_entity\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'json_formatted' formatter.
 *
 * @FieldFormatter(
 *   id = "json_formatted",
 *   label = @Translation("JSON formatted"),
 *   field_types = {
 *     "json"
 *   }
 * )
 */
class JsonFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'format' => 'pretty',
      'depth' => 512,
      'show_keys' => TRUE,
      'collapsible' => FALSE,
      'collapsed' => FALSE,
      'syntax_highlighting' => TRUE,
      'theme' => 'default',
      'show_toolbar' => FALSE,
      'max_height' => 0,
      'render_as' => 'code',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['render_as'] = [
      '#type' => 'select',
      '#title' => $this->t('Render as'),
      '#options' => [
        'code' => $this->t('Code block'),
        'table' => $this->t('Table'),
        'tree' => $this->t('Tree view'),
        'raw' => $this->t('Raw text'),
        'php_array' => $this->t('PHP array syntax'),
      ],
      '#default_value' => $this->getSetting('render_as'),
      '#description' => $this->t('How to display the JSON data.'),
    ];

    $elements['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Format'),
      '#options' => [
        'pretty' => $this->t('Pretty print'),
        'minified' => $this->t('Minified'),
        'inline' => $this->t('Inline'),
      ],
      '#default_value' => $this->getSetting('format'),
      '#states' => [
        'visible' => [
          ':input[name*="render_as"]' => ['value' => 'code'],
        ],
      ],
    ];

    $elements['depth'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum depth'),
      '#default_value' => $this->getSetting('depth'),
      '#min' => 1,
      '#max' => 2048,
      '#description' => $this->t('Maximum depth for displaying nested structures.'),
    ];

    $elements['show_keys'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show object keys'),
      '#default_value' => $this->getSetting('show_keys'),
      '#states' => [
        'visible' => [
          ':input[name*="render_as"]' => ['value' => 'table'],
        ],
      ],
    ];

    $elements['collapsible'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Make collapsible'),
      '#default_value' => $this->getSetting('collapsible'),
      '#description' => $this->t('Allow users to expand/collapse the JSON display.'),
    ];

    $elements['collapsed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Initially collapsed'),
      '#default_value' => $this->getSetting('collapsed'),
      '#states' => [
        'visible' => [
          ':input[name*="collapsible"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $elements['syntax_highlighting'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable syntax highlighting'),
      '#default_value' => $this->getSetting('syntax_highlighting'),
      '#states' => [
        'visible' => [
          ':input[name*="render_as"]' => ['value' => 'code'],
        ],
      ],
    ];

    $elements['theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Syntax theme'),
      '#options' => [
        'default' => $this->t('Default'),
        'dark' => $this->t('Dark'),
        'light' => $this->t('Light'),
        'monokai' => $this->t('Monokai'),
        'solarized' => $this->t('Solarized'),
      ],
      '#default_value' => $this->getSetting('theme'),
      '#states' => [
        'visible' => [
          ':input[name*="syntax_highlighting"]' => ['checked' => TRUE],
          ':input[name*="render_as"]' => ['value' => 'code'],
        ],
      ],
    ];

    $elements['show_toolbar'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show toolbar'),
      '#default_value' => $this->getSetting('show_toolbar'),
      '#description' => $this->t('Display copy/download buttons.'),
    ];

    $elements['max_height'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum height (pixels)'),
      '#default_value' => $this->getSetting('max_height'),
      '#min' => 0,
      '#description' => $this->t('Set to 0 for no limit. Content will scroll if it exceeds this height.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $render_as = $this->getSetting('render_as');
    $render_options = [
      'code' => $this->t('Code block'),
      'table' => $this->t('Table'),
      'tree' => $this->t('Tree view'),
      'raw' => $this->t('Raw text'),
      'php_array' => $this->t('PHP array'),
    ];
    $summary[] = $this->t('Display as: @type', ['@type' => $render_options[$render_as]]);

    if ($render_as === 'code') {
      $format = $this->getSetting('format');
      $summary[] = $this->t('Format: @format', ['@format' => ucfirst($format)]);

      if ($this->getSetting('syntax_highlighting')) {
        $summary[] = $this->t('Syntax highlighting: @theme theme', [
          '@theme' => $this->getSetting('theme'),
        ]);
      }
    }

    if ($this->getSetting('collapsible')) {
      $summary[] = $this->getSetting('collapsed') 
        ? $this->t('Collapsible (initially collapsed)') 
        : $this->t('Collapsible');
    }

    if ($this->getSetting('show_toolbar')) {
      $summary[] = $this->t('Toolbar enabled');
    }

    $max_height = $this->getSetting('max_height');
    if ($max_height > 0) {
      $summary[] = $this->t('Max height: @height px', ['@height' => $max_height]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      if (empty($item->value)) {
        continue;
      }

      $decoded = json_decode($item->value, TRUE);
      if (json_last_error() !== JSON_ERROR_NONE) {
        $elements[$delta] = [
          '#markup' => $this->t('Invalid JSON: @error', [
            '@error' => json_last_error_msg(),
          ]),
          '#prefix' => '<div class="json-error">',
          '#suffix' => '</div>',
        ];
        continue;
      }

      // Build the render array based on the render_as setting.
      $render_as = $this->getSetting('render_as');
      switch ($render_as) {
        case 'table':
          $elements[$delta] = $this->renderAsTable($decoded, $delta);
          break;

        case 'tree':
          $elements[$delta] = $this->renderAsTree($decoded, $delta);
          break;

        case 'raw':
          $elements[$delta] = $this->renderAsRaw($item->value, $delta);
          break;

        case 'php_array':
          $elements[$delta] = $this->renderAsPhpArray($decoded, $delta);
          break;

        case 'code':
        default:
          $elements[$delta] = $this->renderAsCode($item->value, $decoded, $delta);
          break;
      }

      // Add wrapper for collapsible functionality.
      if ($this->getSetting('collapsible')) {
        $elements[$delta] = [
          '#type' => 'details',
          '#title' => $this->t('JSON Data'),
          '#open' => !$this->getSetting('collapsed'),
          'content' => $elements[$delta],
        ];
      }

      // Add toolbar if enabled.
      if ($this->getSetting('show_toolbar')) {
        $elements[$delta]['#prefix'] = $this->buildToolbar($item->value, $delta) . 
          ($elements[$delta]['#prefix'] ?? '');
      }

      // Apply max height if set.
      $max_height = $this->getSetting('max_height');
      if ($max_height > 0) {
        $elements[$delta]['#attributes']['style'] = 'max-height: ' . $max_height . 'px; overflow: auto;';
      }
    }

    return $elements;
  }

  /**
   * Renders JSON as a formatted code block.
   *
   * @param string $json
   *   The raw JSON string.
   * @param mixed $decoded
   *   The decoded JSON data.
   * @param int $delta
   *   The field delta.
   *
   * @return array
   *   The render array.
   */
  protected function renderAsCode($json, $decoded, $delta) {
    $format = $this->getSetting('format');
    
    // Format the JSON based on settings.
    switch ($format) {
      case 'minified':
        $formatted = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

      case 'inline':
        $formatted = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

      case 'pretty':
      default:
        $formatted = json_encode($decoded, 
          JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;
    }

    $element = [
      '#type' => 'html_tag',
      '#tag' => 'pre',
      '#attributes' => [
        'class' => ['json-code', 'json-formatter'],
        'data-delta' => $delta,
      ],
      'code' => [
        '#type' => 'html_tag',
        '#tag' => 'code',
        '#value' => $formatted,
        '#attributes' => [
          'class' => ['language-json'],
        ],
      ],
    ];

    // Add syntax highlighting if enabled.
    if ($this->getSetting('syntax_highlighting')) {
      $element['#attached']['library'][] = 'component_entity/json-syntax';
      $element['#attributes']['data-theme'] = $this->getSetting('theme');
    }

    return $element;
  }

  /**
   * Renders JSON as a table.
   *
   * @param mixed $data
   *   The decoded JSON data.
   * @param int $delta
   *   The field delta.
   *
   * @return array
   *   The render array.
   */
  protected function renderAsTable($data, $delta) {
    $rows = $this->buildTableRows($data, 0);
    
    $element = [
      '#type' => 'table',
      '#header' => [
        $this->t('Key'),
        $this->t('Value'),
        $this->t('Type'),
      ],
      '#rows' => $rows,
      '#attributes' => [
        'class' => ['json-table', 'json-formatter'],
        'data-delta' => $delta,
      ],
      '#empty' => $this->t('No data to display.'),
    ];

    return $element;
  }

  /**
   * Builds table rows from JSON data.
   *
   * @param mixed $data
   *   The data to convert to rows.
   * @param int $depth
   *   The current depth level.
   * @param string $prefix
   *   The key prefix for nested items.
   *
   * @return array
   *   Array of table rows.
   */
  protected function buildTableRows($data, $depth = 0, $prefix = '') {
    $rows = [];
    $max_depth = $this->getSetting('depth');

    if ($depth >= $max_depth) {
      return [[
        $prefix,
        $this->t('(Max depth reached)'),
        'truncated',
      ]];
    }

    if (is_array($data) || is_object($data)) {
      foreach ($data as $key => $value) {
        $full_key = $prefix ? $prefix . '.' . $key : $key;
        
        if (is_array($value) || is_object($value)) {
          if ($this->getSetting('show_keys')) {
            $rows[] = [
              ['data' => $full_key, 'class' => ['json-key', 'depth-' . $depth]],
              ['data' => $this->t('(@count items)', ['@count' => count((array) $value)]), 'class' => ['json-value']],
              ['data' => gettype($value), 'class' => ['json-type']],
            ];
          }
          
          // Recursively add nested items.
          $nested_rows = $this->buildTableRows($value, $depth + 1, $full_key);
          $rows = array_merge($rows, $nested_rows);
        }
        else {
          $rows[] = [
            ['data' => $full_key, 'class' => ['json-key', 'depth-' . $depth]],
            ['data' => $this->formatValue($value), 'class' => ['json-value']],
            ['data' => gettype($value), 'class' => ['json-type']],
          ];
        }
      }
    }
    else {
      $rows[] = [
        ['data' => $prefix ?: '(root)', 'class' => ['json-key']],
        ['data' => $this->formatValue($data), 'class' => ['json-value']],
        ['data' => gettype($data), 'class' => ['json-type']],
      ];
    }

    return $rows;
  }

  /**
   * Renders JSON as a tree view.
   *
   * @param mixed $data
   *   The decoded JSON data.
   * @param int $delta
   *   The field delta.
   *
   * @return array
   *   The render array.
   */
  protected function renderAsTree($data, $delta) {
    $tree = $this->buildTree($data);
    
    $element = [
      '#theme' => 'item_list',
      '#items' => $tree,
      '#attributes' => [
        'class' => ['json-tree', 'json-formatter'],
        'data-delta' => $delta,
      ],
      '#attached' => [
        'library' => ['component_entity/json-tree'],
      ],
    ];

    return $element;
  }

  /**
   * Builds a tree structure from JSON data.
   *
   * @param mixed $data
   *   The data to convert to a tree.
   * @param int $depth
   *   The current depth level.
   *
   * @return array
   *   Array of tree items.
   */
  protected function buildTree($data, $depth = 0) {
    $items = [];
    $max_depth = $this->getSetting('depth');

    if ($depth >= $max_depth) {
      return [$this->t('(Max depth reached)')];
    }

    if (is_array($data) || is_object($data)) {
      foreach ($data as $key => $value) {
        if (is_array($value) || is_object($value)) {
          $children = $this->buildTree($value, $depth + 1);
          $items[] = [
            '#markup' => '<span class="json-tree-key">' . $key . '</span>',
            'children' => [
              '#theme' => 'item_list',
              '#items' => $children,
            ],
          ];
        }
        else {
          $items[] = [
            '#markup' => '<span class="json-tree-key">' . $key . '</span>: ' . 
              '<span class="json-tree-value">' . $this->formatValue($value) . '</span>',
          ];
        }
      }
    }
    else {
      $items[] = [
        '#markup' => '<span class="json-tree-value">' . $this->formatValue($data) . '</span>',
      ];
    }

    return $items;
  }

  /**
   * Renders JSON as raw text.
   *
   * @param string $json
   *   The raw JSON string.
   * @param int $delta
   *   The field delta.
   *
   * @return array
   *   The render array.
   */
  protected function renderAsRaw($json, $delta) {
    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $json,
      '#attributes' => [
        'class' => ['json-raw', 'json-formatter'],
        'data-delta' => $delta,
      ],
    ];
  }

  /**
   * Renders JSON as PHP array syntax.
   *
   * @param mixed $data
   *   The decoded JSON data.
   * @param int $delta
   *   The field delta.
   *
   * @return array
   *   The render array.
   */
  protected function renderAsPhpArray($data, $delta) {
    $php_code = var_export($data, TRUE);
    
    return [
      '#type' => 'html_tag',
      '#tag' => 'pre',
      '#attributes' => [
        'class' => ['php-array', 'json-formatter'],
        'data-delta' => $delta,
      ],
      'code' => [
        '#type' => 'html_tag',
        '#tag' => 'code',
        '#value' => $php_code,
        '#attributes' => [
          'class' => ['language-php'],
        ],
      ],
    ];
  }

  /**
   * Formats a scalar value for display.
   *
   * @param mixed $value
   *   The value to format.
   *
   * @return string
   *   The formatted value.
   */
  protected function formatValue($value) {
    if (is_bool($value)) {
      return $value ? 'true' : 'false';
    }
    elseif (is_null($value)) {
      return 'null';
    }
    elseif (is_string($value)) {
      // Truncate long strings.
      if (strlen($value) > 100) {
        return substr($value, 0, 100) . '...';
      }
      return $value;
    }
    else {
      return (string) $value;
    }
  }

  /**
   * Builds a toolbar for JSON actions.
   *
   * @param string $json
   *   The JSON string.
   * @param int $delta
   *   The field delta.
   *
   * @return string
   *   The toolbar HTML.
   */
  protected function buildToolbar($json, $delta) {
    $toolbar = '<div class="json-toolbar" data-delta="' . $delta . '">';
    
    $toolbar .= '<button type="button" class="json-copy-btn" data-json="' . 
      htmlspecialchars($json, ENT_QUOTES) . '">' . $this->t('Copy') . '</button>';
    
    $toolbar .= '<button type="button" class="json-download-btn" data-json="' . 
      htmlspecialchars($json, ENT_QUOTES) . '">' . $this->t('Download') . '</button>';
    
    $toolbar .= '<button type="button" class="json-expand-btn">' . 
      $this->t('Expand All') . '</button>';
    
    $toolbar .= '<button type="button" class="json-collapse-btn">' . 
      $this->t('Collapse All') . '</button>';
    
    $toolbar .= '</div>';
    
    return $toolbar;
  }

}