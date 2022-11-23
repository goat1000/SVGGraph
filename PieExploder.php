<?php
/**
 * Copyright (C) 2017-2022 Graham Breach
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

class PieExploder {

  protected $graph;
  protected $smallest_value;
  protected $largest_value;
  protected $explode;
  protected $explode_amount;
  protected $reverse;
  protected $auto_aspect;
  protected $radius_x;
  protected $radius_y;

  public function __construct(&$graph, $smallest, $largest)
  {
    $this->graph =& $graph;
    $this->smallest_value = $smallest;
    $this->largest_value = $largest;
    $this->explode = $graph->getOption('explode');
    $this->reverse = $graph->getOption('reverse');
    $amount = $graph->getOption('explode_amount');
    $this->explode_amount = is_numeric($amount) ? $amount : 20;
    $this->auto_aspect = ($graph->getOption('aspect_ratio') == 'auto');
  }

  /**
   * Reduces the radii to fit the exploded portion
   */
  public function fixRadii(&$radius_x, &$radius_y)
  {
    $this->explode_amount = min($radius_x - 10, $radius_y - 10,
      max(2, (int)$this->explode_amount));
    if($this->auto_aspect && $radius_x != $radius_y) {
      $mx = $my = 1.0;
      if($radius_x > $radius_y) {
        $my = $radius_y / $radius_x;
      } else {
        $mx = $radius_x / $radius_y;
      }
      $radius_x -= $this->explode_amount * $mx;
      $radius_y -= $this->explode_amount * $my;
    } else {
      $radius_x -= $this->explode_amount;
      $radius_y -= $this->explode_amount;
    }
    $this->radius_x = $radius_x;
    $this->radius_y = $radius_y;
    return $this->explode_amount;
  }

  /**
   * Returns the x,y offset caused by explosion
   */
  public function getExplode($item, $angle_start, $angle_end)
  {
    if($item === null)
      return [0,0];
    $iamt = $item->explode;
    if($iamt !== null) {
      $amt = $iamt;
    } else {
      $range = $this->largest_value - $this->smallest_value;
      switch($this->explode) {
      case 'none' :
        $amt = 0;
        break;
      case 'all' :
        $amt = 1;
        break;
      case 'large' :
        $amt = $range <= 0 ? 0 : ($item->value - $this->smallest_value) / $range;
        break;
      default :
        $amt = $range <= 0 ? 0 : ($this->largest_value - $item->value) / $range;
      }
    }
    $explode = $this->explode_amount * $amt;
    $explode_direction = $angle_start + ($angle_end - $angle_start) * 0.5;
    $xo = $explode * cos($explode_direction);
    $yo = $explode * sin($explode_direction);

    $aspect = $this->radius_y / $this->radius_x;
    if($aspect < 1.0)
      $yo *= $aspect;
    elseif($aspect > 1.0)
      $xo /= $aspect;
    if($this->reverse)
      $yo = -$yo;

    return [$xo, $yo];
  }
}

