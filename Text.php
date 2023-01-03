<?php
/**
 * Copyright (C) 2018-2022 Graham Breach
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
 * Text functions using calculated font metrics
 */
class Text {

  private $graph;
  private $font;
  private $metrics_file;
  private $adjust;
  private $encoding;
  private $no_tspan;
  private $no_metrics;
  private static $metrics;
  private static $measure_cache;
  private static $use_iconv = null;
  private static $use_mbstring = null;

  public function __construct(&$graph, $font = null, $adjust = 0)
  {
    // iconv can be troublesome
    if(Text::$use_iconv === null) {
      Text::$use_iconv = false;
      if($graph->getOption('use_iconv', true) && extension_loaded('iconv')) {
        // test the iconv function
        $test_euro = "Test:\u{20ac}";
        $out = iconv('UTF-8', 'ASCII//TRANSLIT', $test_euro);
        Text::$use_iconv = (strlen($out) > 0);
      }
    }

    // mbstring should be OK, but allow disabling it
    if(Text::$use_mbstring === null) {
      Text::$use_mbstring = $graph->getOption('use_mbstring', true) &&
        extension_loaded('mbstring');
    }

    $this->graph =& $graph;
    $this->font = $font;
    if($font !== null)
      $this->metrics_file = $this->metricsFilename($font);
    $this->encoding = strtoupper($graph->encoding);
    $this->no_tspan = $graph->getOption('no_tspan');
    $this->no_metrics = $graph->getOption('no_font_metrics');
    $this->adjust = $adjust ? $adjust : 0.6;
  }

  /**
   * Measures a block of text
   *  returns array($width, $height)
   */
  public function measure($text, $font_size, $angle = 0, $line_spacing = 0)
  {
    if(!is_string($text)) {
      if(is_numeric($text)) {
        $num = new Number($text);
        $text = $num->format();
      } else {
        $text = (string)$text;
      }
    }

    // convert to UTF-8
    $text = $this->convert($text);
    $cached = $this->measureCached($text, $font_size, $angle, $line_spacing);
    if($cached !== null)
      return $cached;

    $lines = $line_spacing > 0 ? $this->splitLines($text) : [$text];
    $width = $height = 0;
    foreach($lines as $l) {
      list($lw, $lh) = $this->measureLine($l, $font_size);
      if($lw > $width)
        $width = $lw;
      $height = $lh;
    }
    // height is height of first line + (line spacing) x (other lines)
    $height += $line_spacing * (count($lines) - 1);

    if($angle % 180 != 0) {
      $w = $height;
      $h = $width;
      if($angle % 90 != 0) {
        $a = deg2rad($angle);
        $sa = abs(sin($a));
        $ca = abs(cos($a));
        $w = $ca * $width + $sa * $height;
        $h = $sa * $width + $ca * $height;
      }
      $width = $w;
      $height = $h;
    }

    $this->cacheMeasurement($text, $font_size, $angle, $line_spacing, $width,
      $height);
    return [$width, $height];
  }

  /**
   * Measures a block of text and finds its position
   *  returns array($x, $y, $width, $height)
   */
  public function measurePosition($text, $font_size, $line_spacing, $x, $y,
    $anchor, $angle = 0, $rcx = null, $rcy = null)
  {
    $baseline = $this->baseline($font_size);
    $y -= $baseline;
    list($width, $height) = $this->measure($text, $font_size, 0,
      $line_spacing);
    if($anchor == 'end')
      $x -= $width;
    elseif($anchor == 'middle')
      $x -= $width / 2;

    if($angle) {
      // the four corners of the bounding box
      $points = [
        [$x, $y], [$x + $width, $y],
        [$x, $y + $height], [$x + $width, $y + $height]
      ];
      $arad = deg2rad($angle);
      $s = sin($arad);
      $c = cos($arad);
      $xp = [];
      $yp = [];
      foreach($points as $point) {
        // translate to origin
        $point[0] -= $rcx;
        $point[1] -= $rcy;
        // rotate
        $x1 = $point[0] * $c - $point[1] * $s;
        $y1 = $point[0] * $s + $point[1] * $c;
        // translate back
        $xp[] = $x1 + $rcx;
        $yp[] = $y1 + $rcy;
      }
      // find extents
      $x = min($xp);
      $max_x = max($xp);
      $y = min($yp);
      $max_y = max($yp);
      $width = $max_x - $x;
      $height = $max_y - $y;
    }
    return [$x, $y, $width, $height];
  }

