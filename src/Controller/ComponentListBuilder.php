<?php

namespace Drupal\component_entity\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Datetime\DateFormatterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for Component entities.
 */
class ComponentListBuilder extends EntityListBuilder {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a new ComponentListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, DateFormatterInterface $date_formatter) {
    parent::__construct($entity_type, $storage);
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['name'] = $this->t('Name');
    $header['type'] = $this->t('Type');
    $header['render_method'] = $this->t('Render');
    $header['status'] = $this->t('Status');
    $header['author'] = $this->t('Author');
    $header['changed'] = $this->t('Updated');
    
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\component_entity\Entity\ComponentEntityInterface $entity */
    $row['id'] = $entity->id();
    
    // Component name with link to view.
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.component.canonical',
      ['component' => $entity->id()]
    );
    
    // Component type.
    $component_type = $entity->get('type')->entity;
    if ($component_type) {
      $row['type'] = Link::createFromRoute(
        $component_type->label(),
        'entity.component_type.edit_form',
        ['component_type' => $component_type->id()]
      );
    }
    else {
      $row['type'] = $entity->bundle();
    }
    
    // Render method with badge styling.
    $render_method = $entity->getRenderMethod();
    $row['render_method'] = [
      'data' => [
        '#type' => 'inline_template',
        '#template' => '<span class="render-method-badge render-method-badge--{{ method }}">{{ method|upper }}</span>',
        '#context' => [
          'method' => $render_method,
        ],
      ],
    ];
    
    // Publishing status.
    $row['status'] = $entity->isPublished() ? $this->t('Published') : $this->t('Unpublished');
    
    // Author information.
    $owner = $entity->getOwner();
    if ($owner) {
      $row['author'] = [
        'data' => [
          '#theme' => 'username',
          '#account' => $owner,
        ],
      ];
    }
    else {
      $row['author'] = $this->t('Anonymous');
    }
    
    // Last updated time.
    $row['changed'] = $this->dateFormatter->format($entity->getChangedTime(), 'short');
    
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    
    // Add component entity admin library for styling.
    $build['#attached']['library'][] = 'component_entity/admin';
    
    // Add contextual filter form.
    $build['filters'] = $this->buildFilterForm();
    
    // Add help text if no components exist.
    if (empty($this->load())) {
      $build['empty'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['component-list-empty']],
        'message' => [
          '#markup' => $this->t('No components have been created yet.'),
        ],
      ];
      
      // Add link to create first component if user has permission.
      $account = \Drupal::currentUser();
      $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo('component');
      $create_links = [];
      
      foreach ($bundles as $bundle_id => $bundle_info) {
        if ($account->hasPermission('create ' . $bundle_id . ' component')) {
          $create_links[] = Link::createFromRoute(
            $bundle_info['label'],
            'entity.component.add_form',
            ['component_type' => $bundle_id]
          )->toString();
        }
      }
      
      if (!empty($create_links)) {
        $build['empty']['create'] = [
          '#type' => 'item',
          '#markup' => $this->t('Create your first component: @links', [
            '@links' => implode(' | ', $create_links),
          ]),
        ];
      }
    }
    
    return $build;
  }

  /**
   * Builds the filter form for the component list.
   *
   * @return array
   *   The filter form render array.
   */
  protected function buildFilterForm() {
    $form = [
      '#type' => 'details',
      '#title' => $this->t('Filter components'),
      '#open' => FALSE,
      '#attributes' => ['class' => ['component-list-filters']],
    ];
    
    // Component type filter.
    $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo('component');
    $type_options = ['all' => $this->t('- All types -')];
    foreach ($bundles as $bundle_id => $bundle_info) {
      $type_options[$bundle_id] = $bundle_info['label'];
    }
    
    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Component type'),
      '#options' => $type_options,
      '#default_value' => 'all',
      '#attributes' => [
        'class' => ['component-filter-type'],
        'data-component-filter' => 'type',
      ],
    ];
    
    // Render method filter.
    $form['render_method'] = [
      '#type' => 'select',
      '#title' => $this->t('Render method'),
      '#options' => [
        'all' => $this->t('- All methods -'),
        'twig' => $this->t('Twig'),
        'react' => $this->t('React'),
      ],
      '#default_value' => 'all',
      '#attributes' => [
        'class' => ['component-filter-render-method'],
        'data-component-filter' => 'render_method',
      ],
    ];
    
    // Status filter.
    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => [
        'all' => $this->t('- Any status -'),
        '1' => $this->t('Published'),
        '0' => $this->t('Unpublished'),
      ],
      '#default_value' => 'all',
      '#attributes' => [
        'class' => ['component-filter-status'],
        'data-component-filter' => 'status',
      ],
    ];
    
    // Search field.
    $form['search'] = [
      '#type' => 'search',
      '#title' => $this->t('Search'),
      '#placeholder' => $this->t('Search components...'),
      '#attributes' => [
        'class' => ['component-filter-search'],
        'data-component-filter' => 'search',
      ],
    ];
    
    // Filter actions.
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Filter'),
        '#attributes' => ['class' => ['component-filter-submit']],
      ],
      'reset' => [
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#attributes' => ['class' => ['component-filter-reset']],
      ],
    ];
    
    // Add JavaScript for client-side filtering.
    $form['#attached']['library'][] = 'component_entity/list-filters';
    
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery()
      ->accessCheck(TRUE)
      ->sort('changed', 'DESC');
    
    // Add filters from request.
    $request = \Drupal::request();
    
    if ($type = $request->query->get('type')) {
      if ($type !== 'all') {
        $query->condition('type', $type);
      }
    }
    
    if ($render_method = $request->query->get('render_method')) {
      if ($render_method !== 'all') {
        $query->condition('render_method', $render_method);
      }
    }
    
    if ($status = $request->query->get('status')) {
      if ($status !== 'all') {
        $query->condition('status', $status);
      }
    }
    
    if ($search = $request->query->get('search')) {
      $query->condition('name', $search, 'CONTAINS');
    }
    
    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }
    
    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    
    // Add preview operation.
    if ($entity->access('preview') && $entity->hasLinkTemplate('preview')) {
      $operations['preview'] = [
        'title' => $this->t('Preview'),
        'weight' => 5,
        'url' => $this->ensureDestination($entity->toUrl('preview')),
      ];
    }
    
    // Add duplicate operation.
    if ($entity->access('create')) {
      $operations['duplicate'] = [
        'title' => $this->t('Duplicate'),
        'weight' => 15,
        'url' => $this->ensureDestination(Url::fromRoute('entity.component.duplicate_form', [
          'component' => $entity->id(),
        ])),
      ];
    }
    
    // Add revision operations if applicable.
    if ($entity->getEntityType()->isRevisionable() && $entity->access('view_revision')) {
      $operations['revisions'] = [
        'title' => $this->t('Revisions'),
        'weight' => 20,
        'url' => $entity->toUrl('version-history'),
      ];
    }
    
    return $operations;
  }

}