<?php
/**
 * Copyright (C) 2018 Graham Breach
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


/**
 * Converts an abstract axis into SVG markup
 */
class DisplayAxis {

  protected $graph;
  protected $axis;
  protected $axis_no;
  protected $orientation;
  protected $type;
  protected $main;
  protected $styles;
  protected $show_axis;
  protected $show_divisions;
  protected $show_subdivisions;
  protected $show_text;
  protected $block_label;
  protected $boxed_text;
  protected $minimum_subdivision;
  protected $minimum_units;
  protected $subdivisions_fixed;

  /**
   * $orientation = 'h' or 'v'
   * $type = 'x' or 'y'
   * $main = TRUE for the main axis
   * $label_centre = TRUE for graphs with labels between divisions
   */
  public function __construct(&$graph, &$axis, $axis_no, $orientation, $type,
    $main, $label_centre)
  {
    $this->axis_no = $axis_no;
    $this->orientation = $orientation;
    $this->type = $type;
    $this->main = $main;
    $this->block_label = ($label_centre && $type == 'x');
    $this->boxed_text = FALSE;
    $styles = array();

    // set up options, styles
    $o = $orientation;
    $this->show_axis = $graph->GetOption(array("show_axis_$o", $axis_no),
      'show_axes');
    $this->show_text = $graph->GetOption("show_axis_text_$o");

    // axis and text both need colour
    $styles['colour'] = $graph->GetOption(array("axis_colour_$o", $axis_no),
      'axis_colour');
    if($this->show_axis) {
      $styles['overlap'] = $graph->GetOption('axis_overlap');
      $styles['stroke_width'] = $graph->GetOption(
        array("axis_stroke_width_$o", $axis_no), 'axis_stroke_width');

      if($graph->GetOption('show_divisions')) {
        $this->show_divisions = true;
        $styles['d_style'] = $graph->GetOption(
          array("division_style_$o", $axis_no), 'division_style');
        $styles['d_size'] = $graph->GetOption(
          array("division_size_$o", $axis_no), 'division_size');
        $styles['d_colour'] = $graph->GetOption(
          array("division_colour_$o", $axis_no), 'division_colour',
          array('@', $styles['colour'])
        );
      
        if($graph->GetOption('show_subdivisions')) {
          $this->show_subdivisions = true;
          $styles['s_style'] = $graph->GetOption(
            array("subdivision_style_$o", $axis_no), 'subdivision_style');
          $styles['s_size'] = $graph->GetOption(
            array("subdivision_size_$o", $axis_no), 'subdivision_size');
          $styles['s_colour'] = $graph->GetOption(
            array("subdivision_colour_$o", $axis_no), 'subdivision_colour',
            array("division_colour_$o", $axis_no), 'division_colour',
            array('@', $styles['colour'])
          );
          $this->minimum_subdivision = $graph->GetOption('minimum_subdivision');
          $this->minimum_units = ($type == 'x' ? 1 :
            $graph->GetOption(array('minimum_units_y', $axis_no)));
          $this->subdivisions_fixed = $graph->GetOption(
            array("subdivision_$o", $axis_no));
        }
      }
    }

    if($this->show_text) {
      $styles['t_angle'] = $graph->GetOption(
        array("axis_text_angle_$o", $axis_no), 0);
      $styles['t_position'] = $graph->GetOption(
        array("axis_text_position_$o", $axis_no), "axis_text_position");
      $styles['t_location'] = $graph->GetOption(
        array("axis_text_location_$o", $axis_no), "axis_text_location");
      $styles['t_font'] = $graph->GetOption(
        array("axis_font_$o", $axis_no), "axis_font");
      $styles['t_font_size'] = $graph->GetOption(
        array("axis_font_size_$o", $axis_no), "axis_font_size");
      $styles['t_font_adjust'] = $graph->GetOption(
        array("axis_font_adjust_$o", $axis_no), "axis_font_adjust");
      $styles['t_space'] = $graph->GetOption(
        array("axis_text_space_$o", $axis_no), "axis_text_space");
      $styles['t_colour'] = $graph->GetOption(
        array("axis_text_colour_$o", $axis_no), "axis_text_colour",
        array('@', $styles['colour']));

      // text is boxed only if it is outside and block labelling
      if($this->block_label && $this->show_divisions &&
        $styles['d_style'] == 'box' && $styles['t_position'] != 'inside') {
        $this->boxed_text = TRUE;

        // text must be attached to axis, not grid
        $styles['t_location'] = 'axis';
      }
    }

    $this->styles = $styles;
    $this->axis =& $axis;
    $this->graph =& $graph;
  }

