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

require_once 'SVGGraphDonutGraph.php';

class SemiDonutGraph extends DonutGraph {

  /**
   * Override to set the options for semi-ness
   */
  public function __construct($w, $h, $settings = NULL)
  {
    if(is_null($settings))
      $settings = array();

    $reverse = isset($settings['reverse']) && $settings['reverse'];
    $flipped = isset($settings['flipped']) && $settings['flipped'];

    $start = 180;
    if($flipped) {
      $start = 0;
      $reverse = !$reverse;
    }
    if($reverse) {
      $start += 180;
    }
    $settings['reverse'] = $reverse;
    $settings['start_angle'] = $start;
    $settings['end_angle'] = $settings['start_angle'] + 180;
    $settings['slice_fit'] = true;

    parent::__construct($w, $h, $settings);
  }

  /**
   * Overridden to keep inner text in the middle
   */
  public function DataLabelPosition($dataset, $index, &$item, $x, $y, $w, $h,
    $label_w, $label_h)
  {
    if($dataset === 'innertext') {
      $y_offset = ($this->flipped ? 1 : -1) * $label_h / 2;
      return "centre middle 0 {$y_offset}";
    }

    return parent::DataLabelPosition($dataset, $index, $item, $x, $y, $w, $h,
      $label_w, $label_h);
  }

}

