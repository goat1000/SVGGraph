<?php
/**
 * Copyright (C) 2015 Graham Breach
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


/**
 * Class for parsing and converting coordinates
 */
class SVGGraphCoords {

  private $graph;

  public function __construct(&$graph)
  {
    $this->graph = $graph;
  }

  /**
   * Returns TRUE if (x,y) is grid-based
   */
  public function IsGrid($x, $y)
  {
    if(is_numeric($x) && is_numeric($y))
      return false;
    $first = substr($x, 0, 1);
    if($first == 'g')
      return true;
    $first = substr($y, 0, 1);
    if($first == 'g')
      return true;
    return false;
  }

  /**
   * splits $value, removing leading char and updating $axis
   */
  private function ValueAxis(&$value, &$axis)
  {
    // strip leading u or g
    $value = substr($value, 1);
    $last = substr($value, -1);
    if($last == 'x' || $last == 'y') {
      // axis given, strip last char
      $axis = $last;
      $value = substr($value, 0, -1);
    }
  }

  /**
   * Transform coordinate pair to SVG coords
   */
  public function TransformCoords($x, $y)
  {
    $xy = array($this->Transform($x, 'x'), $this->Transform($y, 'y'));
    if($this->IsGrid($x, $y) && method_exists($this->graph, 'TransformCoords')) {
      $xy = $this->graph->TransformCoords($xy[0], $xy[1]);
    }
    return $xy;
  }

  /**
   * Transform from grid space etc. to SVG space
   */
  public function Transform($value, $axis)
  {
    if(is_numeric($value))
      return $value;
    $value = strtolower($value);
    $first = substr($value, 0, 1);
    $grid_graph = method_exists($this->graph, 'GridX');

    if(!$grid_graph && ($first == 'u' || $first == 'g'))
      throw new Exception('Invalid dimensions (non-grid graph)');

    if($first == 'u') {
      // value is in grid units
      $this->ValueAxis($value, $axis);
      if(is_numeric($value)) {
        return $axis == 'x' ? $this->graph->UnitsX($value) :
          $this->graph->UnitsY($value);
      } else {
        return 0;
      }
    }

    $grid = false;
    if($first == 'g') {
      // value is a grid position
      $this->ValueAxis($value, $axis);
      $grid = true;
    }

    $trans = 0;
    if(is_numeric($value)) {
      if($grid) {
        $trans = $axis == 'x' ? $this->graph->GridX($value) :
          $this->graph->GridY($value);
      } else {
        $trans = $value;
      }
    } else {
      if($grid) {
        list($t, $l, $r, $b) = array(
          $this->graph->pad_top,
          $this->graph->pad_left,
          $this->graph->width - $this->graph->pad_right,
          $this->graph->height - $this->graph->pad_bottom);
      } else {
        list($t, $l, $r, $b) = array(0, 0, $this->graph->width,
          $this->graph->height);
      }

      switch($value) {
        case 't' : $trans = $t; break;
        case 'l' : $trans = $l; break;
        case 'b' : $trans = $b; break;
        case 'r' : $trans = $r; break;
        case 'h' : $trans = $b - $t; break;
        case 'w' : $trans = $r - $l; break;
        case 'cx' : $trans = $l + ($r - $l) / 2; break;
        case 'cy' : $trans = $t + ($b - $t) / 2; break;
        case 'c' :
          $trans = $axis == 'x' ? $l + ($r - $l) / 2 : $t + ($b - $t) / 2;
          break;
      }
    }
    return $trans;
  }
}

