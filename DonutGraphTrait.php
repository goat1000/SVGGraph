<?php
/**
 * Copyright (C) 2021-2022 Graham Breach
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

trait DonutGraphTrait {

  /**
   * Returns the outer and inner angle adjustments for the slice gap
   */
  public function getSliceGap($angle, $ratio)
  {
    $gap_angle = $this->getOption('donut_slice_gap');
    $outer_a = $inner_a = 0;
    if($gap_angle > 0) {
      $a = deg2rad($gap_angle);
      if($a < $angle * 0.5) {
        $outer_a = 0.5 * $a;
        $inner_a = $outer_a / $ratio;
      }
    }
    return [$outer_a, $inner_a];
  }

  /**
   * Override the parent to draw doughnut slice
   */
  protected function getSlice($item, $angle_start, $angle_end, $radius_x,
    $radius_y, &$attr, $single_slice, $colour_index)
  {
    $ratio = min(0.99, max(0.01, $this->getOption('inner_radius')));
    $angle = $angle_end - $angle_start;

    list($outer_a, $inner_a) = $this->getSliceGap($angle, $ratio);
    $x_start = $y_start = $x_end = $y_end = 0;
    $angle_start += $this->s_angle;
    $angle_end += $this->s_angle;
    $this->calcSlice($angle_start + $outer_a, $angle_end - $outer_a,
      $radius_x, $radius_y, $x_start, $y_start, $x_end, $y_end);
    $xc = $this->x_centre;
    $yc = $this->y_centre;
    $rx1 = $radius_x * $ratio;
    $ry1 = $radius_y * $ratio;

    if($single_slice && $this->full_angle >= M_PI * 2.0) {
      $x1_start = $xc + $rx1;
      $x1_end = $xc - $rx1;
      $y1_start = $y1_end = $yc;
      $x2_start = $xc + $radius_x;
      $x2_end = $xc - $radius_x;
      $y2_start = $y2_end = $yc;
      // path with ellipses made of arcs
      $path = new PathData('M', $x1_start, $y1_start);
      $path->add('A', $rx1, $ry1, 0, 0, 0, $x1_end, $y1_end);
      $path->add('A', $rx1, $ry1, 0, 0, 0, $x1_start, $y1_start);
      $path->add('M', $x2_start, $y2_start);
      $path->add('A', $radius_x, $radius_y, 0, 0, 0, $x2_end, $y2_end);
      $path->add('A', $radius_x, $radius_y, 0, 0, 0, $x2_start, $y2_start);
      $attr['d'] = $path;
      $attr['fill-rule'] = 'evenodd';
    } else {
      $outer = ($angle > M_PI ? 1 : 0);
      $sweep = ($this->getOption('reverse') ? 0 : 1);

      // inner radius reduced by gap
      $as = $angle_start + $inner_a;
      $ae = $angle_end - $inner_a;
      if($ae < $as)
        $ae = $as = ($ae + $as) / 2; // not enough space, so come to a point

      $this->calcSlice($as, $ae, $rx1, $ry1, $x1_start, $y1_start, $x1_end, $y1_end);
      $isweep = $sweep ? 0 : 1;
      $path = new PathData('M', $x1_end, $y1_end);
      $path->add('A', $rx1, $ry1, 0, $outer, $isweep, $x1_start, $y1_start);
      $path->add('L', $x_start, $y_start);
      $path->add('A', $radius_x, $radius_y, 0, $outer, $sweep, $x_end, $y_end, 'z');
      $attr['d'] = $path;
    }
    return $this->element('path', $attr);
  }

  /**
   * Returns extra drawing code that goes between pie and labels
   */
  protected function pieExtras()
  {
    $inner_text = $this->getOption('inner_text');
    if(empty($inner_text))
      return '';

    // use content label for inner text - measurements don't really matter
    $this->addContentLabel('innertext', 0,
      $this->x_centre - 100, $this->y_centre - 100, 200, 200,
      $inner_text);
    return '';
  }

  /**
   * Overridden to keep inner text in the middle
   */
  public function dataLabelPosition($dataset, $index, &$item, $x, $y, $w, $h,
    $label_w, $label_h)
  {
    if($dataset === 'innertext')
      return ['centre middle', [$x, $y] ];

    list($pos, $target) = parent::dataLabelPosition($dataset, $index, $item,
      $x, $y, $w, $h, $label_w, $label_h);
    if(isset($this->slice_info[$index]) && $this->getOption('label_position') <= 1) {
      $a = $this->slice_info[$index]->midAngle();
      $ac = $this->s_angle + $a;
      $rx = $this->slice_info[$index]->radius_x;
      $ry = $this->slice_info[$index]->radius_y;
      $ring_centre = ($this->getOption('inner_radius') + 1) * 0.5;
      $xt = $rx * $ring_centre * cos($ac);
      $yt = ($this->getOption('reverse') ? -1 : 1) * $ry * $ring_centre * sin($ac);
      $target = [$x + $xt, $y + $yt];
    }
    return [$pos, $target];
  }

  /**
   * Returns the style options for the inner text label
   */
  public function dataLabelStyle($dataset, $index, &$item)
  {
    $style = parent::dataLabelStyle($dataset, $index, $item);

    if($dataset !== 'innertext')
      return $style;

    // label settings can override global settings
    $opts = [
      'font' => 'inner_text_font',
      'font_size' => 'inner_text_font_size',
      'font_weight' => 'inner_text_font_weight',
      'font_adjust' => 'inner_text_font_adjust',
      'colour' => 'inner_text_colour',
      'back_colour' => 'inner_text_back_colour',
    ];
    foreach($opts as $key => $opt)
      if(isset($this->settings[$opt]) && !empty($this->settings[$opt]))
        $style[$key] = $this->settings[$opt];

    // no boxes
    $style['type'] = 'plain';
    return $style;
  }
}

