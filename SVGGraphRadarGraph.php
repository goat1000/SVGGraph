<?php
/**
 * Copyright (C) 2012-2015 Graham Breach
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

require_once 'SVGGraphLineGraph.php';

/**
 * RadarGraph - a line graph that goes around in circles
 */
class RadarGraph extends LineGraph {

  protected $xc;
  protected $yc;
  protected $radius;
  protected $arad;
  private $pad_v_axis_label;

  // in the case of radar graphs, $label_centre means we want an axis that
  // ends at N points + 1
  protected $label_centre = true;
  protected $require_integer_keys = false;
  protected $single_axis = true;

  protected function Draw()
  {
    $body = $this->Grid();

    $attr = array('stroke' => $this->stroke_colour, 'fill' => 'none');
    $dash = is_array($this->line_dash) ?
      $this->line_dash[0] : $this->line_dash;
    $stroke_width = is_array($this->line_stroke_width) ?
      $this->line_stroke_width[0] : $this->line_stroke_width;
    if(!is_null($dash))
      $attr['stroke-dasharray'] = $dash;
    $attr['stroke-width'] = $stroke_width <= 0 ? 1 : $stroke_width;
    $this->ColourSetup($this->values->ItemsCount());

    $bnum = 0;
    $cmd = 'M';

    $path = '';
    if($this->fill_under) {
      $attr['fill'] = $this->GetColour(null, 0);
      $this->fill_styles[0] = array(
        'fill' => $attr['fill'],
        'stroke' => $attr['fill']
      );
      if($this->fill_opacity < 1.0) {
        $attr['fill-opacity'] = $this->fill_opacity;
        $this->fill_styles[0]['fill-opacity'] = $this->fill_opacity;
      }
    }

    $y_axis = $this->y_axes[$this->main_y_axis];
    foreach($this->values[0] as $item) {
      $point_pos = $this->GridPosition($item->key, $bnum);
      if(!is_null($item->value) && !is_null($point_pos)) {
        $val = $y_axis->Position($item->value);
        if(!is_null($val)) {
          $angle = $this->arad + $point_pos / $this->g_height;
          $x = $this->xc + ($val * sin($angle));
          $y = $this->yc + ($val * cos($angle));
          $path .= "$cmd$x $y ";

          // no need to repeat same L command
          $cmd = $cmd == 'M' ? 'L' : '';
          $marker_id = $this->MarkerLabel(0, $bnum, $item, $x, $y);
          $extra = empty($marker_id) ? NULL : array('id' => $marker_id);
          $this->AddMarker($x, $y, $item, $extra);
        }
      }
      ++$bnum;
    }

    $path .= "z";

    $this->line_styles[0] = $attr;
    $attr['d'] = $path;
    $group = array();

    $this->ClipGrid($group);
    if($this->semantic_classes) {
      $group['class'] = 'series';
      $attr['class'] = "series0";
    }

    $body .= $this->Element('g', $group, NULL, $this->Element('path', $attr));
    $body .= $this->Axes();
    $body .= $this->CrossHairs();
    $body .= $this->DrawMarkers();
    return $body;
  }

  /**
   * Finds the grid position for radar graphs, returns NULL if not on graph
   */
  protected function GridPosition($key, $ikey)
  {
    $gkey = $this->values->AssociativeKeys() ? $ikey : $key;
    $axis = $this->x_axes[$this->main_x_axis];
    $offset = $axis->Zero() + ($axis->Unit() * $gkey);
    if($offset >= 0 && $offset < $this->g_width)
      return $this->reverse ? -$offset : $offset;
    return NULL;
  }

