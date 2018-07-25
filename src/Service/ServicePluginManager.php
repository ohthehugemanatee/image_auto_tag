<?php

namespace Drupal\image_auto_tag\Service;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages Image Auto Tag Service plugins.
 *
 * @see \Drupal\image_auto_tag\Annotation\ImageAutoTagService
 * @see \Drupal\image_auto_tag\Service\ServicePluginBase
 * @see plugin_api
 */
class ServicePluginManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/image_auto_tag/Service',
      $namespaces,
      $module_handler,
      'Drupal\image_auto_tag\Service\ServicePluginInterface',
      'Drupal\image_auto_tag\Annotation\ImageAutoTagService');

    $this->alterInfo('image_auto_tag_service_info');
    $this->setCacheBackend($cache_backend, 'image_auto_tag_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    parent::processDefinition($definition, $plugin_id);

    // Require ID and label.
    if (!isset($definition['id'])) {
      throw new PluginException(sprintf('The facet source plugin %s must define the id property.', $plugin_id));
    }
    if (empty($definition['label'])) {
      throw new PluginException(sprintf('The image auto tag service plugin %s must define the label property.', $plugin_id));
    }
  }

}
