<?php
/**
 * Copyright (C) 2015-2017 Graham Breach
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
  private function ValueAxis(&$value, &$axis, &$axis_no)
  {
    if(preg_match('/^[ug](.*?)(([xy])(\d?))?$/i', $value, $matches)) {
      $value = $matches[1];
      if(count($matches) == 5) {
        $axis = strtolower($matches[3]);
        $axis_no = is_numeric($matches[4]) ? $matches[4] : NULL;
      }
      return;
    }
    // if the regex failed (?!) just strip leading u or g
    $value = substr($value, 1);
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
  public function Transform($value, $axis, $default_pos = 0)
  {
    if(is_numeric($value))
      return $value;
    $first = strtolower(substr($value, 0, 1));
    $grid = false;

    if($first == 'u' || $first == 'g') {
      if(!method_exists($this->graph, 'GridX'))
        throw new Exception('Invalid dimensions (non-grid graph)');

      $this->ValueAxis($value, $axis, $axis_no);

      if($first == 'u') {
        // value is in grid units
        return $axis == 'x' ?
          $this->graph->UnitsX($value, $axis_no) - $this->graph->UnitsX(0, $axis_no):
          $this->graph->UnitsY($value, $axis_no) - $this->graph->UnitsY(0, $axis_no);
      }

      // value is a grid position
      $grid = true;
    }

    // try value as assoc/datetime key first
    if($grid) {
      $axis_inst = $this->graph->GetAxis($axis, $axis_no);
      $position = $axis_inst->PositionByKey($value);
      if(!is_null($position)) {
        if($axis == 'x')
          $position += $this->graph->pad_left;
        else
          $position = $this->graph->height - $this->graph->pad_bottom - $position;
        return $position;
      }
    }

    $trans = $default_pos;
    if(is_numeric($value)) {
      if($grid) {
        $trans = $axis == 'x' ? $this->graph->GridX($value, $axis_no) :
          $this->graph->GridY($value, $axis_no);
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

