<?php
/**
 * Copyright (C) 2019-2021 Graham Breach
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
 * Base class for all graph types
 */
abstract class Graph {

  public $subgraph = false;
  protected $width = 0;
  protected $height = 0;
  protected $settings = [];
  protected $values = [];
  protected $namespace = false;
  protected $link_base = '';
  protected $link_target = '_blank';
  protected $links = [];

  protected $colours = null;
  public $defs = null;
  protected $subgraphs = [];
  protected $back_matter = '';

  protected $namespaces = [];
  protected static $javascript = null;
  private static $last_id = 0;
  public static $key_format = null;
  protected $legend = null;
  protected $data_label_style_cache = [];

  /**
   * @arg $w = width
   * @arg $h = height
   * @arg $settings = user options
   * @arg $fixed_settings = class options overriding user options
   */
  public function __construct($w, $h, array $settings, array $fixed_settings = [])
  {
    $this->width = $w;
    $this->height = $h;
    $this->defs = new Defs($this);

    // get settings from ini file that are relevant to this class
    $class = get_class($this);
    $ini_settings = $this->ini_settings($class);

    // default option overrides - subclasses can override these
    $fixed_setting_defaults = [
      'repeated_keys' => 'error',
      'sort_keys' => true,
      'require_structured' => false,
      'require_integer_keys' => true,
    ];
    $this->settings = array_merge($this->settings, $ini_settings, $settings,
      $fixed_setting_defaults, $fixed_settings);
    $this->namespace = $this->getOption('namespace');
    $this->link_base = $this->getOption('link_base');
    $this->link_target = $this->getOption('link_target');
  }

  /**
   * Retrieves properties from the settings array if they are not already
   * available as properties, also sets up properties that must be created.
   */
  public function __get($name)
  {
    switch($name) {
    case 'javascript':
      // $this->javascript will forward to the static Graph::$javascript
      if(!isset(Graph::$javascript))
        Graph::$javascript = new Javascript($this);
      return Graph::$javascript;
    case 'data_labels':
      $this->data_labels = new DataLabels($this);
      return $this->data_labels;
    case 'shapes':
      $this->shapes = new ShapeList($this);
      $this->shapes->load($this->settings);
      return $this->shapes;
    case 'figures':
      if(!isset($this->settings['figure']))
        throw new \Exception('No figures defined');
      $this->figures = new Figures($this);
      $this->figures->load($this->settings);
      return $this->figures;
    case 'context_menu':
      $this->context_menu = new ContextMenu($this, $this->javascript);
      return $this->context_menu;
    }
    if(isset($this->settings[$name]))
      return $this->settings[$name];
    return null;
  }

  /**
   * Make empty($this->option) more robust
   */
  public function __isset($name)
  {
    return isset($this->settings[$name]);
  }

  public function __set($name, $value)
  {
    /**
     * When everything is converted to getOption/setOption, this function
     * should be redundant
     */
    // trigger_error('Attempt to set $this->' . $name, E_USER_WARNING);
    $this->settings[$name] = $value;
    $this->{$name} = $value;
  }

  /**
   * Returns the settings from the ini file for a class
   */
  protected function ini_settings($class)
  {
    $ini_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'svggraph.ini';
    $ini_settings = false;
    if(file_exists($ini_file))
      $ini_settings = parse_ini_file($ini_file, true);
    if($ini_settings === false)
      throw new \Exception('INI file [' . $ini_file . '] could not be loaded');

    $hierarchy = [$class];
    while($class = get_parent_class($class))
      array_unshift($hierarchy, $class);

    $settings = [];
    while(count($hierarchy)) {
      $class = array_shift($hierarchy);
      $ns = strrpos($class, '\\');
      $class = substr($class, $ns + 1);
      if(array_key_exists($class, $ini_settings))
        $settings = array_merge($settings, $ini_settings[$class]);
    }

    return $settings;
  }

  /**
   * Sets the graph values
   */
  public function values($values)
  {
    $new_values = [];
    $v = func_get_args();
    if(count($v) == 1)
      $v = array_shift($v);

    $set_values = true;
    if(is_array($v)) {
      reset($v);
      $first_key = key($v);
      if($first_key !== null && is_array($v[$first_key])) {
        foreach($v as $data_set)
          $new_values[] = $data_set;
        $set_values = false;
      }
    }

    if($set_values)
      $new_values[] = $v;

    $require_structured = $this->getOption('require_structured');
    $structured_data = $this->getOption('structured_data');
    $structure = $this->getOption('structure');
    $datetime_keys = $this->getOption('datetime_keys');
    $datetime_key_format = $this->getOption('datetime_key_format');
    $force_assoc = $this->getOption('force_assoc');

    if($this->getOption('scatter_2d')) {
      $this->setOption('scatter_2d', false);
      if(empty($structure)) {
        $structure = ['key' => 0, 'value' => 1, 'datasets' => true];
        $this->setOption('structure', $structure);
      }
    }

    if($datetime_keys && $datetime_key_format)
      Graph::$key_format = $datetime_key_format;

    if($structured_data || is_array($structure)) {
      $this->setOption('structured_data', true);
      $this->values = new StructuredData($new_values, $force_assoc,
        $datetime_keys, $structure,
        $this->getOption('repeated_keys'), $this->getOption('sort_keys'),
        $this->getOption('require_integer_keys'), $require_structured);
    } else {
      $this->values = new Data($new_values, $force_assoc, $datetime_keys);
      if(!$this->values->error && !empty($require_structured))
        $this->values->error = get_class($this) . ' requires structured data';
    }

    if($this->values->error)
      return;

    $dataset = $this->getOption('dataset', 0);
    if($dataset === 0)
      return;

    $dcount = count($this->values);
    $dataset = $this->getOption(['dataset', 0], 0);
    if($dcount <= $dataset) {
      $this->values->error = 'No valid datasets selected';
      return;
    }

    // dataset option doesn't work well without structured data
    $this->values = StructuredData::convertFrom($this->values, $force_assoc,
      $datetime_keys, $this->getOption('require_integer_keys'));
  }

