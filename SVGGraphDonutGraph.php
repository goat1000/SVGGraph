<?php
/**
 * Copyright (C) 2014 Graham Breach
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

class DonutGraph extends PieGraph {

  /**
   * Override the parent to draw doughnut slice
   */
  protected function GetSlice($item, $angle_start, $angle_end, $radius_x,
    $radius_y, &$attr, $single_slice)
  {
    $x_start = $y_start = $x_end = $y_end = 0;
    $angle_start += $this->s_angle;
    $angle_end += $this->s_angle;
    $this->CalcSlice($angle_start, $angle_end, $radius_x, $radius_y,
      $x_start, $y_start, $x_end, $y_end);
    $ratio = min(0.99, max(0.01, $this->inner_radius));
    $xc = $this->x_centre;
    $yc = $this->y_centre;
    $rx1 = $radius_x * $ratio;
    $ry1 = $radius_y * $ratio;

    if($single_slice) {
      $x1_start = $xc + $rx1;
      $x1_end = $xc - $rx1;
      $y1_start = $y1_end = $yc;
      $x2_start = $xc + $radius_x;
      $x2_end = $xc - $radius_x;
      $y2_start = $y2_end = $yc;
      // path with ellipses made of arcs
      $attr['d'] = "M{$x1_start},{$y1_start}" .
        "A{$rx1} {$ry1} 0 0 0 $x1_end,$y1_end" .
        "A{$rx1} {$ry1} 0 0 0 $x1_start,$y1_start" .
        "M$x2_start,$y2_start " .
        "A{$radius_x} {$radius_y} 0 0 0 $x2_end,$y2_end " .
        "A{$radius_x} {$radius_y} 0 0 0 $x2_start,$y2_start ";
      $attr['fill-rule'] = "evenodd";
    } else {
      $outer = ($angle_end - $angle_start > M_PI ? 1 : 0);
      $sweep = ($this->reverse ? 0 : 1);

      $x1_start = $xc + (($x_start - $xc) * $ratio);
      $x1_end = $xc + (($x_end - $xc) * $ratio);
      $y1_start = $yc + (($y_start -$yc) * $ratio);
      $y1_end = $yc + (($y_end - $yc) * $ratio);
      $isweep = $sweep ? 0 : 1;
      $attr['d'] = "M{$x1_end},{$y1_end}" .
        "A{$rx1} {$ry1} 0 $outer,$isweep $x1_start,$y1_start" .
        "L$x_start,$y_start " .
        "A{$radius_x} {$radius_y} 0 $outer,$sweep $x_end,$y_end z";
    }
    return $this->Element('path', $attr);
  }

  /**
   * Returns extra drawing code that goes between pie and labels
   */
  protected function PieExtras()
  {
    if(empty($this->inner_text))
      return '';

    // use content label for inner text - measurements don't really matter
    $this->AddContentLabel('innertext', 0,
      $this->x_centre - 100, $this->y_centre - 100, 200, 200,
      $this->inner_text);
    return '';
  }

  /**
   * Overridden to keep inner text in the middle
   */
  public function DataLabelPosition($dataset, $index, &$item, $x, $y, $w, $h,
    $label_w, $label_h)
  {
    if($dataset === 'innertext')
      return 'centre middle';

    return parent::DataLabelPosition($dataset, $index, $item, $x, $y, $w, $h,
      $label_w, $label_h);
  }

  /**
   * Returns the style options for the inner text label
   */
  public function DataLabelStyle($dataset, $index, &$item)
  {
    $style = parent::DataLabelStyle($dataset, $index, $item);

    if($dataset !== 'innertext')
      return $style;

    // label settings can override global settings
    $opts = array(
      'font' => 'inner_text_font',
      'font_size' => 'inner_text_font_size',
      'font_weight' => 'inner_text_font_weight',
      'font_adjust' => 'inner_text_font_adjust',
      'colour' => 'inner_text_colour',
      'back_colour' => 'inner_text_back_colour',
    );
    foreach($opts as $key => $opt)
      if(isset($this->settings[$opt]) && !empty($this->settings[$opt]))
        $style[$key] = $this->settings[$opt];

    // no boxes
    $style['type'] = 'plain';

    return $style;
  }
}

