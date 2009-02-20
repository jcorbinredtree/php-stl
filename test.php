#!/usr/bin/php
<?php

require "PHPSTL.php";

$pstl = new PHPSTL(array(
  'include_path' => array('.'),
  'compile_caching' => false,
  'diskcache_directory' => 'template-test-cache',
  'diskcache_hashed' => false,
  'always_compile' => true
));


// $t->getPHPSTLCompiler()->setCaching(false);
// TODO test abs

$t = $pstl->load('test.xml');
print $t->render();

?>
