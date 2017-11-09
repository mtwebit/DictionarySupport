<?php namespace ProcessWire;
// DEBUG disable file compiler for this file
// FileCompiler=0

/*
 * Dictionary module
 * 
 * Provides dictionary support for ProcessWire.
 * 
 * Copyright 2017 Tamas Meszaros <mt+github@webit.hu>
 * This file licensed under Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 */
 
class Dictionary extends WireData implements Module {
  // TODO: XML mezők láthatósága szerepkörök szerint
  // TODO: vendégek számára elérhető mintakészlet mérete
  // the base URL of the module's admin page
  public $adminUrl; //  = wire('config')->urls->admin.'page/dict-manage/'
  // file tags
  const TAG_IMPORT='import';  // import headwords from the file
  const TAG_UPDATE='update';  // update already existing headwords
  const TAG_PURGE='purge';    // purge the dictionary before import

/***********************************************************************
 * MODULE SETUP
 **********************************************************************/

  /**
   * Called only when this module is installed
   * 
   * Creates new custom database table for storing import configuration data.
   */
  public function ___install() {
  }


  /**
   * Called only when this module is uninstalled
   * 
   * Drops database table created during installation.
   */
  public function ___uninstall() {
  }


  /**
   * Initialization
   * 
   * This function attaches a hook for page save and decodes module options.
   */
  public function init() {
    // install a conditional hook after page save to import dictionary entries
    // PW 3.0.62 has a bug and needs manual fix for conditional hooks:
    // https://github.com/processwire/processwire-issues/issues/261
    wire()->addHookAfter('Page(template='.$this->dictionary_template.')::changed('.$this->sourcefield.')', $this, 'handleSourceChange');
  }




/***********************************************************************
 * HOOKS
 **********************************************************************/

  /**
   * Hook that creates a dictionary task to process the input files
   * Note: it is called several times when the change occurs.
   */
  public function handleSourceChange(HookEvent $event) {
    // return when we could not detect a real change
    if (! $event->arguments(1) instanceOf Pagefiles) return;
    $dictPage = $event->object;
/*
    $event->message(microtime().' - CHANGED: '.$event->arguments(0), Notice::debug);
    $event->message(microtime().' - FROM: '.print_r($event->arguments(1), true), Notice::debug);
    $event->message(microtime().' - TO: '.print_r($event->arguments(2), true), Notice::debug);
*/    
    // create the necessary tasks and add them to the dictionary after the page is saved.
    $event->addHookAfter("Pages::saveReady($dictPage)",
      function($event) use($dictPage) {
        $event->message('Dictionary source has changed. Creating a job to check the changes.', Notice::debug);
        $this->createTasksOnPageSave($dictPage);
        $event->removeHook(null);
      });
  }





/***********************************************************************
 * TASK MANAGEMENT
 **********************************************************************/

  /**
   * Add all necessary tasks to the dictionary when the dictionary page is saved
   * 
   * @param $dictPage ProcessWire Page object
   */
  public function createTasksOnPageSave($dictPage) {
    // check if any file needs to be handled
    $files = $dictPage->{$this->sourcefield}->find('tags*='.self::TAG_IMPORT.'|'.self::TAG_UPDATE.'|'.self::TAG_PURGE);
    if ($files->count()==0) return;

    // constructing dictionary tasks
    // these could be long running progs so we can't execute them right now
    // Tasker module is here to help
    $tasker = $this->modules->getModule('Tasker');

    $firstTask = $prevTask = NULL;
    $data = array();

    // if purge was requested on any file then purge the dictionary before any import
    foreach ($files as $file) if ($file->hasTag(self::TAG_PURGE)) {
      $purgeTask = $tasker->createTask(__CLASS__, 'purge', $dictPage, 'Purge the dictionary before import', $data);
      if ($purgeTask == NULL) return; // tasker failed to add a task
      $this->message("Created a task to purge {$dictPage->title} before import.", Notice::debug);
      $data['dep'] = $purgeTask->id; // add a dependency to import tasks: first delete old entries
      $firstTask = $purgeTask;
      $prevTask = $purgeTask;
    }

    // create an import task for each input file
    foreach ($files as $name => $file) {
      $data['file'] = $name;
      if ($file->hasTag(self::TAG_IMPORT)) {
        $title = 'Import dictionary headwords from '.$name;
      } elseif ($file->hasTag(self::TAG_UPDATE)) {
        $title = 'Update dictionary headwords from '.$name;
      } else {
        continue; // no import, no update (majdbe purge?) - skip this file
      }
      $task = $tasker->createTask(__CLASS__, 'import', $dictPage, $title, $data);
      if ($task == NULL) return; // tasker failed to add a task
      // add this task as a follow-up to the previous task
      if ($prevTask != NULL) $tasker->addNextTask($prevTask, $task);
      $prevTask = $task;
      if ($firstTask == NULL) $firstTask = $task;
    }

    $tasker->activateTask($firstTask); // activate the first task immediately

    return $firstTask;  // and return it
  }



/***********************************************************************
 * CONTENT MANAGEMENT
 **********************************************************************/

