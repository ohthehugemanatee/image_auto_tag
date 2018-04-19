<?php

declare(strict_types = 1);

namespace Drupal\image_auto_tag\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\image_auto_tag\AzureCognitiveServices;
use GuzzleHttp\Exception\TransferException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form for configuring azure services.
 */
class OperationsForm extends FormBase {

  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This method intentionally left blank.
  }

  const PEOPLE_GROUP = 'drupal_image_auto_tag_people';

  /**
   * CogSer service.
   *
   * @var \Drupal\image_auto_tag\AzureCognitiveServices
   */
  protected $azure;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * SettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory service.
   * @param \Drupal\image_auto_tag\AzureCognitiveServices $azureCognitiveServices
   *   Azure CogSer service.
   */
  public function __construct(ConfigFactoryInterface $configFactory, AzureCognitiveServices $azureCognitiveServices, EntityTypeManagerInterface $entityTypeManager) {
    $this->azure = $azureCognitiveServices;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('image_auto_tag.azure'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() : string {
    return 'image_auto_tag_operations';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) : array {
    try {
      $trainingStatus = $this->azure->getPersonGroupTrainingStatus(AzureCognitiveServices::PEOPLE_GROUP);
    }
    catch (TransferException $e) {
      if ($e->getCode() !== 404) {
        $this->messenger()
          ->addError("Could not get training status. Code {$e->getCode()}: {$e->getMessage()}.");
      }
      else {
        $messageBody = json_decode((string) $e->getResponse()->getBody());
        if ($messageBody->error->code !== 'PersonGroupNotTrained') {
          $this->messenger()
            ->addError('Training group does not exist on the remote resource. Please re-save the settings tab.');
          return $form;
        }
        $trainingStatus = new \stdClass();
        $trainingStatus->status = 'Never trained';
        $trainingStatus->lastActionDateTime = 'N/A';
      }
    }
    $form['training_status'] = [
      '#title' => t('Training status'),
      '#type' => 'item',
      '#description' => (string) $trainingStatus->status,
    ];
    $form['last_trained'] = [
      '#title' => $this->t('Last trained'),
      '#type' => 'item',
      '#description' => (string) $trainingStatus->lastActionDateTime . ' UTC',
    ];

    $form['train'] = [
      '#type' => 'submit',
      '#value' => $this->t('Train now'),
      '#name' => 'train',
      '#submit' => [[$this, 'train']],
    ];
    $form['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset and re-submit'),
      '#description' => $this->t('Resets all saved data and re-submits everything'),
      '#submit' => [[$this, 'reset']],
    ];

    return $form;
  }


  /**
   * Submit handler for the "Train" button.
   */
  public function train(array &$form, FormStateInterface $form_state) {
    $this->azure->trainPersonGroup(AzureCognitiveServices::PEOPLE_GROUP);
    $this->messenger()->addStatus('Training request submitted.');
  }

  /**
   * Submit handler for the "Reset" button.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form status.
   */
  public function reset(array &$form, FormStateInterface $form_state) {
    try {
      $this->azure->deletePersonGroup(AzureCognitiveServices::PEOPLE_GROUP);
      $this->azure->createPersonGroup(AzureCognitiveServices::PEOPLE_GROUP, 'Automatically created group for Drupal Image Auto Tag module.');
    }
    catch (TransferException $e) {
      $this->messenger()->addError("Could not reset remote data. Code {$e->getCode()}: {$e->getMessage()}");
      return;
    }
    $personMapStorage = $this->entityTypeManager->getStorage('image_auto_tag_person_map');
    $allPersonMaps = $personMapStorage->loadMultiple();
    $personMapStorage->delete($allPersonMaps);
    $this->messenger()->addStatus('All training records deleted.');
    $this->messenger()->addWarning('Note: Re-submitting is not implemented yet. You can manually re-submit content by saving it.');

  }

}
