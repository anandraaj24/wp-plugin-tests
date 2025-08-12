## WordPress Plugin Unit Testing (Plugin-only, self-contained)

First copy the wordpress-develop tests folder in the public folder i.e., sibling to wp-content

This guide shows how to set up and run PHPUnit tests for your plugin entirely inside `wp-content/plugins/test-plugin/tests`.

### 1) Folder layout

wp-content/plugins/test-plugin/
├── test-plugin.php
└── tests/
├── bootstrap.php
├── phpunit.xml.dist
├── composer.json
├── .gitignore
├── test-smoke.php
├── test-hooks.php
├── test-shortcode.php
├── test-cpt.php
└── test-rest.php



### 2) Dependencies

`tests/composer.json`
```json
{
  "require-dev": {
    "yoast/phpunit-polyfills": "^4.0",
    "phpunit/phpunit": "^9.0"
  },
  "scripts": {
    "test": "phpunit",
    "test-coverage": "XDEBUG_MODE=coverage phpunit"
  }
}
```

Install:
```bash
cd wp-content/plugins/test-plugin/tests
composer install
```

### 3) PHPUnit config

Update `WP_TESTS_DIR` to your site’s core tests path.

`tests/phpunit.xml.dist`
```xml
<?xml version="1.0"?>
<phpunit bootstrap="bootstrap.php" colors="true" stopOnFailure="false" verbose="true">
  <php>
    <env name="WP_TESTS_DIR" value="/Users/anand346/Local Sites/testing-2/app/public/tests/phpunit" />
  </php>

  <testsuites>
    <testsuite name="Plugin">
      <directory prefix="test-" suffix=".php">./</directory>
    </testsuite>
  </testsuites>

  <logging>
    <log type="coverage-html" target="coverage/"/>
  </logging>
</phpunit>
```

Optional:
```bash
./vendor/bin/phpunit --migrate-configuration
```

### 4) Bootstrap (DB ensure + load plugin)

`tests/bootstrap.php`
```php
<?php
$_tests_dir = getenv('WP_TESTS_DIR') ?: '/tmp/wordpress-tests-lib';

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
  fwrite(STDERR, "WP tests not found at {$_tests_dir}\n");
  exit(1);
}

// Ensure test DB exists based on wp-tests-config.php.
if ( file_exists( $_tests_dir . '/wp-tests-config.php' ) ) {
  require $_tests_dir . '/wp-tests-config.php';
  $host = DB_HOST; $port = null; $socket = null;
  if ( substr(DB_HOST,0,1) === '/' ) { $host='localhost'; $socket=DB_HOST; }
  elseif ( strpos(DB_HOST,':') !== false ) { list($host,$port)=explode(':',DB_HOST,2); $port=(int)$port; }
  $mysqli = mysqli_init();
  if ( $socket ) { @mysqli_real_connect($mysqli,$host,DB_USER,DB_PASSWORD,null,null,$socket); }
  else { @mysqli_real_connect($mysqli,$host,DB_USER,DB_PASSWORD,null,$port ?: 3306); }
  if ( $mysqli ) { @mysqli_query($mysqli,'CREATE DATABASE IF NOT EXISTS `'.DB_NAME.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); mysqli_close($mysqli); }
}

