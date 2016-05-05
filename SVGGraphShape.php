<?php
/**
 * Copyright (C) 2015-2016 Graham Breach
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

require_once "SVGGraphCoords.php";

define("SVGG_SHAPE_ABOVE", 1);
define("SVGG_SHAPE_BELOW", 0);

/**
 * Arbitrary shapes for adding to graphs
 */
class SVGGraphShapeList {

  private $graph;
  private $shapes = array();

  public function __construct(&$graph)
  {
    $this->graph = $graph;
  }

  /**
   * Load shapes from options list
   */
  public function Load(&$settings)
  {
    if(!isset($settings['shape']))
      return;
  
    if(!is_array($settings['shape']) || !isset($settings['shape'][0]))
      throw new Exception('Malformed shape option');

    if(!is_array($settings['shape'][0])) {
      $this->AddShape($settings['shape']);
    } else {
      foreach($settings['shape'] as $shape) {
        $this->AddShape($shape);
      }
    }
  }

  /**
   * Draw all the shapes for the selected depth
   */
  public function Draw($depth)
  {
    $content = array();
    foreach($this->shapes as $shape) {
      if($shape->Depth($depth))
        $content[] = $shape->Draw($this->graph);
    }
    return implode($content);
  }

  /**
   * Adds a shape from config array
   */
  private function AddShape(&$shape_array)
  {
    $shape = $shape_array[0];
    unset($shape_array[0]);

    $class_map = array(
      'circle' => 'SVGGraphCircle',
      'ellipse' => 'SVGGraphEllipse',
      'rect' => 'SVGGraphRect',
      'line' => 'SVGGraphLine',
      'polyline' => 'SVGGraphPolyLine',
      'polygon' => 'SVGGraphPolygon',
      'path' => 'SVGGraphPath',
    );

    if(isset($class_map[$shape]) && class_exists($class_map[$shape])) {
      $depth = SVGG_SHAPE_BELOW;
      if(isset($shape_array['depth'])) {
        if($shape_array['depth'] == 'above')
          $depth = SVGG_SHAPE_ABOVE;
      }
      if(isset($shape_array['clip_to_grid']) && $shape_array['clip_to_grid'] &&
        method_exists($this->graph, 'GridClipPath')) {
        $clip_id = $this->graph->GridClipPath();
        $shape_array['clip-path'] = "url(#{$clip_id})";
      }
      unset($shape_array['depth'], $shape_array['clip_to_grid']);
      $this->shapes[] = new $class_map[$shape]($shape_array, $depth);
    } else {
      throw new Exception("Unknown shape [{$shape}]");
    }
  }
}

abstract class SVGGraphShape {

  protected $depth = SVGG_SHAPE_BELOW;
  protected $element = '';
  protected $link = NULL;
  protected $link_target = '_blank';
  protected $coords = NULL;

  /**
   * attributes required to draw shape
   */
  protected $required = array();

  /**
   * attributes that support coordinate transformation
   */
  protected $transform = array();

  /**
   * coordinate pairs for dependent transforns - don't include them in
   * $transform or they will be transformed twice
   */
  protected $transform_pairs = array();

  /**
   * colour gradients/patterns, and whether to allow gradients
   */
  private $colour_convert = array(
    'stroke' => true,
    'fill' => false
  );

  /**
   * default attributes for all shapes
   */
  protected $attrs = array(
    'stroke' => '#000',
    'fill' => 'none'
  );

  public function __construct(&$attrs, $depth)
  {
    $this->attrs = array_merge($this->attrs, $attrs);
    $this->depth = $depth;

    $missing = array();
    foreach($this->required as $opt)
      if(!isset($this->attrs[$opt]))
        $missing[] = $opt;

    if(count($missing))
      throw new Exception("{$this->element} attribute(s) not found: " .
        implode(', ', $missing));

    if(isset($this->attrs['href']))
      $this->link = $this->attrs['href'];
    if(isset($this->attrs['xlink:href']))
      $this->link = $this->attrs['xlink:href'];
    if(isset($this->attrs['target']))
      $this->link_target = $this->attrs['target'];
    unset($this->attrs['href'], $this->attrs['xlink:href'],
      $this->attrs['target']);
  }

