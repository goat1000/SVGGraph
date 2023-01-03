<?php
/**
 * Copyright (C) 2019-2022 Graham Breach
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

trait PolarAreaTrait {

  protected $slice_angle;
  protected $radius_factor_x;
  protected $radius_factor_y;

  /**
   * Sets up the polar graph details
   */
  protected function calc()
  {
    parent::calc();

    $dt = $this->getOption('datetime_keys');
    $max_value = $this->values->getMaxValue($this->dataset);
    $num_values = $dt ? $this->values->itemsCount($this->dataset) :
      $this->values->getMaxKey($this->dataset) + 1;
    $smax = sqrt($max_value);
    $this->radius_factor_x = $this->radius_x / $smax;
    $this->radius_factor_y = $this->radius_y / $smax;
    $this->slice_angle = 2.0 * M_PI / $num_values;
  }

  /**
   * Sets up the angles and radii for slice
   */
  protected function getSliceInfo($num, $item, &$angle_start, &$angle_end,
    &$radius_x, &$radius_y)
  {
    $angle_start = $num * $this->slice_angle;
    $angle_end = ($num + 1) * $this->slice_angle;

    $radius_x = $radius_y = 0;
    if($item->value) {
      $sval = sqrt((float)$item->value);
      $radius_x = $this->radius_factor_x * $sval;
      $radius_y = $this->radius_factor_y * $sval;
    }
    return true;
  }

  /**
   * Returns the position for the label
   */
  public function dataLabelPosition($dataset, $index, &$item, $x, $y, $w, $h,
    $label_w, $label_h)
  {
    if(!isset($this->slice_info[$index]))
      return ['middle centre', [$x, $y] ];

    $ac = $this->slice_info[$index]->midAngle();
    $ab = $ac - $this->slice_info[$index]->start_angle;
    $ac += $this->s_angle;
    $sin_ac = sin($ac);
    $cos_ac = cos($ac);
    $rx = $this->slice_info[$index]->radius_x;
    $ry = $this->slice_info[$index]->radius_y;

    $x1 = $label_w / 2;
    $y1 = $label_h / 2;
    $t_radius = sqrt(pow($x1, 2) + pow($y1, 2));

    // see if the text fits in the slice
    $pos_radius = $this->getOption('label_position');
    $r1 = $pos_radius * $rx;
    $outside = false;
    if(sin($ab) * $r1 > $t_radius) {
      // place it at the label_position distance from centre
      $xc = $pos_radius * $rx * $cos_ac;
      $yc = $pos_radius * $ry * $sin_ac;
    } else {
      // find min distance that label fits in
      $h  = $t_radius / sin($ab);
      $xch = $h * $cos_ac * $this->radius_x / $this->radius_y;
      $ych = $h * $sin_ac;
      $xcr = ($rx + $t_radius) * $cos_ac;
      $ycr = ($ry + $t_radius) * $sin_ac;
      $xmax = ($this->radius_x + $t_radius) * $cos_ac;
      $ymax = ($this->radius_y + $t_radius) * $sin_ac;
      if(abs($xcr) > abs($xch) || abs($ycr) > abs($ych)) {
        $xc = $xcr;
        $yc = $ycr;
      } else {
        $xc = $xch;
        $yc = $ych;
      }
      // if the slice angle is very acute, prevent label going too far out
      if(abs($xmax) < abs($xc) || abs($ymax) < abs($yc)) {
        $xc = $xmax;
        $yc = $ymax;
      }
      $outside = true;
    }
    if($this->getOption('reverse'))
      $yc = -$yc;

    $space = 0;
    $multiplier = 0.5;
    if($pos_radius > 1 || $outside) {
      $space = $this->getOption(['data_label_space', $dataset]);
      $multiplier = 1;
    }
    $xt = ($rx + $space) * $multiplier * $cos_ac;
    $yt = ($this->getOption('reverse') ? -1 : 1) * ($ry + $space) * $multiplier * $sin_ac;
    $target = [$x + $xt, $y + $yt];
    $position = new Number($xc) . ' ' . new Number($yc);
    return [$position, $target];
  }
}

