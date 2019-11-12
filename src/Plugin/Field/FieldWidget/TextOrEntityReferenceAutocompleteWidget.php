<?php

namespace Drupal\text_or_entity\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'text_or_entity_reference_autocomplete' widget.
 *
 * @FieldWidget(
 *   id = "text_or_entity_reference_autocomplete",
 *   label = @Translation("Text or entity autocomplete"),
 *   description = @Translation("A text or entity autocomplete field."),
 *   field_types = {
 *     "text_or_entity_reference"
 *   }
 * )
 */
class TextOrEntityReferenceAutocompleteWidget extends EntityReferenceAutocompleteWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $field_values = $items->fieldItemValues();

    // Append the match operation to the selection settings.
    $selection_settings = $this->getFieldSetting('handler_settings') + [
      'match_operator' => $this->getSetting('match_operator'),
      'match_limit' => $this->getSetting('match_limit'),
    ];

    $element += [
      '#type' => 'text_or_entity_autocomplete',
      '#target_type' => $this->getFieldSetting('target_type'),
      '#selection_handler' => $this->getFieldSetting('handler'),
      '#selection_settings' => $selection_settings,
      // Entity reference field items are handling validation themselves via
      // the 'ValidReference' constraint.
      '#validate_reference' => FALSE,
      '#maxlength' => $this->getSetting('max_length'),
      '#default_value' => isset($field_values[$delta]) ? $field_values[$delta] : NULL,
      '#size' => $this->getSetting('size'),
      '#placeholder' => $this->getSetting('placeholder'),
    ];

    return ['target_id' => $element];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $key => $value) {
      if (is_array($value['target_id'])) {
        unset($values[$key]['target_id']);
        $values[$key] += $value['target_id'];
      }
      if (!empty($value['target_id'])) {
        $entity = \Drupal::entityTypeManager()->getStorage($this->getFieldSetting('target_type'))->load($value['target_id']);
        if (empty($entity) || !($entity instanceof EntityInterface)) {
          $value['text'] = $value['target_id'];
          unset($value['target_id']);
        }
      }
    }

    return $values;
  }

}
