#!/usr/bin/php
<?php

require "PHPSTLTemplate.php";

$t = new PHPSTLTemplate(dirname(__FILE__).'/test.xml');

print $t->render();

?>
