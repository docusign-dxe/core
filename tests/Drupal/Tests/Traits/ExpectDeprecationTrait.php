<?php

namespace Drupal\Tests\Traits;

/**
 * Adds the ability to dynamically set expected deprecation messages in tests.
 *
 * @internal
 *
 * @deprecated in drupal:9.1.0 and is removed from drupal:10.0.0. Use
 *   \Symfony\Bridge\PhpUnit\ExpectDeprecationTrait instead.
 *
 * @see https://www.drupal.org/node/3161901
 */
trait ExpectDeprecationTrait {

  /**
   * Sets an expected deprecation message.
   *
   * @param string $message
   *   The expected deprecation message.
   */
  protected function addExpectedDeprecationMessage($message) {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:9.1.0 and is removed from drupal:10.0.0. Use \Symfony\Bridge\PhpUnit\ExpectDeprecationTrait::expectDeprecation() instead. See https://www.drupal.org/node/3161901', E_USER_DEPRECATED);
    $this->expectDeprecation($message);
  }

  /**
   * Sets an expected deprecation message.
   *
   * @param string $message
   *   The expected deprecation message.
   *
   * @deprecated in drupal:8.8.5 and is removed from drupal:9.0.0. Use
   *   ::addExpectedDeprecationMessage() instead.
   *
   * @see https://www.drupal.org/node/3106024
   */
  protected function expectDeprecation($message) {
    if (strpos($message, 'ExpectDeprecationTrait::expectDeprecation') === FALSE) {
      @trigger_error('ExpectDeprecationTrait::expectDeprecation is deprecated in drupal:8.8.5 and is removed from drupal:9.0.0. Use ::addExpectedDeprecationMessage() instead. See https://www.drupal.org/node/3106024', E_USER_DEPRECATED);
    }
    $this->addExpectedDeprecationMessage($message);
  }

  /**
   * Sets expected deprecation messages.
   *
   * @param string[] $messages
   *   The expected deprecation messages.
   *
   * @see \Symfony\Bridge\PhpUnit\Legacy\ExpectDeprecationTraitForV8_4::expectDeprecation()
   */
  public function expectedDeprecations(array $messages) {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:9.1.0 and is removed from drupal:10.0.0. Use \Symfony\Bridge\PhpUnit\ExpectDeprecationTrait::expectDeprecation() instead. See https://www.drupal.org/node/3161901', E_USER_DEPRECATED);
    foreach ($messages as $message) {
      $this->expectDeprecation($message);
    }
  }

}
