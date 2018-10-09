<?php
/**
 * Copyright (C) 2009-2018 Graham Breach
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

define('SVGGRAPH_VERSION', 'SVGGraph 2.29');

require_once 'SVGGraphColours.php';
require_once 'SVGGraphText.php';

class SVGGraph {

  private $width = 100;
  private $height = 100;
  private $settings = array();
  public $values = array();
  public $links = NULL;
  public $colours = NULL;
  private $colour_sets = 0;
  protected static $last_instance = NULL;

  public function __construct($w, $h, $settings = NULL)
  {
    $this->width = $w;
    $this->height = $h;

    if(is_array($settings)) {
      // structured_data, when FALSE disables structure
      if(isset($settings['structured_data']) && !$settings['structured_data'])
        unset($settings['structure']);
      $this->settings = $settings;
    }
  }

  public function Values($values)
  {
    if(is_array($values)) 
      $this->values = $values;
    else
      $this->values = func_get_args();
  }
  public function Links($links)
  {
    if(is_array($links)) 
      $this->links = $links;
    else
      $this->links = func_get_args();
  }

  /**
   * Assign a single colour set for use across datasets
   */
  public function Colours($colours)
  {
    $this->colours = $colours;
  }

  /**
   * Sets colours for a single dataset
   */
  public function ColourSet($dataset, $colours)
  {
    if(!is_object($this->colours))
      $this->colours = new SVGGraphColours();
    $this->colours->Set($dataset, $colours);
  }

  /**
   * Sets up RGB colour range
   */
  public function ColourRangeRGB($dataset, $r1, $g1, $b1, $r2, $g2, $b2)
  {
    if(!is_object($this->colours))
      $this->colours = new SVGGraphColours();
    $this->colours->RangeRGB($dataset, $r1, $g1, $b1, $r2, $g2, $b2);
  }

  /**
   * RGB colour range from hex codes
   */
  public function ColourRangeHexRGB($dataset, $c1, $c2)
  {
    if(!is_object($this->colours))
      $this->colours = new SVGGraphColours();
    $this->colours->RangeHexRGB($dataset, $c1, $c2);
  }

  /**
   * Sets up HSL colour range
   */
  public function ColourRangeHSL($dataset, $h1, $s1, $l1, $h2, $s2, $l2,
    $reverse = false)
  {
    if(!is_object($this->colours))
      $this->colours = new SVGGraphColours();
    $this->colours->RangeHSL($dataset, $h1, $s1, $l1, $h2, $s2, $l2, $reverse);
  }

  /**
   * HSL colour range from hex codes
   */
  public function ColourRangeHexHSL($dataset, $c1, $c2, $reverse = false)
  {
    if(!is_object($this->colours))
      $this->colours = new SVGGraphColours();
    $this->colours->RangeHexHSL($dataset, $c1, $c2, $reverse);
  }

  /**
   * Sets up HSL colour range from RGB values
   */
  public function ColourRangeRGBtoHSL($dataset, $r1, $g1, $b1, $r2, $g2, $b2,
    $reverse = false)
  {
    if(!is_object($this->colours))
      $this->colours = new SVGGraphColours();
    $this->colours->RangeRGBtoHSL($dataset, $r1, $g1, $b1, $r2, $g2, $b2,
      $reverse);
  }


  /**
   * Instantiate the correct class
   */
  private function Setup($class)
  {
    // load the relevant class file
    if(!class_exists($class, FALSE))
      include 'SVGGraph' . $class . '.php';

    $g = new $class($this->width, $this->height, $this->settings);
    $g->Values($this->values);
    $g->Links($this->links);
    if(is_object($this->colours))
      $g->colours = $this->colours;
    else
      $g->colours = new SVGGraphColours($this->colours);
    return $g;
  }

  /**
   * Fetch the content
   */
  public function Fetch($class, $header = TRUE, $defer_js = TRUE)
  {
    SVGGraph::$last_instance = $this->Setup($class);
    return SVGGraph::$last_instance->Fetch($header, $defer_js);
  }

  /**
   * Pass in the type of graph to display
   */
  public function Render($class, $header = TRUE, $content_type = TRUE,
    $defer_js = FALSE)
  {
    SVGGraph::$last_instance = $this->Setup($class);
    return SVGGraph::$last_instance->Render($header, $content_type, $defer_js);
  }

  /**
   * Fetch the Javascript for ALL graphs that have been Fetched
   */
  public static function FetchJavascript()
  {
    if(!is_null(SVGGraph::$last_instance))
      return SVGGraph::$last_instance->FetchJavascript(true, true, true);
  }
}

/**
 * Base class for all graph types
 */
abstract class Graph {

  protected $settings = array();
  protected $values = array();
  protected $link_base = '';
  protected $link_target = '_blank';
  protected $links = array();

  protected $gradients = array();
  protected $gradient_map = array();
  protected $pattern_list = NULL;
  protected $defs = array();
  protected $back_matter = '';

  protected $namespaces = array();
  protected static $javascript = NULL;
  private static $last_id = 0;
  private static $precision = 5;
  private static $decimal = '.';
  private static $thousands = ',';
  public static $key_format = NULL;
  protected $legend_reverse = false;
  protected $force_assoc = false;
  protected $repeated_keys = 'error';
  protected $sort_keys = true;
  protected $require_structured = false;
  protected $require_integer_keys = true;
  protected $multi_graph = NULL;
  protected $legend = NULL;

  public function __construct($w, $h, $settings = NULL)
  {
    $this->width = $w;
    $this->height = $h;

    // get settings from ini file that are relevant to this class
    $ini_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'svggraph.ini';
    if(!file_exists($ini_file))
      $ini_settings = FALSE;
    else
      $ini_settings = parse_ini_file($ini_file, TRUE);
    if($ini_settings === FALSE)
      die("Ini file [{$ini_file}] not found -- exiting");

    $class = get_class($this);
    $hierarchy = array($class);
    while($class = get_parent_class($class))
      array_unshift($hierarchy, $class);

    while(count($hierarchy)) {
      $class = array_shift($hierarchy);
      if(array_key_exists($class, $ini_settings))
        $this->settings = array_merge($this->settings, $ini_settings[$class]);
    }

    if(is_array($settings))
      $this->Settings($settings);
  }