  /**
   * Find the bounding box of the axis text for given axis lengths
   */
  protected function FindAxisTextBBox($length_x, $length_y, $x_axes, $y_axes)
  {
    $this->xc = $length_x / 2;
    $this->yc = $length_y / 2;
    $diameter = min($length_x, $length_y);
    $length_y = $diameter / 2;
    $length_x = 2 * M_PI * $length_y;
    $this->radius = $length_y;
    foreach($x_axes as $a)
      $a->SetLength($length_x);
    foreach($y_axes as $a)
      $a->SetLength($length_y);

    $min_space_h = $this->GetFirst($this->minimum_grid_spacing_h,
      $this->minimum_grid_spacing);

    // Code from parent implementation, with minor changes
    // initialise maxima and minima
    $min_x = $this->width;
    $min_y = $this->height;
    $max_x = $max_y = 0;

    // need actual text positions
    $div_size = $this->DivisionOverlap($x_axes, $y_axes);
    $inside_x = ('inside' == $this->GetFirst($this->axis_text_position_h,
      $this->axis_text_position));
    $font_size = $this->axis_font_size;

    // if outside, use the division overlap as starting positions
    $min_x = - $div_size['l'];
    $max_y = $length_y + $div_size['b'];

    // only do this if there is x-axis text
    if($this->show_axis_text_h) {
      $x_axis = $x_axes[0];
      $offset = 0;
      $points = $x_axis->GetGridPoints($min_space_h, 0);
      $positions = $this->XAxisTextPositions($points, $offset,
        $div_size['b'], $this->axis_text_angle_h, $inside_x);
      foreach($positions as $p) {
        switch($p['text-anchor']) {
        case 'middle' : $off_x = $p['w'] / 2; break;
        case 'end' : $off_x = $p['w']; break;
        default : $off_x = 0;
        }
        $x = $p['x'] - $off_x;
        $y = $p['y'] - $font_size;
        $xw = $x + $p['w'];
        $yh = $y + $p['h'];

        if($x < $min_x)
          $min_x = $x;
        if($xw > $max_x)
          $max_x = $xw;
        if($y < $min_y)
          $min_y = $y;
        if($yh > $max_y)
          $max_y = $yh;
      }
    }
    if($this->show_axis_text_v) {
      $axis_no = -1;
      foreach($y_axes as $y_axis) {
        ++$axis_no;
        if(is_null($y_axis))
          continue;
        $offset = 0;
        $inside_y = ('inside' == $this->GetFirst(
          $this->ArrayOption($this->axis_text_position_v, $axis_no),
          $this->axis_text_position));
        $min_space_v = $this->GetFirst(
          $this->ArrayOption($this->minimum_grid_spacing_v, $axis_no),
          $this->minimum_grid_spacing);
        $points = $y_axis->GetGridPoints($min_space_v, 0);
        $positions = $this->YAxisTextPositions($points,
          $div_size['l'],
          $offset, $this->ArrayOption($this->axis_text_angle_v, $axis_no),
          false, $axis_no);

        foreach($positions as $p) {
          $x = $p['x'];// - ($p['text-anchor'] == 'end' ? $p['w'] : 0);
          $y = $p['y'];// - $font_size + $length_y; // this messes up Radar graphs padding
          $xw = $x + $p['w'];
          $yh = $y + $p['h'];

          if($x < $min_x)
            $min_x = $x;
          if($xw > $max_x)
            $max_x = $xw;
          if($y < $min_y)
            $min_y = $y;
          if($yh > $max_y)
            $max_y = $yh;
        }
      }
    }
    // end of GridGraph implementation code

    // normalise the bounding box
    $w_half = ($max_x - $min_x) / 2;
    $h_half = ($max_y - $min_y) / 2;
    $bbox = array(
      'min_x' => $this->xc - $w_half,
      'max_x' => $this->xc + $w_half,
      'min_y' => $this->yc - $h_half,
      'max_y' => $this->yc + $h_half
    );
    $this->radius = null;
    return $bbox;
  }

  /**
   * Draws concentric Y grid lines
   */
  protected function YGrid(&$y_points)
  {
    $path = '';

    if($this->grid_straight) {
      $grid_angles = array();
      $points = array_merge($this->GetGridPointsX(0), $this->GetSubDivsX(0));
      foreach($points as $point) {
        $new_x = $point->position - $this->pad_left;
        $grid_angles[] = $this->arad + $new_x / $this->radius;
      }
      // put the grid angles in order
      sort($grid_angles);
      foreach($y_points as $y) {
        $y = $y->position;
        $x1 = $this->xc + $y * sin($this->arad);
        $y1 = $this->yc + $y * cos($this->arad);
        $path .= "M$x1 {$y1}L";
        foreach($grid_angles as $a) {
          $x1 = $this->xc + $y * sin($a);
          $y1 = $this->yc + $y * cos($a);
          $path .= "$x1 $y1 ";
        }
        $path .= "z";
      }
    } else {
      foreach($y_points as $y) {
        $y = $y->position;
        $p1 = $this->xc - $y;
        $p2 = $this->xc + $y;
        $path .= "M$p1 {$this->yc}A $y $y 0 1 1 $p2 {$this->yc}";
        $path .= "M$p2 {$this->yc}A $y $y 0 1 1 $p1 {$this->yc}";
      }
    }
    return $path;
  }

