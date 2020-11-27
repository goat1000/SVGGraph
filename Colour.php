<?php
/**
 * Copyright (C) 2019-2020 Graham Breach
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
 * Class for parsing colour/gradient/pattern
 */
class Colour {
  private $graph;
  private $colour = '#000';
  private $opacity = 1;
  private $as_string = '';
  private $gradient = false;
  private $pattern = false;
  private $radial = false;
  private $key = null;

  public function __construct(&$graph, $colour, $allow_gradient = true,
    $allow_pattern = true, $radial_gradient = false)
  {
    if($colour === null || $colour === 'none') {
      $this->colour = $this->as_string = 'none';
      return;
    }

    if(is_object($colour) && get_class($colour) === 'Goat1000\\SVGGraph\\Colour')
      $colour = $colour->colour;

    if(is_string($colour)) {
      $this->extract($colour);
      return;
    }

    // if not a string, must be an array with a colour at index 0
    $valid = false;
    if(is_array($colour)) {
      if(is_string($colour[0])) {
        $valid = true;
      } elseif(isset($colour['pattern'])) {
        // allow gradients as first colour of pattern
        if(is_array($colour[0]) && count($colour[0]) > 1) {
          $valid = true;
          foreach($colour[0] as $i) {
            if(!is_string($i))
              $valid = false;
          }
        }
      }
    }

    if(!$valid)
      throw new \InvalidArgumentException('Malformed colour value: ' .
        serialize($colour));

    if(count($colour) < 2 || (!$allow_gradient && !$allow_pattern)) {
      $this->extract($colour[0]);
      return;
    }

    $this->graph =& $graph;
    $this->colour = $colour;
    if(isset($colour['pattern'])) {
      if(!$allow_pattern)
        $this->extract($colour[0]);
      $this->pattern = true;
      return;
    }

    if(!$allow_gradient) {
      $this->extract($colour[0]);
      return;
    }

    $err = array_diff_key($colour, array_keys(array_keys($colour)));
    if($err)
      throw new \InvalidArgumentException('Malformed gradient/pattern: ' .
        serialize($colour));
    $this->gradient = true;

    $last = count($colour) - 1;
    $this->radial = $radial_gradient || $colour[$last] == 'r';
  }

  /**
   * Extract the colour and opacity into this instance
   */
  private function extract($colour)
  {
    $filters = '';
    $fpos = strpos($colour, '/');
    if($fpos !== false) {
      $filters = substr($colour, $fpos + 1);
      $colour = substr($colour, 0, $fpos);
    }
    list($colour, $opacity) = $this->extractOpacity($colour);
    $this->colour = $this->as_string = $colour;
    $this->opacity = $opacity;

    // filters don't work on 'none' and 'transparent', so don't try
    if($filters !== '' && $colour !== 'none' && $colour !== 'transparent') {
      $cf = new ColourFilter($colour, $filters);
      $this->colour = $this->as_string = (string)$cf;
    }
  }

  /**
   * Returns an array containing the colour and opacity from a string
   */
  public static function extractOpacity($colour)
  {
    $opacity = 1.0;
    if(strpos($colour, ':') !== false) {
      $parts = explode(':', $colour);
      if(is_numeric($parts[0]) || count($parts) == 3)
        $gstop = array_shift($parts);

      $c = array_shift($parts);
      $ovalue = array_shift($parts);
      if($ovalue !== null) {
        if(!is_numeric($ovalue))
          throw new \Exception('Non-numeric opacity in colour: ' . $colour);
        $opacity = min(1.0, max(0.0, 1.0 * $ovalue));
      }
      $colour = $c;
    }
    return [$colour, $opacity];
  }

  /**
   * Output for SVG values
   */
  public function __toString()
  {
    if($this->as_string !== '')
      return $this->as_string;

    if($this->gradient) {
      $gradient_id = $this->graph->defs->addGradient($this->colour, $this->key,
        $this->radial);
      $this->as_string = 'url(#' . $gradient_id . ')';
    } elseif($this->pattern) {
      $pattern_id = $this->graph->defs->addPattern($this->colour);
      $this->as_string = 'url(#' . $pattern_id . ')';
    }

    return $this->as_string;
  }

  /**
   * Sets the key for use with gradients
   */
  public function setGradientKey($key)
  {
    $this->key = $key;
  }

  /**
   * Returns the opacity value
   */
  public function opacity($as_number = false)
  {
    if($as_number)
      return new Number($this->opacity);
    return $this->opacity;
  }

  /**
   * Returns the solid colour
   */
  public function solid()
  {
    if(!$this->gradient && !$this->pattern)
      return $this->colour;

    list($solid) = $this->extractOpacity($this->colour[0]);
    return $solid;
  }

  /**
   * Returns the R,G,B for the colour
   */
  public function rgb()
  {
    $rgb = new RGBColour($this->solid());
    return $rgb->getRGB();
  }

  /**
   * Returns true if the colour is a gradient
   */
  public function isGradient()
  {
    return $this->gradient;
  }

  /**
   * Returns true if the colour is a pattern
   */
  public function isPattern()
  {
    return $this->pattern;
  }

  /**
   * Returns true if the colour is 'none'
   */
  public function isNone()
  {
    return $this->colour === 'none';
  }

  /**
   * Returns true if a radial gradient
   */
  public function isRadial()
  {
    return $this->gradient && $this->radial;
  }
}

