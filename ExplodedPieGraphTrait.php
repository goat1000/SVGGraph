<?php
/**
 * Copyright (C) 2021-2023 Graham Breach
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

trait ExplodedPieGraphTrait {

  protected $pie_exploder = null;
  protected $explode_amount = 0;

  /**
   * Calculates reduced radius of pie
   */
  protected function calc()
  {
    parent::calc();
    $this->explode_amount = $this->pie_exploder->fixRadii($this->radius_x,
      $this->radius_y);
  }

  /**
   * Returns a single slice of pie
   */
  protected function getSlice($item, $angle_start, $angle_end, $radius_x,
    $radius_y, &$attr, $single_slice, $colour_index)
  {
    if($single_slice)
      return parent::getSlice($item, $angle_start, $angle_end, $radius_x,
        $radius_y, $attr, $single_slice, $colour_index);

    // find and apply explosiveness
    list($xo, $yo) = $this->pie_exploder->getExplode($item, $angle_start +
      $this->s_angle, $angle_end + $this->s_angle);

    $translated = $attr;
    $xform = new Transform;
    $xform->translate($xo, $yo);
    if(isset($translated['transform']))
      $translated['transform']->add($xform);
    else
      $translated['transform'] = $xform;
    return parent::getSlice($item, $angle_start, $angle_end, $radius_x,
      $radius_y, $translated, $single_slice, $colour_index);
  }


  /**
   * Returns the position for the label
   */
  public function dataLabelPosition($dataset, $index, &$item, $x, $y, $w, $h,
    $label_w, $label_h)
  {
    list($pos, $target) = parent::dataLabelPosition($dataset, $index, $item,
      $x, $y, $w, $h, $label_w, $label_h);

    if(isset($this->slice_info[$index])) {
      list($xo, $yo) = $this->pie_exploder->getExplode($item,
        $this->slice_info[$index]->start_angle + $this->s_angle,
        $this->slice_info[$index]->end_angle + $this->s_angle);

      list($x1, $y1) = explode(' ', $pos);
      if(is_numeric($x1) && is_numeric($y1)) {
        $x1 += $xo;
        $y1 += $yo;
      } else {
        $x1 = $xo;
        $y1 = $yo;
      }

      // explode target position too
      $target[0] += $xo;
      $target[1] += $yo;

      $pos = new Number($x1) . ' ' . new Number($y1);
    } else {
      $pos = 'middle centre';
    }
    return [$pos, $target];
  }

  /**
   * Checks that the data are valid
   */
  protected function checkValues()
  {
    parent::checkValues();

    $largest = $this->getMaxValue();
    $smallest = $largest;

    // want smallest non-0 value
    foreach($this->values[0] as $item)
      if($item->value < $smallest)
        $smallest = $item->value;

    $this->pie_exploder = new PieExploder($this, $smallest, $largest);
  }
}

