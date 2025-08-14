<?php

namespace Drupal\component_entity\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for Component Entity module settings.
 */
class ComponentEntitySettingsForm extends ConfigFormBase {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The theme handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The bidirectional sync service.
   *
   * @var \Drupal\component_entity\Service\BiDirectionalSyncInterface
   */
  protected $biDirectionalSync;

  /**
   * The validator service.
   *
   * @var \Drupal\component_entity\Service\ValidatorInterface
   */
  protected $validator;

  /**
   * The file writer service.
   *
   * @var \Drupal\component_entity\Service\FileWriterInterface
   */
  protected $fileWriter;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    ModuleHandlerInterface $module_handler,
    ThemeHandlerInterface $theme_handler,
    EntityTypeManagerInterface $entity_type_manager,
    MessengerInterface $messenger,
  ) {
    $this->moduleHandler = $module_handler;
    $this->themeHandler = $theme_handler;
    $this->entityTypeManager = $entity_type_manager;
    $this->setMessenger($messenger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static(
      $container->get('module_handler'),
      $container->get('theme_handler'),
      $container->get('entity_type.manager'),
      $container->get('messenger')
    );

    // Set optional services if they exist.
    if ($container->has('component_entity.bidirectional_sync')) {
      $instance->setBiDirectionalSync($container->get('component_entity.bidirectional_sync'));
    }
    if ($container->has('component_entity.validator')) {
      $instance->setValidator($container->get('component_entity.validator'));
    }
    if ($container->has('component_entity.file_writer')) {
      $instance->setFileWriter($container->get('component_entity.file_writer'));
    }

    return $instance;
  }

  /**
   * Sets the bidirectional sync service.
   *
   * @param \Drupal\component_entity\Service\BiDirectionalSyncInterface $bi_directional_sync
   *   The bidirectional sync service.
   */
  public function setBiDirectionalSync($bi_directional_sync) {
    $this->biDirectionalSync = $bi_directional_sync;
  }

  /**
   * Sets the validator service.
   *
   * @param \Drupal\component_entity\Service\ValidatorInterface $validator
   *   The validator service.
   */
  public function setValidator($validator) {
    $this->validator = $validator;
  }

  /**
   * Sets the file writer service.
   *
   * @param \Drupal\component_entity\Service\FileWriterInterface $file_writer
   *   The file writer service.
   */
  public function setFileWriter($file_writer) {
    $this->fileWriter = $file_writer;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'component_entity_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['component_entity.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('component_entity.settings');

    // Bi-directional sync settings.
    $form['bidirectional_sync'] = [
      '#type' => 'details',
      '#title' => $this->t('Bi-directional Sync Settings'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $form['bidirectional_sync']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable bi-directional sync'),
      '#description' => $this->t('Automatically sync changes between entity definitions and component files.'),
      '#default_value' => $config->get('bidirectional_sync.enabled'),
    ];

    $form['bidirectional_sync']['auto_generate_files'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto-generate component files'),
      '#description' => $this->t('Automatically create component files when new component types are created.'),
      '#default_value' => $config->get('bidirectional_sync.auto_generate_files'),
      '#states' => [
        'visible' => [
          ':input[name="bidirectional_sync[enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['bidirectional_sync']['auto_update_files'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto-update files on entity changes'),
      '#description' => $this->t('Automatically update component files when fields are added, modified, or removed.'),
      '#default_value' => $config->get('bidirectional_sync.auto_update_files'),
      '#states' => [
        'visible' => [
          ':input[name="bidirectional_sync[enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['bidirectional_sync']['sync_sdc_components'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Sync with SDC components'),
      '#description' => $this->t('Automatically create component types from discovered SDC components.'),
      '#default_value' => $config->get('bidirectional_sync.sync_sdc_components'),
      '#states' => [
        'visible' => [
          ':input[name="bidirectional_sync[enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['bidirectional_sync']['sync_interval'] = [
      '#type' => 'select',
      '#title' => $this->t('Sync interval'),
      '#description' => $this->t('How often to check for changes between files and entities.'),
      '#options' => [
        'immediate' => $this->t('Immediate (on save)'),
        'cron' => $this->t('During cron runs'),
        'manual' => $this->t('Manual only'),
      ],
      '#default_value' => $config->get('bidirectional_sync.sync_interval'),
      '#states' => [
        'visible' => [
          ':input[name="bidirectional_sync[enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Add sync actions.
    if ($config->get('bidirectional_sync.enabled')) {
      $form['sync_actions'] = [
        '#type' => 'details',
        '#title' => $this->t('Sync Actions'),
        '#open' => FALSE,
      ];

      $form['sync_actions']['sync_all'] = [
        '#type' => 'submit',
        '#value' => $this->t('Sync all components now'),
        '#submit' => ['::syncAllComponents'],
      ];

      $form['sync_actions']['validate_files'] = [
        '#type' => 'submit',
        '#value' => $this->t('Validate component files'),
        '#submit' => ['::validateComponentFiles'],
      ];
    }

    // File system settings.
    $form['file_system'] = [
      '#type' => 'details',
      '#title' => $this->t('File System Settings'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    // Get available modules and themes.
    $modules = $this->moduleHandler->getModuleList();
    $themes = $this->themeHandler->listInfo();

    $module_options = [];
    foreach ($modules as $module_name => $module) {
      $module_options[$module_name] = $module_name;
    }

    $theme_options = [];
    foreach ($themes as $theme_name => $theme) {
      $theme_options[$theme_name] = $theme->info['name'];
    }

    $form['file_system']['allowed_modules'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed modules for component storage'),
      '#description' => $this->t('Select which modules can store component files.'),
      '#options' => $module_options,
      '#default_value' => $config->get('file_system.allowed_modules') ?: [],
    ];

    $form['file_system']['allowed_themes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed themes for component storage'),
      '#description' => $this->t('Select which themes can store component files.'),
      '#options' => $theme_options,
      '#default_value' => $config->get('file_system.allowed_themes') ?: [],
    ];

    $form['file_system']['backup_directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Backup directory'),
      '#description' => $this->t('Directory to store component file backups (relative to private files directory).'),
      '#default_value' => $config->get('file_system.backup_directory') ?: 'component_backups',
    ];

    $form['file_system']['max_file_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum component file size (KB)'),
      '#description' => $this->t('Maximum allowed size for component files in kilobytes.'),
      '#default_value' => $config->get('file_system.max_file_size') ?: 500,
      '#min' => 1,
      '#max' => 10000,
    ];

    $form['file_system']['clear_backups'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear old backup files'),
      '#submit' => ['::clearBackupFiles'],
    ];

    // Security settings.
    $form['security'] = [
      '#type' => 'details',
      '#title' => $this->t('Security Settings'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['security']['sanitize_output'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Sanitize component output'),
      '#description' => $this->t('Apply additional sanitization to component output.'),
      '#default_value' => $config->get('security.sanitize_output'),
    ];

    $form['security']['allowed_tags'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed HTML tags'),
      '#description' => $this->t('List of allowed HTML tags in component output (one per line).'),
      '#default_value' => implode("\n", $config->get('security.allowed_tags') ?: []),
      '#states' => [
        'visible' => [
          ':input[name="security[sanitize_output]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['security']['csp_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Content Security Policy'),
      '#description' => $this->t('Add CSP headers for component pages.'),
      '#default_value' => $config->get('security.csp_enabled'),
    ];

    // Caching settings.
    $form['cache'] = [
      '#type' => 'details',
      '#title' => $this->t('Cache Settings'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['cache']['render_cache'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable render caching'),
      '#description' => $this->t('Cache rendered components for better performance.'),
      '#default_value' => $config->get('cache.render_cache'),
    ];

    $form['cache']['cache_lifetime'] = [
      '#type' => 'select',
      '#title' => $this->t('Cache lifetime'),
      '#options' => [
        0 => $this->t('No caching'),
        300 => $this->t('5 minutes'),
        900 => $this->t('15 minutes'),
        1800 => $this->t('30 minutes'),
        3600 => $this->t('1 hour'),
        86400 => $this->t('1 day'),
        604800 => $this->t('1 week'),
        -1 => $this->t('Permanent'),
      ],
      '#default_value' => $config->get('cache.cache_lifetime') ?: 3600,
      '#states' => [
        'visible' => [
          ':input[name="cache[render_cache]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['cache']['cache_contexts'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Cache contexts'),
      '#description' => $this->t('Select which contexts should vary the cache.'),
      '#options' => [
        'languages' => $this->t('Language'),
        'theme' => $this->t('Theme'),
        'user.permissions' => $this->t('User permissions'),
        'user.roles' => $this->t('User roles'),
        'url' => $this->t('URL'),
        'url.query_args' => $this->t('Query parameters'),
      ],
      '#default_value' => $config->get('cache.cache_contexts') ?: ['languages', 'theme'],
      '#states' => [
        'visible' => [
          ':input[name="cache[render_cache]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Logging settings.
    $form['logging'] = [
      '#type' => 'details',
      '#title' => $this->t('Logging Settings'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['logging']['log_sync_operations'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log sync operations'),
      '#description' => $this->t('Log all component sync operations to watchdog.'),
      '#default_value' => $config->get('logging.log_sync_operations'),
    ];

    $form['logging']['log_render_errors'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log render errors'),
      '#description' => $this->t('Log component rendering errors to watchdog.'),
      '#default_value' => $config->get('logging.log_render_errors'),
    ];

    $form['logging']['debug_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Debug mode'),
      '#description' => $this->t('Enable verbose logging for debugging purposes.'),
      '#default_value' => $config->get('logging.debug_mode'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('component_entity.settings');

    // Save bi-directional sync settings.
    $config->set('bidirectional_sync', $form_state->getValue('bidirectional_sync'));

    // Save file system settings.
    $file_system = $form_state->getValue('file_system');
    $config->set('file_system.allowed_modules', array_filter($file_system['allowed_modules']));
    $config->set('file_system.allowed_themes', array_filter($file_system['allowed_themes']));
    $config->set('file_system.backup_directory', $file_system['backup_directory']);
    $config->set('file_system.max_file_size', $file_system['max_file_size']);

    // Save security settings.
    $config->set('security', $form_state->getValue('security'));

    // Save cache settings.
    $config->set('cache', $form_state->getValue('cache'));

    // Save logging settings.
    $config->set('logging', $form_state->getValue('logging'));

    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Syncs all component types.
   */
  public function syncAllComponents(array &$form, FormStateInterface $form_state) {
    $batch = [
      'title' => $this->t('Syncing all components'),
      'operations' => [],
      'finished' => '\Drupal\component_entity\Form\ComponentEntitySettingsForm::batchFinished',
    ];

    $component_types = $this->entityTypeManager
      ->getStorage('component_type')
      ->loadMultiple();

    foreach ($component_types as $component_type) {
      $batch['operations'][] = [
        '\Drupal\component_entity\Form\ComponentEntitySettingsForm::batchSyncComponent',
        [$component_type->id()],
      ];
    }

    batch_set($batch);
  }

  /**
   * Batch operation for syncing a component.
   */
  public static function batchSyncComponent($component_type_id, &$context) {
    $container = \Drupal::getContainer();
    $entity_type_manager = $container->get('entity_type.manager');
    $component_type = $entity_type_manager
      ->getStorage('component_type')
      ->load($component_type_id);

    if ($component_type) {
      $sync_service = $container->get('component_entity.bidirectional_sync');
      $sync_service->checkAndSyncComponentType($component_type);

      $context['results'][] = $component_type_id;
      $context['message'] = t('Synced @type', ['@type' => $component_type->label()]);
    }
  }

  /**
   * Batch finished callback.
   */
  public static function batchFinished($success, $results, $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
      $messenger->addMessage(t('Successfully synced @count component types.', [
        '@count' => count($results),
      ]));
    }
    else {
      $messenger->addError(t('An error occurred during sync.'));
    }
  }

  /**
   * Validates component files.
   */
  public function validateComponentFiles(array &$form, FormStateInterface $form_state) {
    if ($this->validator) {
      // Validate all component files.
      $this->validator->validateAll();
      $this->messenger()->addMessage($this->t('Component file validation completed.'));
    }
    else {
      $this->messenger()->addWarning($this->t('Validator service not available.'));
    }
  }

  /**
   * Clears old backup files.
   */
  public function clearBackupFiles(array &$form, FormStateInterface $form_state) {
    if ($this->fileWriter) {
      // Clear old backup files.
      $this->fileWriter->clearOldBackups();
      $this->messenger()->addMessage($this->t('Old backup files have been cleared.'));
    }
    else {
      $this->messenger()->addWarning($this->t('File writer service not available.'));
    }
  }

}
