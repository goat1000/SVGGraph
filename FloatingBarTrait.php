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

trait FloatingBarTrait {

  private $min_value = null;
  private $max_value = null;

  /**
   * Returns an array with x, y, width and height set
   */
  protected function barDimensions($item, $index, $start, $axis, $dataset)
  {
    $bar = [];
    $bar_x = $this->barX($item, $index, $bar, $axis, $dataset);
    if(is_null($bar_x))
      return [];

    $start = $item->value;
    $value = $item->end - $start;
    $y_pos = $this->barY($value, $bar, $start, $axis);
    if(is_null($y_pos))
      return [];
    return $bar;
  }

  /**
   * Override to replace value
   */
  protected function setTooltip(&$element, &$item, $dataset, $key, $value = null,
    $duplicate = false)
  {
    $value = $item->end - $item->value;
    return parent::setTooltip($element, $item, $dataset, $key, $value, $duplicate);
  }

  /**
   * Returns the maximum bar end
   */
  public function getMaxValue()
  {
    if(!is_null($this->max_value))
      return $this->max_value;
    $max = null;
    foreach($this->values[0] as $item) {
      $s = $item->value;
      $e = $item->end;
      if(is_null($s) || is_null($e))
        continue;
      $m = max($s, $e);
      if(is_null($max) || $m > $max)
        $max = $m;
    }
    return ($this->max_value = $max);
  }

  /**
   * Returns the minimum bar end
   */
  public function getMinValue()
  {
    if(!is_null($this->min_value))
      return $this->min_value;
    $min = null;
    foreach($this->values[0] as $item) {
      $s = $item->value;
      $e = $item->end;
      if(is_null($s) || is_null($e))
        continue;
      $m = min($s, $e);
      if(is_null($min) || $m < $min)
        $min = $m;
    }
    return ($this->min_value = $min);
  }
}

