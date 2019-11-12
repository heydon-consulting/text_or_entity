<?php

namespace Drupal\Tests\text_or_entity\Kernel;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Drupal\Tests\text_or_entity\Traits\TextOrEntityReferenceTestTrait;
use Drupal\Core\Entity\EntityInterface;

/**
 * Tests the formatters functionality.
 *
 * @group entity_reference
 */
class TextOrEntityReferenceFormatterTest extends EntityKernelTestBase {

  use TextOrEntityReferenceTestTrait;

  /**
   * The entity type used in this test.
   *
   * @var string
   */
  protected $entityType = 'entity_test';

  /**
   * The bundle used in this test.
   *
   * @var string
   */
  protected $bundle = 'entity_test';

  /**
   * The name of the field used in this test.
   *
   * @var string
   */
  protected $fieldName = 'field_test';

  /**
   * The entity to be referenced in this test.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $referencedEntity;

  /**
   * The entity that is not yet saved to its persistent storage to be referenced
   * in this test.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $unsavedReferencedEntity;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['text_or_entity'];


  protected function setUp() {
    parent::setUp();

    // Use Classy theme for testing markup output.
    \Drupal::service('theme_installer')->install(['classy']);
    $this->config('system.theme')->set('default', 'classy')->save();
    // Grant the 'view test entity' permission.
    $this->installConfig(['user']);
    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('view test entity')
      ->save();

    // The label formatter rendering generates links, so build the router.
    $this->container->get('router.builder')->rebuild();

    $this->createEntityReferenceField($this->entityType, $this->bundle, $this->fieldName, 'Field test', $this->entityType, 'default', [], FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    // Create the entity to be referenced.
    $this->referencedEntity = $this->container->get('entity_type.manager')
      ->getStorage($this->entityType)
      ->create(['name' => $this->randomMachineName()]);
    $this->referencedEntity->save();
  }

  /**
   * Tests the label formatter.
   */
  public function testLabelFormatter() {
    $this->installEntitySchema('entity_test_label');
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $this->container->get('renderer');
    $formatter = 'text_or_entity_reference_label';

    // The 'link' settings is TRUE by default.
    $testtext = 'My test text';
    $build = $this->buildRenderArray([$this->referencedEntity, $testtext], $formatter);

    $expected_field_cacheability = [
      'contexts' => [],
      'tags' => [],
      'max-age' => Cache::PERMANENT,
    ];
    $this->assertEqual($build['#cache'], $expected_field_cacheability, 'The field render array contains the entity access cacheability metadata');
    $expected_item_0 = [
      '#type' => 'link',
      '#title' => $this->referencedEntity->label(),
      '#url' => $this->referencedEntity->toUrl(),
      '#options' => $this->referencedEntity->toUrl()->getOptions(),
      '#cache' => [
        'contexts' => [
          'user.permissions',
        ],
        'tags' => $this->referencedEntity->getCacheTags(),
      ],
    ];
    $expected_item_1 = [
      '#plain_text' => $testtext,
    ];
    $this->assertEqual($renderer->renderRoot($build[0]), $renderer->renderRoot($expected_item_0), sprintf('The markup returned by the %s formatter is correct for an item with a saved entity.', $formatter));
    $this->assertEqual($renderer->renderRoot($build[1]), $renderer->renderRoot($expected_item_1), sprintf('The markup returned by the %s formatter is correct for an item with a saved entity.', $formatter));
    $this->assertEqual(CacheableMetadata::createFromRenderArray($build[0]), CacheableMetadata::createFromRenderArray($expected_item_0));

    // Test with the 'link' setting set to FALSE.
    $build = $this->buildRenderArray([$this->referencedEntity, $testtext], $formatter, ['link' => FALSE]);
    $this->assertEqual($build[0]['#plain_text'], $this->referencedEntity->label(), sprintf('The markup returned by the %s formatter is correct for an item with a saved entity.', $formatter));
  }

  /**
   * Sets field values and returns a render array as built by
   * \Drupal\Core\Field\FieldItemListInterface::view().
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $referenced_entities
   *   An array of entity objects that will be referenced.
   * @param string $formatter
   *   The formatted plugin that will be used for building the render array.
   * @param array $formatter_options
   *   Settings specific to the formatter. Defaults to the formatter's default
   *   settings.
   *
   * @return array
   *   A render array.
   */
  protected function buildRenderArray(array $values, $formatter, $formatter_options = []) {
    // Create the entity that will have the entity reference field.
    $referencing_entity = $this->container->get('entity_type.manager')
      ->getStorage($this->entityType)
      ->create(['name' => $this->randomMachineName()]);

    $items = $referencing_entity->get($this->fieldName);

    // Assign the referenced entities.
    foreach ($values as $value) {
      if ($value instanceof EntityInterface) {
        $items[] = ['target_id' => $value->id()];
      }
      else {
        $items[] = ['text' => $value];
      }
    }

    // Build the renderable array for the field.
    return $items->view(['type' => $formatter, 'settings' => $formatter_options]);
  }

}
