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
 * Colour range for HSL values
 */
class ColourRangeHSL extends ColourRange {

  private $h1, $s1, $l1;
  private $hdiff, $sdiff, $ldiff;

  /**
   * HSL range
   */
  public function __construct($h1, $s1, $l1, $h2, $s2, $l2)
  {
    $this->h1 = $this->clamp($h1, 0, 360);
    $this->s1 = $this->clamp($s1, 0, 1);
    $this->l1 = $this->clamp($l1, 0, 1);

    $hdiff = $this->clamp($h2, 0, 360) - $this->h1;
    if(abs($hdiff) > 180)
      $hdiff += $hdiff < 0 ? 360 : -360;
    $this->hdiff = $hdiff;
    $this->sdiff = $this->clamp($s2, 0, 1) - $this->s1;
    $this->ldiff = $this->clamp($l2, 0, 1) - $this->l1;
  }

  /**
   * Reverse direction of colour cycle
   */
  public function reverse()
  {
    $this->hdiff += $this->hdiff < 0 ? 360 : -360;
  }

  /**
   * Return the colour from the range
   */
  #[\ReturnTypeWillChange]
  public function offsetGet($offset)
  {
    $c = max($this->count - 1, 1);
    $offset = $this->clamp($offset, 0, $c);
    $h = fmod(360 + $this->h1 + $offset * $this->hdiff / $c, 360);
    $s = $this->s1 + $offset * $this->sdiff / $c;
    $l = $this->l1 + $offset * $this->ldiff / $c;

    list($r, $g, $b) = $this->hslToRgb($h, $s, $l);
    return sprintf('#%02x%02x%02x', $r, $g, $b);
  }

  /**
   * Factory method creates an instance from RGB values
   */
  public static function fromRgb($r1, $g1, $b1, $r2, $g2, $b2)
  {
    list($h1, $s1, $l1) = ColourRangeHSL::rgbToHsl($r1, $g1, $b1);
    list($h2, $s2, $l2) = ColourRangeHSL::rgbToHsl($r2, $g2, $b2);
    return new ColourRangeHSL($h1, $s1, $l1, $h2, $s2, $l2);
  }

  /**
   * Convert RGB to HSL (0-360, 0-1, 0-1)
   */
  public static function rgbToHsl($r, $g, $b)
  {
    $r1 = ColourRangeHSL::clamp($r, 0, 255) / 255;
    $g1 = ColourRangeHSL::clamp($g, 0, 255) / 255;
    $b1 = ColourRangeHSL::clamp($b, 0, 255) / 255;
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
    return [$h, $s, $l];
  }

  /**
   * Convert HSL to RGB
   */
  public static function hslToRgb($h, $s, $l)
  {
    $h1 = ColourRangeHSL::clamp($h, 0, 360);
    $s1 = ColourRangeHSL::clamp($s, 0, 1);
    $l1 = ColourRangeHSL::clamp($l, 0, 1);

    $c = (1 - abs(2 * $l1 - 1)) * $s1;
    $x = $c * (1 - abs(fmod($h1 / 60, 2) - 1));
    $m = $l1 - $c / 2;

    $c = 255 * ($c + $m);
    $x = 255 * ($x + $m);
    $m *= 255;
    switch(floor($h1 / 60)) {
    case 0 : $rgb = [$c, $x, $m]; break;
    case 1 : $rgb = [$x, $c, $m]; break;
    case 2 : $rgb = [$m, $c, $x]; break;
    case 3 : $rgb = [$m, $x, $c]; break;
    case 4 : $rgb = [$x, $m, $c]; break;
    case 5 : $rgb = [$c, $m, $x]; break;
    }

    return $rgb;
  }
}

