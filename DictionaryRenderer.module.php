<?php namespace ProcessWire;
// DEBUG disable file compiler for this file
// FileCompiler=0

/*
 * Rendering module for Dictionary
 * 
 * Provides rendering functions for the Dictionary module.
 * 
 * Copyright 2017 Tamas Meszaros <mt+github@webit.hu>
 * This file licensed under Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 */
 
class DictionaryRenderer extends WireData implements Module {
  // TODO: XML mezők láthatósága szerepkörök szerint
  // TODO: vendégek számára elérhető mintakészlet mérete
  // the base URL of the module's admin page
  public $adminUrl;
  // default initial letters for navigation trees
  public $dictInitialLetters = array(
    'a' => 'A, Á',
    'b' => 'B',
    'c' => 'C',
    'cs' => 'Cs',
    'd' => 'D',
    'dzs' => 'Dzs',
    'e' => 'E, É',
    'f' => 'F',
    'g' => 'G',
    'gy' => 'Gy',
    'h' => 'H',
    'i' => 'I, Í',
    'j' => 'J',
    'k' => 'K',
    'l' => 'L',
    'm' => 'M',
    'n' => 'N',
    'ny' => 'Ny',
    'o' => 'O, Ó',
    'ö' => 'Ö, Ő',
    'p' => 'P',
    'q' => 'Q',
    'r' => 'R',
    's' => 'S',
    'sz' => 'Sz',
    't' => 'T',
    'ty' => 'Ty',
    'u' => 'U, Ú',
    'ü' => 'Ü, Ű',
    'v' => 'V',
    'z' => 'Z',
    'zs' => 'Zs',
    '-' => '-',
    '*' => '*',
    '$' => '$',
    '+' => '+',
  );

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
  }


