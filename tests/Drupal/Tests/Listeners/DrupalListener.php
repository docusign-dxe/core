<?php

namespace Drupal\Tests\Listeners;

use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;
use PHPUnit\Framework\Test;

/**
 * @file
 * Listens to PHPUnit test runs.
 *
 * In order to manage different method signatures between PHPUnit versions, we
 * dynamically load a class dependent on the PHPUnit runner version.
 */
class DrupalListener implements TestListener {

  use TestListenerDefaultImplementation;
  use DeprecationListenerTrait;
  use DrupalComponentTestListenerTrait;
  use DrupalStandardsListenerTrait;

  /**
   * {@inheritdoc}
   */
  public function startTest(Test $test): void {
    $this->deprecationStartTest($test);
  }

  /**
   * {@inheritdoc}
   */
  public function endTest(Test $test, float $time): void {
    $this->deprecationEndTest($test, $time);
    $this->componentEndTest($test, $time);
    $this->standardsEndTest($test, $time);
  }

class_alias("Drupal\TestTools\PhpUnitCompatibility\PhpUnit" . RunnerVersion::getMajor() . "\DrupalListener", DrupalListener::class);
