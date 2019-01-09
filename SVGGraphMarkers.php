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
 * Class for defining marker graphics as symbols
 */
class SVGGraphMarkers {

  private $graph;

  public function __construct(&$graph)
  {
    $this->graph =& $graph;
  }

  /**
   * Creates a marker
   */
  public function Create($type, $size, $fill, $stroke_width, $stroke_colour,
    $opacity, $angle, $more = null)
  {
    $marker = array('fill' => $fill);
    if(!empty($stroke_colour) && $stroke_colour != 'none') {
      $marker['stroke'] = $stroke_colour;
      if(!empty($stroke_width))
        $marker['stroke-width'] = $stroke_width;
    }
    if($opacity > 0.0 && $opacity < 1.0)
      $marker['opacity'] = $opacity;

    // check for custom or image markers
    $content = null;
    if($type[0] == '<') {
      $content = $type;
      $type = 'custom';
    } elseif(strncmp($type, 'image:', 6) == 0) {
      $svg_text = new SVGGraphText($this->graph);
      $image_path = $svg_text->Substr($type, 6);
      $type = 'image';
    } elseif(strncmp($type, 'figure:', 7) == 0) {
      $svg_text = new SVGGraphText($this->graph);
      $figure = $svg_text->Substr($type, 7);
      $type = 'figure';
    }

    $a = $size; // will be repeated a lot, and 'a' is smaller
    $element = 'path';
    switch($type) {
    case 'triangle' :
      $o = $a * tan(M_PI / 6);
      $h = $a / cos(M_PI / 6);
      $marker['d'] = "M$a,$o L0,-$h L-$a,$o z";
      break;
    case 'diamond' :
      $marker['d'] = "M0 -{$a}L$a 0 0 $a -$a 0z";
      break;
    case 'square' :
      $element = 'rect';
      $marker['x'] = $marker['y'] = -$a;
      $marker['width'] = $marker['height'] = $a * 2;
      break;
    case 'x' :
      $marker['transform'] = 'rotate(45)';
      // no break - 'x' is a cross rotated by 45 degrees
    case 'cross' :
      $t = $a / 4;
      $marker['d'] = "M-$a,-$t L-$a,$t -$t,$t -$t,$a " .
        "$t,$a $t,$t $a,$t " .
        "$a,-$t $t,-$t $t,-$a " .
        "-$t,-$a -$t,-$t z";
      break;
    case 'octagon' :
      $t = $a * sin(M_PI / 8);
      $marker['d'] = "M$t -{$a}L$a -$t $a $t $t $a -$t $a " .
        "-$a $t -$a -$t -$t -{$a}z";
      break;
    case 'star' :
      $t = $a * 0.382;
      $x1 = $t * sin(M_PI * 0.8);
      $y1 = $t * cos(M_PI * 0.8);
      $x2 = $a * sin(M_PI * 0.6);
      $y2 = $a * cos(M_PI * 0.6);
      $x3 = $t * sin(M_PI * 0.4);
      $y3 = $t * cos(M_PI * 0.4);
      $x4 = $a * sin(M_PI * 0.2);
      $y4 = $a * cos(M_PI * 0.2);
      $marker['d'] = "M0 -{$a}L$x1 $y1 $x2 $y2 $x3 $y3 $x4 $y4 0 $t " .
        "-$x4 $y4 -$x3 $y3 -$x2 $y2 -$x1 $y1 z";
      break;
    case 'threestar' :
      $t = $a / 4;
      $t1 = $t * cos(M_PI / 6);
      $t2 = $t * sin(M_PI / 6);
      $a1 = $a * cos(M_PI / 6);
      $a2 = $a * sin(M_PI / 6);
      $marker['d'] = "M0 -{$a}L$t1 -$t2 $a1 $a2 0 $t -$a1 $a2 -$t1 -{$t2}z";
      break;
    case 'fourstar' :
      $t = $a / 4;
      $marker['d'] = "M0 -{$a}L$t -$t $a 0 $t $t " .
        "0 $a -$t $t -$a 0 -$t -{$t}z";
      break;
    case 'eightstar' :
      $t = $a * sin(M_PI / 8);
      $marker['d'] = "M0 -{$t}L$t -$a $t -$t $a -$t $t 0 " .
        "$a $t $t $t $t $a 0 $t -$t $a -$t $t -$a $t -$t 0 " .
        "-$a -$t -$t -$t -$t -{$a}z";
      break;
    case 'asterisk' :
      $t = $a / 3;
      $x1 = $a * sin(M_PI * 0.9);
      $y1 = $a * cos(M_PI * 0.9);
      $x2 = $t * sin(M_PI * 0.8);
      $y2 = $t * cos(M_PI * 0.8);
      $x3 = $a * sin(M_PI * 0.7);
      $y3 = $a * cos(M_PI * 0.7);
      $x4 = $a * sin(M_PI * 0.5);
      $y4 = $a * cos(M_PI * 0.5);
      $x5 = $t * sin(M_PI * 0.4);
      $y5 = $t * cos(M_PI * 0.4);
      $x6 = $a * sin(M_PI * 0.3);
      $y6 = $a * cos(M_PI * 0.3);
      $x7 = $a * sin(M_PI * 0.1);
      $y7 = $a * cos(M_PI * 0.1);
      $marker['d'] = "M$x1 {$y1}L$x2 $y2 $x3 $y3 $x4 $y4 $x5 $y5 " .
        "$x6 $y6 $x7 $y7 0 $t -$x7 $y7 -$x6 $y6 -$x5 $y5 -$x4 $y4 " .
        "-$x3 $y3 -$x2 $y2 -$x1 ${y1}z";
      break;
    case 'pentagon' :
      $x1 = $a * sin(M_PI * 0.4);
      $y1 = $a * cos(M_PI * 0.4);
      $x2 = $a * sin(M_PI * 0.2);
      $y2 = $a * cos(M_PI * 0.2);
      $marker['d'] = "M0 -{$a}L$x1 -$y1 $x2 $y2 -$x2 $y2 -$x1 -{$y1}z";
      break;
    case 'hexagon' :
      $x = $a * sin(M_PI / 3);
      $y = $a * cos(M_PI / 3);
      $marker['d'] = "M0 -{$a}L$x -$y $x $y 0 $a -$x $y -$x -{$y}z";
      break;
    case 'image' :
      $element = 'image';
      $marker['xlink:href'] = $image_path;
      $marker['x'] = $marker['y'] = -$size;
      $marker['width'] = $size * 2;
      $marker['height'] = $size * 2;
      break;
    case 'custom' :
      $element = 'g';
      break;
    case 'figure' :
      $element = 'g';
      $figure_id = $this->graph->figures->GetFigure($figure);
      if(empty($figure_id))
        throw new Exception("Figure [$figure] not defined");
      return $figure_id;
      break;
    case 'circle' :
    default :
      $element = 'circle';
      $marker['r'] = $size;
    }

    // angle happens here because the shape might already have a transform
    if($angle != 0) {
      $xform = "rotate({$angle})";
      if(isset($marker['transform']))
        $marker['transform'] .= $xform;
      else
        $marker['transform'] = $xform;
    }

    // extra attributes added at end
    if(is_array($more))
      $marker = array_merge($marker, $more);

    $marker_content = $this->graph->Element($element, $marker, null, $content);
    return $this->graph->symbols->Define($marker_content);
  }
}


