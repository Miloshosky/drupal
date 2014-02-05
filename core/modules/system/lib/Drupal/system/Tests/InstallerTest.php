<?php

/**
 * @file
 * Contains \Drupal\system\Tests\InstallerTest.
 */

namespace Drupal\system\Tests;

use Drupal\Component\Utility\NestedArray;
use Drupal\simpletest\WebTestBase;

/**
 * Allows testing of the interactive installer.
 */
class InstallerTest extends WebTestBase {

  /**
   * Whether the installer has completed.
   *
   * @var bool
   */
  protected $isInstalled = FALSE;

  public static function getInfo() {
    return array(
      'name' => 'Installer tests',
      'description' => 'Tests the interactive installer.',
      'group' => 'Installer',
    );
  }

  protected function setUp() {
    $this->isInstalled = FALSE;



    $settings['conf_path'] = (object) array(
      'value' => $this->public_files_directory,
      'required' => TRUE,
    );
    $settings['config_directories'] = (object) array(
      'value' => array(),
      'required' => TRUE,
    );
    $settings['config']['system.file'] = (object) array(
      'value' => array(
        'path' => array(
          'private' => $this->private_files_directory,
          'temporary' => $this->temp_files_directory,
        ),
      ),
      'required' => TRUE,
    );
    $settings['config']['locale.settings'] = (object) array(
      'value' => array(
        'translation' => array(
          'path' => $this->translation_files_directory,
        ),
      ),
      'required' => TRUE,
    );
    $this->writeSettings($settings);

    $this->drupalGet($GLOBALS['base_url'] . '/core/install.php?langcode=en&profile=minimal');
    $this->drupalPostForm(NULL, array(), 'Save and continue');
    // Reload config directories.
    include $this->public_files_directory . '/settings.php';
    foreach ($config_directories as $type => $path) {
      $GLOBALS['config_directories'][$type] = $path;
    }
    $this->rebuildContainer();

    \Drupal::config('system.file')
      ->set('path.private', $this->private_files_directory)
      ->set('path.temporary', $this->temp_files_directory)
      ->save();
    \Drupal::config('locale.settings')
      ->set('translation.path', $this->translation_files_directory)
      ->save();

    // Use the test mail class instead of the default mail handler class.
    \Drupal::config('system.mail')->set('interface.default', 'Drupal\Core\Mail\TestMailCollector')->save();

    // When running from run-tests.sh we don't get an empty current path which
    // would indicate we're on the home page.
    $path = current_path();
    if (empty($path)) {
      _current_path('run-tests');
    }

    $this->isInstalled = TRUE;
  }

  /**
   * {@inheritdoc}
   *
   * WebTestBase::refreshVariables() tries to operate on persistent storage,
   * which is only available after the installer completed.
   */
  protected function refreshVariables() {
    if ($this->isInstalled) {
      parent::refreshVariables();
    }
  }

  /**
   * {@inheritdoc}
   *
   * This override is necessary because the parent drupalGet() calls t(), which
   * is not available early during installation.
   */
  protected function drupalGet($path, array $options = array(), array $headers = array()) {
    // We are re-using a CURL connection here. If that connection still has
    // certain options set, it might change the GET into a POST. Make sure we
    // clear out previous options.
    $out = $this->curlExec(array(CURLOPT_HTTPGET => TRUE, CURLOPT_URL => $this->getAbsoluteUrl($path), CURLOPT_NOBODY => FALSE, CURLOPT_HTTPHEADER => $headers));
    $this->refreshVariables(); // Ensure that any changes to variables in the other thread are picked up.

    // Replace original page output with new output from redirected page(s).
    if ($new = $this->checkForMetaRefresh()) {
      $out = $new;
    }
    $this->verbose('GET request to: ' . $path .
                   '<hr />Ending URL: ' . $this->getUrl() .
                   '<hr />' . $out);
    return $out;
  }

  /**
   * Ensures that the user page is available after every test installation.
   */
  public function testInstaller() {
    $this->drupalGet('user');
    $this->assertResponse(200);
  }

}
