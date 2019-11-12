<?php

namespace Drupal\text_or_entity\Plugin\Field\FieldType;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\text_or_entity\Plugin\Validation\Constraint\TextOrValidReferenceConstraint;

/**
 * Defines the 'entity_reference' entity field type.
 *
 * Supported settings (below the definition's 'settings' key) are:
 * - target_type: The entity type to reference. Required.
 *
 * @FieldType(
 *   id = "text_or_entity_reference",
 *   label = @Translation("Text or entity reference"),
 *   description = @Translation("A field containing text or an entity reference."),
 *   default_widget = "text_or_entity_reference_autocomplete",
 *   default_formatter = "text_or_entity_reference_label",
 *   list_class = "\Drupal\text_or_entity\Plugin\Field\TextOrEntityReferenceFieldItemList",
 *   category = @Translation("Reference"),
 * )
 */
class TextOrEntityReferenceItem extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'max_length' => 255,
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [
      'text' => DataDefinition::create('string')
        ->setLabel(t('Text')),
      // This property is just for DX convenience, it's never used inside this
      // module.
      'value' => DataDefinition::create('string')
        ->setLabel('Value')
        ->setDescription(new TranslatableMarkup('Text or target ID'))
        ->setComputed(TRUE)
        ->setReadOnly(TRUE)
        ->setClass(\Drupal\text_or_entity\TextOrEntityReferenceValue::class),
      ] + parent::propertyDefinitions($field_definition);
    $properties['target_id']->setRequired(FALSE);
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'value';
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);
    $schema['columns']['text'] = [
      'type' => 'varchar',
      'length' => $field_definition->getSetting('max_length'),
    ];
    $schema['indexes']['text'] = ['text'];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    return [new TextOrValidReferenceConstraint()];
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    // Update the values and return them.
    foreach ($this->properties as $name => $property) {
      $definition = $property->getDataDefinition();
      if (!$definition->isComputed()) {
        $value = $property->getValue();
        // Only write NULL values if the whole map is not NULL.
        if (isset($this->values) || isset($value)) {
          $this->values[$name] = $value;
        }
      }
    }
    return $this->values;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    if (!empty($values['text']) && !empty($values['target_id'])) {
      throw new \InvalidArgumentException('The target_id and text properties should not be both set.');
    }
    if (isset($values) && !is_array($values)) {
      throw new \InvalidArgumentException("Invalid values given. Values must be represented as an associative array.");
    }
    $this->values = $values;

    // Update any existing property objects.
    foreach ($this->properties as $name => $property) {
      $value = isset($values[$name]) ? $values[$name] : NULL;
      $property->setValue($value, FALSE);
      // Remove the value from $this->values to ensure it does not contain any
      // value for computed properties.
      unset($this->values[$name]);
    }
    // Notify the parent of any changes.
    if ($notify && isset($this->parent)) {
      $this->parent->onChange($this->name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($property_name, $notify = TRUE) {
    // Make sure that the target ID and the target property stay in sync.
    if ($property_name == 'entity') {
      $this->writePropertyValue('text', '');
    }
    elseif ($property_name == 'target_id') {
      $this->writePropertyValue('text', '');
    }
    elseif ($property_name === 'text') {
      $this->writePropertyValue('entity', NULL);
      $this->writePropertyValue('target_id', '');
    }
    parent::onChange($property_name, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // Avoid loading the entity by first checking the 'target_id'.
    if ($this->target_id !== NULL) {
      return FALSE;
    }
    if ($this->entity && $this->entity instanceof EntityInterface) {
      return FALSE;
    }
    if ($this->text !== NULL) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    if (!$this->isEmpty() && $this->target_id === NULL && empty($this->text)) {
      $this->target_id = $this->entity->id();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = parent::storageSettingsForm($form, $form_state, $has_data);
    $element['max_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum length of test.'),
      '#min' => 1,
      '#size' => 3,
      '#default_value' => $this->getSetting('max_length'),
      '#required' => TRUE,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::fieldSettingsForm($form, $form_state);
    unset($form['handler']['handler_settings']['auto_create']);
    unset($form['handler']['handler_settings']['auto_create_bundle']);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function getPreconfiguredOptions() {
    return [];
  }

}
