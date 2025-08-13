<?php

namespace Drupal\component_entity\Routing;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for Component entities.
 */
class ComponentRouteProvider extends AdminHtmlRouteProvider implements EntityHandlerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a ComponentRouteProvider object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);
    
    $entity_type_id = $entity_type->id();
    
    // Add custom routes.
    if ($preview_route = $this->getPreviewRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.preview", $preview_route);
    }
    
    if ($duplicate_route = $this->getDuplicateFormRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.duplicate_form", $duplicate_route);
    }
    
    if ($revision_route = $this->getRevisionRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.revision", $revision_route);
    }
    
    if ($revision_revert_route = $this->getRevisionRevertRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.revision_revert", $revision_revert_route);
    }
    
    if ($revision_delete_route = $this->getRevisionDeleteRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.revision_delete", $revision_delete_route);
    }
    
    if ($collection_route = $this->getCollectionRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.collection", $collection_route);
    }
    
    return $collection;
  }

  /**
   * Gets the preview route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getPreviewRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('preview')) {
      $entity_type_id = $entity_type->id();
      $route = new Route($entity_type->getLinkTemplate('preview'));
      
      $route
        ->setDefaults([
          '_controller' => '\Drupal\component_entity\Controller\ComponentController::preview',
          '_title_callback' => '\Drupal\component_entity\Controller\ComponentController::previewTitle',
        ])
        ->setRequirements([
          '_entity_access' => "{$entity_type_id}.view",
          '_component_preview_access' => 'TRUE',
        ])
        ->setOption('parameters', [
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
        ]);
      
      return $route;
    }
  }

  /**
   * Gets the duplicate form route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getDuplicateFormRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('duplicate-form')) {
      $entity_type_id = $entity_type->id();
      $route = new Route($entity_type->getLinkTemplate('duplicate-form'));
      
      $route
        ->setDefaults([
          '_entity_form' => "{$entity_type_id}.duplicate",
          '_title' => 'Duplicate ' . $entity_type->getSingularLabel(),
        ])
        ->setRequirements([
          '_entity_access' => "{$entity_type_id}.view",
          '_entity_create_access' => $entity_type_id,
        ])
        ->setOption('parameters', [
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
        ]);
      
      return $route;
    }
  }

  /**
   * Gets the revision route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getRevisionRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('revision')) {
      $entity_type_id = $entity_type->id();
      $route = new Route($entity_type->getLinkTemplate('revision'));
      
      $route
        ->setDefaults([
          '_controller' => '\Drupal\component_entity\Controller\ComponentController::revisionShow',
          '_title_callback' => '\Drupal\component_entity\Controller\ComponentController::revisionPageTitle',
        ])
        ->setRequirements([
          '_access_component_revision' => 'view',
        ])
        ->setOption('parameters', [
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
          $entity_type_id . '_revision' => ['type' => 'entity_revision:' . $entity_type_id],
        ]);
      
      return $route;
    }
  }

  /**
   * Gets the revision revert route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getRevisionRevertRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('revision-revert')) {
      $entity_type_id = $entity_type->id();
      $route = new Route($entity_type->getLinkTemplate('revision-revert'));
      
      $route
        ->setDefaults([
          '_form' => '\Drupal\component_entity\Form\ComponentRevisionRevertForm',
          '_title' => 'Revert to earlier revision',
        ])
        ->setRequirements([
          '_access_component_revision' => 'update',
        ])
        ->setOption('parameters', [
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
          $entity_type_id . '_revision' => ['type' => 'entity_revision:' . $entity_type_id],
        ]);
      
      return $route;
    }
  }

  /**
   * Gets the revision delete route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getRevisionDeleteRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('revision-delete')) {
      $entity_type_id = $entity_type->id();
      $route = new Route($entity_type->getLinkTemplate('revision-delete'));
      
      $route
        ->setDefaults([
          '_form' => '\Drupal\component_entity\Form\ComponentRevisionDeleteForm',
          '_title' => 'Delete revision',
        ])
        ->setRequirements([
          '_access_component_revision' => 'delete',
        ])
        ->setOption('parameters', [
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
          $entity_type_id . '_revision' => ['type' => 'entity_revision:' . $entity_type_id],
        ]);
      
      return $route;
    }
  }

  /**
   * Gets the collection route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getCollectionRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('collection') && $entity_type->hasListBuilderClass()) {
      $entity_type_id = $entity_type->id();
      $route = new Route($entity_type->getLinkTemplate('collection'));
      
      $route
        ->setDefaults([
          '_entity_list' => $entity_type_id,
          '_title' => $entity_type->getCollectionLabel(),
        ])
        ->setRequirements([
          '_permission' => $entity_type->getAdminPermission() ?: 'access component overview',
        ])
        ->setOption('_admin_route', TRUE);
      
      return $route;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getCanonicalRoute(EntityTypeInterface $entity_type) {
    $route = parent::getCanonicalRoute($entity_type);
    
    if ($route) {
      // Add custom requirements or modifications to the canonical route.
      $route->setRequirement('_entity_access', $entity_type->id() . '.view');
    }
    
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditFormRoute(EntityTypeInterface $entity_type) {
    $route = parent::getEditFormRoute($entity_type);
    
    if ($route) {
      // Ensure edit form uses the correct controller.
      $route->setDefault('_entity_form', $entity_type->id() . '.edit');
    }
    
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getAddFormRoute(EntityTypeInterface $entity_type) {
    $route = parent::getAddFormRoute($entity_type);
    
    if ($route) {
      // Ensure add form uses the correct controller.
      $route->setDefault('_entity_form', $entity_type->id() . '.add');
    }
    
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeleteFormRoute(EntityTypeInterface $entity_type) {
    $route = parent::getDeleteFormRoute($entity_type);
    
    if ($route) {
      // Ensure delete form uses the correct controller.
      $route->setDefault('_entity_form', $entity_type->id() . '.delete');
    }
    
    return $route;
  }

}