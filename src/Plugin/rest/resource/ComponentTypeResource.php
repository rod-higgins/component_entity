<?php

namespace Drupal\component_entity\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a REST resource for component types.
 *
 * @RestResource(
 *   id = "component_type",
 *   label = @Translation("Component Type"),
 *   uri_paths = {
 *     "canonical" = "/api/component-type/{component_type}",
 *     "collection" = "/api/component-types"
 *   }
 * )
 */
class ComponentTypeResource extends ResourceBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a ComponentTypeResource object.
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
   * Responds to GET requests for a single component type.
   *
   * @param string $component_type
   *   The component type ID.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the component type.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  public function get($component_type = NULL) {
    if (!$component_type) {
      // Return collection of all component types.
      return $this->getCollection();
    }

    $type = $this->entityTypeManager
      ->getStorage('component_type')
      ->load($component_type);

    if (!$type) {
      throw new NotFoundHttpException('Component type not found.');
    }

    // Build the response data.
    $data = [
      'id' => $type->id(),
      'label' => $type->label(),
      'description' => $type->getDescription(),
      'sdc_id' => $type->get('sdc_id'),
      'rendering' => $type->get('rendering'),
      'fields' => $this->getFieldDefinitions($type->id()),
    ];

    $response = new ResourceResponse($data, 200);
    $response->addCacheableDependency($type);
    
    return $response;
  }

  /**
   * Gets collection of all component types.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing all component types.
   */
  protected function getCollection() {
    $types = $this->entityTypeManager
      ->getStorage('component_type')
      ->loadMultiple();

    $data = [];
    foreach ($types as $type) {
      $data[] = [
        'id' => $type->id(),
        'label' => $type->label(),
        'description' => $type->getDescription(),
        'sdc_id' => $type->get('sdc_id'),
        'rendering' => $type->get('rendering'),
      ];
    }

    $response = new ResourceResponse($data, 200);
    
    // Add cache tags for all types.
    foreach ($types as $type) {
      $response->addCacheableDependency($type);
    }
    
    return $response;
  }

  /**
   * Gets field definitions for a component type.
   *
   * @param string $bundle
   *   The component type ID.
   *
   * @return array
   *   Array of field definitions.
   */
  protected function getFieldDefinitions($bundle) {
    $field_definitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('component', $bundle);

    $fields = [];
    foreach ($field_definitions as $field_name => $field_definition) {
      // Skip base fields.
      if ($field_definition->getFieldStorageDefinition()->isBaseField()) {
        continue;
      }

      $fields[$field_name] = [
        'label' => $field_definition->getLabel(),
        'type' => $field_definition->getType(),
        'required' => $field_definition->isRequired(),
        'cardinality' => $field_definition->getFieldStorageDefinition()->getCardinality(),
        'settings' => $field_definition->getSettings(),
      ];
    }

    return $fields;
  }

}