  /**
   * Returns the extents of the axis, relative to where it will be drawn from
   *  returns array('x', 'y', 'width', 'height')
   */
  public function Measure()
  {
    $x = $y = $max_x = $max_y = 0;
    if($this->show_axis) {
      list($x, $y, $max_x, $max_y) = $this->GetDivisionsBBox();
      if($this->orientation == 'h') {
        // need to flip direction
        $tmp = $y;
        $y = -$max_y;
        $max_y = -$tmp;
      }
    }

    if($this->show_text) {
      list($tx, $ty, $tmax_x, $tmax_y) = $this->GetTextBBox();
      $x = min($x, $tx);
      $y = min($y, $ty);
      $max_x = max($max_x, $tmax_x);
      $max_y = max($max_y, $tmax_y);
    }

    $width = $max_x - $x;
    $height = $max_y - $y;
    return compact('x', 'y', 'width', 'height');
  }

  /**
   * Measures the space taken up by axis and divisions
   * returns array(min_x, min_y, max_x, max_y)
   */
  protected function GetDivisionsBBox()
  {
    if(!$this->show_axis)
      return array(0, 0, 0, 0);
    // orientation more important than type
    if($this->orientation == 'h') {
      $x = 'y'; $y = 'x';
    } else {
      $x = 'x'; $y = 'y';
    }
    $min = $max = array('x' => 0, 'y' => 0);

    $length = $this->axis->GetLength();
    $d_info = $s_info = array('pos' => 0, 'sz' => 0);
    if($this->show_divisions) {
      $d_info = $this->GetDivisionPathInfo(false, 100, 100);
      if($this->show_subdivisions)
        $s_info = $this->GetDivisionPathInfo(true, 100, 100);
    }

    $points = $this->axis->GetGridPoints(0);
    $p1 = array_pop($points);
    if($p1->position < 0) {
      $min[$y] = $p1->position;
      $max[$y] = 0;
    } else {
      $min[$y] = 0;
      $max[$y] = $p1->position;
    }

    $min[$x] = min($d_info['pos'], $s_info['pos']);
    $max[$x] = max($d_info['pos'] + $d_info['sz'], $s_info['pos'] + $s_info['sz']);

    return array($min['x'], $min['y'], $max['x'], $max['y']);
  }

  /**
   * Returns the bounding box of the text
   */
  protected function GetTextBBox()
  {
    $x = $y = $max_x = $max_y = 0;
    list($x_off, $y_off, $opp) = $this->GetTextOffset(0, 0, 0, 0, 0, 0);

    $points = $this->axis->GetGridPoints(0);
    $count = count($points);
    if($this->block_label)
      --$count;
    for($p = 0; $p < $count; ++$p) {

      if($points[$p]->text == '')
        continue;

      $lbl = $this->GetText($x_off, $y_off, $points[$p], $opp, true);
      $lbl_max_x = $lbl['x'] + $lbl['width'];
      $lbl_max_y = $lbl['y'] + $lbl['height'];
      if($lbl['x'] < $x)
        $x = $lbl['x'];
      if($lbl['y'] < $y)
        $y = $lbl['y'];
      if($lbl_max_x > $max_x)
        $max_x = $lbl_max_x;
      if($lbl_max_y > $max_y)
        $max_y = $lbl_max_y;
    }
    return array($x, $y, $max_x, $max_y);
  }

  /**
   * Draws the axis at ($x,$y)
   * $gx, $gy, $g_width and $g_height are the grid dimensions
   */
  public function Draw($x, $y, $gx, $gy, $g_width, $g_height)
  {
    $content = '';
    if($this->show_axis) {
      if($this->show_divisions) {
        $content .= $this->DrawDivisions($x, $y, $g_width, $g_height);
        if($this->show_subdivisions) {
          $content .= $this->DrawSubDivisions($x, $y, $g_width, $g_height);
        }
      }

      $length = $this->axis->GetLength();
      $content .= $this->DrawAxisLine($x, $y, $length);
    }

    if($this->show_text)
      $content .= $this->DrawText($x, $y, $gx, $gy, $g_width, $g_height);
    return $content;
  }

  /**
   * Draws the axis line
   */
  public function DrawAxisLine($x, $y, $len)
  {
    $overlap = $this->styles['overlap'];
    $length = $len + $overlap * 2;
    $line = $this->orientation;
    if($this->orientation == 'h') {
      $x = $x - $overlap;
    } else {
      $y = $y - $overlap - $len;
    }

    $attr = array('stroke' => $this->styles['colour'],
      'd' => "M{$x} {$y}{$line}{$length}");

    if($this->styles['stroke_width'] != 1)
      $attr['stroke-width'] = $this->styles['stroke_width'];
    return $this->graph->Element('path', $attr);
  }

