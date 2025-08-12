<?php
class Test_Plugin_Smoke extends WP_UnitTestCase {
    public function test_wp_boots() {
        $this->assertTrue( defined('ABSPATH') );
    }

    public function test_plugin_header_parses() {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $file = dirname(__DIR__) . '/test-plugin.php';
        $data = get_plugin_data( $file, false, false );
        $this->assertSame( 'Test Plugin', $data['Name'] );
    }
}