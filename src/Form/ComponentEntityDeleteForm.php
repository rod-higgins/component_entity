<?php

namespace Drupal\component_entity\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form for deleting Component entities.
 */
class ComponentEntityDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the component %name?', [
      '%name' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $description = parent::getDescription();
    
    // Add warning if component is referenced elsewhere.
    if ($this->hasReferences()) {
      $description .= ' ' . $this->t('<strong>Warning:</strong> This component is referenced by other content and deleting it may break those references.');
    }
    
    return $description;
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    // If we have a destination, use it.
    $destination = $this->getRequest()->query->get('destination');
    if ($destination) {
      return Url::fromUserInput($destination);
    }
    
    // Otherwise, go to the component canonical page or collection.
    if ($this->entity->hasLinkTemplate('canonical')) {
      return $this->entity->toUrl('canonical');
    }
    
    return new Url('entity.component.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $entity_type = $entity->getEntityType();
    
    // Log the deletion.
    $this->logger('component_entity')->notice('Deleted component %name (ID: %id, Type: %type).', [
      '%name' => $entity->label(),
      '%id' => $entity->id(),
      '%type' => $entity->bundle(),
    ]);
    
    // Clear any cached rendered output for this component.
    $cache_manager = \Drupal::service('component_entity.cache_manager');
    $cache_manager->invalidateComponentCache($entity);
    
    parent::submitForm($form, $form_state);
    
    // Set redirect to collection page.
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * Checks if the component has references from other entities.
   *
   * @return bool
   *   TRUE if the component is referenced elsewhere.
   */
  protected function hasReferences() {
    $entity = $this->getEntity();
    
    // Check for entity references to this component.
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_type = $entity->getEntityTypeId();
    $entity_id = $entity->id();
    
    // Get all entity reference fields that could reference components.
    $field_map = \Drupal::service('entity_field.manager')->getFieldMapByFieldType('entity_reference');
    
    foreach ($field_map as $referencing_entity_type => $fields) {
      foreach ($fields as $field_name => $field_info) {
        // Check if this field can reference components.
        foreach ($field_info['bundles'] as $bundle) {
          $field_config = \Drupal::entityTypeManager()
            ->getStorage('field_config')
            ->load($referencing_entity_type . '.' . $bundle . '.' . $field_name);
          
          if ($field_config && $field_config->getSetting('target_type') === $entity_type) {
            // Check if any entities reference this component.
            $storage = $entity_type_manager->getStorage($referencing_entity_type);
            $query = $storage->getQuery()
              ->condition($field_name, $entity_id)
              ->accessCheck(FALSE)
              ->range(0, 1);
            
            if ($query->execute()) {
              return TRUE;
            }
          }
        }
      }
    }
    
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    
    // Add component information to help user confirm.
    $entity = $this->getEntity();
    
    $form['component_info'] = [
      '#type' => 'details',
      '#title' => $this->t('Component information'),
      '#open' => TRUE,
      '#weight' => -100,
    ];
    
    $form['component_info']['type'] = [
      '#type' => 'item',
      '#title' => $this->t('Component type'),
      '#markup' => $entity->bundle(),
    ];
    
    $form['component_info']['render_method'] = [
      '#type' => 'item',
      '#title' => $this->t('Render method'),
      '#markup' => $entity->getRenderMethod() ?? 'twig',
    ];
    
    if ($entity->hasField('created')) {
      $form['component_info']['created'] = [
        '#type' => 'item',
        '#title' => $this->t('Created'),
        '#markup' => \Drupal::service('date.formatter')->format($entity->getCreatedTime()),
      ];
    }
    
    // Show usage count if available.
    if ($usage_count = $this->getUsageCount()) {
      $form['component_info']['usage'] = [
        '#type' => 'item',
        '#title' => $this->t('Used in'),
        '#markup' => $this->formatPlural($usage_count, '1 place', '@count places'),
        '#description' => $this->t('This component is referenced by other content.'),
      ];
    }
    
    return $form;
  }

  /**
   * Gets the usage count for the component.
   *
   * @return int
   *   The number of places where this component is used.
   */
  protected function getUsageCount() {
    $entity = $this->getEntity();
    $count = 0;
    
    // This would typically integrate with a usage tracking service.
    // For now, return a simple count of references.
    $entity_type_manager = \Drupal::entityTypeManager();
    $field_map = \Drupal::service('entity_field.manager')->getFieldMapByFieldType('entity_reference');
    
    foreach ($field_map as $referencing_entity_type => $fields) {
      foreach ($fields as $field_name => $field_info) {
        foreach ($field_info['bundles'] as $bundle) {
          $field_config = \Drupal::entityTypeManager()
            ->getStorage('field_config')
            ->load($referencing_entity_type . '.' . $bundle . '.' . $field_name);
          
          if ($field_config && $field_config->getSetting('target_type') === 'component') {
            $storage = $entity_type_manager->getStorage($referencing_entity_type);
            $query = $storage->getQuery()
              ->condition($field_name, $entity->id())
              ->accessCheck(FALSE)
              ->count();
            
            $count += $query->execute();
          }
        }
      }
    }
    
    return $count;
  }

}