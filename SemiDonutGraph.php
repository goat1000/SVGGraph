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

class SemiDonutGraph extends DonutGraph {

  /**
   * Override to set the options for semi-ness
   */
  public function __construct($w, $h, array $settings, array $fixed_settings = [])
  {
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

    $fixed = [
      'reverse' => $reverse,
      'start_angle' => $start,
      'end_angle' => $start + 180,
      'slice_fit' => true
    ];
    $fixed_settings = array_merge($fixed, $fixed_settings);
    parent::__construct($w, $h, $settings, $fixed_settings);
  }

  /**
   * Overridden to keep inner text in the middle
   */
  public function dataLabelPosition($dataset, $index, &$item, $x, $y, $w, $h,
    $label_w, $label_h)
  {
    if($dataset === 'innertext') {
      if($this->getOption('flipped'))
        $y_offset = new Number($label_h / 2);
      else
        $y_offset = new Number($label_h / -2);
      return ['centre middle 0 ' . $y_offset, [$x, $y] ];
    }

    return parent::dataLabelPosition($dataset, $index, $item, $x, $y, $w, $h,
      $label_w, $label_h);
  }
}

