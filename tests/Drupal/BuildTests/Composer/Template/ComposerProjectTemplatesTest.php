<?php

namespace Drupal\BuildTests\Composer\Template;

use Composer\Json\JsonFile;
use Drupal\BuildTests\Framework\BuildTestBase;
use Drupal\Composer\Composer;

/**
 * Demonstrate that Composer project templates are buildable as patched.
 *
 * We have to use the packages.json fixture so that Composer will use the
 * in-codebase version of the project template.
 *
 * We also have to add path repositories to the in-codebase project template or
 * else Composer will try to use packagist to resolve dependencies we'd prefer
 * it to find locally.
 *
 * This is because Composer only uses the packages.json file to resolve the
 * project template and not any other dependencies.
 *
 * @group #slow
 * @group Template
 *
 * @requires externalCommand composer
 */
class ComposerProjectTemplatesTest extends BuildTestBase {

  /**
   * Get Composer items that we want to be path repos, from within a directory.
   *
   * @param string $workspace_directory
   *   The full path to the workspace directory.
   * @param string $subdir
   *   The subdirectory to search under composer/.
   *
   * @return string[]
   *   Array of paths, indexed by package name.
   */
  public function getPathReposForType($workspace_directory, $subdir) {
    // Find the Composer items that we want to be path repos.
    $path_repos = Composer::composerSubprojectPaths($workspace_directory, $subdir);

    $data = [];
    /* @var $path_repo \SplFileInfo */
    foreach ($path_repos as $path_repo) {
      $json_file = new JsonFile($path_repo->getPathname());
      $json = $json_file->read();
      $data[$json['name']] = $path_repo->getPath();
    }
    return $data;
  }

  public function provideTemplateCreateProject() {
    return [
      'recommended-project' => [
        'drupal/recommended-project',
        'composer/Template/RecommendedProject',
        '/web',
      ],
      'legacy-project' => [
        'drupal/legacy-project',
        'composer/Template/LegacyProject',
        '',
      ],
    ];
  }

  /**
   * Make sure we've accounted for all the templates.
   */
  public function testVerifyTemplateTestProviderIsAccurate() {
    $root = $this->getDrupalRoot();
    $data = $this->provideTemplateCreateProject($root);

    // Find all the templates.
    $template_files = Composer::composerSubprojectPaths($root, 'Template');

    $this->assertSame(count($template_files), count($data));

    // We could have the same number of templates but different names.
    $template_data = [];
    foreach ($data as $data_name => $data_value) {
      $template_data[$data_value[0]] = $data_name;
    }
    /* @var $file \SplFileInfo */
    foreach ($template_files as $file) {
      $json_file = new JsonFile($file->getPathname());
      $json = $json_file->read();
      $this->assertArrayHasKey('name', $json);
      // Assert that the template name is in the project created
      // from the template.
      $this->assertArrayHasKey($json['name'], $template_data);
    }
  }

