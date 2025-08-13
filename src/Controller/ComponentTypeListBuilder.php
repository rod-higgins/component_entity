<?php

namespace Drupal\component_entity\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Component type entities.
 */
class ComponentTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The SDC plugin manager.
   *
   * @var \Drupal\Core\Plugin\Component\ComponentPluginManager
   */
  protected $componentManager;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id())
    );
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->componentManager = $container->get('plugin.manager.sdc');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Component type');
    $header['id'] = $this->t('Machine name');
    $header['sdc_id'] = $this->t('SDC ID');
    $header['rendering'] = $this->t('Rendering');
    $header['count'] = $this->t('Components');
    $header['fields'] = $this->t('Fields');
    
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\component_entity\Entity\ComponentTypeInterface $entity */
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    
    // SDC component ID with sync status.
    $sdc_id = $entity->get('sdc_id');
    if ($sdc_id) {
      // Check if SDC component still exists.
      try {
        $component = $this->componentManager->find($sdc_id);
        if ($component) {
          $row['sdc_id'] = [
            'data' => [
              '#type' => 'inline_template',
              '#template' => '<span class="sdc-status sdc-status--synced" title="{{ title }}">{{ id }}</span>',
              '#context' => [
                'id' => $sdc_id,
                'title' => $this->t('Synced with SDC component'),
              ],
            ],
          ];
        }
        else {
          $row['sdc_id'] = [
            'data' => [
              '#type' => 'inline_template',
              '#template' => '<span class="sdc-status sdc-status--missing" title="{{ title }}">{{ id }}</span>',
              '#context' => [
                'id' => $sdc_id,
                'title' => $this->t('SDC component not found'),
              ],
            ],
          ];
        }
      }
      catch (\Exception $e) {
        $row['sdc_id'] = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '<span class="sdc-status sdc-status--error" title="{{ title }}">{{ id }}</span>',
            '#context' => [
              'id' => $sdc_id,
              'title' => $this->t('Error loading SDC component'),
            ],
          ],
        ];
      }
    }
    else {
      $row['sdc_id'] = [
        'data' => [
          '#markup' => '<em>' . $this->t('Not synced') . '</em>',
        ],
      ];
    }
    
    // Rendering methods.
    $rendering = $entity->get('rendering') ?? [];
    $methods = [];
    
    if (!empty($rendering['twig_enabled'])) {
      $methods[] = '<span class="render-method-badge render-method-badge--twig">Twig</span>';
    }
    if (!empty($rendering['react_enabled'])) {
      $methods[] = '<span class="render-method-badge render-method-badge--react">React</span>';
    }
    
    $row['rendering'] = [
      'data' => [
        '#markup' => !empty($methods) ? implode(' ', $methods) : '<em>' . $this->t('None') . '</em>',
      ],
    ];
    
    // Component count.
    $count = $this->entityTypeManager
      ->getStorage('component')
      ->getQuery()
      ->condition('type', $entity->id())
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    
    if ($count > 0) {
      $row['count'] = Link::createFromRoute(
        $count,
        'entity.component.collection',
        [],
        ['query' => ['type' => $entity->id()]]
      );
    }
    else {
      $row['count'] = 0;
    }
    
    // Field count with link to manage fields.
    $fields = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('component', $entity->id());
    
    $field_count = 0;
    foreach ($fields as $field_name => $field) {
      if (strpos($field_name, 'field_') === 0) {
        $field_count++;
      }
    }
    
    if (\Drupal::currentUser()->hasPermission('administer component types')) {
      $row['fields'] = Link::createFromRoute(
        $this->formatPlural($field_count, '1 field', '@count fields'),
        'entity.component.field_ui_fields',
        ['component_type' => $entity->id()]
      );
    }
    else {
      $row['fields'] = $this->formatPlural($field_count, '1 field', '@count fields');
    }
    
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    
    // Add admin library for styling.
    $build['#attached']['library'][] = 'component_entity/admin';
    
    // Add sync status summary.
    $build['summary'] = $this->buildSyncSummary();
    
    // Add action buttons.
    $build['actions'] = $this->buildActions();
    
    // Add help text if no component types exist.
    if (empty($this->load())) {
      $build['empty'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['component-types-empty']],
        'message' => [
          '#markup' => $this->t('No component types have been created yet.'),
        ],
        'help' => [
          '#markup' => $this->t('Component types are automatically created when you sync SDC components or can be created manually.'),
        ],
      ];
    }
    
    return $build;
  }

  /**
   * Builds the sync summary section.
   *
   * @return array
   *   The sync summary render array.
   */
  protected function buildSyncSummary() {
    $sync_service = \Drupal::service('component_entity.sync');
    $status = $sync_service->getSyncStatus();
    
    $summary = [
      '#type' => 'details',
      '#title' => $this->t('Sync Status'),
      '#open' => TRUE,
      '#attributes' => ['class' => ['component-sync-summary']],
    ];
    
    // SDC components count.
    $sdc_components = $this->componentManager->getAllComponents();
    $sdc_count = count($sdc_components);
    
    $summary['stats'] = [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('@count SDC components available', ['@count' => $sdc_count]),
        $this->t('@count component types synced', ['@count' => $status['synced_count']]),
      ],
    ];
    
    // Last sync time.
    if ($status['last_sync']) {
      $summary['last_sync'] = [
        '#markup' => $this->t('Last sync: @time', [
          '@time' => \Drupal::service('date.formatter')->format($status['last_sync'], 'short'),
        ]),
      ];
    }
    
    // Sync needed indicator.
    if ($sdc_count > $status['synced_count']) {
      $summary['sync_needed'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--warning']],
        'message' => [
          '#markup' => $this->t('@count SDC components are not synced.', [
            '@count' => $sdc_count - $status['synced_count'],
          ]),
        ],
      ];
    }
    
    return $summary;
  }

  /**
   * Builds the action buttons section.
   *
   * @return array
   *   The actions render array.
   */
  protected function buildActions() {
    $actions = [
      '#type' => 'container',
      '#attributes' => ['class' => ['component-type-actions']],
    ];
    
    $current_user = \Drupal::currentUser();
    
    // Sync button.
    if ($current_user->hasPermission('sync sdc components')) {
      $actions['sync'] = [
        '#type' => 'link',
        '#title' => $this->t('Sync SDC Components'),
        '#url' => Url::fromRoute('component_entity.sync'),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ];
    }
    
    // Add component type button.
    if ($current_user->hasPermission('administer component types')) {
      $actions['add'] = [
        '#type' => 'link',
        '#title' => $this->t('Add component type'),
        '#url' => Url::fromRoute('entity.component_type.add_form'),
        '#attributes' => [
          'class' => ['button'],
        ],
      ];
    }
    
    // Build React components button.
    if ($current_user->hasPermission('administer component types')) {
      $actions['build'] = [
        '#type' => 'link',
        '#title' => $this->t('Build React Components'),
        '#url' => Url::fromRoute('component_entity.build'),
        '#attributes' => [
          'class' => ['button', 'use-ajax'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode([
            'width' => 700,
            'height' => 400,
          ]),
        ],
      ];
    }
    
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    
    // Add manage fields operation.
    if ($entity->id() && \Drupal::currentUser()->hasPermission('administer component types')) {
      $operations['manage-fields'] = [
        'title' => $this->t('Manage fields'),
        'weight' => 5,
        'url' => Url::fromRoute('entity.component.field_ui_fields', [
          'component_type' => $entity->id(),
        ]),
      ];
      
      $operations['manage-form-display'] = [
        'title' => $this->t('Manage form display'),
        'weight' => 10,
        'url' => Url::fromRoute('entity.entity_form_display.component.default', [
          'component_type' => $entity->id(),
        ]),
      ];
      
      $operations['manage-display'] = [
        'title' => $this->t('Manage display'),
        'weight' => 15,
        'url' => Url::fromRoute('entity.entity_view_display.component.default', [
          'component_type' => $entity->id(),
        ]),
      ];
    }
    
    // Add re-sync operation if this is an SDC-synced type.
    if ($entity->get('sdc_id') && \Drupal::currentUser()->hasPermission('sync sdc components')) {
      $operations['resync'] = [
        'title' => $this->t('Re-sync'),
        'weight' => 20,
        'url' => Url::fromRoute('component_entity.resync_type', [
          'component_type' => $entity->id(),
        ]),
        'attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
        ],
      ];
    }
    
    // Add duplicate operation.
    if (\Drupal::currentUser()->hasPermission('administer component types')) {
      $operations['duplicate'] = [
        'title' => $this->t('Duplicate'),
        'weight' => 25,
        'url' => Url::fromRoute('entity.component_type.duplicate_form', [
          'component_type' => $entity->id(),
        ]),
      ];
    }
    
    return $operations;
  }

}