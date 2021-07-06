<?php

/**
 * @file
 * Defines a class for providing html output results for functional tests.
 *
 * In order to manage different method signatures between PHPUnit versions, we
 * dynamically load a class dependent on the PHPUnit runner version.
 */
class HtmlOutputPrinter extends ResultPrinter {

  use HtmlOutputPrinterTrait;

  /**
   * {@inheritdoc}
   */
  public function printResult(TestResult $result): void {
    parent::printResult($result);

use Drupal\TestTools\PhpUnitCompatibility\RunnerVersion;

class_alias("Drupal\TestTools\PhpUnitCompatibility\PhpUnit" . RunnerVersion::getMajor() . "\HtmlOutputPrinter", HtmlOutputPrinter::class);