  /**
   * Sets the links from each item
   */
  public function links()
  {
    $this->links = func_get_args();
  }

  /**
   * Assigns the list of subgraphs
   */
  public function subgraphs($subgraphs)
  {
    $this->subgraphs = $subgraphs;
  }

  /**
   * Set up the colours
   */
  public function colours(Colours $colours)
  {
    $this->colours = $colours;
  }

  public function getMinValue()
  {
    $d = $this->getOption(['dataset', 0], 0);
    return $this->values->getMinValue($d);
  }
  public function getMaxValue()
  {
    $d = $this->getOption(['dataset', 0], 0);
    return $this->values->getMaxValue($d);
  }
  public function getMinKey()
  {
    $d = $this->getOption(['dataset', 0], 0);
    return $this->values->getMinKey($d);
  }
  public function getMaxKey()
  {
    $d = $this->getOption(['dataset', 0], 0);
    return $this->values->getMaxKey($d);
  }
  public function getKey($i)
  {
    return $this->values->getKey($i);
  }

  /**
   * Sets up the colours used for the graph
   */
  protected function setup()
  {
    $dataset = $this->getOption(['dataset', 0], 0);
    $this->colourSetup($this->values->itemsCount($dataset));
  }

  /**
   * Draws the selected graph
   */
  public function drawGraph()
  {
    $canvas_id = $this->newID();
    $this->initLegend();
    $this->setup();

    $contents = $this->canvas($canvas_id);
    $contents .= $this->drawTitle();
    $contents .= $this->draw();
    $contents .= $this->drawDataLabels();
    $contents .= $this->drawBackMatter();
    $contents .= $this->drawLegend();

    foreach($this->subgraphs as $subgraph)
      $contents .= $subgraph->fetch($this);

    // magnifying means everything must be in a group for transformation
    if(!$this->subgraph && $this->getOption('magnify')) {
      $this->javascript->magnifier();
      $group = ['class' => 'svggraph-magnifier'];
      $contents = $this->element('g', $group, null, $contents);
    }

    // rounded rects might need a clip path
    if($this->getOption('back_round') && $this->getOption('back_round_clip')) {
      $group = ['clip-path' => 'url(#' . $canvas_id . ')'];
      return $this->element('g', $group, null, $contents);
    }
    return $contents;
  }

  /**
   * Adds any markup that goes after the graph
   */
  protected function drawBackMatter()
  {
    return $this->back_matter;
  }

  /**
   * Sets up the legend class
   */
  protected function initLegend()
  {
    $this->legend = null;

    // see if the legend is needed
    if(!$this->getOption('show_legend'))
      return;

    $entries = $this->getOption('legend_entries');
    $structure = $this->getOption('structure');
    if(empty($entries) && !isset($structure['legend_text']))
      return;

    $this->legend = new Legend($this);
  }

  /**
   * Returns the ordering for legend entries
   */
  public function getLegendOrder()
  {
    // null for no special order
    return null;
  }

  /**
   * Draws the legend
   */
  protected function drawLegend()
  {
    if($this->legend === null)
      return '';
    return $this->legend->draw();
  }