  /**
   * Import a dictionary - a PW task
   * 
   * @param $dictPage ProcessWire Page object (the dictionary)
   * @param $taskData task data assoc array
   * @param $params runtime paramteres, e.g. timeout, dryrun, estimation and task object
   * @returns false on error, a result message on success
   * The method also alters elements of the $taskData array.
   */
  public function import($dictPage, &$taskData, $params) {
    // check if we still have the file...
    $file=$dictPage->{$this->sourcefield}->findOne('name='.$taskData['file'].',tags*='.self::TAG_IMPORT.'|'.self::TAG_UPDATE.'|'.self::TAG_PURGE);
    if ($file==NULL) {
      $this->error("Input file '".$taskData['file']."' has disappeared from {$dictPage->title}.");
      return false; // could not find the file(s)
    }

    // get a reference to the XML processor
    $xmlproc = $this->modules->getModule('DictionaryXmlProcessor');

    // estimate and return the task size if requested
    if (isset($params['estimation'])) {
      // TODO check file type and select the appropriate module
      return $xmlproc->countFileRecords($file);
    }

    // check if this is the first invocation
    if ($taskData['records_processed'] == 0) {
      // run an estimation to count processable records
      $ret = $this->import($dictPage, $taskData, array_merge($params, array('estimation' => true)));
      if (false === $ret) {
        return false;
      }
      // initialize task data
      $taskData['max_records'] = $ret;
      $taskData['records_processed'] = 0;
      $taskData['task_done'] = 0;
      $taskData['progress'] = 0;
      $taskData['offset'] = 0;    // file offset
    }

    if ($taskData['max_records'] == 0) { // empty file?
      $taskData['task_done'] = 1;
      $taskData['progress'] = 100;
      return 'Import is done (input is empty).';
    }

    $this->message("Processing file {$file->name}.", Notice::debug);

    // TODO check file type and select the appropriate module

    // import the dictionary from the file
    $ret = $xmlproc->importFromFile($dictPage, $file, $taskData, $params);

    // check if the import failed
    if ($ret === false) {
      return false;
    }

    // check if the file has been only partially processed (e.g. due to max exec time is reached)
    if ($taskData['offset']!=0) {
      return 'The file was only partially processed.';
    }

    if ($taskData['records_processed'] != $taskData['max_records']) {
      $this->warning('Dictionary import: assertion failed: all files are done but not all records processed. '
        . "Processed: {$taskData['records_processed']} =/= Max: {$taskData['max_records']}");
    }

    // file is ready, report back that task is done
    $taskData['task_done'] = 1;
    $taskData['progress'] = 100;

    return 'Import is done from '.$taskData['file'].'.';
  }


