<?php
/**
 * Copyright (C) 2014-2015 Graham Breach
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


class SVGGraphColours implements Countable {

  private $colours = array();
  private $dataset_count = 0;
  private $fallback = FALSE;

  public function __construct($colours = -1)
  {
    $default_colours = array('#11c','#c11','#cc1','#1c1','#c81',
      '#116','#611','#661','#161','#631');

    // default colours
    if(is_null($colours))
      $colours = $default_colours;

    if(is_array($colours)) {
      // fallback to old behaviour
      $this->fallback = $colours;
      return;
    }

    $this->colours[0] = new SVGGraphColourArray($default_colours);
    $this->dataset_count = 1;
    return;
  }

  /**
   * Setup based on graph requirements
   */
  public function Setup($count, $datasets = NULL)
  {
    if($this->fallback !== FALSE) {
      if(!is_null($datasets)) {
        foreach($this->fallback as $colour) {
          // in fallback, each dataset gets one colour
          $this->colours[] = new SVGGraphColourArray(array($colour));
        }
      } else {
        $this->colours[] = new SVGGraphColourArray($this->fallback);
      }
      $this->dataset_count = count($this->colours);
    }

    foreach($this->colours as $clist)
      $clist->Setup($count);
  }


  /**
   * Returns the colour for an index and dataset
   */
  public function GetColour($index, $dataset = NULL)
  {
    // default is for a colour per dataset
    if(is_null($dataset))
      $dataset = 0;

    // see if specific dataset exists
    if(array_key_exists($dataset, $this->colours))
      return $this->colours[$dataset][$index];

    // try mod
    $dataset = $dataset % $this->dataset_count;
    if(array_key_exists($dataset, $this->colours))
      return $this->colours[$dataset][$index];

    // just use first dataset
    reset($this->colours);
    $clist = current($this->colours);
    return $clist[$index];
  }

  /**
   * Implement Countable to make it non-countable
   */
  public function count()
  {
    throw new Exception("Cannot count SVGGraphColours class");
  }

  /**
   * Assign a colour array for a dataset
   */
  public function Set($dataset, $colours)
  {
    if(is_null($colours)) {
      if(array_key_exists($dataset, $this->colours))
        unset($this->colours[$dataset]);
      return;
    }
    $this->colours[$dataset] = new SVGGraphColourArray($colours);
    $this->dataset_count = count($this->colours);
  }

  /**
   * Set up RGB colour range
   */
  public function RangeRGB($dataset, $r1, $g1, $b1, $r2, $g2, $b2)
  {
    $rng = new SVGGraphColourRangeRGB($r1, $g1, $b1, $r2, $g2, $b2);
    $this->colours[$dataset] = $rng;
    $this->dataset_count = count($this->colours);
  }

  /**
   * HSL colour range, with option to go the long way
   */
  public function RangeHSL($dataset, $h1, $s1, $l1, $h2, $s2, $l2,
    $reverse = false)
  {
    $rng = new SVGGraphColourRangeHSL($h1, $s1, $l1, $h2, $s2, $l2);
    if($reverse)
      $rng->Reverse();
    $this->colours[$dataset] = $rng;
    $this->dataset_count = count($this->colours);
  }

  /**
   * HSL colour range from RGB values, with option to go the long way
   */
  public function RangeRGBtoHSL($dataset, $r1, $g1, $b1, $r2, $g2, $b2,
    $reverse = false)
  {
    $rng = SVGGraphColourRangeHSL::FromRGB($r1, $g1, $b1, $r2, $g2, $b2);
    if($reverse)
      $rng->Reverse();
    $this->colours[$dataset] = $rng;
    $this->dataset_count = count($this->colours);
  }

  /**
   * RGB colour range from two RGB hex codes
   */
  public function RangeHexRGB($dataset, $c1, $c2)
  {
    list($r1, $g1, $b1) = $this->HexRGB($c1);
    list($r2, $g2, $b2) = $this->HexRGB($c2);
    $this->RangeRGB($dataset, $r1, $g1, $b1, $r2, $g2, $b2);
  }

  /**
   * HSL colour range from RGB hex codes
   */
  public function RangeHexHSL($dataset, $c1, $c2, $reverse = false)
  {
    list($r1, $g1, $b1) = $this->HexRGB($c1);
    list($r2, $g2, $b2) = $this->HexRGB($c2);
    $this->RangeRGBtoHSL($dataset, $r1, $g1, $b1, $r2, $g2, $b2, $reverse);
  }


  /**
   * Convert a colour code to RGB array
   */
  public static function HexRGB($c)
  {
    $r = $g = $b = 0;
    if(strlen($c) == 7) {
      sscanf($c, '#%2x%2x%2x', $r, $g, $b);
    } elseif(strlen($c) == 4) {
      sscanf($c, '#%1x%1x%1x', $r, $g, $b);
      $r += 16 * $r;
      $g += 16 * $g;
      $b += 16 * $b;
    }
    return array($r, $g, $b);
  }
}


