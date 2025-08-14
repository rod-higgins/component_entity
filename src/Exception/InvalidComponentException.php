<?php

namespace Drupal\component_entity\Exception;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Exception thrown when a component entity is invalid.
 *
 * This exception is used when a component entity fails validation,
 * has missing required fields, or contains invalid data.
 */
class InvalidComponentException extends \Exception {

  use StringTranslationTrait;

  /**
   * The component entity that is invalid.
   *
   * @var \Drupal\component_entity\Entity\ComponentEntityInterface|null
   */
  protected $component;

  /**
   * The validation violations.
   *
   * @var array
   */
  protected $violations = [];

  /**
   * The field that caused the exception.
   *
   * @var string|null
   */
  protected $fieldName;

  /**
   * Validation error types.
   */
  const MISSING_REQUIRED_FIELD = 'missing_required';
  const INVALID_FIELD_VALUE = 'invalid_value';
  const INVALID_FIELD_TYPE = 'invalid_type';
  const FIELD_CARDINALITY_EXCEEDED = 'cardinality_exceeded';
  const INVALID_RENDER_METHOD = 'invalid_render_method';
  const MISSING_SDC_COMPONENT = 'missing_sdc_component';
  const INVALID_PROPS = 'invalid_props';
  const INVALID_SLOTS = 'invalid_slots';
  const SCHEMA_VALIDATION_FAILED = 'schema_validation_failed';
  const CIRCULAR_REFERENCE = 'circular_reference';

  /**
   * The validation error type.
   *
   * @var string
   */
  protected $errorType;

  /**
   * Additional error details.
   *
   * @var array
   */
  protected $details = [];

  /**
   * Constructs an InvalidComponentException.
   *
   * @param string $message
   *   The exception message.
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface|null $component
   *   The invalid component entity.
   * @param string $error_type
   *   The type of validation error.
   * @param array $violations
   *   Array of validation violations.
   * @param string|null $field_name
   *   The field that caused the error.
   * @param array $details
   *   Additional error details.
   * @param int $code
   *   The exception code.
   * @param \Throwable|null $previous
   *   The previous throwable.
   */
  public function __construct(
    $message = '',
    $component = NULL,
    $error_type = self::INVALID_FIELD_VALUE,
    array $violations = [],
    $field_name = NULL,
    array $details = [],
    $code = 0,
    ?\Throwable $previous = NULL,
  ) {
    parent::__construct($message, $code, $previous);
    $this->component = $component;
    $this->errorType = $error_type;
    $this->violations = $violations;
    $this->fieldName = $field_name;
    $this->details = $details;
  }

  /**
   * Creates an exception for missing required fields.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $component
   *   The component entity.
   * @param array $missing_fields
   *   Array of missing field names.
   *
   * @return static
   */
  public static function missingRequiredFields($component, array $missing_fields) {
    $message = sprintf(
      'Component "%s" is missing required fields: %s',
      $component->label() ?? $component->id(),
      implode(', ', $missing_fields)
    );

    $exception = new static(
      $message,
      $component,
      self::MISSING_REQUIRED_FIELD,
      [],
      NULL,
      ['missing_fields' => $missing_fields]
    );

    $exception->component = $component;
    return $exception;
  }

  /**
   * Creates an exception for invalid field values.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $component
   *   The component entity.
   * @param string $field_name
   *   The field name.
   * @param mixed $value
   *   The invalid value.
   * @param string|null $reason
   *   Optional reason for invalidity.
   *
   * @return static
   */
  public static function invalidFieldValue($component, $field_name, $value, $reason = NULL) {
    $message = sprintf(
      'Invalid value for field "%s" in component "%s"',
      $field_name,
      $component->label() ?? $component->id()
    );

    if ($reason) {
      $message .= ': ' . $reason;
    }

    $exception = new static(
      $message,
      $component,
      self::INVALID_FIELD_VALUE,
      [],
      $field_name,
      ['value' => $value, 'reason' => $reason]
    );

    return $exception;
  }

