<?php

namespace Drupal\content_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;

abstract class ContentBlockBase extends BlockBase implements BlockPluginInterface {
  /**
   * Optionally override this to manually set a content_id for this block.
   *
   * @var string
   */
  var $content_id = '';

  /**
   * Override to specify configuration options
   *
   * @return array
   */
  public function getOptions() {
    return [];
  }

  public function getContentBlockId() {
    if (!empty($this->content_id)) {
      return $this->content_id;
    }

    return strtolower(preg_replace([
      '/([a-z\d])([A-Z])/',
      '/([^_])([A-Z][a-z])/'
    ], '$1_$2', self::class));
  }

  /**
   * Gets the current value for an option returned by getOptions()
   *
   * @param $option_name
   * @param bool $replace_tokens
   * @return string
   */
  public function getOptionValue($option_name, $replace_tokens = FALSE) {
    $config = $this->getConfiguration();

    $options = $this->getOptions();

    $default = isset($options[$option_name]['#default_value']) ? $options[$option_name]['#default_value'] : '';

    if (isset($config[$option_name]['#type']) && $config[$option_name]['#type'] === 'formatted_text') {
      $default = [
        'format' => $config[$option_name]['#format'],
        'value' => $default,
      ];
    }

    $value = isset($config[$option_name]) ? $config[$option_name] : $default;

    if ($replace_tokens) {
      $value = \Drupal::token()->replace($value);
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();

    foreach ($this->getOptions() as $option_name => $option) {
      $option += [
        '#type' => 'textfield',
        '#title' => $this->t(ucfirst(str_replace('_', ' ', $option_name))),
      ];

      $default = isset($option['#default_value']) ? $option['#default_value'] : '';

      $option['#default_value'] = isset($config[$option_name]) ? $config[$option_name] : $default;

      $form[$option_name] = $option;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    foreach ($this->getOptions() as $option_name => $option) {
      $this->setConfigurationValue($option_name, $form_state->getValue($option_name));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $default_config = \Drupal::config('content_block.settings');

    $values = [];

    foreach ($this->getOptions() as $option_name => $option) {
      $values[$option_name] = $default_config->get($this->getContentBlockId() . '.' . $option_name);
    }

    return $values;
  }
}
