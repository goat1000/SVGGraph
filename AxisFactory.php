<?php
/**
 * Copyright (C) 2021 Graham Breach
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

class AxisFactory {

  private $datetime = false;
  private $settings = [];
  private $fit = true;
  private $bar = false;
  private $reverse = false;

  /**
   * Constructor
   *
   * $datetime = datetime keys (bool)
   * $settings = settings array
   * $fit = fit to values (bool)
   * $bar = bar-style axis (bool)
   * $reverse = reverse direction (bool)
   */
  public function __construct($datetime, $settings, $fit = true, $bar = false,
    $reverse = false)
  {
    $this->datetime = $datetime;
    $this->settings = $settings;
    $this->fit = $fit;
    $this->bar = $bar;
    $this->reverse = $reverse;
  }

  /**
   * Creates and returns axis
   *
   * $length = length of axis
   * $min = minimum value
   * $max = maximum value
   * $min_unit = minimum unit value
   * $min_space = minimum spacing
   * $grid_division = fixed division size
   * $units_before = text before units
   * $units_after = text after units
   * $decimal_digits = number of digits
   * $text_callback = text formatting function
   * $values = values array/object
   * $log = logarithmic axis (bool)
   * $log_base = log axis base
   * $levels = axis levels
   * $ticks = fixed axis ticks (array)
   */
  public function get($length, $min, $max, $min_unit, $min_space, $grid_division,
    $units_before, $units_after, $decimal_digits, $text_callback, $values,
    $log, $log_base, $levels, $ticks)
  {
    if($this->datetime) {
      // datetime axis

      if(is_array($ticks)) {
        $axis = new AxisFixedTicksDateTime($length, $max, $min, $ticks,
          $this->settings);
      } else {
        $axis = new AxisDateTime($length, $max, $min, $min_space,
          $grid_division, $levels, $this->settings);
      }

    } elseif($log) {

      // logarithmic axis
      $axis = new AxisLog($length, $max, $min, $min_unit, $min_space,
        $this->fit, $units_before, $units_after, $decimal_digits, $log_base,
        $grid_division, $text_callback);

    } elseif(is_array($ticks)) {

      // axis with fixed ticks
      $axis = new AxisFixedTicks($length, $max, $min, $ticks, $units_before,
        $units_after, $decimal_digits, $text_callback, $values);

    } elseif(is_numeric($grid_division)) {

      // fixed grid divisions
      $axis = new AxisFixed($length, $max, $min, $grid_division,
        $units_before, $units_after, $decimal_digits, $text_callback,
        $values);

    } else {

      // calculated axis
      $axis = new Axis($length, $max, $min, $min_unit, $min_space, $this->fit,
        $units_before, $units_after, $decimal_digits,
        $text_callback, $values);

    }
    if($this->bar)
      $axis->bar();
    if($this->reverse)
      $axis->reverse();
    return $axis;
  }
}

