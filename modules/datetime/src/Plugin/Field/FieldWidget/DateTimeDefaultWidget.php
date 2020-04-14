<?php

namespace Drupal\datetime\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'datetime_default' widget.
 *
 * @FieldWidget(
 *   id = "datetime_default",
 *   label = @Translation("Date and time"),
 *   field_types = {
 *     "datetime"
 *   }
 * )
 */
class DateTimeDefaultWidget extends DateTimeWidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The date format storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $dateStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityStorageInterface $date_storage) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $this->dateStorage = $date_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'date_increment' => '1',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')->getStorage('date_format')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['value']['#date_increment'] = $this->getSetting('date_increment');

    // If the field is date-only, make sure the title is displayed. Otherwise,
    // wrap everything in a fieldset, and the title will be shown in the legend.
    if ($this->getFieldSetting('datetime_type') === DateTimeItem::DATETIME_TYPE_DATE) {
      $element['value']['#title'] = $this->fieldDefinition->getLabel();
      $element['value']['#description'] = $this->fieldDefinition->getDescription();
    }
    else {
      $element['#theme_wrappers'][] = 'fieldset';
    }

    // Identify the type of date and time elements to use.
    switch ($this->getFieldSetting('datetime_type')) {
      case DateTimeItem::DATETIME_TYPE_DATE:
        $date_type = 'date';
        $time_type = 'none';
        $date_format = $this->dateStorage->load('html_date')->getPattern();
        $time_format = '';
        break;

      default:
        $date_type = 'date';
        $time_type = 'time';
        $date_format = $this->dateStorage->load('html_date')->getPattern();
        $time_format = $this->dateStorage->load('html_time')->getPattern();
        break;
    }

    $element['value'] += [
      '#date_date_format' => $date_format,
      '#date_date_element' => $date_type,
      '#date_date_callbacks' => [],
      '#date_time_format' => $time_format,
      '#date_time_element' => $time_type,
      '#date_time_callbacks' => [],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $datetime_type = $this->getFieldSetting('datetime_type');

    if ($datetime_type === DateTimeItem::DATETIME_TYPE_DATETIME) {
      // Create the date increment element. Default to one second.
      $element['date_increment'] = [
        '#type' => 'number',
        '#title' => $this->t('Step'),
        '#description' => $this->t('The number of seconds the element will step over. If the value is a multiple of 60, seconds will be disabled in the element. If the value is a multiple of 3600, minutes will be disabled.'),
        '#default_value' => $this->getSetting('date_increment'),
      ];
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $datetime_type = $this->getFieldSetting('datetime_type');

    if ($datetime_type === DateTimeItem::DATETIME_TYPE_DATETIME) {
      return [$this->t('Step: @step', ['@step' => $this->getSetting('date_increment')])];
    }
    return [];
  }

}
