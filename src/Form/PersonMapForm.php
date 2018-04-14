<?php

namespace Drupal\media_auto_tag\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Person map edit forms.
 *
 * @ingroup media_auto_tag
 */
class PersonMapForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\media_auto_tag\Entity\PersonMap */
    $form = parent::buildForm($form, $form_state);

    $entity = $this->entity;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Person map.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Person map.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.media_auto_tag_person_map.canonical', ['media_auto_tag_person_map' => $entity->id()]);
  }

}
