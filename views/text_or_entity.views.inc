<?php

/**
 * @file
 * Views integration for Text or Entity fields.
 */

/**
 * Implements hook_field_views_data().
 */
function text_or_entity_field_views_data($field) {
  $data = field_views_field_default_views_data($field);
  $entity_info = entity_get_info($field['settings']['entity_selection']['target_type']);
  foreach ($data as $table_name => $table_data) {
    $field_name = $field['field_name'] . '_target_id';
    $parameters = array('@entity' => $entity_info['label'], '!field_name' => $field['field_name']);
    $data[$table_name][$field_name]['relationship'] = array(
      'handler' => 'views_handler_relationship',
      'base' => $entity_info['base table'],
      'base field' => $entity_info['entity keys']['id'],
      'label' => t('@entity entity referenced from !field_name', $parameters),
      'group' => t('Text or Entity'),
      'title' => t('Referenced Entity'),
      'help' => t('A bridge to the @entity entity that is referenced via !field_name', $parameters),
    );
  }

  return $data;
}

