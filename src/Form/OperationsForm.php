<?php

declare(strict_types = 1);

namespace Drupal\image_auto_tag\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Queue\QueueFactory;
use Drupal\image_auto_tag\AzureCognitiveServices;
use Drupal\image_auto_tag\EntityOperationsInterface;
use Drupal\image_auto_tag\ImageAutoTag;
use Drupal\image_auto_tag\ImageAutoTagInterface;
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
   * Image Auto Tag service.
   *
   * @var \Drupal\image_auto_tag\ImageAutoTagInterface
   */
  protected $imageAutoTag;

  /**
   * Image Auto Tag Entity Operations service.
   *
   * @var \Drupal\image_auto_tag\EntityOperationsInterface
   */
  protected $entityOperations;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Queue Factory service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * SettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory service.
   * @param \Drupal\image_auto_tag\AzureCognitiveServices $azureCognitiveServices
   *   Azure CogSer service.
   */
  public function __construct(ConfigFactoryInterface $configFactory, AzureCognitiveServices $azureCognitiveServices, ImageAutoTagInterface $imageAutoTag, EntityOperationsInterface $entityOperations, EntityTypeManagerInterface $entityTypeManager, QueueFactory $queueFactory) {
    $this->azure = $azureCognitiveServices;
    $this->imageAutotag = $imageAutoTag;
    $this->entityOperations = $entityOperations;
    $this->entityTypeManager = $entityTypeManager;
    $this->config = $configFactory->get('image_auto_tag.settings');
    $this->queueFactory = $queueFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('image_auto_tag.azure'),
      $container->get('image_auto_tag'),
      $container->get('image_auto_tag.entity_operations'),
      $container->get('entity_type.manager'),
      $container->get('queue')
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
      $trainingStatus = $this->azure->getTrainingStatus();
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
      '#title' => t('Remote Training status'),
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

    $form['queue_status'] = [
      '#title' => $this->t('Queued actions'),
      '#type' => 'item',
      '#description' => $this->t('There are @people person, @detection detection, and @deletion deletion operations in the cron queue.',
        [
          '@people' => $this->queueFactory->get('image_auto_tag_process_person')->numberOfItems(),
          '@detection' => $this->queueFactory->get('image_auto_tag_detect_faces')->numberOfItems(),
          '@deletion' => $this->queueFactory->get('image_auto_tag_deleted_entity')->numberOfItems(),
        ]
      ),
    ];

    $form['run_queues'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run queues now'),
      '#name' => 'run_queues',
      '#submit' => [[$this, 'runQueues']],
    ];

    // Get the "person" entity type and bundle.
    $personEntityBundleString = $this->config->get('person_entity_bundle');
    list($personEntityType, $personEntityBundle) = explode('.', $personEntityBundleString);
    // Count the number of people, and the number of people maps.
    $peopleEntityCount = $this->entityTypeManager->getStorage($personEntityType)->getQuery()
      ->condition('type', $personEntityBundle)
      ->count()
      ->execute();
    $peopleMapCount = $this->entityTypeManager->getStorage('image_auto_tag_person_map')->getQuery()
      ->condition('local_entity_type', $personEntityType)
      ->count()
      ->execute();
    // Progress bar.
    $form['submitted_people'] = [
      '#title' => $this->t('People submitted'),
      '#type' => 'item',
      '#theme' => 'progress_bar',
      '#percent' => $peopleEntityCount ? (int) (100 * $peopleMapCount / $peopleEntityCount) : 100,
      '#message' => $this->t('@indexed/@total submitted', ['@indexed' => $peopleMapCount, '@total' => $peopleEntityCount]),
      // Custom stylesheet to remove background animation from the progress bar.
      '#prefix' => '<div class="image-auto-tag clearfix">',
      '#suffix' => '</div>',
      '#attached' => [
        'library' => [
          'image_auto_tag/drupal.image_auto_tag.admin_css',
        ],
      ],
    ];

    $form['submit_missing_people'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit missing people'),
      '#description' => $this->t('Submit unsubmitted people'),
      '#submit' => [[$this, 'submitMissingPeople']],
    ];

    $form['reset_people'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset/re-submit all people now'),
      '#description' => $this->t('Resets all saved data and re-submits everything'),
      '#submit' => [[$this, 'reset']],
    ];

    $form['submit_to_queue'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset/re-submit all people on cron'),
      '#description' => $this->t('Reset and add all people to the cron queue for processing.'),
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
   * Submit handler for the "Run queues now" button.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function runQueues(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addWarning('Sorry, running queues is not implemented yet.');
  }

  /**
   * Submit handler for the "Submit missing people" button.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function submitMissingPeople(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addWarning('Note: Re-submitting is not implemented yet. You can manually re-submit content by saving it.');
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
      $this->azure->deletePersonGroup();
      $this->azure->createPersonGroup('Automatically created group for Drupal Image Auto Tag module.');
    }
    catch (TransferException $e) {
      $this->messenger()->addError("Could not reset remote data. Code {$e->getCode()}: {$e->getMessage()}");
      return;
    }
    $personMapStorage = $this->entityTypeManager->getStorage('image_auto_tag_person_map');
    $allPersonMaps = $personMapStorage->loadMultiple();
    $personMapStorage->delete($allPersonMaps);
    $this->messenger()->addStatus('All remote records deleted.');
    $this->submitAllPeople();
  }

  /**
   * Submit all people records with batch API.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function submitAllPeople() {
    // Get the "person" entity type and bundle.
    $personEntityBundleString = $this->config->get('person_entity_bundle');
    list($personEntityType, $personEntityBundle) = explode('.', $personEntityBundleString);
    // Get all people entities to submit.
    $peopleEntities = $this->entityTypeManager->getStorage($personEntityType)
      ->loadByProperties(['type' => $personEntityBundle]);

    $batch = array(
      'title' => $this->t('Submitting people records...'),
      'operations' => [],
      'init_message'     => t('Starting'),
      'progress_message' => t('Submitted @current out of @total.'),
      'error_message'    => t('An error occurred during submission'),
    );
    foreach ($peopleEntities as $personEntity) {
      $this->entityOperations->syncPerson($personEntity);
      $batch['operations'][] = ['\Drupal\image_auto_tag\EntityOperations::syncPerson',[$personEntity]];
    }

    batch_set($batch);
  }

}