/***********************************************************************
 * RENDERING FUNCTIONS
 **********************************************************************/

  /**
   * Render a navigation tree for dictionary based on initial letters
   * 
   * @param $dicPage dictionary page object
   * @param $pattern array initial letters for the tree or string to prepend to letters in the default array
   * @param $liClass additional attributes for <li> tags. If null <li> is omitted.
   * @param $aClass additional attributes for <a> tags. If null <a> is omitted.
   * @param $countHeadwords count the headwords matching the pattern (also skips empty sets)
   * @returns html string to output
   */
  public function renderLetterNav($dictPage, $pattern, $liClass=' class="nav-item"', $aClass=' class="nav-link"', $countHeadwords = false) {
    /************ PERFORMANCE DEBUGGING ********************************/
    if (class_exists('\Zarganwar\PerformancePanel\Register'))
      \Zarganwar\PerformancePanel\Register::add('renderLetterNav_start');
    /*******************************************************************/
    $out = '';
    if (is_array($pattern)) {
      $letters = $pattern;
    } else {
      $letters = $this->dictInitialLetters;
      // sanitizing pattern ($sanitizer->selectorValue() would not work well)
      if (mb_strlen($pattern)) $pattern = str_replace('"', '', $pattern);
    }
    foreach ($letters as $u => $t) {
      if (!is_null($liClass)) $out .= "<li$liClass>";
      if (is_null($aClass)) {
        $out .= $t;
      } else if (is_string($pattern) && mb_strlen($pattern)) {
        $url = urlencode($pattern.$u);
        $text = $pattern.$u;
        $selector = $pattern.$u;
      } else {
        $url = urlencode($u);
        $text = $t;
        $selector = $u;
      }
      if ($countHeadwords) {
        $count = $this->pages->count('parent='.$dictPage.',title^="'.$selector.'"');
        if ($count == 0) continue;
        $text .= " ($count)";
      }
      $out .= "<a href='{$dictPage->url}?w={$url}'{$aClass}>{$text}</a>";
      if (!is_null($liClass)) $out .= '</li>';
      $out .= "\n";
    }
    /************ PERFORMANCE DEBUGGING ********************************/
    if (class_exists('\Zarganwar\PerformancePanel\Register'))
      \Zarganwar\PerformancePanel\Register::add('renderLetterNav_end');
    /*******************************************************************/
    return $out;
  }

  /**
   * Display a navigation tree for dictionary items
   * 
   * @param $dictPage dictionary page object
   * @param $letters array initial letters for the tree
   * @param $liClass additional attributes for <li> tags. If null <li> is omitted.
   * @param $aClass additional attributes for <a> tags. If null <a> is omitted.
   * @returns html string to output
   */
  public function renderHeadwordNav($dictPage, $selector='', $liClass=' class="nav-item"', $aClass=' class="nav-link"') {
    /************ PERFORMANCE DEBUGGING ********************************/
    if (class_exists('\Zarganwar\PerformancePanel\Register'))
      \Zarganwar\PerformancePanel\Register::add('renderHeadwordNav_start');
    /*******************************************************************/
    $out = '';
    $headwords = $dictPage->children($selector);
    foreach ($headwords as $headword) {
      if (!is_null($liClass)) $out .= "<li$liClass>";
      if (is_null($aClass)) {
        $out .= $t;
      } else {
        $out .= "<a href='{$headword->url}'{$aClass}>".str_replace('$', '|', $headword->title)."</a>";
      }
      if (!is_null($liClass)) $out .= '</li>';
      $out .= "\n";
    }
    /************ PERFORMANCE DEBUGGING ********************************/
    if (class_exists('\Zarganwar\PerformancePanel\Register'))
      \Zarganwar\PerformancePanel\Register::add('renderHeadwordNav_end');
    /*******************************************************************/
    return $out;
  }

  /**
   * Display a navigation tree for dictionary items
   * 
   * @param $dictPage dictionary page object
   * @param $letters array initial letters for the tree
   * @param $liClass additional attributes for <li> tags. If null <li> is omitted.
   * @param $aClass additional attributes for <a> tags. If null <a> is omitted.
   * @returns html string to output
   */
  public function renderHeadwordList($dictPage, $selector='', $liClass=' class="nav-item"', $aClass=' class="nav-link"') {
    /************ PERFORMANCE DEBUGGING ********************************/
    if (class_exists('\Zarganwar\PerformancePanel\Register'))
      \Zarganwar\PerformancePanel\Register::add('renderHeadwordList_start');
    /*******************************************************************/
    $out = '';
    if (!strlen($selector)) {
      $selector = 'limit=30,sort=random';
    }
    $headwords = $dictPage->children($selector);
    foreach ($headwords as $headword) {
      if (!is_null($liClass)) $out .= "<li$liClass>";
      if (is_null($aClass)) {
        $out .= $t;
      } else {
        $out .= "<a href='{$headword->url}'{$aClass}>".str_replace('$', '|', $headword->title)."</a>";
      }
      if (!is_null($liClass)) $out .= '</li>';
      $out .= "\n";
    }
    /************ PERFORMANCE DEBUGGING ********************************/
    if (class_exists('\Zarganwar\PerformancePanel\Register'))
      \Zarganwar\PerformancePanel\Register::add('renderHeadwordList_end');
    /*******************************************************************/
    return $out;
  }


  /**
   * Display a navigation tree for dictionary items
   * 
   * @param $headwordPage headword page object
   * @returns html string to output
   */
  public function renderHeadwordData($headwordPage) {
    /************ PERFORMANCE DEBUGGING ********************************/
    if (class_exists('\Zarganwar\PerformancePanel\Register'))
      \Zarganwar\PerformancePanel\Register::add('renderHeadword_start');
    /*******************************************************************/
    $xml = new \XMLReader();
    if (false === $xml->xml(str_replace('_', ' ', $headwordPage->xml_data))) {
      $this->error("Error decoding xml_data in {$headwordPage->title}.");
      return '';
    }

    $tagNames = array_flip(json_decode(trim($this->modules->DictionaryXmlProcessor->tagmappings), true));
    $out = '';

    while ($xml->read()) {
      if ($xml->nodeType != \XMLReader::ELEMENT) continue;
      $tagName = (isset($tagNames[$xml->localName]) ? $tagNames[$xml->localName] : $xml->localName);
      switch ($tagName) {
      case 'entry':
      case 'headword':
        break;
      case 'comment':
        $out .= '<p>Megjegyzés: <i>'.$xml->readString()."</i></p>\n";
        break;
      case 'variant':
        $out .= '<h3>'.$xml->readString()."</h3>\n";
        break;
      case 'wordform':
        $out .= '<h5>'.$xml->readString()."</h5>\n";
        break;
      case 'wordform_example':
        $out .= '<i>'.$xml->readString()."</i><br />\n";
        break;
      default:
        $content = $xml->readString();
        if (strlen(trim($content))) {
          $out .= '<p><b>'.$tagName.'</b>: '.$content."</p>\n";
        } else {
          $out .= '<p><b>'.$tagName."</b>\n";
        }
      }
    }
    /************ PERFORMANCE DEBUGGING ********************************/
    if (class_exists('\Zarganwar\PerformancePanel\Register'))
      \Zarganwar\PerformancePanel\Register::add('renderHeadwords_end');
    /*******************************************************************/
    return $out;
  }

}