  /**
   * Creates an exception for invalid field types.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $component
   *   The component entity.
   * @param string $field_name
   *   The field name.
   * @param string $expected_type
   *   The expected type.
   * @param string $actual_type
   *   The actual type.
   *
   * @return static
   */
  public static function invalidFieldType($component, $field_name, $expected_type, $actual_type) {
    $message = sprintf(
      'Field "%s" in component "%s" expects type "%s" but got "%s"',
      $field_name,
      $component->label() ?? $component->id(),
      $expected_type,
      $actual_type
    );

    return new static(
      $message,
      $component,
      self::INVALID_FIELD_TYPE,
      [],
      $field_name,
      ['expected_type' => $expected_type, 'actual_type' => $actual_type]
    );
  }

  /**
   * Creates an exception for field cardinality violations.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $component
   *   The component entity.
   * @param string $field_name
   *   The field name.
   * @param int $max_cardinality
   *   The maximum allowed cardinality.
   * @param int $actual_count
   *   The actual number of values.
   *
   * @return static
   */
  public static function cardinalityExceeded($component, $field_name, $max_cardinality, $actual_count) {
    $message = sprintf(
      'Field "%s" in component "%s" allows maximum %d values but %d were provided',
      $field_name,
      $component->label() ?? $component->id(),
      $max_cardinality,
      $actual_count
    );

    return new static(
      $message,
      $component,
      self::FIELD_CARDINALITY_EXCEEDED,
      [],
      $field_name,
      ['max_cardinality' => $max_cardinality, 'actual_count' => $actual_count]
    );
  }

  /**
   * Creates an exception for invalid render method.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $component
   *   The component entity.
   * @param string $render_method
   *   The invalid render method.
   *
   * @return static
   */
  public static function invalidRenderMethod($component, $render_method) {
    $message = sprintf(
      'Invalid render method "%s" for component "%s". Valid methods are: twig, react',
      $render_method,
      $component->label() ?? $component->id()
    );

    return new static(
      $message,
      $component,
      self::INVALID_RENDER_METHOD,
      [],
      NULL,
      ['method' => $render_method]
    );
  }

  /**
   * Creates an exception for missing SDC component.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $component
   *   The component entity.
   * @param string $sdc_id
   *   The missing SDC component ID.
   *
   * @return static
   */
  public static function missingSdcComponent($component, $sdc_id) {
    $message = sprintf(
      'SDC component "%s" not found for component entity "%s"',
      $sdc_id,
      $component->label() ?? $component->id()
    );

    if ($component && method_exists($component, 'bundle')) {
      $message .= sprintf(' (bundle: %s)', $component->bundle());
    }

    return new static(
      $message,
      $component,
      self::MISSING_SDC_COMPONENT,
      [],
      NULL,
      ['sdc_id' => $sdc_id]
    );
  }

  /**
   * Creates an exception for invalid props.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $component
   *   The component entity.
   * @param array $violations
   *   Array of prop violations.
   *
   * @return static
   */
  public static function invalidProps($component, array $violations) {
    $message = sprintf(
      'Invalid props for component "%s": %s',
      $component->label() ?? $component->id(),
      implode('; ', $violations)
    );

    return new static(
      $message,
      $component,
      self::INVALID_PROPS,
      $violations,
      NULL,
      ['prop_violations' => $violations]
    );
  }

  /**
   * Creates an exception for invalid slots.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $component
   *   The component entity.
   * @param array $violations
   *   Array of slot violations.
   *
   * @return static
   */
  public static function invalidSlots($component, array $violations) {
    $message = sprintf(
      'Invalid slots for component "%s": %s',
      $component->label() ?? $component->id(),
      implode('; ', $violations)
    );

    return new static(
      $message,
      $component,
      self::INVALID_SLOTS,
      $violations,
      NULL,
      ['slot_violations' => $violations]
    );
  }

  /**
   * Creates an exception for schema validation failures.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $component
   *   The component entity.
   * @param array $violations
   *   Array of schema violations.
   *
   * @return static
   */
  public static function schemaValidationFailed($component, array $violations) {
    $message = sprintf(
      'Schema validation failed for component "%s": %s',
      $component->label() ?? $component->id(),
      implode('; ', $violations)
    );

    return new static(
      $message,
      $component,
      self::SCHEMA_VALIDATION_FAILED,
      $violations
    );
  }

