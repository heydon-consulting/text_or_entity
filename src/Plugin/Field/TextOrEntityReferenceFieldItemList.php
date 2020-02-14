<?php

namespace Drupal\text_or_entity\Plugin\Field;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\text_or_entity\Element\TextOrEntityAutocomplete;

/**
 * Defines a item list class for text_or_entity_reference fields.
 *
 * We can't extend EntityReferenceFieldItemList because it breaks JSON API.
 *
 * @see \Drupal\jsonapi\Normalizer\ResourceObjectNormalizer::serializeField()
 */
class TextOrEntityReferenceFieldItemList extends FieldItemList {

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraint_manager = $this->getTypedDataManager()->getValidationConstraintManager();
    $constraints = [$constraint_manager->create('TextOrValidReference', [])];
    return $constraints;
  }

  /**
   * Get field item values.
   */
  public function fieldItemValues() {
    if ($this->isEmpty()) {
      return [];
    }

    $values = [];
    foreach ($this->list as $delta => $item) {
      if ($item->target_id !== NULL) {
        $target_type = $this->getFieldDefinition()->getSetting('target_type');
        $entity = \Drupal::entityTypeManager()->getStorage($target_type)->load($item->target_id);
        $values[$delta] = TextOrEntityAutocomplete::getEntityLabel($entity);
      }
      elseif ($item->text !== NULL) {
        $values[$delta] = $item->text;
      }
    }
    return $values;
  }

  /**
   * Perfect copy from EntityReferenceFieldItemList.
   */
  public function referencedEntities() {
    if ($this->isEmpty()) {
      return [];
    }

    // Collect the IDs of existing entities to load, and directly grab the
    // "autocreate" entities that are already populated in $item->entity.
    $target_entities = $ids = [];
    foreach ($this->list as $delta => $item) {
      if ($item->target_id !== NULL) {
        $ids[$delta] = $item->target_id;
      }
      elseif ($item->hasNewEntity()) {
        $target_entities[$delta] = $item->entity;
      }
    }

    // Load and add the existing entities.
    if ($ids) {
      $target_type = $this->getFieldDefinition()->getSetting('target_type');
      $entities = \Drupal::entityTypeManager()->getStorage($target_type)->loadMultiple($ids);
      foreach ($ids as $delta => $target_id) {
        if (isset($entities[$target_id])) {
          $target_entities[$delta] = $entities[$target_id];
        }
      }
      // Ensure the returned array is ordered by deltas.
      ksort($target_entities);
    }

    return $target_entities;
  }

  /**
   * Perfect copy from EntityReferenceFieldItemList.
   */
  public static function processDefaultValue($default_value, FieldableEntityInterface $entity, FieldDefinitionInterface $definition) {
    $default_value = parent::processDefaultValue($default_value, $entity, $definition);

    if ($default_value) {
      // Convert UUIDs to numeric IDs.
      $uuids = [];
      foreach ($default_value as $delta => $properties) {
        if (isset($properties['target_uuid'])) {
          $uuids[$delta] = $properties['target_uuid'];
        }
      }
      if ($uuids) {
        $target_type = $definition->getSetting('target_type');
        $entity_ids = \Drupal::entityQuery($target_type)
        ->condition('uuid', $uuids, 'IN')
        ->execute();
        $entities = \Drupal::entityTypeManager()
        ->getStorage($target_type)
        ->loadMultiple($entity_ids);

        $entity_uuids = [];
        foreach ($entities as $id => $entity) {
          $entity_uuids[$entity->uuid()] = $id;
        }
        foreach ($uuids as $delta => $uuid) {
          if (isset($entity_uuids[$uuid])) {
            $default_value[$delta]['target_id'] = $entity_uuids[$uuid];
            unset($default_value[$delta]['target_uuid']);
          }
          else {
            unset($default_value[$delta]);
          }
        }
      }

      // Ensure we return consecutive deltas, in case we removed unknown UUIDs.
      $default_value = array_values($default_value);
    }
    return $default_value;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultValuesFormSubmit(array $element, array &$form, FormStateInterface $form_state) {
    // Extract the submitted value, and return it as an array.
    if ($widget = $this->defaultValueWidget($form_state)) {
      $widget->extractFormValues($this, $element, $form_state);
      $default_value = $this->getValue();
    }
    else {
      $default_value = [];
    }

    // Convert numeric IDs to UUIDs to ensure config deployability.
    $ids = [];
    foreach ($default_value as $delta => $properties) {
      if ($default_value[$delta]['target_id']) {
        $ids[$delta] = $default_value[$delta]['target_id'];
      }
    }
    $entities = \Drupal::entityTypeManager()
      ->getStorage($this->getSetting('target_type'))
      ->loadMultiple($ids);

    foreach ($default_value as $delta => $properties) {
      if (!empty($ids[$delta])) {
        unset($default_value[$delta]['target_id']);
        $default_value[$delta]['target_uuid'] = $entities[$ids[$delta]]->uuid();
      }
    }
    return $default_value;
  }

}
