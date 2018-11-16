<?php

declare(strict_types = 1);

namespace Drupal\image_auto_tag\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * ImageAutoTagService annotation class.
 *
 * @package Drupal\image_auto_tag\Annotation
 */
class ImageAutoTagService extends Plugin {

  /**
   * The machine id of the service.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable label of the service.
   *
   * @var string
   */
  public $label;
}