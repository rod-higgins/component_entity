<?php

namespace Drupal\component_entity\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Event fired during component synchronization.
 *
 * This event allows other modules to react to component synchronization
 * operations and potentially modify the sync process.
 */
class ComponentSyncEvent extends Event {

  use StringTranslationTrait;

  /**
   * Event fired when a single component is synced.
   *
   * @var string
   */
  const COMPONENT_SYNCED = 'component_entity.component_synced';

  /**
   * Event fired when sync process starts.
   *
   * @var string
   */
  const SYNC_START = 'component_entity.sync_start';

  /**
   * Event fired when sync process completes.
   *
   * @var string
   */
  const SYNC_COMPLETE = 'component_entity.sync_complete';

  /**
   * Event fired when a sync error occurs.
   *
   * @var string
   */
  const SYNC_ERROR = 'component_entity.sync_error';

  /**
   * Event fired before a component type is created.
   *
   * @var string
   */
  const PRE_CREATE_TYPE = 'component_entity.pre_create_type';

  /**
   * Event fired after a component type is created.
   *
   * @var string
   */
  const POST_CREATE_TYPE = 'component_entity.post_create_type';

  /**
   * Event fired before fields are synced.
   *
   * @var string
   */
  const PRE_SYNC_FIELDS = 'component_entity.pre_sync_fields';

  /**
   * Event fired after fields are synced.
   *
   * @var string
   */
  const POST_SYNC_FIELDS = 'component_entity.post_sync_fields';

  /**
   * The sync data.
   *
   * @var array
   */
  protected $data;

  /**
   * The sync operation type.
   *
   * @var string
   */
  protected $operation;

  /**
   * Any errors that occurred during sync.
   *
   * @var array
   */
  protected $errors = [];

  /**
   * Whether the sync should be stopped.
   *
   * @var bool
   */
  protected $stopPropagation = FALSE;

  /**
   * Constructs a ComponentSyncEvent object.
   *
   * @param array $data
   *   The sync data. This can include:
   *   - component_id: The SDC component ID
   *   - bundle: The entity bundle
   *   - is_new: Whether this is a new component type
   *   - created: Array of created component types
   *   - updated: Array of updated component types
   *   - skipped: Array of skipped components
   *   - errors: Array of errors.
   * @param string $operation
   *   The operation type (create, update, skip, error).
   */
  public function __construct(array $data, $operation = '') {
    $this->data = $data;
    $this->operation = $operation;
  }

  /**
   * Gets the sync data.
   *
   * @return array
   *   The sync data.
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Sets the sync data.
   *
   * @param array $data
   *   The sync data.
   *
   * @return $this
   */
  public function setData(array $data) {
    $this->data = $data;
    return $this;
  }

  /**
   * Gets a specific data value.
   *
   * @param string $key
   *   The data key.
   * @param mixed $default
   *   Default value if key doesn't exist.
   *
   * @return mixed
   *   The data value.
   */
  public function get($key, $default = NULL) {
    return $this->data[$key] ?? $default;
  }

  /**
   * Gets a specific data value.
   *
   * @param string $key
   *   The data key.
   * @param mixed $default
   *   Default value if key doesn't exist.
   *
   * @return mixed
   *   The data value.
   */
  public function getDataValue($key, $default = NULL) {
    return $this->data[$key] ?? $default;
  }

  /**
   * Sets a specific data value.
   *
   * @param string $key
   *   The data key.
   * @param mixed $value
   *   The data value.
   *
   * @return $this
   */
  public function setDataValue($key, $value) {
    $this->data[$key] = $value;
    return $this;
  }

  /**
   * Gets the operation type.
   *
   * @return string
   *   The operation type.
   */
  public function getOperation() {
    return $this->operation;
  }

  /**
   * Sets the operation type.
   *
   * @param string $operation
   *   The operation type.
   *
   * @return $this
   */
  public function setOperation($operation) {
    $this->operation = $operation;
    return $this;
  }

