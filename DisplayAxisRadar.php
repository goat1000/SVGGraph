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
 * Draws the radar graph X axis
 */
class DisplayAxisRadar extends DisplayAxis {

  protected $xc;
  protected $yc;
  protected $radius;
  protected $arad;
  protected $text_offset;
  protected $no_axis;
  protected $direction = 1;

  /**
   * $orientation = 'h' or 'v'
   * $type = 'x' or 'y'
   * $main = TRUE for the main axis
   */
  public function __construct(&$graph, &$axis, $axis_no, $orientation, $type,
    $main, $xc, $yc, $radius)
  {
    // only use this class for the round axis
    if($orientation != 'h')
      throw new \Exception('DisplayAxisRadar: orientation != "h"');
    $this->xc = $xc;
    $this->yc = $yc;
    $this->radius = $radius;
    $this->arad = (90 + $graph->getOption('start_angle')) * M_PI / 180;
    $this->text_offset = 0;
    // the radar-only option for hiding axis but showing divisions
    $this->no_axis = !$graph->getOption('show_x_axis');

    // $label_centre = TRUE because the end of the axis is also the start
    parent::__construct($graph, $axis, $axis_no, $orientation, $type, $main,
      true);

    // no boxed text because this isn't really a block-labelled axis
    $this->boxed_text = false;

    if($graph->getOption('reverse'))
      $this->direction = -1;

    // keep text next to axis
    $this->styles['t_location'] = 'axis';
  }

  /**
   * Returns the extents of the axis, relative to where it will be drawn from
   *  returns array('x', 'y', 'width', 'height')
   */
  public function measure($with_label = true)
  {
    $bbox = new BoundingBox($this->xc, $this->yc, $this->xc, $this->yc);
    if($this->show_axis) {

      // find radius including division marks
      $d = 0;
      if($this->show_divisions) {
        $d_info = $this->getDivisionPathInfo(false, 0, 0, 0);
        if($d_info['pos'] < 0)
          $d = abs($d_info['pos']);
        if($this->show_subdivisions) {
          $s_info = $this->getDivisionPathInfo(true, 0, 0, 0);
          if($s_info['pos'] < 0) {
            $s = abs($s_info['pos']);
            if($s > $d)
              $d = $s;
          }
        }
      }
      $r = $this->radius + $d;
      $bbox->grow($this->xc - $r, $this->yc - $r, $this->xc + $r, $this->yc + $r);
    }

    if($this->show_text) {
      list($x_off, $y_off, $opp) = $this->getTextOffset(0, 0, 0, 0, 0, 0, 0);

      $points = $this->axis->getGridPoints(0);
      $count = count($points);
      if($this->block_label)
        --$count;
      for($p = 0; $p < $count; ++$p) {

        if($points[$p]->blank())
          continue;

        $lbl = $this->measureText($x_off, $y_off, $points[$p], $opp, 0);
        $bbox->grow($lbl['x'], $lbl['y'], $lbl['x'] + $lbl['width'],
          $lbl['y'] + $lbl['height']);
      }
    }

    if($with_label && $this->show_label) {
      $lpos = $this->getLabelPosition();
      $bbox->grow($lpos['x'], $lpos['y'], $lpos['x'] + $lpos['width'],
        $lpos['y'] + $lpos['height']);
    }

    return $bbox;
  }

