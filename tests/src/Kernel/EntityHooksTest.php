<?php

declare(strict_types = 1);

namespace Drupal\tests\media_auto_tag\Kernel;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\field\Tests\EntityReference\EntityReferenceTestTrait;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media_auto_tag\AzureCognitiveServices;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\image\Kernel\ImageFieldCreationTrait;

/**
 * Class EntityHooksTest.
 *
 * Tests the entity API integrations.
 *
 * @package Drupal\tests\media_auto_tag\Kernel
 */
class EntityHooksTest extends KernelTestBase {

  use ImageFieldCreationTrait;
  use EntityReferenceTestTrait;

  protected static $modules = [
    'media_auto_tag',
    'node',
    'image',
    'file',
    'entity_reference',
    'system',
    'user',
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('media_auto_tag_person_map');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    // Create content types.
    try {
      $this->createContentType([
        'type' => 'person',
        'name' => 'person',
      ]);
      $this->createContentType([
        'type' => 'article',
        'name' => 'article',
      ]);
    }
    catch (EntityStorageException $e) {
      self::fail('Could not create content types');
    }
    // Add fields to the content types.
    $this->createImageField('field_face', 'person');
    $this->createEntityReferenceField('node', 'article', 'field_people', 'Detected People', 'node');
    $this->createImageField('field_image', 'article', [], [
      'third_party_settings' => [
        'media_auto_tag' => [
          'detect_faces' => TRUE,
          'tag_field' => 'field_people',
        ],
      ],
    ]);
    // Create our configuration.
    $GLOBALS['config']['media_auto_tag.settings'] = [
      'azure_endpoint' => 'https://example.com/endpoint/',
      'azure_service_key' => 'dummy_service_key',
      'person_entity_bundle' => 'node.person',
      'person_image_field' => 'node.field_face',
      'synchronous' => TRUE,
    ];


  }

  /**
   * Test creating a Person and Face record.
   */
  public function testFaceCreate() {
    // Create a mock AzureCognitiveServices class.
    $azure = $this->prophesize(AzureCognitiveServices::class);
    $azure->serviceStatus()
      ->willReturn(TRUE)
      ->shouldBeCalledTimes(2);
    $personReturn = new \stdClass();
    $personReturn->personId = 'dummy_person_id';
    $azure->createPerson(AzureCognitiveServices::PEOPLE_GROUP, 'Test person')
      ->shouldBeCalled()
      ->willReturn($personReturn);
    $faceReturn = new \stdClass();
    $faceReturn->persistedFaceId = 'dummy_persistent_face_id';
    $azure->addFace(AzureCognitiveServices::PEOPLE_GROUP, 'dummy_person_id', 'public://fakefile.jpeg')
      ->shouldBeCalled()
      ->willReturn($faceReturn);
    $azure = $azure->reveal();
    $this->container->set('media_auto_tag.azure', $azure);

    $file = File::create([
      'filename' => '/tmp/fakefile.jpeg',
      'uri' => 'public://fakefile.jpeg',
      'langcode' => 'en',
    ]);
    $file->save();
    $article = Node::create([
      'type' => 'person',
      'title' => 'Test person',
      'field_face' => $file,
    ]);
    $saveStatus = $article->save();
    // Check that save was successful.
    self::assertEquals(SAVED_NEW, $saveStatus, 'Node was not saved successfully');
    // Check that personMaps were created.
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager */
    $entityTypeManager = $this->container->get('entity_type.manager');
    $personMapStorage = $entityTypeManager->getStorage('media_auto_tag_person_map');
    self::assertNotEmpty($article->id(), 'Node ID was empty.');
    /** @var \Drupal\media_auto_tag\Entity\PersonMap[] $personMapForPerson */
    $personMapForPerson = $personMapStorage->loadByProperties([
      'foreign_id' => 'dummy_person_id',
    ]);
    self::assertNotEmpty($personMapForPerson, 'No PersonMap created for the person node');
    self::assertCount(1, $personMapForPerson, 'Too many PersonMaps created for one person');
    $personMapForPerson = reset($personMapForPerson);
    self::assertEquals($article->getEntityTypeId(), $personMapForPerson->getLocalEntityTypeId(), 'PersonMap for Person points to the wrong entity type id.');
    self::assertEquals($article->id(), $personMapForPerson->getLocalId(), 'PersonMap for Person points to the wrong Node Id.');
    $personMapForFace = $personMapStorage->loadByProperties([
      'foreign_id' => 'dummy_persistent_face_id',
    ]);
    self::assertNotEmpty($personMapForFace, 'No PersonMap created for the face file');
    self::assertCount(1, $personMapForFace, 'Too many PersonMaps created for one face');
    $personMapForFace = reset($personMapForFace);
    self::assertEquals($file->getEntityTypeId(), $personMapForFace->getLocalEntityTypeId(), 'PersonMap for Face points to the wrong entity type id.');
    self::assertEquals($article->id(), $personMapForFace->getLocalId(), 'PersonMap for Face points to the wrong entity id.');
  }

  /**
   * Re-implementation of ContentTypeCreationTrait, because it's buggy :( .
   *
   * @param array $values
   *   An array of settings to change from the defaults.
   *   Example: 'type' => 'foo'.
   *
   * @return \Drupal\node\Entity\NodeType
   *   Created content type.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   If anything goes wrong.
   */
  protected function createContentType(array $values) : NodeType {
    // Find a non-existent random type name.
    if (!isset($values['type'])) {
      do {
        $id = strtolower($this->randomMachineName(8));
      } while (NodeType::load($id));
    }
    else {
      $id = $values['type'];
    }
    $values += [
      'type' => $id,
      'name' => $id,
    ];
    $type = NodeType::create($values);
    $status = $type->save();
    return $type;
  }

}