  /**
   * Draws radiating X grid lines
   */
  protected function XGrid(&$x_points)
  {
    $path = '';
    foreach($x_points as $x) {
      $x = $x->position - $this->pad_left;
      $angle = $this->arad + $x / $this->radius;
      $p1 = $this->radius * sin($angle);
      $p2 = $this->radius * cos($angle);
      $path .= "M{$this->xc} {$this->yc}l$p1 $p2";
    }
    return $path;
  }

  /**
   * Draws the grid behind the graph
   */
  protected function Grid()
  {
    $this->CalcAxes();
    $this->CalcGrid();
    if(!$this->show_grid || (!$this->show_grid_h && !$this->show_grid_v))
      return '';

    $xc = $this->xc;
    $yc = $this->yc;
    $r = $this->radius;

    $back = $subpath = '';
    $back_colour = $this->ParseColour($this->grid_back_colour);
    $y_points = $this->GetGridPointsY(0);
    $x_points = $this->GetGridPointsX(0);
    $y_subdivs = $this->GetSubDivsY(0);
    $x_subdivs = $this->GetSubDivsX(0);
    if(!empty($back_colour) && $back_colour != 'none') {
      // use the YGrid function to get the path
      $points = array(new GridPoint($r, '', 0));
      $bpath = array(
        'd' => $this->YGrid($points),
        'fill' => $back_colour
      );
      $back = $this->Element('path', $bpath);
    }
    if($this->grid_back_stripe) {
      $bpath = array(
        'fill' => $this->grid_back_stripe_colour,
        'd' => $this->YGrid($y_points),
        'fill-rule' => 'evenodd' // fill alternating 
      );
      $back .= $this->Element('path', $bpath);
    }
    if($this->show_grid_subdivisions) {
      $subpath_h = $this->show_grid_h ? $this->YGrid($y_subdivs) : '';
      $subpath_v = $this->show_grid_v ? $this->XGrid($x_subdivs) : '';
      if($subpath_h != '' || $subpath_v != '') {
        $colour_h = $this->GetFirst($this->grid_subdivision_colour_h,
          $this->grid_subdivision_colour, $this->grid_colour_h,
          $this->grid_colour);
        $colour_v = $this->GetFirst($this->grid_subdivision_colour_v,
          $this->grid_subdivision_colour, $this->grid_colour_v,
          $this->grid_colour);
        $dash_h = $this->GetFirst($this->grid_subdivision_dash_h,
          $this->grid_subdivision_dash, $this->grid_dash_h, $this->grid_dash);
        $dash_v = $this->GetFirst($this->grid_subdivision_dash_v,
          $this->grid_subdivision_dash, $this->grid_dash_v, $this->grid_dash);

        if($dash_h == $dash_v && $colour_h == $colour_v) {
          $subpath = $this->GridLines($subpath_h . $subpath_v, $colour_h,
            $dash_h, 'none');
        } else {
          $subpath = $this->GridLines($subpath_h, $colour_h, $dash_h, 'none') .
            $this->GridLines($subpath_v, $colour_v, $dash_v, 'none');
        }
      }
    }

    $path_v = $this->show_grid_h ? $this->YGrid($y_points) : '';
    $path_h = $this->show_grid_v ? $this->XGrid($x_points) : '';

    $colour_h = $this->GetFirst($this->grid_colour_h, $this->grid_colour);
    $colour_v = $this->GetFirst($this->grid_colour_v, $this->grid_colour);
    $dash_h = $this->GetFirst($this->grid_dash_h, $this->grid_dash);
    $dash_v = $this->GetFirst($this->grid_dash_v, $this->grid_dash);

    if($dash_h == $dash_v && $colour_h == $colour_v) {
      $path = $this->GridLines($path_v . $path_h, $colour_h, $dash_h, 'none');
    } else {
      $path = $this->GridLines($path_h, $colour_h, $dash_h, 'none') .
        $this->GridLines($path_v, $colour_v, $dash_v, 'none');
    }

    return $back . $subpath . $path;
  }

  /**
   * Sets the grid size as circumference x radius
   */
  protected function SetGridDimensions()
  {
    if(is_null($this->radius)) {
      $w = $this->width - $this->pad_left - $this->pad_right;
      $h = $this->height - $this->pad_top - $this->pad_bottom;
      $this->xc = $this->pad_left + $w / 2;
      $this->yc = $this->pad_top + $h / 2;
      $this->radius = min($w, $h) / 2;
    }
    $this->g_height = $this->radius;
    $this->g_width = 2 * M_PI * $this->radius;
  }

