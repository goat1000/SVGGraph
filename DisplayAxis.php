<?php
/**
 * Copyright (C) 2018-2021 Graham Breach
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
  protected $show_label;
  protected $label = '';
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
    $this->block_label = ($type == 'x' && ($label_centre ||
      $graph->getOption('force_block_label_x')));
    $this->boxed_text = false;
    $styles = [];

    // set up options, styles
    $o = $orientation;
    $this->show_axis = false;
    $this->show_text = false;
    if($graph->getOption('show_axes')) {
      $this->show_axis = $graph->getOption(['show_axis_' . $o, $axis_no]);
      $this->show_text = $graph->getOption('show_axis_text_' . $o);
    }

    // gridgraph moves label_[xy] into label_[hv]
    $o_labels = $graph->getOption('label_' . $o);
    if(is_array($o_labels)) {
      // use array entry if one exists for this axis
      if(isset($o_labels[$axis_no]))
        $this->label = $o_labels[$axis_no];
    } elseif($axis_no == 0 && !empty($o_labels)) {
      // not an array, so only valid for axis 0
      $this->label = $o_labels;
    }
    $this->show_label = ($this->label != '');

    // axis and text both need colour
    $styles['colour'] = new Colour($graph,
      $graph->getOption(['axis_colour_' . $o, $axis_no], 'axis_colour'));
    if($this->show_axis) {
      $styles['overlap'] = $graph->getOption('axis_overlap');
      $styles['stroke_width'] = $graph->getOption(
        ['axis_stroke_width_' . $o, $axis_no], 'axis_stroke_width');

      if($graph->getOption('show_divisions')) {
        $this->show_divisions = true;
        $styles['d_style'] = $graph->getOption(
          ['division_style_' . $o, $axis_no], 'division_style');
        $styles['d_size'] = $graph->getOption(
          ['division_size_' . $o, $axis_no], 'division_size');
        $styles['d_colour'] = new Colour($graph, $graph->getOption(
          ['division_colour_' . $o, $axis_no], 'division_colour',
          ['@', $styles['colour']]));

        if($graph->getOption('show_subdivisions')) {
          $this->show_subdivisions = true;
          $styles['s_style'] = $graph->getOption(
            ['subdivision_style_' . $o, $axis_no], 'subdivision_style');
          $styles['s_size'] = $graph->getOption(
            ['subdivision_size_' . $o, $axis_no], 'subdivision_size');
          $styles['s_colour'] = new Colour($graph, $graph->getOption(
            ['subdivision_colour_' . $o, $axis_no], 'subdivision_colour',
            ['division_colour_' . $o, $axis_no], 'division_colour',
            ['@', $styles['colour']]));
          $this->minimum_subdivision = $graph->getOption('minimum_subdivision');
          $this->minimum_units = ($type == 'x' ? 1 :
            $graph->getOption(['minimum_units_y', $axis_no]));
          $this->subdivisions_fixed = $graph->getOption(
            ['subdivision_' . $o, $axis_no]);
        }
      }
    }

    if($this->show_text) {
      $styles['t_angle'] = $graph->getOption(
        ['axis_text_angle_' . $o, $axis_no], 0);
      if($graph->getOption('limit_text_angle')) {
        $angle = $styles['t_angle'] % 360;
        if($angle > 180)
          $angle = -360 + $angle;
        if($angle < -180)
          $angle = 360 + $angle;
        $angle = min(90, max(-90, $angle));
        $styles['t_angle'] = $angle;
      }
      $styles['t_position'] = $graph->getOption(
        ['axis_text_position_' . $o, $axis_no], 'axis_text_position');
      $styles['t_location'] = $graph->getOption(
        ['axis_text_location_' . $o, $axis_no], 'axis_text_location');
      $styles['t_font'] = $graph->getOption(
        ['axis_font_' . $o, $axis_no], 'axis_font');
      $styles['t_font_size'] = $graph->getOption(
        ['axis_font_size_' . $o, $axis_no], 'axis_font_size');
      $styles['t_font_adjust'] = $graph->getOption(
        ['axis_font_adjust_' . $o, $axis_no], 'axis_font_adjust');
      $styles['t_font_weight'] = $graph->getOption(
        ['axis_font_weight_' . $o, $axis_no], 'axis_font_weight');
      $styles['t_space'] = $graph->getOption(
        ['axis_text_space_' . $o, $axis_no], 'axis_text_space');
      $styles['t_colour'] = new Colour($graph, $graph->getOption(
        ['axis_text_colour_' . $o, $axis_no], 'axis_text_colour',
        ['@', $styles['colour']]));
      $styles['t_line_spacing'] = $graph->getOption(
        ['axis_text_line_spacing_' . $o, $axis_no], 'axis_text_line_spacing');
      if($styles['t_line_spacing'] === null || $styles['t_line_spacing'] < 1)
        $styles['t_line_spacing'] = $styles['t_font_size'];
      $styles['t_back_colour'] = null;

      // fill in background colour array, if required
      $back_colour = $graph->getOption(
        ['axis_text_back_colour_' . $o, $axis_no], 'axis_text_back_colour');
      if(!empty($back_colour)) {
        $styles['t_back_colour'] = [
          'stroke-width' => '3px',
          'stroke' => new Colour($graph, $back_colour),
          'stroke-linejoin' => 'round',
        ];
      }

      // text is boxed only if it is outside and block labelling
      if($this->block_label && $this->show_divisions &&
        $styles['d_style'] == 'box' && $styles['t_position'] != 'inside') {
        $this->boxed_text = true;

        // text must be attached to axis, not grid
        $styles['t_location'] = 'axis';
      }
    }

    if($this->show_label) {
      $styles['l_font'] = $graph->getOption(
        ['label_font_' . $o, $axis_no], 'label_font',
        ['axis_font_' . $o, $axis_no], 'axis_font');
      $styles['l_font_size'] = $graph->getOption(
        ['label_font_size_' . $o, $axis_no], 'label_font_size',
        ['axis_font_size_' . $o, $axis_no], 'axis_font_size');
      $styles['l_font_weight'] = $graph->getOption(
        ['label_font_weight_' . $o, $axis_no], 'label_font_weight');
      $styles['l_colour'] = new Colour($graph, $graph->getOption(
        ['label_colour_' . $o, $axis_no], 'label_colour',
        ['axis_text_colour_' . $o, $axis_no], 'axis_text_colour',
        ['@', $styles['colour']]));
      $styles['l_space'] = $graph->getOption('label_space');
      $styles['l_pos'] = $graph->getOption(
        ['axis_label_position_' . $o, $axis_no], 'axis_label_position');
      $styles['l_line_spacing'] = $graph->getOption(
        ['label_line_spacing_' . $o, $axis_no], 'label_line_spacing');
      if($styles['l_line_spacing'] === null || $styles['l_line_spacing'] < 1)
        $styles['l_line_spacing'] = $styles['l_font_size'];
    }

    $this->styles = $styles;
    $this->axis =& $axis;
    $this->graph =& $graph;
  }

  /**
   * Returns the extents of the axis, relative to where it will be drawn from
   *  returns BoundingBox
   */
  public function measure($with_label = true)
  {
    $bbox = new BoundingBox(0, 0, 0, 0);
    if($this->show_axis) {
      $dbox = $this->getDivisionsBBox(0);
      if($this->orientation == 'h')
        $dbox->flipY();
      $bbox->growBox($dbox);
    }

    if($this->show_text) {
      $tbox = $this->getTextBBox(0);
      $bbox->growBox($tbox);
    }

    if($with_label && $this->show_label) {
      $lpos = $this->getLabelPosition();
      $bbox->grow($lpos['x'], $lpos['y'], $lpos['x'] + $lpos['width'],
        $lpos['y'] + $lpos['height']);
    }

    return $bbox;
  }

  /**
   * Measures the space taken up by axis and divisions
   *  returns BoundingBox
   */
  protected function getDivisionsBBox($level)
  {
    $bbox = new BoundingBox(0, 0, 0, 0);
    if(!$this->show_axis)
      return $bbox;

    // orientation more important than type
    $x = 'x';
    $y = 'y';
    if($this->orientation == 'h') {
      $x = 'y';
      $y = 'x';
    }
    $min = $max = ['x' => 0, 'y' => 0];

    $length = $this->axis->getLength();
    $d_info = $s_info = ['pos' => 0, 'sz' => 0];
    if($this->show_divisions) {
      $di = $this->getDivisionPathInfo(false, 100, 100, $level);
      if($di !== null)
        $d_info = $di;
      if($this->show_subdivisions) {
        $si = $this->getDivisionPathInfo(true, 100, 100, $level);
        if($si !== null)
          $s_info = $si;
      }
    }

    $points = $this->axis->getGridPoints(0);
    $p1 = end($points);
    if($p1->position < 0) {
      $min[$y] = $p1->position;
      $max[$y] = 0;
    } else {
      $min[$y] = 0;
      $max[$y] = $p1->position;
    }

    $min[$x] = min($d_info['pos'], $s_info['pos']);
    $max[$x] = max($d_info['pos'] + $d_info['sz'], $s_info['pos'] + $s_info['sz']);

    $bbox->grow($min['x'], $min['y'], $max['x'], $max['y']);
    return $bbox;
  }

  /**
   * Returns the bounding box of the text
   */
  protected function getTextBBox($level)
  {
    $bbox = new BoundingBox(0, 0, 0, 0);
    list($x_off, $y_off, $opp) = $this->getTextOffset(0, 0, 0, 0, 0, 0, $level);

    $t_offset = ($this->orientation == 'h' ? $x_off : -$y_off);
    $length = $this->axis->getLength();
    $points = $this->axis->getGridPoints(0);
    $count = count($points);
    for($p = 0; $p < $count; ++$p) {

      if(!$this->pointTextVisible($points[$p], $length, $t_offset))
        continue;

      $lbl = $this->measureText($x_off, $y_off, $points[$p], $opp, $level);
      $bbox->grow($lbl['x'], $lbl['y'], $lbl['x'] + $lbl['width'],
        $lbl['y'] + $lbl['height']);
    }
    return $bbox;
  }

  /**
   * Returns true if the text exists and its location is within the axis
   */
  protected function pointTextVisible($point, $axis_len, $offset)
  {
    if($point->blank())
      return false;

    // amount of space to allow for rounding errors
    $leeway = 0.5;
    $position = abs($point->position) + $offset;
    return $position >= -$leeway && $position <= $axis_len + $leeway;
  }

  /**
   * Draws the axis at ($x,$y)
   * $gx, $gy, $g_width and $g_height are the grid dimensions
   */
  public function draw($x, $y, $gx, $gy, $g_width, $g_height)
  {
    $content = '';
    if($this->show_axis) {
      if($this->show_divisions) {
        $content .= $this->drawDivisions($x, $y, $g_width, $g_height);
        if($this->show_subdivisions) {
          $content .= $this->drawSubDivisions($x, $y, $g_width, $g_height);
        }
      }

      $length = $this->axis->getLength();
      $content .= $this->drawAxisLine($x, $y, $length);
    }

    if($this->show_text || $this->show_label)
      $content .= $this->drawText($x, $y, $gx, $gy, $g_width, $g_height);

    return $content;
  }

  /**
   * Draws the axis line
   */
  public function drawAxisLine($x, $y, $len)
  {
    $overlap = $this->styles['overlap'];
    $length = $len + $overlap * 2;
    $line = $this->orientation;
    if($this->orientation == 'h') {
      $x = $x - $overlap;
    } else {
      $y = $y - $overlap - $len;
    }

    $colour = $this->styles['colour'];
    if($colour->isGradient()) {
      // gradients don't work on stroked horizontal or vertical lines
      $sw = $this->styles['stroke_width'];
      $attr = [
        'fill' => $colour,
        'x' => $x,
        'y' => $y,
      ];
      if($this->orientation == 'h') {
        $attr['y'] -= $sw / 2;
        $attr['width'] = $length;
        $attr['height'] = $sw;
      } else {
        $attr['x'] -= $sw / 2;
        $attr['width'] = $sw;
        $attr['height'] = $length;
      }
      return $this->graph->element('rect', $attr);
    }

    $attr = [
      'stroke' => $colour,
      'd' => new PathData('M', $x, $y, $line, $length),
    ];

    if($this->styles['stroke_width'] != 1)
      $attr['stroke-width'] = $this->styles['stroke_width'];
    return $this->graph->element('path', $attr);
  }

  /**
   * Draws the axis divisions
   */
  public function drawDivisions($x, $y, $g_width, $g_height)
  {
    $path_info = $this->getDivisionPathInfo(false, $g_width, $g_height, 0);
    if($path_info === null)
      return '';

    $points = $this->axis->getGridPoints(0);
    $d = $this->getDivisionPath($x, $y, $points, $path_info, 0);

    $attr = [
      'd' => $d,
      'stroke' => $this->styles['d_colour'],
      'fill' => 'none',
    ];
    return $this->graph->element('path', $attr);
  }

  /**
   * Draws the axis subdivisions
   */
  public function drawSubDivisions($x, $y, $g_width, $g_height)
  {
    $path_info = $this->getDivisionPathInfo(true, $g_width, $g_height, 0);
    if($path_info === null)
      return '';

    $points = $this->axis->getGridSubdivisions($this->minimum_subdivision,
      $this->minimum_units, 0, $this->subdivisions_fixed);
    $d = $this->getDivisionPath($x, $y, $points, $path_info, 0);
    $attr = [
      'd' => $d,
      'stroke' => $this->styles['s_colour'],
      'fill' => 'none',
    ];
    return $this->graph->element('path', $attr);
  }

  /**
   * Draws the axis text labels
   */
  public function drawText($x, $y, $gx, $gy, $g_width, $g_height)
  {
    $labels = '';
    if($this->show_text) {
      list($x_offset, $y_offset, $opposite) = $this->getTextOffset($x, $y,
        $gx, $gy, $g_width, $g_height, 0);

      $t_offset = ($this->orientation == 'h' ? $x_offset : -$y_offset);
      $length = $this->axis->getLength();
      $points = $this->axis->getGridPoints(0);
      $count = count($points);
      for($p = 0; $p < $count; ++$p) {

        $point = $points[$p];
        if(!$this->pointTextVisible($point, $length, $t_offset))
          continue;

        $labels .= $this->getText($x + $x_offset, $y + $y_offset, $point,
          $opposite, 0);
      }
      if($labels != '') {
        $group = [
          'font-family' => $this->styles['t_font'],
          'font-size' => $this->styles['t_font_size'],
          'fill' => $this->styles['t_colour'],
        ];
        $weight = $this->styles['t_font_weight'];
        if($weight != 'normal' && $weight !== null)
          $group['font-weight'] = $weight;

        $labels = $this->graph->element('g', $group, null, $labels);
      }
    }
    if($this->show_label)
      $labels .= $this->getLabel($x, $y, $gx, $gy, $g_width, $g_height);

    return $labels;
  }

  /**
   * Returns the label
   */
  protected function getLabel($x, $y, $gx, $gy, $g_width, $g_height)
  {
    $pos = $this->getLabelPosition();
    $offset = $this->getLabelOffset($x, $y, $gx, $gy, $g_width, $g_height);
    $tx = $offset['x'] + $pos['tx'];
    $ty = $offset['y'] + $pos['ty'];
    $label = [
      'text-anchor' => 'middle',
      'font-family' => $this->styles['l_font'],
      'font-size' => $this->styles['l_font_size'],
      'fill' => $this->styles['l_colour'],
      'x' => $tx,
      'y' => $ty,
    ];
    if(!empty($this->styles['l_font_weight']) &&
      $this->styles['l_font_weight'] != 'normal')
      $label['font-weight'] = $this->styles['l_font_weight'];
    if($pos['angle']) {
      $xform = new Transform;
      $xform->rotate($pos['angle'], $tx, $ty);
      $label['transform'] = $xform;
    }

    $svg_text = new Text($this->graph, $this->styles['l_font']);
    return $svg_text->text($this->label, $this->styles['l_line_spacing'], $label);
  }

  /**
   * Returns the corrected offset for the label
   */
  protected function getLabelOffset($x, $y, $gx, $gy, $g_width, $g_height)
  {
    if($this->orientation == 'h') {

      // label always at bottom of grid
      $y = $gy + $g_height;

    } else {

      // leftmost axis label must be outside grid
      if($this->axis_no == 0)
        $x = $gx;
    }
    return ['x' => $x, 'y' => $y];
  }

  /**
   * Returns the dimensions of the label
   * x, y, width, height = position and size
   * tx, tx = text anchor point
   * angle = text angle
   */
  protected function getLabelPosition()
  {
    $bbox = $this->measure(false);
    $font_size = $this->styles['l_font_size'];
    $line_spacing = $this->styles['l_line_spacing'];
    $svg_text = new Text($this->graph, $this->styles['l_font']);
    $tsize = $svg_text->measure($this->label, $font_size, 0, $line_spacing);
    $baseline = $svg_text->baseline($font_size);
    $a_length = $this->axis->getLength();
    $space = $this->styles['l_space'];
    $align = $this->styles['l_pos'];

    if($this->orientation == 'h') {
      $width = $tsize[0];
      $height = $tsize[1];
      $y = $bbox->y2 + $space;
      if(is_numeric($align)) {
        $tx = $a_length * $align;
      } else {
        switch($align) {
        case 'left' :
          $tx = $width / 2;
          break;
        case 'right':
          $tx = $a_length - ($width / 2);
          break;
        default:
          $tx = $a_length / 2;
          break;
        }
      }
      $ty = $y + $baseline;
      $x = $tx - $width / 2;

      $height += $space;
      $angle = 0;
    } else {
      $width = $tsize[1];
      $height = $tsize[0];
      if($this->axis_no > 0) {
        $x = $bbox->x2 + $space;
        $tx = $x + $width - $baseline;
      } else {
        $x = $bbox->x1 - $space - $width;
        $tx = $x + $baseline;
        $x -= $space;
      }
      if(is_numeric($align)) {
        $ty = -$a_length * $align;
      } else {
        switch($align) {
        case 'top' :
          $ty = -$a_length + $height / 2;
          break;
        case 'bottom':
          $ty = -$height / 2;
          break;
        default:
          $ty = -$a_length / 2;
          break;
        }
      }
      $y = $ty - $height / 2;
      $width += $space;
      $angle = $this->axis_no > 0 ? 90 : 270;
    }

    return compact('x', 'y', 'width', 'height', 'tx', 'ty', 'angle');
  }

  /**
   * Returns the distance from the axis to draw the text
   */
  protected function getTextOffset($ax, $ay, $gx, $gy, $g_width, $g_height,
    $level)
  {
    $d1 = $d2 = 0;
    if($this->show_divisions && $level == 0) {
      if(!$this->boxed_text) {
        $d_info = $this->getDivisionPathInfo(false, 100, 100, 0);
        if($d_info !== null) {
          $d1 = $d_info['pos'];
          $d2 = $d1 + $d_info['sz'];
        }
      }
      if($this->show_subdivisions) {
        $s_info = $this->getDivisionPathInfo(true, 100, 100, 0);
        if($s_info !== null) {
          $s1 = $s_info['pos'];
          $s2 = $s1 + $s_info['sz'];
          $d1 = min($d1, $s1);
          $d2 = max($d2, $s2);
        }
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
      $x = ($this->block_label ? $this->axis->unit() * 0.5 : 0);
      if($grid)
        $y += $gy + $g_height - $ay;
    } else {
      if($this->axis_no > 0)
        $opposite = !$opposite;
      $x = ($opposite ? $d2 : $d1);
      $y = ($this->block_label ? $this->axis->unit() * -0.5 : 0);
      if($grid && $this->axis_no < 2) {
        $x += $gx - $ax;
        if($this->axis_no == 1)
          $x += $g_width;
      }
    }
    return [$x, $y, $opposite];
  }

  /**
   * Returns the bounding box for a single axis label
   */
  protected function measureText($x, $y, &$point, $opposite, $level)
  {
    list($svg_text, $font_size, $attr, $anchor, $rcx, $rcy, $angle,
      $line_spacing) = $this->getTextInfo($x, $y, $point, $opposite, $level);

    // find (rotated) size and position now
    list($x, $y, $w, $h) = $svg_text->measurePosition($point->getText($level),
      $font_size, $line_spacing, $attr['x'], $attr['y'], $anchor, $angle,
      $rcx, $rcy);
    return ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h];
  }

  /**
   * Returns the SVG fragment for a single axis label
   */
  protected function getText($x, $y, &$point, $opposite, $level)
  {
    // skip 0 on axis when it would sit on top of other axis
    if(!$this->block_label && $point->value == 0) {
      if($this->axis_no == 0 && $opposite)
        return '';
      if($this->axis_no == 1 && !$opposite)
        return '';
    }

    $string = $point->getText($level);
    $text_out = '';
    $text_info = $this->getTextInfo($x, $y, $point, $opposite, $level);
    $svg_text = $text_info[0];
    $attr = $text_info[2];
    $line_spacing = $text_info[7];

    if(!empty($this->styles['t_back_colour'])) {
      $b_attr = array_merge($this->styles['t_back_colour'], $attr);
      $text_out .= $svg_text->text($string, $line_spacing, $b_attr);
    }
    $text_out .= $svg_text->text($string, $line_spacing, $attr);
    return $text_out;
  }

  /**
   * Returns text information:
   * [Text, $font_size, $attr, $anchor, $rcx, $rcy, $angle, $line_spacing]
   */
  protected function getTextInfo($x, $y, &$point, $opposite, $level)
  {
    $font_size = $this->styles['t_font_size'];
    $line_spacing = $this->styles['t_line_spacing'];
    $svg_text = new Text($this->graph, $this->styles['t_font'],
      $this->styles['t_font_adjust']);
    $baseline = $svg_text->baseline($font_size);
    list($w, $h) = $svg_text->measure($point->getText($level), $font_size, 0,
      $line_spacing);
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
    $rcx = $rcy = null;
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
      $xform = new Transform;
      $xform->rotate($angle, $rcx, $rcy);
      $attr['transform'] = $xform;
    }
    $attr['text-anchor'] = $anchor;

    return [$svg_text, $font_size, $attr, $anchor, $rcx, $rcy, $angle,
      $line_spacing];
  }

  /**
   * Returns the path for divisions or subdivisions
   */
  protected function getDivisionPath($x, $y, $points, $path_info, $level)
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
    $path = new PathData;
    foreach($points as $point) {
      $x = $x0 + $point->position * $xinc;
      $y = $y0 + $point->position * $yinc;
      $path->add('M', $x, $y, $line, $len);
    }

    if(!$path->isEmpty() && $path_info['box']) {
      $x = $x0 + $path_info['box_pos'] * $yinc;
      $y = $y0 + $path_info['box_pos'] * $xinc;
      $line = $path_info['box_line'];
      $len = $path_info['box_len'];
      $path->add('M', $x, $y, $line, $len);
    }
    return $path;
  }

  /**
   * Returns the details of the path segment
   */
  protected function getDivisionPathInfo($subdiv, $g_width, $g_height, $level)
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
        $bbox = $this->getTextBBox($level);
        $sx = $bbox->width();
        $sy = $bbox->height();
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

