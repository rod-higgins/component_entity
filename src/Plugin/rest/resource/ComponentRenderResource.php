<?php

namespace Drupal\component_entity\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\component_entity\Entity\ComponentEntity;
use Drupal\component_entity\Plugin\ComponentRendererManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a REST resource for rendering components.
 *
 * @RestResource(
 *   id = "component_render",
 *   label = @Translation("Component Render"),
 *   uri_paths = {
 *     "canonical" = "/api/component/{component}/render"
 *   }
 * )
 */
class ComponentRenderResource extends ResourceBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The component renderer manager.
   *
   * @var \Drupal\component_entity\Plugin\ComponentRendererManager
   */
  protected $componentRendererManager;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * Constructs a ComponentRenderResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\component_entity\Plugin\ComponentRendererManager $component_renderer_manager
   *   The component renderer manager.
   * @param \Symfony\Component\HttpFoundation\Request $current_request
   *   The current request.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager,
    RendererInterface $renderer,
    ComponentRendererManager $component_renderer_manager,
    Request $current_request,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
    $this->componentRendererManager = $component_renderer_manager;
    $this->currentRequest = $current_request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('component_entity'),
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('plugin.manager.component_renderer'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * Responds to GET requests for rendering a component.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntity $component
   *   The component entity.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the rendered component.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function get(?ComponentEntity $component = NULL) {
    if (!$component) {
      throw new NotFoundHttpException('Component entity not found.');
    }

    // Check view access.
    if (!$component->access('view')) {
      throw new AccessDeniedHttpException();
    }

    // Get rendering parameters from query.
    $view_mode = $this->currentRequest->query->get('view_mode', 'default');
    $render_method = $this->currentRequest->query->get('render_method');
    $include_assets = $this->currentRequest->query->get('include_assets', FALSE);
    $context = $this->getContextFromRequest();

    try {
      // Get the appropriate renderer.
      $renderer_plugin = $this->componentRendererManager->getRenderer($component, $render_method);

      if (!$renderer_plugin) {
        throw new BadRequestHttpException('No suitable renderer found for this component.');
      }

      // Build the render array.
      $build = $renderer_plugin->render($component, [
        'view_mode' => $view_mode,
        'context' => $context,
      ]);

      // Render to HTML.
      $html = $this->renderer->renderRoot($build);

      // Build response data.
      $data = [
        'id' => $component->id(),
        'type' => $component->bundle(),
        'render_method' => $renderer_plugin->getPluginId(),
        'view_mode' => $view_mode,
        'html' => (string) $html,
      ];

      // Include assets if requested.
      if ($include_assets) {
        $data['assets'] = $this->extractAssets($build);
      }

      // Include cache metadata.
      $data['cache'] = [
        'tags' => $build['#cache']['tags'] ?? [],
        'contexts' => $build['#cache']['contexts'] ?? [],
        'max_age' => $build['#cache']['max-age'] ?? -1,
      ];

      $response = new ResourceResponse($data, 200);
      $response->addCacheableDependency($component);

      return $response;
    }
    catch (\Exception $e) {
      throw new BadRequestHttpException('Error rendering component: ' . $e->getMessage());
    }
  }

  /**
   * Responds to POST requests for rendering with custom data.
   *
   * @param \Drupal\component_entity\Entity\ComponentEntity $component
   *   The component entity.
   * @param array $data
   *   The custom data for rendering.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the rendered component.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function post(?ComponentEntity $component = NULL, array $data = []) {
    if (!$component) {
      throw new NotFoundHttpException('Component entity not found.');
    }

    // Check view access.
    if (!$component->access('view')) {
      throw new AccessDeniedHttpException();
    }

    // Extract rendering options from data.
    $view_mode = $data['view_mode'] ?? 'default';
    $render_method = $data['render_method'] ?? NULL;
    $props = $data['props'] ?? [];
    $slots = $data['slots'] ?? [];
    $include_assets = $data['include_assets'] ?? FALSE;

    try {
      // Get the appropriate renderer.
      $renderer_plugin = $this->componentRendererManager->getRenderer($component, $render_method);

      if (!$renderer_plugin) {
        throw new BadRequestHttpException('No suitable renderer found for this component.');
      }

      // Build the render array with custom props and slots.
      $build = $renderer_plugin->render($component, [
        'view_mode' => $view_mode,
        'props' => $props,
        'slots' => $slots,
      ]);

      // Render to HTML.
      $html = $this->renderer->renderRoot($build);

      // Build response data.
      $response_data = [
        'id' => $component->id(),
        'type' => $component->bundle(),
        'render_method' => $renderer_plugin->getPluginId(),
        'view_mode' => $view_mode,
        'html' => (string) $html,
      ];

      // Include assets if requested.
      if ($include_assets) {
        $response_data['assets'] = $this->extractAssets($build);
      }

      $response = new ResourceResponse($response_data, 200);
      $response->addCacheableDependency($component);

      return $response;
    }
    catch (\Exception $e) {
      throw new BadRequestHttpException('Error rendering component: ' . $e->getMessage());
    }
  }

  /**
   * Gets context data from the request.
   *
   * @return array
   *   The context array.
   */
  protected function getContextFromRequest() {
    $context = [];

    // Get all query parameters that start with 'context_'.
    foreach ($this->currentRequest->query->all() as $key => $value) {
      if (strpos($key, 'context_') === 0) {
        $context_key = substr($key, 8);
        $context[$context_key] = $value;
      }
    }

    return $context;
  }

  /**
   * Extracts assets from a render array.
   *
   * @param array $build
   *   The render array.
   *
   * @return array
   *   Array of assets (CSS and JS).
   */
  protected function extractAssets(array $build) {
    $assets = [
      'css' => [],
      'js' => [],
      'libraries' => [],
    ];

    // Extract libraries.
    if (!empty($build['#attached']['library'])) {
      $assets['libraries'] = $build['#attached']['library'];
    }

    // Extract inline CSS.
    if (!empty($build['#attached']['html_head'])) {
      foreach ($build['#attached']['html_head'] as $item) {
        if (isset($item[0]['#tag']) && $item[0]['#tag'] === 'style') {
          $assets['css'][] = [
            'type' => 'inline',
            'content' => $item[0]['#value'] ?? '',
          ];
        }
      }
    }

    // Extract drupalSettings.
    if (!empty($build['#attached']['drupalSettings'])) {
      $assets['settings'] = $build['#attached']['drupalSettings'];
    }

    return $assets;
  }

}
