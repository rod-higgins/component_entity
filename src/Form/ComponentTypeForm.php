<?php

namespace Drupal\component_entity\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Component\ComponentPluginManager;
use Drupal\component_entity\ComponentSyncService;
use Drupal\component_entity\ComponentCacheManager;
use Drupal\Core\Extension\ModuleExtensionList;

/**
 * Form handler for Component type add and edit forms.
 */
class ComponentTypeForm extends EntityForm {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The SDC component plugin manager.
   *
   * @var \Drupal\Core\Plugin\Component\ComponentPluginManager
   */
  protected $componentManager;

  /**
   * The component sync service.
   *
   * @var \Drupal\component_entity\ComponentSyncService
   */
  protected $syncService;

  /**
   * The cache manager service.
   *
   * @var \Drupal\component_entity\ComponentCacheManager
   */
  protected $cacheManager;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * Constructs a ComponentTypeForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Plugin\Component\ComponentPluginManager $component_manager
   *   The SDC component plugin manager.
   * @param \Drupal\component_entity\ComponentSyncService $sync_service
   *   The component sync service.
   * @param \Drupal\component_entity\ComponentCacheManager $cache_manager
   *   The cache manager service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MessengerInterface $messenger,
    ComponentPluginManager $component_manager,
    ComponentSyncService $sync_service,
    ComponentCacheManager $cache_manager,
    ModuleExtensionList $module_extension_list,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->componentManager = $component_manager;
    $this->syncService = $sync_service;
    $this->cacheManager = $cache_manager;
    $this->moduleExtensionList = $module_extension_list;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $container->get('plugin.manager.sdc'),
      $container->get('component_entity.sync'),
      $container->get('component_entity.cache_manager'),
      $container->get('extension.list.module')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\component_entity\Entity\ComponentTypeInterface $component_type */
    $component_type = $this->entity;

