<?php

namespace Drupal\component_entity\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\component_entity\Service\ValidatorInterface;
use Drupal\component_entity\Service\CacheManagerInterface;

/**
 * Form controller for Component entity edit forms.
 */
class ComponentEntityForm extends ContentEntityForm {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The component validator service.
   *
   * @var \Drupal\component_entity\Service\ValidatorInterface
   */
  protected $validator;

  /**
   * The cache manager service.
   *
   * @var \Drupal\component_entity\Service\CacheManagerInterface
   */
  protected $cacheManager;

  /**
   * Constructs a ComponentEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\component_entity\Service\ValidatorInterface $validator
   *   The component validator service.
   * @param \Drupal\component_entity\Service\CacheManagerInterface $cache_manager
   *   The cache manager service.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    MessengerInterface $messenger,
    AccountProxyInterface $current_user,
    ValidatorInterface $validator,
    CacheManagerInterface $cache_manager,
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->messenger = $messenger;
    $this->currentUser = $current_user;
    $this->validator = $validator;
    $this->cacheManager = $cache_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('messenger'),
      $container->get('current_user'),
      $container->get('component_entity.validator'),
      $container->get('component_entity.cache_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    /** @var \Drupal\component_entity\Entity\ComponentEntityInterface $entity */
    $entity = $this->buildEntity($form, $form_state);

