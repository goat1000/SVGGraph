<?php
/**
 * Copyright (C) 2009-2015 Graham Breach
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

class PieGraph extends Graph {

  // for internal use
  protected $x_centre;
  protected $y_centre;
  protected $radius_x;
  protected $radius_y;
  protected $s_angle; // start_angle in radians
  protected $calc_done;
  protected $slice_styles = array();
  protected $slice_info = array();
  protected $total = 0;

  private $sub_total = 0;

  public function __construct($w, $h, $settings = NULL)
  {
    // backwards compatibility
    $copy = array(
      'show_labels' => 'show_data_labels',
      'label_fade_in_speed' => 'data_label_fade_in_speed',
      'label_fade_out_speed' => 'data_label_fade_out_speed',
    );
    foreach($copy as $from => $to)
      if(isset($settings[$from]))
        $settings[$to] = $settings[$from];

    parent::__construct($w, $h, $settings);
  }

  /**
   * Calculates position of pie
   */
  protected function Calc()
  {
    $bound_x_left = $this->pad_left;
    $bound_y_top = $this->pad_top;
    $bound_x_right = $this->width - $this->pad_right;
    $bound_y_bottom = $this->height - $this->pad_bottom;

    $w = $bound_x_right - $bound_x_left;
    $h = $bound_y_bottom - $bound_y_top;

    if($this->aspect_ratio == 'auto')
      $this->aspect_ratio = $h/$w;
    elseif($this->aspect_ratio <= 0)
      $this->aspect_ratio = 1.0;

    $this->x_centre = (($bound_x_right - $bound_x_left) / 2) + $bound_x_left;
    $this->y_centre = (($bound_y_bottom - $bound_y_top) / 2) + $bound_y_top;
    $this->start_angle %= 360;
    if($this->start_angle < 0)
      $this->start_angle = 360 + $this->start_angle;
    $this->s_angle = deg2rad($this->start_angle);

    if($h/$w > $this->aspect_ratio) {
      $this->radius_x = $w / 2.0;
      $this->radius_y = $this->radius_x * $this->aspect_ratio;
    } else {
      $this->radius_y = $h / 2.0;
      $this->radius_x = $this->radius_y / $this->aspect_ratio;
    }
    $this->calc_done = true;
    $this->sub_total = 0;
    $this->ColourSetup($this->values->ItemsCount());
  }

  /**
   * Draws the pie graph
   */
  protected function Draw()
  {
    if(!$this->calc_done)
      $this->Calc();

    $unit_slice = 2.0 * M_PI / $this->total;
    $min_slice_angle = $this->ArrayOption($this->data_label_min_space, 0);
    $vcount = 0;

    // need to store the original position of each value, because the
    // sorted list must still refer to the relevant legend entries
    $position = 0;
    $values = array();
    foreach($this->values[0] as $item) {
      $values[$item->key] = array($position++, $item->value, $item);
      if(!is_null($item->value))
        ++$vcount;
    }
    if($this->sort)
      uasort($values, 'pie_rsort');

    $body = '';
    $slice = 0;
    $slices = array();
    $slice_no = 0;
    foreach($values as $key => $value) {

      // get the original array position of the value
      $original_position = $value[0];
      $item = $value[2];
      $value = $value[1];
      if($this->legend_show_empty || $item->value != 0) {
        $attr = array('fill' => $this->GetColour($item, $slice, NULL, true,
          true));
        $this->SetStroke($attr, $item, 0, 'round');

        // store the current style referenced by the original position
        $this->slice_styles[$original_position] = $attr;
        ++$slice;
      }

      if(!$this->GetSliceInfo($slice_no++, $item, $angle_start, $angle_end,
        $radius_x, $radius_y))
        continue;

      // store details for label position and tail
      $this->slice_info[$original_position] = new SVGGraphSliceInfo($angle_start,
        $angle_end, $radius_x, $radius_y);

      $parts = array();
      if($this->show_label_key)
        $parts = explode("\n", $this->GetKey($this->values->AssociativeKeys() ? 
          $original_position : $key));
      if($this->show_label_amount)
        $parts[] = $this->units_before_label . Graph::NumString($value) .
          $this->units_label;
      if($this->show_label_percent)
        $parts[] = Graph::NumString($value / $this->total * 100.0,
          $this->label_percent_decimals) . '%';
      $label_content = implode("\n", $parts);

      // add the data label if the slice angle is big enough
      if($this->slice_info[$original_position]->Degrees() >= $min_slice_angle) {
        $this->AddDataLabel(0, $original_position, $attr, $item,
          $this->x_centre, $this->y_centre, 1, 1, $label_content);
      }

      if($radius_x || $radius_y) {
        if($this->show_tooltips)
          $this->SetTooltip($attr, $item, 0, $key, $value, !$this->compat_events);
  
        $this->CalcSlice($angle_start, $angle_end, $radius_x, $radius_y,
          $x1, $y1, $x2, $y2);
        $single_slice = ($vcount == 1) || 
          ((string)$x1 == (string)$x2 && (string)$y1 == (string)$y2 &&
            (string)$angle_start != (string)$angle_end);

        if($this->semantic_classes)
          $attr['class'] = "series0";
        $path = $this->GetSlice($item, $angle_start, $angle_end,
          $radius_x, $radius_y, $attr, $single_slice);
        $this_slice = $this->GetLink($item, $key, $path);
        if($single_slice)
          array_unshift($slices, $this_slice);
        else
          $slices[] = $this_slice;
      }
    }

    $series = implode($slices);
    if($this->semantic_classes)
      $series = $this->Element('g', array('class' => 'series'), NULL, $series);
    $body .= $series;

    $extras = $this->PieExtras();
    return $body . $extras;
  }

  /**
   * Returns a single slice of pie
   */
  protected function GetSlice($item, $angle_start, $angle_end, $radius_x, $radius_y,
    &$attr, $single_slice)
  {
    $x_start = $y_start = $x_end = $y_end = 0;
    $angle_start += $this->s_angle;
    $angle_end += $this->s_angle;
    $this->CalcSlice($angle_start, $angle_end, $radius_x, $radius_y,
      $x_start, $y_start, $x_end, $y_end);
    if($single_slice) {
      $attr['cx'] = $this->x_centre;
      $attr['cy'] = $this->y_centre;
      $attr['rx'] = $radius_x;
      $attr['ry'] = $radius_y;
      return $this->Element('ellipse', $attr);
    } else {
      $outer = ($angle_end - $angle_start > M_PI ? 1 : 0);
      $sweep = ($this->reverse ? 0 : 1);
      $attr['d'] = "M{$this->x_centre},{$this->y_centre} L$x_start,$y_start " .
        "A{$radius_x} {$radius_y} 0 $outer,$sweep $x_end,$y_end z";
      return $this->Element('path', $attr);
    }
  }

  /**
   * Calculates start and end points of slice
   */
  protected function CalcSlice($angle_start, $angle_end, $radius_x, $radius_y,
    &$x_start, &$y_start, &$x_end, &$y_end)
  {
    $x_start = ($radius_x * cos($angle_start));
    $y_start = ($this->reverse ? -1 : 1) *
      ($radius_y * sin($angle_start));
    $x_end = ($radius_x * cos($angle_end));
    $y_end = ($this->reverse ? -1 : 1) *
      ($radius_y * sin($angle_end));

    $x_start += $this->x_centre;
    $y_start += $this->y_centre;
    $x_end += $this->x_centre;
    $y_end += $this->y_centre;
  }

  /**
   * Finds the angles and radii for a slice
   */
  protected function GetSliceInfo($num, $item, &$angle_start, &$angle_end,
    &$radius_x, &$radius_y)
  {
    if(!$item->value)
      return false;

    $unit_slice = 2.0 * M_PI / $this->total;
    $angle_start = $this->sub_total * $unit_slice;
    $angle_end = ($this->sub_total + $item->value) * $unit_slice;
    $radius_x = $this->radius_x;
    $radius_y = $this->radius_y;

    $this->sub_total += $item->value;
    return true;
  }

  /**
   * Checks that the data are valid
   */
  protected function CheckValues()
  {
    parent::CheckValues();
    if($this->GetMinValue() < 0)
      throw new Exception('Negative value for pie chart');

    $sum = 0;
    foreach($this->values[0] as $item)
      $sum += $item->value;
    if($sum <= 0)
      throw new Exception('Empty pie chart');

    $this->total = $sum;
  }

  /**
   * Returns extra drawing code that goes between pie and labels
   */
  protected function PieExtras()
  {
    return '';
  }

  /**
   * Return box for legend
   */
  protected function DrawLegendEntry($set, $x, $y, $w, $h)
  {
    if(!isset($this->slice_styles[$set]))
      return '';

    $bar = array('x' => $x, 'y' => $y, 'width' => $w, 'height' => $h);
    return $this->Element('rect', $bar, $this->slice_styles[$set]);
  }

  /**
   * Returns the position for the label
   */
  public function DataLabelPosition($dataset, $index, &$item, $x, $y, $w, $h,
    $label_w, $label_h)
  {
    if(isset($this->slice_info[$index])) {
      $a = $this->slice_info[$index]->MidAngle();
      $rx = $this->slice_info[$index]->radius_x;
      $ry = $this->slice_info[$index]->radius_y;

      // place it at the label_position distance from centre
      $ac = $this->s_angle + $a;
      $xc = $this->label_position * $rx * cos($ac);
      $yc = ($this->reverse ? -1 : 1) * $this->label_position * $ry * sin($ac);
      $pos = "$xc $yc";
    } else {
      $pos = 'middle centre';
    }
    return $pos;
  }

  /**
   * Returns the style options for bar labels
   */
  public function DataLabelStyle($dataset, $index, &$item)
  {
    $style = parent::DataLabelStyle($dataset, $index, $item);

    // old pie label settings can override global data_label settings
    $opts = array(
      'font' => 'label_font',
      'font_size' => 'label_font_size',
      'font_weight' => 'label_font_weight',
      'colour' => 'label_colour',
      'back_colour' => 'label_back_colour',
    );
    foreach($opts as $key => $opt)
      if(isset($this->settings[$opt]))
        $style[$key] = $this->settings[$opt];

    return $style;
  }

  /**
   * Overload to return the firection of the pie centre
   */
  public function DataLabelTailDirection($dataset, $index, $hpos, $vpos)
  {
    if(isset($this->slice_info[$index])) {
      $a = rad2deg($this->slice_info[$index]->MidAngle());
      // tail direction is opposite slice direction
      if($this->reverse)
        return (900 - $this->start_angle - $a) % 360; // 900 == 360 + 360 + 180
      else
        return (180 + $this->start_angle + $a) % 360;
    }

    // fall back to default
    return parent::DataLabelTailDirection($dataset, $index, $hpos, $vpos);
  }
}

/**
 *  Sort callback function reverse-sorts by value
 */
function pie_rsort($a, $b)
{
  return $b[1] - $a[1];
}


/**
 * Class for details of each pie slice
 */
class SVGGraphSliceInfo {
  public $start_angle;
  public $end_angle;
  public $radius_x;
  public $radius_y;

  public function __construct($start, $end, $rx, $ry)
  {
    $this->start_angle = $start;
    $this->end_angle = $end;
    $this->radius_x = $rx;
    $this->radius_y = $ry;
  }

  /*
   * Calculates the middle angle of the slice
   */
  public function MidAngle()
  {
    return $this->start_angle + ($this->end_angle - $this->start_angle) / 2;
  }

  /**
   * Returns the slice angle in degrees
   */
  public function Degrees()
  { 
    return rad2deg($this->end_angle - $this->start_angle);
  }
}

