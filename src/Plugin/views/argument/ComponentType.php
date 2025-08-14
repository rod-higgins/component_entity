<?php

namespace Drupal\component_entity\Plugin\views\argument;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Plugin\views\argument\StringArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Argument handler for component types.
 *
 * @ViewsArgument("component_type")
 */
class ComponentType extends StringArgument implements ContainerFactoryPluginInterface {

  /**
   * The component type entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $componentTypeStorage;

  /**
   * Constructs a ComponentType argument handler.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $component_type_storage
   *   The component type entity storage.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityStorageInterface $component_type_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->componentTypeStorage = $component_type_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('component_type')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function summaryName($data) {
    if ($this->argument) {
      $component_type = $this->componentTypeStorage->load($this->argument);
      if ($component_type) {
        return $component_type->label();
      }
    }
    return parent::summaryName($data);
  }

  /**
   * {@inheritdoc}
   */
  public function title() {
    if ($this->argument) {
      $component_type = $this->componentTypeStorage->load($this->argument);
      if ($component_type) {
        return $component_type->label();
      }
    }
    return parent::title();
  }

  /**
   * {@inheritdoc}
   */
  public function summaryArgument($data) {
    $value = $data->{$this->name_alias};
    if ($value) {
      $component_type = $this->componentTypeStorage->load($value);
      if ($component_type) {
        return $component_type->label();
      }
    }
    return parent::summaryArgument($data);
  }

}
