<?php
/**
 * Copyright (C) 2015-2022 Graham Breach
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
  private static function valueAxis(&$value, &$axis, &$axis_no)
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
   * Determines the type of value
   */
  public static function parseValue($value, $axis = null)
  {
    $info = [
      'value' => $value, 'axis' => $axis, 'axis_no' => null,
      'simple' => true, 'grid' => false, 'units' => false,
      'offset' => 0, 'offset_units' => false,
    ];
    if(is_numeric($value) || !is_string($value))
      return $info;

    $info['simple'] = false;
    $first = strtolower(substr($value, 0, 1));
    if($first == 'u' || $first == 'g') {
      Coords::valueAxis($value, $axis, $axis_no);
      $info['value'] = $value;
      $info['axis'] = $axis;
      $info['axis_no'] = $axis_no;
      $info['grid'] = true;
      $info['units'] = ($first == 'u');
      $first = strtolower(substr($value, 0, 1));
    }

    // check for offset from relative position
    if(!$info['units'] && in_array($first, ['t','l','b','r','h','w','c']) &&
      preg_match('/(.+)([-+][0-9.]+)(u?)/', $info['value'], $matches)) {
      $info['value'] = $matches[1];
      $info['offset'] = $matches[2];
      $info['offset_units'] = ($matches[3] == 'u' || $matches[3] == 'U');
    }
    return $info;
  }

  /**
   * Transform from grid space etc. to SVG space
   */
  public function transform($value, $axis, $default_pos = 0, $measure_from = 0)
  {
    $v_info = Coords::parseValue($value, $axis);
    if($v_info['simple'])
      return $value;

    $value = $v_info['value'];
    if($v_info['grid'] && !method_exists($this->graph, 'gridX'))
      throw new \Exception('Invalid dimensions (non-grid graph)');

    if($v_info['units']) {
      $axis_inst = $this->graph->getAxis($v_info['axis'], $v_info['axis_no']);
      return $axis_inst->measureUnits($measure_from, $value);
    }

    $dim = $this->graph->getDimensions();

    // try value as assoc/datetime key first
    if($v_info['grid']) {
      $axis_inst = $this->graph->getAxis($v_info['axis'], $v_info['axis_no']);
      $position = $axis_inst->positionByKey($value);
      if($position !== null) {
        if($v_info['axis'] == 'x')
          return $position + $dim['pad_left'];
        return $axis_inst->reversed() ?
          $dim['height'] - $dim['pad_bottom'] - $position :
          $position + $dim['pad_top'];
      }
    }

    if(is_numeric($value)) {
      if($v_info['grid']) {
        $func = $axis == 'x' ? 'gridX' : 'gridY';
        return $this->graph->{$func}($value, $v_info['axis_no']);
      }
      return $value;
    }

    if($value == 'c')
      $value .= $axis;
    $pos = $v_info['grid'] ? $this->getGridPosition($value, $default_pos) :
      $this->getGraphPosition($value, $default_pos);

    // handle offset from relative position
    if($v_info['offset']) {
      if($v_info['offset_units']) {
        $axis_inst = $this->graph->getAxis($v_info['axis'], $v_info['axis_no']);
        $pos += $axis_inst->measureUnits($measure_from, $v_info['offset']);
      } else {
        $pos += $v_info['offset'];
      }
    }
    return $pos;
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