  /**
   * Calculate the extra details for radar axes
   */
  protected function CalcAxes($h_by_count = false, $bar = false)
  {
    $this->arad = (90 + $this->start_angle) * M_PI / 180;
    $this->axis_right = false;
    parent::CalcAxes($h_by_count, $bar);
  }

  /**
   * The X-axis is wrapped around the graph
   */
  protected function XAxis($yoff)
  {
    if(!$this->show_x_axis)
      return '';

    // use the YGrid function to get the path
    $points = array(new GridPoint($this->radius, '', 0));
    $path = array(
      'd' => $this->YGrid($points),
      'fill' => 'none' // it's a circle or polygon, don't want it filled
    );
    if(!empty($this->axis_colour_h))
      $path['stroke'] = $this->axis_colour_h;
    if(!empty($this->axis_stroke_width_h))
      $path['stroke-width'] = $this->axis_stroke_width_h;
    return $this->Element('path', $path);
  }

  /**
   * The Y-axis is at start angle
   */
  protected function YAxis($i)
  {
    $radius = $this->radius + $this->axis_overlap;
    $x1 = $radius * sin($this->arad);
    $y1 = $radius * cos($this->arad);
    $path = array('d' => "M{$this->xc} {$this->yc}l$x1 $y1");

    $colour = $this->ArrayOption($this->axis_colour_v, $i);
    $thickness = $this->ArrayOption($this->axis_stroke_width_v, $i);
    if(!empty($colour))
      $path['stroke'] = $colour;
    if(!empty($thickness))
      $path['stroke-width'] = $thickness;
    return $this->Element('path', $path);
  }

  /**
   * Division marks around the graph
   */
  protected function XAxisDivisions(&$points, $style, $size, $yoff)
  {
    $r1 = $this->radius;
    $path = '';
    $pos = $this->DivisionsPositions($style, $size, $this->radius, 0, 0, false, false);
    if(is_null($pos))
      return '';
    $r1 = $this->radius - $pos['pos'];
    foreach($points as $p) {
      $p = $p->position - $this->pad_left;
      $a = $this->arad + $p / $this->radius;
      $x1 = $this->xc + $r1 * sin($a);
      $y1 = $this->yc + $r1 * cos($a);
      $x2 = -$pos['sz'] * sin($a);
      $y2 = -$pos['sz'] * cos($a);
      $path .= "M$x1 {$y1}l$x2 $y2";
    }
    return $path;
  }

  /**
   * Draws Y-axis divisions at whatever angle the Y-axis is
   */
  protected function YAxisDivisions(&$points, $xoff, $subdiv, $axis_no)
  {
    $dz = 'division_size';
    $ds = 'division_style';
    $dzv = 'division_size_v';
    $dsv = 'division_style_v';
    if($subdiv) {
      $dz = 'subdivision_size';
      $ds = 'subdivision_style';
      $dzv = 'subdivision_size_v';
      $dsv = 'subdivision_style_v';
    }

    $style = $this->GetFirst($this->ArrayOption($this->{$dsv}, $axis_no), $this->{$ds});
    $size = $this->GetFirst($this->ArrayOption($this->{$dzv}, $axis_no), $this->{$dz});
    $path = '';
    $pos = $this->DivisionsPositions($style, $size, $size, 0, 0, false, false);
    if(is_null($pos))
      return '';
    $a = $this->arad + ($this->arad <= M_PI_2 ? - M_PI_2 : M_PI_2);
    $px = $pos['pos'] * sin($a);
    $py = $pos['pos'] * cos($a);
    $x2 = $pos['sz'] * sin($a);
    $y2 = $pos['sz'] * cos($a);
    $c = cos($this->arad);
    $s = sin($this->arad);
    foreach($points as $y) {
      $y = $y->position;
      $x1 = ($this->xc + $y * $s) + $px;
      $y1 = ($this->yc + $y * $c) + $py;
      $path .= "M$x1 {$y1}l$x2 $y2";
    }
    return $path;
  }

