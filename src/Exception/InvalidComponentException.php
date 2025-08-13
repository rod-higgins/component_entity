<?php

namespace Drupal\component_entity\Exception;

/**
 * Exception thrown when a component entity is invalid.
 *
 * This exception is used when a component entity fails validation,
 * has missing required fields, or contains invalid data.
 */
class InvalidComponentException extends \Exception {

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
    \Throwable $previous = NULL
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
    
    return new static(
      $message,
      $component,
      self::MISSING_REQUIRED_FIELD,
      [],
      NULL,
      ['missing_fields' => $missing_fields]
    );
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
   * @param string $reason
   *   The reason why the value is invalid.
   *
   * @return static
   */
  public static function invalidFieldValue($component, $field_name, $value, $reason) {
    $message = sprintf(
      'Invalid value for field "%s" in component "%s": %s',
      $field_name,
      $component->label() ?? $component->id(),
      $reason
    );
    
    return new static(
      $message,
      $component,
      self::INVALID_FIELD_VALUE,
      [],
      $field_name,
      ['value' => $value, 'reason' => $reason]
    );
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
   * @param array $allowed_methods
   *   Array of allowed render methods.
   *
   * @return static
   */
  public static function invalidRenderMethod($component, $render_method, array $allowed_methods) {
    $message = sprintf(
      'Invalid render method "%s" for component "%s". Allowed methods: %s',
      $render_method,
      $component->label() ?? $component->id(),
      implode(', ', $allowed_methods)
    );
    
    return new static(
      $message,
      $component,
      self::INVALID_RENDER_METHOD,
      [],
      NULL,
      ['render_method' => $render_method, 'allowed_methods' => $allowed_methods]
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
   * @param array $invalid_props
   *   Array of invalid prop names and reasons.
   *
   * @return static
   */
  public static function invalidProps($component, array $invalid_props) {
    $prop_messages = [];
    foreach ($invalid_props as $prop => $reason) {
      $prop_messages[] = sprintf('%s: %s', $prop, $reason);
    }
    
    $message = sprintf(
      'Invalid props for component "%s": %s',
      $component->label() ?? $component->id(),
      implode('; ', $prop_messages)
    );
    
    return new static(
      $message,
      $component,
      self::INVALID_PROPS,
      [],
      NULL,
      ['invalid_props' => $invalid_props]
    );
  }

  /**
   * Creates an exception for invalid slots.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $component
   *   The component entity.
   * @param array $invalid_slots
   *   Array of invalid slot names and reasons.
   *
   * @return static
   */
  public static function invalidSlots($component, array $invalid_slots) {
    $slot_messages = [];
    foreach ($invalid_slots as $slot => $reason) {
      $slot_messages[] = sprintf('%s: %s', $slot, $reason);
    }
    
    $message = sprintf(
      'Invalid slots for component "%s": %s',
      $component->label() ?? $component->id(),
      implode('; ', $slot_messages)
    );
    
    return new static(
      $message,
      $component,
      self::INVALID_SLOTS,
      [],
      NULL,
      ['invalid_slots' => $invalid_slots]
    );
  }

  /**
   * Creates an exception for schema validation failures.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $component
   *   The component entity.
   * @param array $schema_errors
   *   Array of schema validation errors.
   *
   * @return static
   */
  public static function schemaValidationFailed($component, array $schema_errors) {
    $message = sprintf(
      'Schema validation failed for component "%s": %s',
      $component->label() ?? $component->id(),
      implode('; ', $schema_errors)
    );
    
    return new static(
      $message,
      $component,
      self::SCHEMA_VALIDATION_FAILED,
      $schema_errors,
      NULL,
      ['schema_errors' => $schema_errors]
    );
  }

  /**
   * Creates an exception for circular references.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $component
   *   The component entity.
   * @param array $reference_chain
   *   The chain of references that forms a circle.
   *
   * @return static
   */
  public static function circularReference($component, array $reference_chain) {
    $message = sprintf(
      'Circular reference detected in component "%s": %s',
      $component->label() ?? $component->id(),
      implode(' -> ', $reference_chain)
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
   *   The error type.
   */
  public function getErrorType() {
    return $this->errorType;
  }

  /**
   * Gets the error details.
   *
   * @return array
   *   The error details.
   */
  public function getDetails() {
    return $this->details;
  }

  /**
   * Gets a specific detail value.
   *
   * @param string $key
   *   The detail key.
   * @param mixed $default
   *   Default value if key doesn't exist.
   *
   * @return mixed
   *   The detail value.
   */
  public function getDetail($key, $default = NULL) {
    return $this->details[$key] ?? $default;
  }

  /**
   * Checks if the error is related to a specific field.
   *
   * @param string $field_name
   *   The field name to check.
   *
   * @return bool
   *   TRUE if the error is related to the field.
   */
  public function isFieldError($field_name) {
    return $this->fieldName === $field_name;
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
        return t('Please fill in all required fields.');
        
      case self::INVALID_FIELD_VALUE:
        return t('The value entered for @field is invalid.', ['@field' => $this->fieldName]);
        
      case self::INVALID_FIELD_TYPE:
        return t('The data type for @field is incorrect.', ['@field' => $this->fieldName]);
        
      case self::FIELD_CARDINALITY_EXCEEDED:
        return t('Too many values provided for @field.', ['@field' => $this->fieldName]);
        
      case self::INVALID_RENDER_METHOD:
        return t('The selected render method is not available for this component.');
        
      case self::MISSING_SDC_COMPONENT:
        return t('The component template is missing or unavailable.');
        
      case self::INVALID_PROPS:
        return t('The component properties contain invalid values.');
        
      case self::INVALID_SLOTS:
        return t('The component slots contain invalid content.');
        
      case self::SCHEMA_VALIDATION_FAILED:
        return t('The component data does not match the expected format.');
        
      case self::CIRCULAR_REFERENCE:
        return t('This component references itself, creating an infinite loop.');
        
      default:
        return t('The component contains invalid data.');
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
      } else {
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