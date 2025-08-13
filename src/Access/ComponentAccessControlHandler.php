<?php

namespace Drupal\component_entity\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Access controller for the Component entity.
 *
 * @see \Drupal\component_entity\Entity\ComponentEntity
 */
class ComponentAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\component_entity\Entity\ComponentEntityInterface $entity */
    
    // Check published status for view operation.
    if ($operation === 'view') {
      // Check if the entity is published.
      $published = $entity->isPublished();
      
      if (!$published) {
        // Check for view unpublished permission.
        return AccessResult::allowedIfHasPermission($account, 'view unpublished component entities')
          ->addCacheableDependency($entity);
      }
      
      // Published entities can be viewed with the general permission.
      return AccessResult::allowedIfHasPermission($account, 'view component entities')
        ->addCacheableDependency($entity);
    }
    
    // Check for preview operation.
    if ($operation === 'preview') {
      return AccessResult::allowedIfHasPermission($account, 'preview components')
        ->orIf(AccessResult::allowedIfHasPermission($account, 'edit any ' . $entity->bundle() . ' component'))
        ->orIf(
          AccessResult::allowedIfHasPermission($account, 'edit own ' . $entity->bundle() . ' component')
          ->andIf(AccessResult::allowedIf($entity->getOwnerId() == $account->id()))
        )
        ->addCacheableDependency($entity);
    }
    
    // Check render method switching permission.
    if ($operation === 'switch_render_method') {
      return AccessResult::allowedIfHasPermission($account, 'configure react rendering')
        ->andIf(AccessResult::allowedIfHasPermission($account, 'edit any ' . $entity->bundle() . ' component'))
        ->addCacheableDependency($entity);
    }
    
    // Get the bundle for permission checking.
    $bundle = $entity->bundle();
    
    switch ($operation) {
      case 'update':
        // Check for bundle-specific edit permissions.
        $result = AccessResult::allowedIfHasPermission($account, 'edit any ' . $bundle . ' component');
        
        // Check for "own" permission if the user is the owner.
        if ($entity->getOwnerId() == $account->id()) {
          $result = $result->orIf(
            AccessResult::allowedIfHasPermission($account, 'edit own ' . $bundle . ' component')
          );
        }
        
        return $result->addCacheableDependency($entity);
        
      case 'delete':
        // Check for bundle-specific delete permissions.
        $result = AccessResult::allowedIfHasPermission($account, 'delete any ' . $bundle . ' component');
        
        // Check for "own" permission if the user is the owner.
        if ($entity->getOwnerId() == $account->id()) {
          $result = $result->orIf(
            AccessResult::allowedIfHasPermission($account, 'delete own ' . $bundle . ' component')
          );
        }
        
        return $result->addCacheableDependency($entity);
        
      case 'view_revision':
        return AccessResult::allowedIfHasPermission($account, 'view component revisions')
          ->addCacheableDependency($entity);
        
      case 'revert':
        return AccessResult::allowedIfHasPermission($account, 'revert component revisions')
          ->addCacheableDependency($entity);
        
      case 'delete_revision':
        return AccessResult::allowedIfHasPermission($account, 'delete component revisions')
          ->addCacheableDependency($entity);
        
      default:
        // No opinion.
        return AccessResult::neutral();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    // Check for bundle-specific create permission.
    if ($entity_bundle) {
      return AccessResult::allowedIfHasPermission($account, 'create ' . $entity_bundle . ' component');
    }
    
    // Check if user can create any component type.
    $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo('component');
    
    foreach (array_keys($bundles) as $bundle) {
      $permission = 'create ' . $bundle . ' component';
      if ($account->hasPermission($permission)) {
        return AccessResult::allowed()->addCacheTags(['component_type_list']);
      }
    }
    
    return AccessResult::neutral()->addCacheTags(['component_type_list']);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkFieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, FieldItemListInterface $items = NULL) {
    // Special handling for render_method field.
    if ($field_definition->getName() === 'render_method') {
      if ($operation === 'edit') {
        // Only users with React configuration permission can change render method.
        return AccessResult::allowedIfHasPermission($account, 'configure react rendering');
      }
    }
    
    // Special handling for react_config field.
    if ($field_definition->getName() === 'react_config') {
      if ($operation === 'edit') {
        // Only users with React configuration permission can edit React config.
        return AccessResult::allowedIfHasPermission($account, 'configure react rendering');
      }
      if ($operation === 'view') {
        // Hide React config from users without permission.
        return AccessResult::allowedIfHasPermission($account, 'configure react rendering');
      }
    }
    
    // Revision fields require appropriate permissions.
    if ($field_definition->getName() === 'revision_log_message') {
      if ($operation === 'view') {
        return AccessResult::allowedIfHasPermission($account, 'view component revisions');
      }
    }
    
    // Check if field is part of a slot.
    if (strpos($field_definition->getName(), '_slot') !== FALSE) {
      // Slots may have special access rules based on the component type.
      if ($items) {
        $entity = $items->getEntity();
        $component_type = \Drupal::entityTypeManager()
          ->getStorage('component_type')
          ->load($entity->bundle());
        
        if ($component_type && $component_type->hasSlotRestrictions()) {
          // Apply slot-specific restrictions.
          $slot_name = str_replace(['field_', '_slot'], '', $field_definition->getName());
          $slot_config = $component_type->getSlotConfiguration($slot_name);
          
          if (!empty($slot_config['restricted'])) {
            return AccessResult::allowedIfHasPermission($account, 'edit restricted component slots');
          }
        }
      }
    }
    
    return parent::checkFieldAccess($operation, $field_definition, $account, $items);
  }

}