  /**
   * Purge the dictionary removing all its child nodes - a PW task
   * 
   * @param $dictPage ProcessWire Page object (the dictionary)
   * @param $taskData task data assoc array
   * @param $params runtime paramteres, e.g. timeout, estimation and task object
   * @returns false on error, a result message on success
   */
  public function purge($dictPage, &$taskData, $params) {
    // calculate the task's actual size
    $tsize=$this->pages->count('parent='.$dictPage->id.',template='.$this->headword_template.',include=all');

    // return the task size if requested
    if (isset($params['estimation'])) {
      return $tsize;
    }

    if (!$taskData['records_processed']) { // this is the first invocation
      // initialize task data
      $taskData['progress'] = 0;
      $taskData['max_records'] = $tsize;
      $taskData['records_processed'] = 0;
    }

    // check if we have nothing to do
    if ($tsize==0) {
      $taskData['task_done'] = 1;
      $taskData['progress'] = 100;
      return 'Done deleting dictionary entries.';
    }

    $taskData['task_done'] = 1; // we're optimistic that we could finish the task this time

    if (isset($params['task'])) { // check if Tasker is active
      $tasker = wire('modules')->get('Tasker');
      $task = $params['task'];
    } else $tasker = false;

    // store a few headword names to print out
    $count = 0; $deleted = array();

    $children = $this->pages->findMany('parent='.$dictPage->id.',template='.$this->headword_template.',include=all');

    foreach ($children as $child) {
      $taskData['records_processed']++;
      if ($count++ < 10) $deleted[] = $child->title;
//      $child->trash();  // probably not a good idea to fill the trash
      $child->delete(true); // delete children as well
      // if Tasker is active and we've processed 200 records
      if ($tasker && ($count > 200)) {
        // report progress and check for Tasker events
        $taskData['progress'] = round(100 * $taskData['records_processed'] / $taskData['max_records'], 2);
        $this->message('Deleted pages: '.implode(', ', $deleted).' and '.($count-10).' other.');
        $count = 0; $deleted = array();
        if (!$tasker->saveProgress($task, $taskData)) { // returns false if the task is no longer active
          $this->warning('The dictionary is not purged entirely since the task is no longer active.', Notice::debug);
          $taskData['task_done'] = 0;
          break; // the foreach loop
        }
      }
      if ($params['timeout'] && $params['timeout'] <= time()) { // time is over
        $this->warning('The dictionary is not purged entirely since maximum execution time is too close.', Notice::debug);
        $taskData['task_done'] = 0;
        break;  // the while loop
      }
    } // foreach pages to delete

    if ($count > 10) {
      $this->message('Deleted pages: '.implode(', ', $deleted).' and '.($count-10).' other.');
    } else if ($count > 0) {
      $this->message('Deleted pages: '.implode(', ', $deleted).'.');
    }

    if ($taskData['task_done']) {
      $taskData['progress'] = 100;
      return 'Done deleting dictionary entries';
    }

    $taskData['progress'] = round(100 * $taskData['records_processed'] / $taskData['max_records'], 2);

    return 'Purge is not finished.';
  }



  /**
   * Create and save a new Processwire Page and set its fields.
   * 
   * @param $parent the parent node reference
   * @param $template the template of the new page
   * @param $title title for the new page
   * @param $fields assoc array of field name => value pairs to be set
   */
  public function createPage(Page $parent, $template, $title, $fields = array()) {
    if (!is_object($parent) || ($parent instanceof NullPage)) {
      $this->error("Error creating new {$template} named {$title} since its parent does not exists.");
      return NULL;
    }
    // parent page needs to have an ID, get one by saving it
    if (!$parent->id) $parent->save();
    $p = $this->wire(new Page());
    if (!is_object($p)) {
      $this->error("Error creating new page named {$title} from {$template} template.");
      return NULL;
    }
    $p->template = $template;
    $p->parent = $parent;
    $p->title = $title;
    if (count($fields)) foreach ($fields as $field => $value) {
      // if ($p->hasField($field)) $p->$field = $value;
      $p->$field = $value;
    }

// TODO multi-language support for headwords?
/*
    $p->of(false); // turn of output formatting

    //foreach ($this->languages as $lang) {
    $langs = $p->getLanguages();
    if (count($langs)) foreach ($p->getLanguages() as $lang) {
      $p->title->setLanguageValue($lang, $title);
    } else $p->title = $title;

    if (count($fields)) foreach ($fields as $field => $value) {
      // if ($p->hasField($field)) $p->$field = $value;
      if (count($langs)) foreach ($p->getLanguages() as $lang) {
        $p->{$field}->setLanguageValue($lang, $value);
      } else $p->set($field, $value);
    }
*/

    // $this->message("{$parent->title} / {$title} [{$template}] created.", Notice::debug);
    $p->save(); // pages must be saved to be a parent or to be referenced
    return $p;
  }



/***********************************************************************
 * UTILITY METHODS
 **********************************************************************/

}