  /**
   * Retrieves properties from the settings array if they are not already
   * available as properties, also sets up javascript and data_labels
   */
  public function __get($name)
  {
    switch($name) {
    case 'javascript':
      // $this->javascript will forward to the static Graph::$javascript
      if(!isset(Graph::$javascript)) {
        include_once 'SVGGraphJavascript.php';
        Graph::$javascript = new SVGGraphJavascript($this->settings, $this);
      }
      return Graph::$javascript;
    case 'data_labels':
      include_once 'SVGGraphDataLabels.php';
      $this->data_labels = new DataLabels($this);
      return $this->data_labels;
    }
    $this->{$name} = isset($this->settings[$name]) ? $this->settings[$name] : null;
    return $this->{$name};
  }

  /**
   * Make empty($this->option) more robust
   */
  public function __isset($name)
  {
    return isset($this->settings[$name]);
  }

  /**
   * Sets the options
   */
  public function Settings(&$settings)
  {
    foreach($settings as $key => $value) {
      $this->settings[$key] = $value;
      $this->{$key} = $value;
    }
  }

  /**
   * Sets the graph values
   */
  public function Values($values)
  {
    $new_values = array();
    $v = func_get_args();
    if(count($v) == 1)
      $v = array_shift($v);

    $set_values = true;
    if(is_array($v)) {
      reset($v);
      $first_key = key($v);
      if(!is_null($first_key) && is_array($v[$first_key])) {
        foreach($v as $data_set)
          $new_values[] = $data_set;
        $set_values = false;
      }
    }

    if($set_values)
      $new_values[] = $v;

    if($this->scatter_2d) {
      $this->scatter_2d = false;
      if(empty($this->structure))
        $this->structure = array('key' => 0, 'value' => 1, 'datasets' => true);
    }

    if($this->datetime_keys && $this->datetime_key_format) {
      Graph::$key_format = $this->datetime_key_format;
    }

    if($this->structured_data || is_array($this->structure)) {
      $this->structured_data = true;
      require_once 'SVGGraphStructuredData.php';
      $this->values = new SVGGraphStructuredData($new_values, $this->force_assoc,
        $this->datetime_keys, $this->structure, $this->repeated_keys,
        $this->sort_keys, $this->require_integer_keys,
        $this->require_structured);
    } else {
      require_once 'SVGGraphData.php';
      $this->values = new SVGGraphData($new_values, $this->force_assoc,
        $this->datetime_keys);
      if(!$this->values->error && !empty($this->require_structured))
        $this->values->error = get_class($this) . ' requires structured data';
    }
  }

  /**
   * Sets the links from each item
   */
  public function Links()
  {
    $this->links = func_get_args();
  }

  protected function GetMinValue()
  {
    if(!is_null($this->multi_graph))
      return $this->multi_graph->GetMinValue();
    return $this->values->GetMinValue();
  }
  protected function GetMaxValue()
  {
    if(!is_null($this->multi_graph))
      return $this->multi_graph->GetMaxValue();
    return $this->values->GetMaxValue();
  }
  protected function GetMinKey()
  {
    if(!is_null($this->multi_graph))
      return $this->multi_graph->GetMinKey();
    return $this->values->GetMinKey();
  }
  protected function GetMaxKey()
  {
    if(!is_null($this->multi_graph))
      return $this->multi_graph->GetMaxKey();
    return $this->values->GetMaxKey();
  }
  protected function GetKey($i)
  {
    if(!is_null($this->multi_graph))
      return $this->multi_graph->GetKey($i);
    return $this->values->GetKey($i);
  }

  /**
   * Draws the selected graph
   */
  public function DrawGraph()
  {
    $canvas_id = $this->NewID();
    $this->InitLegend();

    $contents = $this->Canvas($canvas_id);
    $contents .= $this->DrawTitle();
    $contents .= $this->Draw();
    $contents .= $this->DrawDataLabels();
    $contents .= $this->DrawBackMatter();
    $contents .= $this->DrawLegend();

    // rounded rects might need a clip path
    if($this->back_round && $this->back_round_clip) {
      $group = array('clip-path' => "url(#{$canvas_id})");
      return $this->Element('g', $group, NULL, $contents);
    }
    return $contents;
  }


  /**
   * Adds any markup that goes after the graph
   */
  protected function DrawBackMatter()
  {
    return $this->back_matter;
  }


  /**
   * Sets up the legend class
   */
  protected function InitLegend()
  {
    // see if the legend is needed
    if(!$this->show_legend || (empty($this->legend_entries) &&
      (!isset($this->structure) || !isset($this->structure['legend_text'])))) {
      $this->legend = NULL;
      return;
    }
    require_once 'SVGGraphLegend.php';
    $this->legend = new SVGGraphLegend($this, $this->legend_reverse);
  }

  /**
   * Draws the legend
   */
  protected function DrawLegend()
  {
    if(is_null($this->legend))
      return '';
    return $this->legend->Draw();
  }

  /**
   * Parses a position string, returning x and y coordinates
   */
  public function ParsePosition($pos, $w = 0, $h = 0, $pad = 0)
  {
    $inner = true;
    $parts = preg_split('/\s+/', $pos);
    if(count($parts)) {
      // if 'outer' is found after 'inner', it takes precedence
      $parts = array_reverse($parts);
      $inner_at = array_search('inner', $parts);
      $outer_at = array_search('outer', $parts);

      if($outer_at !== false && ($inner_at === false || $inner_at < $outer_at))
        $inner = false;
    }
  
    if($inner) {
      $t = $this->pad_top;
      $l = $this->pad_left;
      $b = $this->height - $this->pad_bottom;
      $r = $this->width - $this->pad_right;
      // make sure it fits to keep RelativePosition happy
      if($w > $r - $l) $w = $r - $l;
      if($h > $b - $t) $h = $b - $t;
    } else {
      $t = $l = 0;
      $b = $this->height;
      $r = $this->width;
    }

    // ParsePosition is always inside canvas or graph, defaulted to top left
    $pos = "top left " . str_replace('outer', 'inner', $pos);
    return Graph::RelativePosition($pos, $t, $l, $b, $r, $w, $h, $pad);
  }

