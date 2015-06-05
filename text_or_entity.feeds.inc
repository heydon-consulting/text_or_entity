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
      $targets[$base_type . ':' . $base_bundle . ':' . $field_name . ':target_id'] = array(
        'name' => t('@name: Target entity ID', array('@name' => $instance['label'])),
        'callback' => 'text_or_entity_feeds_set_target_id',
        'description' => t('The @label of the entity.', array('@label' => $instance['label'])),
        'real_target' => $field_name,
      );
    }
  }
}

function text_or_entity_feeds_set_value($source, $entity, $target, $values, $mapping) {
  drupal_set_message('Value');
  list($base_type, $base_bundle, $field_name, $column) = explode(':', $target . ':value');
  $language = $entity->language;
  $field_data = isset($entity->$target) ? $entity->$target : array($language => array());
  $field = field_info_field($field_name);
  $instance = field_info_instance($base_type, $field_name, $base_bundle);
  $target_type = $field['settings']['entity_selection']['target_type'];
  $target_bundles = $field['settings']['entity_selection']['target_bundles'];
  $target_info = field_info_field($field_name);

  // Iterate over all values.
  $delta = 0;
  if (!is_array($values)) {
    $values = array($values);
  }
  foreach ($values as $value) {
    if (is_object($value) && $value instanceof FeedsElement) {
      $value = $value->getValue();
    }
    if (is_scalar($value) && strlen($value)) {
      // Set text value.
      $field_data[$language][$delta]['value'] = (string) $value;
      // Set base_type.
      $field_data[$language][$delta]['base_type'] = $base_type;
      // Set target_id if matching entity exists.
      $target_entity = text_or_entity_query_entity_label($field, $instance, $value, TRUE);
      $target_id = count($target_entity) ? reset($target_entity)['target_id'] : NULL;
      $field_data[$language][$delta]['target_id'] = $target_id;
    }
    if ($target_info['cardinality'] == 1) {
      break;
    }
    $delta++;
  }

  $entity->{$field_name} = $field_data;
}

function text_or_entity_feeds_set_target_id($source, $entity, $target, $values, $mapping) {
  drupal_set_message('Target ID');
  list($base_type, $base_bundle, $field_name, $column) = explode(':', $target . ':value');
  $language = $entity->language;
  $field_data = isset($entity->$target) ? $entity->$target : array($language => array());
  $field = field_info_field($field_name);
  $instance = field_info_instance($base_type, $field_name, $base_bundle);
  $target_type = $field['settings']['entity_selection']['target_type'];
  $target_bundles = $field['settings']['entity_selection']['target_bundles'];
  $target_info = field_info_field($field_name);

  // Iterate over all values.
  $delta = 0;
  if (!is_array($values)) {
    $values = array($values);
  }
  foreach ($values as $target_id) {
    if (is_object($target_id) && $target_id instanceof FeedsElement) {
      $target_id = $target_id->getValue();
    }
    if (is_numeric($target_id) && strlen($target_id)) {
      // Validate target_id entity type and bundle with field settings.
      $target_entity = entity_load($target_type, array($target_id));
      $target_entity = count($target_entity) ? reset($target_entity) : NULL;
      if (empty($target_entity)) {
        // Make sure we get a second chance to import this target_id.
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
    if ($target_info['cardinality'] == 1) {
      break;
    }
    $delta++;
  }

  $entity->{$field_name} = $field_data;
}
