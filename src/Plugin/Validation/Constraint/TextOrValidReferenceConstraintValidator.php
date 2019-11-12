<?php

namespace Drupal\text_or_entity\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\text_or_entity\Plugin\Field\TextOrEntityReferenceFieldItemList;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Checks if referenced entities are valid.
 */
class TextOrValidReferenceConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The selection plugin manager.
   *
   * @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface
   */
  protected $selectionManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a ValidReferenceConstraintValidator object.
   *
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $selection_manager
   *   The selection plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(SelectionPluginManagerInterface $selection_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->selectionManager = $selection_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.entity_reference_selection'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if (!isset($value)) {
      return;
    }

    if ($value instanceof TextOrEntityReferenceFieldItemList) {
      $max_length = $value->getFieldDefinition()->getSetting('max_length');
      /** @var \Drupal\text_or_entity\Plugin\Field\FieldType\TextOrEntityReferenceItem $item */
      foreach ($value as $delta => $item) {
        $target_id = $item->get('target_id')->getValue();
        $text = $item->get('text')->getValue();
        if (!empty($target_id)) {
          /** @var \Drupal\text_or_entity\Plugin\Field\TextOrEntityReferenceFieldItemList $item_list */
          $item_list = $this->context->getValue();
          if ($item_list) {
            $target_type = $item_list->getFieldDefinition()->getSetting('target_type');
            if ($target_type) {
              $existing_entity = $this->entityTypeManager->getStorage($target_type)->loadUnchanged($target_id);
              if ($existing_entity) {
                continue;
              }
            }
          }

          if (mb_strlen($target_id) > $max_length) {
            $this->context->buildViolation($constraint->longTextMessage)
              ->atPath((string) $delta)
              ->addViolation();
            return;
          }
        }
        elseif (!empty($text)) {
          if (mb_strlen($text) > $max_length) {
            $this->context->buildViolation($constraint->longTextMessage)
              ->atPath((string) $delta)
              ->addViolation();
            return;
          }
        }
      }
    }
  }

}