  /**
   * Measures a single line of UTF-8 text
   */
  private function measureLine($text, $font_size)
  {
    $metrics = $this->no_metrics ? false : $this->loadMetrics($this->font);
    $width = $height = 0;

    // want to measure characters, not entities
    $text = $this->replaceEntities($text);

    $chars = $this->processLineArray($text);
    if(!$metrics) {
      // no metrics, use adjust value based on length of string
      $length = 0;
      foreach($chars as $char) {
        $code = Text::charCode($char);
        $length += Text::charWidth($code);
      }
      $width = $length * $font_size * $this->adjust;
      // height is font size
      return [$width, $font_size];
    }

    // metrics file found, so use char widths and kerning
    $metrics =& Text::$metrics[$this->metrics_file];

    $char_widths = [];
    foreach($chars as $char) {
      $code = Text::charCode($char);
      if(isset($metrics['widths'][$code])) {
        $char_widths[] = $metrics['widths'][$code];
        continue;
      }

      // use mean * relative width
      $char_size = Text::charWidth($code);
      $char_width = $metrics['mean'] * $char_size;
      $char_widths[] = $char_width;
    }
    $len = count($chars);
    for($i = 0; $i < $len - 1; ++$i) {
      $j = $i + 1;
      $sub = $chars[$i] . $chars[$j];
      if(isset($metrics['kern'][$sub])) {
        $kerning = $char_widths[$i] + $char_widths[$j] - $metrics['kern'][$sub];
        $char_widths[$j] -= $kerning;
      }
    }
    for($i = 0; $i < $len; ++$i) {
      $width += $char_widths[$i];
    }
    $width = $width * $font_size / $metrics['size'];
    $height = $metrics['height'] * $font_size / $metrics['size'];
    return [$width, $height];
  }

  /**
   * Returns the cached measurement, or NULL if not in cache
   */
  private function measureCached($text, $font_size, $angle, $line_spacing)
  {
    $key = $this->getCacheKey($text, $font_size, $angle, $line_spacing);
    return isset(Text::$measure_cache[$key]) ?
      Text::$measure_cache[$key] : null;
  }

  /**
   * Caches measurement
   */
  private function cacheMeasurement($text, $font_size, $angle, $line_spacing,
    $width, $height)
  {
    $key = $this->getCacheKey($text, $font_size, $angle, $line_spacing);
    Text::$measure_cache[$key] = [$width, $height];
  }

  /**
   * Returns the cache key for a string
   */
  private function getCacheKey($text, $font_size, $angle, $line_spacing)
  {
    $key = $text . '-' . $this->font . '-' . $this->adjust . '-' . $font_size .
      '-' . $angle . '-' . $line_spacing;
    return $key;
  }

  /**
   * Returns the baseline offset for the current font
   */
  public function baseline($font_size)
  {
    $metrics = $this->no_metrics ? false : $this->loadMetrics($this->font);

    if($metrics) {
      $metrics =& Text::$metrics[$this->metrics_file];
      return $metrics['baseline'] * $font_size / $metrics['size'];
    }

    // this approximation has been good enough until now
    return $font_size * 0.85;
  }

  /**
   * Displays text
   */
  public function text($text, $line_spacing, $attribs, $styles = null)
  {
    // convert to UTF-8 for processing
    $text = $this->convert($text);

    // strip special characters
    $text = htmlspecialchars($text, ENT_COMPAT, 'UTF-8');

    // put entities back in (XML entity names only)
    $text = preg_replace('/&amp;(amp|apos|gt|lt|quot|#x[a-fA-F0-9]+|#\d+);/u',
      '&$1;', $text);

    // empty string
    if($this->strlen($text, 'UTF-8') == 0) {
      $content = $this->unconvert(' ');
      return $this->graph->element('text', $attribs, $styles, $content);
    }

    // single line
    if($this->strpos($text, "\n", 0, 'UTF-8') === false) {
      $content = $this->processLine($text);
      $content = $this->unconvert($content);
      return $this->graph->element('text', $attribs, $styles, $content);
    }

    $lines = $this->splitLines($text);
    $content = '';
    $group = 'text';
    $line_element = 'tspan';
    $line_attr = ['x' => $attribs['x']];
    if($this->no_tspan) {
      $line_attr['y'] = $attribs['y'];
      $line_element = 'text';
      $group = 'g';
    } else {
      $line_attr['dy'] = 0;
    }
    $count = 1;
    foreach($lines as $line) {
      // blank tspan elements collapse to nothing, so insert a space
      if($this->strlen($line, 'UTF-8') == 0)
        $line = ' ';
      else
        $line = $this->processLine($line);
      $line = $this->unconvert($line);

      // the TRUE is for no whitespace
      $content .= $this->graph->element($line_element, $line_attr, null,
        $line, true);
      if($this->no_tspan) {
        $line_attr['y'] = $attribs['y'] + $line_spacing * $count;
      } else {
        $line_attr['dy'] = $line_spacing;
      }
      ++$count;
    }
    if($this->no_tspan)
      unset($attribs['x'], $attribs['y']);

    return $this->graph->element($group, $attribs, $styles, $content);
  }

