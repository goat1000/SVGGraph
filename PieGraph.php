<?php
/**
 * Copyright (C) 2009-2021 Graham Breach
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

class PieGraph extends Graph {

  // for internal use
  protected $x_centre;
  protected $y_centre;
  protected $radius_x;
  protected $radius_y;
  protected $s_angle; // start_angle in radians
  protected $full_angle; // amount of pie in radians
  protected $calc_done;
  protected $slice_info = [];
  protected $total = 0;
  protected $legend_order = [];
  protected $dataset = 0;

  private $sub_total = 0;

  public function __construct($w, $h, array $settings, array $fixed_settings = [])
  {
    // backwards compatibility
    $copy = [
      'show_labels' => 'show_data_labels',
      'label_fade_in_speed' => 'data_label_fade_in_speed',
      'label_fade_out_speed' => 'data_label_fade_out_speed',
    ];
    foreach($copy as $from => $to)
      if(isset($settings[$from]) && !isset($settings[$to]))
        $settings[$to] = $settings[$from];

    $fs = ['repeated_keys' => 'accept'];
    $fs = array_merge($fs, $fixed_settings);
    parent::__construct($w, $h, $settings, $fs);
  }

  /**
   * Calculates position of pie
   */
  protected function calc()
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

    if($this->end_angle === null || !is_numeric($this->end_angle) ||
      $this->end_angle == $this->start_angle ||
      abs($this->end_angle - $this->start_angle) % 360 == 0) {
      $this->full_angle = M_PI * 2.0;

      $this->setupAspectRatio($w, $h);

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

        $all_slice = new SliceInfo($this->s_angle,
          deg2rad($this->end_angle), $sw, $sh);
        $bbox = $all_slice->boundingBox($this->reverse);

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
        $this->setupAspectRatio($w, $h);
      }
    }

    $this->calc_done = true;
    $this->sub_total = 0;
  }

  /**
   * Sets the aspect ratio and radius members
   */
  private function setupAspectRatio($w, $h)
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
  protected function draw()
  {
    if(!$this->calc_done)
      $this->calc();

    $min_slice_angle = $this->getOption(['data_label_min_space', $this->dataset]);
    $vcount = 0;

    // need to store the original position of each value, because the
    // sorted list must still refer to the relevant legend entries
    $values = [];
    foreach($this->values[$this->dataset] as $position => $item) {
      $values[] = [$position, $item->value, $item];
      if($item->value !== null)
        ++$vcount;
    }
    if($this->sort) {
      uasort($values, function($a, $b) {
        return $b[1] - $a[1];
      });
    }

    $body = $this->underShapes();
    $slice = 0;
    $slices = [];
    $slice_no = 0;
    $legend_entries = [];
    $legend_order = [];
    foreach($values as $value) {

      // get the original array position of the value
      $original_position = $value[0];
      $item = $value[2];
      $value = $value[1];
      $key = $item->key;
      $colour_index = $this->keep_colour_order ? $original_position : $slice;
      if($this->legend_show_empty || $item->value != 0) {
        $attr = [
          'fill' => $this->getColour($item, $colour_index, $this->dataset, false, true)
        ];
        $this->setStroke($attr, $item, $colour_index, $this->dataset, 'round');

        // use the original position for legend index
        $legend_entries[] = [$original_position, $item, $attr];
        $legend_order[] = $original_position;
        ++$slice;
      }

      if(!$this->getSliceInfo($slice_no++, $item, $angle_start, $angle_end,
        $radius_x, $radius_y))
        continue;

      // store details for label position and tail
      $this->slice_info[$original_position] = new SliceInfo($angle_start,
        $angle_end, $radius_x, $radius_y);

      // add the data label if the slice angle is big enough
      if($this->slice_info[$original_position]->degrees() >= $min_slice_angle) {
        $parts = [];
        if($this->show_label_key) {
          $label_key = $this->getKey($this->values->associativeKeys() ?
            $original_position : $key);
          if($this->datetime_keys) {
            $number_key = new Number($label_key);
            $dtf = new DateTimeFormatter;
            $dt = new \DateTime('@' . $number_key);
            $label_key = $dtf->format($dt, $this->data_label_datetime_format);
          }
          $parts = explode("\n", $label_key);
        }
        if($this->show_label_amount) {
          if($value === null) {
            $parts[] = '';
          } else {
            $num = new Number($value * 1.0, $this->units_label, $this->units_before_label);
            $parts[] = $num->format();
          }
        }
        if($this->show_label_percent) {
          $num = new Number($value / $this->total * 100.0, '%');
          $parts[] = $num->format($this->label_percent_decimals);
        }
        $label_content = implode("\n", $parts);

        $this->addDataLabel($this->dataset, $original_position, $attr, $item,
          $this->x_centre, $this->y_centre, 1, 1, $label_content);
      }

      if($radius_x || $radius_y) {

        $this->calcSlice($angle_start, $angle_end, $radius_x, $radius_y,
          $x1, $y1, $x2, $y2);
        $single_slice = ($vcount == 1) ||
          ((string)$x1 == (string)$x2 && (string)$y1 == (string)$y2 &&
            (string)$angle_start != (string)$angle_end);

        if($this->semantic_classes)
          $attr['class'] = 'series0';

        $this_slice = [
          'original_position' => $original_position,
          'attr' => $attr,
          'item' => $item,
          'angle_start' => $angle_start,
          'angle_end' => $angle_end,
          'radius_x' => $radius_x,
          'radius_y' => $radius_y,
          'single_slice' => $single_slice,
          'colour_index' => $colour_index,
        ];
        if($single_slice)
          array_unshift($slices, $this_slice);
        else
          $slices[] = $this_slice;
      }
    }

    // put the slices back in natural order for the legend
    usort($legend_entries, function($a, $b) { return $a[0] - $b[0]; });
    foreach($legend_entries as $e) {
      $this->setLegendEntry(0, $e[0], $e[1], $e[2]);
    }
    $this->legend_order = $legend_order;

    $group = [];
    $series = $this->drawSlices($slices);
    if($this->semantic_classes)
      $group['class'] = 'series';
    $shadow_id = $this->defs->getShadow();
    if($shadow_id !== null)
      $group['filter'] = 'url(#' . $shadow_id . ')';
    if(!empty($group))
      $series = $this->element('g', $group, null, $series);
    $body .= $series;

    $body .= $this->overShapes();
    $extras = $this->pieExtras();
    return $body . $extras;
  }

  /**
   * Returns the SVG markup to draw all slices
   */
  protected function drawSlices($slice_list)
  {
    $slices = [];
    foreach($slice_list as $slice) {
      $item = $slice['item'];

      if($this->show_tooltips)
        $this->setTooltip($slice['attr'], $item, $this->dataset, $item->key,
          $item->value, true);
      if($this->show_context_menu)
        $this->setContextMenu($slice['attr'], $this->dataset, $item, true);
      $path = $this->getSlice($item,
        $slice['angle_start'], $slice['angle_end'],
        $slice['radius_x'], $slice['radius_y'],
        $slice['attr'], $slice['single_slice'], $slice['colour_index']);
      $this_slice = $this->getLink($item, $item->key, $path);
      $slices[] = $this_slice;
    }
    return implode($slices);
  }

  /**
   * Returns a single slice of pie
   */
  protected function getSlice($item, $angle_start, $angle_end,
    $radius_x, $radius_y, &$attr, $single_slice, $colour_index)
  {
    $x_start = $y_start = $x_end = $y_end = 0;
    $angle_start += $this->s_angle;
    $angle_end += $this->s_angle;
    $this->calcSlice($angle_start, $angle_end, $radius_x, $radius_y,
      $x_start, $y_start, $x_end, $y_end);
    if($single_slice && $this->full_angle >= M_PI * 2.0) {
      $attr['cx'] = $this->x_centre;
      $attr['cy'] = $this->y_centre;
      $attr['rx'] = $radius_x;
      $attr['ry'] = $radius_y;
      return $this->element('ellipse', $attr);
    } else {
      $outer = ($angle_end - $angle_start > M_PI ? 1 : 0);
      $sweep = ($this->reverse ? 0 : 1);
      $d = new PathData('M', $this->x_centre, $this->y_centre, 'L', $x_start,
        $y_start, 'A', $radius_x, $radius_y, 0, $outer, $sweep, $x_end,
        $y_end, 'z');
      $attr['d'] = $d;
      return $this->element('path', $attr);
    }
  }

  /**
   * Calculates start and end points of slice
   */
  protected function calcSlice($angle_start, $angle_end, $radius_x, $radius_y,
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
  protected function getSliceInfo($num, $item, &$angle_start, &$angle_end,
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
  protected function checkValues()
  {
    $this->dataset = $this->getOption(['dataset',0], 0);
    parent::checkValues();
    if($this->values->getMinValue($this->dataset) < 0)
      throw new \Exception('Negative value for pie chart');

    $sum = 0;
    foreach($this->values[$this->dataset] as $item) {
      if($item->value !== null && !is_numeric($item->value))
        throw new \Exception('Non-numeric value');
      $sum += $item->value;
    }
    if($sum <= 0)
      throw new \Exception('Empty pie chart');

    $this->total = $sum;
  }

  /**
   * Returns extra drawing code that goes between pie and labels
   */
  protected function pieExtras()
  {
    return '';
  }

  /**
   * Return box for legend
   */
  public function drawLegendEntry($x, $y, $w, $h, $entry)
  {
    $bar = ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h];
    return $this->element('rect', $bar, $entry->style);
  }

  /**
   * Returns the position for the label and its target
   */
  public function dataLabelPosition($dataset, $index, &$item, $x, $y, $w, $h,
    $label_w, $label_h)
  {
    if(isset($this->slice_info[$index])) {
      $a = $this->slice_info[$index]->midAngle();
      $rx = $this->slice_info[$index]->radius_x;
      $ry = $this->slice_info[$index]->radius_y;

      // place it at the label_position distance from centre
      $pos_radius = $this->label_position;
      $ac = $this->s_angle + $a;
      $xc = $pos_radius * $rx * cos($ac);
      $yc = ($this->reverse ? -1 : 1) * $pos_radius * $ry * sin($ac);
      $pos = new Number($xc) . ' ' . new Number($yc);

      if($pos_radius > 1) {
        $space = $this->getOption(['data_label_space', $dataset]);
        $xt = ($rx + $space) * cos($ac);
        $yt = ($this->reverse ? -1 : 1) * ($ry + $space) * sin($ac);
      } else {
        $xt = $rx * 0.5 * cos($ac);
        $yt = ($this->reverse ? -1 : 1) * $ry * 0.5 * sin($ac);
      }
      $target = [$x + $xt, $y + $yt];
    } else {
      $pos = 'middle centre';
      $target = [$x, $y];
    }
    return [$pos, $target];
  }

  /**
   * Returns the style options for bar labels
   */
  public function dataLabelStyle($dataset, $index, &$item)
  {
    $style = parent::dataLabelStyle($dataset, $index, $item);

    // old pie label settings can override global data_label settings
    $opts = [
      'font' => 'label_font',
      'font_size' => 'label_font_size',
      'font_weight' => 'label_font_weight',
      'colour' => 'label_colour',
      'back_colour' => 'label_back_colour',
    ];
    foreach($opts as $key => $opt)
      if(isset($this->settings[$opt]))
        $style[$key] = $this->settings[$opt];

    return $style;
  }

  /**
   * Overload to return the firection of the pie centre
   */
  public function dataLabelTailDirection($dataset, $index, $hpos, $vpos)
  {
    if(isset($this->slice_info[$index])) {
      $a = rad2deg($this->slice_info[$index]->midAngle());
      // tail direction is opposite slice direction
      if($this->reverse)
        return (900 - $this->start_angle - $a) % 360; // 900 == 360 + 360 + 180
      else
        return (180 + $this->start_angle + $a) % 360;
    }

    // fall back to default
    return parent::dataLabelTailDirection($dataset, $index, $hpos, $vpos);
  }

  /**
   * Returns the order that the slices appear in
   */
  public function getLegendOrder()
  {
    return $this->legend_order;
  }
}

