<?php

namespace Drupal\component_entity\Exception;

/**
 * Exception thrown during component synchronization operations.
 *
 * This exception is used to indicate various sync-related errors,
 * such as invalid component definitions, field mapping failures,
 * or configuration conflicts.
 */
class ComponentSyncException extends \Exception {

  /**
   * The component ID that caused the exception.
   *
   * @var string|null
   */
  protected $componentId;

  /**
   * The bundle name involved in the exception.
   *
   * @var string|null
   */
  protected $bundle;

  /**
   * Additional context data about the exception.
   *
   * @var array
   */
  protected $context = [];

  /**
   * Error type constants.
   */
  const ERROR_INVALID_DEFINITION = 'invalid_definition';
  const ERROR_FIELD_MAPPING = 'field_mapping';
  const ERROR_BUNDLE_CONFLICT = 'bundle_conflict';
  const ERROR_PERMISSION_DENIED = 'permission_denied';
  const ERROR_MISSING_DEPENDENCY = 'missing_dependency';
  const ERROR_CONFIGURATION = 'configuration';
  const ERROR_VALIDATION = 'validation';

  /**
   * The error type.
   *
   * @var string
   */
  protected $errorType;

  /**
   * Constructs a ComponentSyncException.
   *
   * @param string $message
   *   The exception message.
   * @param string|null $component_id
   *   The component ID that caused the exception.
   * @param string|null $bundle
   *   The bundle name involved.
   * @param string $error_type
   *   The type of error.
   * @param array $context
   *   Additional context data.
   * @param int $code
   *   The exception code.
   * @param \Throwable|null $previous
   *   The previous throwable.
   */
  public function __construct(
    $message = '',
    $component_id = NULL,
    $bundle = NULL,
    $error_type = self::ERROR_CONFIGURATION,
    array $context = [],
    $code = 0,
    ?\Throwable $previous = NULL,
  ) {
    parent::__construct($message, $code, $previous);
    $this->componentId = $component_id;
    $this->bundle = $bundle;
    $this->errorType = $error_type;
    $this->context = $context;
  }

  /**
   * Creates an exception for invalid component definition.
   *
   * @param string $component_id
   *   The component ID.
   * @param string $reason
   *   The reason why the definition is invalid.
   * @param array $context
   *   Additional context.
   *
   * @return static
   */
  public static function invalidDefinition($component_id, $reason, array $context = []) {
    $message = sprintf(
      'Invalid component definition for "%s": %s',
      $component_id,
      $reason
    );

    return new static(
      $message,
      $component_id,
      NULL,
      self::ERROR_INVALID_DEFINITION,
      $context
    );
  }

  /**
   * Creates an exception for field mapping errors.
   *
   * @param string $component_id
   *   The component ID.
   * @param string $field_name
   *   The field name.
   * @param string $reason
   *   The reason for the mapping failure.
   * @param array $context
   *   Additional context.
   *
   * @return static
   */
  public static function fieldMappingError($component_id, $field_name, $reason, array $context = []) {
    $message = sprintf(
      'Field mapping error for component "%s", field "%s": %s',
      $component_id,
      $field_name,
      $reason
    );

    $context['field_name'] = $field_name;

    return new static(
      $message,
      $component_id,
      NULL,
      self::ERROR_FIELD_MAPPING,
      $context
    );
  }

  /**
   * Creates an exception for bundle conflicts.
   *
   * @param string $component_id
   *   The component ID.
   * @param string $bundle
   *   The conflicting bundle name.
   * @param string $reason
   *   The reason for the conflict.
   * @param array $context
   *   Additional context.
   *
   * @return static
   */
  public static function bundleConflict($component_id, $bundle, $reason, array $context = []) {
    $message = sprintf(
      'Bundle conflict for component "%s" with bundle "%s": %s',
      $component_id,
      $bundle,
      $reason
    );

    return new static(
      $message,
      $component_id,
      $bundle,
      self::ERROR_BUNDLE_CONFLICT,
      $context
    );
  }

  /**
   * Creates an exception for missing dependencies.
   *
   * @param string $component_id
   *   The component ID.
   * @param string $dependency
   *   The missing dependency.
   * @param array $context
   *   Additional context.
   *
   * @return static
   */
  public static function missingDependency($component_id, $dependency, array $context = []) {
    $message = sprintf(
      'Missing dependency for component "%s": %s',
      $component_id,
      $dependency
    );

    $context['dependency'] = $dependency;

    return new static(
      $message,
      $component_id,
      NULL,
      self::ERROR_MISSING_DEPENDENCY,
      $context
    );
  }

