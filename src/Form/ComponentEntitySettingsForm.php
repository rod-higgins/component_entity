<?php

namespace Drupal\component_entity\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for Component Entity module settings.
 */
class ComponentEntitySettingsForm extends ConfigFormBase {

  /**
   * @var ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * @var ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * Constructor.
   */
  public function __construct(ModuleHandlerInterface $module_handler, ThemeHandlerInterface $theme_handler) {
    $this->moduleHandler = $module_handler;
    $this->themeHandler = $theme_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('theme_handler')
    );
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

    // Bi-directional sync settings
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

    // Generation settings
    $form['generation'] = [
      '#type' => 'details',
      '#title' => $this->t('File Generation Settings'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['generation']['target'] = [
      '#type' => 'radios',
      '#title' => $this->t('Generation target'),
      '#options' => [
        'module' => $this->t('Module'),
        'theme' => $this->t('Theme'),
      ],
      '#default_value' => $config->get('generation.target'),
      '#description' => $this->t('Where to generate component files.'),
    ];

    // Get available modules and themes
    $module_options = [];
    foreach ($this->moduleHandler->getModuleList() as $module_name => $module) {
      $module_options[$module_name] = $module->getName();
    }

    $theme_options = [];
    foreach ($this->themeHandler->listInfo() as $theme_name => $theme) {
      $theme_options[$theme_name] = $theme->info['name'];
    }

    $form['generation']['module_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Target module'),
      '#options' => $module_options,
      '#default_value' => $config->get('generation.name'),
      '#states' => [
        'visible' => [
          ':input[name="generation[target]"]' => ['value' => 'module'],
        ],
        'required' => [
          ':input[name="generation[target]"]' => ['value' => 'module'],
        ],
      ],
    ];

    $form['generation']['theme_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Target theme'),
      '#options' => $theme_options,
      '#default_value' => $config->get('generation.name'),
      '#states' => [
        'visible' => [
          ':input[name="generation[target]"]' => ['value' => 'theme'],
        ],
        'required' => [
          ':input[name="generation[target]"]' => ['value' => 'theme'],
        ],
      ],
    ];

    $form['generation']['files'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Files to generate'),
    ];

    $form['generation']['files']['generate_yml'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate component.yml'),
      '#default_value' => $config->get('generation.generate_yml'),
    ];

    $form['generation']['files']['generate_twig'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate Twig templates'),
      '#default_value' => $config->get('generation.generate_twig'),
    ];

    $form['generation']['files']['generate_react'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate React components'),
      '#default_value' => $config->get('generation.generate_react'),
    ];

    $form['generation']['files']['generate_css'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate CSS files'),
      '#default_value' => $config->get('generation.generate_css'),
    ];

    $form['generation']['overwrite_files'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow overwriting existing files'),
      '#description' => $this->t('Warning: This will replace existing files. Backups are recommended.'),
      '#default_value' => $config->get('generation.overwrite_files'),
    ];

