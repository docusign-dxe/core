<?php

/**
 * @file
 * Listens to PHPUnit test runs.
 *
 * In order to manage different method signatures between PHPUnit versions, we
 * dynamically load a class dependent on the PHPUnit runner version.
 */

  /**
   * {@inheritdoc}
   */
  public function endTest(Test $test, float $time): void {
    restore_error_handler();
  }

class_alias("Drupal\TestTools\PhpUnitCompatibility\PhpUnit" . RunnerVersion::getMajor() . "\AfterSymfonyListener", AfterSymfonyListener::class);
