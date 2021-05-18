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
 * A rotated axis
 */
class DisplayAxisRotated extends DisplayAxis {

  protected $arad;
  protected $anchor;
  protected $top;
  protected $label_offset = 0;
  protected $label_angle = 0;

  /**
   * $arad = angle in radians
   */
  public function __construct(&$graph, &$axis, $axis_no, $orientation, $type,
    $main, $arad)
  {
    if($orientation != 'v')
      throw new \Exception('DisplayAxisRotated: orientation != "v"');
    $this->arad = $arad;
    $this->anchor = 'end';
    $this->top = false;
    parent::__construct($graph, $axis, $axis_no, $orientation, $type, $main,
      false);

    // rotated axis can't position text at edge of grid
    $this->styles['t_location'] = 'axis';
  }

  /**
   * Draws the axis line
   */
  public function drawAxisLine($x, $y, $len)
  {
    $overlap = $this->styles['overlap'];
    $length = $len + $overlap * 2;
    $x0 = $x - $overlap * sin($this->arad);
    $y0 = $y - $overlap * cos($this->arad);
    $x1 = $length * sin($this->arad);
    $y1 = $length * cos($this->arad);

    $attr = [
      'stroke' => $this->styles['colour'],
      'd' => new PathData('M', $x0, $y0, 'l', $x1, $y1),
    ];

    if($this->styles['stroke_width'] != 1)
      $attr['stroke-width'] = $this->styles['stroke_width'];
    return $this->graph->element('path', $attr);
  }

  /**
   * Returns the path for divisions or subdivisions
   */
  protected function getDivisionPath($x, $y, $points, $path_info, $level)
  {
    $a = $this->arad + ($this->arad <= M_PI_2 ? - M_PI_2 : M_PI_2);
    $path = new PathData;
    $c = cos($this->arad);
    $s = sin($this->arad);
    $px = $path_info['pos'] * sin($a);
    $py = $path_info['pos'] * cos($a);
    $x2 = $path_info['sz'] * sin($a);
    $y2 = $path_info['sz'] * cos($a);
    if($this->type == 'y')
    {
      $px = -$px;
      $py = -$py;
      $x2 = -$x2;
      $y2 = -$y2;
    }
    foreach($points as $pt) {
      $x1 = ($x - $pt->position * $s) + $px;
      $y1 = ($y - $pt->position * $c) + $py;
      $path->add('M', $x1, $y1, 'l', $x2, $y2);
    }
    if($path != '' && $path_info['box']) {
      $x1 = abs($path_info['box_len']) * $s;
      $y1 = abs($path_info['box_len']) * $c;
      $x -= $x2;
      $y -= $y2;
      $path->add('M', $x, $y, 'l', $x1, $y1);
    }
    return $path;
  }

  /**
   * Calculates the rotated offset
   */
  protected function getTextOffset($ax, $ay, $gx, $gy, $g_width, $g_height, $level)
  {
    list($x, $y, $opposite) = parent::getTextOffset($ax, $ay, $gx, $gy,
      $g_width, $g_height, $level);

    $tau = 2 * M_PI;
    $a = $this->arad + M_PI_2;
    while($a < 0)
      $a += $tau;
    $a = fmod($a, $tau);

    $t_angle = deg2rad($this->styles['t_angle']);
    while($t_angle < 0)
      $t_angle += $tau;

    $sector = floor(($a + $t_angle) * 15.999 / $tau) % 16;
    $c = cos($a);
    $s = sin($a);
    $len = $x;
    $x = -$len * $s;
    $y = -$len * $c;

    // text anchor depends on angle between text and axis
    $left = $opposite ? 'start' : 'end';
    $right = $opposite ? 'end' : 'start';
    $this->anchor = $right;
    if($sector == 0 || $sector == 7 || $sector == 8 || $sector == 15)
      $this->anchor = 'middle';
    elseif($sector > 8)
      $this->anchor = $left;
    $this->top = ($opposite ? $sector == 7 || $sector == 8 :
      $sector == 0 || $sector == 15);
    return [$x, $y, $opposite];
  }

  /**
   * Returns text information:
   * [Text, $font_size, $attr, $anchor, $rcx, $rcy, $angle, $line_spacing]
   */
  protected function getTextInfo($x, $y, &$point, $opposite, $level)
  {
    $direction = $this->type == 'y' ? -1 : 1;
    $x_add = $point->position * $direction * sin($this->arad);
    $y_add = $point->position * $direction * cos($this->arad);

    $font_size = $this->styles['t_font_size'];
    $line_spacing = $this->styles['t_line_spacing'];
    $svg_text = new Text($this->graph, $this->styles['t_font'],
      $this->styles['t_font_adjust']);
    $baseline = $svg_text->baseline($font_size);
    list($w, $h) = $svg_text->measure($point->getText(), $font_size, 0,
      $line_spacing);
    $attr = [
      'x' => $x + $x_add,
      'text-anchor' => $this->anchor,
    ];

    if($this->anchor == 'middle')
      $attr['y'] = $y + $y_add + ($this->top ? $baseline : 0);
    else
      $attr['y'] = $y + $y_add + $baseline - $h / 2;

    $angle = $this->styles['t_angle'];
    $rcx = $rcy = null;
    if($angle) {
      $rcx = $x + $x_add;
      $rcy = $y + $y_add;
      $xform = new Transform;
      $xform->rotate($angle, $rcx, $rcy);
      $attr['transform'] = $xform;
    }
    return [$svg_text, $font_size, $attr, $this->anchor, $rcx, $rcy, $angle,
      $line_spacing];
  }

  /**
   * Override position correction
   */
  protected function getLabelOffset($x, $y, $gx, $gy, $g_width, $g_height)
  {
    // no need for correction
    return ['x' => $x, 'y' => $y];
  }

  /**
   * Returns the dimensions of the label
   */
  protected function getLabelPosition()
  {
    $font_size = $this->styles['l_font_size'];
    $line_spacing = $this->styles['l_line_spacing'];
    $svg_text = new Text($this->graph, $this->styles['l_font']);
    $tsize = $svg_text->measure($this->label, $font_size, 0, $line_spacing);
    $baseline = $svg_text->baseline($font_size);
    $c = cos($this->arad);
    $s = sin($this->arad);

    // use plain axis for calculating distance from axis
    $plain = new DisplayAxis($this->graph, $this->axis, $this->axis_no,
      $this->orientation, $this->type, $this->main, false);
    $bbox = $plain->measure(false);
    $space = $this->styles['l_space'];

    if($s < 0) {
      $offset = $bbox->x2 + $space + $tsize[1] - $baseline;
      $angle = 180 - (rad2deg($this->arad) - 90);
    } else {
      $offset = -$bbox->x1 + $space + $tsize[1] - $baseline;
      $angle = - (rad2deg($this->arad) - 90);
    }

    $a = $this->arad + M_PI_2;
    $x2 = $offset * sin($a);
    $y2 = $offset * cos($a);
    $p = $this->axis->getLength() / 2;
    $tx = $p * sin($this->arad) + $x2;
    $ty = $p * cos($this->arad) + $y2;

    // these don't matter - the text is over the graph anyway
    $x = $y = $width = $height = 0;

    return compact('x', 'y', 'width', 'height', 'tx', 'ty', 'angle');
  }

  /**
   * Returns true if the text exists
   */
  protected function pointTextVisible($point, $axis_len, $offset)
  {
    return !$point->blank();
  }
}
