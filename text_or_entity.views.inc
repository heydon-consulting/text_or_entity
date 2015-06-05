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
    $replacements = array(
      '@entity' => $entity_info['label'],
      '!field_name' => $field['field_name'],
    );
    $data[$table_name][$field_name]['relationship'] = array(
      'handler' => 'views_handler_relationship',
      'base' => $entity_info['base table'],
      'base field' => $entity_info['entity keys']['id'],
      'label' => t('@entity referenced via !field_name', $replacements),
      'group' => t('Text or Entity'),
      'title' => t('Referenced @entity', $replacements),
      'help' => t('A bridge to the @entity that is referenced via !field_name', $replacements),
    );
  }

  return $data;
}

/**
 * Implements hook_field_views_data_views_data_alter().
 *
 * Views integration to provide reverse relationships on text_or_entity fields.
 */
function text_or_entity_field_views_data_views_data_alter(&$data, $field) {
  foreach ($field['bundles'] as $entity_type => $bundles) {
    $target_entity_info = entity_get_info($field['settings']['entity_selection']['target_type']);
    if (isset($target_entity_info['base table'])) {
      $entity_info = entity_get_info($entity_type);
      $pseudo_field_name = 'reverse_' . $field['field_name'] . '_' . $entity_type;
      $replacements = array(
        '@entity' => $entity_info['label'],
        '@target_entity' => $target_entity_info['label'],
        '!field_name' => $field['field_name'],
      );
      $data[$target_entity_info['base table']][$pseudo_field_name]['relationship'] = array(
        'handler' => 'views_handler_relationship_entity_reverse',
        'field_name' => $field['field_name'],
        'field table' => _field_sql_storage_tablename($field),
        'field field' => $field['field_name'] . '_target_id',
        'base' => $entity_info['base table'],
        'base field' => $entity_info['entity keys']['id'],
        'label' => t('@entity referencing @target_entity via !field_name', $replacements),
        'group' => t('Text or Entity'),
        'title' => t('Referencing @entity', $replacements),
        'help' => t('A bridge to the @entity that is referencing @target_entity via !field_name', $replacements),
        'join_extra' => array(
          0 => array(
            'field' => 'entity_type',
            'value' => $entity_type,
          ),
          1 => array(
            'field' => 'deleted',
            'value' => 0,
            'numeric' => TRUE,
          ),
        ),
      );
    }
  }
}
