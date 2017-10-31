<?php namespace ProcessWire;
// DEBUG disable file compiler for this file
// FileCompiler=0

/*
 * Admin module for Dictionary
 * 
 * Provides rendering functions for the Dictionary module.
 * 
 * Copyright 2017 Tamas Meszaros <mt+github@webit.hu>
 * This file licensed under Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 */
 
class DictionaryAdmin extends Process implements Module {
  // the base URL of the module's admin page
  public $adminUrl;
  // internal commands
  const CMD_PURGE='purge';
  const CMD_IMPORT='import';

/***********************************************************************
 * MODULE SETUP
 **********************************************************************/

  /**
   * Called only when this module is installed
   * 
   * Creates the admin page.
   */
  public function ___install() {
    parent::___install(); // parent creates the admin page
  }

  /**
   * Called only when this module is uninstalled
   * 
   * Removes the admin page.
   */
  public function ___uninstall() {
    parent::___uninstall(); // parent deletes the admin page
  }

  /**
   * Initialization
   * 
   * This function attaches a hook for page save and decodes module options.
   */
  public function init() {
    parent::init();
    if (!$this->modules->isInstalled("Dictionary")) {
      $this->error('Dictionary module is missing.');
    }
    // set admin URL
    $this->adminUrl = wire('config')->urls->admin.'page/dicts/';
  }

/***********************************************************************
 * Process module endpoints
 * Module routing:
 *     admin page - loc: /?id=$taskId&cmd=$command - execute() - display dictionaries or execute a command
 *     JSON API   - loc: /api/?id=$dictId&cmd=$command - executeApi() - execute an api call and return a JSON object
 *    
 * More info about routing:
 * https://processwire.com/talk/topic/7832-module-routing-executesomething-versus-this-input-urlsegment1-in-process-modules/
 **********************************************************************/
  /**
   * Execute the main function for the admin menu interface
   * 
   */
  public function execute() {
    list ($command, $dictId, $params) = $this->analyzeRequestURI($_SERVER['REQUEST_URI']);
    if ($command !== false) {
      // the purge command will display it's own page
      if ($command == 'purge') return $this->runCommand($command, $dictId, $params);
      $out = '<h2>Executing command '.$command.'</h2>';
      $out .= '<p>'.$this->runCommand($command, $dictId, $params).'</p>';
    } else $out = '';

    $out = '<h2>Dictionary management</h2>';

    $dm = wire('modules')->get('Dictionary');

    $out .= '<h3>Dictionaries</h3>';
    $dictPages = $this->pages->find('template='.$dm->dictionary_template);
    if (!count($dictPages)) {
      $out .= '<p>None found.</p>';
      $out .= '<p><a href="'.$this->page->url.'">Refresh this page.</a></p>';
      return $out;
    }

    $out .= "<ul>\n";
    foreach ($dictPages as $dictPage) {
      $out .= '<li>'.$dictPage->title;
      $out .= ' ('.$dictPage->numChildren('template='.$dm->headword_template)." headwords) ";
      // if the above is too slow, we could call a javascript job to count the pages
      // $out .= '(<i>Javascript is needed to count headwords.</i>)';
      $out .= '<ul class="actions" style="display: inline !important;">';
      $out .= '<li style="display: inline !important;"><a href="'.$dictPage->url."\">View</a></li>\n";
      $out .= '<li style="display: inline !important;"><a href="'.$this->adminUrl.'?id='.$dictPage->id.'&cmd='.self::CMD_IMPORT."\">Import/update from all sources</a></li>\n";
      if ($dictPage->editable()) {
        $out .= '<li style="display: inline !important;"><a href="'.$dictPage->editUrl()."\">Edit</a></li>\n";
        $out .= '<li style="display: inline !important;"><a href="'.$this->adminUrl.'?id='.$dictPage->id.'&cmd='.self::CMD_PURGE."\">Purge</a></li>\n";
      }
      $out .= "</ul></li>\n";
    }
    $out .= "</ul>\n";

    if ($this->modules->isInstalled("TaskerAdmin")) {
      $out .= '<h3>Dictionary tasks</h3>';
      $taskerAdmin = wire('modules')->get('TaskerAdmin');
      $out .= $taskerAdmin->renderTaskList('parent='.implode('|', $dictPages->explode('id')).',sort=task_state');
    }

    $out .= '<p><a href="'.$this->page->url.'">Refresh this page.</a></p>';
    return $out;
  }

