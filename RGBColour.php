<?php
/**
 * Copyright (C) 2020-2022 Graham Breach
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
 * Class for colour manipulation
 */
class RGBColour {
  private $r = 0;
  private $g = 0;
  private $b = 0;
  private $a = 1.0;

  const KEYWORDS = [
    'aliceblue' => '#f0f8ff',
    'antiquewhite' => '#faebd7',
    'aqua' => '#00ffff',
    'aquamarine' => '#7fffd4',
    'azure' => '#f0ffff',
    'beige' => '#f5f5dc',
    'bisque' => '#ffe4c4',
    'black' => '#000000',
    'blanchedalmond' => '#ffebcd',
    'blue' => '#0000ff',
    'blueviolet' => '#8a2be2',
    'brown' => '#a52a2a',
    'burlywood' => '#deb887',
    'cadetblue' => '#5f9ea0',
    'chartreuse' => '#7fff00',
    'chocolate' => '#d2691e',
    'coral' => '#ff7f50',
    'cornflowerblue' => '#6495ed',
    'cornsilk' => '#fff8dc',
    'crimson' => '#dc143c',
    'cyan' => '#00ffff',
    'darkblue' => '#00008b',
    'darkcyan' => '#008b8b',
    'darkgoldenrod' => '#b8860b',
    'darkgray' => '#a9a9a9',
    'darkgreen' => '#006400',
    'darkgrey' => '#a9a9a9',
    'darkkhaki' => '#bdb76b',
    'darkmagenta' => '#8b008b',
    'darkolivegreen' => '#556b2f',
    'darkorange' => '#ff8c00',
    'darkorchid' => '#9932cc',
    'darkred' => '#8b0000',
    'darksalmon' => '#e9967a',
    'darkseagreen' => '#8fbc8f',
    'darkslateblue' => '#483d8b',
    'darkslategray' => '#2f4f4f',
    'darkslategrey' => '#2f4f4f',
    'darkturquoise' => '#00ced1',
    'darkviolet' => '#9400d3',
    'deeppink' => '#ff1493',
    'deepskyblue' => '#00bfff',
    'dimgray' => '#696969',
    'dimgrey' => '#696969',
    'dodgerblue' => '#1e90ff',
    'firebrick' => '#b22222',
    'floralwhite' => '#fffaf0',
    'forestgreen' => '#228b22',
    'fuchsia' => '#ff00ff',
    'gainsboro' => '#dcdcdc',
    'ghostwhite' => '#f8f8ff',
    'gold' => '#ffd700',
    'goldenrod' => '#daa520',
    'gray' => '#808080',
    'green' => '#008000',
    'greenyellow' => '#adff2f',
    'grey' => '#808080',
    'honeydew' => '#f0fff0',
    'hotpink' => '#ff69b4',
    'indianred' => '#cd5c5c',
    'indigo' => '#4b0082',
    'ivory' => '#fffff0',
    'khaki' => '#f0e68c',
    'lavender' => '#e6e6fa',
    'lavenderblush' => '#fff0f5',
    'lawngreen' => '#7cfc00',
    'lemonchiffon' => '#fffacd',
    'lightblue' => '#add8e6',
    'lightcoral' => '#f08080',
    'lightcyan' => '#e0ffff',
    'lightgoldenrodyellow' => '#fafad2',
    'lightgray' => '#d3d3d3',
    'lightgreen' => '#90ee90',
    'lightgrey' => '#d3d3d3',
    'lightpink' => '#ffb6c1',
    'lightsalmon' => '#ffa07a',
    'lightseagreen' => '#20b2aa',
    'lightskyblue' => '#87cefa',
    'lightslategray' => '#778899',
    'lightslategrey' => '#778899',
    'lightsteelblue' => '#b0c4de',
    'lightyellow' => '#ffffe0',
    'lime' => '#00ff00',
    'limegreen' => '#32cd32',
    'linen' => '#faf0e6',
    'magenta' => '#ff00ff',
    'maroon' => '#800000',
    'mediumaquamarine' => '#66cdaa',
    'mediumblue' => '#0000cd',
    'mediumorchid' => '#ba55d3',
    'mediumpurple' => '#9370db',
    'mediumseagreen' => '#3cb371',
    'mediumslateblue' => '#7b68ee',
    'mediumspringgreen' => '#00fa9a',
    'mediumturquoise' => '#48d1cc',
    'mediumvioletred' => '#c71585',
    'midnightblue' => '#191970',
    'mintcream' => '#f5fffa',
    'mistyrose' => '#ffe4e1',
    'moccasin' => '#ffe4b5',
    'navajowhite' => '#ffdead',
    'navy' => '#000080',
    'oldlace' => '#fdf5e6',
    'olive' => '#808000',
    'olivedrab' => '#6b8e23',
    'orange' => '#ffa500',
    'orangered' => '#ff4500',
    'orchid' => '#da70d6',
    'palegoldenrod' => '#eee8aa',
    'palegreen' => '#98fb98',
    'paleturquoise' => '#afeeee',
    'palevioletred' => '#db7093',
    'papayawhip' => '#ffefd5',
    'peachpuff' => '#ffdab9',
    'peru' => '#cd853f',
    'pink' => '#ffc0cb',
    'plum' => '#dda0dd',
    'powderblue' => '#b0e0e6',
    'purple' => '#800080',
    'red' => '#ff0000',
    'rosybrown' => '#bc8f8f',
    'royalblue' => '#4169e1',
    'saddlebrown' => '#8b4513',
    'salmon' => '#fa8072',
    'sandybrown' => '#f4a460',
    'seagreen' => '#2e8b57',
    'seashell' => '#fff5ee',
    'sienna' => '#a0522d',
    'silver' => '#c0c0c0',
    'skyblue' => '#87ceeb',
    'slateblue' => '#6a5acd',
    'slategray' => '#708090',
    'slategrey' => '#708090',
    'snow' => '#fffafa',
    'springgreen' => '#00ff7f',
    'steelblue' => '#4682b4',
    'tan' => '#d2b48c',
    'teal' => '#008080',
    'thistle' => '#d8bfd8',
    'tomato' => '#ff6347',
    'turquoise' => '#40e0d0',
    'violet' => '#ee82ee',
    'wheat' => '#f5deb3',
    'white' => '#ffffff',
    'whitesmoke' => '#f5f5f5',
    'yellow' => '#ffff00',
    'yellowgreen' => '#9acd32',
  ];

