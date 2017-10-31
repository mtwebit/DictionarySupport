<?php namespace ProcessWire;
// DEBUG disable file compiler for this file
// FileCompiler=0

/*
 * Admin module for Dictionary - module configuration
 * 
 * Provides rendering functions for the Dictionary module.
 * 
 * Copyright 2017 Tamas Meszaros <mt+github@webit.hu>
 * This file licensed under Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 */

class DictionaryAdminConfig extends ModuleConfig {

  public function getDefaults() {
    return array(
    );
  }

  public function getInputfields() {
    $inputfields = parent::getInputfields();

    $fieldset = $this->wire('modules')->get("InputfieldFieldset");
    $fieldset->label = __("Requirements");

    if (!$this->modules->isInstalled('Dictionary')) {
      $f = $this->modules->get("InputfieldMarkup");
      $this->warning('Dictionary module is missing.');
      $f->value = '<p>Dictionary module is missing. Install it before using this module.</p>';
      $f->columnWidth = 100;
      $fieldset->add($f);
    }

    $inputfields->add($fieldset);

    return $inputfields;
  }
}