  /**
   * Returns [hpos,vpos,offset_x,offset_y] positions derived from full
   * position string
   */
  public static function TranslatePosition($pos)
  {
    $parts = preg_split('/\s+/', strtolower($pos));
    $offset_x = $offset_y = 0;
    $inside = true;
    $vpos = 'm';
    $hpos = 'c';

    // translated positions:
    // ot, t, m, b, ob = outside top, top, middle, bottom, outside bottom
    // ol, l, c, r, or = outside left, left, centre, right, outside right
    while(count($parts)) {
      $part = array_shift($parts);
      switch($part) {
      case 'outer' :
      case 'outside' : $inside = false;
        break;
      case 'inner' :
      case 'inside' : $inside = true;
        break;
      case 'top' : $vpos = $inside ? 't' : 'ot';
        break;
      case 'bottom' : $vpos = $inside ? 'b' : 'ob';
        break;
      case 'left' : $hpos = $inside ? 'l' : 'ol';
        break;
      case 'right' : $hpos = $inside ? 'r' : 'or';
        break;
      case 'above' : $inside = false; $vpos = 'ot';
        break;
      case 'below' : $inside = false; $vpos = 'ob';
        break;
      default:
        if(is_numeric($part)) {
          $offset_x = $part;
          if(count($parts) && is_numeric($parts[0]))
            $offset_y = array_shift($parts);
        }
      }
    }
    return array($hpos, $vpos, $offset_x, $offset_y);
  }

  /**
   * Returns [x,y,text-anchor,hpos,vpos] position that is $pos relative to the
   * top, left, bottom and right.
   * When $text is true, x and y are adjusted for text-anchor position
   */
  public static function RelativePosition($pos, $top, $left,
    $bottom, $right, $width, $height, $pad, $text = false)
  {
    list($hpos, $vpos, $offset_x, $offset_y) = Graph::TranslatePosition($pos);

    // if the containers have no thickness, position outside
    $translate = array('l' => 'ol', 'r' => 'or', 't' => 'ot', 'b' => 'ob');
    if($top == $bottom && isset($translate[$vpos]))
      $vpos = $translate[$vpos];
    if($left == $right && isset($translate[$hpos]))
      $hpos = $translate[$hpos];

    switch($vpos) {
    case 'ot' : $y = $top - $height - $pad; break;
    case 't' : $y = $top + $pad; break;
    case 'b' : $y = $bottom - $height - $pad; break;
    case 'ob' : $y = $bottom + $pad; break;
    case 'm' :
    default :
      $y = $top + ($bottom - $top - $height) / 2; break;
    }

    if(($hpos == 'r' || $hpos == 'l') && $right - $left - $pad - $width < 0)
      $hpos = 'c';
    switch($hpos) {
    case 'ol' : $x = $left - $width - $pad; break;
    case 'l' : $x = $left + $pad; break;
    case 'r' : $x = $right - $width - $pad; break;
    case 'or' : $x = $right + $pad; break;
    case 'c' :
    default :
      $x = $left + ($right - $left - $width) / 2; break;
    }

    $y += $offset_y;
    $x += $offset_x;
  
    // third return value is text alignment
    $align_map = array(
      'ol' => 'end', 'l' => 'start', 'c' => 'middle',
      'r' => 'end', 'or' => 'start'
    );
    $text_align = $align_map[$hpos];

    // in text mode, adjust X for text alignment
    if($text && $hpos != 'l' && $hpos != 'or') {
      if($hpos == 'c')
        $x += $width / 2;
      else
        $x += $width;
    }
    return array($x, $y, $text_align, $hpos, $vpos);
  }

  /**
   * Sets the style info for the legend
   */
  protected function SetLegendEntry($dataset, $index, $item, $style_info)
  {
    if(is_null($this->legend))
      return;
    $this->legend->SetEntry($dataset, $index, $item, $style_info);
  }

  /**
   * Subclasses must draw the entry, if they can
   */
  protected function DrawLegendEntry($x, $y, $w, $h, $entry)
  {
    return '';
  }

  /**
   * Draws the graph title, if there is one
   */
  protected function DrawTitle()
  {
    $svg_text = new SVGGraphText($this, $this->graph_title_font);
    if($svg_text->Strlen($this->graph_title) <= 0)
      return '';

    $pos = $this->graph_title_position;
    if($pos != 'bottom' && $pos != 'left' && $pos != 'right')
      $pos = 'top';
    $pad_side = 'pad_' . $pos;
    $font_size = $this->graph_title_font_size;
    list($width, $height) = $svg_text->Measure($this->graph_title, $font_size,
      0, $font_size);
    $baseline = $svg_text->Baseline($font_size);
    $text = array(
      'font-size' => $font_size,
      'font-family' => $this->graph_title_font,
      'font-weight' => $this->graph_title_font_weight,
      'text-anchor' => 'middle',
      'fill' => $this->graph_title_colour
    );

    // ensure outside padding is at least the title space
    if($this->{$pad_side} < $this->graph_title_space)
      $this->{$pad_side} = $this->graph_title_space;

    if($pos == 'left') {
      $text['x'] = $this->pad_left + $baseline;
      $text['y'] = $this->height / 2;
      $text['transform'] = "rotate(270,$text[x],$text[y])";
    } elseif($pos == 'right') {
      $text['x'] = $this->width - $this->pad_right - $baseline;
      $text['y'] = $this->height / 2;
      $text['transform'] = "rotate(90,$text[x],$text[y])";
    } elseif($pos == 'bottom') {
      $text['x'] = $this->width / 2;
      $text['y'] = $this->height - $this->pad_bottom - $height + $baseline;
    } else {
      $text['x'] = $this->width / 2;
      $text['y'] = $this->pad_top + $baseline;
    }
    // increase padding by size of text
    $this->{$pad_side} += $height + $this->graph_title_space;

    // the Text function will break it into lines
    return $svg_text->Text($this->graph_title, $font_size, $text);
  }


