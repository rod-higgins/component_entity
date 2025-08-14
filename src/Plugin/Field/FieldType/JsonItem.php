<?php

namespace Drupal\component_entity\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'json' field type.
 *
 * @FieldType(
 *   id = "json",
 *   label = @Translation("JSON"),
 *   description = @Translation("Stores JSON data for component properties"),
 *   default_widget = "json_textarea",
 *   default_formatter = "json_formatted",
 *   category = @Translation("Component Entity")
 * )
 */
class JsonItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'max_length' => 0,
      'schema' => '',
      'validate_schema' => FALSE,
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'schema' => '',
      'validate_schema' => FALSE,
      'pretty_print' => TRUE,
      'depth' => 512,
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('JSON value'))
      ->setDescription(new TranslatableMarkup('The JSON data as a string'))
      ->setRequired(TRUE);

    $properties['decoded'] = DataDefinition::create('any')
      ->setLabel(new TranslatableMarkup('Decoded value'))
      ->setDescription(new TranslatableMarkup('The decoded JSON data'))
      ->setComputed(TRUE)
      ->setInternal(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = [
      'columns' => [
        'value' => [
          'type' => 'text',
          'size' => 'big',
          'not null' => FALSE,
        ],
      ],
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = parent::getConstraints();

    // Add JSON validation constraint.
    $constraints[] = $this->getTypedDataManager()
      ->getValidationConstraintManager()
      ->create('ComplexData', [
        'value' => [
          'Callback' => [
            'callback' => [$this, 'validateJson'],
            'message' => 'Invalid JSON format.',
          ],
        ],
      ]);

    return $constraints;
  }

  /**
   * Validates JSON data.
   *
   * @param mixed $value
   *   The value to validate.
   * @param \Symfony\Component\Validator\Context\ExecutionContextInterface $context
   *   The validation context.
   */
  public function validateJson($value, $context = NULL) {
    if (empty($value)) {
      return;
    }

    // Attempt to decode JSON.
    $decoded = json_decode($value, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      if ($context) {
        $context->addViolation('Invalid JSON: @error', [
          '@error' => json_last_error_msg(),
        ]);
      }
      return;
    }

    // Validate against schema if enabled.
    if ($this->getSetting('validate_schema') && $schema = $this->getSetting('schema')) {
      $this->validateAgainstSchema($decoded, $schema, $context);
    }
  }

  /**
   * Validates data against a JSON schema.
   *
   * @param mixed $data
   *   The data to validate.
   * @param string $schema
   *   The JSON schema.
   * @param \Symfony\Component\Validator\Context\ExecutionContextInterface $context
   *   The validation context.
   */
  protected function validateAgainstSchema($data, $schema, $context = NULL) {
    // This would integrate with a JSON Schema validator library.
    // For now, we'll implement basic structure validation.
    $schema_obj = json_decode($schema, TRUE);
    if (!$schema_obj) {
      return;
    }

    // Basic type validation.
    if (isset($schema_obj['type'])) {
      $type = gettype($data);
      $expected = $schema_obj['type'];

      $type_map = [
        'integer' => 'integer',
        'double' => 'number',
        'string' => 'string',
        'boolean' => 'boolean',
        'array' => 'array',
        'object' => 'object',
        'NULL' => 'null',
      ];

      if (isset($type_map[$type]) && $type_map[$type] !== $expected) {
        if ($context) {
          $context->addViolation('Expected type @expected, got @actual', [
            '@expected' => $expected,
            '@actual' => $type_map[$type],
          ]);
        }
      }
    }

    // Required properties validation for objects.
    if (isset($schema_obj['required']) && is_array($data)) {
      foreach ($schema_obj['required'] as $required_prop) {
        if (!isset($data[$required_prop])) {
          if ($context) {
            $context->addViolation('Missing required property: @prop', [
              '@prop' => $required_prop,
            ]);
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $elements = parent::storageSettingsForm($form, $form_state, $has_data);

    $elements['schema'] = [
      '#type' => 'textarea',
      '#title' => $this->t('JSON Schema'),
      '#description' => $this->t('Optional JSON Schema for validation (JSON format).'),
      '#default_value' => $this->getSetting('schema'),
      '#rows' => 10,
      '#disabled' => $has_data,
    ];

    $elements['validate_schema'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Validate against schema'),
      '#description' => $this->t('Enable JSON Schema validation.'),
      '#default_value' => $this->getSetting('validate_schema'),
      '#disabled' => $has_data,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::fieldSettingsForm($form, $form_state);

    $elements['pretty_print'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Pretty print JSON'),
      '#description' => $this->t('Format JSON with indentation when displaying.'),
      '#default_value' => $this->getSetting('pretty_print'),
    ];

    $elements['depth'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum depth'),
      '#description' => $this->t('Maximum depth for encoding/decoding JSON.'),
      '#default_value' => $this->getSetting('depth'),
      '#min' => 1,
      '#max' => 2048,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('value')->getValue();
    return $value === NULL || $value === '' || $value === '{}' || $value === '[]' || $value === 'null';
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();

    // Ensure value is valid JSON.
    $value = $this->get('value')->getValue();
    if (!empty($value)) {
      // Try to decode and re-encode to normalize.
      $decoded = json_decode($value, TRUE);
      if (json_last_error() === JSON_ERROR_NONE) {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($this->getSetting('pretty_print')) {
          $flags |= JSON_PRETTY_PRINT;
        }
        $this->set('value', json_encode($decoded, $flags));
      }
    }
  }

  /**
   * Gets the decoded JSON value.
   *
   * @return mixed
   *   The decoded JSON data.
   */
  public function getDecodedValue() {
    $value = $this->get('value')->getValue();
    if (empty($value)) {
      return NULL;
    }

    $decoded = json_decode($value, TRUE, $this->getSetting('depth'));
    if (json_last_error() !== JSON_ERROR_NONE) {
      \Drupal::logger('component_entity')->error('JSON decode error: @error', [
        '@error' => json_last_error_msg(),
      ]);
      return NULL;
    }

    return $decoded;
  }

  /**
   * Sets the value from decoded data.
   *
   * @param mixed $data
   *   The data to encode as JSON.
   *
   * @return $this
   */
  public function setDecodedValue($data) {
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if ($this->getSetting('pretty_print')) {
      $flags |= JSON_PRETTY_PRINT;
    }

    $json = json_encode($data, $flags, $this->getSetting('depth'));
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \InvalidArgumentException('Failed to encode data as JSON: ' . json_last_error_msg());
    }

    $this->set('value', $json);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldStorageDefinitionInterface $field_definition) {
    $sample_data = [
      'id' => rand(1, 1000),
      'name' => 'Sample ' . rand(1, 100),
      'enabled' => (bool) rand(0, 1),
      'tags' => ['tag1', 'tag2', 'tag3'],
      'metadata' => [
        'created' => date('Y-m-d H:i:s'),
        'version' => '1.0.' . rand(0, 99),
      ],
    ];

    return [
      'value' => json_encode($sample_data, JSON_PRETTY_PRINT),
    ];
  }

}
