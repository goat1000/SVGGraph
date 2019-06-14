<?php
/**
 * Copyright (C) 2019 Graham Breach
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
 * Data class for SVG attribute
 */
class Attribute {
  public $name;
  public $value;
  public $encoding;
  public $units = '';
  
  // these properties require units to work well
  private static $require_units = [
    'baseline-shift' => 1,
    'font-size' => 1,
    'kerning' => 1,
    'letter-spacing' => 1,
    'stroke-dashoffset' => 1,
    'stroke-width' => 1,
    'word-spacing' => 1,
  ];

  public function __construct($name, $value, $encoding)
  {
    $this->name = $name;
    if(isset(Attribute::$require_units[$name]))
      $this->units = 'px';
    $this->value = $value;
    $this->encoding = $encoding;

    if(is_numeric($value))
      $this->value = new Number($value, $this->units);
  }

  public function __toString()
  {
    if(is_object($this->value))
      return (string)$this->value;

    return htmlspecialchars($this->value, ENT_COMPAT, $this->encoding);
  }
}

