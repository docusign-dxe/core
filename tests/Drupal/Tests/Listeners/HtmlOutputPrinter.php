<?php

namespace Drupal\Tests\Listeners;

use Drupal\TestTools\PhpUnitCompatibility\RunnerVersion;
use PHPUnit\Framework\TestResult;

// In order to manage different implementations across PHPUnit versions, we
// dynamically load the base ResultPrinter class dependent on the PHPUnit runner
// version.
if (!class_exists(ResultPrinterBase::class, FALSE)) {
  if (RunnerVersion::getMajor() < 9) {
    class_alias('PHPUnit\TextUI\ResultPrinter', ResultPrinterBase::class);
  }
  else {
    class_alias('PHPUnit\TextUI\DefaultResultPrinter', ResultPrinterBase::class);
  }
}

/**
 * @file
 * Defines a class for providing html output results for functional tests.
 *
 * In order to manage different method signatures between PHPUnit versions, we
 * dynamically load a class dependent on the PHPUnit runner version.
 */
class HtmlOutputPrinter extends ResultPrinterBase {

  use HtmlOutputPrinterTrait;

  /**
   * {@inheritdoc}
   */
  public function printResult(TestResult $result): void {
    parent::printResult($result);

use Drupal\TestTools\PhpUnitCompatibility\RunnerVersion;

class_alias("Drupal\TestTools\PhpUnitCompatibility\PhpUnit" . RunnerVersion::getMajor() . "\HtmlOutputPrinter", HtmlOutputPrinter::class);
