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

require_once 'SVGGraphDisplayAxis.php';

/**
 * A rotated axis
 */
class DisplayAxisRotated extends DisplayAxis {

  protected $arad;
  protected $anchor;
  protected $top;

  /**
   * $arad = angle in radians
   */
  public function __construct(&$graph, &$axis, $axis_no, $orientation, $type,
    $main, $arad)
  {
    if($orientation != 'v')
      throw new Exception('DisplayAxisRotated: orientation != "v"');
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
  public function DrawAxisLine($x, $y, $len)
  {
    $overlap = $this->styles['overlap'];
    $length = $len + $overlap * 2;
    $x0 = $x - $overlap * sin($this->arad);
    $y0 = $y - $overlap * cos($this->arad);
    $x1 = $length * sin($this->arad);
    $y1 = $length * cos($this->arad);

    $attr = array('stroke' => $this->styles['colour'],
      'd' => "M{$x0} {$y0}l$x1 $y1");

    if($this->styles['stroke_width'] != 1)
      $attr['stroke-width'] = $this->styles['stroke_width'];
    return $this->graph->Element('path', $attr);
  }

  /**
   * Returns the path for divisions or subdivisions
   */
  protected function GetDivisionPath($x, $y, $points, $path_info)
  {
    $a = $this->arad + ($this->arad <= M_PI_2 ? - M_PI_2 : M_PI_2);
    $path = '';
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
      $path .= "M$x1 {$y1}l$x2 $y2";
    }
    if($path != '' && $path_info['box']) {
      $x1 = abs($path_info['box_len']) * $s;
      $y1 = abs($path_info['box_len']) * $c;
      $x -= $x2;
      $y -= $y2;
      $path .= "M$x {$y}l$x1 $y1";
    }
    return $path;
  }

  /**
   * Calculates the rotated offset
   */
  protected function GetTextOffset($ax, $ay, $gx, $gy, $g_width, $g_height)
  {
    list($x, $y, $opposite) = parent::GetTextOffset($ax, $ay, $gx, $gy,
      $g_width, $g_height);

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
    return array($x, $y, $opposite);
  }

  /**
   * Returns the SVG fragment for a single axis label
   */
  protected function GetText($x, $y, &$point, $opposite, $measure = FALSE)
  {
    $direction = $this->type == 'y' ? -1 : 1;
    $x_add = $point->position * $direction * sin($this->arad);
    $y_add = $point->position * $direction * cos($this->arad);

    $font_size = $this->styles['t_font_size'];
    $svg_text = new SVGGraphText($this->graph, $this->styles['t_font'],
      $this->styles['t_font_adjust']);
    $baseline = $svg_text->Baseline($font_size);
    list($w, $h) = $svg_text->Measure($point->text, $font_size, 0, $font_size);
    $attr['x'] = $x + $x_add;

    if($this->anchor == 'middle')
      $attr['y'] = $y + $y_add + ($this->top ? $baseline : 0);
    else
      $attr['y'] = $y + $y_add + $baseline - $h / 2;

    $angle = $this->styles['t_angle'];
    $rcx = $rcy = NULL;
    if($angle) {
      $rcx = $x + $x_add;
      $rcy = $y + $y_add;
      $attr['transform'] = "rotate({$angle},{$rcx},{$rcy})";
    }
    $attr['text-anchor'] = $this->anchor;
    if($measure) {
      list($x, $y, $width, $height) = $svg_text->MeasurePosition($point->text,
        $font_size, $font_size, $attr['x'], $attr['y'], $this->anchor, $angle,
        $rcx, $rcy);
      return compact('x', 'y', 'width', 'height');
    }
    return $svg_text->Text($point->text, $font_size, $attr);
  }

}

