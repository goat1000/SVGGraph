<?php
/**
 * Copyright (C) 2013-2019 Graham Breach
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

class PatternList {

  private $graph;
  private $pattern_map = [];
  private $patterns = [];

  public function __construct(&$graph)
  {
    $this->graph =& $graph;
  }

  /**
   * Adds a pattern to the list
   */
  public function add($pattern)
  {
    $hash = md5(serialize($pattern));
    if(isset($this->pattern_map[$hash]))
      return $this->pattern_map[$hash];

    if(method_exists($this, $pattern['pattern'])) {
      if(!isset($pattern['size']))
        $pattern['size'] = 10;
      $pattern['width'] = $pattern['height'] = $pattern['size'];
      $opacity = null;
      if(strpos($pattern[0], ':') !== false)
        list($colour, $opacity) = explode(':', $pattern[0]);
      else
        $colour = $pattern[0];
      $pattern['colour'] = $colour;
      if(is_numeric($opacity))
        $pattern['opacity'] = $opacity;
      $pattern = call_user_func([$this, $pattern['pattern']], $pattern);
    }

    // validate pattern content a bit
    $e = false;
    $s = strpos($pattern['pattern'], '<');
    if(false !== $s)
      $e = strpos($pattern['pattern'], '>', $s);
    if(false === $s || false === $e)
      throw new \Exception('Invalid pattern');

    // validate width and height
    if(!isset($pattern['width']) || !isset($pattern['height']))
      throw new \Exception('Pattern width and height not set');

    $id = $this->graph->newID();

    // check background colour
    $back_colour = '#fff';
    $back_opacity = '';
    if(array_key_exists('back_colour', $pattern)) {
      $back_colour = $pattern['back_colour'];
      if(!is_null($back_colour) && strpos($back_colour, ':') !== false)
        list($back_colour, $back_opacity) = explode(':', $back_colour);
    }

    if(!is_null($back_colour)) {
      $rect = [
        'width' => $pattern['width'],
        'height' => $pattern['height'],
        'fill' => $back_colour,
      ];
      if(is_numeric($back_opacity))
        $rect['opacity'] = $back_opacity;
      $pattern['pattern'] = $this->graph->element('rect', $rect) .
        $pattern['pattern'];
    }

    $pat = [
      'id' => $id, 'x' => 0, 'y' => 0,
      'width' => $pattern['width'], 'height' => $pattern['height'],
      'patternUnits' => 'userSpaceOnUse',
    ];
    if(isset($pattern['angle']))
      $pat['patternTransform'] = "rotate({$pattern['angle']})";

    $this->patterns[$id] = $this->graph->element('pattern', $pat, null,
      $pattern['pattern']);
    $this->pattern_map[$hash] = $id;
    return $id;
  }

  /**
   * Adds the stored patterns to the list of definitions
   */
  public function makePatterns(&$defs)
  {
    foreach($this->patterns as $pat)
      $defs[] = $pat;
  }

  /**
   * Spots - circles with diameter half of pattern size
   */
  private function spot($pattern, $scale = 0.25)
  {
    $spot = ['cx' => $pattern['size'] * $scale];
    $spot['cy'] = $spot['r'] = $spot['cx'];
    $spot['fill'] = $pattern['colour'];
    if(isset($pattern['opacity']))
      $spot['opacity'] = $pattern['opacity'];
    $pattern['pattern'] = $this->graph->element('circle', $spot);
    return $pattern;
  }

  /**
   * Polka dots - spots in a checked pattern
   */
  private function polkaDot($pattern, $scale = 0.25)
  {
    $spot = ['cx' => $pattern['size'] * $scale];
    $spot['cy'] = $spot['r'] = $spot['cx'];
    $spot['fill'] = $pattern['colour'];
    if(isset($pattern['opacity']))
      $spot['opacity'] = $pattern['opacity'];
    $pattern['pattern'] = $this->graph->element('circle', $spot);

    $spot['cx'] = $spot['cy'] = ($pattern['size'] / 2) + $spot['r'];
    $pattern['pattern'] .= $this->graph->element('circle', $spot);
    return $pattern;
  }

  /**
   * Check pattern
   */
  private function check($pattern)
  {
    $rect = ['width' => $pattern['size'] / 2];
    $rect['height'] = $rect['width'];
    $rect['fill'] = $pattern['colour'];
    if(isset($pattern['opacity']))
      $rect['opacity'] = $pattern['opacity'];
    $pattern['pattern'] = $this->graph->element('rect', $rect);
    $rect['x'] = $rect['y'] = $rect['width'];
    $pattern['pattern'] .= $this->graph->element('rect', $rect);
    return $pattern;
  }

  /**
   * Squares
   */
  private function square($pattern, $scale = 0.8)
  {
    $rect = ['width' => $pattern['size'] * $scale];
    $rect['height'] = $rect['width'];
    $rect['fill'] = $pattern['colour'];
    if(isset($pattern['opacity']))
      $rect['opacity'] = $pattern['opacity'];
    $pattern['pattern'] = $this->graph->element('rect', $rect);
    return $pattern;
  }

  /**
   * Hatching using a single line
   */
  private function line($pattern, $thickness = 0.8)
  {
    $w = $pattern['size'];
    $y = $w / 2;
    $line = [
      'd' => "M0 {$y}h{$w}",
      'stroke' => $pattern['colour'],
      'stroke-width' => $pattern['size'] * $thickness
    ];
    if(isset($pattern['opacity']))
      $line['opacity'] = $pattern['opacity'];
    $pattern['pattern'] = $this->graph->element('path', $line);
    return $pattern;
  }

  /**
   * Hatching using crossed lines
   */
  private function cross($pattern, $thickness = 0.4)
  {
    $w = $pattern['size'];
    $y = $w / 2;
    $line = [
      'd' => "M0 {$y}h{$w}M{$y} 0v{$w}",
      'stroke' => $pattern['colour'],
      'stroke-width' => $pattern['size'] * $thickness
    ];
    if(isset($pattern['opacity']))
      $line['opacity'] = $pattern['opacity'];
    $pattern['pattern'] = $this->graph->element('path', $line);
    return $pattern;
  }

  /**
   * Spot2, Spot3 use smaller spots
   */
  private function spot2($pattern)
  {
    return $this->spot($pattern, 0.16666);
  }
  private function spot3($pattern)
  {
    return $this->spot($pattern, 0.125);
  }

  /**
   * Circles are bigger spots
   */
  private function circle($pattern)
  {
    return $this->spot($pattern, 0.45);
  }
  private function circle2($pattern)
  {
    return $this->spot($pattern, 0.4);
  }
  private function circle3($pattern)
  {
    return $this->spot($pattern, 0.33333);
  }

  /**
   * PolkaDot2 etc use smaller dots
   */
  private function polkaDot2($pattern)
  {
    return $this->polkaDot($pattern, 0.16666);
  }
  private function polkaDot3($pattern)
  {
    return $this->polkaDot($pattern, 0.125);
  }

  /**
   * Differently proportioned squares
   */
  private function square2($pattern)
  {
    return $this->square($pattern, 0.6);
  }
  private function square3($pattern)
  {
    return $this->square($pattern, 0.4);
  }
  private function square4($pattern)
  {
    return $this->square($pattern, 0.2);
  }

  /**
   * Thinner lines
   */
  private function line2($pattern)
  {
    return $this->line($pattern, 0.6);
  }
  private function line3($pattern)
  {
    return $this->line($pattern, 0.4);
  }
  private function line4($pattern)
  {
    return $this->line($pattern, 0.2);
  }
  private function cross2($pattern)
  {
    return $this->cross($pattern, 0.25);
  }
  private function cross3($pattern)
  {
    return $this->cross($pattern, 0.1);
  }
}