  /**
   * This should be overridden by subclass!
   */
  abstract protected function Draw();

  /**
   * Displays the background image
   */
  protected function BackgroundImage()
  {
    if(!$this->back_image)
      return '';
    $image = array(
      'width' => $this->back_image_width,
      'height' => $this->back_image_height,
      'x' => $this->back_image_left,
      'y' => $this->back_image_top,
      'xlink:href' => $this->back_image,
      'preserveAspectRatio' => 
        ($this->back_image_mode == 'stretch' ? 'none' : 'xMinYMin')
    );
    $style = array();
    if($this->back_image_opacity)
      $style['opacity'] = $this->back_image_opacity;

    $contents = '';
    if($this->back_image_mode == 'tile') {
      $image['x'] = 0; $image['y'] = 0;
      $im = $this->Element('image', $image, $style);
      $pattern = array(
        'id' => $this->NewID(),
        'width' => $this->back_image_width,
        'height' => $this->back_image_height,
        'x' => $this->back_image_left,
        'y' => $this->back_image_top,
        'patternUnits' => 'userSpaceOnUse'
      );
      // tiled image becomes a pattern to replace background colour
      $this->defs[] = $this->Element('pattern', $pattern, NULL, $im);
      $this->back_colour = "url(#{$pattern['id']})";
    } else {
      $im = $this->Element('image', $image, $style);
      $contents .= $im;
    }
    return $contents;
  }

  /**
   * Displays the background
   */
  protected function Canvas($id)
  {
    $bg = $this->BackgroundImage();
    $colour = $this->ParseColour($this->back_colour);
    $opacity = 1;
    if(strpos($colour, ':') !== FALSE)
      list($colour, $opacity) = explode(':', $colour);

    $canvas = array(
      'width' => '100%', 'height' => '100%',
      'fill' => $colour,
      'stroke-width' => 0
    );
    if($opacity < 1)
      if($opacity <= 0)
        $canvas['fill'] = 'none';
      else
        $canvas['opacity'] = $opacity;

    if($this->back_round)
      $canvas['rx'] = $canvas['ry'] = $this->back_round;
    if($bg == '' && $this->back_stroke_width) {
      $canvas['stroke-width'] = $this->back_stroke_width;
      $canvas['stroke'] = $this->back_stroke_colour;
    }
    $c_el = $this->Element('rect', $canvas);

    // create a clip path for rounded rectangle
    if($this->back_round)
      $this->defs[] = $this->Element('clipPath', array('id' => $id),
        NULL, $c_el);
    // if the background image is an element, insert it between the background
    // colour and border rect
    if($bg != '') {
      $c_el .= $bg;
      if($this->back_stroke_width) {
        $canvas['stroke-width'] = $this->back_stroke_width;
        $canvas['stroke'] = $this->back_stroke_colour;
        $canvas['fill'] = 'none';
        $c_el .= $this->Element('rect', $canvas);
      }
    }
    return $c_el;
  }

  /**
   * Displays readable (hopefully) error message
   */
  protected function ErrorText($error)
  {
    $text = array('x' => 3, 'y' => $this->height - 3);
    $style = array(
      'font-family' => 'Courier New',
      'font-size' => '11px',
      'font-weight' => 'bold',
    );
    
    $e = $this->ContrastText($text['x'], $text['y'], $error, 'blue',
      'white', $style);
    return $e;
  }

  /**
   * Displays high-contrast text
   */
  protected function ContrastText($x, $y, $text, $fcolour = 'black',
    $bcolour = 'white', $properties = NULL, $styles = NULL)
  {
    $props = array('transform' => 'translate(' . $x . ',' . $y . ')',
      'fill' => $fcolour);
    if(is_array($properties))
      $props = array_merge($properties, $props);

    $bg = $this->Element('text',
      array('stroke-width' => '2px', 'stroke' => $bcolour), NULL, $text);
    $fg = $this->Element('text', NULL, NULL, $text);
    return $this->Element('g', $props, $styles, $bg . $fg);
  }
 
  /**
   * Builds an element
   */
  public function Element($name, $attribs = NULL, $styles = NULL,
    $content = NULL, $no_whitespace = FALSE)
  {
    // these properties require units to work well
    $require_units = array('stroke-width' => 1, 'stroke-dashoffset' => 1,
      'font-size' => 1, 'baseline-shift' => 1, 'kerning' => 1, 
      'letter-spacing' =>1, 'word-spacing' => 1);

    if($this->namespace && strpos($name, ':') === FALSE)
      $name = 'svg:' . $name;
    $element = '<' . $name;
    if(is_array($attribs))
      foreach($attribs as $attr => $val) {

        // if units required, add px
        if(is_numeric($val)) {
          if(isset($require_units[$attr]))
            $val .= 'px';
        } else {
          $val = htmlspecialchars($val, ENT_COMPAT, $this->encoding);
        }
        $element .= ' ' . $attr . '="' . $val . '"';
      }

    if(is_array($styles)) {
      $element .= ' style="';
      foreach($styles as $attr => $val) {
        // check units again
        if(is_numeric($val)) {
          if(isset($require_units[$attr]))
            $val .= 'px';
        } else {
          $val = htmlspecialchars($val, ENT_COMPAT, $this->encoding);
        }
        $element .= $attr . ':' . $val . ';';
      }
      $element .= '"';
    }

    if(is_null($content))
      $element .= "/>";
    else
      $element .= '>' . $content . '</' . $name . ">";
    if(!$no_whitespace)
      $element .= "\n";

    return $element;
  }

  /**
   * Returns a link URL or NULL if none
   */
  protected function GetLinkURL($item, $key, $row = 0)
  {
    $link = is_null($item) ? null : $item->Data('link');
    if(is_null($link) && is_array($this->links[$row]) &&
      isset($this->links[$row][$key])) {
      $link = $this->links[$row][$key];
    }

    // check for absolute links
    if(!is_null($link) && strpos($link,'//') === FALSE)
      $link = $this->link_base . $link;

    return $link;
  }