  /**
   * Trim spaces at ends, collapse multiple spaces, return chars as array
   */
  public function processLineArray($str)
  {
    $chars = $this->splitChars($str);
    $start = 0;
    $end = count($chars) - 1;

    if($end >= $start) {
      // find first non-space
      do {
        $code = $this->charCode($chars[$start]);
      } while($this->isSpace($code) && ++$start <= $end);
    }

    if($start > $end) {
      // all spaces
      return [' '];
    }

    // find last non-space
    do {
      $code = $this->charCode($chars[$end]);
    } while($this->isSpace($code) && $start < --$end);

    $last_space = false;
    $processed = [];
    for($i = $start; $i <= $end; ++$i) {

      // reduce multiple normal spaces to one
      if($chars[$i] == ' ') {
        $last_space = true;
        continue;
      }

      if($last_space)
        $processed[] = ' ';
      $processed[] = $chars[$i];
      $last_space = false;
    }
    return $processed;
  }

  /**
   * Trim spaces at ends, collapse multiple spaces, return string
   */
  public function processLine($str)
  {
    $processed = $this->processLineArray($str);
    return implode($processed);
  }

  /**
   * Returns string length
   */
  public function strlen($text, $enc = null)
  {
    if($text === null)
      return 0;
    if($enc === null)
      $enc = $this->encoding;
    if(Text::$use_iconv)
      return iconv_strlen($text, $enc);
    if(Text::$use_mbstring)
      return mb_strlen($text, $enc);
    return strlen($text);
  }

  /**
   * Returns position of needle in haystack
   */
  public function strpos($text, $needle, $offset = 0, $enc = null)
  {
    if($enc === null)
      $enc = $this->encoding;
    if(Text::$use_iconv)
      return iconv_strpos($text, $needle, $offset, $enc);
    if(Text::$use_mbstring)
      return mb_strpos($text, $needle, $offset, $enc);
    return strpos($text, $needle, $offset);
  }

  /**
   * Splits UTF-8 string into lines
   */
  public function splitLines($text)
  {
    return preg_split("/\\n/u", $text);
  }

  /**
   * Splits UTF-8 string into chars
   */
  private function splitChars($text)
  {
    return preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
  }

  /**
   * Returns the code for a UTF-8 character
   */
  public static function charCode($char)
  {
    $code = ord(substr($char, 0, 1));
    if($code < 0x80) {
      return $code;
    } elseif(($code & 0xe0) == 0xc0) {
      $n = 1;
      $code = $code & 0x1f;
    } elseif(($code & 0xf0) == 0xe0) {
      $n = 2;
      $code = $code & 0x0f;
    } elseif(($code & 0xf8) == 0xf0) {
      $n = 3;
      $code = $code & 0x07;
    } else {
      // replacement character for unknown code point
      return 0xfffd;
    }

    for($i = 1; $i <= $n; ++$i) {
      $byte = ord(substr($char, $i, 1));
      $code = ($code * 0x40) + ($byte & 0x3f);
    }
    // should be some validation here

    return $code;
  }

  /**
   * Returns the UTF-8 character for a code point
   */
  public static function charFromCode($code)
  {
    if($code < 0x80)
      return chr($code);

    $u = ($code >> 16) & 0x1f;
    $z = ($code >> 12) & 0x0f;
    $y = ($code >> 6) & 0x3f;
    $x = ($code & 0x3f);

    if($u) {
      $b1 = 0xf0 + ($u >> 2);
      $b2 = 0x80 + (($u & 0x03) << 4) + $z;
      $b3 = 0x80 + $y;
      $b4 = 0x80 + $x;
      return chr($b1) . chr($b2) . chr($b3) . chr($b4);
    }

    if($z) {
      $b1 = 0xe0 + $z;
      $b2 = 0x80 + $y;
      $b3 = 0x80 + $x;
      return chr($b1) . chr($b2) . chr($b3);
    }

    $b1 = 0xc0 + $y;
    $b2 = 0x80 + $x;
    return chr($b1) . chr($b2);
  }