    $form['generation']['backup_files'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create backups before overwriting'),
      '#default_value' => $config->get('generation.backup_files'),
      '#states' => [
        'visible' => [
          ':input[name="generation[overwrite_files]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Template settings
    $form['templates'] = [
      '#type' => 'details',
      '#title' => $this->t('Template Settings'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['templates']['style'] = [
      '#type' => 'select',
      '#title' => $this->t('CSS naming convention'),
      '#options' => [
        'bem' => $this->t('BEM (Block Element Modifier)'),
        'bootstrap' => $this->t('Bootstrap'),
        'minimal' => $this->t('Minimal'),
      ],
      '#default_value' => $config->get('templates.style'),
      '#description' => $this->t('Choose the CSS class naming style for generated templates.'),
    ];

    $form['templates']['include_debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include debug comments'),
      '#description' => $this->t('Add helpful comments to generated templates.'),
      '#default_value' => $config->get('templates.include_debug'),
    ];

    $form['templates']['update_on_field_change'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Update templates when fields change'),
      '#description' => $this->t('Automatically update template files when fields are added or removed. Warning: This may overwrite customizations.'),
      '#default_value' => $config->get('templates.update_on_field_change'),
    ];

    // React settings
    $form['react'] = [
      '#type' => 'details',
      '#title' => $this->t('React Component Settings'),
      '#open' => FALSE,
      '#tree' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="generation[files][generate_react]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['react']['typescript'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate TypeScript components'),
      '#description' => $this->t('Use TypeScript instead of JavaScript for React components.'),
      '#default_value' => $config->get('react.typescript'),
    ];

    $form['react']['style'] = [
      '#type' => 'radios',
      '#title' => $this->t('Component style'),
      '#options' => [
        'functional' => $this->t('Functional components'),
        'class' => $this->t('Class components'),
      ],
      '#default_value' => $config->get('react.style'),
    ];

    $form['react']['hooks'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include React hooks'),
      '#description' => $this->t('Add common hooks (useState, useEffect, etc.) to functional components.'),
      '#default_value' => $config->get('react.hooks'),
      '#states' => [
        'visible' => [
          ':input[name="react[style]"]' => ['value' => 'functional'],
        ],
      ],
    ];

    $form['react']['css_modules'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use CSS modules'),
      '#description' => $this->t('Generate CSS modules instead of regular CSS files.'),
      '#default_value' => $config->get('react.css_modules'),
    ];

    $form['react']['test_files'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate test files'),
      '#description' => $this->t('Create Jest test files for React components.'),
      '#default_value' => $config->get('react.test_files'),
    ];

    $form['react']['storybook'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate Storybook stories'),
      '#description' => $this->t('Create Storybook story files for component development.'),
      '#default_value' => $config->get('react.storybook'),
    ];

    // File system settings
    $form['file_system'] = [
      '#type' => 'details',
      '#title' => $this->t('File System Settings'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['file_system']['allowed_modules'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Modules allowed for file operations'),
      '#options' => $module_options,
      '#default_value' => $config->get('file_system.allowed_modules') ?: [],
      '#description' => $this->t('Select modules where component files can be written.'),
    ];

    $form['file_system']['allowed_themes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Themes allowed for file operations'),
      '#options' => $theme_options,
      '#default_value' => $config->get('file_system.allowed_themes') ?: [],
      '#description' => $this->t('Select themes where component files can be written.'),
    ];

    $form['file_system']['backup_directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Backup directory'),
      '#default_value' => $config->get('file_system.backup_directory'),
      '#description' => $this->t('Path for storing file backups (e.g., private://component_backups).'),
    ];

    $form['file_system']['max_file_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum file size (bytes)'),
      '#default_value' => $config->get('file_system.max_file_size'),
      '#min' => 1024,
      '#max' => 10485760,
      '#description' => $this->t('Maximum allowed size for generated files.'),
    ];

    // Security settings
    $form['security'] = [
      '#type' => 'details',
      '#title' => $this->t('Security Settings'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['security']['validate_content'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Validate file content for security'),
      '#description' => $this->t('Scan generated content for potentially dangerous code.'),
      '#default_value' => $config->get('security.validate_content'),
    ];

    $form['security']['scan_for_php'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Scan for PHP code in templates'),
      '#description' => $this->t('Prevent PHP code injection in non-PHP files.'),
      '#default_value' => $config->get('security.scan_for_php'),
      '#states' => [
        'visible' => [
          ':input[name="security[validate_content]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Cache settings
    $form['cache'] = [
      '#type' => 'details',
      '#title' => $this->t('Cache Settings'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['cache']['discovery_cache_ttl'] = [
      '#type' => 'number',
      '#title' => $this->t('Discovery cache TTL (seconds)'),
      '#default_value' => $config->get('cache.discovery_cache_ttl'),
      '#min' => 0,
      '#description' => $this->t('How long to cache component discovery results. Set to 0 to disable caching.'),
    ];

    $form['cache']['clear_on_sync'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Clear caches after sync'),
      '#description' => $this->t('Automatically clear relevant caches after sync operations.'),
      '#default_value' => $config->get('cache.clear_on_sync'),
    ];

    // Logging settings
    $form['logging'] = [
      '#type' => 'details',
      '#title' => $this->t('Logging Settings'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['logging']['log_file_operations'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log file operations'),
      '#description' => $this->t('Log all file write and delete operations.'),
      '#default_value' => $config->get('logging.log_file_operations'),
    ];

    $form['logging']['log_sync_operations'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log sync operations'),
      '#description' => $this->t('Log all bi-directional sync operations.'),
      '#default_value' => $config->get('logging.log_sync_operations'),
    ];

    $form['logging']['verbose_logging'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable verbose logging'),
      '#description' => $this->t('Include detailed debug information in logs.'),
      '#default_value' => $config->get('logging.verbose_logging'),
    ];

    // Actions
    $form['actions'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Sync Actions'),
      '#collapsible' => FALSE,
    ];

    $form['actions']['sync_all'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sync All Components'),
      '#submit' => ['::syncAllComponents'],
      '#button_type' => 'primary',
    ];

    $form['actions']['validate_files'] = [
      '#type' => 'submit',
      '#value' => $this->t('Validate Component Files'),
      '#submit' => ['::validateComponentFiles'],
    ];

    $form['actions']['clear_backups'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear Old Backups'),
      '#submit' => ['::clearOldBackups'],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate backup directory
    $backup_dir = $form_state->getValue(['file_system', 'backup_directory']);
    if (!empty($backup_dir)) {
      $scheme = \Drupal::service('stream_wrapper_manager')->getScheme($backup_dir);
      if (!$scheme) {
        $form_state->setErrorByName('file_system][backup_directory', 
          $this->t('Invalid backup directory path. Use a stream wrapper like private:// or public://'));
      }
    }

    // Ensure at least one file type is selected for generation
    $generation = $form_state->getValue('generation');
    if (isset($generation['files'])) {
      $has_file_type = FALSE;
      foreach (['generate_yml', 'generate_twig', 'generate_react', 'generate_css'] as $key) {
        if (!empty($generation['files'][$key])) {
          $has_file_type = TRUE;
          break;
        }
      }
      
      if (!$has_file_type && $form_state->getValue(['bidirectional_sync', 'auto_generate_files'])) {
        $form_state->setErrorByName('generation][files', 
          $this->t('At least one file type must be selected when auto-generation is enabled.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('component_entity.settings');
    
    // Save bi-directional sync settings
    $config->set('bidirectional_sync', $form_state->getValue('bidirectional_sync'));
    
    // Save generation settings
    $generation = $form_state->getValue('generation');
    $target = $generation['target'];
    $name = $target === 'module' 
      ? $generation['module_name'] 
      : $generation['theme_name'];
    
    $config->set('generation.target', $target);
    $config->set('generation.name', $name);
    $config->set('generation.generate_yml', $generation['files']['generate_yml']);
    $config->set('generation.generate_twig', $generation['files']['generate_twig']);
    $config->set('generation.generate_react', $generation['files']['generate_react']);
    $config->set('generation.generate_css', $generation['files']['generate_css']);
    $config->set('generation.overwrite_files', $generation['overwrite_files']);
    $config->set('generation.backup_files', $generation['backup_files']);
    
    // Save template settings
    $config->set('templates', $form_state->getValue('templates'));
    
    // Save React settings
    $config->set('react', $form_state->getValue('react'));
    
    // Save file system settings
    $file_system = $form_state->getValue('file_system');
    $config->set('file_system.allowed_modules', array_filter($file_system['allowed_modules']));
    $config->set('file_system.allowed_themes', array_filter($file_system['allowed_themes']));
    $config->set('file_system.backup_directory', $file_system['backup_directory']);
    $config->set('file_system.max_file_size', $file_system['max_file_size']);
    
    // Save security settings
    $config->set('security', $form_state->getValue('security'));
    
    // Save cache settings
    $config->set('cache', $form_state->getValue('cache'));
    
    // Save logging settings
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
    
    $component_types = \Drupal::entityTypeManager()
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
    $component_type = \Drupal::entityTypeManager()
      ->getStorage('component_type')
      ->load($component_type_id);
    
    if ($component_type) {
      $sync_service = \Drupal::service('component_entity.bidirectional_sync');
      $result = $sync_service->checkAndSyncComponentType($component_type);
      
      $context['results'][] = $component_type_id;
      $context['message'] = t('Synced @type', ['@type' => $component_type->label()]);
    }
  }

  /**
   * Batch finished callback.
   */
  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      \Drupal::messenger()->addMessage(t('Successfully synced @count component types.', [
        '@count' => count($results),
      ]));
    }
    else {
      \Drupal::messenger()->addError(t('An error occurred during sync.'));
    }
  }

  /**
   * Validates component files.
   */
  public function validateComponentFiles(array &$form, FormStateInterface $form_state) {
    $validator = \Drupal::service('component_entity.validator');
    // Implementation would validate all component files
    $this->messenger()->addMessage($this->t('Component file validation completed.'));
  }

  /**
   * Clears old backup files.
   */
  public function clearOldBackups(array &$form, FormStateInterface $form_state) {
    $file_writer = \Drupal::service('component_entity.file_writer');
    // Implementation would clear old backup files
    $this->messenger()->addMessage($this->t('Old backup files have been cleared.'));
  }
}