  /**
   * Parses a position string, returning x and y coordinates
   */
  public function parsePosition($pos, $w = 0, $h = 0, $pad = 0)
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
    $pos = 'top left ' . str_replace('outer', 'inner', $pos);
    return Graph::relativePosition($pos, $t, $l, $b, $r, $w, $h, $pad);
  }

  /**
   * Returns [hpos,vpos,offset_x,offset_y] positions derived from full
   * position string
   */
  public static function translatePosition($pos)
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
    return [$hpos, $vpos, $offset_x, $offset_y];
  }

  /**
   * Returns [x,y,text-anchor,hpos,vpos] position that is $pos relative to the
   * top, left, bottom and right.
   * When $text is true, x and y are adjusted for text-anchor position
   */
  public static function relativePosition($pos, $top, $left,
    $bottom, $right, $width, $height, $pad, $text = false)
  {
    list($hpos, $vpos, $offset_x, $offset_y) = Graph::translatePosition($pos);

    // if the containers have no thickness, position outside
    $translate = ['l' => 'ol', 'r' => 'or', 't' => 'ot', 'b' => 'ob'];
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
    $align_map = [
      'ol' => 'end', 'l' => 'start', 'c' => 'middle',
      'r' => 'end', 'or' => 'start'
    ];
    $text_align = $align_map[$hpos];

    // in text mode, adjust X for text alignment
    if($text && $hpos != 'l' && $hpos != 'or') {
      if($hpos == 'c')
        $x += $width / 2;
      else
        $x += $width;
    }
    return [$x, $y, $text_align, $hpos, $vpos];
  }

  /**
   * Sets the style info for the legend
   */
  protected function setLegendEntry($dataset, $index, $item, $style_info)
  {
    if($this->legend === null)
      return;
    $this->legend->setEntry($dataset, $index, $item, $style_info);
  }

  /**
   * Subclasses must draw the entry, if they can
   */
  protected function drawLegendEntry($x, $y, $w, $h, $entry)
  {
    return '';
  }

  /**
   * Draws the graph title, if there is one
   */
  protected function drawTitle()
  {
    $title = $this->getOption('graph_title');
    $title_font = $this->getOption('graph_title_font');

    $svg_text = new Text($this, $title_font);
    if($svg_text->strlen($title) <= 0)
      return '';

    $title_font_size = $this->getOption('graph_title_font_size');
    $title_weight = $this->getOption('graph_title_font_weight');
    $title_colour = $this->getOption('graph_title_colour');
    $title_pos = $this->getOption('graph_title_position');
    $title_space = $this->getOption('graph_title_space');
    $title_line_spacing = $this->getOption('graph_title_line_spacing');
    if($title_line_spacing === null || $title_line_spacing < 1)
      $title_line_spacing = $title_font_size;

    if($title_pos != 'bottom' && $title_pos != 'left' && $title_pos != 'right')
      $title_pos = 'top';
    $pad_side = 'pad_' . $title_pos;
    list($width, $height) = $svg_text->measure($title, $title_font_size,
      0, $title_line_spacing);
    $baseline = $svg_text->baseline($title_font_size);
    $text = [
      'font-size' => $title_font_size,
      'font-family' => $title_font,
      'font-weight' => $title_weight,
      'text-anchor' => 'middle',
      'fill' => new Colour($this, $title_colour),
    ];

    // ensure outside padding is at least the title space
    if($this->{$pad_side} < $title_space)
      $this->{$pad_side} = $title_space;

    $xform = new Transform;
    if($title_pos == 'left') {
      $text['x'] = $this->pad_left + $baseline;
      $text['y'] = $this->height / 2;
      $xform->rotate(270, $text['x'], $text['y']);
      $text['transform'] = $xform;
    } elseif($title_pos == 'right') {
      $text['x'] = $this->width - $this->pad_right - $baseline;
      $text['y'] = $this->height / 2;
      $xform->rotate(90, $text['x'], $text['y']);
      $text['transform'] = $xform;
    } elseif($title_pos == 'bottom') {
      $text['x'] = $this->width / 2;
      $text['y'] = $this->height - $this->pad_bottom - $height + $baseline;
    } else {
      $text['x'] = $this->width / 2;
      $text['y'] = $this->pad_top + $baseline;
    }
    // increase padding by size of text
    $this->{$pad_side} += $height + $title_space;

    // now deal with sub-title
    $subtitle = $this->getOption('graph_subtitle');
    $subtitle_font = $this->getOption('graph_subtitle_font', 'graph_title_font');
    $svg_subtext = new Text($this, $subtitle_font);

    $subtitle_text = '';
    if($svg_subtext->strlen($subtitle) > 0) {

      $subtitle_font_size = $this->getOption('graph_subtitle_font_size',
        'graph_title_font_size');
      $subtitle_weight = $this->getOption('graph_subtitle_font_weight',
        'graph_title_font_weight');
      $subtitle_colour = $this->getOption('graph_subtitle_colour',
        'graph_title_colour');
      $subtitle_space = $this->getOption('graph_subtitle_space',
        'graph_title_space');
      $subtitle_line_spacing = $this->getOption('graph_subtitle_line_spacing');
      if($subtitle_line_spacing === null || $subtitle_line_spacing < 1)
        $subtitle_line_spacing = $subtitle_font_size;

      list($swidth, $sheight) = $svg_subtext->measure($subtitle,
        $subtitle_font_size, 0, $subtitle_line_spacing);
      $sbaseline = $svg_text->baseline($subtitle_font_size);
      $stext = [
        'font-size' => $subtitle_font_size,
        'font-family' => $subtitle_font,
        'font-weight' => $subtitle_weight,
        'text-anchor' => 'middle',
        'fill' => new Colour($this, $subtitle_colour),
      ];

      $sxform = new Transform;
      $sub_offset = $sbaseline + $subtitle_space - $title_space;
      if($title_pos == 'left') {
        $stext['x'] = $this->pad_left + $sub_offset;
        $stext['y'] = $text['y'];
        $sxform->rotate(270, $stext['x'], $stext['y']);
        $stext['transform'] = $sxform;
      } elseif($title_pos == 'right') {
        $stext['x'] = $this->width - $this->pad_right - $sub_offset;
        $stext['y'] = $text['y'];
        $sxform->rotate(90, $stext['x'], $stext['y']);
        $stext['transform'] = $sxform;
      } elseif($title_pos == 'bottom') {

        // complicates things - need to shift title up
        $stext['x'] = $text['x'];
        $stext['y'] = $text['y'] - $baseline + $height + $sbaseline - $sheight;
        $text['y'] -= $sheight + $subtitle_space;
      } else {
        $stext['x'] = $text['x'];
        $stext['y'] = $this->pad_top + $sub_offset;
      }

      $this->{$pad_side} += $sheight + $subtitle_space;
      $subtitle_text = $svg_subtext->text($subtitle, $subtitle_line_spacing, $stext);
    }

    // the Text function will break it into lines
    $title_text = $svg_text->text($title, $title_line_spacing, $text);

    return $title_text . $subtitle_text;
  }

  /**
   * This should be overridden by subclass!
   */
  abstract protected function draw();

  /**
   * Displays the background image
   */
  protected function backgroundImage($shadow, $clip_path)
  {
    $image_src = $this->getOption('back_image');
    if(!$image_src)
      return '';

    $width = $this->getOption('back_image_width');
    $height = $this->getOption('back_image_height');
    $left = $this->getOption('back_image_left');
    $top = $this->getOption('back_image_top');

    if($shadow) {
      if($width === '100%')
        $width = new Number($this->width - $shadow);
      if($height === '100%')
        $height = new Number($this->height - $shadow);
    }

    $image = [
      'width' => $width, 'height' => $height,
      'x' => $left, 'y' => $top,
      'xlink:href' => $image_src,
      'preserveAspectRatio' =>
        ($this->back_image_mode == 'stretch' ? 'none' : 'xMinYMin')
    ];

    if($clip_path !== null)
      $image['clip-path'] = 'url(#' . $clip_path . ')';

    $style = [];
    if($this->back_image_opacity)
      $style['opacity'] = $this->back_image_opacity;

    $contents = '';
    if($this->back_image_mode == 'tile') {
      $image['x'] = 0; $image['y'] = 0;
      $im = $this->element('image', $image, $style);
      $pattern = [
        'id' => $this->newID(),
        'width' => $width, 'height' => $height,
        'x' => $left, 'y' => $top,
        'patternUnits' => 'userSpaceOnUse'
      ];
      // tiled image becomes a pattern to replace background colour
      $this->defs->add($this->element('pattern', $pattern, null, $im));
      $this->setOption('back_colour', 'url(#' . $pattern['id'] . ')');
    } else {
      $im = $this->element('image', $image, $style);
      $contents .= $im;
    }
    return $contents;
  }

  /**
   * Displays the background
   */
  protected function canvas($id)
  {
    $round = $this->getOption('back_round');
    $stroke_width = $this->getOption('back_stroke_width');
    $stroke_colour = new Colour($this, $this->getOption('back_stroke_colour'));
    $shadow_opacity = $this->getOption('back_shadow_opacity');
    $shadow_blur = $this->getOption('back_shadow_blur');
    $shadow = $this->getOption('back_shadow');
    if($shadow === true)
      $shadow = 2;

    // background image can replace the back_colour option
    $bg = $this->backgroundImage($shadow, $round ? $id : null);
    $colour = new Colour($this, $this->getOption('back_colour'));

    $canvas = [
      'width' => '100%', 'height' => '100%',
      'fill' => $colour,
      'stroke-width' => 0,
    ];
    $c_el = '';

    if($shadow) {
      // shadow means canvas cannot be 100% of document
      $canvas['x'] = $canvas['y'] = new Number($stroke_width / 2);

      // blurring means using a filter and a larger offset
      if($shadow_blur) {
        $filter_opts = [
          'offset_x' => $shadow,
          'offset_y' => $shadow,
          'blur' => $shadow_blur,
          'opacity' => $shadow_opacity,
          'shadow_only' => true,
        ];

        $shadow += $shadow_blur;
        $filter = $this->defs->addFilter('shadow', $filter_opts);
      }
      $canvas['width'] = new Number($this->width - $shadow - $stroke_width);
      $canvas['height'] = new Number($this->height - $shadow - $stroke_width);

      $filled = [
        //'x' => $shadow, 'y' => $shadow,
        'width' => new Number($this->width - $shadow),
        'height' => new Number($this->height - $shadow),
        'fill' => '#000',
        'stroke-width' => 0,
        //'opacity' => $shadow_opacity,
      ];

      if($shadow_blur) {
        $filled['filter'] = 'url(#' . $filter . ')';
      } else {
        $filled['x'] = $shadow;
        $filled['y'] = $shadow;
        $filled['opacity'] = $shadow_opacity;
      }

      if($round)
        $filled['rx'] = $filled['ry'] = new Number($round);

      $c_el .= $this->element('rect', $filled);

      // increase padding to clear shadow
      $this->pad_right += $shadow;
      $this->pad_bottom += $shadow;
    }

    if($colour->opacity() < 1)
      $canvas['opacity'] = $colour->opacity(true);

    if($round)
      $canvas['rx'] = $canvas['ry'] = new Number($round);
    if($bg == '' && $stroke_width) {
      $canvas['stroke-width'] = $stroke_width;
      $canvas['stroke'] = $stroke_colour;
    }
    $c_el .= $this->element('rect', $canvas);

    // create a clip path for rounded rectangle
    if($round) {
      $this->defs->add($this->element('clipPath', ['id' => $id], null,
        $this->element('rect', $canvas)));
    }

    // if the background image is an element, insert it between the background
    // colour and border rect
    if($bg != '') {
      $c_el .= $bg;
      if($stroke_width) {
        $canvas['stroke-width'] = $stroke_width;
        $canvas['stroke'] = $stroke_colour;
        $canvas['fill'] = 'none';
        $c_el .= $this->element('rect', $canvas);
      }
    }

    return $c_el;
  }

  /**
   * Displays readable (hopefully) error message
   */
  protected function errorText($error)
  {
    $text = ['x' => 3, 'y' => $this->height - 3];
    $style = [
      'font-family' => 'Courier New',
      'font-size' => '11px',
      'font-weight' => 'bold',
    ];

    $e = $this->contrastText($text['x'], $text['y'], $error, 'blue',
      'white', $style);
    return $e;
  }

  /**
   * Displays high-contrast text
   */
  protected function contrastText($x, $y, $text, $fcolour = 'black',
    $bcolour = 'white', $properties = null, $styles = null)
  {
    $xform = new Transform;
    $xform->translate($x, $y);
    $props = ['transform' => $xform, 'fill' => $fcolour];
    if(is_array($properties))
      $props = array_merge($properties, $props);

    $bg = $this->element('text', ['stroke-width' => '2px', 'stroke' => $bcolour],
      null, $text);
    $fg = $this->element('text', null, null, $text);
    return $this->element('g', $props, $styles, $bg . $fg);
  }

  /**
   * Builds an element
   */
  public function element($name, $attribs = null, $styles = null,
    $content = null, $no_whitespace = false)
  {
    if($this->namespace && strpos($name, ':') === false)
      $name = 'svg:' . $name;
    $element = '<' . $name;
    if(is_array($attribs)) {
      foreach($attribs as $attr => $val) {
        $value = new Attribute($attr, $val, $this->encoding);
        $element .= ' ' . $attr . '="' . $value . '"';
      }
    }

    if(is_array($styles)) {
      $element .= ' style="';
      foreach($styles as $attr => $val) {
        $value = new Attribute($attr, $val, $this->encoding);
        $element .= $attr . ':' . $value . ';';
      }
      $element .= '"';
    }

    if($content === null)
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
  public function getLinkURL($item, $key, $row = 0)
  {
    $link = ($item === null ? null : $item->link);
    if($link === null && is_array($this->links[$row]) &&
      isset($this->links[$row][$key])) {
      $link = $this->links[$row][$key];
    }

    // check for absolute links
    if($link !== null && strpos($link,'//') === false)
      $link = $this->link_base . $link;

    return $link;
  }

  /**
   * Retrieves a link
   */
  public function getLink($item, $key, $content, $row = 0)
  {
    $link = $this->getLinkURL($item, $key, $row);
    if($link === null)
      return $content;

    $link_attr = ['xlink:href' => $link];
    if(!empty($this->link_target))
      $link_attr['target'] = $this->link_target;
    return $this->element('a', $link_attr, null, $content);
  }

  /**
   * Returns TRUE if the item is visible on the graph
   */
  public function isVisible($item, $dataset = 0)
  {
    // default implementation is for all non-zero values to be visible
    return ($item->value != 0);
  }

  /**
   * Sets up the colour class
   */
  protected function colourSetup($count, $datasets = null)
  {
    $this->colours->setup($count, $datasets);
  }

  /**
   * Returns a Colour
   */
  public function getColour($item, $key, $dataset, $allow_gradient = true,
    $allow_pattern = true)
  {
    if($item !== null && $item->colour !== null)
      return new Colour($this, $item->colour, $allow_gradient, $allow_pattern);

    $c = $this->colours->getColour($key, $dataset);
    if($c === null)
      return new Colour($this, null);

    $colour = new Colour($this, $c, $allow_gradient, $allow_pattern);

    // make key reflect dataset as well (for gradients)
    if($dataset !== null)
      $key = $dataset . ':' . $key;
    if($key !== null)
      $colour->setGradientKey($key);

    return $colour;
  }

  /**
   * Returns the first non-empty option in named argument list.
   * Arguments must be "opt_name" or array("opt_name", $index), optionally
   * ending with default value (non-string or array('@', $value))
   */
  public function getOption($opt, $opt2 = null)
  {
    // single option - checking for null second option is faster
    // than using func_num_args()
    if($opt2 === null && is_string($opt)) {
      if(isset($this->settings[$opt]) && $this->settings[$opt] !== '')
        return $this->settings[$opt];
      return null;
    }

    $opts = func_get_args();
    foreach($opts as $opt) {
      // not string or array, default value
      if(!is_array($opt) && !is_string($opt))
        return $opt;

      if(is_array($opt)) {
        // validate
        if(!isset($opt[0]) || !isset($opt[1]) || !is_string($opt[0]))
          throw new \InvalidArgumentException('Malformed option array');

        // default value
        if($opt[0] === '@')
          return $opt[1];

        list($name, $index) = $opt;
        if(isset($this->settings[$name])) {
          $val = $this->settings[$name];
          if(is_array($val)) {

            if(isset($val[$index])) {
              $val = $val[$index];
            } else {
              if(!is_numeric($index))
                $index = 0;
              $val = $val[$index % count($val)];
            }
          }

          if($val !== null && $val !== '')
            return $val;
        }
        continue;
      }

      // not an array
      if(isset($this->settings[$opt])) {
        $val = $this->settings[$opt];
        if($val !== null && $val !== '')
          return $val;
      }
    }

    return null;
  }

  /**
   * Returns option from data item if present, or from settings if not
   */
  public function getItemOption($option, $dataset, &$item, $item_option = null)
  {
    if($item_option === null)
      $item_option = $option;
    $value = null;
    if($item !== null)
      $value = $item->data($item_option);
    if($value === null)
      $value = $this->getOption([$option, $dataset]);
    return $value;
  }

  /**
   * Option setter
   */
  public function setOption($name, $value, $index = null)
  {
    // very simple for now, might have to revisit it later
    if($index === null) {
      $this->settings[$name] = $value;
      return;
    }
    $this->settings[$name][$index] = $value;
  }

  /**
   * Returns the graph size and padding
   */
  public function getDimensions()
  {
    $dimensions = [
      'width' => $this->width,
      'height' => $this->height,
      'pad_left' => $this->pad_left,
      'pad_top' => $this->pad_top,
      'pad_right' => $this->pad_right,
      'pad_bottom' => $this->pad_bottom,
    ];
    return $dimensions;
  }

  /**
   * Checks that the data are valid
   */
  protected function checkValues()
  {
    if($this->values->error)
      throw new \Exception($this->values->error);
  }

  /**
   * Sets the stroke options for an element
   */
  protected function setStroke(&$attr, &$item, $key, $dataset, $line_join = null)
  {
    unset($attr['stroke'], $attr['stroke-width'], $attr['stroke-linejoin'],
      $attr['stroke-dasharray']);

    $stroke_width = $this->getItemOption('stroke_width', $dataset, $item);
    if($stroke_width > 0) {
      $cg = new ColourGroup($this, $item, $key, $dataset);
      $attr['stroke'] = $cg->stroke();

      if($attr['stroke']->opacity() < 1)
        $attr['stroke-opacity'] = $attr['stroke']->opacity(true);
      $attr['stroke-width'] = $stroke_width;
      if($line_join !== null)
        $attr['stroke-linejoin'] = $line_join;

      $dash = $this->getItemOption('stroke_dash', $dataset, $item);
      if(!empty($dash))
        $attr['stroke-dasharray'] = $dash;
    }
  }

  /**
   * Creates a new ID for an element
   */
  public function newID()
  {
    $prefix = (string)$this->getOption('id_prefix');
    $i = ++Graph::$last_id;

    // id is case sensitive, so use lower and upper case
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $iid = [];
    while($i > 0) {
      $c = $i % 62;
      $i = floor($i / 62);
      $iid[] = $chars[$c];
    }
    return $prefix . 'e' . implode(array_reverse($iid));
  }

  /**
   * Adds markup to be inserted between graph and legend
   */
  public function addBackMatter($fragment)
  {
    $this->back_matter .= $fragment;
  }

  /**
   * Adds context menu for item
   */
  protected function setContextMenu(&$element, $dataset, &$item,
    $duplicate = false)
  {
    $this->context_menu->setMenu($element, $dataset, $item, $duplicate);
  }

  /**
   * Default tooltip contents are key and value, or whatever
   * $key is if $value is not set
   */
  protected function setTooltip(&$element, &$item, $dataset, $key, $value = null,
    $duplicate = false)
  {
    $callback = $this->getOption('tooltip_callback');
    if(is_callable($callback)) {
      if($value === null)
        $value = $key;
      $text = call_user_func_array($callback, [$dataset, $key, $value]);
    } elseif(is_array($this->structure) && isset($this->structure['tooltip'])) {
      // use structured data tooltips if specified
      $text = $item->tooltip;
    } else {
      $text = $this->formatTooltip($item, $dataset, $key, $value);
    }
    if($text === null)
      return;
    $text = addslashes(str_replace("\n", '\n', $text));
    return $this->javascript->setTooltip($element, $text, $duplicate);
  }

  /**
   * Default format is value only
   */
  protected function formatTooltip(&$item, $dataset, $key, $value)
  {
    $n = new Number($value, $this->units_tooltip, $this->units_before_tooltip);
    return $n->format();
  }

  /**
   * Adds a data label to the list
   */
  protected function addDataLabel($dataset, $index, &$element, &$item,
    $x, $y, $w, $h, $content = null, $duplicate = true)
  {
    if(!$this->getOption(['show_data_labels', $dataset]))
      return false;

    // set up fading for this label?
    $id = null;
    $fade_in = $this->getOption(['data_label_fade_in_speed', $dataset]);
    $fade_out = $this->getOption(['data_label_fade_out_speed', $dataset]);
    $click = $this->getOption(['data_label_click', $dataset]);
    $popup = $this->getOption(['data_label_popfront', $dataset]);
    if($click == 'hide' || $click == 'show') {
      $id = $this->newID();
      $this->javascript->setClickShow($element, $id, $click == 'hide', $duplicate);
    }
    if($popup) {
      if(!$id)
        $id = $this->newID();
      $this->javascript->setPopFront($element, $id, $duplicate);
    }
    if($fade_in || $fade_out) {
      $speed_in = $fade_in ? $fade_in / 100 : 0;
      $speed_out = $fade_out ? $fade_out / 100 : 0;
      if(!$id)
        $id = $this->newID();
      $this->javascript->setFader($element, $speed_in, $speed_out, $id, $duplicate);
    }
    $this->data_labels->addLabel($dataset, $index, $item, $x, $y, $w, $h, $id,
      $content, $fade_in, $click);
    return true;
  }

  /**
   * Adds an element as a client of existing label
   */
  protected function addLabelClient($dataset, $index, &$element)
  {
    $label = $this->data_labels->getLabel($dataset, $index);
    if($label === null)
      return false;

    $id = $label['id'];
    $fade_in = $this->getOption(['data_label_fade_in_speed', $dataset]);
    $fade_out = $this->getOption(['data_label_fade_out_speed', $dataset]);
    $click = $this->getOption(['data_label_click', $dataset]);
    $popup = $this->getOption(['data_label_popfront', $dataset]);
    if($click == 'hide' || $click == 'show')
      $this->javascript->setClickShow($element, $id, $click == 'hide', true);
    if($popup)
      $this->javascript->setPopFront($element, $id, true);
    if($fade_in || $fade_out) {
      $speed_in = $fade_in ? $fade_in / 100 : 0;
      $speed_out = $fade_out ? $fade_out / 100 : 0;
      $this->javascript->setFader($element, $speed_in, $speed_out, $id, true);
    }
  }

  /**
   * Adds a label for non-data text
   */
  protected function addContentLabel($dataset, $index, $x, $y, $w, $h, $content)
  {
    $this->data_labels->addContentLabel($dataset, $index, $x, $y, $w, $h,
      $content);
    return true;
  }

  /**
   * Draws the data labels
   */
  protected function drawDataLabels()
  {
    if(isset($this->settings['label']))
      $this->data_labels->load($this->settings);
    return $this->data_labels->getLabels();
  }

  /**
   * Returns the position for a data label
   */
  public function dataLabelPosition($dataset, $index, &$item, $x, $y, $w, $h,
    $label_w, $label_h)
  {
    $pos = $this->getOption(['data_label_position', $dataset]);
    if(empty($pos))
      $pos = 'above';
    $end = [$x + $w * 0.5, $y + $h * 0.5];
    return [$pos, $end];
  }

  public function underShapes()
  {
    if(!isset($this->settings['shape']))
      return '';
    return $this->shapes->draw(ShapeList::BELOW);
  }

  public function overShapes()
  {
    if(!isset($this->settings['shape']))
      return '';
    return $this->shapes->draw(ShapeList::ABOVE);
  }

  /**
   * Returns TRUE if the position is inside the item
   */
  public static function isPositionInside($pos)
  {
    list($hpos, $vpos) = Graph::translatePosition($pos);
    return strpos($hpos . $vpos, 'o') === false;
  }

  /**
   * Sets the styles for data labels
   */
  public function dataLabelStyle($dataset, $index, &$item)
  {
    // this function gets called a lot, so cache the return values
    if(isset($this->data_label_style_cache[$dataset]))
      return $this->data_label_style_cache[$dataset];

    $map = $this->data_labels->getStyleMap();
    $style = [];
    foreach($map as $key => $option) {
      $style[$key] = $this->getOption([$option, $dataset]);
    }

    // padding x/y options override single value
    $style['pad_x'] = $this->getOption(['data_label_padding_x', $dataset],
      ['data_label_padding', $dataset]);
    $style['pad_y'] = $this->getOption(['data_label_padding_y', $dataset],
      ['data_label_padding', $dataset]);

    $this->data_label_style_cache[$dataset] = $style;
    return $style;
  }

  /**
   * Tail direction is required for some types of label
   */
  public function dataLabelTailDirection($dataset, $index, $hpos, $vpos)
  {
    // angle starts at right, goes clockwise
    $angle = 90;
    $pos = str_replace(['i', 'o', 'm'], '', $vpos) .
      str_replace(['i', 'o', 'c'], '', $hpos);
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
  private function buildGraph()
  {
    $this->checkValues($this->values);

    // body content comes from the subclass
    return $this->drawGraph();
  }

  /**
   * Returns the SVG document
   */
  public function fetch($header = true, $defer_javascript = true)
  {
    $content = '';
    if($header) {
      $content .= '<?xml version="1.0"';
      // encoding comes before standalone
      if(strlen($this->encoding) > 0)
        $content .= ' encoding="' . $this->encoding . '"';
      $content .= ' standalone="no"?>' . "\n";
      if($this->doctype)
        $content .= '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" ' .
        '"http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">' . "\n";
    }

    // set the precision - PHP default is 14 digits!
    $old_precision = ini_set('precision', $this->settings['precision']);
    Number::setup($this->settings['precision'], $this->settings['decimal'],
      $this->settings['thousands']);

    $heading = $foot = '';
    // display title and description if available
    if($this->title)
      $heading .= $this->element('title', null, null, $this->title);
    if($this->description)
      $heading .= $this->element('desc', null, null, $this->description);

    try {
      $body = $this->buildGraph();
    } catch(\Exception $e) {
      if($this->getOption('exception_throw'))
        throw $e;

      $err = $e->getMessage();
      $details = $this->getOption('exception_details');
      if($details)
        $err .= ' [' . basename($e->getFile()) . ' #' . $e->getLine() . ']';
      $body = $this->errorText($err);

      if($details) {
        $body .= "<!--\nException thrown from " .
          $e->getFile() . " @ " . $e->getLine() . "\nTrace: \n" .
          $e->getTraceAsString() . "\n-->\n";
      }
    }

    $svg = [
      'version' => '1.1',
      'width' => new Number($this->width),
      'height' => new Number($this->height),
    ];

    if($this->subgraph) {
      // subgraphs need x and y, and can overflow
      $x = $this->getOption('graph_x');
      $y = $this->getOption('graph_y');
      if($x)
        $svg['x'] = $x;
      if($y)
        $svg['y'] = $y;
      $svg['overflow'] = 'visible';
    } else {
      if($this->namespace)
        $svg['xmlns:svg'] = 'http://www.w3.org/2000/svg';
      else
        $svg['xmlns'] = 'http://www.w3.org/2000/svg';
      $svg['xmlns:xlink'] = 'http://www.w3.org/1999/xlink';

      // add any extra namespaces
      foreach($this->namespaces as $ns => $url)
        $svg['xmlns:' . $ns] = $url;

      if($this->auto_fit) {
        // convert pixel size to viewbox size
        $svg['viewBox'] = '0 0 ' . $svg['width'] . ' ' . $svg['height'];
        $svg['width'] = $svg['height'] = '100%';
      }
    }
    if($this->svg_class)
      $svg['class'] = $this->svg_class;

    if(!$defer_javascript)
      $foot .= $this->fetchJavascript(true, !$this->namespace);

    // add defs to heading
    $heading .= $this->defs->get();

    // display version string
    if($this->show_version) {
      $text = ['x' => $this->pad_left, 'y' => $this->height - 3];
      $style = [
        'font-family' => 'Courier New',
        'font-size' => '12px',
        'font-weight' => 'bold',
      ];
      $body .= $this->contrastText($text['x'], $text['y'], SVGGraph::VERSION,
        'blue', 'white', $style);
    }

    $content .= $this->element('svg', $svg, null, $heading . $body . $foot);
    // replace PHP's precision
    ini_set('precision', $old_precision);

    if($this->minify)
      $content = preg_replace('/\>\s+\</', '><', $content);
    return $content;
  }

  /**
   * Renders the SVG document
   */
  public function render($header = true, $content_type = true,
    $defer_javascript = false)
  {
    $mime_header = 'Content-type: image/svg+xml; charset=UTF-8';
    if($content_type)
      header($mime_header);

    try {
      echo $this->fetch($header, $defer_javascript);
    } catch(\Exception $e) {
      if($this->getOption('exception_throw'))
        throw $e;
      $this->errorText($e);
    }
  }

  /**
   * When using the defer_javascript option, this returns the
   * Javascript block
   */
  public function fetchJavascript($cdata = true, $no_namespace = true)
  {
    if(!isset(Graph::$javascript))
      return '';

    $script = Graph::$javascript->getCode($cdata, $this->getOption('minify_js'));
    if($script == '')
      return '';

    $script_attr = ['type' => 'application/ecmascript'];
    $namespace = $this->namespace;
    if($no_namespace)
      $this->namespace = false;
    $js = $this->element('script', $script_attr, null, $script);
    if($no_namespace)
      $this->namespace = $namespace;
    return $js;
  }

  /**
   * Returns the minimum value in the array, ignoring NULLs
   */
  public static function min(&$a)
  {
    $min = null;
    foreach($a as $v) {
      if($v !== null && ($min === null || $v < $min))
        $min = $v;
    }
    return $min;
  }

  /**
   * Converts a string key to a unix timestamp, or NULL if invalid
   */
  public static function dateConvert($k)
  {
    // date_create functions return false if the conversion fails
    $dt = false;

    // if the format is set, try it
    if(Graph::$key_format !== null)
      $dt = date_create_from_format(Graph::$key_format, $k);

    // try default conversion
    if($dt === false)
      $dt = date_create($k);

    // give up
    if($dt === false)
      return null;

    // this works in 64-bit on 32-bit systems, getTimestamp() doesn't
    return $dt->format('U');
  }
}