// If WordPress tests don't exist, download and install them
if (!file_exists($_tests_dir . '/includes/functions.php')) {
    echo "WordPress test library not found. Installing...\n";
    
    // Create temporary directory
    $tmp_dir = sys_get_temp_dir() . '/wordpress-tests-lib-' . uniqid();
    mkdir($tmp_dir);
    
    // Download WordPress test library
    $wp_tests_url = 'https://github.com/WordPress/wordpress-develop/archive/refs/heads/trunk.zip';
    $zip_file = $tmp_dir . '/wp-tests.zip';
    
    if (function_exists('curl_init')) {
        $ch = curl_init($wp_tests_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $content = curl_exec($ch);
        curl_close($ch);
        file_put_contents($zip_file, $content);
    }
    
    // Extract and move tests
    $zip = new ZipArchive;
    if ($zip->open($zip_file) === TRUE) {
        $zip->extractTo($tmp_dir);
        $zip->close();
        
        // Move tests to expected location
        rename($tmp_dir . '/wordpress-develop-trunk/tests/phpunit', $_tests_dir);
        
        // Cleanup
        unlink($zip_file);
        rmdir($tmp_dir);
    }
    
    echo "WordPress test library installed to: {$_tests_dir}\n";
}

require_once $_tests_dir . '/includes/functions.php';

// Load the plugin before WP test env boots.
function _manually_load_plugin() {
  require dirname(__DIR__) . '/test-plugin.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Boot WP test env.
require $_tests_dir . '/includes/bootstrap.php';
```

`.gitignore`
```gitignore
vendor/
coverage/
.phpunit.result.cache
```

### 5) Example tests

A) Smoke
`tests/test-smoke.php`
```php
<?php
class Test_Plugin_Smoke extends WP_UnitTestCase {
  public function test_wp_boots() { $this->assertTrue( defined('ABSPATH') ); }

  public function test_plugin_header() {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $data = get_plugin_data( dirname(__DIR__).'/test-plugin.php', false, false );
    $this->assertSame( 'Test Plugin', $data['Name'] );
  }
}
```

B) Hooks/filters
`tests/test-hooks.php`
```php
<?php
class Test_Plugin_Hooks extends WP_UnitTestCase {
  public function test_filter_applies() {
    add_filter('test_plugin_text', fn($s) => $s.'!');
    $this->assertSame('hello!', apply_filters('test_plugin_text','hello'));
  }
}
```

C) Shortcodes (if you add one later)
`tests/test-shortcode.php`
```php
<?php
class Test_Plugin_Shortcode extends WP_UnitTestCase {
  public function test_shortcode_renders() {
    add_shortcode('hello', fn()=>'world');
    $this->assertSame('world', do_shortcode('[hello]'));
  }
}
```

D) Custom Post Type (if your plugin registers it)
`tests/test-cpt.php`
```php
<?php
class Test_Plugin_CPT extends WP_UnitTestCase {
  public function test_cpt_registered() {
    $this->assertTrue( post_type_exists('my_cpt') ); // adjust slug
    $obj = get_post_type_object('my_cpt');
    $this->assertNotNull($obj);
  }
}
```

E) REST (if you add routes)
`tests/test-rest.php`
```php
<?php
class Test_Plugin_REST extends WP_UnitTestCase {
  public function test_route_registered() {
    global $wp_rest_server; $wp_rest_server = new WP_REST_Server();
    do_action('rest_api_init');
    $routes = $wp_rest_server->get_routes();
    $this->assertArrayHasKey('/test-plugin/v1/ping', $routes); // adjust route
  }
}
```

### 6) Run tests

- Ensure DB exists (one-time):
```bash
mysql -h 127.0.0.1 -P 10078 -u root -proot \
  -e "CREATE DATABASE IF NOT EXISTS wp_tests DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

- Run from plugin tests dir:
```bash
cd "/Users/anand346/Local Sites/testing-2/app/public/wp-content/plugins/test-plugin/tests"
./vendor/bin/phpunit
```

- With coverage:
```bash
XDEBUG_MODE=coverage ./vendor/bin/phpunit
open "./coverage/index.html"
```

- Filter:
```bash
./vendor/bin/phpunit --filter Test_Plugin_Smoke
./vendor/bin/phpunit --filter test_plugin_header
```

### 7) Tips

- Keep everything inside `tests/`; don’t modify project root.
- If XML schema warning appears, run `./vendor/bin/phpunit --migrate-configuration`.
- For multisite: `WP_MULTISITE=1 ./vendor/bin/phpunit`.
- Add real tests as you add features (CPTs, REST, shortcodes, settings).


### Do I always need to include core files when testing?

No. The WordPress test bootstrap loads most core APIs. Only include specific admin files or trigger hooks when you use features not loaded by default.

### When to include admin-only helpers

- get_plugin_data, is_plugin_active, activate_plugin:
```php
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
```
- WP_Filesystem, download_url, get_home_path:
```php
    require_once ABSPATH . 'wp-admin/includes/file.php';
```
- Media/image helpers (media_handle_sideload, wp_generate_attachment_metadata):
```php
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
```
### When to trigger hooks (to register things)

- Customizer:
```php
    require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
    $wp_customize = new WP_Customize_Manager();
    do_action('customize_register', $wp_customize);
```
- REST routes:
```php
    global $wp_rest_server;
    $wp_rest_server = new WP_REST_Server();
    do_action('rest_api_init');
```
- Widgets/sidebars:
```php
    do_action('widgets_init');
```
- Rewrite rules:
```php
    global $wp_rewrite;
    $wp_rewrite->init();
    flush_rewrite_rules(false);
```
- Cron events:
```php
    require_once ABSPATH . 'wp-includes/cron.php';
    // then wp_schedule_event(), do_action('wp_scheduled_event'), etc.
```
### Usually no include needed for

- Options API: get_option, update_option
- Posts/users/terms via factories:
```php
    $post_id = self::factory()->post->create();
    $user_id = self::factory()->user->create();
    $term_id = self::factory()->term->create(['taxonomy' => 'category']);
```
### Rule of thumb

- If you see “undefined function,” include the specific admin file for that function.
- If something isn’t registered (Customizer sections, REST routes, sidebars), fire the corresponding action before asserting.


