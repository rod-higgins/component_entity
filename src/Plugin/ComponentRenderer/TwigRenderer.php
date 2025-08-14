<?php

namespace Drupal\component_entity\Plugin\ComponentRenderer;

use Drupal\component_entity\Entity\ComponentEntityInterface;
use Drupal\component_entity\Plugin\ComponentRendererBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\Core\Plugin\Component\ComponentPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides a Twig renderer for components.
 *
 * @ComponentRenderer(
 *   id = "twig",
 *   label = @Translation("Twig Renderer"),
 *   description = @Translation("Renders components using Twig templates with full Drupal integration."),
 *   method = "twig",
 *   supports_ssr = TRUE,
 *   supports_hydration = FALSE,
 *   supports_progressive = TRUE,
 *   weight = 0,
 *   file_extensions = {"twig", "html.twig"},
 *   libraries = {
 *     "component_entity/twig-renderer"
 *   },
 *   cache_contexts = {"theme", "languages", "user.permissions"},
 *   enabled = TRUE,
 *   config_schema = {
 *     "debug" = {
 *       "type" = "boolean",
 *       "label" = "Debug Mode",
 *       "default" = FALSE
 *     },
 *     "auto_reload" = {
 *       "type" = "boolean",
 *       "label" = "Auto Reload Templates",
 *       "default" = TRUE
 *     },
 *     "strict_variables" = {
 *       "type" = "boolean",
 *       "label" = "Strict Variables",
 *       "default" = FALSE
 *     }
 *   }
 * )
 */
class TwigRenderer extends ComponentRendererBase implements ContainerFactoryPluginInterface {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The Twig environment.
   *
   * @var \Drupal\Core\Template\TwigEnvironment
   */
  protected $twig;

