<?php

namespace Drupal\component_entity\Entity;

use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Component entity.
 *
 * @ContentEntityType(
 *   id = "component",
 *   label = @Translation("Component"),
 *   label_collection = @Translation("Components"),
 *   label_singular = @Translation("component"),
 *   label_plural = @Translation("components"),
 *   label_count = @PluralTranslation(
 *     singular = "@count component",
 *     plural = "@count components",
 *   ),
 *   bundle_label = @Translation("Component type"),
 *   handlers = {
 *     "view_builder" = "Drupal\component_entity\ComponentViewBuilder",
 *     "list_builder" = "Drupal\component_entity\Controller\ComponentListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "translation" = "Drupal\content_translation\ContentTranslationHandler",
 *     "form" = {
 *       "default" = "Drupal\component_entity\Form\ComponentEntityForm",
 *       "add" = "Drupal\component_entity\Form\ComponentEntityForm",
 *       "edit" = "Drupal\component_entity\Form\ComponentEntityForm",
 *       "delete" = "Drupal\component_entity\Form\ComponentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\component_entity\Routing\ComponentRouteProvider",
 *     },
 *     "access" = "Drupal\component_entity\Access\ComponentAccessControlHandler",
 *   },
 *   base_table = "component",
 *   data_table = "component_field_data",
 *   revision_table = "component_revision",
 *   revision_data_table = "component_field_revision",
 *   translatable = TRUE,
 *   revisionable = TRUE,
 *   admin_permission = "administer component entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "bundle" = "type",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "uid",
 *     "langcode" = "langcode",
 *     "published" = "status",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_uid",
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log",
 *   },
 *   bundle_entity_type = "component_type",
 *   field_ui_base_route = "entity.component_type.edit_form",
 *   common_reference_target = TRUE,
 *   permission_granularity = "bundle",
 *   links = {
 *     "canonical" = "/component/{component}",
 *     "add-page" = "/component/add",
 *     "add-form" = "/component/add/{component_type}",
 *     "edit-form" = "/component/{component}/edit",
 *     "delete-form" = "/component/{component}/delete",
 *     "version-history" = "/component/{component}/revisions",
 *     "revision" = "/component/{component}/revisions/{component_revision}/view",
 *     "revision_revert" = "/component/{component}/revisions/{component_revision}/revert",
 *     "revision_delete" = "/component/{component}/revisions/{component_revision}/delete",
 *     "translation_revert" = "/component/{component}/revisions/{component_revision}/revert/{langcode}",
 *     "collection" = "/admin/content/components",
 *   },
 *   constraints = {
 *     "ComponentConstraint" = {}
 *   }
 * )
 */
class ComponentEntity extends RevisionableContentEntityBase implements ComponentEntityInterface {

  use EntityChangedTrait;
  use EntityPublishedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    parent::preCreate($storage, $values);
    $values += [
      'uid' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);

    if ($rel === 'revision_revert' && $this instanceof RevisionableInterface) {
      $uri_route_parameters[$this->getEntityTypeId() . '_revision'] = $this->getRevisionId();
    }
    elseif ($rel === 'revision_delete' && $this instanceof RevisionableInterface) {
      $uri_route_parameters[$this->getEntityTypeId() . '_revision'] = $this->getRevisionId();
    }

    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Set default render method if not set.
    if ($this->get('render_method')->isEmpty()) {
      $component_type = $this->getComponentType();
      if ($component_type) {
        $rendering_config = $component_type->getRenderingConfiguration();
        $this->set('render_method', $rendering_config['default_method'] ?? 'twig');
      }
    }

    // Validate React configuration.
    if ($this->getRenderMethod() === 'react') {
      $this->validateReactConfig();
    }