  /**
   * Draws the axis line
   */
  public function drawAxisLine($x, $y, $len)
  {
    // the 'show_x_axis' option turns the line off
    if($this->no_axis)
      return '';
    $points = [new GridPoint($this->radius, '', 0)];
    $attr = [
      'stroke' => $this->styles['colour'],
      'd' => $this->graph->yGrid($points),
      'fill' => 'none',
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
    $path = new PathData;
    $len = -$path_info['sz'];
    $r1 = $this->radius - $path_info['pos'];
    foreach($points as $p) {
      $a = $this->arad + $this->direction * $p->position / $this->radius;
      $x1 = $x + $r1 * sin($a);
      $y1 = $y + $r1 * cos($a);
      $x2 = $len * sin($a);
      $y2 = $len * cos($a);
      $path->add('M', $x1, $y1, 'l', $x2, $y2);
    }
    if(!$path->isEmpty() && $path_info['box']) {
      $points = [new GridPoint($this->radius + $path_info['sz'], '', 0)];
      $path->add($this->graph->yGrid($points));
    }
    return $path;
  }

  /**
   * Returns the distance from the axis to draw the text
   */
  protected function getTextOffset($ax, $ay, $gx, $gy, $g_width, $g_height, $level)
  {
    list($x, $y, $opposite) = parent::getTextOffset($ax, $ay, $gx, $gy,
      $g_width, $g_height, $level);
    $this->text_offset = $y;
    return [$x, $y, $opposite];
  }

  /**
   * Returns text information:
   * [Text, $font_size, $attr, $anchor, $rcx, $rcy, $angle, $line_spacing]
   */
  protected function getTextInfo($x, $y, &$point, $opposite, $level)
  {
    $a = $this->arad + $this->direction * $point->position / $this->radius;
    $r1 = $this->radius + $this->text_offset;
    $x1 = $this->xc + $r1 * sin($a);
    $y1 = $this->yc + $r1 * cos($a);
    $text_angle = $this->styles['t_angle'];

    $tau = 2 * M_PI;
    while($a < 0)
      $a += $tau;
    $a = fmod($a, $tau);

    $t_angle = deg2rad($text_angle);
    while($t_angle < 0)
      $t_angle += $tau;

    $sector = floor(($a + $t_angle) * 15.999 / $tau) % 16;
    // text anchor depends on angle between text and axis
    $left = $opposite ? 'start' : 'end';
    $right = $opposite ? 'end' : 'start';
    $anchor = $right;
    if($sector == 0 || $sector == 7 || $sector == 8 || $sector == 15)
      $anchor = 'middle';
    elseif($sector > 8)
      $anchor = $left;
    $top = ($opposite ? $sector == 7 || $sector == 8 :
      $sector == 0 || $sector == 15);

    $font_size = $this->styles['t_font_size'];
    $line_spacing = $this->styles['t_line_spacing'];
    $svg_text = new Text($this->graph, $this->styles['t_font'],
      $this->styles['t_font_adjust']);
    $baseline = $svg_text->baseline($font_size);
    list($w, $h) = $svg_text->measure($point->getText(), $font_size, 0,
      $line_spacing);

    $attr['x'] = $x1;
    if($anchor == 'middle')
      $attr['y'] = $y1 + ($top ? $baseline : 0);
    else
      $attr['y'] = $y1 + $baseline - $h / 2;

    $rcx = $rcy = null;
    if($text_angle) {
      $rcx = $x1;
      $rcy = $y1;
      $xform = new Transform;
      $xform->rotate($text_angle, $rcx, $rcy);
      $attr['transform'] = $xform;
    }
    $attr['text-anchor'] = $anchor;
    return [$svg_text, $font_size, $attr, $anchor, $rcx, $rcy, $text_angle,
      $line_spacing];
  }

  /**
   * Returns the label
   */
  protected function getLabel($x, $y, $gx, $gy, $g_width, $g_height)
  {
    // GetLabelPosition returns absolute text position, so ignore $x and $y
    return parent::getLabel(0, 0, $gx, $gy, $g_width, $g_height);
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
    $space = $this->styles['l_space'];

    $width = $tsize[0];
    $height = $tsize[1];
    $y = $bbox->y2 + $space;
    $tx = $this->xc;
    $ty = $y + $baseline;
    $x = $tx - $width / 2;

    $height += $space;
    $angle = 0;
    $res = compact('x', 'y', 'width', 'height', 'tx', 'ty', 'angle', 'bbox');
    return $res;
  }

}

