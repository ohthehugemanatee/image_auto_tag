<?php

namespace Drupal\media_auto_tag\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Person map entities.
 */
class PersonMapViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Additional information for Views integration, such as table joins, can be
    // put here.

    return $data;
  }

}
