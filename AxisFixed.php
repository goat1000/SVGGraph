<?php
/**
 * Copyright (C) 2009-2019 Graham Breach
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
 * Axis with fixed measurements
 */
class AxisFixed extends Axis {

  protected $step;
  protected $orig_max_value;
  protected $orig_min_value;

  public function __construct($length, $max_val, $min_val, $step,
    $units_before, $units_after, $decimal_digits, $label_callback, $values)
  {
    // min_unit = 1, min_space = 1, fit = false
    parent::__construct($length, $max_val, $min_val, 1, 1, false, $units_before,
      $units_after, $decimal_digits, $label_callback, $values);
    $this->orig_max_value = $max_val;
    $this->orig_min_value = $min_val;
    $this->step = $step;
  }

  /**
   * Calculates a grid based on min, max and step
   * min and max will be adjusted to fit step
   */
  protected function grid()
  {
    // use the original min/max to prevent compounding of floating-point
    // rounding problems
    $min = $this->orig_min_value;
    $max = $this->orig_max_value;

    // if min and max are the same side of 0, only adjust one of them
    if($max * $min >= 0) {
      $count = $max - $min;
      if(abs($max) >= abs($min)) {
        $this->max_value = $min + $this->step * ceil($count / $this->step);
      } else {
        $this->min_value = $max - $this->step * ceil($count / $this->step);
      }
    } else {
      $this->max_value = $this->step * ceil($max / $this->step);
      $this->min_value = $this->step * floor($min / $this->step);
    }

    $count = ($this->max_value - $this->min_value) / $this->step;
    $ulen = $this->max_value - $this->min_value;
    if($ulen == 0)
      throw new \Exception('Zero length axis (min >= max)');
    $this->unit_size = $this->length / $ulen;
    $grid = $this->length / $count;
    $this->zero = (-$this->min_value / $this->step) * $grid;
    return $grid;
  }

  /**
   * Sets the bar style, adding an extra unit
   */
  public function bar()
  {
    if(!$this->rounded_up) {
      $this->orig_max_value += $this->min_unit;
      parent::bar();
    }
  }
}

