<?php namespace ProcessWire;
// DEBUG disable file compiler for this file
// FileCompiler=0

/*
 * Dictionary XML import module
 * 
 * Provides XML import functions for the Dictionary module.
 * 
 * Copyright 2017 Tamas Meszaros <mt+github@webit.hu>
 * This file licensed under Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 */

class DictionaryXmlProcessorConfig extends ModuleConfig {

  public function getDefaults() {
    return array(
  // canonical name : XML tagname mappings
      'tagmappings' => '{
  "entry":"entry",
  "headword":"U",
  "related":"R",
  "comment":"K",
  "meaning":"J",
  "variant":"Q",
  "wordform":"B",
  "wordform_example":"I"
}',
    );
  }

  public function getInputfields() {
    $inputfields = parent::getInputfields();

    $fieldset = $this->wire('modules')->get("InputfieldFieldset");
    $fieldset->label = __("Requirements");

    // check required module
    $f = $this->modules->get('InputfieldMarkup');
    $f->label = 'Dictionary module';
    $f->columnWidth = 50;
    if (!$this->modules->isInstalled('Dictionary')) {
      $f->value = '<p>The module is missing. Install it before using this module.</p>';
    } else {
      $f->value = '<p>The module is installed.</p>';
    }
    $fieldset->add($f);

    $inputfields->add($fieldset);

/********************  Input mapping settings *************************/
    $fieldset = $this->wire('modules')->get("InputfieldFieldset");
    $fieldset->label = __("Input XML mapping setup");

    $f = $this->modules->get('InputfieldTextarea');
    $f->attr('name', 'tagmappings');
    $f->label = __('Canonical name <-> XML tagname mappings in JSON.');
    $f->description = __('Canonical names: headword, variant, wordform[, wordform_example, comment, related, meaning]');
    $f->stripTags = true;
    $f->useLanguages = false;
    $f->columnWidth = 50;
    $fieldset->add($f);
    
    $inputfields->add($fieldset);

    return $inputfields;
  }
}