  /**
   * Retrieves a link
   */
  protected function GetLink($item, $key, $content, $row = 0)
  {
    $link = $this->GetLinkURL($item, $key, $row);
    if(is_null($link))
      return $content;

    $link_attr = array('xlink:href' => $link, 'target' => $this->link_target);
    return $this->Element('a', $link_attr, NULL, $content);
  }

  /**
   * Returns TRUE if the item is visible on the graph
   */
  public function IsVisible($item, $dataset = 0)
  {
    // default implementation is for all non-zero values to be visible
    return ($item->value != 0);
  }

  /**
   * Sets up the colour class
   */
  protected function ColourSetup($count, $datasets = NULL)
  {
    $this->colours->Setup($count, $datasets);
  }

  /**
   * Returns a colour reference
   */
  protected function GetColour($item, $key, $dataset = NULL,
    $no_gradient = FALSE, $allow_pattern = FALSE)
  {
    $colour = 'none';
    $icolour = is_null($item) ? null : $item->Data('colour');
    if(!is_null($icolour)) {
      $colour = $icolour;
      $key = null; // don't reuse existing colours
    } else {
      $c = $this->colours->GetColour($key, $dataset);
      if(!is_null($c))
        $colour = $c;

      // make key reflect dataset as well (for gradients)
      if(!is_null($dataset))
        $key = "{$dataset}:{$key}";
    }
    return $this->ParseColour($colour, $key, $no_gradient, $allow_pattern);
  }

  /**
   * Converts a SVGGraph colour/gradient/pattern to a SVG attribute
   */
  public function ParseColour($colour, $key = NULL, $no_gradient = FALSE,
    $allow_pattern = FALSE, $radial_gradient = FALSE)
  {
    if(is_array($colour)) {
      if(!isset($colour['pattern']))
        $allow_pattern = FALSE;
      if(count($colour) < 2 || ($no_gradient && !$allow_pattern)) {
        $colour = $this->SolidColour($colour);
      } elseif(isset($colour['pattern'])) {
        $pattern_id = $this->AddPattern($colour);
        $colour = "url(#{$pattern_id})";
      } else {
        $err = array_diff_key($colour, array_keys(array_keys($colour)));
        if($err)
          throw new Exception('Malformed gradient/pattern');
        $gradient_id = $this->AddGradient($colour, $key, $radial_gradient);
        $colour = "url(#{$gradient_id})";
      }
    }
    return $colour;
  }

  /**
   * Returns the solid colour from a gradient
   */
  protected static function SolidColour($c)
  {
    if(is_array($c)) {
      // grab the first colour in the array, discarding opacity
      $c = $c[0];
      $colon = strpos($c, ':');
      if($colon)
        $c = substr($c, 0, $colon);
    }
    return $c;
  }

  /**
   * Returns the first non-empty option in named argument list.
   * Arguments must be "opt_name" or array("opt_name", $index), optionally
   * ending with default value (non-string or array('@', $value))
   */
  public function GetOption()
  {
    $opts = func_get_args();
    foreach($opts as $opt) {
      if(is_array($opt)) {
        // default value?
        if($opt[0] == '@')
          return $opt[1];

        // member in $opt[0]
        $val = $this->{$opt[0]};
        $i = is_numeric($opt[1]) ? $opt[1] : 0;
        if(is_array($val))
          $val = $val[$i % count($val)];
      } elseif(is_string($opt)) {
        $val = $this->{$opt};
      } else {
        // not string or array, default value
        return $opt;
      }

      // most values are acceptable
      if($val === FALSE || $val === 0.0 || $val === 0 || !empty($val))
        return $val;
    }
  }

  /**
   * Checks that the data are valid
   */
  protected function CheckValues()
  {
    if($this->values->error)
      throw new Exception($this->values->error);
  }

  /**
   * Sets the stroke options for an element
   */
  protected function SetStroke(&$attr, &$item, $set = 0, $line_join = null)
  {
    $stroke_width = $this->GetFromItemOrMember('stroke_width', $set, $item);
    if($stroke_width > 0) {
      $attr['stroke'] = $this->GetFromItemOrMember('stroke_colour', $set, $item);
      $attr['stroke-width'] = $stroke_width;
      if(!is_null($line_join))
        $attr['stroke-linejoin'] = $line_join;
      else
        unset($attr['stroke-linejoin']);

      $dash = $this->GetFromItemOrMember('stroke_dash', $set, $item);
      if(!empty($dash))
        $attr['stroke-dasharray'] = $dash;
      else
        unset($attr['stroke-dasharray']);
    } else {
      unset($attr['stroke'], $attr['stroke-width'], $attr['stroke-linejoin'],
        $attr['stroke-dasharray']);
    }
  }

  /**
   * Creates a new ID for an element
   */
  public function NewID()
  {
    return $this->id_prefix . 'e' . base_convert(++Graph::$last_id, 10, 36);
  }

  /**
   * Adds to the defs section of the document
   */
  public function AddDefs($def)
  {
    $this->defs[] = $def;
  }

  /**
   * Adds markup to be inserted between graph and legend
   */
  public function AddBackMatter($fragment)
  {
    $this->back_matter .= $fragment;
  }

  /**
   * Adds a pattern, returning the element ID
   */
  public function AddPattern($pattern)
  {
    if(is_null($this->pattern_list)) {
      require_once 'SVGGraphPattern.php';
      $this->pattern_list = new SVGGraphPatternList($this);
    }
    return $this->pattern_list->Add($pattern);
  }

  /**
   * Adds a gradient to the list, returning the element ID for use in url
   */
  public function AddGradient($colours, $key = null, $radial = FALSE)
  {
    if(is_null($key) || !isset($this->gradients[$key])) {

      if($radial) {
        // if this is a radial gradient, it must end with 'r'
        $last = count($colours) - 1;
        if(strlen($colours[$last]) == 1)
          $colours[$last] = 'r';
        else
          $colours[] = 'r';
      }

      // find out if this gradient already stored
      $hash = serialize($colours);
      if(isset($this->gradient_map[$hash]))
        return $this->gradient_map[$hash];

      $id = $this->NewID();
      if(is_null($key))
        $key = $id;
      $this->gradients[$key] = array(
        'id' => $id,
        'colours' => $colours
      );
      $this->gradient_map[$hash] = $id;
      return $id;
    }
    return $this->gradients[$key]['id'];
  }

