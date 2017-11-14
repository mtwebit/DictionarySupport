<?php namespace ProcessWire;
// DEBUG disable file compiler for this file
// FileCompiler=0

/*
 * Dictionary module - configuration
 * 
 * Provides dictionary support for ProcessWire.
 * 
 * Copyright 2017 Tamas Meszaros <mt+git@webit.hu>
 * This file licensed under Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 */

class DictionaryConfig extends ModuleConfig {

  public function getDefaults() {
    return array(
      'sourcefield' => 'sourcefield',
      'dictionary_template' => 'dictionary',
      'headword_template' => 'headword',
//      'variant_template' => 'variant',
//      'wordform_template' => 'wordform',
  // canonical name : XML tagname mappings
    );
  }

  public function getInputfields() {
    $inputfields = parent::getInputfields();

    $fieldset = $this->wire('modules')->get("InputfieldFieldset");
    $fieldset->label = __("Requirements");

    if (!$this->modules->isInstalled("Tasker")) {
      $f = $this->modules->get("InputfieldMarkup");
      $this->message("Tasker module is missing.", Notice::warning);
      $f->value = '<p>Tasker module is missing. Install it before using this module.</p>';
      $f->columnWidth = 50;
      $fieldset->add($f);
    }

    $inputfields->add($fieldset);

/********************  Basic settings *********************************/
    $fieldset = $this->wire('modules')->get("InputfieldFieldset");
    $fieldset->label = __("Module information and usage tips");
    $fieldset->collapsed = InputfieldFieldset::collapsedYes;

    $f = $this->modules->get("InputfieldMarkup");
    $f->label = "About the module";
    $f->columnWidth = 50;
    $f->value = "<p>
This module provides various support functions to import, edit and display dictionaries.<br />
It has been created to handle author's dictionaries in the field of Digital Humanities.<br />
For more information check the module's home page.
</p>
<p>
Quick howto:<br />
- Create field types.<br />
- Create templates and turn off multi-language support for headwords.<br />
- Configure the module.<br />
- Create a dictionary page, upload a file and assign the import tag to it.<br />
- Click on save.<br />
</p>";
    $fieldset->add($f);

    $f = $this->modules->get("InputfieldMarkup");
    $f->label = "Tips";
    $f->columnWidth = 50;
    $f->value = "<ul>
<li>Large dictionaries take considerable time to import.
The current PHP max_execution_time is ".ini_get('max_execution_time')." second.
The module will try to stop its actions before this time is passed and continue the import process in the background.
Please note that long running tasks are also affected by the Web server's time limits (e.g. fastcgi_read_timeout).</li>
<li>It is possible to import large dictionaries in smaller chunks. You can split the original file by e.g. letters.</li>
<li>If you're using characters other that ascii then you might run into collation issues while searching.
Fix these by changing the default utf8_general_ci MySQL collation to utf8_unicode_ci.
This slightly affects search performance.<br />
See also PW extended page names support and db charset settings.</li>
<li></li>
</ul>";
    $fieldset->add($f);

    $inputfields->add($fieldset);

/********************  Field name settings ****************************/
    $fieldset = $this->wire('modules')->get("InputfieldFieldset");
    $fieldset->label = __("Field setup");

    $f = $this->modules->get('InputfieldSelect');
    $f->attr('name', 'sourcefield');
    $f->label = 'File field to process';
    $f->description = __('The file field that contains the source of the dictionary. The field should support the use of "import", "delete" and "update" tags.');
    $f->options = array();
    $f->required = true;
    $f->columnWidth = 50;
    foreach ($this->wire('fields') as $field) {
      if (!$field->type instanceof FieldtypeFile) continue;
      $f->addOption($field->name, $field->label);
    }
    $fieldset->add($f);

    $inputfields->add($fieldset);

/********************  Template settings ******************************/
    $fieldset = $this->wire('modules')->get("InputfieldFieldset");
    $fieldset->label = __("Template setup");

    $f = $this->modules->get('InputfieldSelect');
    $f->attr('name', 'dictionary_template');
    $f->label = 'Dictionary template';
    $f->description = __('This is the root element of the dictionary. It should contain a filesource field.');
    $f->options = array();
    $f->required = true;
    $f->columnWidth = 50;
    foreach($this->wire('templates') as $template) {
      foreach($template->fields as $field) {
        if ($field->type instanceof FieldtypeFile) {
          $f->addOption($template->name, $template->name);
        }
      }
    }
    $fieldset->add($f);

    $f = $this->modules->get('InputfieldSelect');
    $f->attr('name', 'headword_template');
    $f->label = 'Headword template';
    $f->description = __('This holds a headword. It should contain a TextArea field that stores the headword data.');
    $f->options = array();
    $f->required = true;
    $f->columnWidth = 50;
    foreach($this->wire('templates') as $template) {
      foreach($template->fields as $field) {
        if ($field->type instanceof FieldtypeFile) {
          $f->addOption($template->name, $template->name);
        }
      }
    }
    $fieldset->add($f);

/* not used atm
    $f = $this->modules->get('InputfieldSelect');
    $f->attr('name', 'variant_template');
    $f->label = 'Template that stores headword variants (by author / epoch / region etc.).';
    $f->description = __('The template should have a Page Reference field named "wordforms".');
    $f->options = array();
    $f->required = false;
    $f->columnWidth = 50;
    foreach($this->wire('templates') as $template) {
      if ($template->hasField('wordforms')) { // TODO check for a better field
        $f->addOption($template->name, $template->name);
      }
    }
    $fieldset->add($f);

    $f = $this->modules->get('InputfieldSelect');
    $f->attr('name', 'wordform_template');
    $f->label = 'Template that stores a wordform (writing form of a headword variant).';
    $f->description = __('The template should have a Textarea field named "wordform_examples".');
    $f->options = array();
    $f->required = false;
    $f->columnWidth = 50;
    foreach($this->wire('templates') as $template) {
      if ($template->hasField('wordform_examples')) { // TODO check for better field
        $f->addOption($template->name, $template->name);
      }
    }
    $fieldset->add($f);
*/

    $inputfields->add($fieldset);

    return $inputfields;
  }
}
