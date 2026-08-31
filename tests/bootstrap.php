<?php

/**

* PHPUnit bootstrap file.
*
* @package Wholesale_Ordering
  */

/*

* Load Composer dependencies first.
  */
  require_once dirname( __DIR__ ) . '/vendor/autoload.php';

/*

* Tell the WordPress PHPUnit bootstrap where the test configuration lives.
  */
  if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
  define(
  'WP_TESTS_CONFIG_FILE_PATH',
  dirname( __DIR__ ) . '/wp-tests-config.php'
  );
  }

/*

* Locate the WordPress PHPUnit library installed by Composer.
  */
  $_tests_dir = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
fwrite(
STDERR,
sprintf(
"Could not find %s/includes/functions.php. Run composer install first.\n",
$_tests_dir
)
);

exit( 1 );

}

/*

* Give access to tests_add_filter().
  */
  require_once $_tests_dir . '/includes/functions.php';

/**

* Manually load the plugin being tested.
*
* WordPress will execute this callback during muplugins_loaded.
*
* @return void
  */
  function _manually_load_plugin(): void {
  require dirname( __DIR__ ) . '/wholesale-ordering.php';
  }

tests_add_filter(
'muplugins_loaded',
'_manually_load_plugin'
);

/*

* Start the WordPress testing environment.
  */
  require $_tests_dir . '/includes/bootstrap.php';