  /**
   * Creates an exception for circular references.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $component
   *   The component entity.
   * @param array $reference_chain
   *   Array showing the circular reference chain.
   *
   * @return static
   */
  public static function circularReference($component, array $reference_chain) {
    $message = sprintf(
      'Circular reference detected in component "%s": %s',
      $component->id(),
      implode(' → ', $reference_chain)
    );

    return new static(
      $message,
      $component,
      self::CIRCULAR_REFERENCE,
      [],
      NULL,
      ['reference_chain' => $reference_chain]
    );
  }

  /**
   * Gets the component entity.
   *
   * @return \Drupal\component_entity\Entity\ComponentEntityInterface|null
   *   The component entity or NULL.
   */
  public function getComponent() {
    return $this->component;
  }

  /**
   * Gets the validation violations.
   *
   * @return array
   *   Array of violations.
   */
  public function getViolations() {
    return $this->violations;
  }

  /**
   * Gets the field name that caused the error.
   *
   * @return string|null
   *   The field name or NULL.
   */
  public function getFieldName() {
    return $this->fieldName;
  }

  /**
   * Gets the error type.
   *
   * @return string
   *   The error type constant.
   */
  public function getErrorType() {
    return $this->errorType;
  }

  /**
   * Gets additional error details.
   *
   * @return array
   *   Array of additional details.
   */
  public function getDetails() {
    return $this->details;
  }

  /**
   * Gets a user-friendly error message.
   *
   * @return string
   *   A message suitable for display to users.
   */
  public function getUserMessage() {
    switch ($this->errorType) {
      case self::MISSING_REQUIRED_FIELD:
        return $this->t('Please fill in all required fields.');

      case self::INVALID_FIELD_VALUE:
        return $this->t('The value entered for @field is invalid.', ['@field' => $this->fieldName]);

      case self::INVALID_FIELD_TYPE:
        return $this->t('The data type for @field is incorrect.', ['@field' => $this->fieldName]);

      case self::FIELD_CARDINALITY_EXCEEDED:
        return $this->t('Too many values provided for @field.', ['@field' => $this->fieldName]);

      case self::INVALID_RENDER_METHOD:
        return $this->t('The selected render method is not available for this component.');

      case self::MISSING_SDC_COMPONENT:
        return $this->t('The component template is missing or unavailable.');

      case self::INVALID_PROPS:
        return $this->t('The component properties contain invalid values.');

      case self::INVALID_SLOTS:
        return $this->t('The component slots contain invalid content.');

      case self::SCHEMA_VALIDATION_FAILED:
        return $this->t('The component data does not match the expected format.');

      case self::CIRCULAR_REFERENCE:
        return $this->t('This component references itself, creating an infinite loop.');

      default:
        return $this->t('The component contains invalid data.');
    }
  }

  /**
   * Gets validation messages as an array.
   *
   * @return array
   *   Array of validation messages.
   */
  public function getValidationMessages() {
    $messages = [];

    // Add main message.
    $messages[] = $this->getMessage();

    // Add violation messages.
    foreach ($this->violations as $violation) {
      if (is_object($violation) && method_exists($violation, 'getMessage')) {
        $messages[] = $violation->getMessage();
      }
      else {
        $messages[] = (string) $violation;
      }
    }

    return array_unique($messages);
  }

  /**
   * Converts the exception to an array for logging or API responses.
   *
   * @return array
   *   Array representation of the exception.
   */
  public function toArray() {
    $data = [
      'message' => $this->getMessage(),
      'error_type' => $this->errorType,
      'field_name' => $this->fieldName,
      'violations' => $this->getValidationMessages(),
      'details' => $this->details,
      'user_message' => $this->getUserMessage(),
    ];

    if ($this->component) {
      $data['component'] = [
        'id' => $this->component->id(),
        'bundle' => $this->component->bundle(),
        'label' => $this->component->label(),
      ];
    }

    return $data;
  }

}
