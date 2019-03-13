<?php
/**
 * Copyright (C) 2017-2019 Graham Breach
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

class Guidelines {

  const ABOVE = 1;
  const BELOW = 0;

  protected $graph;
  protected $flip_axes;
  protected $assoc_keys;
  protected $datetime_keys;
  protected $guidelines;
  protected $coords;
  protected $min_guide = ['x' => null, 'y' => null];
  protected $max_guide = ['x' => null, 'y' => null];

  private $above;
  private $colour;
  private $dash;
  private $font;
  private $font_adjust;
  private $font_size;
  private $font_weight;
  private $length;
  private $length_units;
  private $opacity;
  private $stroke_width;
  private $text_align;
  private $text_angle;
  private $text_colour;
  private $text_opacity;
  private $text_padding;
  private $text_position;

  public function __construct(&$graph, $flip_axes, $assoc, $datetime)
  {
    // see if there is anything to do
    $lines = $graph->getOption('guideline');
    if(empty($lines) && $lines !== 0)
      return;

    $this->graph =& $graph;
    $this->flip_axes = $flip_axes;
    $this->assoc_keys = $assoc;
    $this->datetime_keys = $datetime;
    $this->guidelines = [];

    // set up options
    $opts = ['above', 'colour', 'dash', 'font', 'font_adjust', 'font_size',
      'font_weight', 'length', 'length_units', 'opacity', 'stroke_width',
      'text_align', 'text_angle', 'text_padding', 'text_position'];
    foreach($opts as $opt)
      $this->{$opt} = $graph->getOption("guideline_{$opt}");

    // options with fallbacks
    $this->text_colour = $graph->getOption('guideline_text_colour',
      'guideline_colour');
    $this->text_opacity = $graph->getOption('guideline_text_opacity',
      'guideline_opacity');

    if(is_array($lines) &&
      (is_array($lines[0]) || (count($lines) > 1 && !is_string($lines[1])))) {

      // array of guidelines
      foreach($lines as $line)
        $this->calculate($line);
    } else {

      // single guideline
      $this->calculate($lines);
    }

    if(!empty($this->guidelines)) {
      $this->coords = new Coords($graph);
    }
  }

  /**
   * Converts guideline options to more useful member variables
   */
  protected function calculate($g)
  {
    if(!is_array($g))
      $g = [$g];

    $value = $g[0];
    $axis = (isset($g[2]) && ($g[2] == 'x' || $g[2] == 'y')) ? $g[2] : 'y';
    if($axis == 'x') {
      if($this->datetime_keys) {
        // $value is a datetime string, so convert it
        $value = Graph::dateConvert($value);

        // if the value could not be converted it can't be drawn either
        if(is_null($value))
          return;
      } else if($this->assoc_keys) {
        // $value is a key - must be converted later when the axis
        // has been created
      }
    }
    $above = isset($g['above']) ? $g['above'] : $this->above;
    $position = $above ? Guidelines::ABOVE : Guidelines::BELOW;
    $guideline = [
      'value' => $value,
      'depth' => $position,
      'title' => isset($g[1]) ? $g[1] : '',
      'axis' => $axis
    ];
    $lopts = $topts = [];
    $line_opts = [
      'colour' => 'stroke',
      'dash' => 'stroke-dasharray',
      'stroke_width' => 'stroke-width',
      'opacity' => 'opacity',

      // not SVG attributes
      'length' => 'length',
      'length_units' => 'length_units',
    ];
    $text_opts = [
      'colour' => 'fill',
      'opacity' => 'opacity',
      'font' => 'font-family',
      'font_size' => 'font-size',
      'font_weight' => 'font-weight',
      'text_colour' => 'fill', // overrides 'colour' option from line
      'text_opacity' => 'opacity', // overrides line opacity

      // these options do not map to SVG attributes
      'font_adjust' => 'font_adjust',
      'text_position' => 'text_position',
      'text_padding' => 'text_padding',
      'text_angle' => 'text_angle',
      'text_align' => 'text_align',
    ];
    foreach($line_opts as $okey => $opt)
      if(isset($g[$okey]))
        $lopts[$opt] = $g[$okey];
    foreach($text_opts as $okey => $opt)
      if(isset($g[$okey]))
        $topts[$opt] = $g[$okey];

    if(count($lopts))
      $guideline['line'] = $lopts;
    if(count($topts))
      $guideline['text'] = $topts;

    // update maxima and minima
    if(is_null($this->max_guide[$axis]) || $value > $this->max_guide[$axis])
      $this->max_guide[$axis] = $value;
    if(is_null($this->min_guide[$axis]) || $value < $this->min_guide[$axis])
      $this->min_guide[$axis] = $value;

    // can flip the axes now the min/max are stored
    if($this->flip_axes)
      $guideline['axis'] = ($guideline['axis'] == 'x' ? 'y' : 'x');

    $this->guidelines[] = $guideline;
  }

  /**
   * Returns the minimum and maximum axis guidelines
   * array($min_x, $min_y, $max_x, $max_y)
   */
  public function getMinMax()
  {
    $min_max = [
      $this->min_guide['x'], $this->min_guide['y'],
      $this->max_guide['x'], $this->max_guide['y']
    ];
    return $min_max;
  }

  /**
   * Returns the guidelines above content
   */
  public function getAbove()
  {
    return $this->get(Guidelines::ABOVE);
  }

  /**
   * Returns the guidelines below content
   */
  public function getBelow()
  {
    return $this->get(Guidelines::BELOW);
  }

  /**
   * Returns the elements to draw the guidelines
   */
  protected function get($depth)
  {
    if(empty($this->guidelines))
      return '';

    // build all the lines at this depth (above/below) that use
    // global options as one path
    $d = $lines = $text = '';
    $path = [
      'stroke' => $this->colour,
      'stroke-width' => $this->stroke_width,
      'stroke-dasharray' => $this->dash,
      'fill' => 'none'
    ];
    if($this->opacity != 1)
      $path['opacity'] = $this->opacity;
    $textopts = [
      'font-family' => $this->font,
      'font-size' => $this->font_size,
      'font-weight' => $this->font_weight,
      'fill' => $this->text_colour,
    ];

    foreach($this->guidelines as $line) {
      if($line['depth'] == $depth) {
        // opacity cannot go in the group because child opacity is multiplied
        // by group opacity
        if($this->text_opacity != 1 && !isset($line['text']['opacity']))
          $line['text']['opacity'] = $this->text_opacity;
        $this->buildGuideline($line, $lines, $text, $path, $d);
      }
    }
    if(!empty($d)) {
      $path['d'] = $d;
      $lines .= $this->graph->element('path', $path);
    }

    if(!empty($text))
      $text = $this->graph->element('g', $textopts, null, $text);
    return $lines . $text;
  }

  /**
   * Adds a single guideline and its title to content
   */
  protected function buildGuideline(&$line, &$lines, &$text, &$path, &$d)
  {
    $length = $this->length;
    $length_units = $this->length_units;
    if(isset($line['line'])) {
      $this->updateAndUnset($length, $line['line'], 'length');
      $this->updateAndUnset($length_units, $line['line'], 'length_units');
    }

    $reverse_length = false;
    $w = $h = 0;
    if($length != 0) {
      if($length < 0) {
        $reverse_length = true;
        $length = -$length;
      }

      if($line['axis'] == 'x')
        $h = $length;
      else
        $w = $length;

    } elseif($length_units != 0) {
      if($length_units < 0) {
        $reverse_length = true;
        $length_units = -$length_units;
      }

      if($line['axis'] == 'x')
        $h = "u{$length_units}";
      else
        $w = "u{$length_units}";
    }

    // if the graph class has a custom path method, use it
    // - its signature is the same as GuidelinePath but without $depth
    $custom_method = ($line['depth'] == Guidelines::ABOVE ?
      'GuidelinePathAbove' : 'GuidelinePathBelow');

    if(method_exists($this->graph, $custom_method)) {
      $path_data = $this->graph->{$custom_method}($line['axis'], $line['value'],
        $x, $y, $w, $h, $reverse_length);
    } else {
      $path_data = $this->guidelinePath($line['axis'], $line['value'],
        $line['depth'], $x, $y, $w, $h, $reverse_length);
    }
    if($path_data == '')
      return;

    if(!isset($line['line'])) {
      // no special options, add to main path
      $d .= $path_data;
    } else {
      $line_path = array_merge($path, $line['line'], ['d' => $path_data]);
      $lines .= $this->graph->element('path', $line_path);
    }
    if(!empty($line['title'])) {
      $text_pos = $this->text_position;
      $text_pad = $this->text_padding;
      $text_angle = $this->text_angle;
      $text_align = $this->text_align;
      $font = $this->font;
      $font_size = $this->font_size;
      $font_adjust = $this->font_adjust;
      if(isset($line['text'])) {
        $this->updateAndUnset($text_pos, $line['text'], 'text_position');
        $this->updateAndUnset($text_pad, $line['text'], 'text_padding');
        $this->updateAndUnset($text_angle, $line['text'], 'text_angle');
        $this->updateAndUnset($text_align, $line['text'], 'text_align');
        $this->updateAndUnset($font_adjust, $line['text'], 'font_adjust');
        if(isset($line['text']['font-family']))
          $font = $line['text']['font-family'];
        if(isset($line['text']['font-size']))
          $font_size = $line['text']['font-size'];
      }

      $svg_text = new Text($this->graph, $font, $font_adjust);
      list($text_w, $text_h) = $svg_text->measure($line['title'], $font_size,
        $text_angle, $font_size);

      list($x, $y, $text_pos_align) = Graph::relativePosition(
        $text_pos, $y, $x, $y + $h, $x + $w,
        $text_w, $text_h, $text_pad, true);

      $t = ['x' => $x, 'y' => $y + $svg_text->baseline($font_size)];
      if(empty($text_align) && $text_pos_align != 'start') {
        $t['text-anchor'] = $text_pos_align;
      } else {
        $align_map = ['right' => 'end', 'centre' => 'middle'];
        if(isset($align_map[$text_align]))
          $t['text-anchor'] = $align_map[$text_align];
      }

      if($text_angle != 0) {
        $rx = $x + $text_h/2;
        $ry = $y + $text_h/2;
        $t['transform'] = "rotate($text_angle,$rx,$ry)";
      }

      if(isset($line['text']))
        $t = array_merge($t, $line['text']);
      $text .= $svg_text->text($line['title'], $font_size, $t);
    }
  }

  /**
   * Creates the path data for a guideline and sets the dimensions
   */
  protected function guidelinePath($axis, $value, $depth, &$x, &$y, &$w, &$h,
    $reverse_length)
  {
    // use the Coords class to find measurements
    if($axis == 'x') {
      $y = $this->coords->transform("gt", 'y');
      $x = $this->coords->transform("g{$value}", 'x', null);
      if(is_null($x))
        return '';

      if(is_string($h) || $h > 0) {
        $h = $this->coords->transform("{$h}", 'y');
      } else {
        $h = $this->coords->transform("gh", 'y');
      }
      if(!$reverse_length)
        $y = $this->coords->transform("gb", 'y') - $h;
      return "M$x {$y}v$h";
    } else {
      $x = $this->coords->transform("gl", 'x');
      $y = $this->coords->transform("g{$value}", 'y', null);
      if(is_null($y))
        return '';

      if(is_string($w) || $w > 0) {
        $w = $this->coords->transform("{$w}", 'x');
      } else {
        $w = $this->coords->transform('gw', 'x');
      }
      if($reverse_length)
        $x = $this->coords->transform("gr", 'x') - $w;
      $h = 0;
      return "M$x {$y}h$w";
    }
  }

  /**
   * Updates $var with $array[$key] and removes it from array
   */
  protected function updateAndUnset(&$var, &$array, $key)
  {
    if(isset($array[$key])) {
      $var = $array[$key];
      unset($array[$key]);
    }
  }
}