  /**
   * Returns the positions of the X-axis text
   */
  protected function XAxisTextPositions(&$points, $xoff, $yoff, $angle, $inside)
  {
    $positions = array();
    $font_size = $this->GetFirst(
      $this->ArrayOption($this->axis_font_size_h, 0),
      $this->axis_font_size);
    $font_adjust = $this->GetFirst(
      $this->ArrayOption($this->axis_font_adjust_h, 0),
      $this->axis_font_adjust);
    $text_space = $this->GetFirst(
      $this->ArrayOption($this->axis_text_space_h, 0),
      $this->axis_text_space);
    $r = $this->radius + $yoff + $text_space;
    $text_centre = $font_size * 0.3;
    $count = count($points);
    $p = 0;
    $direction = $this->reverse ? -1 : 1;
    foreach($points as $grid_point) {
      $key = $grid_point->text;
      $x = $grid_point->position - $this->pad_left;
      // if the key is different to value, use it
      $k = $this->GetKey($grid_point->value);
      if($k !== $grid_point->value)
        $key = $k;
      if(SVGGraphStrlen($key, $this->encoding) > 0 && ++$p < $count) {
        $a = $this->arad + $direction * $x / $this->radius;
        $s = sin($a);
        $c = cos($a);
        $x1 = $r * $s;
        $y1 = $r * $c - $text_centre;
        $position = array(
          'x' => $this->xc + $x1,
          'y' => $this->yc + $y1,
          // $c == +1 or -1 is a particular case: anchor on middle of text
          'text-anchor' => (pow($c, 2) == 1 ? 'middle' :
            ($x1 >= 0 ? 'start' : 'end')),
          'angle' => $a,
          'sin' => $s,
          'cos' => $c
        );
        $size = $this->TextSize((string)$key, $font_size, $font_adjust,
          $this->encoding, $angle, $font_size);
        // $s == +1 or -1 is a particular case: vertically centre
        $lines = $this->CountLines($key);
        if(pow($s, 2) == 1)
          $position['y'] -= ($lines / 2 - 1) * $font_size;
        elseif($c < 0)
          $position['y'] -= ($lines - 1) * $font_size;
        else
          $position['y'] += $font_size;
        if($angle != 0) {
          $rcx = $position['x'];
          $rcy = $position['y'];
          if($c < 0)
            $rcy += $font_size;
          elseif(pow($s, 2) != 1)
            $rcy -= $font_size;
          $position['transform'] = "rotate($angle,$rcx,$rcy)";
        }
        // $c == -1 is particular too : XAxis text can bump YAxis texts
        $y_nudge = $this->GetFirst($this->axis_font_size_v,
          $this->axis_font_size) / 2;
        if($c == -1 && $this->start_angle % 360 == 90) {
          $position['y'] -= $y_nudge;
        } elseif($c == 1 && $this->start_angle % 360 == 270) {
          $position['y'] += $y_nudge;
        }
        $position['text'] = $key;
        $position['w'] = $size[0];
        $position['h'] = $size[1];
        $positions[] = $position;
      }
    }
    return $positions;
  }

  /**
   * Text labels for the wrapped X-axis
   */
  protected function XAxisText(&$points, $xoff, $yoff, $angle)
  { 
    $inside = ('inside' == $this->GetFirst($this->axis_text_position_h,
      $this->axis_text_position));
    $font_size = $this->GetFirst($this->axis_font_size_h, $this->axis_font_size);
    $positions = $this->XAxisTextPositions($points, $xoff, $yoff, $angle,
      $inside);
    $labels = '';
    foreach($positions as $pos) {
      $text = $pos['text'];
      unset($pos['w'], $pos['h'], $pos['text'], $pos['angle'], $pos['sin'],
        $pos['cos']);
      $labels .= $this->Text($text, $font_size, $pos);
    }
    $group = array();
    if(!empty($this->axis_font_h))
      $group['font-family'] = $this->axis_font_h;
    if(!empty($this->axis_font_size_h))
      $group['font-size'] = $font_size;
    if(!empty($this->axis_text_colour_h))
      $group['fill'] = $this->axis_text_colour_h;

    if(empty($group))
      return $labels;
    return $this->Element('g', $group, NULL, $labels);
  }