  /**
   * Returns the width of a character, relative to a plain letter
   */
  public static function charWidth($char)
  {
    if(Text::isCombiner($char))
      return 0;

    if(Text::isDoubleWidth($char))
      return 2;
    return 1;
  }

  /**
   * Returns TRUE if the character is some kind of space
   */
  public static function isSpace($char, $type = false)
  {
    $spaces = [
      0x20 => 'space',
      0xa0 => 'no-break space',
      // 0x1680 => 'ogham space mark', // more dash than space
      0x2000 => 'en quad',
      0x2001 => 'em quad',
      0x2002 => 'en space',
      0x2003 => 'em space',
      0x2004 => 'three-per-em space',
      0x2005 => 'four-per-em space',
      0x2006 => 'six-per-em space',
      0x2007 => 'figure space',
      0x2008 => 'punctuation space',
      0x2009 => 'thin space',
      0x200a => 'hair space',
      0x202f => 'narrow no-break space',
      0x205f => 'medium mathematical space',
      0x3000 => 'ideographic space',
    ];
    if(isset($spaces[$char]))
      return $type ? $spaces[$char] : true;
    return false;
  }

  /**
   * Returns TRUE if the character is a combining mark
   */
  public static function isCombiner($char)
  {
    $ranges = [
      [0x300,0x36f],   // combining diacritical marks
      [0x1ab0,0x1aff], // combining diacritical marks extended
      [0x1dc0,0x1dff], // combining diacritical marks supplement
      [0x20d0,0x20ff], // combining diacritical marks for symbols
      [0xfe20,0xfe2f], // combining half marks
    ];
    foreach($ranges as $range) {
      if($char >= $range[0] && $char <= $range[1])
        return true;
    }
    return false;
  }

  /**
   * Returns TRUE for a double-width character
   */
  public static function isDoubleWidth($char)
  {
    $ranges = [
      [0x1100,0x115F], // hangul jamo
      [0x11A3,0x11A7],
      [0x11FA,0x11FF],
      [0x2329,0x232A], // tech
      [0x2E80,0x2E99], // cjk radicals
      [0x2E9B,0x2EF3],
      [0x2F00,0x2FD5], // kangxi radicals
      [0x2FF0,0x2FFB], // ideographic description characters
      [0x3000,0x303E], // cjk symbols
      [0x3041,0x3096], // hiragana
      [0x3099,0x30FF], // katakana
      [0x3105,0x312D], // bopomofo
      [0x3131,0x318E], // hangul compat jamo
      [0x3190,0x31BA], // kanbun
      [0x31C0,0x31E3], // cjk strokes
      [0x31F0,0x321E], // katakana phonetic extensions
      [0x3220,0x3247], // enclosed cjk
      [0x3250,0x32FE],
      [0x3300,0x4DBF], // cjk
      [0x4E00,0xA48C],
      [0xA490,0xA4C6], // yi radicals
      [0xA960,0xA97C], // hangul jamo extended a
      [0xAC00,0xD7A3], // hangul syllables
      [0xD7B0,0xD7C6], // hangul jamo extended b
      [0xD7CB,0xD7FB],
      [0xF900,0xFAFF], // cjk compat ideographs
      [0xFE10,0xFE19], // vertical forms
      [0xFE30,0xFE52], // cjk compat forms
      [0xFE54,0xFE66], // small form variants
      [0xFE68,0xFE6B],
      [0xFF01,0xFF60], // halfwidth and fullwidth forms
      [0xFFE0,0xFFE6],
      [0x1B000,0x1B001], // kana supplement
      [0x1F200,0x1F202], // enclosed ideographic supplement
      [0x1F210,0x1F23A],
      [0x1F240,0x1F248],
      [0x1F250,0x1F251],
      [0x1F600,0x1F64F], // emoticons
      [0x1F900,0x1F9FF], // emoticons
      [0x20000,0x2FFFD], // cjk ideographs
    ];
    foreach($ranges as $range) {
      if($char >= $range[0] && $char <= $range[1])
        return true;
    }
    return false;
  }

