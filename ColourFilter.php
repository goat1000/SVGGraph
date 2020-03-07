<?php
/**
 * Copyright (C) 2020 Graham Breach
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
 * Class for modifying a colour
 */
class ColourFilter {
  private $colour;
  private $filters;

  public function __construct($colour, $filters)
  {
    // these are special cases, can't be processed
    if($colour === 'transparent' || $colour === 'none')
      throw new \InvalidArgumentException('Unable to filter colour [' . $colour . ']');
    $this->colour = new RGBColour($colour);

    $filters = explode('/', $filters);
    foreach($filters as $f) {
      $filter = $f;
      $args = [];
      $fpos = strpos($f, '(');
      $epos = strpos($f, ')');
      if($fpos > 0) {
        $filter = substr($f, 0, $fpos);
        $a = '';
        if($epos > $fpos)
          $a = substr($f, $fpos + 1, $epos - $fpos - 1);
        if($a !== '')
          $args = preg_split('/[\s,]+/', $a);
      }

      if(method_exists($this, $filter))
        call_user_func_array([$this, $filter], $args);
    }
  }

  /**
   * Increase or decrease brightness
   */
  public function brightness($amount = '1.2')
  {
    list ($operator, $value) = $this->expression($amount);
    if($value === null)
      throw new \InvalidArgumentException('Invalid brightness [' . $amount . ']');
    list($h, $s, $l) = $this->colour->getHSL();

    $l = min(1.0, max(0.0, $operator === '+' ? $l + $value : $l * $value));
    $this->colour->setHSL($h, $s, $l);
  }

  /**
   * Increase or decrease saturation
   */
  public function saturation($amount = '0.0')
  {
    list ($operator, $value) = $this->expression($amount);
    if($value === null)
      throw new \InvalidArgumentException('Invalid saturation [' . $amount . ']');
    list($h, $s, $l) = $this->colour->getHSL();

    $s = min(1.0, max(0.0, $operator === '+' ? $s + $value : $s * $value));
    $this->colour->setHSL($h, $s, $l);
  }

  /**
   * Modify the hue
   */
  public function hue($amount = '60')
  {
    if(!is_numeric($amount))
      throw new \InvalidArgumentException('Invalid hue [' . $amount . ']');
    list($h, $s, $l) = $this->colour->getHSL();
    $h += $amount;
    $this->colour->setHSL($h, $s, $l);
  }

  /**
   * Returns the expression to be applied
   */
  public static function expression($a)
  {
    $operator = '*';
    $value = null;
    if($a[0] === '+' || $a[0] === '-')
      $operator = '+';

    $p = strpos($a, '%');
    if($p > 0)
      $a = substr($a, 0, $p);

    if(is_numeric($a))
      $value = $p > 0 ? $a / 100 : $a;
    return [$operator, $value];
  }

  public function __toString()
  {
    return $this->colour->getHex();
  }
}

