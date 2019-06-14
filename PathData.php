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
 * Data class for SVG path data
 */
class PathData {

  private $parts = '';
  private $last = '';

  /**
   * Constructs a path from another PathData, elements or an array of elements
   */
  public function __construct()
  {
    $segments = func_get_args();
    $num = count($segments);
    if($num) {
      if($num > 1) {
        $this->add($segments);
        return;
      }

      $this->add($segments[0]);
    }
  }

  /**
   * Adds segments to the path
   */
  public function add($e)
  {
    $segments = func_get_args();
    if(count($segments) < 1)
      throw new \Exception('No segments to add');

    if(count($segments) == 1) {
      $e = $segments[0];

      // add another PathData
      if(is_object($e) && get_class($e) === 'Goat1000\\SVGGraph\\PathData') {
        $this->parts .= ' ' . $e->parts;
        $this->last = $e->last;
        return;
      }

      if(is_array($e))
        $segments = $e;
    }

    $this->addSegments($segments);
  }

  /**
   * Returns true if the path has no segments
   */
  public function isEmpty()
  {
    return $this->parts === '';
  }

  /**
   * Clears any existing segments
   */
  public function clear()
  {
    $this->parts = '';
    $this->last = '';
  }

  /**
   * Converts the path segments to a string
   */
  public function __toString()
  {
    // already contains a string, this just reduces whitespace
    return preg_replace(['/ ([a-zA-Z])/', '/([a-zA-Z]) /'], '${1}', $this->parts);
  }

  /**
   * Adds an array of segments
   */
  private function addSegments($segments)
  {
    $last = $this->last;
    $parts = '';
    foreach($segments as $part) {

      if(is_object($part)) {
        if(get_class($part) === 'Goat1000\\SVGGraph\\PathData')
          throw new \InvalidArgumentException('PathData in segment list. Use separate add() calls.');

        $parts .= ' ' . $part;
        continue;
      }

      if(!is_numeric($part)) {
        // skip duplicate path commands
        if($part == $last)
          continue;
        $last = $part;
        $parts .= $part;
        continue;
      }

      $parts .= ' ' . new Number($part);
    }

    if($parts !== '') {
      $this->last = $last;
      $this->parts .= $parts;
    }
  }
}