  /**
   * returns true if the depth is correct
   */
  public function Depth($d)
  {
    return $this->depth == $d;
  }

  /**
   * draws the shape
   */
  public function Draw(&$graph)
  {
    $this->coords = new SVGGraphCoords($graph);

    $attributes = array();
    foreach($this->attrs as $attr => $value) {
      if(!is_null($value)) {
        if(isset($this->transform[$attr])) {
          $val = $this->coords->Transform($value, $this->transform[$attr]);
        } else {
          $val = isset($this->colour_convert[$attr]) ? 
            $graph->ParseColour($value, NULL, $this->colour_convert[$attr]) :
            $value;
        }
        $attr = str_replace('_', '-', $attr);
        $attributes[$attr] = $val;
      }
    }
    $this->TransformCoordinates($attributes);
    $element = $this->DrawElement($graph, $attributes);
    if(!is_null($this->link)) {
      $link = array('xlink:href' => $this->link);
      if(!is_null($this->link_target))
        $link['target'] = $this->link_target;
      $element = $graph->Element('a', $link, NULL, $element);
    }
    return $element;
  }

  /**
   * Transform coordinate pairs
   */
  protected function TransformCoordinates(&$attributes)
  {
    if(count($this->transform_pairs)) {
      foreach($this->transform_pairs as $pair) {
        $coords = $this->coords->TransformCoords($attributes[$pair[0]],
          $attributes[$pair[1]]);
        $attributes[$pair[0]] = $coords[0];
        $attributes[$pair[1]] = $coords[1];
      }
    }
  }

  /**
   * Performs the conversion to SVG fragment
   */
  protected function DrawElement(&$graph, &$attributes)
  {
    return $graph->Element($this->element, $attributes);
  }

}

class SVGGraphCircle extends SVGGraphShape {
  protected $element = 'circle';
  protected $required = array('cx','cy','r');
  protected $transform = array('r' => 'y');
  protected $transform_pairs = array(array('cx', 'cy'));
}

class SVGGraphEllipse extends SVGGraphShape {
  protected $element = 'ellipse';
  protected $required = array('cx','cy','rx','ry');
  protected $transform = array('rx' => 'x', 'ry' => 'y');
  protected $transform_pairs = array(array('cx', 'cy'));
}

class SVGGraphRect extends SVGGraphShape {
  protected $element = 'rect';
  protected $required = array('x','y','width','height');
  protected $transform = array('width' => 'x', 'height' => 'y');
  protected $transform_pairs = array(array('x', 'y'));
}

class SVGGraphLine extends SVGGraphShape {
  protected $element = 'line';
  protected $required = array('x1','y1','x2','y2');
  protected $transform_pairs = array(array('x1', 'y1'), array('x2','y2'));
}

class SVGGraphPath extends SVGGraphShape {
  protected $element = 'path';
  protected $required = array('d');
}

class SVGGraphPolyLine extends SVGGraphShape {
  protected $element = 'polyline';
  protected $required = array('points');

  public function __construct(&$attrs, $depth)
  {
    parent::__construct($attrs, $depth);
    if(!is_array($this->attrs['points']))
      $this->attrs['points'] = explode(' ', $this->attrs['points']);
    $count = count($this->attrs['points']);
    if($count < 4 || $count % 2 == 1)
      throw new Exception("Shape must have at least 2 pairs of points");
  }

  /**
   * Override to transform pairs of points
   */
  protected function TransformCoordinates(&$attributes)
  {
    $count = count($attributes['points']);
    for($i = 0; $i < $count; $i += 2) {
      $x = $attributes['points'][$i];
      $y = $attributes['points'][$i + 1];
      $coords = $this->coords->TransformCoords($x, $y);
      $attributes['points'][$i] = $coords[0];
      $attributes['points'][$i + 1] = $coords[1];
    }
  }

  /**
   * Override to build the points attribute
   */
  protected function DrawElement(&$graph, &$attributes)
  {
    $attributes['points'] = implode(' ', $attributes['points']);
    return parent::DrawElement($graph, $attributes);
  }
}

class SVGGraphPolygon extends SVGGraphPolyLine {
  protected $element = 'polygon';
}

