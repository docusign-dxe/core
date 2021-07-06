<?php

namespace Drupal\Tests\Listeners;

/**
 * @file
 * Defines a class for providing html output links in the Simpletest UI.
 *
 * In order to manage different method signatures between PHPUnit versions, we
 * dynamically load a class dependent on the PHPUnit runner version.
 */

  /**
   * {@inheritdoc}
   */
  public function write(string $buffer): void {
    $this->simpletestUiWrite($buffer);
  }

class_alias("Drupal\TestTools\PhpUnitCompatibility\PhpUnit" . RunnerVersion::getMajor() . "\SimpletestUiPrinter", SimpletestUiPrinter::class);