class SVGGraphColourArray implements ArrayAccess {

  private $colours;
  private $count;

  public function __construct($colours)
  {
    $this->colours = $colours;
    $this->count = count($colours);
  }

  /**
   * Not used by this class
   */
  public function Setup($count)
  {
    // count comes from array, not number of bars etc.
  }

  /**
   * always true, because it wraps around
   */
  public function offsetExists($offset)
  {
    return true;
  }

  /**
   * return the colour
   */
  public function offsetGet($offset)
  {
    return $this->colours[$offset % $this->count];
  }

  public function offsetSet($offset, $value)
  {
    $this->colours[$offset % $this->count] = $value;
  }

  public function offsetUnset($offset)
  {
    throw new Exception('Unexpected offsetUnset');
  }

}


/**
 * Abstract class implements common methods
 */
abstract class SVGGraphColourRange implements ArrayAccess {

  protected $count = 2;

  /**
   * Sets up the length of the range
   */
  public function Setup($count)
  {
    $this->count = $count;
  }

  /**
   * always true, because it wraps around
   */
  public function offsetExists($offset)
  {
    return true;
  }

  public function offsetSet($offset, $value)
  {
    throw new Exception('Unexpected offsetSet');
  }

  public function offsetUnset($offset)
  {
    throw new Exception('Unexpected offsetUnset');
  }

  /**
   * Clamps a value to range $min-$max
   */
  protected static function Clamp($val, $min, $max)
  {
    return min($max, max($min, $val));
  }
}

/**
 * Colour range for RGB values
 */
class SVGGraphColourRangeRGB extends SVGGraphColourRange {

  private $r1, $g1, $b1;
  private $rdiff, $gdiff, $bdiff;

  /**
   * RGB range
   */
  public function __construct($r1, $g1, $b1, $r2, $g2, $b2)
  {
    $this->r1 = $this->Clamp($r1, 0, 255);
    $this->g1 = $this->Clamp($g1, 0, 255);
    $this->b1 = $this->Clamp($b1, 0, 255);
    $this->rdiff = $this->Clamp($r2, 0, 255) - $this->r1;
    $this->gdiff = $this->Clamp($g2, 0, 255) - $this->g1;
    $this->bdiff = $this->Clamp($b2, 0, 255) - $this->b1;
  }

  /**
   * Return the colour from the range
   */
  public function offsetGet($offset)
  {
    $c = max($this->count - 1, 1);
    $offset = $this->Clamp($offset, 0, $c);
    $r = $this->r1 + $offset * $this->rdiff / $c;
    $g = $this->g1 + $offset * $this->gdiff / $c;
    $b = $this->b1 + $offset * $this->bdiff / $c;
    return sprintf('#%02x%02x%02x', $r, $g, $b);
  }

}

/**
 * Colour range for HSL values
 */
class SVGGraphColourRangeHSL extends SVGGraphColourRange {

  private $h1, $s1, $l1;
  private $hdiff, $sdiff, $ldiff;

