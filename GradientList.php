<?php
/**
 * Copyright (C) 2019-2022 Graham Breach
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
 * Creates and stores gradients
 */
class GradientList {

  private $graph;
  private $gradients = [];
  private $gradient_map = [];

  public function __construct(&$graph)
  {
    $this->graph =& $graph;
  }

  /**
   * Adds a gradient to the list, returning the element ID for use in url
   */
  public function addGradient($colours, $key = null, $radial = false)
  {
    if($key === null || !isset($this->gradients[$key])) {

      if($radial) {
        // if this is a radial gradient, it must end with 'r'
        $last = count($colours) - 1;
        if(strlen($colours[$last]) == 1)
          $colours[$last] = 'r';
        else
          $colours[] = 'r';
      }

      // find out if this gradient already stored
      $hash = md5(serialize($colours));
      if(isset($this->gradient_map[$hash]))
        return $this->gradient_map[$hash];

      $id = $this->graph->newID();
      if($key === null)
        $key = $id;
      $this->gradients[$key] = ['id' => $id, 'colours' => $colours];
      $this->gradient_map[$hash] = $id;
      return $id;
    }
    return $this->gradients[$key]['id'];
  }

  /**
   * Creates a gradient element
   */
  private function makeGradient($key)
  {
    $stops = '';
    $direction = 'v';
    $type = 'linearGradient';
    $colours = $this->gradients[$key]['colours'];
    $id = $this->gradients[$key]['id'];

    if(in_array($colours[count($colours)-1], ['h', 'v', 'r']))
      $direction = array_pop($colours);
    if($direction == 'r') {
      $type = 'radialGradient';
      $gradient = ['id' => $id];
    } else {
      $x2 = $direction == 'v' ? 0 : '100%';
      $y2 = $direction == 'h' ? 0 : '100%';
      $gradient = ['id' => $id, 'x1' => 0, 'x2' => $x2, 'y1' => 0, 'y2' => $y2];
    }

    $segments = $this->decompose($colours);
    foreach($segments as $segment) {
      list($offset, $colour, $opacity) = $segment;
      $stop = ['offset' => $offset . '%', 'stop-color' => $colour];
      if(is_numeric($opacity))
        $stop['stop-opacity'] = $opacity;
      $stops .= $this->graph->element('stop', $stop);
    }

    return $this->graph->element($type, $gradient, null, $stops);
  }

  /**
   * Calculates a colour component from a gradient step
   */
  private function calcComponent($c0, $c1, $o0, $o1, $d)
  {
    return $c0 + ($c1 - $c0) * ($d - $o0) / ($o1 - $o0);
  }

  /**
   * Returns the colour at a position in the gradient
   */
  public function getColour($key, $position)
  {
    if(!isset($this->gradients[$key]))
      return '';
    $colours = $this->gradients[$key]['colours'];
    if(in_array($colours[count($colours)-1], ['h', 'v', 'r']))
      $direction = array_pop($colours);
    $segments = $this->decompose($colours);

    // function to produce colour string
    $make_colour = function($r, $g, $b, $o) {
      $str = "rgb({$r},{$g},{$b})";
      if(!is_numeric($o) || $o >= 1)
        return $str;
      if($o <= 0)
        return 'none';
      $str .= ':' . $o;
      return $str;
    };

    $position = min(100, max(0, $position));
    $seg0 = $segments[0];
    $seg1 = null;
    foreach($segments as $seg) {
      if(abs($seg[0] - $position) < 0.1) {
        // stop colour at or close to requested position
        $c = $seg[1]->rgb();
        $c_string = $make_colour($c[0], $c[1], $c[2], $seg[2]);
        $colour = new Colour($this->graph, $c_string);
        return $colour;
      }

      if($seg[0] > $position) {
        $seg1 = $seg;
        break;
      }
      $seg0 = $seg;
    }
    if($seg1 === null) {
      $seg1 = array_pop($segments);
      $seg1[0] = 100;
    }

    $c0 = $seg0[1]->rgb();
    $c1 = $seg1[1]->rgb();
    $vr = $this->calcComponent($c0[0], $c1[0], $seg0[0], $seg1[0], $position);
    $vg = $this->calcComponent($c0[1], $c1[1], $seg0[0], $seg1[0], $position);
    $vb = $this->calcComponent($c0[2], $c1[2], $seg0[0], $seg1[0], $position);

    $o0 = $seg0[2] === null ? 1 : $seg0[2];
    $o1 = $seg1[2] === null ? 1 : $seg1[2];
    $vo = $this->calcComponent($o0, $o1, $seg0[0], $seg1[0], $position);
    $c_string = $make_colour($vr, $vg, $vb, $vo);
    $colour = new Colour($this->graph, $c_string);
    return $colour;
  }

  /**
   * Breaks gradient array down into components
   */
  public function decompose($colours)
  {
    $col_mul = 100 / (count($colours) - 1);
    $offset = 0;
    $decomposed = [];
    foreach($colours as $pos => $colour) {
      $opacity = null;
      $poffset = $pos * $col_mul;
      if(strpos($colour, ':') !== false) {
        // opacity, stop offset or both
        $parts = explode(':', $colour);
        if(is_numeric($parts[0]) || count($parts) == 3) {
          $poffset = array_shift($parts);
        }
        // stick the other parts back together and let the Colour class
        // figure it out
        $colour = new Colour($this->graph, implode(':', $parts));
        $opacity = $colour->opacity();
        if($opacity == 1)
          $opacity = null;
      } else {
        $colour = new Colour($this->graph, $colour);
      }
      // set the offset to the most meaningful number
      $offset = min(100, max(0, $offset, $poffset));
      $decomposed[] = [$offset, $colour, $opacity];
    }
    return $decomposed;
  }

  /**
   * Defines the list of gradients
   */
  public function makeGradients(&$defs)
  {
    foreach($this->gradients as $key => $gradient)
      $defs->add($this->makeGradient($key));
  }
}

