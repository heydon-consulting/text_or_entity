<?php

namespace Drupal\text_or_entity\Plugin\Validation\Constraint;

use Drupal\Core\Entity\Plugin\Validation\Constraint\ValidReferenceConstraint;

/**
 * Text length or valid entity reference constraint.
 *
 * Verifies that referenced entities are valid or if invalid, the text is not
 * too long.
 *
 * @Constraint(
 *   id = "TextOrValidReference",
 *   label = @Translation("Text length or entity reference valid reference", context = "Validation")
 * )
 */
class TextOrValidReferenceConstraint extends ValidReferenceConstraint {

  /**
   * Violation message when the text is too long.
   *
   * @var string
   */
  public $longTextMessage = 'The text is too long.';

}