  /**
   * The SDC component plugin manager.
   *
   * @var \Drupal\Core\Plugin\Component\ComponentPluginManager
   */
  protected $componentManager;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a TwigRenderer object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Template\TwigEnvironment $twig
   *   The Twig environment.
   * @param \Drupal\Core\Plugin\Component\ComponentPluginManager $component_manager
   *   The SDC component plugin manager.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RendererInterface $renderer,
    TwigEnvironment $twig,
    ComponentPluginManager $component_manager,
    ThemeManagerInterface $theme_manager,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->renderer = $renderer;
    $this->twig = $twig;
    $this->componentManager = $component_manager;
    $this->themeManager = $theme_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('renderer'),
      $container->get('twig'),
      $container->get('plugin.manager.sdc'),
      $container->get('theme.manager'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render(ComponentEntityInterface $entity, array $context = []) {
    $bundle = $entity->bundle();
    $view_mode = $context['view_mode'] ?? 'default';

    // Configure Twig environment based on settings.
    $this->configureTwigEnvironment();

    // Determine which template to use.
    $template = $this->determineTemplate($entity, $view_mode, $context);

    // Prepare variables for the template.
    $variables = $this->prepareVariables($entity, $context);

    // Build the render array.
    $build = [
      '#theme' => $template['theme_hook'] ?? 'component_entity',
      '#component' => $entity,
      '#variables' => $variables,
      '#view_mode' => $view_mode,
      '#bundle' => $bundle,
      '#attached' => [
        'library' => $this->getRequiredLibraries(),
      ],
      '#cache' => [
        'keys' => ['component', $bundle, $entity->id(), $view_mode],
        'contexts' => $this->getCacheContexts(),
        'tags' => $this->getCacheTags($entity),
        'max-age' => $this->getCacheMaxAge($entity),
      ],
    ];

    // If using SDC component, render it.
    if ($template['type'] === 'sdc') {
      $build = $this->renderSDCComponent($entity, $template['sdc_id'], $variables);
    }
    // If using custom template file.
    elseif ($template['type'] === 'custom') {
      $build['#theme'] = $template['theme_hook'];
      $build['#template'] = $template['template_file'];
    }

    // Allow other modules to alter the build.
    $this->moduleHandler->alter('component_twig_render', $build, $entity, $context);

    // Add debug information if enabled.
    if ($this->configuration['debug'] ?? FALSE) {
      $build = $this->addDebugInfo($build, $entity, $template);
    }

    return $build;
  }

  /**
   * Configures the Twig environment.
   */
  protected function configureTwigEnvironment() {
    if ($this->configuration['debug'] ?? FALSE) {
      $this->twig->enableDebug();
    }

    if ($this->configuration['auto_reload'] ?? TRUE) {
      $this->twig->enableAutoReload();
    }

    if ($this->configuration['strict_variables'] ?? FALSE) {
      $this->twig->enableStrictVariables();
    }
  }

  /**
   * Determines which template to use for rendering.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param string $view_mode
   *   The view mode.
   * @param array $context
   *   The render context.
   *
   * @return array
   *   Template information with keys:
   *   - type: 'sdc', 'custom', or 'default'
   *   - theme_hook: The theme hook to use
   *   - template_file: The template file path (if custom)
   *   - sdc_id: The SDC component ID (if SDC)
   */
  protected function determineTemplate(ComponentEntityInterface $entity, $view_mode, array $context) {
    $bundle = $entity->bundle();
    $component_type = $entity->getComponentType();

    // Check if SDC component exists.
    if ($component_type && $sdc_id = $component_type->get('sdc_id')) {
      if ($this->componentManager->hasDefinition($sdc_id)) {
        return [
          'type' => 'sdc',
          'sdc_id' => $sdc_id,
          'theme_hook' => 'component_entity_sdc',
        ];
      }
    }

    // Check for custom template in the theme.
    $theme_path = $this->themeManager->getActiveTheme()->getPath();
    $template_suggestions = [
      "component--{$bundle}--{$view_mode}",
      "component--{$bundle}",
      "component--{$view_mode}",
      "component",
    ];

    foreach ($template_suggestions as $suggestion) {
      $template_file = "{$theme_path}/templates/{$suggestion}.html.twig";
      if (file_exists($template_file)) {
        return [
          'type' => 'custom',
          'theme_hook' => str_replace('-', '_', $suggestion),
          'template_file' => $template_file,
        ];
      }
    }

    // Fall back to default template.
    return [
      'type' => 'default',
      'theme_hook' => 'component_entity',
      'template_file' => 'component-entity.html.twig',
    ];
  }

  /**
   * Prepares variables for the template.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param array $context
   *   The render context.
   *
   * @return array
   *   The variables array.
   */
  protected function prepareVariables(ComponentEntityInterface $entity, array $context) {
    $variables = [
      'entity' => $entity,
      'entity_id' => $entity->id(),
      'entity_uuid' => $entity->uuid(),
      'bundle' => $entity->bundle(),
      'label' => $entity->label(),
      'created' => $entity->getCreatedTime(),
      'changed' => $entity->getChangedTime(),
      'published' => $entity->isPublished(),
    ];

    // Add all field values.
    foreach ($entity->getFields() as $field_name => $field) {
      $variables['fields'][$field_name] = $field->view();

      // Also add raw values for easier access.
      $value = $field->getValue();
      if (!empty($value)) {
        $variables['values'][$field_name] = $this->processFieldValue($field, $value);
      }
    }

    // Process specific field types.
    $this->processEntityReferences($entity, $variables);
    $this->processMediaFields($entity, $variables);
    $this->processLinkFields($entity, $variables);

    // Add context variables.
    if (!empty($context['variables'])) {
      $variables = array_merge($variables, $context['variables']);
    }

    // Add theme-specific variables.
    $variables['theme_hook_suggestions'] = $this->buildThemeHookSuggestions($entity, $context);
    $variables['attributes'] = $this->buildAttributes($entity, $context);

    return $variables;
  }

  /**
   * Renders an SDC component.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param string $sdc_id
   *   The SDC component ID.
   * @param array $variables
   *   The variables array.
   *
   * @return array
   *   The render array.
   */
  protected function renderSDCComponent(ComponentEntityInterface $entity, $sdc_id, array $variables) {
    // Extract props and slots from variables.
    $props = $this->extractPropsFromVariables($variables);
    $slots = $this->extractSlotsFromVariables($variables);

    $build = [
      '#type' => 'component',
      '#component' => $sdc_id,
      '#props' => $props,
      '#slots' => $slots,
      '#context' => [
        'entity' => $entity,
        'variables' => $variables,
      ],
    ];

    // Wrap in a container for styling.
    $build['#prefix'] = sprintf(
      '<div class="component-twig component-twig--%s" data-entity-id="%s">',
      $entity->bundle(),
      $entity->id()
    );
    $build['#suffix'] = '</div>';

    return $build;
  }

  /**
   * Processes entity reference fields.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param array &$variables
   *   The variables array.
   */
  protected function processEntityReferences(ComponentEntityInterface $entity, array &$variables) {
    foreach ($entity->getFields() as $field_name => $field) {
      $field_definition = $field->getFieldDefinition();

      if ($field_definition->getType() === 'entity_reference' ||
          $field_definition->getType() === 'entity_reference_revisions') {
        $referenced_entities = $field->referencedEntities();

        if (!empty($referenced_entities)) {
          $variables['references'][$field_name] = $referenced_entities;

          // Add rendered versions.
          $view_builder = \Drupal::entityTypeManager()
            ->getViewBuilder($field_definition->getSetting('target_type'));

          foreach ($referenced_entities as $delta => $referenced_entity) {
            $variables['rendered_references'][$field_name][$delta] = $view_builder->view($referenced_entity, 'teaser');
          }
        }
      }
    }
  }

  /**
   * Processes media fields.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param array &$variables
   *   The variables array.
   */
  protected function processMediaFields(ComponentEntityInterface $entity, array &$variables) {
    foreach ($entity->getFields() as $field_name => $field) {
      $field_definition = $field->getFieldDefinition();

      if ($field_definition->getType() === 'entity_reference' &&
          $field_definition->getSetting('target_type') === 'media') {
        $media_entities = $field->referencedEntities();

        foreach ($media_entities as $delta => $media) {
          // Get the media URL.
          if ($media->hasField('field_media_image')) {
            $image_field = $media->get('field_media_image');
            if (!$image_field->isEmpty()) {
              $file = $image_field->entity;
              $variables['media_urls'][$field_name][$delta] = $file->createFileUrl();
            }
          }
        }
      }
    }
  }

  /**
   * Processes link fields.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param array &$variables
   *   The variables array.
   */
  protected function processLinkFields(ComponentEntityInterface $entity, array &$variables) {
    foreach ($entity->getFields() as $field_name => $field) {
      if ($field->getFieldDefinition()->getType() === 'link') {
        $links = $field->getValue();

        foreach ($links as $delta => $link) {
          $variables['links'][$field_name][$delta] = [
            'url' => $link['uri'],
            'title' => $link['title'] ?? '',
            'options' => $link['options'] ?? [],
          ];
        }
      }
    }
  }

  /**
   * Builds theme hook suggestions.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param array $context
   *   The render context.
   *
   * @return array
   *   Array of theme hook suggestions.
   */
  protected function buildThemeHookSuggestions(ComponentEntityInterface $entity, array $context) {
    $suggestions = [];
    $bundle = $entity->bundle();
    $view_mode = $context['view_mode'] ?? 'default';

    $suggestions[] = 'component_entity';
    $suggestions[] = 'component_entity__' . $bundle;
    $suggestions[] = 'component_entity__' . $view_mode;
    $suggestions[] = 'component_entity__' . $bundle . '__' . $view_mode;
    $suggestions[] = 'component_entity__' . $bundle . '__' . $entity->id();

    return $suggestions;
  }

  /**
   * Builds attributes for the component wrapper.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param array $context
   *   The render context.
   *
   * @return array
   *   The attributes array.
   */
  protected function buildAttributes(ComponentEntityInterface $entity, array $context) {
    $attributes = [
      'class' => [
        'component-entity',
        'component-entity--' . $entity->bundle(),
        'component-entity--' . ($context['view_mode'] ?? 'default'),
      ],
      'data-entity-type' => 'component',
      'data-entity-bundle' => $entity->bundle(),
      'data-entity-id' => $entity->id(),
      'data-entity-uuid' => $entity->uuid(),
    ];

    // Add custom attributes from context.
    if (!empty($context['attributes'])) {
      $attributes = array_merge_recursive($attributes, $context['attributes']);
    }

    return $attributes;
  }

  /**
   * Extracts props from variables for SDC.
   *
   * @param array $variables
   *   The variables array.
   *
   * @return array
   *   The props array.
   */
  protected function extractPropsFromVariables(array $variables) {
    $props = [];

    // Extract specific values as props.
    if (!empty($variables['values'])) {
      foreach ($variables['values'] as $field_name => $value) {
        // Skip slot fields.
        if (strpos($field_name, 'field_slot_') !== 0) {
          $prop_name = str_replace('field_', '', $field_name);
          $props[$prop_name] = $value;
        }
      }
    }

    return $props;
  }

  /**
   * Extracts slots from variables for SDC.
   *
   * @param array $variables
   *   The variables array.
   *
   * @return array
   *   The slots array.
   */
  protected function extractSlotsFromVariables(array $variables) {
    $slots = [];

    // Extract slot fields.
    if (!empty($variables['fields'])) {
      foreach ($variables['fields'] as $field_name => $render_array) {
        if (strpos($field_name, 'field_slot_') === 0) {
          $slot_name = str_replace('field_slot_', '', $field_name);
          $slots[$slot_name] = $render_array;
        }
      }
    }

    return $slots;
  }

  /**
   * Processes field value based on field type.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field object.
   * @param array $value
   *   The field value.
   *
   * @return mixed
   *   The processed value.
   */
  protected function processFieldValue($field, array $value) {
    // Handle single vs multiple values.
    if (count($value) === 1) {
      $item = reset($value);

      // Extract the appropriate value.
      if (isset($item['value'])) {
        return $item['value'];
      }
      elseif (isset($item['target_id'])) {
        return $item['target_id'];
      }
      elseif (isset($item['uri'])) {
        return $item['uri'];
      }

      return $item;
    }

    // For multi-value fields, process each value.
    return array_map(function ($item) {
      if (isset($item['value'])) {
        return $item['value'];
      }
      elseif (isset($item['target_id'])) {
        return $item['target_id'];
      }
      elseif (isset($item['uri'])) {
        return $item['uri'];
      }
      return $item;
    }, $value);
  }

  /**
   * Adds debug information to the build.
   *
   * @param array $build
   *   The render array.
   * @param \Drupal\component_entity\Entity\ComponentEntityInterface $entity
   *   The component entity.
   * @param array $template
   *   The template information.
   *
   * @return array
   *   The build with debug info.
   */
  protected function addDebugInfo(array $build, ComponentEntityInterface $entity, array $template) {
    $build['#prefix'] = '<!-- COMPONENT DEBUG START -->' . PHP_EOL;
    $build['#prefix'] .= '<!-- Entity: ' . $entity->getEntityTypeId() . '/' . $entity->bundle() . '/' . $entity->id() . ' -->' . PHP_EOL;
    $build['#prefix'] .= '<!-- Template: ' . ($template['template_file'] ?? 'none') . ' -->' . PHP_EOL;
    $build['#prefix'] .= '<!-- Template Type: ' . $template['type'] . ' -->' . PHP_EOL;

    if ($template['type'] === 'sdc') {
      $build['#prefix'] .= '<!-- SDC ID: ' . $template['sdc_id'] . ' -->' . PHP_EOL;
    }

    $build['#suffix'] = PHP_EOL . '<!-- COMPONENT DEBUG END -->';

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsSSR() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsHydration() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsProgressive() {
    return TRUE;
  }

}
