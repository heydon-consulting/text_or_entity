<?php

namespace Drupal\text_or_entity\Element;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a text or entity autocomplete form element.
 *
 * The #default_value accepted by this element is either an entity object or an
 * array of entity objects or a string.
 *
 * @FormElement("text_or_entity_autocomplete")
 */
class TextOrEntityAutocomplete extends EntityAutocomplete {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo();
    $info['#autocreate'] = FALSE;
    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    // Process the #default_value property.
    if ($input === FALSE && isset($element['#default_value']) && $element['#process_default_value']) {
      if (is_array($element['#default_value'])) {
        throw new \InvalidArgumentException('The #default_value property is an array but the form element does not allow multiple values.');
      }

      if ($element['#default_value']) {
        if (!($element['#default_value'] instanceof EntityInterface) && !is_string($element['#default_value'])) {
          throw new \InvalidArgumentException('The #default_value property has to be an entity object or a string.');
        }

        // Extract the labels from the passed-in entity objects, taking access
        // checks into account.
        if ($element['#default_value'] instanceof EntityInterface) {
          return static::getEntityLabel($element['#default_value']);
        }
        return $element['#default_value'];
      }
    }

    // Potentially the #value is set directly, so it contains the 'target_id'
    // array structure instead of a string.
    if ($input !== FALSE && is_array($input)) {
      $entity = \Drupal::entityTypeManager()->getStorage($element['#target_type'])->loadMultiple($input['target_id']);
      if ($entity) {
        return static::getEntityLabel($entity);
      }
      return $input['target_id'];
    }
  }


  /**
   * Form element validation handler for entity_autocomplete elements.
   */
  public static function validateEntityAutocomplete(array &$element, FormStateInterface $form_state, array &$complete_form) {
    if (!empty($element['#value'])) {
      $options = $element['#selection_settings'] + [
        'target_type' => $element['#target_type'],
        'handler' => $element['#selection_handler'],
      ];
      /** @var /Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface $handler */
      $handler = \Drupal::service('plugin.manager.entity_reference_selection')->getInstance($options);
      $input_values = [$element['#value']];

      foreach ($input_values as $input) {
        $entity = \Drupal::entityTypeManager()->getStorage($element['#target_type'])->load($input);
        if ($entity === NULL) {
          $match = static::extractEntityIdFromAutocompleteInput($input);
        }
        else {
          $match = $entity->id();
        }
        if ($match === NULL) {
          // Try to get a match from the input string when the user didn't use
          // the autocomplete but filled in a value manually.
          $match = static::matchEntityByTitle($handler, $input, $element, $form_state, FALSE);
        }

        if ($match !== NULL) {
          $form_state->setValueForElement($element, $match);
        }
        // This is not an entity but just a text.
        else {
          $form_state->setValueForElement($element, NULL);
          $parents = $element['#parents'];
          array_pop($parents);
          $parents[] = 'text';
          $form_state->setValueForElement(['#parents' => $parents], $input);
        }
      }
    }
    else {
      $form_state->setValueForElement($element, NULL);
    }
  }

  /**
   * Converts an entity object into a string of entity label.
   *
   * This method is also responsible for checking the 'view label' access on the
   * passed-in entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity object.
   *
   * @return string
   *   The entity label.
   */
  public static function getEntityLabel(EntityInterface $entity) {
    /** @var \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository */
    $entity_repository = \Drupal::service('entity.repository');

    // Set the entity in the correct language for display.
    $entity = $entity_repository->getTranslationFromContext($entity);

    // Use the special view label, since some entities allow the label to be
    // viewed, even if the entity is not allowed to be viewed.
    $label = ($entity->access('view label')) ? $entity->label() : t('- Restricted access -');

    // Take into account "autocreated" entities.
    if (!$entity->isNew()) {
      $label .= ' (' . $entity->id() . ')';
    }

    return $label;
  }

}
