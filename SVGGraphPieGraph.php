<?php
/**
 * Copyright (C) 2009-2017 Graham Breach
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
  protected $full_angle; // amount of pie in radians
  protected $calc_done;
  protected $slice_info = array();
  protected $total = 0;
  protected $repeated_keys = 'accept';

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

    $this->x_centre = (($bound_x_right - $bound_x_left) / 2) + $bound_x_left;
    $this->y_centre = (($bound_y_bottom - $bound_y_top) / 2) + $bound_y_top;
    $this->start_angle %= 360;
    while($this->start_angle < 0)
      $this->start_angle += 360;
    $this->s_angle = deg2rad($this->start_angle);

    // sanitize aspect ratio
    if($this->aspect_ratio != 'auto' && $this->aspect_ratio <= 0)
      $this->aspect_ratio = 1.0;

    if(is_null($this->end_angle) || !is_numeric($this->end_angle) ||
      $this->end_angle == $this->start_angle ||
      abs($this->end_angle - $this->start_angle) % 360 == 0) {
      $this->full_angle = M_PI * 2.0;

      $this->SetupAspectRatio($w, $h);

    } else {

      while($this->end_angle < $this->start_angle)
        $this->end_angle += 360;
      $full_angle = $this->end_angle - $this->start_angle;
      if($full_angle > 360)
        $full_angle %= 360;
      $this->full_angle = deg2rad($full_angle);

      if($this->slice_fit) {
        // not a full pie, position based on actual shape
        $sw = 100;
        $sh = 100;
        if($this->aspect_ratio != 'auto')
          $sw /= $this->aspect_ratio;

        $all_slice = new SVGGraphSliceInfo($this->s_angle,
          deg2rad($this->end_angle), $sw, $sh);
        $bbox = $all_slice->BoundingBox($this->reverse);

        $bw = $bbox[2] - $bbox[0];
        $bh = $bbox[3] - $bbox[1];
        $scale_x = $bw / $w;
        $scale_y = $bh / $h;

        if($this->aspect_ratio == 'auto') {
          $this->x_centre = $bound_x_left + ($bbox[0] / -$scale_x);
          $this->y_centre = $bound_y_top + ($bbox[1] / -$scale_y);
          $w *= $scale_x;
          $h *= $scale_y;

          $this->aspect_ratio = $bh / $bw;
        } else {

          // calculate size and position from aspect ratio
          $scale_x = $scale_y = max($scale_x, $scale_y);
          $bw = ($bbox[2] - $bbox[0]) / $scale_x;
          $bh = ($bbox[3] - $bbox[1]) / $scale_y;
          $offset_x = $bbox[0] / -$scale_x;
          $offset_y = $bbox[1] / -$scale_y;

          $this->x_centre = $bound_x_left + ($w - $bw) / 2 + $offset_x;
          $this->y_centre = $bound_y_top + ($h - $bh) / 2 + $offset_y;
        }
        $this->radius_x = $sw / $scale_x;
        $this->radius_y = $sh / $scale_y;
      } else {
        $this->SetupAspectRatio($w, $h);
      }
    }

    $this->calc_done = true;
    $this->sub_total = 0;
    $this->ColourSetup($this->values->ItemsCount());
  }

  /**
   * Sets the aspect ratio and radius members
   */
  private function SetupAspectRatio($w, $h)
  {
    if($this->aspect_ratio == 'auto')
      $this->aspect_ratio = $h/$w;

    if($h / $w > $this->aspect_ratio) {
      $this->radius_x = $w / 2.0;
      $this->radius_y = $this->radius_x * $this->aspect_ratio;
    } else {
      $this->radius_y = $h / 2.0;
      $this->radius_x = $this->radius_y / $this->aspect_ratio;
    }
  }

  /**
   * Draws the pie graph
   */
  protected function Draw()
  {
    if(!$this->calc_done)
      $this->Calc();

    $min_slice_angle = $this->ArrayOption($this->data_label_min_space, 0);
    $vcount = 0;

    // need to store the original position of each value, because the
    // sorted list must still refer to the relevant legend entries
    $values = array();
    foreach($this->values[0] as $position => $item) {
      $values[] = array($position, $item->value, $item);
      if(!is_null($item->value))
        ++$vcount;
    }
    if($this->sort)
      uasort($values, 'pie_rsort');

    $body = $this->UnderShapes();
    $slice = 0;
    $slices = array();
    $slice_no = 0;
    foreach($values as $value) {

      // get the original array position of the value
      $original_position = $value[0];
      $item = $value[2];
      $value = $value[1];
      $key = $item->key;
      $colour_index = $this->keep_colour_order ? $original_position : $slice;
      if($this->legend_show_empty || $item->value != 0) {
        $attr = array('fill' => $this->GetColour($item, $colour_index, NULL,
          true, true));
        $this->SetStroke($attr, $item, 0, 'round');
        
        // use the original position for legend index
        $this->SetLegendEntry(0, $original_position, $item, $attr);
        ++$slice;
      }

      if(!$this->GetSliceInfo($slice_no++, $item, $angle_start, $angle_end,
        $radius_x, $radius_y))
        continue;

      // store details for label position and tail
      $this->slice_info[$original_position] = new SVGGraphSliceInfo($angle_start,
        $angle_end, $radius_x, $radius_y);

      $parts = array();
      if($this->show_label_key) {
        $label_key = $this->GetKey($this->values->AssociativeKeys() ?
          $original_position : $key);
        if($this->datetime_keys) {
          $dt = new DateTime("@{$label_key}");
          $label_key = $dt->Format($this->data_label_datetime_format);
        }
        $parts = explode("\n", $label_key);
      }
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
  
        $this->CalcSlice($angle_start, $angle_end, $radius_x, $radius_y,
          $x1, $y1, $x2, $y2);
        $single_slice = ($vcount == 1) || 
          ((string)$x1 == (string)$x2 && (string)$y1 == (string)$y2 &&
            (string)$angle_start != (string)$angle_end);

        if($this->semantic_classes)
          $attr['class'] = "series0";

        $this_slice = array(
          'original_position' => $original_position,
          'attr' => $attr,
          'item' => $item,
          'angle_start' => $angle_start,
          'angle_end' => $angle_end,
          'radius_x' => $radius_x,
          'radius_y' => $radius_y,
          'single_slice' => $single_slice,
          'colour_index' => $colour_index,
        );
        if($single_slice)
          array_unshift($slices, $this_slice);
        else
          $slices[] = $this_slice;
      }
    }

    $series = $this->DrawSlices($slices);
    if($this->semantic_classes)
      $series = $this->Element('g', array('class' => 'series'), NULL, $series);
    $body .= $series;

    $body .= $this->OverShapes();
    $extras = $this->PieExtras();
    return $body . $extras;
  }

  /**
   * Returns the SVG markup to draw all slices
   */
  protected function DrawSlices($slice_list)
  {
    $slices = array();
    foreach($slice_list as $slice) {
      $item = $slice['item'];

      if($this->show_tooltips)
        $this->SetTooltip($slice['attr'], $item, 0, $item->key, $item->value,
          !$this->compat_events);
      $path = $this->GetSlice($item,
        $slice['angle_start'], $slice['angle_end'],
        $slice['radius_x'], $slice['radius_y'],
        $slice['attr'], $slice['single_slice'], $slice['colour_index']);
      $this_slice = $this->GetLink($item, $item->key, $path);
      $slices[] = $this_slice;
    }
    return implode($slices);
  }

  /**
   * Returns a single slice of pie
   */
  protected function GetSlice($item, $angle_start, $angle_end,
    $radius_x, $radius_y, &$attr, $single_slice, $colour_index)
  {
    $x_start = $y_start = $x_end = $y_end = 0;
    $angle_start += $this->s_angle;
    $angle_end += $this->s_angle;
    $this->CalcSlice($angle_start, $angle_end, $radius_x, $radius_y,
      $x_start, $y_start, $x_end, $y_end);
    if($single_slice && $this->full_angle >= M_PI * 2.0) {
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

    $unit_slice = $this->full_angle / $this->total;
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
    foreach($this->values[0] as $item) {
      if(!is_null($item->value) && !is_numeric($item->value))
        throw new Exception('Non-numeric value');
      $sum += $item->value;
    }
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
  public function DrawLegendEntry($x, $y, $w, $h, $entry)
  {
    $bar = array('x' => $x, 'y' => $y, 'width' => $w, 'height' => $h);
    return $this->Element('rect', $bar, $entry->style);
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

  /**
   * Returns the bounding box for the slice, radius from 0,0
   * @return array($x1, $y1, $x2, $y2)
   */
  public function BoundingBox($reverse)
  {
    $x1 = $y1 = $x2 = $y2 = 0;
    $angle = fmod($this->end_angle - $this->start_angle, 2 * M_PI);
    $right_angle = M_PI * 0.5;

    $rx = $this->radius_x;
    $ry = $this->radius_y;
    $a1 = fmod($this->start_angle, 2 * M_PI);
    $a2 = $a1 + $angle;
    $start_sector = floor($a1 / $right_angle);
    $end_sector = floor($a2 / $right_angle);

    switch($end_sector - $start_sector) {

    case 0:
      // slice all in one sector
      $x = max(abs(cos($a1)), abs(cos($a2))) * $rx;
      $y = max(abs(sin($a1)), abs(sin($a2))) * $ry;
      switch($start_sector) {
      case 0:
        $x2 = $x;
        $y2 = $y;
        break;
      case 1:
        $x1 = -$x;
        $y2 = $y;
        break;
      case 2:
        $x1 = -$x;
        $y1 = -$y;
        break;
      case 3:
        $x2 = $x;
        $y1 = -$y;
        break;
      }
      break;

    case 1:
      // slice across two sectors
      switch($start_sector) {
      case 0:
        $x1 = cos($a2) * $rx;
        $x2 = cos($a1) * $rx;
        $y2 = $ry;
        break;
      case 1:
        $x1 = -$rx;
        $y1 = sin($a2) * $ry;
        $y2 = sin($a1) * $ry;
        break;
      case 2:
        $x1 = cos($a1) * $rx;
        $x2 = cos($a2) * $rx;
        $y1 = -$ry;
        break;
      case 3:
        $x2 = $rx;
        $y1 = sin($a1) * $ry;
        $y2 = sin($a2) * $ry;
        break;
      }
      break;

    case 2:
      // slice across three sectors
      $x1 = -$rx;
      $y1 = -$ry;
      $x2 = $rx;
      $y2 = $ry;
      switch($start_sector) {
      case 0:
        $y1 = sin($a2) * $ry;
        $x2 = cos($a1) * $rx;
        break;
      case 1:
        $x2 = cos($a2) * $rx;
        $y2 = sin($a1) * $ry;
        break;
      case 2:
        $x1 = cos($a1) * $rx;
        $y2 = sin($a2) * $ry;
        break;
      case 3:
        $x1 = cos($a2) * $rx;
        $y1 = sin($a1) * $ry;
        break;
      }
      break;

    case 3:
      // slice across four sectors
      $x = max(abs(cos($a1)), abs(cos($a2))) * $rx;
      $y = max(abs(sin($a1)), abs(sin($a2))) * $ry;
      $x1 = -$rx;
      $y1 = -$ry;
      $x2 = $rx;
      $y2 = $ry;
      switch($start_sector) {
      case 0: $x2 = $x; break;
      case 1: $y2 = $y; break;
      case 2: $x1 = -$x; break;
      case 3: $y1 = -$y; break;
      }
      break;

    case 4:
      // slice is > 270 degrees and both ends in one sector
      $x1 = -$rx;
      $y1 = -$ry;
      $x2 = $rx;
      $y2 = $ry;
      break;
    }

    if($reverse) {
      // swap Y around origin
      $y = -$y1;
      $y1 = -$y2;
      $y2 = $y;
    }
    return array($x1, $y1, $x2, $y2);
  }
}

