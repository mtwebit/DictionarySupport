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
 
class DictionaryXmlProcessor extends WireData implements Module {
  // array of input XML tag name => canonical name mappings
  private $tagNames;

/***********************************************************************
 * MODULE SETUP
 **********************************************************************/

  /**
   * Called only when this module is installed
   */
  public function ___install() {
  }


  /**
   * Called only when this module is uninstalled
   */
  public function ___uninstall() {
  }


  /**
   * Initialization
   * 
   * This function attaches a hook for page save and decodes module options.
   */
  public function init() {
    $this->tagNames = json_decode(trim($this->tagmappings), true);
    if (!is_array($this->tagNames)) {
      $this->error('Invalid XML name mappings. Check the module\'s configuration.');
      return;
    }
  }


  /**
   * Get custom input XML tagname for $tagname
   * 
   * @param $cname canonical input name
   */
  public function getTagName($cname) {
    return (isset($this->tagNames[$cname]) ? $this->tagNames[$cname] : $cname);
  }


  /**
   * Count XML entries in a file
   * 
   * @param $file filefield entry to process
   * returns false on fatal error, number of records on success
   */
  public function countFileRecords($file) {
    // create a new XML pull parser
    $xml = new \XMLReader();

    // number of records
    $count = 0;

    // open the file
    if (!$xml->open($file->filename)) {
      $this->error("Unable to open {$file->name}.");
      return false;
    }

/* TODO skip validation as we don't specify a DTD atm.
    $xml->setParserProperty(\XMLReader::VALIDATE, false);
    if (!$xml->isValid()) {
      $this->module->error("Invalid XML file {$file->name}.");
      return false;
    }
*/

    // find the first <entry> tag
    while ($xml->read() && $xml->localName != $this->getTagName('entry'));

    while ($xml->next($this->getTagName('entry'))) {
      if ($xml->nodeType != \XMLReader::ELEMENT) continue;
      $count++;
    }

    $xml->close();

    return $count;
  }


  /**
   * Import data from the XML file and add/update child nodes @$page
   * 
   * @param $file filefield entry to process
   * @param $params array of arguments like offset, records_processed
   * returns false on fatal error
   */
  public function importFromFile($dictPage, $file, &$taskData, &$params) {
    // create a new XML pull parser
    $xml = new \XMLReader();

    // open the file
    if (!$xml->open($file->filename)) {
      $this->error("Unable to open {$file->name}.");
      return false;
    }

/* TODO skip validation as we don't specify a DTD atm.
    $xml->setParserProperty(\XMLReader::VALIDATE, false);
    if (!$xml->isValid()) {
      $this->module->error("Invalid XML file {$file->name}.");
      return false;
    }
*/

    // check if Tasker is active and we don't have a timeout
    if (isset($params['task']) && !$params['timeout']) {
      $tasker = wire('modules')->get('Tasker');
      $task = $params['task'];
    } else $tasker = false;

    // count and store a few processed records
    $headwordCounter = 0; $headwords = array();
    // Entry record number from the beginning of the input (offset)
    $entrySerial = 0;

    // find the first <entry> tag
    while ($xml->read() && $xml->localName != $this->getTagName('entry'));

    // check if we need to skip a few records
    if ($taskData['offset'] > 0) {
      $this->message('Skipping '.$taskData['offset'].' entries.', Notice::debug);
      while ($notFinished=$xml->next($this->getTagName('entry'))) {
        // skip the end element
        if ($xml->nodeType != \XMLReader::ELEMENT) continue;
        // skip the specified number of entries
        if (++$entrySerial == $taskData['offset']) break;
      }
      $taskData['offset'] = 0; // clear the old offset, will be set again later on
    } else {
      $notFinished = true;
    }

    if ($notFinished) do {
      // increase the actual offset counter
      $entrySerial++;

      // skip the element if it is empty
      if ($xml->isEmptyElement) continue;

      if ($xml->nodeType != \XMLReader::ELEMENT || $xml->localName != $this->getTagName('entry')) {
        // this should not happen
        $this->error("Internal XML parsing error at {$xml->localName}");
        // skip to the next <entry> tag
        continue;
      }

      $headword = $this->addHeadwordXML($dictPage, $xml->getAttribute('headword'), $xml->readOuterXML(), $file->tags(true));

      if ($headword instanceof Page) {
        // increase the headword counter and store a few headword names
        if ($headwordCounter++ < 10) $headwords[] = $headword->title;
        $taskData['records_processed']++;
      }

      // if we've processed a few records 
      if ($tasker && ($headwordCounter > 200)) {
        // report actual progress and check for Tasker events
        $taskData['progress'] = round(100 * $taskData['records_processed'] / $taskData['max_records'], 2);
        $this->message(implode(',', $headwords).' and '.($headwordCounter-10).' other entries have been imported.');
        $headwordCounter = 0; $headwords = array();
        if (!$tasker->saveProgress($task, $taskData)) { // returns false if the task is no longer active
          $this->warning("Suspending import at offset {$entrySerial} since the import task is no longer active.", Notice::debug);
          $taskData['offset'] = $entrySerial;
          break; // the do-while loop
        }
      }
      // stop processing records before the max_execution_time is reached
      if ($params['timeout'] && $params['timeout'] <= time()) {
        $this->message("Suspending import at offset {$entrySerial} since maximum execution time is over.", Notice::debug);
        // store the new offset
        $taskData['offset'] = $entrySerial;
        break; // the do-while loop
      }
    } while ($xml->next($this->getTagName('entry')));

    // close the XML input
    $xml->close();

    // print out some info for the user
    if ($headwordCounter > 10) {
      $this->message(implode(',', $headwords).' and '.($headwordCounter-10).' other entries have been imported.');
    } else if ($headwordCounter > 1) {
      $this->message(implode(',', $headwords).' have been imported.');
    } else if ($headwordCounter == 1) {
      $this->message(implode(',', $headwords).' has been imported.');
    }

    return true;
  }


