<?php

namespace Drupal\component_entity\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\component_entity\Entity\ComponentEntity;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a REST resource for component entities.
 *
 * @RestResource(
 *   id = "component_entity",
 *   label = @Translation("Component Entity"),
 *   entity_type = "component",
 *   serialization_class = "Drupal\component_entity\Entity\ComponentEntity",
 *   uri_paths = {
 *     "canonical" = "/api/component/{component}",
 *     "create" = "/api/component"
 *   }
 * )
 */
class ComponentEntityResource extends ResourceBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a ComponentEntityResource object.
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
    EntityTypeManagerInterface $entity_type_manager,
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
   * Responds to GET requests.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntity $component
   *   The component entity.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the component entity.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function get(?ComponentEntity $component = NULL) {
    if (!$component) {
      throw new NotFoundHttpException('Component entity not found.');
    }

    // Check access.
    if (!$component->access('view')) {
      throw new AccessDeniedHttpException();
    }

    // Add cache metadata.
    $response = new ResourceResponse($component, 200);
    $response->addCacheableDependency($component);

    return $response;
  }

  /**
   * Responds to POST requests.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntity $component
   *   The component entity.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response containing the created component entity.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function post(?ComponentEntity $component = NULL) {
    if (!$component) {
      throw new BadRequestHttpException('No component entity provided.');
    }

    // Validate the component entity.
    $violations = $component->validate();
    if ($violations->count() > 0) {
      $message = "Validation failed: ";
      foreach ($violations as $violation) {
        $message .= $violation->getMessage() . " ";
      }
      throw new BadRequestHttpException($message);
    }

    // Check create access for the bundle.
    $bundle = $component->bundle();
    $access = $this->entityTypeManager
      ->getAccessControlHandler('component')
      ->createAccess($bundle, NULL, [], TRUE);

    if (!$access->isAllowed()) {
      throw new AccessDeniedHttpException();
    }

    try {
      $component->save();
      $this->logger->notice('Created component entity %id.', ['%id' => $component->id()]);

      // Return 201 Created with the location of the new resource.
      $url = $component->toUrl('canonical', ['absolute' => TRUE])->toString(TRUE);
      $response = new ModifiedResourceResponse($component, 201, ['Location' => $url->getGeneratedUrl()]);

      return $response;
    }
    catch (\Exception $e) {
      throw new BadRequestHttpException('Could not save component entity: ' . $e->getMessage());
    }
  }

  /**
   * Responds to PATCH requests.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntity $original_component
   *   The original component entity.
   * @param \Drupal\component_entity\Entity\ComponentEntity $component
   *   The component entity with updates.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response containing the updated component entity.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function patch(?ComponentEntity $original_component = NULL, ?ComponentEntity $component = NULL) {
    if (!$original_component || !$component) {
      throw new BadRequestHttpException('Component entity not provided.');
    }

    // Check update access.
    if (!$original_component->access('update')) {
      throw new AccessDeniedHttpException();
    }

    // Ensure we're updating the same entity.
    if ($original_component->id() != $component->id()) {
      throw new BadRequestHttpException('Component ID mismatch.');
    }

    // Apply the changes.
    foreach ($component->getFields() as $field_name => $field) {
      // Skip read-only fields.
      if (in_array($field_name, ['id', 'uuid', 'vid', 'created'])) {
        continue;
      }

      // Update field values.
      $original_component->set($field_name, $field->getValue());
    }

    // Validate the updated entity.
    $violations = $original_component->validate();
    if ($violations->count() > 0) {
      $message = "Validation failed: ";
      foreach ($violations as $violation) {
        $message .= $violation->getMessage() . " ";
      }
      throw new BadRequestHttpException($message);
    }

    try {
      $original_component->save();
      $this->logger->notice('Updated component entity %id.', ['%id' => $original_component->id()]);

      return new ModifiedResourceResponse($original_component, 200);
    }
    catch (\Exception $e) {
      throw new BadRequestHttpException('Could not update component entity: ' . $e->getMessage());
    }
  }

  /**
   * Responds to DELETE requests.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntity $component
   *   The component entity.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response confirming deletion.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function delete(?ComponentEntity $component = NULL) {
    if (!$component) {
      throw new NotFoundHttpException('Component entity not found.');
    }

    // Check delete access.
    if (!$component->access('delete')) {
      throw new AccessDeniedHttpException();
    }

    try {
      $component->delete();
      $this->logger->notice('Deleted component entity %id.', ['%id' => $component->id()]);

      // Return 204 No Content.
      return new ModifiedResourceResponse(NULL, 204);
    }
    catch (\Exception $e) {
      throw new BadRequestHttpException('Could not delete component entity: ' . $e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function permissions() {
    // The permissions are handled by the entity access system.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $collection = parent::routes();

    // Customize routes if needed.
    foreach ($collection as $route) {
      $route->setRequirement('_format', 'json');
    }

    return $collection;
  }

}
