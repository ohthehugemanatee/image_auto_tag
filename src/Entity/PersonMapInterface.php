<?php

namespace Drupal\media_auto_tag\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Person map entities.
 *
 * @ingroup media_auto_tag
 */
interface PersonMapInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Gets the Person map name.
   *
   * @return string
   *   Name of the Person map.
   */
  public function getName();

  /**
   * Sets the Person map name.
   *
   * @param string $name
   *   The Person map name.
   *
   * @return \Drupal\media_auto_tag\Entity\PersonMapInterface
   *   The called Person map entity.
   */
  public function setName($name);

  /**
   * Gets the Person map creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Person map.
   */
  public function getCreatedTime();

  /**
   * Sets the Person map creation timestamp.
   *
   * @param int $timestamp
   *   The Person map creation timestamp.
   *
   * @return \Drupal\media_auto_tag\Entity\PersonMapInterface
   *   The called Person map entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Person map published status indicator.
   *
   * Unpublished Person map are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Person map is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Person map.
   *
   * @param bool $published
   *   TRUE to set this Person map to published, FALSE to set it to unpublished.
   *
   * @return \Drupal\media_auto_tag\Entity\PersonMapInterface
   *   The called Person map entity.
   */
  public function setPublished($published);

}