  /**
   * HSL range
   */
  public function __construct($h1, $s1, $l1, $h2, $s2, $l2)
  {
    $this->h1 = $this->Clamp($h1, 0, 360);
    $this->s1 = $this->Clamp($s1, 0, 1);
    $this->l1 = $this->Clamp($l1, 0, 1);

    $hdiff = $this->Clamp($h2, 0, 360) - $this->h1;
    if(abs($hdiff) > 180)
      $hdiff += $hdiff < 0 ? 360 : -360;
    $this->hdiff = $hdiff;
    $this->sdiff = $this->Clamp($s2, 0, 1) - $this->s1;
    $this->ldiff = $this->Clamp($l2, 0, 1) - $this->l1;
  }

  /**
   * Reverse direction of colour cycle
   */
  public function Reverse()
  {
    $this->hdiff += $this->hdiff < 0 ? 360 : -360;
  }

  /**
   * Return the colour from the range
   */
  public function offsetGet($offset)
  {
    $c = max($this->count - 1, 1);
    $offset = $this->Clamp($offset, 0, $c);
    $h = fmod(360 + $this->h1 + $offset * $this->hdiff / $c, 360);
    $s = $this->s1 + $offset * $this->sdiff / $c;
    $l = $this->l1 + $offset * $this->ldiff / $c;

    list($r, $g, $b) = $this->HSLtoRGB($h, $s, $l);
    return sprintf('#%02x%02x%02x', $r, $g, $b);
  }

  /**
   * Factory method creates an instance from RGB values
   */
  public static function FromRGB($r1, $g1, $b1, $r2, $g2, $b2)
  {
    list($h1, $s1, $l1) = SVGGraphColourRangeHSL::RGBtoHSL($r1, $g1, $b1);
    list($h2, $s2, $l2) = SVGGraphColourRangeHSL::RGBtoHSL($r2, $g2, $b2);
    return new SVGGraphColourRangeHSL($h1, $s1, $l1, $h2, $s2, $l2);
  }

  /**
   * Convert RGB to HSL (0-360, 0-1, 0-1)
   */
  public static function RGBtoHSL($r, $g, $b)
  {
    $r1 = SVGGraphColourRangeHSL::Clamp($r, 0, 255) / 255;
    $g1 = SVGGraphColourRangeHSL::Clamp($g, 0, 255) / 255;
    $b1 = SVGGraphColourRangeHSL::Clamp($b, 0, 255) / 255;
    $cmax = max($r1, $g1, $b1);
    $cmin = min($r1, $g1, $b1);
    $delta = $cmax - $cmin;

    $l = ($cmax + $cmin) / 2;
    if($delta == 0) {
      $h = $s = 0;
    } else {
      if($cmax == $r1) {
        $h = fmod(($g1 - $b1) / $delta, 6);
      } elseif($cmax == $g1) {
        $h = 2 + ($b1 - $r1) / $delta;
      } else {
        $h = 4 + ($r1 - $g1) / $delta;
      }
      $h = fmod(360 + ($h * 60), 360);
      $s = $delta / (1 - abs(2 * $l - 1));
    }
    return array($h, $s, $l);
  }

  /**
   * Convert HSL to RGB
   */
  public static function HSLtoRGB($h, $s, $l)
  {
    $h1 = SVGGraphColourRangeHSL::Clamp($h, 0, 360);
    $s1 = SVGGraphColourRangeHSL::Clamp($s, 0, 1);
    $l1 = SVGGraphColourRangeHSL::Clamp($l, 0, 1);

    $c = (1 - abs(2 * $l1 - 1)) * $s1;
    $x = $c * (1 - abs(fmod($h1 / 60, 2) - 1));
    $m = $l1 - $c / 2;

    $c = 255 * ($c + $m);
    $x = 255 * ($x + $m);
    $m *= 255;
    switch(floor($h1 / 60)) {
    case 0 : $rgb = array($c, $x, $m); break;
    case 1 : $rgb = array($x, $c, $m); break;
    case 2 : $rgb = array($m, $c, $x); break;
    case 3 : $rgb = array($m, $x, $c); break;
    case 4 : $rgb = array($x, $m, $c); break;
    case 5 : $rgb = array($c, $m, $x); break;
    }

    return $rgb;
  }
}


