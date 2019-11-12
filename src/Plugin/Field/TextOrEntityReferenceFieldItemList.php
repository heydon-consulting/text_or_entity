<?php

namespace Drupal\text_or_entity\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\text_or_entity\Element\TextOrEntityAutocomplete;

/**
 * Defines a item list class for text_or_entity_reference fields.
 */
class TextOrEntityReferenceFieldItemList extends EntityReferenceFieldItemList {

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
