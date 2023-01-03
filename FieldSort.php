<?php
/**
 * Copyright (C) 2019-2023 Graham Breach
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
 * Class for sorting array by field
 */
class FieldSort {

  private $key = null;
  private $reverse = false;

  public function __construct($key, $reverse = false)
  {
    $this->key = $key;
    $this->reverse = $reverse;
  }

  /**
   * Sorts the array based on value of key field
   */
  public function sort(&$data)
  {
    $key = $this->key;
    $get_val = function($a, $key) {
      return (!isset($a[$key]) || $a[$key] === null ? PHP_INT_MIN : $a[$key]);
    };
    $bigger = function($a, $b, $key) use($get_val) {
      $va = $get_val($a, $key);
      $vb = $get_val($b, $key);
      if($va == $vb)
        return 0;
      return $va > $vb ? 1 : -1;
    };
    $smaller = function($a, $b, $key) use($get_val) {
      $va = $get_val($a, $key);
      $vb = $get_val($b, $key);
      if($va == $vb)
        return 0;
      return $va < $vb ? 1 : -1;
    };
    $fn = $this->reverse ? $smaller : $bigger;
    usort($data, function($a, $b) use($key, $fn) {
      return $fn($a, $b, $key);
    });
  }
}

