<?php
/**
 * Copyright (C) 2014-2015 Graham Breach
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

require_once 'SVGGraphPieGraph.php';

class ExplodedPieGraph extends PieGraph {

  protected $largest_value;
  protected $smallest_value;

  /**
   * Calculates reduced radius of pie
   */
  protected function Calc()
  {
    parent::Calc();
    $this->explode_amount = min($this->radius_x - 10, $this->radius_y - 10,
      max(2, (int)$this->explode_amount));
    $this->radius_y -= $this->explode_amount;
    $this->radius_x -= $this->explode_amount;
  }

  /**
   * Returns a single slice of pie
   */
  protected function GetSlice($item, $angle_start, $angle_end, $radius_x,
    $radius_y, &$attr, $single_slice)
  {
    if($single_slice)
      return parent::GetSlice($item, $angle_start, $angle_end, $radius_x,
        $radius_y, $attr, $single_slice);

    $x_start = $y_start = $x_end = $y_end = 0;
    $angle_start += $this->s_angle;
    $angle_end += $this->s_angle;
    $this->CalcSlice($angle_start, $angle_end, $radius_x, $radius_y,
      $x_start, $y_start, $x_end, $y_end);
    $outer = ($angle_end - $angle_start > M_PI ? 1 : 0);
    $sweep = ($this->reverse ? 0 : 1);

    // find explosiveness
    list($xo, $yo) = $this->GetExplode($item, $angle_start, $angle_end);
    $xc = $this->x_centre + $xo;
    $yc = $this->y_centre + $yo;
    $x_start += $xo;
    $x_end += $xo;
    $y_start += $yo;
    $y_end += $yo;
    $attr['d'] = "M{$xc},{$yc} L$x_start,$y_start " .
      "A{$radius_x} {$radius_y} 0 $outer,$sweep $x_end,$y_end z";
    return $this->Element('path', $attr);
  }

  /**
   * Returns the x,y offset caused by explosion
   */
  protected function GetExplode($item, $angle_start, $angle_end)
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

    $a = $angle_end - $angle_start;
    $a_centre = $angle_start + ($angle_end - $angle_start) / 2;
    $xo = $explode * cos($a_centre);
    $yo = $explode * sin($a_centre);
    if($this->reverse)
      $yo = -$yo;

    return array($xo, $yo);
  }

  /**
   * Returns the position for the label
   */
  public function DataLabelPosition($dataset, $index, &$item, $x, $y, $w, $h,
    $label_w, $label_h)
  {
    $pos = parent::DataLabelPosition($dataset, $index, $item, $x, $y, $w, $h,
      $label_w, $label_h);

    if(isset($this->slice_info[$index])) {
      list($xo, $yo) = $this->GetExplode($item,
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

      $pos = "$x1 $y1";
    } else {
      $pos = 'middle centre';
    }
    return $pos;
  }

  /**
   * Checks that the data are valid
   */
  protected function CheckValues()
  {
    parent::CheckValues();

    $this->largest_value = $this->GetMaxValue();
    $this->smallest_value = $this->largest_value;

    // want smallest non-0 value
    foreach($this->values[0] as $item)
      if($item->value < $this->smallest_value)
        $this->smallest_value = $item->value;
  }

}

