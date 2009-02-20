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

$coretest = $pstl->load('coretest.xml');
print "CoreTag Test:\n";
$out = $coretest->render();
$a = explode("\n", $out);
for ($i=0; $i<count($a); ++$i) {
  printf("%01d: %s\n", $i, $a[$i]);
}

?>
