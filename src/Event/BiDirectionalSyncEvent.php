<?php

namespace Drupal\component_entity\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Drupal\component_entity\Entity\ComponentTypeInterface;

/**
 * Event for bi-directional sync operations.
 */
class BiDirectionalSyncEvent extends Event {

  /**
   * Event fired before sync starts.
   */
  const PRE_SYNC = 'component_entity.bi_directional_sync.pre';

  /**
   * Event fired after sync completes.
   */
  const POST_SYNC = 'component_entity.bi_directional_sync.post';

  /**
   * @var ComponentTypeInterface
   */
  protected $componentType;

  /**
   * @var string
   */
  protected $operation;

  /**
   * @var array
   */
  protected $results;

  /**
   * @var bool
   */
  protected $cancelled = FALSE;

  /**
   * Constructor.
   *
   * @param ComponentTypeInterface $component_type
   *   The component type being synced.
   * @param string $operation
   *   The operation being performed (create, update, delete).
   * @param array $results
   *   Results from the sync operation (for post-sync events).
   */
  public function __construct(ComponentTypeInterface $component_type, $operation, array $results = []) {
    $this->componentType = $component_type;
    $this->operation = $operation;
    $this->results = $results;
  }

  /**
   * Gets the component type.
   *
   * @return ComponentTypeInterface
   */
  public function getComponentType() {
    return $this->componentType;
  }

  /**
   * Gets the operation.
   *
   * @return string
   */
  public function getOperation() {
    return $this->operation;
  }

  /**
   * Gets the results.
   *
   * @return array
   */
  public function getResults() {
    return $this->results;
  }

  /**
   * Sets the results.
   *
   * @param array $results
   *   The results array.
   */
  public function setResults(array $results) {
    $this->results = $results;
  }

  /**
   * Cancels the sync operation.
   */
  public function cancel() {
    $this->cancelled = TRUE;
    $this->stopPropagation();
  }

  /**
   * Checks if the sync was cancelled.
   *
   * @return bool
   */
  public function isCancelled() {
    return $this->cancelled;
  }
}

/**
 * Event for file write operations.
 */
class FileWriteEvent extends Event {

  /**
   * Event fired when a file is written.
   */
  const FILE_WRITTEN = 'component_entity.file.written';

  /**
   * Event fired when a file is deleted.
   */
  const FILE_DELETED = 'component_entity.file.deleted';

  /**
   * @var string
   */
  protected $filePath;

  /**
   * @var string
   */
  protected $content;

  /**
   * @var array
   */
  protected $options;

  /**
   * Constructor.
   *
   * @param string $file_path
   *   The file path.
   * @param string $content
   *   The file content.
   * @param array $options
   *   Write options.
   */
  public function __construct($file_path, $content, array $options = []) {
    $this->filePath = $file_path;
    $this->content = $content;
    $this->options = $options;
  }

  /**
   * Gets the file path.
   *
   * @return string
   */
  public function getFilePath() {
    return $this->filePath;
  }

  /**
   * Gets the content.
   *
   * @return string
   */
  public function getContent() {
    return $this->content;
  }

  /**
   * Gets the options.
   *
   * @return array
   */
  public function getOptions() {
    return $this->options;
  }
}

/**
 * Event for component sync operations.
 */
class ComponentSyncEvent extends Event {

  /**
   * Event fired when a component is synced.
   */
  const COMPONENT_SYNCED = 'component_entity.component.synced';

  /**
   * @var array
   */
  protected $data;

  /**
   * Constructor.
   *
   * @param array $data
   *   Event data including component_id, bundle, is_new.
   */
  public function __construct(array $data) {
    $this->data = $data;
  }

  /**
   * Gets the event data.
   *
   * @return array
   */
  public function getData() {
    return $this->data;
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
   */
  public function get($key, $default = NULL) {
    return $this->data[$key] ?? $default;
  }
}