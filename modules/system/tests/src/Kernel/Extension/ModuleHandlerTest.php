<?php

namespace Drupal\Tests\system\Kernel\Extension;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Extension\MissingDependencyException;
use Drupal\Core\Extension\ModuleUninstallValidatorException;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests ModuleHandler functionality.
 *
 * @group Extension
 */
class ModuleHandlerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * The basic functionality of retrieving enabled modules.
   */
  public function testModuleList() {
    $module_list = ['path_alias', 'system'];

    $this->assertModuleList($module_list, 'Initial');

    // Try to install a new module.
    $this->moduleInstaller()->install(['ban']);
    $module_list[] = 'ban';
    sort($module_list);
    $this->assertModuleList($module_list, 'After adding a module');

    // Try to mess with the module weights.
    module_set_weight('ban', 20);

    // Move ban to the end of the array.
    unset($module_list[array_search('ban', $module_list)]);
    $module_list[] = 'ban';
    $this->assertModuleList($module_list, 'After changing weights');

    // Test the fixed list feature.
    $fixed_list = [
      'system' => 'core/modules/system/system.module',
      'menu' => 'core/modules/menu/menu.module',
    ];
    $this->moduleHandler()->setModuleList($fixed_list);
    $new_module_list = array_combine(array_keys($fixed_list), array_keys($fixed_list));
    $this->assertModuleList($new_module_list, t('When using a fixed list'));
  }

  /**
   * Assert that the extension handler returns the expected values.
   *
   * @param array $expected_values
   *   The expected values, sorted by weight and module name.
   * @param $condition
   */
  protected function assertModuleList(array $expected_values, $condition) {
    $expected_values = array_values(array_unique($expected_values));
    $enabled_modules = array_keys($this->container->get('module_handler')->getModuleList());
    $this->assertEqual($expected_values, $enabled_modules, new FormattableMarkup('@condition: extension handler returns correct results', ['@condition' => $condition]));
  }

  /**
   * Tests dependency resolution.
   *
   * Intentionally using fake dependencies added via hook_system_info_alter()
   * for modules that normally do not have any dependencies.
   *
   * To simplify things further, all of the manipulated modules are either
   * purely UI-facing or live at the "bottom" of all dependency chains.
   *
   * @see module_test_system_info_alter()
   * @see https://www.drupal.org/files/issues/dep.gv__0.png
   */
  public function testDependencyResolution() {
    $this->enableModules(['module_test']);
    $this->assertTrue($this->moduleHandler()->moduleExists('module_test'), 'Test module is enabled.');

    // Ensure that modules are not enabled.
    $this->assertFalse($this->moduleHandler()->moduleExists('color'), 'Color module is disabled.');
    $this->assertFalse($this->moduleHandler()->moduleExists('config'), 'Config module is disabled.');
    $this->assertFalse($this->moduleHandler()->moduleExists('help'), 'Help module is disabled.');

    // Create a missing fake dependency.
    // Color will depend on Config, which depends on a non-existing module Foo.
    // Nothing should be installed.
    \Drupal::state()->set('module_test.dependency', 'missing dependency');

    try {
      $result = $this->moduleInstaller()->install(['color']);
      $this->fail('ModuleInstaller::install() throws an exception if dependencies are missing.');
    }
    catch (MissingDependencyException $e) {
      // Expected exception; just continue testing.
    }

    $this->assertFalse($this->moduleHandler()->moduleExists('color'), 'ModuleInstaller::install() aborts if dependencies are missing.');

    // Fix the missing dependency.
    // Color module depends on Config. Config depends on Help module.
    \Drupal::state()->set('module_test.dependency', 'dependency');

    $result = $this->moduleInstaller()->install(['color']);
    $this->assertTrue($result, 'ModuleInstaller::install() returns the correct value.');

    // Verify that the fake dependency chain was installed.
    $this->assertTrue($this->moduleHandler()->moduleExists('config'));
    $this->assertTrue($this->moduleHandler()->moduleExists('help'));

    // Verify that the original module was installed.
    $this->assertTrue($this->moduleHandler()->moduleExists('color'), 'Module installation with dependencies succeeded.');

    // Verify that the modules were enabled in the correct order.
    $module_order = \Drupal::state()->get('module_test.install_order', []);
    $this->assertEqual(['help', 'config', 'color'], $module_order);

    // Uninstall all three modules explicitly, but in the incorrect order,
    // and make sure that ModuleInstaller::uninstall() uninstalled them in the
    // correct sequence.
    $result = $this->moduleInstaller()->uninstall(['config', 'help', 'color']);
    $this->assertTrue($result, 'ModuleInstaller::uninstall() returned TRUE.');

    foreach (['color', 'config', 'help'] as $module) {
      $this->assertEqual(SCHEMA_UNINSTALLED, drupal_get_installed_schema_version($module), "{$module} module was uninstalled.");
    }
    $uninstalled_modules = \Drupal::state()->get('module_test.uninstall_order', []);
    $this->assertEqual(['color', 'config', 'help'], $uninstalled_modules, 'Modules were uninstalled in the correct order.');

    // Enable Color module again, which should enable both the Config module and
    // Help module. But, this time do it with Config module declaring a
    // dependency on a specific version of Help module in its info file. Make
    // sure that Drupal\Core\Extension\ModuleInstaller::install() still works.
    \Drupal::state()->set('module_test.dependency', 'version dependency');

    $result = $this->moduleInstaller()->install(['color']);
    $this->assertTrue($result, 'ModuleInstaller::install() returns the correct value.');

    // Verify that the fake dependency chain was installed.
    $this->assertTrue($this->moduleHandler()->moduleExists('config'));
    $this->assertTrue($this->moduleHandler()->moduleExists('help'));

    // Verify that the original module was installed.
    $this->assertTrue($this->moduleHandler()->moduleExists('color'), 'Module installation with version dependencies succeeded.');

    // Finally, verify that the modules were enabled in the correct order.
    $enable_order = \Drupal::state()->get('module_test.install_order', []);
    $this->assertSame(['help', 'config', 'color'], $enable_order);
  }

  /**
   * Tests uninstalling a module installed by a profile.
   */
  public function testUninstallProfileDependency() {
    $profile = 'testing_install_profile_dependencies';
    $dependency = 'dblog';
    $non_dependency = 'ban';
    $this->setInstallProfile($profile);
    // Prime the drupal_get_filename() static cache with the location of the
    // testing_install_profile_dependencies profile as it is not the currently
    // active profile and we don't yet have any cached way to retrieve its
    // location.
    // @todo Remove as part of https://www.drupal.org/node/2186491
    drupal_get_filename('profile', $profile, 'core/profiles/' . $profile . '/' . $profile . '.info.yml');
    $this->enableModules(['module_test', $profile]);

    $data = \Drupal::service('extension.list.module')->reset()->getList();
    $this->assertArrayHasKey($dependency, $data[$profile]->requires);
    $this->assertArrayNotHasKey($non_dependency, $data[$profile]->requires);

    $this->moduleInstaller()->install([$dependency, $non_dependency]);
    $this->assertTrue($this->moduleHandler()->moduleExists($dependency));

    // Uninstall the profile module that is not a dependent.
    $result = $this->moduleInstaller()->uninstall([$non_dependency]);
    $this->assertTrue($result, 'ModuleInstaller::uninstall() returns TRUE.');
    $this->assertFalse($this->moduleHandler()->moduleExists($non_dependency));
    $this->assertEquals(drupal_get_installed_schema_version($non_dependency), SCHEMA_UNINSTALLED, "$non_dependency module was uninstalled.");

    // Verify that the installation profile itself was not uninstalled.
    $uninstalled_modules = \Drupal::state()->get('module_test.uninstall_order', []);
    $this->assertContains($non_dependency, $uninstalled_modules, "$non_dependency module is in the list of uninstalled modules.");
    $this->assertNotContains($profile, $uninstalled_modules, 'The installation profile is not in the list of uninstalled modules.');

    // Try uninstalling the required module.
    $this->expectException(ModuleUninstallValidatorException::class);
    $this->expectExceptionMessage('The following reasons prevent the modules from being uninstalled: The Testing install profile dependencies module is required');
    $this->moduleInstaller()->uninstall([$dependency]);
  }

  /**
   * Tests that a profile can supply only real dependencies.
   */
  public function testProfileAllDependencies() {
    $profile = 'testing_install_profile_all_dependencies';
    $dependencies = ['dblog', 'ban'];

    $this->setInstallProfile($profile);
    // Prime the drupal_get_filename() static cache with the location of the
    // testing_install_profile_dependencies profile as it is not the currently
    // active profile and we don't yet have any cached way to retrieve its
    // location.
    // @todo Remove as part of https://www.drupal.org/node/2186491
    drupal_get_filename('profile', $profile, 'core/profiles/' . $profile . '/' . $profile . '.info.yml');
    $this->enableModules(['module_test', $profile]);

    $data = \Drupal::service('extension.list.module')->reset()->getList();
    foreach ($dependencies as $dependency) {
      $this->assertArrayHasKey($dependency, $data[$profile]->requires);
    }

    $this->moduleInstaller()->install($dependencies);
    foreach ($dependencies as $dependency) {
      $this->assertTrue($this->moduleHandler()->moduleExists($dependency));
    }

    // Try uninstalling the dependencies.
    $this->expectException(ModuleUninstallValidatorException::class);
    $this->expectExceptionMessage('The following reasons prevent the modules from being uninstalled: The Testing install profile all dependencies module is required');
    $this->moduleInstaller()->uninstall($dependencies);
  }

  /**
   * Tests uninstalling a module that has content.
   */
  public function testUninstallContentDependency() {
    $this->enableModules(['module_test', 'entity_test', 'text', 'user', 'help']);
    $this->assertTrue($this->moduleHandler()->moduleExists('entity_test'), 'Test module is enabled.');
    $this->assertTrue($this->moduleHandler()->moduleExists('module_test'), 'Test module is enabled.');

    $this->installSchema('user', 'users_data');
    $entity_types = \Drupal::entityTypeManager()->getDefinitions();
    foreach ($entity_types as $entity_type) {
      if ('entity_test' == $entity_type->getProvider()) {
        $this->installEntitySchema($entity_type->id());
      }
    }

    // Create a fake dependency.
    // entity_test will depend on help. This way help can not be uninstalled
    // when there is test content preventing entity_test from being uninstalled.
    \Drupal::state()->set('module_test.dependency', 'dependency');

    // Create an entity so that the modules can not be disabled.
    $entity = EntityTest::create(['name' => $this->randomString()]);
    $entity->save();

    // Uninstalling entity_test is not possible when there is content.
    try {
      $message = 'ModuleInstaller::uninstall() throws ModuleUninstallValidatorException upon uninstalling a module which does not pass validation.';
      $this->moduleInstaller()->uninstall(['entity_test']);
      $this->fail($message);
    }
    catch (ModuleUninstallValidatorException $e) {
      // Expected exception; just continue testing.
    }

    // Uninstalling help needs entity_test to be un-installable.
    try {
      $message = 'ModuleInstaller::uninstall() throws ModuleUninstallValidatorException upon uninstalling a module which does not pass validation.';
      $this->moduleInstaller()->uninstall(['help']);
      $this->fail($message);
    }
    catch (ModuleUninstallValidatorException $e) {
      // Expected exception; just continue testing.
    }

    // Deleting the entity.
    $entity->delete();

    $result = $this->moduleInstaller()->uninstall(['help']);
    $this->assertTrue($result, 'ModuleInstaller::uninstall() returns TRUE.');
    $this->assertEqual(SCHEMA_UNINSTALLED, drupal_get_installed_schema_version('entity_test'), "entity_test module was uninstalled.");
  }

  /**
   * Tests whether the correct module metadata is returned.
   */
  public function testModuleMetaData() {
    // Generate the list of available modules.
    $modules = $this->container->get('extension.list.module')->getList();
    // Check that the mtime field exists for the system module.
    $this->assertTrue(!empty($modules['system']->info['mtime']), 'The system.info.yml file modification time field is present.');
    // Use 0 if mtime isn't present, to avoid an array index notice.
    $test_mtime = !empty($modules['system']->info['mtime']) ? $modules['system']->info['mtime'] : 0;
    // Ensure the mtime field contains a number that is greater than zero.
    $this->assertIsNumeric($test_mtime);
    $this->assertGreaterThan(0, $test_mtime);
  }

  /**
   * Tests whether module-provided stream wrappers are registered properly.
   */
  public function testModuleStreamWrappers() {
    // file_test.module provides (among others) a 'dummy' stream wrapper.
    // Verify that it is not registered yet to prevent false positives.
    $stream_wrappers = \Drupal::service('stream_wrapper_manager')->getWrappers();
    $this->assertFalse(isset($stream_wrappers['dummy']));
    $this->moduleInstaller()->install(['file_test']);
    // Verify that the stream wrapper is available even without calling
    // \Drupal::service('stream_wrapper_manager')->getWrappers() again.
    // If the stream wrapper is not available file_exists() will raise a notice.
    file_exists('dummy://');
    $stream_wrappers = \Drupal::service('stream_wrapper_manager')->getWrappers();
    $this->assertTrue(isset($stream_wrappers['dummy']));
  }

  /**
   * Tests whether the correct theme metadata is returned.
   */
  public function testThemeMetaData() {
    // Generate the list of available themes.
    $themes = \Drupal::service('theme_handler')->rebuildThemeData();
    // Check that the mtime field exists for the bartik theme.
    $this->assertTrue(!empty($themes['bartik']->info['mtime']), 'The bartik.info.yml file modification time field is present.');
    // Use 0 if mtime isn't present, to avoid an array index notice.
    $test_mtime = !empty($themes['bartik']->info['mtime']) ? $themes['bartik']->info['mtime'] : 0;
    // Ensure the mtime field contains a number that is greater than zero.
    $this->assertIsNumeric($test_mtime);
    $this->assertGreaterThan(0, $test_mtime);
  }

  /**
   * Returns the ModuleHandler.
   *
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected function moduleHandler() {
    return $this->container->get('module_handler');
  }

  /**
   * Returns the ModuleInstaller.
   *
   * @return \Drupal\Core\Extension\ModuleInstallerInterface
   */
  protected function moduleInstaller() {
    return $this->container->get('module_installer');
  }

}
