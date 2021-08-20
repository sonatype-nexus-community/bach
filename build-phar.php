<?php
$srcRoot = __DIR__;
$buildRoot = dirname(__FILE__) . DIRECTORY_SEPARATOR . "build" . DIRECTORY_SEPARATOR;

$phar = new Phar($buildRoot . "bach.phar", FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, "bach.phar");
$phar->buildFromDirectory(dirname(__FILE__));
$phar->setStub($phar->createDefaultStub("bach"));
