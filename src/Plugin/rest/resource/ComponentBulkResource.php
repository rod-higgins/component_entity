<?php

namespace Drupal\component_entity\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\component_entity\Entity\ComponentEntity;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides a REST resource for bulk component operations.
 *
 * @RestResource(
 *   id = "component_bulk",
 *   label = @Translation("Component Bulk Operations"),
 *   uri_paths = {
 *     "create" = "/api/components/bulk"
 *   }
 * )
 */
class ComponentBulkResource extends ResourceBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a ComponentBulkResource object.
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
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * Responds to POST requests for bulk create.
   *
   * @param array $data
   *   Array of component data.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response containing the results.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   */
  public function post(array $data = []) {
    if (empty($data['operation'])) {
      throw new BadRequestHttpException('Operation type must be specified.');
    }

    $operation = $data['operation'];
    $components = $data['components'] ?? [];

    if (empty($components)) {
      throw new BadRequestHttpException('No components provided.');
    }

    // Limit bulk operations to prevent timeout.
    if (count($components) > 100) {
      throw new BadRequestHttpException('Maximum 100 components allowed per bulk operation.');
    }

    switch ($operation) {
      case 'create':
        return $this->bulkCreate($components);
      
      case 'update':
        return $this->bulkUpdate($components);
      
      case 'delete':
        return $this->bulkDelete($components);
      
      case 'publish':
        return $this->bulkPublish($components);
      
      case 'unpublish':
        return $this->bulkUnpublish($components);
      
      default:
        throw new BadRequestHttpException('Invalid operation type.');
    }
  }

  /**
   * Bulk create components.
   *
   * @param array $components
   *   Array of component data.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response containing results.
   */
  protected function bulkCreate(array $components) {
    $results = [
      'success' => [],
      'errors' => [],
    ];

    foreach ($components as $index => $component_data) {
      try {
        // Validate required fields.
        if (empty($component_data['type'])) {
          throw new \Exception('Component type is required.');
        }

        // Check create access for the bundle.
        $access = $this->entityTypeManager
          ->getAccessControlHandler('component')
          ->createAccess($component_data['type'], NULL, [], TRUE);

        if (!$access->isAllowed()) {
          throw new \Exception('Access denied for creating components of type ' . $component_data['type']);
        }

        // Create the component entity.
        $component = ComponentEntity::create($component_data);
        
        // Validate the entity.
        $violations = $component->validate();
        if ($violations->count() > 0) {
          $messages = [];
          foreach ($violations as $violation) {
            $messages[] = $violation->getMessage();
          }
          throw new \Exception(implode(', ', $messages));
        }

        // Save the component.
        $component->save();
        
        $results['success'][] = [
          'index' => $index,
          'id' => $component->id(),
          'uuid' => $component->uuid(),
        ];
      }
      catch (\Exception $e) {
        $results['errors'][] = [
          'index' => $index,
          'message' => $e->getMessage(),
        ];
      }
    }

    $this->logger->notice('Bulk created %success components with %errors errors.', [
      '%success' => count($results['success']),
      '%errors' => count($results['errors']),
    ]);

    return new ModifiedResourceResponse($results, 200);
  }

  /**
   * Bulk update components.
   *
   * @param array $components
   *   Array of component data with IDs.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response containing results.
   */
  protected function bulkUpdate(array $components) {
    $results = [
      'success' => [],
      'errors' => [],
    ];

    foreach ($components as $index => $component_data) {
      try {
        if (empty($component_data['id'])) {
          throw new \Exception('Component ID is required for update.');
        }

        $component = $this->entityTypeManager
          ->getStorage('component')
          ->load($component_data['id']);

        if (!$component) {
          throw new \Exception('Component not found.');
        }

        // Check update access.
        if (!$component->access('update')) {
          throw new \Exception('Access denied for updating component ' . $component_data['id']);
        }

        // Update fields.
        foreach ($component_data as $field_name => $value) {
          if (!in_array($field_name, ['id', 'uuid', 'vid', 'created'])) {
            $component->set($field_name, $value);
          }
        }

        // Validate the entity.
        $violations = $component->validate();
        if ($violations->count() > 0) {
          $messages = [];
          foreach ($violations as $violation) {
            $messages[] = $violation->getMessage();
          }
          throw new \Exception(implode(', ', $messages));
        }

        // Save the component.
        $component->save();
        
        $results['success'][] = [
          'index' => $index,
          'id' => $component->id(),
        ];
      }
      catch (\Exception $e) {
        $results['errors'][] = [
          'index' => $index,
          'id' => $component_data['id'] ?? NULL,
          'message' => $e->getMessage(),
        ];
      }
    }

    $this->logger->notice('Bulk updated %success components with %errors errors.', [
      '%success' => count($results['success']),
      '%errors' => count($results['errors']),
    ]);

    return new ModifiedResourceResponse($results, 200);
  }

  /**
   * Bulk delete components.
   *
   * @param array $components
   *   Array of component IDs.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response containing results.
   */
  protected function bulkDelete(array $components) {
    $results = [
      'success' => [],
      'errors' => [],
    ];

    foreach ($components as $index => $component_id) {
      try {
        $component = $this->entityTypeManager
          ->getStorage('component')
          ->load($component_id);

        if (!$component) {
          throw new \Exception('Component not found.');
        }

        // Check delete access.
        if (!$component->access('delete')) {
          throw new \Exception('Access denied for deleting component ' . $component_id);
        }

        // Delete the component.
        $component->delete();
        
        $results['success'][] = [
          'index' => $index,
          'id' => $component_id,
        ];
      }
      catch (\Exception $e) {
        $results['errors'][] = [
          'index' => $index,
          'id' => $component_id,
          'message' => $e->getMessage(),
        ];
      }
    }

    $this->logger->notice('Bulk deleted %success components with %errors errors.', [
      '%success' => count($results['success']),
      '%errors' => count($results['errors']),
    ]);

    return new ModifiedResourceResponse($results, 200);
  }

  /**
   * Bulk publish components.
   *
   * @param array $components
   *   Array of component IDs.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response containing results.
   */
  protected function bulkPublish(array $components) {
    return $this->bulkStatusChange($components, TRUE);
  }

  /**
   * Bulk unpublish components.
   *
   * @param array $components
   *   Array of component IDs.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response containing results.
   */
  protected function bulkUnpublish(array $components) {
    return $this->bulkStatusChange($components, FALSE);
  }

  /**
   * Bulk status change for components.
   *
   * @param array $components
   *   Array of component IDs.
   * @param bool $status
   *   The status to set.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response containing results.
   */
  protected function bulkStatusChange(array $components, $status) {
    $results = [
      'success' => [],
      'errors' => [],
    ];

    $action = $status ? 'publish' : 'unpublish';

    foreach ($components as $index => $component_id) {
      try {
        $component = $this->entityTypeManager
          ->getStorage('component')
          ->load($component_id);

        if (!$component) {
          throw new \Exception('Component not found.');
        }

        // Check update access.
        if (!$component->access('update')) {
          throw new \Exception('Access denied for updating component ' . $component_id);
        }

        // Update status.
        $component->setPublished($status);
        $component->save();
        
        $results['success'][] = [
          'index' => $index,
          'id' => $component_id,
          'action' => $action,
        ];
      }
      catch (\Exception $e) {
        $results['errors'][] = [
          'index' => $index,
          'id' => $component_id,
          'message' => $e->getMessage(),
        ];
      }
    }

    $this->logger->notice('Bulk %action %success components with %errors errors.', [
      '%action' => $action,
      '%success' => count($results['success']),
      '%errors' => count($results['errors']),
    ]);

    return new ModifiedResourceResponse($results, 200);
  }

}