  public function __construct($colour)
  {
    if(!is_string($colour))
      throw new \InvalidArgumentException('Expected string');

    $original = $colour;
    $colour = strtolower($colour);
    if($colour === 'transparent') {
      $this->r = $this->g = $this->b = $this->a = 0;
      return;
    }

    if(isset(RGBColour::KEYWORDS[$colour]))
      $colour = RGBColour::KEYWORDS[$colour];

    $r = $g = $b = 0;
    $c = $colour;
    if(strpos($colour, '#') !== 0) {
      $c = RGBColour::fromRGB($colour);
      if($c === null)
        $c = RGBColour::fromRGBA($colour);
      if($c === null)
        $c = RGBColour::fromHSL($colour);
      if($c === null)
        $c = RGBColour::fromHSLA($colour);
    }

    if(is_array($c)) {
      $this->r = $c[0];
      $this->g = $c[1];
      $this->b = $c[2];
      if(isset($c[3]))
        $this->a = $c[3];
      return;
    }

    if(strlen($colour) === 4)
      $c = RGBColour::hex3ToHex($colour);

    if($c !== null && preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $c, $m)) {
      $red = $m[1];
      $green = $m[2];
      $blue = $m[3];
      $this->r = base_convert($red, 16, 10);
      $this->g = base_convert($green, 16, 10);
      $this->b = base_convert($blue, 16, 10);
      return;
    }
    throw new \Exception('Unable to parse colour: [' . $original .']');
  }

  /**
   * Converts rgb(r,g,b) to [$r, $g, $b]
   */
  public static function fromRGB($colour)
  {
    $r = $g = $b = 0;
    $rgb_integer = '/^rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)$/';
    $rgb_percent = '/^rgb\(\s*(\d+)%\s*,\s*(\d+)%\s*,\s*(\d+)%\s*\)$/';
    if(preg_match($rgb_integer, $colour, $m)) {
      $r = min(255, max(0, $m[1]));
      $g = min(255, max(0, $m[2]));
      $b = min(255, max(0, $m[3]));
    } elseif(preg_match($rgb_percent, $colour, $m)) {
      $r = min(100, max(0, $m[1])) * 255 / 100;
      $g = min(100, max(0, $m[2])) * 255 / 100;
      $b = min(100, max(0, $m[3])) * 255 / 100;
    } else {
      return null;
    }

    return [$r, $g, $b];
  }

  /**
   * Converts rgba(r,g,b,a) to [$r, $g, $b, $a]
   */
  public static function fromRGBA($colour)
  {
    $r = $g = $b = $a = 0;
    $rgba_integer = '/^rgba\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+(\.\d+)?)\s*\)$/';
    $rgba_percent = '/^rgba\(\s*(\d+)%\s*,\s*(\d+)%\s*,\s*(\d+)%\s*,\s*(\d+(\.\d+)?)\s*\)$/';
    if(preg_match($rgba_integer, $colour, $m)) {
      $r = min(255, max(0, $m[1]));
      $g = min(255, max(0, $m[2]));
      $b = min(255, max(0, $m[3]));
      $a = min(1.0, max(0.0, $m[4]));
    } elseif(preg_match($rgba_percent, $colour, $m)) {
      $r = min(100, max(0, $m[1])) * 255 / 100;
      $g = min(100, max(0, $m[2])) * 255 / 100;
      $b = min(100, max(0, $m[3])) * 255 / 100;
      $a = min(1.0, max(0.0, $m[4]));
    } else {
      return null;
    }

    return [$r, $g, $b, $a];
  }

  /**
   * Converts hsl(h, s%, l%) to [$r, $g, $b]
   */
  public static function fromHSL($colour)
  {
    $r = $g = $b = 0;
    $hsl = '/^hsl\(\s*(\d+)\s*,\s*(\d+)%\s*,\s*(\d+)%\s*\)$/';
    if(preg_match($hsl, $colour, $m)) {
      $h = $m[1];
      $s = $m[2] / 100.0;
      $l = $m[3] / 100.0;
      return RGBColour::hslToRgb($h, $s, $l);
    }
    return null;
  }

  /**
   * Converts hsla(h, s%, l%, a) to [$r, $g, $b, $a]
   */
  public static function fromHSLA($colour)
  {
    $r = $g = $b = 0;
    $hsla = '/^hsla\(\s*(\d+)\s*,\s*(\d+)%\s*,\s*(\d+)%\s*,\s*(\d+(\.\d+)?)\s*\)$/';
    if(preg_match($hsla, $colour, $m)) {
      $h = $m[1];
      $s = $m[2] / 100.0;
      $l = $m[3] / 100.0;
      list($r, $g, $b) = RGBColour::hslToRgb($h, $s, $l);
      $a = min(1.0, max(0.0, $m[4]));
      return [$r, $g, $b, $a];
    }
    return null;
  }

  /**
   * Converts 3-digit hex to 6-digit hex
   */
  public static function hex3ToHex($colour)
  {
    if(strlen($colour) !== 4 || strpos($colour, '#') !== 0)
      return null;
    if(preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', $colour, $m))
      return strtolower('#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3]);
    return null;
  }

  /**
   * Convert HSL to RGB
   */
  public static function hslToRgb($h, $s, $l)
  {
    $h1 = fmod($h + 720, 360);
    $s1 = min(1.0, max(0.0, $s));
    $l1 = min(1.0, max(0.0, $l));

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

  /**
   * Convert RGB to HSL
   */
  public static function rgbToHsl($r, $g, $b)
  {
    $r1 = min(255.0, max(0.0, $r)) / 255.0;
    $g1 = min(255.0, max(0.0, $g)) / 255.0;
    $b1 = min(255.0, max(0.0, $b)) / 255.0;
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
   * Sets the colour components and alpha
   */
  public function setRGB($r, $g, $b, $a = null)
  {
    $this->r = $r;
    $this->g = $g;
    $this->b = $b;
    if($a !== null)
      $this->a = $a;
  }

  /**
   * Returns the R,G,B values
   */
  public function getRGB()
  {
    return [$this->r, $this->g, $this->b];
  }

  /**
   * Sets the colour from HSL and alpha
   */
  public function setHSL($h, $s, $l, $a = null)
  {
    list($r, $g, $b) = RGBColour::hslToRgb($h, $s, $l);
    $this->setRGB($r, $g, $b, $a);
  }

  /**
   * Returns H, S, L values
   */
  public function getHSL()
  {
    return RGBColour::rgbToHsl($this->r, $this->g, $this->b);
  }

  /**
   * Returns the A value
   */
  public function getA()
  {
    return $this->a;
  }

  /**
   * Returns the value as a hex string
   */
  public function getHex()
  {
    return sprintf('#%02x%02x%02x', $this->r, $this->g, $this->b);
  }
}

