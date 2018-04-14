<?php

namespace Drupal\media_auto_tag;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Person map entities.
 *
 * @ingroup media_auto_tag
 */
class PersonMapListBuilder extends EntityListBuilder {


  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Person map ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\media_auto_tag\Entity\PersonMap */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.media_auto_tag_person_map.edit_form',
      ['media_auto_tag_person_map' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