  /**
   * Create a headword page from an XML source and store its XML data in a field named xml_data
   * 
   * TODO process the XML data and store it in other format?
   * 
   * @param $dictPage that stores the headword (parent Page)
   * @param $headword unique string name for the headword
   * @param $xml_data headword data in XML form
   * @param $tags command options: IMPORT, UPDATE, DELETE old version first (coming from file tags)
   * returns PW Page object that has been added/updated, NULL otherwise
   */
  public function addHeadwordXML($dictPage, $headword, $xml_data, $tags = array()) {
    if (strlen($xml_data)<10) {
      $this->error("Invalid headword data found for '{$headword}' in the input.");
      return NULL;
    }

    // check and normalize the headword
    if (strlen($headword)<1) {
      $this->error("Invalid headword '{$headword}' found in the input.");
      return NULL;
    }
    if (false !== strpos($headword, '&')) { // entities present...
      $headword = html_entity_decode($headword, 0,
                  isset(wire('config')->dbCharset) ? isset(wire('config')->dbCharset) : '');
    }

    // find headwords already present in the dictionary
    $template = $this->modules->Dictionary->headword_template;
    $selector = 'title='.$this->sanitizer->selectorValue($headword)
               .', template='.$template.', include=all';
    $hwp=$dictPage->child($selector);

    if ($hwp->id) { // found a page with the same title
      if (isset($tags[Dictionary::TAG_UPDATE])) { // update the existing headword
        // $this->message("Updating headword '{$headword}' page '{$hwp->title}'[{$hwp->id}]", Notice::debug);
        $hwp->xml_data = $xml_data;
        $hwp->save('xml_data');
        return $hwp;
      } else {
        // $this->message("Skipping already existing headword {$headword}.", Notice::debug);
        return NULL;
      }
    }

    if (isset($tags[Dictionary::TAG_IMPORT])) {
      return $this->modules->Dictionary->createPage($dictPage, $template, $headword, array('xml_data' => $xml_data));
    } else {
      return NULL;
    }
  }
}