  /**
   * Returns the positions of the Y-axis text
   */
  protected function YAxisTextPositions(&$points, $xoff, $yoff, $angle, $inside, $axis_no)
  {
    $positions = array();
    $labels = '';
    $font_size = $this->GetFirst($this->axis_font_size_v, $this->axis_font_size);
    $font_adjust = $this->GetFirst($this->axis_font_adjust_v, $this->axis_font_adjust);
    $text_space = $this->GetFirst($this->axis_text_space_v, $this->axis_text_space);
    $c = cos($this->arad);
    $s = sin($this->arad);
    $a = $this->arad + ($s * $c > 0 ? - M_PI_2 : M_PI_2);
    $x2 = ($xoff + $text_space) * sin($a);
    $y2 = ($xoff + $text_space) * cos($a);
    $x3 = 0;
    $y3 = $c > 0 ? $font_size : 0;
    $position = array('text-anchor' => $s < 0 ? 'start' : 'end');
    foreach($points as $grid_point) {
      $key = $grid_point->text;
      $y = $grid_point->position;
      if(SVGGraphStrlen($key, $this->encoding) > 0) {
        $x1 = $y * $s;
        $y1 = $y * $c;
        $position['x'] = $this->xc + $x1 + $x2 + $x3;
        $position['y'] = $this->yc + $y1 + $y2 + $y3;
        if($angle != 0) {
          $rcx = $position['x'];
          $rcy = $position['y'];
          $position['transform'] = "rotate($angle,$rcx,$rcy)";
        }
        $size = $this->TextSize((string)$key, $font_size, $font_adjust, 
          $this->encoding, $angle, $font_size);
        $position['text'] = $key;
        $position['w'] = $size[0];
        $position['h'] = $size[1];
        $positions[] = $position;
      }
    }
    return $positions;
  }

  /**
   * Text labels for the Y-axis
   */
  protected function YAxisText(&$points, $xoff, $yoff, $angle, $right, $axis_no)
  { 
    $positions = $this->YAxisTextPositions($points, $xoff, $yoff, $angle, false, $axis_no);
    $labels = '';
    $font_size = $this->GetFirst(
      $this->ArrayOption($this->axis_font_size_v, $axis_no),
      $this->axis_font_size);
    $anchor = $positions[0]['text-anchor'];
    foreach($positions as $pos) {
      $text = $pos['text'];
      unset($pos['w'], $pos['h'], $pos['text'], $pos['text-anchor']);
      $labels .= $this->Text($text, $font_size, $pos);
    }
    $group = array('text-anchor' => $anchor);
    if(!empty($this->axis_font_v))
      $group['font-family'] = $this->ArrayOption($this->axis_font_v, $axis_no);
    if(!empty($this->axis_font_size_v))
      $group['font-size'] = $font_size;
    if(!empty($this->axis_text_colour_v))
      $group['fill'] = $this->ArrayOption($this->axis_text_colour_v, $axis_no);
    return $this->Element('g', $group, NULL, $labels);
  }


  /**
   * Returns what would be the vertical axis label
   */
  protected function VLabel(&$attribs)
  {
    if(empty($this->label_v))
      return '';

    $c = cos($this->arad);
    $s = sin($this->arad);
    $a = $this->arad + ($s * $c > 0 ? - M_PI_2 : M_PI_2);
    $offset = max($this->division_size * (int)$this->show_divisions,
      $this->subdivision_size * (int)$this->show_subdivisions) +
      $this->pad_v_axis_label + $this->label_space;
    $offset += ($c < 0 ? ($this->CountLines($this->label_v) - 1) : 1) *
      $this->label_font_size;

    $x2 = $offset * sin($a);
    $y2 = $offset * cos($a);
    $p = $this->radius / 2;
    $x = $this->xc + $p * sin($this->arad) + $x2;
    $y = $this->yc + $p * cos($this->arad) + $y2;
    $a = $s < 0 ? 180 - $this->start_angle : -$this->start_angle;
    $pos = array(
      'x' => $x,
      'y' => $y,
      'transform' => "rotate($a,$x,$y)",
    );
    return $this->Text($this->label_v, $this->label_font_size,
      array_merge($attribs, $pos));
  }

  /**
   * Returns the grid points for a Y-axis
   */
  protected function GetGridPointsY($axis)
  {
    $min_space_v = $this->GetFirst(
      $this->ArrayOption($this->minimum_grid_spacing_v, $axis),
      $this->minimum_grid_spacing);
    $points = $this->y_axes[$axis]->GetGridPoints($min_space_v, 0);
    foreach($points as $k => $p)
      $points[$k]->position = -$p->position;
    return $points;
  }

  /**
   * Returns the subdivisions for a Y-axis
   */
  protected function GetSubDivsY($axis)
  {
    $points = $this->y_axes[$axis]->GetGridSubdivisions(
      $this->minimum_subdivision,
      $this->ArrayOption($this->minimum_units_y, $axis), 0, 
      $this->ArrayOption($this->subdivision_v, $axis));
    foreach($points as $k => $p)
      $points[$k]->position = -$p->position;
    return $points;
  }

}