    // Update revision metadata.
    if (!$this->isNew()) {
      $this->setRevisionCreationTime(\Drupal::time()->getRequestTime());
      $this->setRevisionUserId(\Drupal::currentUser()->id());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Invalidate component cache.
    if ($update) {
      \Drupal::service('component_entity.cache_manager')->invalidateComponentCache($this);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Add the published field.
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    // Add the owner field.
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    // Name field.
    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Name'))
      ->setDescription(new TranslatableMarkup('The name of the component entity.'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -10,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    // Render method field.
    $fields['render_method'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Render method'))
      ->setDescription(new TranslatableMarkup('How this component should be rendered.'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'allowed_values' => [
          'twig' => 'Twig (Server-side)',
          'react' => 'React (Client-side)',
        ],
      ])
      ->setDefaultValue('twig')
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE);

    // React configuration field.
    $fields['react_config'] = BaseFieldDefinition::create('map')
      ->setLabel(new TranslatableMarkup('React configuration'))
      ->setDescription(new TranslatableMarkup('React-specific rendering configuration.'))
      ->setRevisionable(TRUE)
      ->setDefaultValue([
        'hydration' => 'full',
        'progressive' => FALSE,
        'lazy' => FALSE,
        'ssr' => FALSE,
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    // Created field.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'))
      ->setDescription(new TranslatableMarkup('The time that the entity was created.'))
      ->setRevisionable(TRUE);

    // Changed field.
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Changed'))
      ->setDescription(new TranslatableMarkup('The time that the entity was last edited.'))
      ->setRevisionable(TRUE);

    // Status field modifications.
    $fields['status']
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 120,
      ])
      ->setDisplayConfigurable('form', TRUE);

    // Owner field modifications.
    $fields['uid']
      ->setLabel(new TranslatableMarkup('Authored by'))
      ->setDescription(new TranslatableMarkup('The user that created this component.'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Revision log message field.
    if ($entity_type->hasKey('revision')) {
      $fields['revision_log_message'] = BaseFieldDefinition::create('string_long')
        ->setLabel(new TranslatableMarkup('Revision log message'))
        ->setDescription(new TranslatableMarkup('Briefly describe the changes you have made.'))
        ->setRevisionable(TRUE)
        ->setDefaultValue('')
        ->setDisplayOptions('form', [
          'type' => 'string_textarea',
          'weight' => 25,
          'settings' => [
            'rows' => 4,
          ],
        ]);
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderMethod() {
    return $this->get('render_method')->value ?? 'twig';
  }

  /**
   * {@inheritdoc}
   */
  public function setRenderMethod($method) {
    if (!in_array($method, ['twig', 'react'])) {
      throw new \InvalidArgumentException('Invalid render method. Must be "twig" or "react".');
    }
    $this->set('render_method', $method);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getReactConfig() {
    $config = $this->get('react_config')->getValue();
    return $config[0] ?? [
      'hydration' => 'full',
      'progressive' => FALSE,
      'lazy' => FALSE,
      'ssr' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setReactConfig(array $config) {
    $this->set('react_config', $config);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getComponentType() {
    return $this->type->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function hasReactSupport() {
    $component_type = $this->getComponentType();
    if ($component_type) {
      $rendering_config = $component_type->getRenderingConfiguration();
      return !empty($rendering_config['react_enabled']);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasTwigSupport() {
    $component_type = $this->getComponentType();
    if ($component_type) {
      $rendering_config = $component_type->getRenderingConfiguration();
      return !empty($rendering_config['twig_enabled']);
    }
    // Default to TRUE for backward compatibility.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getProps() {
    $props = [];

    foreach ($this->getFields() as $field_name => $field) {
      // Only process fields that start with 'field_' and are not slots.
      if (strpos($field_name, 'field_') === 0 && strpos($field_name, '_slot') === FALSE) {
        $prop_name = str_replace('field_', '', $field_name);

        if (!$field->isEmpty()) {
          $field_type = $field->getFieldDefinition()->getType();

          // Handle different field types appropriately.
          switch ($field_type) {
            case 'boolean':
              $props[$prop_name] = (bool) $field->value;
              break;

            case 'integer':
              $props[$prop_name] = (int) $field->value;
              break;

            case 'decimal':
            case 'float':
              $props[$prop_name] = (float) $field->value;
              break;

            case 'json':
              $props[$prop_name] = json_decode($field->value, TRUE);
              break;

            case 'entity_reference':
            case 'entity_reference_revisions':
              // For entity references, return the target ID(s).
              $values = [];
              foreach ($field as $item) {
                $values[] = $item->target_id;
              }
              $props[$prop_name] = $field->getFieldDefinition()
                ->getFieldStorageDefinition()
                ->isMultiple() ? $values : ($values[0] ?? NULL);
              break;

            default:
              // For other fields, get the value property.
              $values = [];
              foreach ($field as $item) {
                $values[] = $item->value;
              }
              $props[$prop_name] = $field->getFieldDefinition()
                ->getFieldStorageDefinition()
                ->isMultiple() ? $values : ($values[0] ?? NULL);
              break;
          }
        }
      }
    }

    return $props;
  }

  /**
   * {@inheritdoc}
   */
  public function getSlots() {
    $slots = [];

    foreach ($this->getFields() as $field_name => $field) {
      // Only process slot fields.
      if (strpos($field_name, 'field_') === 0 && strpos($field_name, '_slot') !== FALSE) {
        $slot_name = str_replace(['field_', '_slot'], '', $field_name);

        if (!$field->isEmpty()) {
          $slots[$slot_name] = $field;
        }
      }
    }

    return $slots;
  }

  /**
   * Validates React configuration.
   *
   * @throws \InvalidArgumentException
   *   If React configuration is invalid.
   */
  protected function validateReactConfig() {
    $config = $this->getReactConfig();

    // Validate hydration method.
    $valid_hydration = ['full', 'partial', 'none'];
    if (isset($config['hydration']) && !in_array($config['hydration'], $valid_hydration)) {
      throw new \InvalidArgumentException('Invalid hydration method. Must be one of: ' . implode(', ', $valid_hydration));
    }

    // Ensure React is enabled for this component type.
    if (!$this->hasReactSupport()) {
      throw new \InvalidArgumentException('React rendering is not enabled for this component type.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTagsToInvalidate() {
    $tags = parent::getCacheTagsToInvalidate();

    // Add render method specific tag.
    $tags[] = 'component_render:' . $this->getRenderMethod();

    // Add bundle-specific tag.
    $tags[] = 'component_type:' . $this->bundle();

    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = parent::getCacheContexts();

    // Add render method context if component supports both.
    if ($this->hasReactSupport() && $this->hasTwigSupport()) {
      $contexts[] = 'component_render_method';
    }

    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->getName();
  }

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $fields = parent::bundleFieldDefinitions($entity_type, $bundle, $base_field_definitions);

    // Load the component type to check rendering configuration.
    $component_type = \Drupal::entityTypeManager()
      ->getStorage('component_type')
      ->load($bundle);

    if ($component_type) {
      $rendering_config = $component_type->getRenderingConfiguration();

      // If only one render method is available, hide the field.
      if ((!$rendering_config['twig_enabled'] && $rendering_config['react_enabled']) ||
          ($rendering_config['twig_enabled'] && !$rendering_config['react_enabled'])) {
        if (isset($fields['render_method'])) {
          $fields['render_method']->setDisplayConfigurable('form', FALSE);
          $fields['render_method']->setDisplayOptions('form', [
            'region' => 'hidden',
          ]);
        }
      }
    }

    return $fields;
  }

}
