<?php

namespace Drupal\text_or_entity;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\TypedData\TypedData;

/**
 * A computed property for processing text or entity reference.
 */
class TextOrEntityReferenceValue extends TypedData implements CacheableDependencyInterface {

  /**
   * Cached processed text.
   *
   * @var \Drupal\filter\FilterProcessResult|null
   */
  protected $processed = NULL;

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    if ($this->processed !== NULL) {
      return $this->processed;
    }

    $item = $this->getParent();
    $text = $item->text;
    if ($text) {
      $this->processed = $text;
      return $text;
    }
    $this->processed = $item->target_id;
    return $item->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE) {
    $this->processed = $value;
    // Notify the parent of any changes.
    if ($notify && isset($this->parent)) {
      $this->parent->onChange($this->name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $this->getValue();
    return $this->processed->getCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $this->getValue();
    return $this->processed->getCacheContexts();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    $this->getValue();
    return $this->processed->getCacheMaxAge();
  }

}
