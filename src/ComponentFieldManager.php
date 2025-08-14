<?php

namespace Drupal\component_entity;

/**
 * Manages field operations for component entities.
 */
class ComponentFieldManager {

  /**
   * Maps SDC prop types to Drupal field types.
   */
  public function mapPropToFieldType($prop_schema) {
    $type = $prop_schema->type ?? 'string';

    // Handle special cases first.
    if (isset($prop_schema->enum)) {
      return 'list_string';
    }

    $mapping = [
      'string' => 'string',
      'text' => 'text_long',
      'number' => 'decimal',
      'integer' => 'integer',
      'boolean' => 'boolean',
      'object' => 'map',
      'array' => 'entity_reference',
    ];

    return $mapping[$type] ?? 'string';
  }

  /**
   * Creates fields for a component bundle from SDC props.
   */
  public function createFieldsFromProps($bundle, $props) {
    foreach ($props as $prop_name => $prop_schema) {
      $field_name = 'field_' . $prop_name;
      $this->createField($bundle, $field_name, $prop_schema);
    }
  }

}
