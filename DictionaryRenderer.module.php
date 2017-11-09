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
  // letter substitutions in user input (what => with)
  // the result is used in indices and selectors
  public $dictLetterSubstitutions = array(
    'á' => 'a',
    'é' => 'e',
    'í' => 'i',
    'ó' => 'o',
    'ő' => 'ö',
    'ú' => 'u',
    'ű' => 'ü',
  );
  // default initial letters for navigation trees
  public $dictInitialLetters = array(
    'a' => 'a, á',
    'b' => 'b',
    'c' => 'c',
    'cs' => 'cs',
    'd' => 'd',
    'dz' => 'dz',
    'dzs' => 'dzs',
    'e' => 'e, é',
    'f' => 'f',
    'g' => 'g',
    'gy' => 'gy',
    'h' => 'h',
    'i' => 'i, í',
    'j' => 'j',
    'k' => 'k',
    'l' => 'l',
    'ly' => 'ly',
    'm' => 'm',
    'n' => 'n',
    'ny' => 'ny',
    'o' => 'o, ó',
    'ö' => 'ö, ő',
    'p' => 'p',
    'q' => 'q',
    'r' => 'r',
    's' => 's',
    'sz' => 'sz',
    't' => 't',
    'ty' => 'ty',
    'u' => 'u, ú',
    'ü' => 'ü, ű',
    'v' => 'v',
    'w' => 'w',
    'x' => 'x',
    'y' => 'y',
    'z' => 'z',
    'zs' => 'zs',
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
    $out = ''; $initial = '';

    // always use the default language for listing headwords
    // TODO multilanguage dictionaries are not supported atm
    $lang = $this->languages->get('default');

    if (is_array($pattern)) {
      // print out these letters
      $letters = $pattern;
    } else if (is_string($pattern) && mb_strlen($pattern)) {
      // sanitizing pattern ($sanitizer->selectorValue() would not work well)
      $pattern = str_replace('"', '', $pattern);
      // assemble a set of letters for the menu
      $letters = array(); $substr = '';
      // add increasing number of starting letters of $pattern: 1 12 123 1234 ...
      foreach (preg_split('//u', $pattern, -1, PREG_SPLIT_NO_EMPTY) as $letter) {
        $substr .= $letter;
        $index = mb_strtolower($substr);
        if (isset($this->dictInitialLetters[$index])) {
          // if the substring found in the default letters, get its qualified name
          // this is the case with double and triple letters like sz, cs, dzs etc.
          $letters[$index] = $this->dictInitialLetters[$index];
          $initial = $index;
        } else {
          $letters[$index] = $substr;
        }
      }
      // add possible letters after $pattern from the default letter set
      foreach ($this->dictInitialLetters as $u => $l) {
        $index = mb_strtolower($pattern).$u;
        $letters[$index] = $index;
      }
    } else { // empty or invalid pattern, use the default letter set
      $letters = $this->dictInitialLetters;
    }

    foreach ($letters as $u => $t) {
      if (!is_null($liClass)) $out .= "<li$liClass>";
      $url = urlencode($u);
      $text = $t;
      $selector = $u;
      // for the active nav item add an extra span wrapper (TODO: this is a hack, avoid it)
      if ($initial == $u) {
        $text = '<span class="bg-primary text-white mx-2"> '.$text.' </span>';
      }
      // TODO always use the default language for querying headwords
      $count = $this->pages->count('parent='.$dictPage.',title^="'.$selector.'"');
      if ($count == 0) continue;
      if ($countHeadwords) $text .= " ($count)";
      $out .= "<a href='{$dictPage->url}?w={$url}'{$aClass}>{$text}</a>";
      if (!is_null($liClass)) $out .= '</li>';
      $out .= "\n";
    }

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
    $out = '';
    // always use the default language for listing headwords
    // TODO multilanguage dictionaries are not supported atm
    $lang = $this->user->language;
    $this->user->language = $this->languages->get('default');

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

    // restore the original language
    $this->user->language = $lang;

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
    return $out;
  }


  /**
   * Display a navigation tree for dictionary items
   * 
   * @param $headwordPage headword page object
   * @returns html string to output
   */
  public function renderHeadwordData($headwordPage) {
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
    return $out;
  }

/***********************************************************************
 * UTILITY FUNCTIONS
 **********************************************************************/
  /**
   * Sanitize user input
   * 
   * @param $input string
   * @returns string to use as selector or array index
   */
  public function sanitizeInput($input) {
    // TODO ?? mb_internal_encoding('UTF-8');
    // TODO ?? html_entity_decode($input);

    // filter out some illegal characters
    $ret = mb_ereg_replace('["]', '', $input);

    // replace letters with others
    foreach ($this->dictLetterSubstitutions as $what => $with)
      $ret = mb_ereg_replace($what, $with, $ret);

    // lower case
    return mb_strtolower($ret);
  }
}