  /**
   * Returns a substring
   */
  public function substr($text, $begin, $length = null)
  {
    if(Text::$use_iconv) {
      if($length === null)
        $length = iconv_strlen($text, $this->encoding);
      return iconv_substr($text, $begin, $length, $this->encoding);
    }

    if(Text::$use_mbstring)
      return mb_substr($text, $begin, $length, $this->encoding);

    return $length === null ? substr($text, $begin) :
      substr($text, $begin, $length);
  }

  /**
   * Returns the number of lines in a string
   */
  public function lines($text)
  {
    $c = 1;
    $pos = 0;
    while(($pos = $this->strpos($text, "\n", $pos)) !== false) {
      ++$c;
      ++$pos;
    }
    return $c;
  }

  /**
   * Replaces the entities in a string
   */
  public function replaceEntities($text)
  {
    $patterns = ['&amp;', '&apos;', '&lt;', '&gt;', '&quot;'];
    $replacements = ['&', "'", '<', '>', '"'];
    $text = str_replace($patterns, $replacements, $text);

    // numeric entities a bit trickier
    $text = preg_replace_callback('/&#(x)?([a-fA-F0-9]+);/ui', function($ent) {
      $code = ($ent[1] == 'x' ? base_convert($ent[2], 16, 10) : $ent[2]);
      return Text::charFromCode($code);
    }, $text);
    return $text;
  }

  /**
   * Converts a string to UTF-8, if possible
   */
  private function convert($text)
  {
    if($this->encoding != 'UTF-8') {
      $converted = false;
      if(Text::$use_iconv) {
        $converted = @iconv($this->encoding, 'UTF-8', $text);
        if($converted !== false) {
          $text = $converted;
          $converted = true;
        }
      }
      if(!$converted && Text::$use_mbstring) {
        $order = mb_detect_order();
        array_unshift($order, $this->encoding);
        $enc = mb_detect_encoding($text, $order);
        if($enc !== false)
          $text = @mb_convert_encoding($text, 'UTF-8', $enc);
      }
    }
    return $text;
  }

  /**
   * Converts back from UTF-8 to chosen encoding
   */
  private function unconvert($text)
  {
    if($this->encoding != 'UTF-8') {
      $converted = false;
      if(Text::$use_iconv) {
        $converted = @iconv('UTF-8', $this->encoding, $text);
        if($converted !== false) {
          $text = $converted;
          $converted = true;
        }
      }
      if(!$converted && Text::$use_mbstring) {
        $text = @mb_convert_encoding($text, $this->encoding, 'UTF-8');
      }
    }
    return $text;
  }

  /**
   * Returns the filename for a font metrics file
   */
  private static function metricsFilename($font)
  {
    return preg_replace('/[^a-z0-9]+/ui', '_', strtolower($font));
  }

  /**
   * Loads metrics for a font
   */
  private static function loadMetrics($font)
  {
    // metrics use JSON, so we need support
    if(!extension_loaded('json'))
      return false;

    $filename = Text::metricsFilename($font);
    if(isset(Text::$metrics[$filename])) {
      return (Text::$metrics[$filename] !== false);
    }

    $metrics_path = __DIR__ . '/fonts/' . $filename . '.json';
    if(file_exists($metrics_path)) {
      $metrics = file_get_contents($metrics_path);
      $metrics = @json_decode($metrics, true);
      // validate the metrics in case the file is corrupt
      if(is_array($metrics)) {
        if(isset($metrics['map'])) {
          $map_to = $metrics['map'];
          if(isset(Text::$metrics[$map_to])) {
            $metrics = Text::$metrics[$map_to];
          } else {
            $metrics_path = __DIR__ . '/fonts/' . $map_to . '.json';
            if(file_exists($metrics_path)) {
              $metrics = file_get_contents($metrics_path);
              $metrics = @json_decode($metrics, true);
            }
          }
        }

        if(isset($metrics['mean']) && $metrics['mean'] > 1
          && isset($metrics['size']) && $metrics['size'] > 1
          && isset($metrics['height']) && $metrics['height'] > 1
          && isset($metrics['baseline']) && $metrics['baseline'] > 1) {
          Text::$metrics[$filename] = $metrics;
          return true;
        }
      }
    }
    Text::$metrics[$filename] = false;
    return false;
  }
}

