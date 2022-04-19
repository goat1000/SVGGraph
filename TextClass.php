<?php
/**
 * Copyright (C) 2022 Graham Breach
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * For more information, please contact <graham@goat1000.com>
 */

namespace Goat1000\SVGGraph;

/**
 * A class for setting text styles from text_classes.ini file
 */
class TextClass {

  private static $classes = null;
  private static $file = null;

  private $prefix = '';
  private $fields = [];

  public function __construct($class_name, $prefix = '')
  {
    if(TextClass::$classes === null) {
      if(TextClass::$file === null)
        TextClass::$file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'text_classes.ini';
      TextClass::load();
    }
    $this->prefix = $prefix;
    if(isset(TextClass::$classes[$class_name]))
      $this->fields = TextClass::$classes[$class_name];
  }

  /**
   * Sets the classes file
   */
  public static function setFile($filename)
  {
    TextClass::$file = $filename;
  }

  /**
   * Loads the text classes file in
   */
  public static function load()
  {
    $classes = @parse_ini_file(TextClass::$file, true);
    if($classes === false) {
      trigger_error("Text classes file '" . TextClass::$file . "' not found");
      $classes = [];
    }
    TextClass::$classes = $classes;
  }

  /**
   * Returns the value of a field from the text class
   */
  public function __get($field)
  {
    if($this->prefix !== '' && strpos($field, $this->prefix) === 0)
      $field = substr($field, strlen($this->prefix));
    if(isset($this->fields[$field]))
      return $this->fields[$field];
    return null;
  }
}