  /**
   * Creates a linear gradient element
   */
  private function MakeLinearGradient($key)
  {
    $stops = '';
    $direction = 'v';
    $type = 'linearGradient';
    $colours = $this->gradients[$key]['colours'];
    $id = $this->gradients[$key]['id'];

    if(in_array($colours[count($colours)-1], array('h','v','r')))
      $direction = array_pop($colours);
    if($direction == 'r')
    {
      $type = 'radialGradient';
      $gradient = array('id' => $id);
    }
    else
    {
      $x2 = $direction == 'v' ? 0 : '100%';
      $y2 = $direction == 'h' ? 0 : '100%';
      $gradient = array('id' => $id, 'x1' => 0, 'x2' => $x2,
        'y1' => 0, 'y2' => $y2);
    }

    $col_mul = 100 / (count($colours) - 1);
    $offset = 0;
    foreach($colours as $pos => $colour) {
      $opacity = null;
      $poffset = $pos * $col_mul;
      if(strpos($colour, ':') !== FALSE) {
        // opacity, stop offset or both
        $parts = explode(':', $colour);
        if(is_numeric($parts[0])) {
          $poffset = array_shift($parts);
        }
        $colour = array_shift($parts);
        $opacity = array_shift($parts); // NULL if not set
      }
      // set the offset to the most meaningful number
      $offset = min(100, max(0, $offset, $poffset));
      $stop = array(
        'offset' => $offset . '%',
        'stop-color' => $colour
      );
      if(is_numeric($opacity))
        $stop['stop-opacity'] = $opacity;
      $stops .= $this->Element('stop', $stop);
    }

    return $this->Element($type, $gradient, NULL, $stops);
  }

  /**
   * Adds context menu for item
   */
  protected function SetContextMenu(&$element, $dataset, &$item,
    $duplicate = FALSE)
  {
    $menu = NULL;
    if(is_callable($this->context_callback)) {
      $menu = call_user_func($this->context_callback, $dataset, $item->key,
        $item->value);
    } elseif(is_array($this->structure) &&
      isset($this->structure['context_menu'])) {
      $menu = $item->Data('context_menu');
    }
    $this->javascript->SetContextMenu($element, $menu, $duplicate);

    if(!isset($this->root_menu)) {

      $global = $this->GetOption('context_global');
      if($global === FALSE) {
        $this->root_menu = TRUE;
        return;
      }

      if(is_null($global))
        $global = array(array(SVGGRAPH_VERSION, NULL));

      $entries = '';
      foreach($global as $entry) {
        $entries .= '<svggraph:menuitem name="';
        $entries .= htmlspecialchars($entry[0], ENT_COMPAT, $this->encoding);
        if(!is_null($entry[1])) {
          $entries .= '" link="';
          $entries .= htmlspecialchars($entry[1], ENT_COMPAT, $this->encoding);
        }
        $entries .= '"/>' . "\n";
      }
      $xml = <<<XML
<svggraph:data xmlns:svggraph="http://www.goat1000.com/svggraph">
<svggraph:menu>
{$entries}</svggraph:menu>
</svggraph:data>
XML;
      $this->AddDefs($xml);
      $this->root_menu = TRUE;
    }
  }

  /**
   * Default tooltip contents are key and value, or whatever
   * $key is if $value is not set
   */
  protected function SetTooltip(&$element, &$item, $dataset, $key, $value = NULL,
    $duplicate = FALSE)
  {
    if(is_callable($this->tooltip_callback)) {
      if(is_null($value))
        $value = $key;
      $text = call_user_func($this->tooltip_callback, $dataset, $key, $value);
    } elseif(is_array($this->structure) && isset($this->structure['tooltip'])) {
      // use structured data tooltips if specified
      $text = $item->Data('tooltip');
    } else {
      $text = $this->FormatTooltip($item, $dataset, $key, $value);
    }
    if(is_null($text))
      return;
    $text = addslashes(str_replace("\n", '\n', $text));
    return $this->javascript->SetTooltip($element, $text, $duplicate);
  }

  /**
   * Default format is value only
   */
  protected function FormatTooltip(&$item, $dataset, $key, $value)
  {
    return $this->units_before_tooltip . Graph::NumString($value) .
      $this->units_tooltip;
  }

  /**
   * Adds a data label to the list
   */
  protected function AddDataLabel($dataset, $index, &$element, &$item,
    $x, $y, $w, $h, $content = NULL, $duplicate = TRUE)
  {
    if(!$this->GetOption(array('show_data_labels', $dataset)))
      return false;

    // set up fading for this label?
    $id = NULL;
    $fade_in = $this->GetOption(array('data_label_fade_in_speed', $dataset));
    $fade_out = $this->GetOption(array('data_label_fade_out_speed', $dataset));
    $click = $this->GetOption(array('data_label_click', $dataset));
    $popup = $this->GetOption(array('data_label_popfront', $dataset));
    if($click == 'hide' || $click == 'show') {
      $id = $this->NewID();
      $this->javascript->SetClickShow($element, $id, $click == 'hide',
        $duplicate && !$this->compat_events);
    }
    if($popup) {
      if(!$id)
        $id = $this->NewID();
      $this->javascript->SetPopFront($element, $id,
        $duplicate && !$this->compat_events);
    }
    if($fade_in || $fade_out) {
      $speed_in = $fade_in ? $fade_in / 100 : 0;
      $speed_out = $fade_out ? $fade_out / 100 : 0;
      if(!$id)
        $id = $this->NewID();
      $this->javascript->SetFader($element, $speed_in, $speed_out, $id,
        $duplicate && !$this->compat_events);
    }
    $this->data_labels->AddLabel($dataset, $index, $item, $x, $y, $w, $h, $id,
      $content, $fade_in, $click);
    return true;
  }

