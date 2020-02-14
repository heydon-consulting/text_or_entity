<?php

namespace Drupal\text_or_entity\Plugin\jsonapi\FieldEnhancer;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerBase;
use Shaper\Util\Context;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create JSON output for text_or_entity fields.
 *
 * @ResourceFieldEnhancer(
 *   id = "text_or_entity",
 *   label = @Translation("Text or Entity Reference Field"),
 *   description = @Translation("Render a text_or_entity field as json")
 * )
 */
class TextOrEntityFieldEnhancer extends ResourceFieldEnhancerBase implements ContainerFactoryPluginInterface {

  /**
   * The serialization json.
   *
   * @var \Drupal\Component\serialization\Json
   */
  protected $encoder;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface;
   */
  protected $entityTypeManager;

  /**
   * Constructs a new JSONFieldEnhancer.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Component\Serialization\Json $encoder
   *   The serialization json.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface;
   *   The entity type manager interface.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, Json $encoder, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->encoder = $encoder;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('serialization.json'), $container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function doUndoTransform($data, Context $context) {
    if (!empty($data['target_id'])) {
      /** @var \Drupal\text_or_entity\Plugin\Field\FieldType\TextOrEntityReferenceItem $field_item */
      $field_item = $context->offsetGet('field_item_object');
      $entity_type = $field_item->getFieldDefinition()->getFieldStorageDefinition()->getSetting('target_type');
      $entity = $this->entityTypeManager->getStorage($entity_type)->load($data['target_id']);
      $bundle = $entity->bundle();
      return [
        'type' => $entity_type . '--' . $bundle,
        'id' => $data['target_id'],
      ];
    }
    else if (!empty($data['text'])) {
      return $data['text'];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function doTransform($data, Context $context) {
    return $this->encoder->encode($data);
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputJsonSchema() {
    return [
      'oneOf' => [
        ['type' => 'object'],
        ['type' => 'string'],
        ['type' => 'null'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $resource_field_info) {
    return [];
  }

}
