<?php

/**
 * @file
 * Feeds integration for Text or Entity fields.
 */

/**
 * Implements hook_feeds_processor_targets_alter().
 */
function text_or_entity_feeds_processor_targets_alter(&$targets, $base_type, $base_bundle) {
  foreach (field_info_instances($base_type, $base_bundle) as $field_name => $instance) {
    $field = field_info_field($field_name);
    if ($field['type'] == 'text_or_entity') {
      $targets[$base_type . ':' . $base_bundle . ':' . $field_name . ':value'] = array(
        'name' => t('@name: Text value', array('@name' => $instance['label'])),
        'callback' => 'text_or_entity_feeds_set_value',
        'description' => t('The @label of the entity.', array('@label' => $instance['label'])),
        'real_target' => $field_name,
      );
      $targets[$base_type . ':' . $field_name . ':target_id'] = array(
        'name' => t('@name: Target entity ID', array('@name' => $instance['label'])),
        'callback' => 'text_or_entity_feeds_set_target_id',
        'description' => t('The @label of the entity.', array('@label' => $instance['label'])),
        'real_target' => $field_name,
      );
    }
  }
}

/**
 * Feeds target callback for Text or Entity text value.
 */
function text_or_entity_feeds_set_value($source, $entity, $target, $values, $mapping) {
  list($base_type, $base_bundle, $field_name,) = explode(':', $target . ':value');
  $language = isset($mapping['language']) ? $mapping['language'] : LANGUAGE_NONE;
  $field_data = isset($entity->$target) ? $entity->$target : array($language => array());
  $field = field_info_field($field_name);
  $instance = field_info_instance($base_type, $field_name, $base_bundle);

  // Iterate over all values.
  if (!is_array($values)) {
    $values = array($values);
  }
  $delta = 0;
  foreach ($values as $value) {
    if (is_object($value) && $value instanceof FeedsElement) {
      $value = $value->getValue();
    }
    if (is_scalar($value) && strlen($value)) {
      // Set text value / entity label.
      $field_data[$language][$delta]['value'] = (string) $value;
      // Set target_id if entity with matching label exists.
      $target_entity = text_or_entity_query_entity_label($field, $instance, $value, TRUE);
      $target_entity = count($target_entity) ? reset($target_entity) : NULL;
      $target_id = isset($target_entity['target_id']) ? $target_entity['target_id'] : NULL;
      $field_data[$language][$delta]['target_id'] = $target_id;
      // Set base_type.
      $field_data[$language][$delta]['base_type'] = $base_type;
    }
    if ($field['cardinality'] == 1) {
      break;
    }
    $delta++;
  }

  $entity->{$field_name} = $field_data;
}

/**
 * Feeds target callback for Text or Entity target_id.
 */
function text_or_entity_feeds_set_target_id($source, $entity, $target, $values, $mapping) {
  list($base_type, $field_name,) = explode(':', $target . ':value');
  $language = isset($mapping['language']) ? $mapping['language'] : LANGUAGE_NONE;
  $field_data = isset($entity->$target) ? $entity->$target : array($language => array());
  $field = field_info_field($field_name);
  $target_type = $field['settings']['entity_selection']['target_type'];
  $target_bundles = $field['settings']['entity_selection']['target_bundles'];

  // Iterate over all values.
  if (!is_array($values)) {
    $values = array($values);
  }
  $delta = 0;
  foreach ($values as $target_id) {
    if (is_object($target_id) && $target_id instanceof FeedsElement) {
      $target_id = $target_id->getValue();
    }
    if (is_numeric($target_id) && strlen($target_id)) {
      // Validate target_id entity type and bundle with field settings.
      $target_entity = entity_load($target_type, array($target_id));
      $target_entity = count($target_entity) ? reset($target_entity) : NULL;
      if (empty($target_entity)) {
        // Invalid target_id for configured target type.
        unset($entity->feeds_item->hash);
        continue;
      }
      $wrapper = entity_metadata_wrapper($target_type, $target_id);
      $target_bundle = $wrapper->getBundle();
      if (!empty($target_bundles) && !in_array($target_bundle, $target_bundles)) {
        // Invalid target_id for configured target bundles.
        unset($entity->feeds_item->hash);
        continue;
      }
      // Set target_id.
      $field_data[$language][$delta]['target_id'] = $target_id;
      // Set base_type.
      $field_data[$language][$delta]['base_type'] = $base_type;
      // Update text value from matching entity label.
      $target_label = entity_label($target_type, $target_entity);
      $field_data[$language][$delta]['value'] = $target_label;
    }
    if ($field['cardinality'] == 1) {
      break;
    }
    $delta++;
  }

  $entity->{$field_name} = $field_data;
}