  /**
   * Creates an exception for permission denied errors.
   *
   * @param string $operation
   *   The operation that was denied.
   * @param string|null $component_id
   *   The component ID.
   * @param array $context
   *   Additional context.
   *
   * @return static
   */
  public static function permissionDenied($operation, $component_id = NULL, array $context = []) {
    $message = sprintf(
      'Permission denied for operation "%s"',
      $operation
    );

    if ($component_id) {
      $message .= sprintf(' on component "%s"', $component_id);
    }

    $context['operation'] = $operation;

    return new static(
      $message,
      $component_id,
      NULL,
      self::ERROR_PERMISSION_DENIED,
      $context
    );
  }

  /**
   * Creates an exception for validation errors.
   *
   * @param string $component_id
   *   The component ID.
   * @param array $violations
   *   Array of validation violations.
   * @param array $context
   *   Additional context.
   *
   * @return static
   */
  public static function validationError($component_id, array $violations, array $context = []) {
    $violation_messages = array_map(function ($violation) {
      return is_object($violation) && method_exists($violation, 'getMessage')
        ? $violation->getMessage()
        : (string) $violation;
    }, $violations);

    $message = sprintf(
      'Validation failed for component "%s": %s',
      $component_id,
      implode('; ', $violation_messages)
    );

    $context['violations'] = $violations;

    return new static(
      $message,
      $component_id,
      NULL,
      self::ERROR_VALIDATION,
      $context
    );
  }

  /**
   * Gets the component ID.
   *
   * @return string|null
   *   The component ID or NULL.
   */
  public function getComponentId() {
    return $this->componentId;
  }

  /**
   * Gets the bundle name.
   *
   * @return string|null
   *   The bundle name or NULL.
   */
  public function getBundle() {
    return $this->bundle;
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
   * Gets the exception context.
   *
   * @return array
   *   The context data.
   */
  public function getContext() {
    return $this->context;
  }

  /**
   * Gets a specific context value.
   *
   * @param string $key
   *   The context key.
   * @param mixed $default
   *   Default value if key doesn't exist.
   *
   * @return mixed
   *   The context value.
   */
  public function getContextValue($key, $default = NULL) {
    return $this->context[$key] ?? $default;
  }

  /**
   * Checks if this is a recoverable error.
   *
   * @return bool
   *   TRUE if the error is potentially recoverable.
   */
  public function isRecoverable() {
    // Permission and dependency errors might be recoverable.
    return in_array($this->errorType, [
      self::ERROR_PERMISSION_DENIED,
      self::ERROR_MISSING_DEPENDENCY,
    ]);
  }

  /**
   * Gets a user-friendly error message.
   *
   * @return string
   *   A message suitable for display to users.
   */
  public function getUserMessage() {
    switch ($this->errorType) {
      case self::ERROR_INVALID_DEFINITION:
        return t('The component definition is invalid. Please check the component configuration.');

      case self::ERROR_FIELD_MAPPING:
        return t('Unable to map component properties to fields. Please review the field configuration.');

      case self::ERROR_BUNDLE_CONFLICT:
        return t('There is a naming conflict with an existing component type.');

      case self::ERROR_PERMISSION_DENIED:
        return t('You do not have permission to perform this operation.');

      case self::ERROR_MISSING_DEPENDENCY:
        return t('Required dependencies are missing. Please install the necessary modules.');

      case self::ERROR_VALIDATION:
        return t('The component data is invalid. Please check the provided values.');

      case self::ERROR_CONFIGURATION:
      default:
        return t('A configuration error occurred during component synchronization.');
    }
  }

  /**
   * Converts the exception to an array for logging.
   *
   * @return array
   *   Array representation of the exception.
   */
  public function toArray() {
    return [
      'message' => $this->getMessage(),
      'component_id' => $this->componentId,
      'bundle' => $this->bundle,
      'error_type' => $this->errorType,
      'context' => $this->context,
      'code' => $this->getCode(),
      'file' => $this->getFile(),
      'line' => $this->getLine(),
      'trace' => $this->getTraceAsString(),
    ];
  }

}