  /**
   * Gets the component ID being synced.
   *
   * @return string|null
   *   The component ID or NULL if not available.
   */
  public function getComponentId() {
    return $this->getDataValue('component_id');
  }

  /**
   * Gets the bundle name.
   *
   * @return string|null
   *   The bundle name or NULL if not available.
   */
  public function getBundle() {
    return $this->getDataValue('bundle');
  }

  /**
   * Checks if this is a new component type.
   *
   * @return bool
   *   TRUE if this is a new component type.
   */
  public function isNew() {
    return (bool) $this->getDataValue('is_new', FALSE);
  }

  /**
   * Gets the sync results.
   *
   * @return array
   *   Array with keys: created, updated, skipped, errors.
   */
  public function getResults() {
    return [
      'created' => $this->getDataValue('created', []),
      'updated' => $this->getDataValue('updated', []),
      'skipped' => $this->getDataValue('skipped', []),
      'errors' => $this->getDataValue('errors', []),
    ];
  }

  /**
   * Adds an error to the sync process.
   *
   * @param string $component_id
   *   The component ID that had an error.
   * @param string $message
   *   The error message.
   * @param array $context
   *   Additional error context.
   *
   * @return $this
   */
  public function addError($component_id, $message, array $context = []) {
    $this->errors[$component_id] = [
      'message' => $message,
      'context' => $context,
    ];

    // Also add to data errors.
    $errors = $this->getDataValue('errors', []);
    $errors[] = $component_id;
    $this->setDataValue('errors', $errors);

    return $this;
  }

  /**
   * Gets all errors.
   *
   * @return array
   *   Array of errors.
   */
  public function getErrors() {
    return $this->errors;
  }

  /**
   * Checks if there are any errors.
   *
   * @return bool
   *   TRUE if there are errors.
   */
  public function hasErrors() {
    return !empty($this->errors) || !empty($this->getDataValue('errors', []));
  }

  /**
   * Marks that the sync should be stopped.
   *
   * @param bool $stop
   *   Whether to stop the sync.
   *
   * @return $this
   */
  public function stopSync($stop = TRUE) {
    $this->stopPropagation = $stop;
    return $this;
  }

  /**
   * Checks if the sync should be stopped.
   *
   * @return bool
   *   TRUE if the sync should be stopped.
   */
  public function isSyncStopped() {
    return $this->stopPropagation;
  }

  /**
   * Gets sync statistics.
   *
   * @return array
   *   Array with sync statistics.
   */
  public function getStatistics() {
    $results = $this->getResults();
    return [
      'total' => count($results['created']) + count($results['updated']) + count($results['skipped']),
      'created' => count($results['created']),
      'updated' => count($results['updated']),
      'skipped' => count($results['skipped']),
      'errors' => count($results['errors']),
      'success_rate' => $this->calculateSuccessRate(),
    ];
  }

  /**
   * Calculates the success rate of the sync.
   *
   * @return float
   *   The success rate as a percentage.
   */
  protected function calculateSuccessRate() {
    $stats = $this->getStatistics();
    $total = $stats['total'] + $stats['errors'];

    if ($total === 0) {
      return 100.0;
    }

    return round(($stats['total'] / $total) * 100, 2);
  }

  /**
   * Gets a summary message for the sync operation.
   *
   * @return string
   *   A human-readable summary.
   */
  public function getSummary() {
    $stats = $this->getStatistics();

    $parts = [];
    if ($stats['created'] > 0) {
      $parts[] = $this->t('@count created', ['@count' => $stats['created']]);
    }
    if ($stats['updated'] > 0) {
      $parts[] = $this->t('@count updated', ['@count' => $stats['updated']]);
    }
    if ($stats['skipped'] > 0) {
      $parts[] = $this->t('@count skipped', ['@count' => $stats['skipped']]);
    }
    if ($stats['errors'] > 0) {
      $parts[] = $this->t('@count errors', ['@count' => $stats['errors']]);
    }

    return implode(', ', $parts) ?: $this->t('No components processed');
  }

}