    // Basic information.
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $component_type->label(),
      '#description' => $this->t('Name of the component type.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $component_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\component_entity\Entity\ComponentType::load',
        'source' => ['label'],
      ],
      '#disabled' => !$component_type->isNew(),
      '#description' => $this->t('A unique machine-readable name for this component type. It must only contain lowercase letters, numbers, and underscores.'),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $component_type->get('description'),
      '#description' => $this->t('A brief description of this component type.'),
    ];

    // Rendering configuration.
    $form['rendering'] = [
      '#type' => 'details',
      '#title' => $this->t('Rendering Configuration'),
      '#open' => TRUE,
    ];

    $form['rendering']['twig_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Twig rendering'),
      '#default_value' => $component_type->get('rendering')['twig_enabled'] ?? TRUE,
      '#description' => $this->t('Allow this component to be rendered using Twig templates.'),
    ];

    $form['rendering']['react_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable React rendering'),
      '#default_value' => $component_type->get('rendering')['react_enabled'] ?? FALSE,
      '#description' => $this->t('Allow this component to be rendered using React.'),
    ];

    $form['rendering']['default_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Default render method'),
      '#options' => [
        'twig' => $this->t('Twig'),
        'react' => $this->t('React'),
      ],
      '#default_value' => $component_type->get('rendering')['default_method'] ?? 'twig',
      '#description' => $this->t('The default rendering method for this component type.'),
    ];

    $form['rendering']['react_library'] = [
      '#type' => 'textfield',
      '#title' => $this->t('React library'),
      '#default_value' => $component_type->get('rendering')['react_library'] ?? '',
      '#description' => $this->t('The Drupal library that contains the React component (e.g., "mymodule/my-component").'),
      '#states' => [
        'visible' => [
          ':input[name="react_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // SDC Mapping.
    $form['sdc_mapping'] = [
      '#type' => 'details',
      '#title' => $this->t('SDC Component Mapping'),
      '#open' => TRUE,
    ];

    $form['sdc_mapping']['sdc_id'] = [
      '#type' => 'select',
      '#title' => $this->t('SDC Component'),
      '#options' => $this->getAllAvailableComponents(),
      '#default_value' => $component_type->get('sdc_id'),
      '#empty_option' => $this->t('- Select SDC Component -'),
      '#description' => $this->t('Select the SDC component this type should sync with.'),
      '#ajax' => [
        'callback' => '::updateComponentInfo',
        'wrapper' => 'component-info-wrapper',
      ],
    ];

    // Component info display.
    $form['sdc_mapping']['component_info'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'component-info-wrapper'],
    ];

    $sdc_id = $form_state->getValue('sdc_id') ?? $component_type->get('sdc_id');
    if ($sdc_id) {
      $form['sdc_mapping']['component_info']['details'] = $this->buildComponentInfo($sdc_id);
    }

    // Advanced settings.
    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Settings'),
      '#open' => FALSE,
    ];

    $form['advanced']['revision'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create new revision'),
      '#default_value' => $component_type->get('revision') ?? FALSE,
      '#description' => $this->t('Automatically create a new revision when components of this type are updated.'),
    ];

    $form['advanced']['preview_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Preview mode'),
      '#options' => [
        'none' => $this->t('No preview'),
        'inline' => $this->t('Inline preview'),
        'modal' => $this->t('Modal preview'),
        'sidebar' => $this->t('Sidebar preview'),
      ],
      '#default_value' => $component_type->get('preview_mode') ?? 'inline',
      '#description' => $this->t('How to display previews of this component type.'),
    ];

    $form['advanced']['workflow'] = [
      '#type' => 'select',
      '#title' => $this->t('Workflow'),
      '#options' => [
        'none' => $this->t('No workflow'),
        'simple' => $this->t('Simple (Draft/Published)'),
        'editorial' => $this->t('Editorial workflow'),
      ],
      '#default_value' => $component_type->get('workflow') ?? 'none',
      '#description' => $this->t('The workflow to use for components of this type.'),
    ];

    // Validation settings.
    $form['validation'] = [
      '#type' => 'details',
      '#title' => $this->t('Validation Settings'),
      '#open' => FALSE,
    ];

    $form['validation']['strict_schema'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Strict schema validation'),
      '#default_value' => $component_type->get('strict_schema') ?? TRUE,
      '#description' => $this->t('Enforce strict validation against the SDC component schema.'),
    ];

    $form['validation']['required_fields'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Required fields'),
      '#default_value' => $component_type->get('required_fields') ?? '',
      '#description' => $this->t('Machine names of fields that should be required, one per line.'),
      '#rows' => 3,
    ];

    // Auto-sync configuration.
    $form['sync'] = [
      '#type' => 'details',
      '#title' => $this->t('Synchronization Settings'),
      '#open' => FALSE,
    ];

    $form['sync']['auto_sync'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto-sync with SDC'),
      '#default_value' => $component_type->get('auto_sync') ?? TRUE,
      '#description' => $this->t('Automatically synchronize fields when the SDC component definition changes.'),
    ];

    $form['sync']['sync_slots'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Sync slots as fields'),
      '#default_value' => $component_type->get('sync_slots') ?? TRUE,
      '#description' => $this->t('Create fields for SDC component slots.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate that at least one render method is enabled.
    if (!$form_state->getValue('twig_enabled') && !$form_state->getValue('react_enabled')) {
      $form_state->setError($form['rendering']['twig_enabled'], $this->t('At least one render method must be enabled.'));
    }

    // Validate default render method.
    $default_method = $form_state->getValue('default_method');
    if ($default_method === 'react' && !$form_state->getValue('react_enabled')) {
      $form_state->setError($form['rendering']['default_method'], $this->t('Cannot set React as default when React rendering is disabled.'));
    }
    if ($default_method === 'twig' && !$form_state->getValue('twig_enabled')) {
      $form_state->setError($form['rendering']['default_method'], $this->t('Cannot set Twig as default when Twig rendering is disabled.'));
    }

    // Validate SDC component exists.
    $sdc_id = $form_state->getValue('sdc_id');
    if ($sdc_id) {
      try {
        $component = $this->componentManager->find($sdc_id);
        if (!$component) {
          $form_state->setError($form['sdc_mapping']['sdc_id'], $this->t('The selected SDC component does not exist.'));
        }
      }
      catch (\Exception $e) {
        $form_state->setError($form['sdc_mapping']['sdc_id'], $this->t('Error loading SDC component: @message', [
          '@message' => $e->getMessage(),
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\component_entity\Entity\ComponentTypeInterface $component_type */
    $component_type = $this->entity;

    // Set rendering configuration.
    $component_type->set('rendering', [
      'twig_enabled' => $form_state->getValue('twig_enabled'),
      'react_enabled' => $form_state->getValue('react_enabled'),
      'default_method' => $form_state->getValue('default_method'),
      'react_library' => $form_state->getValue('react_library'),
    ]);

    // Set other configurations.
    $component_type->set('description', $form_state->getValue('description'));
    $component_type->set('sdc_id', $form_state->getValue('sdc_id'));
    $component_type->set('revision', $form_state->getValue('revision'));
    $component_type->set('preview_mode', $form_state->getValue('preview_mode'));
    $component_type->set('workflow', $form_state->getValue('workflow'));
    $component_type->set('strict_schema', $form_state->getValue('strict_schema'));
    $component_type->set('required_fields', $form_state->getValue('required_fields'));
    $component_type->set('auto_sync', $form_state->getValue('auto_sync'));
    $component_type->set('sync_slots', $form_state->getValue('sync_slots'));

    $status = $component_type->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger->addMessage($this->t('Created the %label component type.', [
          '%label' => $component_type->label(),
        ]));

        // Trigger field sync if auto-sync is enabled.
        if ($form_state->getValue('auto_sync')) {
          $this->triggerFieldSync($component_type);
        }

        break;

      default:
        $this->messenger->addMessage($this->t('Saved the %label component type.', [
          '%label' => $component_type->label(),
        ]));

        // Clear cache for this component type.
        $this->cacheManager->invalidateBundleCache($component_type->id());
    }

    // Redirect to the collection page.
    $form_state->setRedirectUrl($component_type->toUrl('collection'));
  }

  /**
   * AJAX callback to update component information.
   */
  public function updateComponentInfo(array &$form, FormStateInterface $form_state) {
    return $form['sdc_mapping']['component_info'];
  }

  /**
   * Builds component information display.
   *
   * @param string $sdc_id
   *   The SDC component ID.
   *
   * @return array
   *   Render array with component information.
   */
  protected function buildComponentInfo($sdc_id) {
    $info = [];

    try {
      $component = $this->componentManager->find($sdc_id);
      if ($component) {
        $metadata = $component->metadata;

        $info['name'] = [
          '#type' => 'item',
          '#title' => $this->t('Component Name'),
          '#markup' => $metadata->name ?? $sdc_id,
        ];

        if (!empty($metadata->description)) {
          $info['description'] = [
            '#type' => 'item',
            '#title' => $this->t('Description'),
            '#markup' => $metadata->description,
          ];
        }

        // Display props.
        if (!empty($metadata->props)) {
          $props_list = [];
          foreach ($metadata->props as $prop_name => $prop_definition) {
            $required = !empty($prop_definition['required']) ? ' (required)' : '';
            $props_list[] = $prop_name . $required;
          }
          $info['props'] = [
            '#type' => 'item',
            '#title' => $this->t('Props'),
            '#markup' => implode(', ', $props_list),
          ];
        }

        // Display slots.
        if (!empty($metadata->slots)) {
          $info['slots'] = [
            '#type' => 'item',
            '#title' => $this->t('Slots'),
            '#markup' => implode(', ', array_keys($metadata->slots)),
          ];
        }

        // Check if React component exists.
        if ($this->hasReactComponent($sdc_id)) {
          $info['react_available'] = [
            '#type' => 'item',
            '#title' => $this->t('React Component'),
            '#markup' => '<span class="color-success">' . $this->t('Available') . '</span>',
          ];
        }
      }
    }
    catch (\Exception $e) {
      $info['error'] = [
        '#type' => 'item',
        '#markup' => '<div class="messages messages--error">' . $this->t('Error loading component: @message', [
          '@message' => $e->getMessage(),
        ]) . '</div>',
      ];
    }

    return $info;
  }

  /**
   * Gets all available SDC components.
   *
   * @return array
   *   Array of component options keyed by ID.
   */
  protected function getAllAvailableComponents() {
    $options = [];

    try {
      $components = $this->componentManager->getAllComponents();
      foreach ($components as $id => $component) {
        $name = $component->metadata->name ?? $id;
        $provider = $component->getPluginDefinition()['provider'] ?? 'unknown';
        $options[$provider][$id] = $name;
      }
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('Error loading components: @message', [
        '@message' => $e->getMessage(),
      ]));
    }

    return $options;
  }

  /**
   * Checks if a React component exists for the given SDC ID.
   *
   * @param string $sdc_id
   *   The SDC component ID.
   *
   * @return bool
   *   TRUE if React component exists, FALSE otherwise.
   */
  protected function hasReactComponent($sdc_id) {
    // Parse the SDC ID to get module and component name.
    $parts = explode(':', $sdc_id);
    if (count($parts) !== 2) {
      return FALSE;
    }

    [$module, $component_name] = $parts;

    // Check common locations for React components.
    $module_path = $this->moduleExtensionList->getPath($module);

    $possible_files = [
      $module_path . '/js/components/' . $component_name . '.js',
      $module_path . '/js/components/' . $component_name . '.jsx',
      $module_path . '/components/' . $component_name . '/' . $component_name . '.js',
      $module_path . '/components/' . $component_name . '/' . $component_name . '.jsx',
      $module_path . '/components/' . $component_name . '/' . $component_name . '.tsx',
      $module_path . '/dist/js/' . $component_name . '.component.js',
    ];

    foreach ($possible_files as $file) {
      if (file_exists($file)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Triggers field synchronization for a component type.
   *
   * @param \Drupal\component_entity\Entity\ComponentTypeInterface $component_type
   *   The component type entity.
   */
  protected function triggerFieldSync($component_type) {
    try {
      $sdc_id = $component_type->get('sdc_id');

      if ($sdc_id) {
        $component = $this->componentManager->find($sdc_id);
        if ($component) {
          $this->syncService->syncComponent($sdc_id, $component, TRUE);
          $this->messenger->addMessage($this->t('Fields have been synchronized from the SDC component definition.'));
        }
      }
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('Error synchronizing fields: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

}
