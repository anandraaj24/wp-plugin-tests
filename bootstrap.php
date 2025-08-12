<?php
$_tests_dir = getenv('WP_TESTS_DIR') ?: '/tmp/wordpress-tests-lib';

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    fwrite(STDERR, "WP tests not found at {$_tests_dir}\n");
    exit(1);
}

// Ensure the tests DB exists using the WP tests config.
if ( file_exists( $_tests_dir . '/wp-tests-config.php' ) ) {
    require $_tests_dir . '/wp-tests-config.php';

    $host = DB_HOST;
    $port = null; $socket = null;
    if ( substr(DB_HOST, 0, 1) === '/' ) { $host = 'localhost'; $socket = DB_HOST; }
    elseif ( strpos(DB_HOST, ':') !== false ) { list($host,$port) = explode(':', DB_HOST, 2); $port = (int) $port; }

    $mysqli = mysqli_init();
    if ( $socket ) {
        @mysqli_real_connect( $mysqli, $host, DB_USER, DB_PASSWORD, null, null, $socket );
    } else {
        @mysqli_real_connect( $mysqli, $host, DB_USER, DB_PASSWORD, null, $port ?: 3306 );
    }
    if ( $mysqli ) {
        @mysqli_query( $mysqli, 'CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' );
        mysqli_close( $mysqli );
    }
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

// Load the plugin before the WP test environment boots.
function _manually_load_plugin() {
    require dirname(__DIR__) . '/test-plugin.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Boot the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';