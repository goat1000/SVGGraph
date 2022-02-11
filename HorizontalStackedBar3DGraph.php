<?php
/**
 * Copyright (C) 2022 Graham Breach
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

class HorizontalStackedBar3DGraph extends HorizontalBar3DGraph {

  use StackedBarTrait;

  public function __construct($w, $h, $settings, $fixed_settings = [])
  {
    $fixed = [ 'single_axis' => true ];
    $fixed_settings = array_merge($fixed, $fixed_settings);
    parent::__construct($w, $h, $settings, $fixed_settings);
  }

  /**
   * Trait version draws totals above or below bars, we want left and right
   */
  public function dataLabelPosition($dataset, $index, &$item, $x, $y, $w, $h,
    $label_w, $label_h)
  {
    list($pos, $target) = parent::dataLabelPosition($dataset, $index, $item,
      $x, $y, $w, $h, $label_w, $label_h);
    if(!is_numeric($dataset)) {
      list($d) = explode('-', $dataset);
      if($d === 'totalpos') {
        if(isset($this->last_position_pos[$index])) {
          list($lpos, $l_w) = $this->last_position_pos[$index];
          list($hpos, $vpos) = Graph::translatePosition($lpos);
          if($hpos == 'or') {
            $num_offset = new Number($l_w);
            return ['middle outside right ' . $num_offset . ' 0', $target];
          }
        }
        return ['outside right', $target];
      }
      if($d === 'totalneg') {
        if(isset($this->last_position_neg[$index])) {
          list($lpos, $l_w) = $this->last_position_neg[$index];
          list($hpos, $vpos) = Graph::translatePosition($lpos);
          if($hpos == 'ol') {
            $num_offset = new Number(-$l_w);
            return ['middle outside left ' . $num_offset . ' 0', $target];
          }
        }
        return ['outside left', $target];
      }
    }
    if($label_w > $w && Graph::isPositionInside($pos))
      $pos = str_replace(['outside left','outside right'], 'centre', $pos);

    if($item->value > 0)
      $this->last_position_pos[$index] = [$pos, $label_w];
    else
      $this->last_position_neg[$index] = [$pos, $label_w];
    return [$pos, $target];
  }

  /**
   * Returns the ordering for legend entries
   */
  public function getLegendOrder()
  {
    // bars are stacked from left to right
    return null;
  }
}