    // Validate component data against SDC schema if available.
    try {
      // Fixed: Use injected service instead of \Drupal::service()
      $this->validator->validateComponent($entity);
    }
    catch (\Exception $e) {
      $form_state->setError($form, $this->t('Component validation failed: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\component_entity\Entity\ComponentEntityInterface $entity */
    $entity = $this->entity;

    // Process React configuration.
    if ($form_state->hasValue('render_method')) {
      $entity->set('render_method', $form_state->getValue('render_method'));

      if ($form_state->getValue('render_method') === 'react') {
        $react_config = [
          'hydration' => $form_state->getValue('hydration', 'full'),
          'progressive' => (bool) $form_state->getValue('progressive', FALSE),
          'lazy' => (bool) $form_state->getValue('lazy', FALSE),
          'ssr' => (bool) $form_state->getValue('ssr', FALSE),
        ];
        $entity->set('react_config', json_encode($react_config));
      }
    }

    // Set revision information.
    if ($entity->getEntityType()->isRevisionable()) {
      $entity->setNewRevision();
      $entity->setRevisionUserId($this->currentUser->id());
      $entity->setRevisionCreationTime($this->time->getRequestTime());

      // Set revision log message.
      $revision_log = $form_state->getValue('revision_log');
      if (empty($revision_log)) {
        $revision_log = $this->t('Updated component @name', ['@name' => $entity->label()]);
      }
      $entity->setRevisionLogMessage($revision_log);
    }

    $status = parent::save($form, $form_state);

    // Clear component cache.
    // Fixed: Use injected service instead of \Drupal::service()
    $this->cacheManager->invalidateComponentCache($entity);

    // Set messages based on save status.
    switch ($status) {
      case SAVED_NEW:
        $this->messenger->addMessage($this->t('Created the %label component.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $this->messenger->addMessage($this->t('Saved the %label component.', [
          '%label' => $entity->label(),
        ]));
    }

    // Handle different submit buttons.
    $triggering_element = $form_state->getTriggeringElement();

    if (isset($triggering_element['#parents']) && in_array('save_continue', $triggering_element['#parents'])) {
      // Stay on the edit form.
      $form_state->setRedirect('entity.component.edit_form', ['component' => $entity->id()]);
    }
    elseif (isset($triggering_element['#parents']) && in_array('preview', $triggering_element['#parents'])) {
      // Redirect to preview.
      $form_state->setRedirect('component_entity.preview', ['component' => $entity->id()]);
    }
    else {
      // Default redirect to canonical page.
      if ($entity->hasLinkTemplate('canonical')) {
        $form_state->setRedirectUrl($entity->toUrl('canonical'));
      }
      else {
        $form_state->setRedirect('entity.component.collection');
      }
    }

    return $status;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /** @var \Drupal\component_entity\Entity\ComponentEntityInterface $entity */
    $entity = $this->entity;

    // Add component type information.
    $component_type = $this->entityTypeManager
      ->getStorage('component_type')
      ->load($entity->bundle());

    if ($component_type) {
      $form['component_info'] = [
        '#type' => 'details',
        '#title' => $this->t('Component Information'),
        '#open' => TRUE,
        '#weight' => -100,
        '#attributes' => ['class' => ['component-info-wrapper']],
      ];

      $form['component_info']['type_label'] = [
        '#type' => 'item',
        '#title' => $this->t('Component Type'),
        '#markup' => $component_type->label(),
      ];

      if ($description = $component_type->get('description')) {
        $form['component_info']['type_description'] = [
          '#type' => 'item',
          '#markup' => $description,
        ];
      }

      // Check if both render methods are available.
      $rendering_config = $component_type->get('rendering') ?? [];
      $has_dual_render = !empty($rendering_config['twig_enabled']) && !empty($rendering_config['react_enabled']);

      if ($has_dual_render) {
        // Add render method selection.
        $form['render_settings'] = [
          '#type' => 'details',
          '#title' => $this->t('Render Settings'),
          '#open' => TRUE,
          '#weight' => 100,
          '#attributes' => ['class' => ['component-render-settings']],
        ];

        $form['render_settings']['render_method'] = [
          '#type' => 'radios',
          '#title' => $this->t('Render Method'),
          '#options' => [
            'twig' => $this->t('Server-side (Twig)'),
            'react' => $this->t('Client-side (React)'),
          ],
          '#default_value' => $entity->get('render_method')->value ?? $rendering_config['default_method'] ?? 'twig',
          '#description' => $this->t('Choose how this component should be rendered. Twig provides server-side rendering for better SEO, while React enables client-side interactivity.'),
          '#required' => TRUE,
        ];

        // React-specific settings.
        $react_config = $entity->get('react_config')->value ?? [];
        if (is_string($react_config)) {
          $react_config = json_decode($react_config, TRUE) ?? [];
        }

        $form['render_settings']['react_settings'] = [
          '#type' => 'container',
          '#states' => [
            'visible' => [
              ':input[name="render_method"]' => ['value' => 'react'],
            ],
          ],
          '#attributes' => ['class' => ['react-settings-container']],
        ];

        $form['render_settings']['react_settings']['hydration'] = [
          '#type' => 'select',
          '#title' => $this->t('Hydration Method'),
          '#options' => [
            'full' => $this->t('Full hydration - Component is immediately interactive'),
            'partial' => $this->t('Partial hydration - Interactive on user interaction'),
            'none' => $this->t('No hydration - Static rendering only'),
          ],
          '#default_value' => $react_config['hydration'] ?? 'full',
          '#description' => $this->t('Controls how React components are initialized on the client.'),
        ];

        $form['render_settings']['react_settings']['progressive'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Progressive Enhancement'),
          '#default_value' => $react_config['progressive'] ?? FALSE,
          '#description' => $this->t('Render with Twig first, then enhance with React. Provides better initial load performance.'),
        ];

        $form['render_settings']['react_settings']['lazy'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Lazy Load Component'),
          '#default_value' => $react_config['lazy'] ?? FALSE,
          '#description' => $this->t('Load the React component only when needed, reducing initial bundle size.'),
        ];

        $form['render_settings']['react_settings']['ssr'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Server-Side Rendering (SSR)'),
          '#default_value' => $react_config['ssr'] ?? FALSE,
          '#description' => $this->t('Enable server-side rendering for better SEO and initial paint. Requires Node.js SSR service.'),
        // Disable until SSR service is configured.
          '#disabled' => TRUE,
        ];
      }
      else {
        // Single render method, set it as hidden field.
        $default_method = $rendering_config['twig_enabled'] ? 'twig' : 'react';
        $form['render_method'] = [
          '#type' => 'hidden',
          '#value' => $default_method,
        ];
      }
    }

    // Add preview button.
    $form['actions']['preview'] = [
      '#type' => 'submit',
      '#value' => $this->t('Preview'),
      '#submit' => ['::submitForm', '::preview'],
      '#weight' => 5,
      '#attributes' => ['class' => ['button--preview']],
    ];

    // Add "Save and continue" button for better UX.
    $form['actions']['save_continue'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save and continue editing'),
      '#submit' => ['::submitForm', '::save'],
      '#weight' => 7,
      '#attributes' => ['class' => ['button--primary']],
    ];

    // Attach library for form enhancements.
    $form['#attached']['library'][] = 'component_entity/admin';

    // Add AJAX preview container if in edit mode.
    if (!$entity->isNew()) {
      $form['preview_container'] = [
        '#type' => 'container',
        '#weight' => 200,
        '#attributes' => ['id' => 'component-preview-container'],
      ];

      $form['actions']['ajax_preview'] = [
        '#type' => 'button',
        '#value' => $this->t('Update Preview'),
        '#ajax' => [
          'callback' => '::ajaxPreview',
          'wrapper' => 'component-preview-container',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Generating preview...'),
          ],
        ],
        '#weight' => 4,
        '#attributes' => ['class' => ['button--small']],
      ];
    }

    return $form;
  }

  /**
   * Preview submit handler.
   */
  public function preview(array &$form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $form_state->setRedirect('component_entity.preview', ['component' => $entity->id()]);
  }

  /**
   * AJAX callback for preview.
   */
  public function ajaxPreview(array &$form, FormStateInterface $form_state) {
    $entity = $this->buildEntity($form, $form_state);

    // Build preview render array.
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('component');
    $preview = $view_builder->view($entity, 'default');

    // Wrap in preview container.
    $response = [
      '#theme' => 'component_preview',
      '#component_render' => $preview,
      '#component_type' => $entity->bundle(),
      '#render_method' => $entity->get('render_method')->value ?? 'twig',
      '#cache_tags' => $entity->getCacheTags(),
    ];

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);

    // Customize button labels.
    if (isset($actions['submit'])) {
      $actions['submit']['#value'] = $this->entity->isNew()
        ? $this->t('Create component')
        : $this->t('Save component');
    }

    // Add classes for styling.
    $actions['submit']['#attributes']['class'][] = 'button--primary';

    if (isset($actions['delete'])) {
      $actions['delete']['#attributes']['class'][] = 'button--danger';
    }

    return $actions;
  }

}