  /**
   * Execute a command
   * 
   * @param $command string command
   * @param $dictId int ID of the dictionary Page
   * @param $params assoc array of query arguments
   * 
   */
  public function runCommand($command, $dictPageId, $params) {
    // select the matching dictionary
    $dm = wire('modules')->get('Dictionary');
    $dictPage = $this->pages->findOne('id='.$dictPageId.',template='.$dm->dictionary_template);

    if ($dictPage instanceof NullPage) {
      $this->error('Dictionary not found.');
      return;
    }

    // commands could be long running progs so we can't execute them right now
    // Tasker module is here to help
    $tasker = $this->modules->getModule('Tasker');
    if ($this->modules->isInstalled('TaskerAdmin')) {
      $taskerAdmin = $this->modules->getModule('TaskerAdmin');
    } else {
      $taskerAdmin = false;
    }

    switch ($command) {
      case self::CMD_PURGE:
        $data = array();
        $purgeTask = $tasker->createTask($dm, 'purge', $dictPage, 'Purge the dictionary', $data);
        if ($purgeTask == NULL) return; // tasker failed to add a task
        $tasker->activateTask($purgeTask); // activate the purge task
        // if TaskerAdmin is installed display its task execution page
        if (false !== $taskerAdmin) return $taskerAdmin->runCommand('run', $purgeTask->id, $params);
      case self::CMD_IMPORT:
        $task = $dm->createTasksOnPageSave($dictPage);
        if ($task == NULL) return; // failed to create tasks
        if (false !== $taskerAdmin) return $taskerAdmin->runCommand('run', $task->id, $params);
      default:
        return 'Unknown command: '.$command;
    }
  }


  /**
   * Public admin API functions over HTTP (/api)
   * URI structure: .... api/?id=dictId&cmd=command[&arguments]
   */
  public function executeApi() {
    // response object (will be encoded in JSON form)
    $ret = array(
      'status'=> false,
      'result' => '',
      'value' => 0,
      'log' => '',
      'debug' => $this->config->debug
      );

    // analyze the request
    list ($command, $dictId, $params) = $this->analyzeRequestURI($_SERVER['REQUEST_URI']);

    if (!$command || !$dictId) {
      $ret['result'] = 'Invalid API request: '.$_SERVER['REQUEST_URI'];
      $ret['log'] = $this->getNotices();
      echo json_encode($ret);
      exit;
    }

    // turn off debugging since this is only for executing jobs via javascript
    $this->config->debug = false;

    $dm = wire('modules')->get('Dictionary');

    // select the matching dictionary
    $dictPage = $this->pages->findOne('id='.$dictId.',template='.$dm->dictionary_template);
    if ($dictPage instanceof NullPage) {
      $ret['result'] = 'No matching dictionary found.';
      $ret['log'] = $this->getNotices();
      echo json_encode($ret);
      exit;
    }

    // report back some info after before we start the command
    $ret['dictinfo'] = $dictPage->title;
    $ret['dictid'] = $dictPage->id;

    switch ($command) {
      // TODO
    }

    $ret['log'] = $this->getNotices();

    echo json_encode($ret);
    exit; // don't output anything else
  }

/***********************************************************************
 * LOGGING AND UTILITY METHODS
 **********************************************************************/

  /**
   * Decompose an URI request
   * 
   * TODO: replace taskid/?x=command with command/?id=taskId
   * 
   * @param $request URI
   */
  public function analyzeRequestURI($request) {
    // match the module base URL / command / taskId ? arguments
    $uriparts = parse_url($request);
    $taskId = $command = false;
    $params = array();
    // TODO url segments?
    if (isset($uriparts['query'])) {
      parse_str($uriparts['query'], $query);
      foreach ($query as $key => $value) {
        if ($key == 'id') $taskId = $value;
        elseif ($key == 'cmd') $command = $value;
        else $params[$key] = urldecode($value);
      }
    }

    //$this->message("Command {$command} task {$taskId} with params ".print_r($params, true), Notice::debug);

    return array($command, $taskId, $params);
  }


  /**
   * Get notices
   * 
   * @return HTML-encoded list of system notices
   */
  public function getNotices() {
    $ret = '<ul class="NoticeMessages">';
    foreach(wire('notices') as $notice) {
      $class = $notice->className();
      $text = wire('sanitizer')->entities($notice->text);
      // $ret .= '<li class="'.$class.">$text</li>\n";
      $ret .= "<li>$text</li>\n";
    }
    $ret .= '</ul>';
    return $ret;
  }

}
