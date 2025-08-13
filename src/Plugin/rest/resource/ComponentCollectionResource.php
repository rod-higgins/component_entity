<?php

namespace Drupal\component_entity\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides a REST resource for component collections.
 *
 * @RestResource(
 *   id = "component_collection",
 *   label = @Translation("Component Collection"),
 *   uri_paths = {
 *     "canonical" = "/api/components"
 *   }
 * )
 */
class ComponentCollectionResource extends ResourceBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * Constructs a ComponentCollectionResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\Request $current_request
   *   The current request.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager,
    Request $current_request
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityTypeManager = $entity_type_manager;
    $this->currentRequest = $current_request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('component_entity'),
      $container->get('entity_type.manager'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * Responds to GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the component collection.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   */
  public function get() {
    $storage = $this->entityTypeManager->getStorage('component');
    $query = $storage->getQuery();
    $query->accessCheck(TRUE);

    // Apply filters from query parameters.
    $this->applyFilters($query);

    // Apply sorting.
    $this->applySorting($query);

    // Apply pagination.
    $limit = $this->currentRequest->query->get('limit', 50);
    $offset = $this->currentRequest->query->get('offset', 0);

    // Validate pagination parameters.
    if (!is_numeric($limit) || $limit < 1 || $limit > 100) {
      throw new BadRequestHttpException('Invalid limit parameter. Must be between 1 and 100.');
    }
    if (!is_numeric($offset) || $offset < 0) {
      throw new BadRequestHttpException('Invalid offset parameter. Must be 0 or greater.');
    }

    // Get total count before applying limit.
    $count_query = clone $query;
    $total = $count_query->count()->execute();

    // Apply limit and offset.
    $query->range($offset, $limit);

    // Execute query.
    $ids = $query->execute();
    $components = $storage->loadMultiple($ids);

    // Build response data.
    $data = [
      'total' => $total,
      'limit' => (int) $limit,
      'offset' => (int) $offset,
      'items' => [],
    ];

    foreach ($components as $component) {
      // Only include components the user has access to view.
      if ($component->access('view')) {
        $data['items'][] = $this->serializeComponent($component);
      }
    }

    // Add links for pagination.
    $data['links'] = $this->buildPaginationLinks($total, $limit, $offset);

    $response = new ResourceResponse($data, 200);
    
    // Add cache metadata.
    $cache_tags = ['component_list'];
    foreach ($components as $component) {
      $response->addCacheableDependency($component);
    }
    $response->getCacheableMetadata()->addCacheTags($cache_tags);
    
    return $response;
  }

  /**
   * Applies filters to the query.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The entity query.
   */
  protected function applyFilters($query) {
    $filters = $this->currentRequest->query->all();

    // Filter by bundle/type.
    if (isset($filters['type'])) {
      $query->condition('type', $filters['type']);
    }

    // Filter by status.
    if (isset($filters['status'])) {
      $query->condition('status', $filters['status']);
    }

    // Filter by render method.
    if (isset($filters['render_method'])) {
      $query->condition('render_method', $filters['render_method']);
    }

    // Filter by creation date.
    if (isset($filters['created_after'])) {
      $query->condition('created', strtotime($filters['created_after']), '>=');
    }
    if (isset($filters['created_before'])) {
      $query->condition('created', strtotime($filters['created_before']), '<=');
    }

    // Filter by author.
    if (isset($filters['uid'])) {
      $query->condition('uid', $filters['uid']);
    }

    // Search in name/label.
    if (isset($filters['search'])) {
      $query->condition('name', $filters['search'], 'CONTAINS');
    }
  }

  /**
   * Applies sorting to the query.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The entity query.
   */
  protected function applySorting($query) {
    $sort = $this->currentRequest->query->get('sort', 'created');
    $order = $this->currentRequest->query->get('order', 'DESC');

    // Validate sort field.
    $allowed_sorts = ['created', 'changed', 'name', 'type', 'status'];
    if (!in_array($sort, $allowed_sorts)) {
      $sort = 'created';
    }

    // Validate order direction.
    $order = strtoupper($order);
    if (!in_array($order, ['ASC', 'DESC'])) {
      $order = 'DESC';
    }

    $query->sort($sort, $order);
  }

  /**
   * Serializes a component entity.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntity $component
   *   The component entity.
   *
   * @return array
   *   The serialized component data.
   */
  protected function serializeComponent($component) {
    return [
      'id' => $component->id(),
      'uuid' => $component->uuid(),
      'type' => $component->bundle(),
      'name' => $component->label(),
      'status' => $component->isPublished(),
      'render_method' => $component->getRenderMethod(),
      'created' => $component->getCreatedTime(),
      'changed' => $component->getChangedTime(),
      'author' => [
        'uid' => $component->getOwnerId(),
        'name' => $component->getOwner()->getDisplayName(),
      ],
      'url' => $component->toUrl('canonical', ['absolute' => TRUE])->toString(),
    ];
  }

  /**
   * Builds pagination links.
   *
   * @param int $total
   *   Total number of items.
   * @param int $limit
   *   Items per page.
   * @param int $offset
   *   Current offset.
   *
   * @return array
   *   Array of pagination links.
   */
  protected function buildPaginationLinks($total, $limit, $offset) {
    $base_url = $this->currentRequest->getSchemeAndHttpHost() . '/api/components';
    $query_params = $this->currentRequest->query->all();
    unset($query_params['limit'], $query_params['offset']);

    $links = [
      'self' => $this->buildLink($base_url, $query_params, $limit, $offset),
      'first' => $this->buildLink($base_url, $query_params, $limit, 0),
      'last' => $this->buildLink($base_url, $query_params, $limit, max(0, $total - $limit)),
    ];

    // Add prev link if not on first page.
    if ($offset > 0) {
      $links['prev'] = $this->buildLink($base_url, $query_params, $limit, max(0, $offset - $limit));
    }

    // Add next link if not on last page.
    if ($offset + $limit < $total) {
      $links['next'] = $this->buildLink($base_url, $query_params, $limit, $offset + $limit);
    }

    return $links;
  }

  /**
   * Builds a pagination link.
   *
   * @param string $base_url
   *   The base URL.
   * @param array $query_params
   *   Query parameters.
   * @param int $limit
   *   Items per page.
   * @param int $offset
   *   Offset.
   *
   * @return string
   *   The complete URL.
   */
  protected function buildLink($base_url, array $query_params, $limit, $offset) {
    $query_params['limit'] = $limit;
    $query_params['offset'] = $offset;
    return $base_url . '?' . http_build_query($query_params);
  }

}