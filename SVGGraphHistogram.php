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

require_once 'SVGGraphBarGraph.php';

class Histogram extends BarGraph {

  protected $label_centre = FALSE;
  protected $force_assoc = TRUE;
  protected $minimum_units_y = 1;

  protected $increment = NULL;
  protected $percentage = false;


  /**
   * Process the values
   */
  public function Values($values)
  {
    if(!empty($values)) {
      parent::Values($values);
      $values = array();

      // find min, max, strip out nulls
      $min = $max = NULL;
      foreach($this->values[0] as $item) {
        if(!is_null($item->value)) {
          if(is_null($min) || $item->value < $min)
            $min = $item->value;
          if(is_null($max) || $item->value > $max)
            $max = $item->value;
          $values[] = $item->value;
        }
      }

      // calculate increment?
      if($this->increment <= 0) {
        $diff = $max - $min;
        if($diff <= 0) {
          $inc = 1;
        } else {
          $inc = pow(10, floor(log10($diff)));
          $d1 = $diff / $inc;
          if(($inc != 1 || !is_integer($diff)) && $d1 < 4) {
            if($d1 < 3)
              $inc *= 0.2;
            else
              $inc *= 0.5;
          }
        }
        $this->increment = $inc;
      }

      // prefill the map with nulls
      $map = array();
      $start = $this->Interval($min);
      $end = $this->Interval($max, true) + $this->increment / 2;
      for($i = $start; $i < $end; $i += $this->increment) {
        $key = Graph::NumString($i);
        $map[$key] = null;
      }

      foreach($values as $val) {
        $k = Graph::NumString($this->Interval($val));
        if(!array_key_exists($k, $map))
          $map[$k] = 1;
        else
          $map[$k]++;
      }

      if($this->percentage) {
        $total = count($values);
        $pmap = array();
        foreach($map as $k => $v)
          $pmap[$k] = 100 * $v / $total;
        $values = $pmap;
      } else {
        $values = $map;
      }

      // turn off structured data
      $this->structure = NULL;
      $this->structured_data = FALSE;
    }
    parent::Values($values);
  }

  /**
   * Returns the start (or next) interval for a value
   */
  public function Interval($value, $next = false)
  {
    $n = floor($value / $this->increment);
    if($next)
      ++$n;
    return $n * $this->increment;
  }

  /**
   * Sets up the colour class with corrected number of colours
   */
  protected function ColourSetup($count, $datasets = NULL)
  {
    // $count is off by 1 because the divisions are numbered
    return parent::ColourSetup($count - 1, $datasets);
  }

  /**
   * Override because of the shifted numbering
   */
  protected function GridPosition($key, $ikey)
  {
    $position = null;
    $zero = -0.01; // catch values close to 0
    $axis = $this->x_axes[$this->main_x_axis];
    $offset = $axis->Zero() + ($axis->Unit() * $ikey);
    $g_limit = $this->g_width - ($axis->Unit() / 2);
    if($offset >= $zero && floor($offset) <= $g_limit)
      $position = $this->pad_left + $offset;

    return $position;
  }
}

