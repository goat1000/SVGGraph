<?php
/**
 * Copyright (C) 2017 Graham Breach
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

class PieExploder {

  protected $settings;
  protected $graph;
  protected $smallest_value;
  protected $largest_value;
  protected $explode_amount;
  protected $auto_aspect;
  protected $radius_x;
  protected $radius_y;

  public function __construct(&$settings, &$graph, $smallest, $largest)
  {
    $this->settings = $settings;
    $this->graph = $graph;
    $this->smallest_value = $smallest;
    $this->largest_value = $largest;
    $this->explode_amount = isset($settings['explode_amount']) &&
      is_numeric($settings['explode_amount']) ?
      $settings['explode_amount'] : 20;
    $this->auto_aspect = isset($settings['aspect_ratio']) &&
      $settings['aspect_ratio'] == 'auto';
  }

  /**
   * Return the settings as properties
   */
  public function __get($name)
  {
    $this->{$name} = isset($this->settings[$name]) ?
      $this->settings[$name] : null;
    return $this->{$name};
  }

  /**
   * Reduces the radii to fit the exploded portion
   */
  public function FixRadii(&$radius_x, &$radius_y)
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
  public function GetExplode($item, $angle_start, $angle_end)
  {
    $range = $this->largest_value - $this->smallest_value;
    switch($this->explode) {
    case 'none' :
      $diff = 0;
      break;
    case 'all' :
      $diff = $range;
      break;
    case 'large' :
      $diff = $item->value - $this->smallest_value;
      break;
    default :
      $diff = $this->largest_value - $item->value;
    }
    $amt = $range > 0 ? $diff / $range : 0;
    $iamt = $item->Data('explode');
    if(!is_null($iamt))
      $amt = $iamt;
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

    return array($xo, $yo);
  }
}