  /**
   * Adds an element as a client of existing label
   */
  protected function AddLabelClient($dataset, $index, &$element)
  {
    $label = $this->data_labels->GetLabel($dataset, $index);
    if(is_null($label))
      return false;

    $id = $label['id'];
    $fade_in = $this->GetOption(array('data_label_fade_in_speed', $dataset));
    $fade_out = $this->GetOption(array('data_label_fade_out_speed', $dataset));
    $click = $this->GetOption(array('data_label_click', $dataset));
    $popup = $this->GetOption(array('data_label_popfront', $dataset));
    if($click == 'hide' || $click == 'show')
      $this->javascript->SetClickShow($element, $id, $click == 'hide',
        !$this->compat_events);
    if($popup)
      $this->javascript->SetPopFront($element, $id, !$this->compat_events);
    if($fade_in || $fade_out) {
      $speed_in = $fade_in ? $fade_in / 100 : 0;
      $speed_out = $fade_out ? $fade_out / 100 : 0;
      $this->javascript->SetFader($element, $speed_in, $speed_out, $id,
        !$this->compat_events);
    }
  }

  /**
   * Adds a label for non-data text
   */
  protected function AddContentLabel($dataset, $index, $x, $y, $w, $h, $content)
  {
    $this->data_labels->AddContentLabel($dataset, $index, $x, $y, $w, $h,
      $content);
    return true;
  }

  /**
   * Draws the data labels
   */
  protected function DrawDataLabels()
  {
    if(isset($this->settings['label']))
      $this->data_labels->Load($this->settings);
    return $this->data_labels->GetLabels();
  }

  /**
   * Returns the position for a data label
   */
  public function DataLabelPosition($dataset, $index, &$item, $x, $y, $w, $h,
    $label_w, $label_h)
  {
    $pos = $this->GetOption(array('data_label_position', $dataset));
    if(empty($pos))
      $pos = 'above';
    $end = array($x + $w * 0.5, $y + $h * 0.5);
    return array($pos, $end);
  }


  public function LoadShapes()
  {
    include_once 'SVGGraphShape.php';
    $this->shapes = new SVGGraphShapeList($this);

    $this->shapes->Load($this->settings);
  }

  public function UnderShapes()
  {
    if(!isset($this->shapes) && isset($this->settings['shape'])) {
      $this->LoadShapes();
    }
    return isset($this->shapes) ? $this->shapes->Draw(SVGG_SHAPE_BELOW) : '';
  }

  public function OverShapes()
  {
    return isset($this->shapes) ? $this->shapes->Draw(SVGG_SHAPE_ABOVE) : '';
  }

  /**
   * Returns TRUE if the position is inside the item
   */
  public static function IsPositionInside($pos)
  {
    list($hpos, $vpos) = Graph::TranslatePosition($pos);
    return strpos($hpos . $vpos, 'o') === FALSE;
  }

  /**
   * Sets the styles for data labels
   */
  public function DataLabelStyle($dataset, $index, &$item)
  {
    $map = $this->data_labels->GetStyleMap();
    $style = array();
    foreach($map as $key => $option) {
      $style[$key] = $this->GetOption(array($option, $dataset));
    }

    // padding x/y options override single value
    $style['pad_x'] = $this->GetOption(array('data_label_padding_x', $dataset),
        array('data_label_padding', $dataset));
    $style['pad_y'] = $this->GetOption(array('data_label_padding_y', $dataset),
        array('data_label_padding', $dataset));
    return $style;
  }

  /**
   * Tail direction is required for some types of label
   */
  public function DataLabelTailDirection($dataset, $index, $hpos, $vpos)
  {
    // angle starts at right, goes clockwise
    $angle = 90;
    $pos = str_replace(array('i','o','m'), '', $vpos) .
      str_replace(array('i','o','c'), '', $hpos);
    switch($pos) {
      case 'l' : $angle = 0; break;
      case 'tl' : $angle = 45; break;
      case 't' : $angle = 90; break;
      case 'tr' : $angle = 135; break;
      case 'r' : $angle = 180; break;
      case 'br' : $angle = 225; break; 
      case 'b' : $angle = 270; break;
      case 'bl' : $angle = 315; break;
    }
    return $angle; 
  }

  /**
   * Builds and returns the body of the graph
   */
  private function BuildGraph()
  {
    $this->CheckValues($this->values);

    // body content comes from the subclass
    return $this->DrawGraph();
  }

  /**
   * Returns the SVG document
   */
  public function Fetch($header = TRUE, $defer_javascript = TRUE)
  {
    $content = '';
    if($header) {
      $content .= '<?xml version="1.0"';
      // encoding comes before standalone
      if(strlen($this->encoding) > 0)
        $content .= " encoding=\"{$this->encoding}\"";
      // '>' is with \n so as not to confuse syntax highlighting
      $content .= " standalone=\"no\"?" . ">\n";
      if($this->doctype)
        $content .= '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" ' .
        '"http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">' . "\n";
    }

    // set the precision - PHP default is 14 digits!
    Graph::$precision = $this->settings['precision'];
    $old_precision = ini_set('precision', Graph::$precision);
    // set decimal and thousands for NumString
    Graph::SetNumStringOptions($this->settings['decimal'],
      $this->settings['thousands']);

    // display title and description if available
    $heading = '';
    if($this->title)
      $heading .= $this->Element('title', NULL, NULL, $this->title);
    if($this->description)
      $heading .= $this->Element('desc', NULL, NULL, $this->description);

    if($this->exception_throw) {
      $body = $this->BuildGraph();
    } else {
      try {
        $body = $this->BuildGraph();
      } catch(Exception $e) {
        $err = $e->getMessage();
        if($this->exception_details)
          $err .= " [" . basename($e->getFile()) . ' #' . $e->getLine() . ']';
        $body = $this->ErrorText($err);
      }
    }

    $svg = array(
      'width' => $this->width, 'height' => $this->height,
      'version' => '1.1', 
      'xmlns:xlink' => 'http://www.w3.org/1999/xlink'
    );
    if($this->auto_fit) {
      $svg['viewBox'] = "0 0 {$this->width} {$this->height}";
      $svg['width'] = $svg['height'] = '100%';
    }
    if($this->svg_class)
      $svg['class'] = $this->svg_class;

    if(!$defer_javascript) {
      $js = $this->FetchJavascript();
      if($js != '') {
        $heading .= $js;
        $onload = Graph::$javascript->GetOnload();
        if($onload != '')
          $svg['onload'] = $onload;
      }
    }

    // insert any gradients that are used
    foreach($this->gradients as $key => $gradient)
      $this->defs[] = $this->MakeLinearGradient($key);
    // and any patterns
    if(!is_null($this->pattern_list))
      $this->pattern_list->MakePatterns($this->defs);

    // show defs and body content
    if(count($this->defs))
      $heading .= $this->Element('defs', NULL, NULL, implode('', $this->defs));
    if($this->namespace)
      $svg['xmlns:svg'] = "http://www.w3.org/2000/svg";
    else
      $svg['xmlns'] = "http://www.w3.org/2000/svg";

    // add any extra namespaces
    foreach($this->namespaces as $ns => $url)
      $svg['xmlns:' . $ns] = $url;

    // display version string
    if($this->show_version) {
      $text = array('x' => $this->pad_left, 'y' => $this->height - 3);
      $style = array(
        'font-family' => 'Courier New', 'font-size' => '12px',
        'font-weight' => 'bold',
      );
      $body .= $this->ContrastText($text['x'], $text['y'], SVGGRAPH_VERSION,
        'blue', 'white', $style);
    }

    $content .= $this->Element('svg', $svg, NULL, $heading . $body);
    // replace PHP's precision
    ini_set('precision', $old_precision);

    if($this->minify)
      $content = preg_replace('/\>\s+\</', '><', $content);
    return $content;
  }

