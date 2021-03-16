<?php

namespace Drupal\Tests\views_ui\Functional;

/**
 * Tests the Xss vulnerability.
 *
 * @group views_ui
 */
class XssTest extends UITestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['node', 'user', 'views_ui', 'views_ui_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  public function testViewsUi() {
    $this->drupalGet('admin/structure/views/view/sa_contrib_2013_035');
    // Verify that the field admin label is properly escaped.
    $this->assertSession()->assertEscaped('<marquee>test</marquee>');

    $this->drupalGet('admin/structure/views/nojs/handler/sa_contrib_2013_035/page_1/header/area');
    // Verify that the token label is properly escaped.
    $this->assertSession()->assertEscaped('{{ title }} == <marquee>test</marquee>');
    $this->assertSession()->assertEscaped('{{ title_1 }} == <script>alert("XSS")</script>');
  }

  /**
   * Checks the admin UI for double escaping.
   */
  public function testNoDoubleEscaping() {
    $this->drupalGet('admin/structure/views');
    $this->assertSession()->assertNoEscaped('&lt;');

    $this->drupalGet('admin/structure/views/view/sa_contrib_2013_035');
    $this->assertSession()->assertNoEscaped('&lt;');

    $this->drupalGet('admin/structure/views/nojs/handler/sa_contrib_2013_035/page_1/header/area');
    $this->assertSession()->assertNoEscaped('&lt;');
  }

  /**
   * Test properly escaped characters in description when views block title
   * contains special characters.
   */
  public function testEscapedBlockDescription() {
    // Visit the block placement URL directly and validate block description.
    $this->drupalGet('admin/structure/block/add/views_block%3Aarticles_and_pages-block_1/' . $this->config('system.theme')->get('default') . '?region=content');
    $this->assertSession()->pageTextContains('Articles and Pages: Articles & Pages');
  }

}
