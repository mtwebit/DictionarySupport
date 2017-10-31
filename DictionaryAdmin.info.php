<?php namespace ProcessWire;
// DEBUG disable file compiler for this file
// FileCompiler=0

/*
 * Admin module for Dictionary - module information
 * 
 * Provides rendering functions for the Dictionary module.
 * 
 * Copyright 2017 Tamas Meszaros <mt+github@webit.hu>
 * This file licensed under Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 */

$info = array(
  'title' => 'Dictionary Administration',
  'version' => '0.3.1', // semver.org
  'summary' => 'The module provides administrative functions for dictionaries.',
  'href' => 'https://github.com/mtwebit/DictionarySupport',
  'singular' => true,
  'autoload' => false,
  'icon' => 'link', // fontawesome icon
  'page' => array( // we create an admin page for this module
    'name' => 'dicts',
    'parent' => '/admin/page/',
    'title' => 'Dictionary management',
    'template' => 'admin'
  ),
);
