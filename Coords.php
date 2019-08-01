<?php
/**
 * Copyright (C) 2015-2019 Graham Breach
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
 * Class for parsing and converting coordinates
 */
class Coords {

  private $graph;

  public function __construct(&$graph)
  {
    $this->graph =& $graph;
  }

  /**
   * Returns TRUE if (x,y) is grid-based
   */
  public function isGrid($x, $y)
  {
    if(is_numeric($x) && is_numeric($y))
      return false;
    $first = strtolower(substr($x, 0, 1));
    if($first == 'g')
      return true;
    $first = strtolower(substr($y, 0, 1));
    if($first == 'g')
      return true;
    return false;
  }

  /**
   * splits $value, removing leading char and updating $axis, $axis_no
   */
  private function valueAxis(&$value, &$axis, &$axis_no)
  {
    if(preg_match('/^[ug](.*?)(([xy])(\d?))?$/i', $value, $matches)) {
      $value = $matches[1];
      if(count($matches) == 5) {
        $axis = strtolower($matches[3]);
        $axis_no = is_numeric($matches[4]) ? $matches[4] : null;
      }
      return;
    }
    // if the regex failed (?!) just strip leading u or g
    $value = substr($value, 1);
  }

  /**
   * Transform coordinate pair to SVG coords
   */
  public function transformCoords($x, $y)
  {
    $xy = [ $this->transform($x, 'x'), $this->transform($y, 'y') ];
    if($this->isGrid($x, $y) && method_exists($this->graph, 'transformCoords')) {
      $xy = $this->graph->transformCoords($xy[0], $xy[1]);
    }
    return $xy;
  }

  /**
   * Transform from grid space etc. to SVG space
   */
  public function transform($value, $axis, $default_pos = 0)
  {
    if(is_numeric($value) || !is_string($value))
      return $value;
    $first = strtolower(substr($value, 0, 1));
    $grid = false;

    if($first == 'u' || $first == 'g') {
      if(!method_exists($this->graph, 'gridX'))
        throw new \Exception('Invalid dimensions (non-grid graph)');

      $this->valueAxis($value, $axis, $axis_no);

      if($first == 'u') {
        // value is in grid units
        $func = $axis == 'x' ? 'unitsX' : 'unitsY';
        return $this->graph->{$func}($value, $axis_no) -
          $this->graph->{$func}(0, $axis_no);
      }

      // value is a grid position
      $grid = true;
    }

    $dim = $this->graph->getDimensions();

    // try value as assoc/datetime key first
    if($grid) {
      $axis_inst = $this->graph->getAxis($axis, $axis_no);
      $position = $axis_inst->positionByKey($value);
      if($position !== null) {
        if($axis == 'x')
          return $position + $dim['pad_left'];
        return $dim['height'] - $dim['pad_bottom'] - $position;
      }
    }

    if(is_numeric($value)) {
      if($grid) {
        $func = $axis == 'x' ? 'gridX' : 'gridY';
        return $this->graph->{$func}($value, $axis_no);
      }
      return $value;
    }

    if($value == 'c')
      $value .= $axis;
    if($grid)
      return $this->getGridPosition($value, $default_pos);
    return $this->getGraphPosition($value, $default_pos);
  }

  /**
   * Converts a grid position to a number
   */
  public function getGridPosition($pos, $default_pos)
  {
    $dim = $this->graph->getDimensions();
    switch($pos) {
    case 't' : return $dim['pad_top'];
    case 'l' : return $dim['pad_left'];
    case 'b' : return $dim['height'] - $dim['pad_bottom'];
    case 'r' : return $dim['width'] - $dim['pad_right'];
    case 'h' : return $dim['height'] - $dim['pad_bottom'] - $dim['pad_top'];
    case 'w' : return $dim['width'] - $dim['pad_right'] - $dim['pad_left'];
    case 'cx' : return ($dim['width'] - $dim['pad_right'] + $dim['pad_left']) / 2;
    case 'cy' : return ($dim['height'] - $dim['pad_bottom'] + $dim['pad_top']) / 2;
    }
    return $default_pos;
  }

  /**
   * Converts a graph position to a number
   */
  public function getGraphPosition($pos, $default_pos)
  {
    $dim = $this->graph->getDimensions();
    switch($pos) {
    case 't' : return 0;
    case 'l' : return 0;
    case 'b' : return $dim['height'];
    case 'r' : return $dim['width'];
    case 'h' : return $dim['height'];
    case 'w' : return $dim['width'];
    case 'cx' : return $dim['width'] / 2;
    case 'cy' : return $dim['height'] / 2;
    }
    return $default_pos;
  }
}

