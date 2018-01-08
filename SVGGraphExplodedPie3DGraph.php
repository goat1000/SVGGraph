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

require_once 'SVGGraphPie3DGraph.php';
require_once 'SVGGraphPieExploder.php';

class ExplodedPie3DGraph extends Pie3DGraph {

  protected $draw_flat_sides = true;
  protected $separate_slices = true;
  protected $pie_exploder = NULL;

  /**
   * Calculates reduced radius of pie
   */
  protected function Calc()
  {
    parent::Calc();
    $this->explode_amount = $this->pie_exploder->FixRadii($this->radius_x,
      $this->radius_y);
  }

  /**
   * Returns a single slice of pie
   */
  protected function GetSlice($item, $angle_start, $angle_end, $radius_x,
    $radius_y, &$attr, $single_slice, $colour_index)
  {
    if($single_slice)
      return parent::GetSlice($item, $angle_start, $angle_end, $radius_x,
        $radius_y, $attr, $single_slice, $colour_index);

    // find and apply explosiveness
    list($xo, $yo) = $this->pie_exploder->GetExplode($item, $angle_start +
      $this->s_angle, $angle_end + $this->s_angle);

    $translated = $attr;
    $xform = "translate({$xo},{$yo})";
    if(isset($translated['transform']))
      $translated['transform'] .= ' ' . $xform;
    else
      $translated['transform'] = $xform;
    return parent::GetSlice($item, $angle_start, $angle_end, $radius_x,
      $radius_y, $translated, $single_slice, $colour_index);
  }

  /**
   * Returns an edge markup
   */
  protected function GetEdge($edge, $x_centre, $y_centre, $depth, $overlay)
  {
    list($xo, $yo) = $this->pie_exploder->GetExplode($edge->slice['item'],
      $edge->slice['angle_start'] + $this->s_angle,
      $edge->slice['angle_end'] + $this->s_angle);
    return parent::GetEdge($edge, $x_centre + $xo, $y_centre + $yo, $depth,
      $overlay);
  }

  /**
   * Returns the position for the label
   */
  public function DataLabelPosition($dataset, $index, &$item, $x, $y, $w, $h,
    $label_w, $label_h)
  {
    list($pos, $target) = parent::DataLabelPosition($dataset, $index, $item,
      $x, $y, $w, $h, $label_w, $label_h);

    if(isset($this->slice_info[$index])) {
      list($xo, $yo) = $this->pie_exploder->GetExplode($item,
        $this->slice_info[$index]->start_angle + $this->s_angle,
        $this->slice_info[$index]->end_angle + $this->s_angle);

      list($x1, $y1) = explode(' ', $pos);
      if(is_numeric($x1) && is_numeric($y1)) {
        $x1 += $xo;
        $y1 += $yo;
      } else {
        // this shouldn't happen, but just in case
        $x1 = $this->centre_x + $xo;
        $y1 = $this->centre_y + $yo;
      }

      // explode target position too
      $target[0] += $xo;
      $target[1] += $yo;

      $pos = "$x1 $y1";
    } else {
      $pos = 'middle centre';
    }
    return array($pos, $target);
  }

  /**
   * Checks that the data are valid
   */
  protected function CheckValues()
  {
    parent::CheckValues();

    $largest_value = $this->GetMaxValue();
    $smallest_value = $largest_value;

    // want smallest non-0 value
    foreach($this->values[0] as $item)
      if($item->value < $smallest_value)
        $smallest_value = $item->value;

    $this->pie_exploder = new PieExploder($this->settings, $this, 
      $smallest_value, $largest_value);
  }

}

