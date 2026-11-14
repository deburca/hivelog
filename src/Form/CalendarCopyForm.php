<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\HivelogCalendarCopier;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds another apiary's calendar (or the starter one) to this apiary (0249).
 *
 * Additive: actions the apiary already has are skipped and nothing is
 * deleted or changed, so running it twice is harmless. A skipped action is
 * given a requirement or yield it lacks (task 0270), and the message says
 * how many were added. The source list comes
 * from `HivelogCalendarCopier::sourceOptions()`, so a new kind of source
 * shows up here without a change to this form.
 */
class CalendarCopyForm extends FormBase {

  /**
   * The apiary receiving the calendar.
   */
  protected Apiary $apiary;

  public function __construct(protected HivelogCalendarCopier $copier) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self($container->get('hivelog.calendar_copier'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hivelog_calendar_copy_form';
  }

  /**
   * Title of the page, naming the apiary.
   */
  public static function title(Apiary $apiary) {
    return new TranslatableMarkup('Copy a calendar into @apiary', ['@apiary' => $apiary->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?Apiary $apiary = NULL): array {
    $this->apiary = $apiary;
    $form['#prefix'] = '<div class="hivelog-entity-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'hivelog/forms';

    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Adds the actions of another calendar to this apiary\'s. An action this apiary already has (same title, scope and starting week) is skipped: it is not changed, but it is given a required item or expected yield the other calendar has for it and it lacks. Nothing is removed. Items and products an action needs are matched by name and unit, and added to this apiary\'s catalogue if missing, with no stock and no price history.') . '</p>',
    ];
    $form['source'] = [
      '#type' => 'select',
      '#title' => $this->t('Copy from'),
      '#options' => $this->copier->sourceOptions($this->currentUser(), $apiary),
      '#required' => TRUE,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Copy calendar'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'component',
      '#component' => 'hivelog:button',
      '#props' => [
        'label' => (string) $this->t('Cancel'),
        'url' => $apiary->toUrl()->toString(),
        'variant' => 'default',
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $result = $this->copier->apply($this->apiary, (string) $form_state->getValue('source'), $this->currentUser());
    }
    catch (\InvalidArgumentException $e) {
      $this->messenger()->addError($this->t('That calendar is not available to you.'));
      return;
    }
    $this->messenger()->addStatus($this->formatPlural(
      $result['created'],
      'Added 1 calendar action to @apiary (@skipped already there).',
      'Added @count calendar actions to @apiary (@skipped already there).',
      ['@apiary' => $this->apiary->label(), '@skipped' => $result['skipped']],
    ));
    // Say so when recipe rows were added, above all when no action was: the
    // message would otherwise read as if nothing had happened (task 0270).
    if ($result['requirements'] || $result['yields']) {
      $this->messenger()->addStatus($this->t('Also added @requirements required item(s) and @yields expected yield(s) to its actions.', [
        '@requirements' => $result['requirements'],
        '@yields' => $result['yields'],
      ]));
    }
    $form_state->setRedirect('hivelog.apiary.calendar_action.collection', ['apiary' => $this->apiary->id()]);
  }

}
