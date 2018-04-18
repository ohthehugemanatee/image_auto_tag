<?php

namespace Drupal\media_auto_tag\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the Person map entity.
 *
 * @ingroup media_auto_tag
 *
 * @ContentEntityType(
 *   id = "media_auto_tag_person_map",
 *   label = @Translation("Person map"),
 *   handlers = {
 *     "views_data" = "Drupal\media_auto_tag\Entity\PersonMapViewsData",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *   },
 *   base_table = "media_auto_tag_person_map",
 *   entity_keys = {
 *     "id" = "id",
 *     "foreign_id" = "foreign_id",
 *     "local_id" = "local_id",
 *     "local_entity_type" = "local_entity_type"
 *   },
 *   render_cache = FALSE,
 *   fieldable = FALSE,
 *   translatable = FALSE,
 *   links = {
 *   },
 *   permission_granularity = "entity_type",
 *   admin_permission = "administer media auto tag",
 * )
 */
class PersonMap extends ContentEntityBase implements PersonMapInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() : int {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) : PersonMapInterface {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    // Foreign Id.
    $fields['foreign_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Foreign Id'))
      ->setDescription(t('The foreign Id of the Person.'))
      ->setSettings([
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setRequired(TRUE);
    // Local entity Id.
    $fields['local_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Drupal entity Id'))
      ->setDescription(t('The Drupal entity Id of the Person.'))
      ->setDefaultValue('')
      ->setRequired(TRUE);
    // Local entity type Id.
    $fields['local_entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Drupal entity type'))
      ->setDescription(t('The Drupal entity type of the Person.'))
      ->setSettings([
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setRequired(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getForeignId(): string {
    return $this->get('foreign_id')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function setForeignId($foreignId): PersonMapInterface {
    $this->set('foreign_id', $foreignId);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocalId(): string {
    return $this->get('local_id')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function setLocalId(string $localId): PersonMapInterface {
    $this->set('local_id', $localId);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocalEntityTypeId(): string {
    return $this->get('local_entity_type')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function setLocalEntityTypeId(string $entityTypeId): PersonMapInterface {
    $this->set('local_entity_type', $entityTypeId);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocalEntity(): EntityInterface {
    return $this->entityTypeManager()->getStorage($this->getLocalEntityTypeId())
      ->load($this->getLocalId());
  }

}