  /**
   * Draws the axis divisions
   */
  public function DrawDivisions($x, $y, $g_width, $g_height)
  {
    $path_info = $this->GetDivisionPathInfo(false, $g_width, $g_height);
    if(is_null($path_info))
      return '';

    $points = $this->axis->GetGridPoints(0);
    $d = $this->GetDivisionPath($x, $y, $points, $path_info);

    $attr = array(
      'd' => $d,
      'stroke' => $this->styles['d_colour'],
      'fill' => 'none',
    );
    return $this->graph->Element('path', $attr);
  }

  /**
   * Draws the axis subdivisions
   */
  public function DrawSubDivisions($x, $y, $g_width, $g_height)
  {
    $path_info = $this->GetDivisionPathInfo(true, $g_width, $g_height);
    if(is_null($path_info))
      return '';

    $points = $this->axis->GetGridSubdivisions($this->minimum_subdivision,
      $this->minimum_units, 0, $this->subdivisions_fixed);
    $d = $this->GetDivisionPath($x, $y, $points, $path_info);
    $attr = array(
      'd' => $d,
      'stroke' => $this->styles['s_colour'],
      'fill' => 'none',
    );
    return $this->graph->Element('path', $attr);
  }

  /**
   * Draws the axis text labels
   */
  public function DrawText($x, $y, $gx, $gy, $g_width, $g_height)
  {
    $labels = '';
    list($x_offset, $y_offset, $opposite) = $this->GetTextOffset($x, $y,
      $gx, $gy, $g_width, $g_height);

    $points = $this->axis->GetGridPoints(0);
    $count = count($points);
    if($this->block_label)
      --$count;
    for($p = 0; $p < $count; ++$p) {

      $point = $points[$p];
      if($point->text == '')
        continue;

      $labels .= $this->GetText($x + $x_offset, $y + $y_offset, $point,
        $opposite);
    }
    if($labels != '') {
      $group = array(
        'font-family' => $this->styles['t_font'],
        'font-size' => $this->styles['t_font_size'],
        'fill' => $this->styles['t_colour'],
      );
      $labels = $this->graph->Element('g', $group, NULL, $labels);
    }
    return $labels;
  }

  /**
   * Returns the distance from the axis to draw the text
   */
  protected function GetTextOffset($ax, $ay, $gx, $gy, $g_width, $g_height)
  {
    $d1 = $d2 = 0;
    if($this->show_divisions) {
      if(!$this->boxed_text) {
        $d_info = $this->GetDivisionPathInfo(false, 100, 100);
        $d1 = $d_info['pos'];
        $d2 = $d1 + $d_info['sz'];
      }
      if($this->show_subdivisions) {
        $s_info = $this->GetDivisionPathInfo(true, 100, 100);
        $s1 = $s_info['pos'];
        $s2 = $s1 + $s_info['sz'];
        $d1 = min($d1, $s1);
        $d2 = max($d2, $s2);
      }
    }
    $space = $this->styles['t_space'];
    $d1 -= $space;
    $d2 += $space;
    $opposite = ($this->styles['t_position'] == 'inside');
    $grid = ($this->styles['t_location'] == 'grid');
    $anchor = 'end';
    if($this->orientation == 'h') {
      $y = ($opposite ? -$d2 : -$d1);
      $x = ($this->block_label ? $this->axis->Unit() * 0.5 : 0);
      if($grid)
        $y += $gy + $g_height - $ay;
    } else {
      if($this->axis_no > 0)
        $opposite = !$opposite;
      $x = ($opposite ? $d2 : $d1);
      $y = ($this->block_label ? $this->axis->Unit() * -0.5 : 0);
      if($grid && $this->axis_no < 2) {
        $x += $gx - $ax;
        if($this->axis_no == 1)
          $x += $g_width;
      }
    }
    return array($x, $y, $opposite);
  }

