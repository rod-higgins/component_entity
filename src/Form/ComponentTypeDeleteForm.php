<?php

namespace Drupal\component_entity\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Provides a form for deleting Component type entities.
 */
class ComponentTypeDeleteForm extends EntityConfirmFormBase {

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
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a ComponentTypeDeleteForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    MessengerInterface $messenger
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the component type %name?', [
      '%name' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $description = $this->t('This action cannot be undone.');
    
    // Count existing components of this type.
    $count = $this->getComponentCount();
    
    if ($count > 0) {
      $description .= ' ' . $this->formatPlural(
        $count,
        '<strong>Warning:</strong> There is 1 component of this type. It will also be deleted.',
        '<strong>Warning:</strong> There are @count components of this type. They will all be deleted.'
      );
      
      // Add extra warning for large numbers.
      if ($count > 10) {
        $description .= ' ' . $this->t('<strong>This is a destructive operation that will delete a large amount of content!</strong>');
      }
    }
    
    // Check for field data that will be lost.
    $fields = $this->getFieldDefinitions();
    if (!empty($fields)) {
      $description .= ' ' . $this->t('All field data for this component type will be permanently deleted.');
    }
    
    return $description;
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.component_type.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    $count = $this->getComponentCount();
    
    if ($count > 10) {
      return $this->t('I understand this will delete @count components', ['@count' => $count]);
    }
    
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    
    $count = $this->getComponentCount();
    
    // Add confirmation checkbox for destructive operations.
    if ($count > 10) {
      $form['confirm_delete'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('I understand that this action will permanently delete @count components and cannot be undone.', [
          '@count' => $count,
        ]),
        '#required' => TRUE,
        '#weight' => -10,
      ];
      
      // List some example components that will be deleted.
      $examples = $this->getExampleComponents(5);
      if (!empty($examples)) {
        $items = [];
        foreach ($examples as $component) {
          $items[] = $component->label() . ' (ID: ' . $component->id() . ')';
        }
        
        $form['examples'] = [
          '#theme' => 'item_list',
          '#title' => $this->t('Examples of components that will be deleted:'),
          '#items' => $items,
          '#weight' => -5,
        ];
        
        if ($count > 5) {
          $form['examples']['#suffix'] = $this->t('... and @count more', [
            '@count' => $count - 5,
          ]);
        }
      }
    }
    
    // Add information about the component type.
    $form['info'] = [
      '#type' => 'details',
      '#title' => $this->t('Component type information'),
      '#open' => FALSE,
      '#weight' => -8,
    ];
    
    $form['info']['machine_name'] = [
      '#type' => 'item',
      '#title' => $this->t('Machine name'),
      '#markup' => $this->entity->id(),
    ];
    
    if ($sdc_id = $this->entity->get('sdc_id')) {
      $form['info']['sdc_id'] = [
        '#type' => 'item',
        '#title' => $this->t('SDC Component ID'),
        '#markup' => $sdc_id,
      ];
    }
    
    // Show field information.
    $fields = $this->getFieldDefinitions();
    if (!empty($fields)) {
      $field_list = [];
      foreach ($fields as $field_name => $field_definition) {
        if (strpos($field_name, 'field_') === 0) {
          $field_list[] = $field_definition->getLabel() . ' (' . $field_name . ')';
        }
      }
      
      if (!empty($field_list)) {
        $form['info']['fields'] = [
          '#theme' => 'item_list',
          '#title' => $this->t('Fields that will be deleted:'),
          '#items' => $field_list,
        ];
      }
    }
    
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    
    // Additional validation for large deletions.
    $count = $this->getComponentCount();
    if ($count > 10 && !$form_state->getValue('confirm_delete')) {
      $form_state->setError($form['confirm_delete'], $this->t('You must confirm the deletion of @count components.', [
        '@count' => $count,
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $component_type = $this->entity;
    $component_type_id = $component_type->id();
    $component_type_label = $component_type->label();
    
    // Delete all components of this type first.
    $component_storage = $this->entityTypeManager->getStorage('component');
    $component_ids = $component_storage->getQuery()
      ->condition('type', $component_type_id)
      ->accessCheck(FALSE)
      ->execute();
    
    $deleted_count = 0;
    if (!empty($component_ids)) {
      $components = $component_storage->loadMultiple($component_ids);
      $component_storage->delete($components);
      $deleted_count = count($components);
      
      // Clear cache for all deleted components.
      $cache_manager = \Drupal::service('component_entity.cache_manager');
      $cache_manager->invalidateBundleCache($component_type_id);
    }
    
    // Delete field storage configs if they're not used by other bundles.
    $this->deleteUnusedFieldStorage();
    
    // Delete the component type.
    $component_type->delete();
    
    // Log the deletion.
    $this->logger('component_entity')->notice('Deleted component type %name (ID: %id) and @count components.', [
      '%name' => $component_type_label,
      '%id' => $component_type_id,
      '@count' => $deleted_count,
    ]);
    
    // Set appropriate message.
    if ($deleted_count > 0) {
      $this->messenger->addStatus($this->formatPlural(
        $deleted_count,
        'Component type %name and 1 component have been deleted.',
        'Component type %name and @count components have been deleted.',
        ['%name' => $component_type_label]
      ));
    }
    else {
      $this->messenger->addStatus($this->t('Component type %name has been deleted.', [
        '%name' => $component_type_label,
      ]));
    }
    
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * Gets the count of components of this type.
   *
   * @return int
   *   The number of components.
   */
  protected function getComponentCount() {
    $storage = $this->entityTypeManager->getStorage('component');
    return $storage->getQuery()
      ->condition('type', $this->entity->id())
      ->accessCheck(FALSE)
      ->count()
      ->execute();
  }

  /**
   * Gets example components of this type.
   *
   * @param int $limit
   *   The maximum number of examples to return.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Array of component entities.
   */
  protected function getExampleComponents($limit = 5) {
    $storage = $this->entityTypeManager->getStorage('component');
    $ids = $storage->getQuery()
      ->condition('type', $this->entity->id())
      ->accessCheck(FALSE)
      ->range(0, $limit)
      ->sort('created', 'DESC')
      ->execute();
    
    return $storage->loadMultiple($ids);
  }

  /**
   * Gets field definitions for this component type.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   Array of field definitions.
   */
  protected function getFieldDefinitions() {
    return $this->entityFieldManager->getFieldDefinitions('component', $this->entity->id());
  }

  /**
   * Deletes unused field storage after bundle deletion.
   */
  protected function deleteUnusedFieldStorage() {
    $bundle = $this->entity->id();
    $fields = $this->getFieldDefinitions();
    
    foreach ($fields as $field_name => $field_definition) {
      // Only process configurable fields.
      if ($field_definition->isComputed() || !$field_definition->getFieldStorageDefinition()->isDeleteable()) {
        continue;
      }
      
      // Check if this field storage is used by other bundles.
      $field_storage = $field_definition->getFieldStorageDefinition();
      $bundles_using_field = $field_storage->getBundles();
      
      // Remove current bundle from the list.
      unset($bundles_using_field[$bundle]);
      
      // If no other bundles use this field storage, delete it.
      if (empty($bundles_using_field)) {
        $field_storage_config = $this->entityTypeManager
          ->getStorage('field_storage_config')
          ->load('component.' . $field_name);
        
        if ($field_storage_config && $field_storage_config->isDeletable()) {
          $field_storage_config->delete();
          
          $this->logger('component_entity')->notice('Deleted unused field storage: @field', [
            '@field' => $field_name,
          ]);
        }
      }
    }
  }

}