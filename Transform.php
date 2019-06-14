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
 * Class for SVG transforms
 */
class Transform {
  private $transforms = [];
  
  /**
   * Output for SVG values
   */
  public function __toString()
  {
    $str = '';
    foreach($this->transforms as $xform) {
      $str .= $xform[0] . '(';
      $str .= implode(' ', $xform[1]);
      $str .= ')';
    }
    return $str;
  }

  /**
   * Adds another transform to this one
   */
  public function add($xform)
  {
    if(!is_object($xform) || get_class($xform) !== 'Goat1000\\SVGGraph\\Transform')
      throw new \InvalidArgumentException('Argument is not a Transform');

    $this->transforms = array_merge($this->transforms, $xform->transforms);
  }

  /**
   * Translate by $x, $y
   */
  public function translate($x, $y)
  {
    $this->transforms[] = ['translate', [new Number($x), new Number($y)]];
  }

  /**
   * Scale by $x, or by $x and $y
   */
  public function scale($x, $y = null)
  {
    $args = [new Number($x)];
    if($y !== null)
      $args[] = new Number($y);
    $this->transforms[] = ['scale', $args];
  }

  /**
   * Rotate by $a degrees, around point $x, $y
   */
  public function rotate($a, $x = null, $y = null)
  {
    $args = [new Number($a)];
    if($x !== null && $y !== null) {
      $args[] = new Number($x);
      $args[] = new Number($y);
    }
    $this->transforms[] = ['rotate', $args];
  }

  /**
   * Skew by $a degrees along X-axis
   */
  public function skewX($a)
  {
    $this->transforms[] = ['skewX', [new Number($a)]];
  }

  /**
   * Skew by $a degrees along Y-axis
   */
  public function skewY($a)
  {
    $this->transforms[] = ['skewY', [new Number($a)]];
  }
}