  /**
   * Returns the SVG fragment for a single axis label
   */
  protected function GetText($x, $y, &$point, $opposite, $measure = FALSE)
  {
    // skip 0 on axis when it would sit on top of other axis
    if(!$measure && !$this->block_label && $point->value == 0) {
      if($this->axis_no == 0 && $opposite)
        return '';
      if($this->axis_no == 1 && !$opposite)
        return '';
    }

    $font_size = $this->styles['t_font_size'];
    $svg_text = new SVGGraphText($this->graph, $this->styles['t_font'],
      $this->styles['t_font_adjust']);
    $baseline = $svg_text->Baseline($font_size);
    list($w, $h) = $svg_text->Measure($point->text, $font_size, 0, $font_size);
    if($this->orientation == 'h') {
      $attr['x'] = $x + $point->position;
      $attr['y'] = $y + $baseline - ($opposite ? $h : 0);
      $anchor = 'middle';
    } else {
      $attr['x'] = $x;
      $attr['y'] = $y + $baseline + $point->position - $h / 2;
      $anchor = $opposite ? 'start' : 'end';
    }

    $angle = $this->styles['t_angle'];
    $rcx = $rcy = NULL;
    if($angle) {
      if($this->orientation == 'h') {
        $rcx = $x + $point->position;
        $rcy = $y + $h * ($opposite ? -0.5 : 0.5);
        if($angle < 0) {
          $anchor = 'end';
          $attr['x'] += $h * 0.5;
        } else {
          $anchor = 'start';
          $attr['x'] -= $h * 0.5;
        }
        if($opposite)
          $angle = -$angle;
      } else {
        $rcx = $attr['x'] + $h * ($opposite ? 0.5 : -0.5);
        $rcy = $y + $point->position;
      }
      $attr['transform'] = "rotate({$angle},{$rcx},{$rcy})";
    }
    $attr['text-anchor'] = $anchor;

    // if measuring the text, find (rotated) size and position now
    if($measure) {
      list($x, $y, $width, $height) = $svg_text->MeasurePosition($point->text,
        $font_size, $font_size, $attr['x'], $attr['y'], $anchor, $angle,
        $rcx, $rcy);
      return compact('x', 'y', 'width', 'height');
    }
    return $svg_text->Text($point->text, $font_size, $attr);
  }

  /**
   * Returns the path for divisions or subdivisions
   */
  protected function GetDivisionPath($x, $y, $points, $path_info)
  {
    $y0 = $y;
    $x0 = $x;
    $yinc = $xinc = 0;
    if($this->orientation == 'v')
    {
      $x0 = $x + $path_info['pos'];
      $len = $path_info['sz'];
      $yinc = 1;
    }
    else
    {
      $y0 = $y - $path_info['pos'];
      $len = -$path_info['sz'];
      $xinc = 1;
    }

    $line = $path_info['line'];
    $path = '';
    foreach($points as $point) {
      $x = $x0 + $point->position * $xinc;
      $y = $y0 + $point->position * $yinc;
      $path .= "M{$x} {$y}{$line}{$len}";
    }

    if($path != '' && $path_info['box']) {
      $x = $x0 + $path_info['box_pos'] * $yinc;
      $y = $y0 + $path_info['box_pos'] * $xinc;
      $line = $path_info['box_line'];
      $len = $path_info['box_len'];
      $path .= "M{$x} {$y}{$line}{$len}";
    }
    return $path;
  }

  /**
   * Returns the details of the path segment
   */
  protected function GetDivisionPathInfo($subdiv, $g_width, $g_height)
  {
    if($this->orientation == 'h') {
      $line = 'v';
      $full = $g_height;
      $box_len = $g_width;
      $box_line = 'h';
    } else {
      $line = 'h';
      $full = $g_width;
      $box_len = -$g_height;
      $box_line = 'v';
    }

    if($subdiv) {
      $style = $this->styles['s_style'];
      $sz = $size = $this->styles['s_size'];
    } else {
      $style = $this->styles['d_style'];
      $sz = $size = $this->styles['d_size'];
      if($this->boxed_text) {
        list($x, $y, $max_x, $max_y) = $this->GetTextBBox();
        $sx = $max_x - $x;
        $sy = $max_y - $y;
        $sz = $size = ($this->orientation == 'h' ? $sy : $sx) +
          $this->styles['t_space'];
      }
    }
    if(!$this->main)
      $style = str_replace('full', '', $style);
    $pos = 0;
    $box_pos = 0;
    $box = false;

    switch($style) {
    case 'none' :
      return null; // no pos or sz
    case 'infull' :
      $sz = $full;
      break;
    case 'over' :
      $pos = -$size;
      $sz = $size * 2;
      break;
    case 'overfull' :
      if($this->axis_no == 0)
        $pos = -$size;
      $sz = $full + $size;
      break;
    case 'in' :
      if($this->axis_no != 0)
        $pos = -$size;
      break;
    case 'box' :
      $box = true;
      if($this->axis_no > 0)
        $box_pos += $size;
      // fall through
    case 'out' :
    default :
      if($this->axis_no == 0)
        $pos -= $size;
    }

    return compact('sz', 'pos', 'line', 'full', 'box', 'box_len', 'box_line',
      'box_pos');
  }

}

