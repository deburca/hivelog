<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\HiveMover;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Moves a hive to another apiary, asking what to do with its parts (0231).
 *
 * For each part that cannot follow the hive unchanged (its components, its
 * sensors) the user chooses to move it with the hive or to detach it first;
 * leaving the page cancels the whole move. Nothing already written is changed.
 */
class HiveMoveForm extends FormBase {

  /**
   * The hive being moved.
   */
  protected Hive $hive;

  public function __construct(protected HiveMover $mover) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self($container->get('hivelog.hive_mover'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hivelog_hive_move_form';
  }

  /**
   * Title of the page, naming the hive.
   *
   * @param \Drupal\hivelog\Entity\Hive $hive
   *   The hive from the route.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title.
   */
  public static function title(Hive $hive) {
    return new TranslatableMarkup('Move @hive to another apiary', ['@hive' => $hive->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?Hive $hive = NULL): array {
    $this->hive = $hive;
    $form['#prefix'] = '<div class="hivelog-entity-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'hivelog/forms';

    $targets = $this->mover->targets($hive, $this->currentUser());
    if (!$targets) {
      $form['none'] = [
        '#markup' => '<p>' . $this->t('There is no other apiary you are a beekeeper or owner in, so this hive cannot be moved.') . '</p>',
      ];
      $form['back'] = $this->cancelLink();
      return $form;
    }

    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Records already written are not changed: the hive keeps its history, and the apiary it is leaving keeps what was written there. New records will belong to the new apiary. The hive\'s queens go with it.') . '</p>',
    ];
    $form['target'] = [
      '#type' => 'select',
      '#title' => $this->t('Move to'),
      '#options' => array_map(fn(Apiary $apiary): string => (string) $apiary->label(), $targets),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Choose an apiary -'),
    ];

    $form['parts'] = ['#tree' => TRUE];
    foreach ($this->mover->dependents($hive) as $id => $part) {
      $form['parts'][$id] = [
        '#type' => 'radios',
        '#title' => $part['label'],
        '#description' => $part['items'] ? $this->t('On this hive: @items', ['@items' => implode(', ', $part['items'])]) : '',
        '#options' => $part['choices'],
        '#required' => TRUE,
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Move hive'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = $this->cancelLink();
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $target = Apiary::load($form_state->getValue('target'));
    if (!$target || !$target->access('write', $this->currentUser())) {
      $form_state->setErrorByName('target', $this->t('You cannot move a hive to that apiary.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $target = Apiary::load($form_state->getValue('target'));
    try {
      $this->mover->move($this->hive, $target, (array) $form_state->getValue('parts'));
    }
    catch (\InvalidArgumentException $e) {
      $this->messenger()->addError($e->getMessage());
      return;
    }
    $this->messenger()->addStatus($this->t('Hive @hive has been moved to @apiary.', [
      '@hive' => $this->hive->label(),
      '@apiary' => $target->label(),
    ]));
    $form_state->setRedirectUrl($this->hive->toUrl());
  }

  /**
   * A Cancel button that returns to the hive.
   */
  protected function cancelLink(): array {
    return [
      '#type' => 'component',
      '#component' => 'hivelog:button',
      '#props' => [
        'label' => (string) $this->t('Cancel'),
        'url' => $this->hive->toUrl()->toString(),
        'variant' => 'default',
      ],
    ];
  }

}