  /**
   * @dataProvider provideTemplateCreateProject
   */
  public function testTemplateCreateProject($project, $package_dir, $docroot_dir) {
    // Make a working COMPOSER_HOME directory for setting global composer config
    $composer_home = $this->getWorkspaceDirectory() . '/composer-home';
    mkdir($composer_home);
    // Create an empty global composer.json file, just to avoid warnings.
    file_put_contents("$composer_home/composer.json", '{}');

    // Disable packagist globally (but only in our own custom COMPOSER_HOME).
    // It is necessary to do this globally rather than in our SUT composer.json
    // in order to ensure that Packagist is disabled during the
    // `composer create-project` command.
    $this->executeCommand("COMPOSER_HOME=$composer_home composer config --no-interaction --global repo.packagist false");
    $this->assertCommandSuccessful();

    // Get the Drupal core version branch. For instance, this should be
    // 8.9.x-dev for the 8.9.x branch.
    $core_version = Composer::drupalVersionBranch();

    // In order to use create-project on our template, we must have stable
    // versions of drupal/core and our other SUT repositories. Since we have
    // provided these as path repositories, they will take on the version of
    // the root project. We'll make a simulated version number that is stable
    // to fulfill this role.
    $simulated_stable_version = str_replace('.x-dev', '.99', $core_version);

    // Create a "Composer"-type repository containing one entry for every
    // package in the vendor directory.
    $vendor_packages_path = $this->getWorkspaceDirectory() . '/vendor_packages/packages.json';
    $this->makeVendorPackage($vendor_packages_path);

    // Make a copy of the code to alter in the workspace directory.
    $this->copyCodebase();

    // Set the Drupal version and minimum stability of the template projects
    Composer::setDrupalVersion($this->getWorkspaceDirectory(), $simulated_stable_version);
    $this->assertDrupalVersion($simulated_stable_version, $this->getWorkspaceDirectory());

    // Remove the packages.drupal.org entry (and any other custom repository)
    // from the SUT's repositories section. There is no way to do this via
    // `composer config --unset`, so we read and rewrite composer.json.
    $composer_json_path = $this->getWorkspaceDirectory() . "/$package_dir/composer.json";
    $composer_json = json_decode(file_get_contents($composer_json_path), TRUE);
    unset($composer_json['repositories']);
    $json = json_encode($composer_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    file_put_contents($composer_json_path, $json);

    // Set up the template to use our path repos. Inclusion of metapackages is
    // reported differently, so we load up a separate set for them.
    $metapackage_path_repos = $this->getPathReposForType($this->getWorkspaceDirectory(), 'Metapackage');
    $this->assertArrayHasKey('drupal/core-recommended', $metapackage_path_repos);
    $path_repos = array_merge($metapackage_path_repos, $this->getPathReposForType($this->getWorkspaceDirectory(), 'Plugin'));
    // Always add drupal/core as a path repo.
    $path_repos['drupal/core'] = $this->getWorkspaceDirectory() . '/core';
    foreach ($path_repos as $name => $path) {
      $this->executeCommand("composer config --no-interaction repositories.$name path $path", $package_dir);
      $this->assertCommandSuccessful();
    }
    // Fix up drupal/core-recommended so that it requires a stable version
    // of drupal/core rather than a dev version.
    $core_recommended_dir = 'composer/Metapackage/CoreRecommended';
    $this->executeCommand("composer remove --no-interaction drupal/core --no-update", $core_recommended_dir);
    $this->assertCommandSuccessful();
    $this->executeCommand("composer require --no-interaction drupal/core:^$simulated_stable_version --no-update", $core_recommended_dir);
    $this->assertCommandSuccessful();

    // Add our vendor package repository to our SUT's repositories section.
    // Call it "local" (although the name does not matter).
    $this->executeCommand("composer config --no-interaction repositories.local composer file://" . $vendor_packages_path, $package_dir);
    $this->assertCommandSuccessful();

    $this->executeCommand("composer config --no-interaction repositories.local composer file://" . $vendor_packages_path, $package_dir);
    $this->assertCommandSuccessful();

    $repository_path = $this->getWorkspaceDirectory() . '/test_repository/packages.json';
    $this->makeTestPackage($repository_path, $simulated_stable_version);

    $installed_composer_json = $this->getWorkspaceDirectory() . '/testproject/composer.json';
    $autoloader = $this->getWorkspaceDirectory() . '/testproject' . $docroot_dir . '/autoload.php';
    $this->assertFileNotExists($autoloader);

    // At the moment, we are only testing stable versions. If we used a
    // non-stable version instead of $simulated_stable_version, then we would
    // also need to pass the --stability flag to composer create-project.
    $this->executeCommand("COMPOSER_HOME=$composer_home COMPOSER_ROOT_VERSION=$simulated_stable_version composer create-project --no-ansi $project testproject -vvv --repository $repository_path");
    $this->assertCommandSuccessful();

    // Ensure we used the project from our codebase.
    $this->assertErrorOutputContains("Installing $project ($simulated_stable_version): Symlinking from $package_dir");
    // Ensure that we used drupal/core from our codebase. This probably means
    // that drupal/core-recommended was added successfully by the project.
    $this->assertErrorOutputContains("Installing drupal/core ($simulated_stable_version): Symlinking from");
    // Verify that there is an autoloader. This is written by the scaffold
    // plugin, so its existence assures us that scaffolding happened.
    $this->assertFileExists($autoloader);

    // Verify that the minimum stability in the installed composer.json file
    // is 'stable'
    $this->assertFileExists($installed_composer_json);
    $composer_json_contents = file_get_contents($installed_composer_json);
    $this->assertStringContainsString('"minimum-stability": "stable"', $composer_json_contents);

    // In order to verify that Composer used the path repos for our project, we
    // have to get the requirements from the project composer.json so we can
    // reconcile our expectations.
    $template_json_file = $this->getWorkspaceDirectory() . '/' . $package_dir . '/composer.json';
    $this->assertFileExists($template_json_file);
    $json_file = new JsonFile($template_json_file);
    $template_json = $json_file->read();
    // Get the require and require-dev information, and ensure that our
    // requirements are not erroneously empty.
    $this->assertNotEmpty(
      $require = array_merge($template_json['require'] ?? [], $template_json['require-dev'] ?? [])
    );
    // Verify that path repo packages were installed.
    $path_repos = array_keys($path_repos);
    foreach (array_keys($require) as $package_name) {
      if (in_array($package_name, $path_repos)) {
        // Metapackages do not report that they were installed as symlinks, but
        // we still must check that their installed version matches
        // COMPOSER_CORE_VERSION.
        if (array_key_exists($package_name, $metapackage_path_repos)) {
          $this->assertErrorOutputContains("Installing $package_name ($simulated_stable_version)");
        }
        else {
          $this->assertErrorOutputContains("Installing $package_name ($simulated_stable_version): Symlinking from");
        }
      }
    }
  }

  /**
   * Assert that the VERSION constant in Drupal.php is the expected value.
   *
   * @param string $expectedVersion
   * @param string $dir
   */
  protected function assertDrupalVersion($expectedVersion, $dir) {
    $drupal_php_path = $dir . '/core/lib/Drupal.php';
    $this->assertFileExists($drupal_php_path);

    // Read back the Drupal version that was set and assert it matches expectations.
    $this->executeCommand("php -r 'include \"$drupal_php_path\"; print \Drupal::VERSION;'");
    $this->assertCommandSuccessful();
    $this->assertCommandOutputContains($expectedVersion);
  }

  /**
   * Creates a test package that points to the templates.
   *
   * @param string $repository_path
   *   The path where to create the test package.
   * @param string $version
   *   The version under test.
   */
  protected function makeTestPackage($repository_path, $version) {
    $json = <<<JSON
{
  "packages": {
    "drupal/recommended-project": {
      "$version": {
        "name": "drupal/recommended-project",
        "dist": {
          "type": "path",
          "url": "composer/Template/RecommendedProject"
        },
        "type": "project",
        "version": "$version"
      }
    },
    "drupal/legacy-project": {
      "$version": {
        "name": "drupal/legacy-project",
        "dist": {
          "type": "path",
          "url": "composer/Template/LegacyProject"
        },
        "type": "project",
        "version": "$version"
      }
    }
  }
}
JSON;
    mkdir(dirname($repository_path));
    file_put_contents($repository_path, $json);
  }

  /**
   * Creates a test package that points to all the projects in vendor.
   *
   * @param string $repository_path
   *   The path where to create the test package.
   */
  protected function makeVendorPackage($repository_path) {
    $root = $this->getDrupalRoot();
    $process = $this->executeCommand("composer --working-dir=$root info --format=json");
    $this->assertCommandSuccessful();
    $installed = json_decode($process->getOutput(), TRUE);

    // Build out package definitions for everything installed in
    // the vendor directory.
    $packages = [];
    foreach ($installed['installed'] as $project) {
      $name = $project['name'];
      $version = $project['version'];
      $path = "vendor/$name";
      $full_path = "$root/$path";
      // We are building a set of path repositories to projects in the vendor
      // directory, so we will skip any project that does not exist in vendor.
      // Also skip the projects that are symlinked in vendor. These are in our
      // metapackage. They will be represented as path repositories in the test
      // project's composer.json.
      if (is_dir($full_path) && !is_link($full_path)) {
        $packages['packages'][$name] = [
          $version => [
            "name" => $name,
            "dist" => [
              "type" => "path",
              "url" => $full_path,
            ],
            "version" => $version,
          ],
        ];
      }
    }

    $json = json_encode($packages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    mkdir(dirname($repository_path));
    file_put_contents($repository_path, $json);
  }

}
