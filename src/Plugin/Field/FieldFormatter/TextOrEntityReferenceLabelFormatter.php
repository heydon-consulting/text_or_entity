<?php

namespace Drupal\text_or_entity\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;
use Drupal\Core\TypedData\TranslatableInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'text_or_entity_reference_label' formatter.
 *
 * @FieldFormatter(
 *   id = "text_or_entity_reference_label",
 *   label = @Translation("Text or entity label"),
 *   description = @Translation("Display the text or the label of the referenced entity."),
 *   field_types = {
 *     "text_or_entity_reference"
 *   }
 * )
 */
class TextOrEntityReferenceLabelFormatter extends EntityReferenceLabelFormatter {

  /**
   * The entity type manager.
   *
   * @var \ Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a TextOrEntityReferenceLabelFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $output_as_link = $this->getSetting('link');

    foreach ($items as $delta => $item) {
      if ($target_id = $item->target_id) {
        $entity = $this->entityTypeManager->getStorage($this->fieldDefinition->getSetting('target_type'))->load($target_id);
        // Set the entity in the correct language for display.
        if ($entity instanceof TranslatableInterface) {
          $entity = \Drupal::service('entity.repository')->getTranslationFromContext($entity, $langcode);
        }

        $access = $this->checkAccess($entity);
        // Add the access result's cacheability, ::view() needs it.
        $item->_accessCacheability = CacheableMetadata::createFromObject($access);
        if ($access->isAllowed()) {
          // Add the referring item, in case the formatter needs it.
          $entity->_referringItem = $items[$delta];
          $label = $entity->label();
          // If the link is to be displayed and the entity has a uri, display a
          // link.
          if ($output_as_link) {
            try {
              $uri = $entity->toUrl();
            }
            catch (UndefinedLinkTemplateException $e) {
              // This exception is thrown by \Drupal\Core\Entity\Entity::urlInfo()
              // and it means that the entity type doesn't have a link template nor
              // a valid "uri_callback", so don't bother trying to output a link for
              // the rest of the referenced entities.
              $output_as_link = FALSE;
            }
          }

          if ($output_as_link && isset($uri)) {
            $elements[$delta] = [
              '#type' => 'link',
              '#title' => $label,
              '#url' => $uri,
              '#options' => $uri->getOptions(),
            ];

            if (!empty($items[$delta]->_attributes)) {
              $elements[$delta]['#options'] += ['attributes' => []];
              $elements[$delta]['#options']['attributes'] += $items[$delta]->_attributes;
              // Unset field item attributes since they have been included in the
              // formatter output and shouldn't be rendered in the field template.
              unset($items[$delta]->_attributes);
            }
          }
          else {
            $elements[$delta] = ['#plain_text' => $label];
          }
          $elements[$delta]['#cache']['tags'] = $entity->getCacheTags();
        }
      }
      elseif ($text = $item->text) {
        $elements[$delta] = ['#plain_text' => $text];
      }
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareView(array $entities_items) {}

}