  /**
   * Renders the SVG document
   */
  public function Render($header = TRUE, $content_type = TRUE, 
    $defer_javascript = FALSE)
  {
    $mime_header = 'Content-type: image/svg+xml; charset=UTF-8';
    if($content_type)
      header($mime_header);
    if($this->exception_throw) {
      echo $this->Fetch($header, $defer_javascript);
    } else {
      try {
        echo $this->Fetch($header, $defer_javascript);
      } catch(Exception $e) {
        $this->ErrorText($e);
      }
    }
  }

  /**
   * When using the defer_javascript option, this returns the
   * Javascript block
   */
  public function FetchJavascript($onload_immediate = TRUE, $cdata_wrap = TRUE,
    $no_namespace = TRUE)
  {
    $js = '';
    if(isset(Graph::$javascript)) {
      $variables = Graph::$javascript->GetVariables();
      $functions = Graph::$javascript->GetFunctions();
      $onload = Graph::$javascript->GetOnload();

      if($variables != '' || $functions != '') {
        if($onload_immediate)
          $functions .= "\n" . "setTimeout(function(){{$onload}},20);";
        $script_attr = array('type' => 'application/ecmascript');
          $script = "$variables\n$functions\n";
        if(is_callable($this->minify_js))
          $script = call_user_func($this->minify_js, $script);
        if($cdata_wrap)
          $script = "// <![CDATA[\n$script\n// ]]>";
        $namespace = $this->namespace;
        if($no_namespace)
          $this->namespace = false;
        $js = $this->Element('script', $script_attr, NULL, $script);
        if($no_namespace)
          $this->namespace = $namespace;
      }
    }
    return $js;
  }

  /**
   * Returns a value from the $item, or the member % set
   */
  protected function GetFromItemOrMember($member, $set, &$item, $ikey = null)
  {
    $value = is_null($item) ? null : $item->Data(is_null($ikey) ? $member : $ikey);
    if(is_null($value))
      $value = is_array($this->{$member}) ?
        $this->{$member}[$set % count($this->{$member})] :
        $this->{$member};
    return $value;
  }

  /**
   * Converts number to string
   */
  public static function NumString($n, $decimals = null, $precision = null)
  {
    if(is_int($n)) {
      $d = is_null($decimals) ? 0 : $decimals;
    } else {

      if(is_null($precision))
        $precision = Graph::$precision;

      // if there are too many zeroes before other digits, round to 0
      $e = floor(log(abs($n), 10));
      if(-$e > $precision)
        $n = 0;

      // subtract number of digits before decimal point from precision
      // for precision-based decimals
      $d = is_null($decimals) ? $precision - ($e > 0 ? $e : 0) : $decimals;
    }
    $s = number_format($n, $d, Graph::$decimal, Graph::$thousands);

    if(is_null($decimals) && $d && strpos($s, Graph::$decimal) !== false) {
      list($a, $b) = explode(Graph::$decimal, $s);
      $b1 = rtrim($b, '0');
      if($b1 != '')
        return $a . Graph::$decimal . $b1;
      return $a;
    }
    return $s;
  }

  /**
   * Sets the number format characters
   *
   * @throws LogicException if $decimal and $thousands parameters are equal.
   */
  public static function SetNumStringOptions($decimal, $thousands)
  {
    if ($decimal === $thousands) {
      throw new LogicException(sprintf('Decimal and thousand separator must not be equal. Please use different settings for "thousands" and "decimal".'));
    }
    Graph::$decimal = $decimal;
    Graph::$thousands = $thousands;
  }

  /**
   * Returns the minimum value in the array, ignoring NULLs
   */
  public static function min(&$a)
  {
    $min = null;
    foreach($a as $v) {
      if(!is_null($v) && (is_null($min) || $v < $min))
        $min = $v;
    }
    return $min;
  }
}


/**
 * Converts a string key to a unix timestamp, or NULL if invalid
 */
function SVGGraphDateConvert($k)
{
  // if the format is set, try it
  if(!is_null(Graph::$key_format)) {
    $dt = date_create_from_format(Graph::$key_format, $k);

    // if the specified format fails, try default format
    if($dt === FALSE)
      $dt = date_create($k);
  } else {
    $dt = date_create($k);
  }
  if($dt === FALSE)
    return NULL;
  // this works in 64-bit on 32-bit systems, getTimestamp() doesn't
  return $dt->format('U');
}

