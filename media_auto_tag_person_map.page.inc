<?php

/**
 * @file
 * Contains media_auto_tag_person_map.page.inc.
 *
 * Page callback for Person map entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Person map templates.
 *
 * Default template: media_auto_tag_person_map.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_media_auto_tag_person_map(array &$variables) {
  // Fetch PersonMap Entity Object.
  $media_auto_tag_person_map = $variables['elements']['#media_auto_tag_person_map'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
