<?php

namespace Drupal\media_auto_tag;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Person map entity.
 *
 * @see \Drupal\media_auto_tag\Entity\PersonMap.
 */
class PersonMapAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\media_auto_tag\Entity\PersonMapInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished person map entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published person map entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit person map entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete person map entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add person map entities');
  }

}
