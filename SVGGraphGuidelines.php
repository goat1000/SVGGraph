<?php
/**
 * Copyright (C) 2017 Graham Breach
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

define("SVGG_GUIDELINE_ABOVE", 1);
define("SVGG_GUIDELINE_BELOW", 0);

class Guidelines {

  protected $settings;
  protected $graph;
  protected $flip_axes;
  protected $assoc_keys;
  protected $datetime_keys;
  protected $guidelines;
  protected $coords;
  protected $min_guide = array('x' => null, 'y' => null);
  protected $max_guide = array('x' => null, 'y' => null);

  public function __construct(&$settings, &$graph, $flip_axes, $assoc, $datetime)
  {
    // see if there is anything to do
    if(empty($graph->guideline) && $graph->guideline !== 0)
      return;

    $this->graph = $graph;
    $this->settings = $settings;
    $this->flip_axes = $flip_axes;
    $this->assoc_keys = $assoc;
    $this->datetime_keys = $datetime;

    $this->guidelines = array();
    $lines = $graph->guideline;

    if(is_array($lines) && is_array($lines[0]) ||
      (count($lines) > 1 && !is_string($lines[1]))) {

      // array of guidelines
      foreach($lines as $line)
        $this->Calculate($line);
    } else {

      // single guideline
      $this->Calculate($lines);
    }

    if(!empty($this->guidelines)) {
      require_once 'SVGGraphCoords.php';
      $this->coords = new SVGGraphCoords($graph);
    }
  }

  /**
   * Return the settings as properties
   */
  public function __get($name)
  {
    $this->{$name} = isset($this->settings[$name]) ?
      $this->settings[$name] : null;
    return $this->{$name};
  }

  /**
   * Converts guideline options to more useful member variables
   */
  protected function Calculate($g)
  {
    if(!is_array($g))
      $g = array($g);

    $value = $g[0];
    $axis = (isset($g[2]) && ($g[2] == 'x' || $g[2] == 'y')) ? $g[2] : 'y';
    if($axis == 'x') {
      if($this->datetime_keys) {
        // $value is a datetime string, so convert it
        $value = SVGGraphDateConvert($value);

        // if the value could not be converted it can't be drawn either
        if(is_null($value))
          return;
      } else if($this->assoc_keys) {
        // $value is a key - must be converted later when the axis
        // has been created
      }
    }
    $above = isset($g['above']) ? $g['above'] : $this->guideline_above;
    $position = $above ? SVGG_GUIDELINE_ABOVE : SVGG_GUIDELINE_BELOW;
    $guideline = array(
      'value' => $value,
      'depth' => $position,
      'title' => isset($g[1]) ? $g[1] : '',
      'axis' => $axis
    );
    $lopts = $topts = array();
    $line_opts = array(
      'colour' => 'stroke',
      'dash' => 'stroke-dasharray',
      'stroke_width' => 'stroke-width',
      'opacity' => 'opacity',

      // not SVG attributes
      'length' => 'length',
      'length_units' => 'length_units',
    );
    $text_opts = array(
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
    );
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
  public function GetMinMax()
  {
    $min_max = array(
      $this->min_guide['x'], $this->min_guide['y'],
      $this->max_guide['x'], $this->max_guide['y']
    );
    return $min_max;
  }

  /**
   * Returns the guidelines above content
   */
  public function GetAbove()
  {
    return $this->Get(SVGG_GUIDELINE_ABOVE);
  }

  /**
   * Returns the guidelines below content
   */
  public function GetBelow()
  {
    return $this->Get(SVGG_GUIDELINE_BELOW);
  }

  /**
   * Returns the elements to draw the guidelines
   */
  protected function Get($depth)
  {
    if(empty($this->guidelines))
      return '';

    // build all the lines at this depth (above/below) that use
    // global options as one path
    $d = $lines = $text = '';
    $path = array(
      'stroke' => $this->guideline_colour,
      'stroke-width' => $this->guideline_stroke_width,
      'stroke-dasharray' => $this->guideline_dash,
      'fill' => 'none'
    );
    if($this->guideline_opacity != 1)
      $path['opacity'] = $this->guideline_opacity;
    $textopts = array(
      'font-family' => $this->guideline_font,
      'font-size' => $this->guideline_font_size,
      'font-weight' => $this->guideline_font_weight,
      'fill' => $this->graph->GetFirst($this->guideline_text_colour, 
        $this->guideline_colour),
    );
    $text_opacity = $this->graph->GetFirst($this->guideline_text_opacity, 
      $this->guideline_opacity);

    foreach($this->guidelines as $line) {
      if($line['depth'] == $depth) {
        // opacity cannot go in the group because child opacity is multiplied
        // by group opacity
        if($text_opacity != 1 && !isset($line['text']['opacity']))
          $line['text']['opacity'] = $text_opacity;
        $this->BuildGuideline($line, $lines, $text, $path, $d);
      }
    }
    if(!empty($d)) {
      $path['d'] = $d;
      $lines .= $this->graph->Element('path', $path);
    }

    if(!empty($text))
      $text = $this->graph->Element('g', $textopts, null, $text);
    return $lines . $text;
  }

  /**
   * Adds a single guideline and its title to content
   */
  protected function BuildGuideline(&$line, &$lines, &$text, &$path, &$d)
  {
    $length = $this->guideline_length;
    $length_units = $this->guideline_length_units;
    if(isset($line['line'])) {
      $this->UpdateAndUnset($length, $line['line'], 'length');
      $this->UpdateAndUnset($length_units, $line['line'], 'length_units');
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

    $path_data = $this->GuidelinePath($line['axis'], $line['value'],
      $line['depth'], $x, $y, $w, $h, $reverse_length);
    if($path_data == '')
      return;

    if(!isset($line['line'])) {
      // no special options, add to main path
      $d .= $path_data;
    } else {
      $line_path = array_merge($path, $line['line'], array('d' => $path_data));
      $lines .= $this->graph->Element('path', $line_path);
    }
    if(!empty($line['title'])) {
      $text_pos = $this->guideline_text_position;
      $text_pad = $this->guideline_text_padding;
      $text_angle = $this->guideline_text_angle;
      $text_align = $this->guideline_text_align;
      $font_size = $this->guideline_font_size;
      $font_adjust = $this->guideline_font_adjust;
      if(isset($line['text'])) {
        $this->UpdateAndUnset($text_pos, $line['text'], 'text_position');
        $this->UpdateAndUnset($text_pad, $line['text'], 'text_padding');
        $this->UpdateAndUnset($text_angle, $line['text'], 'text_angle');
        $this->UpdateAndUnset($text_align, $line['text'], 'text_align');
        $this->UpdateAndUnset($font_adjust, $line['text'], 'font_adjust');
        if(isset($line['text']['font-size']))
          $font_size = $line['text']['font-size'];
      }
      list($text_w, $text_h) = $this->graph->TextSize($line['title'], 
        $font_size, $font_adjust, $this->encoding, $text_angle, $font_size);

      list($x, $y, $text_pos_align) = Graph::RelativePosition(
        $text_pos, $y, $x, $y + $h, $x + $w,
        $text_w, $text_h, $text_pad, true);

      $t = array('x' => $x, 'y' => $y + $font_size);
      if(empty($text_align) && $text_pos_align != 'start') {
        $t['text-anchor'] = $text_pos_align;
      } else {
        $align_map = array('right' => 'end', 'centre' => 'middle');
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
      $text .= $this->graph->Text($line['title'], $font_size, $t);
    }
  }

  /**
   * Creates the path data for a guideline and sets the dimensions
   */
  protected function GuidelinePath($axis, $value, $depth, &$x, &$y, &$w, &$h,
    $reverse_length)
  {
    // use the Coords class to find measurements
    if($axis == 'x') {
      $y = $this->coords->Transform("gt", 'y');
      $x = $this->coords->Transform("g{$value}", 'x', NULL);
      if(is_null($x))
        return '';

      if(is_string($h) || $h > 0) {
        $h = $this->coords->Transform("{$h}", 'y');
      } else {
        $h = $this->coords->Transform("gh", 'y');
      }
      if(!$reverse_length)
        $y = $this->coords->Transform("gb", 'y') - $h;
      return "M$x {$y}v$h";
    } else {
      $x = $this->coords->Transform("g0", 'x');
      $y = $this->coords->Transform("g{$value}", 'y', NULL);
      if(is_null($y))
        return '';

      if(is_string($w) || $w > 0) {
        $w = $this->coords->Transform("{$w}", 'x');
      } else {
        $w = $this->coords->Transform('gw', 'x');
      }
      if($reverse_length)
        $x = $this->coords->Transform("gr", 'x') - $w;
      $h = 0;
      return "M$x {$y}h$w";
    }
  }

  /**
   * Updates $var with $array[$key] and removes it from array
   */
  protected function UpdateAndUnset(&$var, &$array, $key)
  {
    if(isset($array[$key])) {
      $var = $array[$key];
      unset($array[$key]);
    }
  }
}

