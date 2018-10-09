<?php
/**
 * Copyright (C) 2015-2018 Graham Breach
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

class DataLabels {

  protected $graph;
  protected $have_filters = false;
  private $labels = array();
  private $max_values = array();
  private $min_values = array();
  private $start_indices = array();
  private $end_indices = array();
  private $peak_indices = array();
  private $trough_indices = array();
  private $directions = array();
  private $last = array();
  private $max_labels = 1000;
  private $coords = NULL;

  /**
   * Details of each label type
   */
  private $types_info = array(
    'box' => array('shape' => 'BoxLabel', 'tail' => false, 'pad' => true),
    'bubble' => array('shape' => 'BubbleLabel', 'tail' => true, 'pad' => true),
    'circle' => array('shape' => 'CircleLabel', 'tail' => false, 'pad' => true),
    'line' => array('shape' => 'LineLabel', 'tail' => true, 'pad' => false),
    'line2' => array('shape' => 'LineLabel2', 'tail' => true, 'pad' => true),
    'linebox' => array('shape' => 'BoxLineLabel', 'tail' => true, 'pad' => true),
    'linecircle' => array('shape' => 'CircleLineLabel', 'tail' => true, 'pad' => true),
    'linesquare' => array('shape' => 'SquareLineLabel', 'tail' => true, 'pad' => true),
    'plain' => array('shape' => NULL, 'tail' => false, 'pad' => false),
    'square' => array('shape' => 'SquareLabel', 'tail' => false, 'pad' => true),
  );

  /**
   * Mapping between label style array members and options
   */
  protected $style_map = array(
    'type' => 'data_label_type',
    'font' => 'data_label_font',
    'font_size' => 'data_label_font_size',
    'font_adjust' => 'data_label_font_adjust',
    'font_weight' => 'data_label_font_weight',
    'colour' => 'data_label_colour',
    'altcolour' => 'data_label_colour_outside',
    'back_colour' => 'data_label_back_colour',
    'back_altcolour' => 'data_label_back_colour_outside',
    'space' => 'data_label_space',
    'angle' => 'data_label_angle',
    'pad_x' => 'data_label_padding_x',
    'pad_y' => 'data_label_padding_y',
    'round' => 'data_label_round',
    'stroke' => 'data_label_outline_colour',
    'stroke_width' => 'data_label_outline_thickness',
    'fill' => 'data_label_fill',
    'tail_width' => 'data_label_tail_width',
    'tail_length' => 'data_label_tail_length',
    'tail_end' => 'data_label_tail_end',
    'tail_end_angle' => 'data_label_tail_end_angle',
    'tail_end_width' => 'data_label_tail_end_width',
    'shadow_opacity' => 'data_label_shadow_opacity',
  );

  function __construct(&$graph)
  {
    $this->graph =& $graph;
    $this->have_filters = !empty($graph->data_label_filter) &&
      $graph->data_label_filter !== 'all';
    if($graph->data_label_max_count >= 1)
      $this->max_labels = (int)$graph->data_label_max_count;
  }

  /**
   * Retrieves properties from the graph if they are not
   * already available as properties
   */
  public function __get($name)
  {
    return $this->graph->{$name};
  }

  /**
   * Make empty($this->option) more robust
   */
  public function __isset($name)
  {
    return isset($this->graph->{$name});
  }

  /**
   * Adds a label to the list
   */
  public function AddLabel($dataset, $index, &$item, $x, $y, $w, $h,
    $id = NULL, $content = NULL, $fade_in = NULL, $click = NULL)
  {
    if(!isset($this->labels[$dataset]))
      $this->labels[$dataset] = array();
    $this->labels[$dataset][$index] = array(
      'item' => $item, 'id' => $id, 'content' => $content,
      'x' => $x, 'y' => $y, 'width' => $w, 'height' => $h,
      'fade' => $fade_in, 'click' => $click,
    );

    if($this->have_filters)
      $this->SetupFilters($dataset, $index, $item->value);
  }

  /**
   * Adds a content (non-data) label
   */
  public function AddContentLabel($dataset, $index, $x, $y, $w, $h, $content)
  {
    if(!isset($this->labels[$dataset]))
      $this->labels[$dataset] = array();
    $this->labels[$dataset][$index] = array(
      'item' => null, 'id' => null, 'content' => $content,
      'x' => $x, 'y' => $y, 'width' => $w, 'height' => $h,
      'fade' => null, 'click' => null,
    );
  }

  /**
   * Adds a user-defined label from a label option
   */
  public function AddUserLabel($label_array)
  {
    if(!isset($this->labels['_user']))
      $this->labels['_user'] = array();

    if(!isset($label_array[0]) || !isset($label_array[1]) || !isset($label_array[2]))
      throw new Exception('Malformed label option - required fields missing');

    $x = $label_array[0];
    $y = $label_array[1];
    $content = $label_array[2];
    $w = 0;
    $h = 0;
    // merge the options with required fields
    $this->labels['_user'][] = array_merge($label_array, array(
      'item' => null, 'id' => null, 'content' => $content,
      'x' => $x, 'y' => $y, 'width' => $w, 'height' => $h,
      'fade' => null, 'click' => null,
    ));
  }

  /**
   * Returns label details
   */
  public function GetLabel($dataset, $index)
  {
    if(isset($this->labels[$dataset][$index]))
      return $this->labels[$dataset][$index];
    return NULL;
  }

  /**
   * Updates filter information from label
   */
  protected function SetupFilters($dataset, $index, $value)
  {
    // set up filtering info
    if(!isset($this->max_values[$dataset]) ||
      $this->max_values[$dataset] < $value)
      $this->max_values[$dataset] = $value;
    if(!isset($this->min_values[$dataset]) ||
      $this->min_values[$dataset] > $value)
      $this->min_values[$dataset] = $value;
    if(!isset($this->start_indices[$dataset]) ||
      $this->start_indices[$dataset] > $index)
      $this->start_indices[$dataset] = $index;
    if(!isset($this->end_indices[$dataset]) ||
      $this->end_indices[$dataset] < $index)
      $this->end_indices[$dataset] = $index;

    // peaks and troughs are a bit more complicated
    if(!isset($this->last[$dataset])) {
      $this->last[$dataset] = array($index, $value);
      $this->directions[$dataset] = null;
      $this->peak_indices[$dataset] = array();
      $this->trough_indices[$dataset] = array();
    } elseif($this->last[$dataset][1] != $value) {
      $last = $this->last[$dataset];
      $diff = $value - $last[1];
      $direction = ($diff > 0);
      if(!is_null($this->directions[$dataset]) &&
        $direction !== $this->directions[$dataset]) {
        if($diff > 0)
          $this->trough_indices[$dataset][] = $last[0];
        else
          $this->peak_indices[$dataset][] = $last[0];
      }
      $this->last[$dataset] = array($index, $value);
      $this->directions[$dataset] = $direction;
    }
  }


  /**
   * Load user-defined labels
   */
  public function Load(&$settings)
  {
    if(!is_array($settings['label']) || !isset($settings['label'][0]))
      throw new Exception('Malformed label option');

    $count = 0;
    if(!is_array($settings['label'][0])) {
      $this->AddUserLabel($settings['label']);
      ++$count;
    } else {
      foreach($settings['label'] as $label) {
        $this->AddUserLabel($label);
        ++$count;
      }
    }
    if($count) {
      require_once "SVGGraphCoords.php";
      $this->coords = new SVGGraphCoords($this->graph);
    }
  }

  /**
   * Returns all the labels as a string
   */
  public function GetLabels()
  {
    $filters = $this->data_label_filter;
    $label_list = array();
    foreach($this->labels as $dataset => $label_set) {

      if(is_numeric($dataset)) {
        $set_filter = is_array($filters) ? $filters[$dataset % count($filters)] :
          $filters;
      } else {
        $set_filter = 'all';
      }
      $count = 0;
      foreach($label_set as $i => $label) {
        if($this->Filter($set_filter, $dataset, $label, $i)) {
          $content = $this->GetLabelText($dataset, $label);
          if(!is_null($content) && $content != '') {

            list($w, $h) = $this->MeasureLabel($content, $dataset, $i, $label);

            $label_list[] = compact('content', 'w', 'h', 'dataset', 'i', 'label');
            if(++$count >= $this->max_labels)
              break;
          }
        }
      }
    }

    $this->SetLabelSizes($label_list);
    $labels = '';
    foreach($label_list as $l) {
      $labels .= $this->DrawLabel($l['content'], $l['w'], $l['h'],
        $l['dataset'], $l['i'], $l['label']);
    }
    if($labels != '') {
      $group = array();
      if($this->semantic_classes)
        $group['class'] = 'data-labels';
      $labels = $this->graph->Element('g', $group, NULL, $labels);
    }
    return $labels;
  }

  /**
   * Adjusts the label sizes
   */
  protected function SetLabelSizes(&$labels)
  {
    if(!$this->data_label_same_size)
      return;

    // globally equal sizes
    if(!is_array($this->data_label_same_size)) {
      $max_w = 0;
      $max_h = 0;
      foreach($labels as $l) {
        if(is_numeric($l['dataset'])) {
          if($l['w'] > $max_w)
            $max_w = $l['w'];
          if($l['h'] > $max_h)
            $max_h = $l['h'];
        }
      }

      foreach($labels as $k => $l) {
        if(is_numeric($l['dataset'])) {
          $labels[$k]['w'] = $max_w;
          $labels[$k]['h'] = $max_h;
        }
      }
    } else {
      // per-dataset maxima (20 datasets should be enough for anybody)
      $max_w = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
      $max_h = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);

      foreach($labels as $l) {
        $d = $l['dataset'];
        if(is_numeric($d)) {
          if($l['w'] > $max_w[$d])
            $max_w[$d] = $l['w'];
          if($l['h'] > $max_h[$d])
            $max_h[$d] = $l['h'];
        }
      }

      foreach($labels as $k => $l) {
        $d = $l['dataset'];
        if(is_numeric($d) && $this->graph->GetOption(
          array('data_label_same_size', $d))) {
          $labels[$k]['w'] = $max_w[$d];
          $labels[$k]['h'] = $max_h[$d];
        }
      }
    }
  }

  /**
   * Returns the text for a label
   */
  protected function GetLabelText($dataset, &$gobject)
  {
    if(is_null($gobject['item']) && empty($gobject['content']))
      return '';

    if(is_null($gobject['item'])) {
      $content = (string)$gobject['content'];
    } else {
      if(is_callable($this->data_label_callback)) {
        $content = call_user_func($this->data_label_callback, $dataset,
          $gobject['item']->key, $gobject['item']->value);
        if(is_null($content))
          $content = '';
      } else {
        $content = $gobject['item']->Data('label');
      }
      if(is_null($content)) {
        $content = !is_null($gobject['content']) ? $gobject['content'] :
          $this->units_before_label . Graph::NumString($gobject['item']->value) .
          $this->units_label;
      }
    }
    return $content;
  }

  /**
   * Returns the style details for a label
   */
  protected function GetStyle($dataset, $index, &$gobject)
  {
    // global styles filled in by graph class
    $style = $this->graph->DataLabelStyle($dataset, $index, $gobject['item']);

    // structured styles and user defined label styles
    if(!is_null($gobject['item']))
      $this->ItemStyles($style, $gobject['item']);
    elseif($dataset === '_user')
      $this->UserStyles($style, $gobject);
    return $style;
  }

  /**
   * Returns size of a label as array (w, h)
   */
  protected function MeasureLabel($content, $dataset, $index, &$gobject)
  {
    $style = $this->GetStyle($dataset, $index, $gobject);

    // get size of text
    $font_size = max(4, (float)$style['font_size']);
    $svg_text = new SVGGraphText($this->graph, $style['font'], $style['font_adjust']);
    list($w, $h) = $svg_text->Measure($content, $font_size, $style['angle'],
      $font_size);

    // if this label type uses padding, add it in
    $type_info = isset($this->types_info[$style['type']]) ?
      $this->types_info[$style['type']] : $this->types_info['plain'];
    if($type_info['pad']) {
      $w += $style['pad_x'] * 2;
      $h += $style['pad_y'] * 2;
    }

    return array($w, $h);
  }

  /**
   * Returns the foreground and background colours, dependent on relative
   * position
   */
  protected function GetColours($hpos, $vpos, $style)
  {
    // if the position is outside, use the alternative colours
    $colour = $style['colour'];
    $back_colour = $style['back_colour'];
    if(strpos($hpos . $vpos, 'o') !== FALSE) {
      if(!empty($style['altcolour']))
        $colour = $style['altcolour'];
      if(!empty($style['back_altcolour']))
        $back_colour = $style['back_altcolour'];
    }
    return array($colour, $back_colour);
  }

  /**
   * Draws a label
   */
  protected function DrawLabel($content, $label_w, $label_h, $dataset, $index,
    &$gobject)
  {
    $style = $this->GetStyle($dataset, $index, $gobject);
    $style['target'] = array($gobject['x'], $gobject['y']);

    $space = (float)$style['space'];
    $pos = NULL;
    if($dataset === '_user') {
      // user label, so convert coordinates
      $pos = isset($gobject['position']) ? $gobject['position'] : 'above';
      $xy = $this->coords->TransformCoords($gobject['x'], $gobject['y']);
      $gobject['x'] = $xy[0];
      $gobject['y'] = $xy[1];
    } else {
      // try to get position from item
      if(!is_null($gobject['item']))
        $pos = $gobject['item']->Data('data_label_position');

      // find out from graph class where this label should go
      if(is_null($pos)) {
        $label_wp = $label_w + $space * 2;
        $label_hp = $label_h + $space * 2;

        // get the label position and the target for tail
        list($pos, $target) = $this->graph->DataLabelPosition($dataset,
          $index, $gobject['item'], $gobject['x'], $gobject['y'],
          $gobject['width'], $gobject['height'], $label_wp, $label_hp);
        $style['target'] = $target;
      }
    }

    // convert position string to an actual location
    list($x, $y, $anchor, $hpos, $vpos) = $res = Graph::RelativePosition($pos,
      $gobject['y'], $gobject['x'],
      $gobject['y'] + $gobject['height'], $gobject['x'] + $gobject['width'],
      $label_w, $label_h, $space, true);

    list($colour, $back_colour) = $this->GetColours($hpos, $vpos, $style);

    $font_size = max(4, (float)$style['font_size']);
    $text = array(
      'font-family' => $style['font'],
      'font-size' => $font_size,
      'fill' => $colour,
    );

    $label_markup = '';
    $type_info = isset($this->types_info[$style['type']]) ?
      $this->types_info[$style['type']] : $this->types_info['plain'];
    if($type_info['pad']) {
      $label_pad_x = $style['pad_x'];
      $label_pad_y = $style['pad_y'];
    } else {
      $label_pad_x = $label_pad_y = 0;
    }

    // need text size without padding, rotation, etc.
    $svg_text = new SVGGraphText($this->graph, $style['font'],
      $style['font_adjust']);
    list($tbw, $tbh) = $svg_text->Measure($content, $font_size, 0, $font_size);
    $text_baseline = $svg_text->Baseline($font_size);

    $text['y'] = $y + ($label_h - $tbh) / 2 + $text_baseline;
    if($style['angle'] != 0) {

      if($anchor == 'middle') {
        $text['x'] = $x;
      } elseif($anchor == 'start') {
        $text['x'] = $x + ($label_w - $tbw) / 2;
      } else {
        $text['x'] = $x - ($label_w - $tbw) / 2;
      }

    } else {

      if($anchor == 'start') {
        $text['x'] = $x + $label_pad_x;
      } elseif($anchor == 'end') {
        $text['x'] = $x - $label_pad_x;
      } else {
        $text['x'] = $x;
      }
    }

    // make x right for bounding box
    if($anchor == 'middle') {
      $x -= $label_w / 2;
    } elseif($anchor == 'end') {
      $x -= $label_w;
    }

    if($style['angle'] != 0) {
      // rotate text around centre of box
      $rx = $x + $label_w / 2;
      $ry = $y + $label_h / 2;
      $text['transform'] = "rotate({$style['angle']},$rx,$ry)";
    }

    if($anchor != 'start')
      $text['text-anchor'] = $anchor;
    if(!empty($style['font_weight']) && $style['font_weight'] != 'normal')
      $text['font-weight'] = $style['font_weight'];

    $surround = array();
    $element = null;
    $shape_func = null;
    $need_tail = false;

    if($type_info['shape']) {
      if($type_info['tail']) {
        $style['tail_direction'] = $this->graph->DataLabelTailDirection($dataset,
          $index, $hpos, $vpos);
      }

      // make the shape
      $element = $this->{$type_info['shape']}($x, $y, $label_w, $label_h,
        $style, $surround);
      if($element) {
        $surround['stroke'] = $style['stroke'];
        if($style['stroke_width'] != 1)
          $surround['stroke-width'] = (float)$style['stroke_width'];

        // add shadow if not completely transparent
        if($style['shadow_opacity'] > 0) {
          $shadow = $surround;
          $offset = 2 + floor($style['stroke_width'] / 2);
          $shadow['transform'] = "translate({$offset},{$offset})";
          $shadow['fill'] = $shadow['stroke'] = '#000';
          $shadow['opacity'] = $style['shadow_opacity'];
          $label_markup .= $this->graph->Element($element, $shadow);
        }
        $label_markup .= $this->graph->Element($element, $surround);
      }
    }

    if(!empty($back_colour)) {
      $outline = array(
        'stroke-width' => '3px',
        'stroke' => $back_colour,
        'stroke-linejoin' => 'round',
      );
      $t1 = array_merge($outline, $text);
      $label_markup .= $svg_text->Text($content, $font_size, $t1);
    }
    $label_markup .= $svg_text->Text($content, $font_size, $text);

    $group = array();
    if(isset($gobject['id']) && !is_null($gobject['id']))
      $group['id'] = $gobject['id'];

    // start off hidden?
    if($gobject['click'] == 'show')
      $group['opacity'] = 1; // set opacity explicitly for calculations
    elseif($gobject['click'] == 'hide' || $gobject['fade'])
      $group['opacity'] = 0;

    $label_markup = $this->graph->Element('g', $group, NULL, $label_markup);
    return $label_markup;
  }

  /**
   * Returns the mapping between style members and option names
   */
  public function GetStyleMap()
  {
    return $this->style_map;
  }

  /**
   * Individual label styles from the structured data item
   */
  protected function ItemStyles(&$style, &$item)
  {
    // overwrite any style options that the item has set
    $v = $item->Data('data_label_padding');
    if(!is_null($v))
      $style['pad_x'] = $style['pad_y'] = $v;
    foreach($this->style_map as $s => $k) {
      $v = $item->Data($k);
      if(!is_null($v))
        $style[$s] = $v;
    }
  }

  /**
   * Styles from the label option
   */
  protected function UserStyles(&$style, &$label_array)
  {
    // pad_x and pad_y will override single padding option
    if(isset($label_array['padding']))
      $style['pad_x'] = $style['pad_y'] = $label_array['padding'];
    foreach($this->style_map as $s => $k) {
      // remove the "data_label_" part
      $o = substr($k, 11);
      if(isset($label_array[$o]))
        $style[$s] = $label_array[$o];
    }
  }

  /**
   * Returns TRUE if the label should be shown
   */
  protected function Filter($filter, $dataset, &$label, $index)
  {
    // non-numeric datasets are for additional labels
    if(!is_numeric($dataset))
      return true;

    $item =& $label['item'];

    // if the item has a show_label member, use it
    $struct_show = $item->Data('show_label');
    if(!is_null($struct_show))
      return $struct_show;

    // if empty option or 'all' is in the list, others don't matter
    $filters = explode(' ', $filter);
    if(empty($filter) || in_array('all', $filters, true))
      return true;

    // default is to show nothing
    $show = false;
    foreach($filters as $f) {

      switch($f) {
      case 'start' :
        if($index == $this->start_indices[$dataset])
          $show = true;
        break;
      case 'end' :
        if($index == $this->end_indices[$dataset])
          $show = true;
        break;
      case 'max' :
        if($item->value == $this->max_values[$dataset])
          $show = true;
        break;
      case 'min' :
        if($item->value == $this->min_values[$dataset])
          $show = true;
        break;
      case 'peaks' :
        if(in_array($index, $this->peak_indices[$dataset], true))
          $show = true;
        break;
      case 'troughs' :
        if(in_array($index, $this->trough_indices[$dataset], true))
          $show = true;
        break;
      case 'nonzero' :
        if($item->value != 0)
          $show = true;
        break;
      default :
        // integer step
        if(is_numeric($f) && $index % (int)$f == 0) {
          $show = true;
        } else {
          // step with offset
          $parts = explode('+', $f);
          if(count($parts) == 2 &&
            is_numeric($parts[0]) && is_numeric($parts[1]) &&
            $parts[0] > 1 && $parts[1] < $parts[0] &&
            $index % (int)$parts[0] == $parts[1])
            $show = true;
        }
        break;
      }
    }

    return $show;
  }


  /**
   * Straight line label style
   */
  protected function LineLabel($x, $y, $w, $h, &$style, &$surround)
  {
    $w2 = $w / 2;
    $h2 = $h / 2;
    $a = $style['tail_direction'] * M_PI / 180;

    if($style['round']) {
      $bbradius = sqrt($w2 * $w2 + $h2 * $h2);
      $w2a = $bbradius * cos($a);
      $h2a = $bbradius * sin($a);
    } else {
      // start at edge of text bounding box
      $w2a = $w2;
      $h2a = $w2 * tan($a);
      if(abs($h2a) > $h2) {
        $h2a = $h2;
        $w2a = $h2 / tan($a);
      }
    }
    if(($a < M_PI && $h2a < 0) || ($a > M_PI && $h2a > 0)) {
      $h2a = -$h2a;
      $w2a = -$w2a;
    }
     
    $x1 = $x + $w2 + $w2a;
    $y1 = $y + $h2 + $h2a;
    if($style['tail_length'] == 'auto') {
      list($x2, $y2) = $style['target'];
      // check line is outside bbox
      if($style['round']) {
        $llen = sqrt(pow($x2 - $x - $w2, 2) + pow($y2 - $y - $h2, 2));
        if($llen < $bbradius)
          return '';
      } else {
        if($x2 > $x && $x2 < $x + $w && $y2 > $y && $y2 < $y + $h)
          return '';
      }
    } else {
      // make sure line is long enough to not look like part of text
      $llen = max($style['font_size'], $style['tail_length']);
      $x2 = $x1 + ($llen * cos($a));
      $y2 = $y1 + ($llen * sin($a));
    }
    $surround['d'] = "M{$x1} {$y1}L{$x2} {$y2}";
    return 'path';
  }

  /**
   * Simple box label style
   */
  protected function BoxLabel($x, $y, $w, $h, &$style, &$surround)
  {
    $surround['x'] = $x;
    $surround['y'] = $y;
    $surround['width'] = $w;
    $surround['height'] = $h;
    if($style['round'])
      $surround['rx'] = $surround['ry'] = min((float)$style['round'],
        $h / 2, $w / 2);
    $surround['fill'] = $this->graph->ParseColour($style['fill']);
    return 'rect';
  }

  /**
   * Speech bubble label style
   */
  protected function BubbleLabel($x, $y, $w, $h, &$style, &$surround)
  {
    // can't be more round than this!
    $round = min((float)$style['round'], $h / 3, $w / 3);
    $drop = max(2, $style['tail_length']);
    $spread = min(max(2, $style['tail_width']), $w - $round * 2);

    $vert = $h - $round * 2;
    $horz = $w - $round * 2;
    $start = 'M' . ($x + $w - $round) . ' ' . $y;
    $t = 'z';
    $r = 'v' . $vert;
    $b = 'h' . -$horz;
    $l = 'v' . -$vert;
    if($round) {
      $tr = 'a' . $round . ' ' . $round . ' 90 0 1 ' . $round . ' ' . $round;
      $br = 'a' . $round . ' ' . $round . ' 90 0 1 ' . -$round . ' ' . $round;
      $bl = 'a' . $round . ' ' . $round . ' 90 0 1 ' . -$round . ' ' . -$round;
      $tl = 'a' . $round . ' ' . $round . ' 90 0 1 ' . $round . ' ' . -$round;
    } else {
      $tr = $br = $bl = $tl = '';
    }

    $direction = floor(($style['tail_direction'] + 22.5) * 8 / 360) % 8;
    $ddrop = 0.707 * $drop; // cos 45
    $p1 = $ddrop + $spread * 0.707;
    $s2 = $spread / 2;
    $vcropped = $vert - $spread * 0.707 + $round;
    $hcropped = $horz - $spread * 0.707 + $round;
    switch($direction) {
    case 0 :
      $bside = $h / 2 - $s2 - $round;
      $r = "v{$bside}l{$drop} {$s2}l-{$drop} {$s2}v{$bside}";
      break;
    case 1 :
      $r = 'v' . $vcropped;
      $br = 'l' . $ddrop . ' ' . $p1 . 'l' . -$p1 . ' ' . -$ddrop;
      $b = 'h' . -$hcropped;
      break;
    case 2 :
      $bside = $w / 2 - $s2 - $round;
      $b = "h-{$bside}l-{$s2} {$drop}l-{$s2} -{$drop}h-{$bside}";
      break;
    case 3 :
      $l = 'v' . -$vcropped;
      $bl = 'l' . -$p1 . ' ' . $ddrop . 'l' . $ddrop . ' ' . -$p1;
      $b = 'h' . -$hcropped;
      break;
    case 4 :
      $bside = $h / 2 - $s2 - $round;
      $l = "v-{$bside}l-{$drop} -{$s2}l{$drop} -{$s2}v-{$bside}";
      break;
    case 5 :
      $l = 'v' . -$vcropped;
      $tl = 'l' . -$ddrop . ' ' . -$p1 . 'l' . $p1 . ' ' . $ddrop;
      break;
    case 6 :
      $bside = $w / 2 - $s2 - $round;
      $t = "h{$bside}l{$s2} -{$drop}l{$s2} {$drop}z";
      break;
    case 7 :
      $start = 'M' . ($x + $hcropped + $round) . ' ' . $y;
      $r = 'v' . $vcropped;
      $tr = 'l' . $p1 . ' ' . -$ddrop . 'l' . -$ddrop . ' ' . $p1;
      break;
    }
    $surround['d'] = $start . $tr . $r . $br . $b . $bl . $l . $tl . $t;
    $surround['fill'] = $this->graph->ParseColour($style['fill']);
    return 'path';
  }

  /**
   * Returns the cx, cy and radius for a round label
   */
  protected function CalcRoundLabel($x, $y, $w, $h)
  {
    $w2 = $w / 2;
    $h2 = $h / 2;
    $r = sqrt($w2 * $w2 + $h2 * $h2);
    return array($x + $w2, $y + $h2, $r);
  }

  /**
   * Circular label style
   */
  protected function CircleLabel($x, $y, $w, $h, &$style, &$surround)
  {
    $params = $this->CalcRoundLabel($x, $y, $w, $h);
    $surround['cx'] = $params[0];
    $surround['cy'] = $params[1];
    $surround['r'] = $params[2];
    $surround['fill'] = $this->graph->ParseColour($style['fill']);
    return 'circle';
  }

  /**
   * Returns the tail target coordinates, angle and length for a box label,
   * or NULL if the tail would end inside the label.
   * Return value is array(array($x, $y), $angle, $length)
   */
  protected function GetBoxTailTarget(&$style, $x1, $y1, $x2, $y2)
  {
    $target = NULL;
    $length = $style['tail_length'];
    $cx = ($x1 + $x2) / 2;
    $cy = ($y1 + $y2) / 2;
    $w2 = $cx - $x1;
    $h2 = $cy - $y1;
    if($length == 'auto') {
      // just use the defined target
      $target = $style['target'];

      // check that target is outside the label
      $tx = $target[0]; $ty = $target[1];
      if($tx >= $x1 && $tx <= $x2 && $ty >= $y1 && $ty <= $y2)
        return NULL;

      if($tx > $x2) {
        $dx = $tx - $x2;
      } elseif($tx < $x1) {
        $dx = $x1 - $tx;
      } else {
        $dx = abs($tx - $cx);
      }
      if($ty > $y2) {
        $dy = $ty - $y2;
      } elseif($ty < $y1) {
        $dy = $y1 - $ty;
      } else {
        $dy = abs($ty - $cy);
      }
      $length = sqrt($dx * $dx + $dy * $dy);
      $angle = atan2($ty - $cy, $tx - $cx);
    } else {
      // target is radius + tail length away
      if($length <= 0)
        return NULL;
      $angle = $style['tail_direction'] * M_PI / 180;

      $lx = $length * cos($angle);
      $ly = $length * sin($angle);
      // compare tangent with box ratio
      if(abs(tan($angle)) > $h2 / $w2) {
        // out top or bottom
        $target = array($cx + $lx,
          $ly > 0 ? $y2 + $ly : $y1 + $ly);
      } else {
        // out left or right
        $target = array(
          $lx > 0 ? $x2 + $lx : $x1 + $lx,
          $cy + $ly);
      }
    }
    return array($target, $angle, $length);
  }

  /**
   * Returns the tail target coordinates, angle and length for a round label,
   * or NULL if the tail would end inside the label.
   * Return value is array(array($x, $y), $angle, $length)
   */
  protected function GetRoundTailTarget(&$style, $cx, $cy, $radius)
  {
    $target = NULL;
    $length = $style['tail_length'];
    if($length == 'auto') {
      // just use the defined target
      $target = $style['target'];

      // check that target is outside the label radius
      $tx = $target[0] - $cx;
      $ty = $target[1] - $cy;
      $rt = sqrt($tx * $tx + $ty * $ty);
      if($rt <= $radius)
        return NULL;
      $angle = atan2($ty, $tx);
      $length = $rt - $radius;
    } else {
      // target is radius + tail length away
      if($length <= 0)
        return NULL;
      $len = $radius + $length;
      $angle = $style['tail_direction'] * M_PI / 180;
      $target = array($cx + ($len * cos($angle)), $cy + ($len * sin($angle)));
    }
    return array($target, $angle, $length);
  }

  /**
   * Rotate and translate $x and $y, returning SVGGraphPoint
   */
  private static function XForm($x, $y, $a, $tx, $ty)
  {
    if($x == 0 && $y == 0)
      return new SVGGraphPoint($tx, $ty);
    $sa = sin($a);
    $ca = cos($a);
    $x1 = $x * $ca - $y * $sa;
    $y1 = $x * $sa + $y * $ca;
    return new SVGGraphPoint($tx + $x1, $ty + $y1);
  }

  /**
   * Returns the tail ending path fragment
   */
  protected function GetTailEnding($x, $y, $langle, $lwidth, $dist, &$style)
  {
    $a = max(5, min(80, $style['tail_end_angle']));
    $ewidth = max($lwidth, $style['tail_end_width']);
    $eangle = M_PI * $a / 180;

    // first fallback is a tapering line
    $fallback = "L{$x} {$y}";
    $lw = $lwidth * 0.5;
    $ew = $ewidth * 0.5;
    $ll = $lw / tan($eangle);
    $el = $ew / tan($eangle);

    // ends are defined pointing upwards
    $langle -= M_PI * 0.5;
    $type = $style['tail_end'];

    // "point" style by default
    $points = array(array(-$lw, -$ll), array(0, 0),
      array($lw, -$ll));

    switch($type)
    {
    default:
    case 'flat' :
      $points = array(array(-$lw, 0), array($lw, 0));
      break;
    case 'taper' :
      return $fallback;
    case 'point' :
      if($dist <= $ll)
        return $fallback;
      break;

    case 'filled' :
      if($dist <= $el)
        return $fallback;
      if($ew > $lw) {
        $points = array(array(-$lw, -$el),
          array(-$ew, -$el),
          array(0,0),
          array($ew, -$el),
          array($lw, -$el));
      }
      break;
    case 'arrow' :
      $tip_w = $lwidth * sin($eangle);
      $w1 = $ew - $tip_w;
      $l2 = $el + $lwidth * cos($eangle);
      $l1 = $l2 - ($ew - $tip_w - $lw) / tan($eangle);
      if($dist < $l2)
        return $fallback;
      if($w1 > $lw) {
        $points = array(array(-$lw, -$l1),
          array(-$w1, -$l2),
          array(-$ew, -$el),
          array(0, 0),
          array($ew, -$el),
          array($w1, -$l2),
          array($lw, -$l1));
        break;
      }
      // fall through to diamond shape if not wide enough

    case 'diamond' :
      $blen = 2 * $el - $ll;
      if($dist <= $blen)
        return $fallback;
      if($ew > $lw) {
        $points = array(array(-$lw, -$blen),
          array(-$ew, -$el),
          array(0,0),
          array($ew, -$el),
          array($lw, -$blen));
      }
      break;
    case 'tee' :
      if($dist <= $lwidth)
        return $fallback;
      $points = array(array(-$lw, -$lwidth),
        array(-$ew, -$lwidth),
        array(-$ew, 0),
        array($ew, 0),
        array($ew, -$lwidth),
        array($lw, -$lwidth));
      break;
    case 'round' :
      if($dist < $lw)
        return $fallback;
      $cradius = min($ew, max($lw, $dist / 2));
      $rlen = sqrt(($cradius * $cradius) - ($lw * $lw));
      $rdist = $cradius + $rlen;
      $p1 = $this->XForm(-$lw, -$rdist, $langle, $x, $y);
      $p2 = $this->XForm($lw, -$rdist, $langle, $x, $y);
      return "L{$p1}A{$cradius} {$cradius} 0 1 0 {$p2}";
    }

    $path = '';
    foreach($points as $pair) {
      $pt = $this->XForm($pair[0], $pair[1], $langle, $x, $y);
      $path .= 'L' . $pt;
    }
    return $path;
  }

  /**
   * Returns the point where a line crosses an arc
   */
  private function LineCrossArc($x, $y, $angle, $cx, $cy, $radius, $corner)
  {
    $h = $cx;
    $k = $cy;
    $r = $radius;

    // 90-degree angles are simpler
    $cos = abs(cos($angle));
    $sin = abs(sin($angle));
    if($cos == 1 || $sin == 1) {
      if($cos == 1) {
        $rt = sqrt(-($y * $y) + (2 * $y * $k) - ($k * $k) + ($r * $r));
        $y1 = $y2 = $y;
        $x1 = $h - $rt;
        $x2 = $h + $rt;
      } else {
        $rt = sqrt(-($x * $x) + (2 * $x * $h) - ($h * $h) + ($r * $r));
        $x1 = $x2 = $x;
        $y1 = $k - $rt;
        $y2 = $k + $rt;
      }
    } else {
      // y = mx + c
      $m = tan($angle);
      $c = $y - ($x * $m);

      // using quadratic formula
      $disc = -($c * $c) - (2 * $c * $h * $m) + (2 * $c * $k)
        - ($h * $h * $m * $m) + (2 * $h * $k * $m) - ($k * $k)
        + ($m * $m * $r * $r) + ($r * $r);
      $rt = sqrt($disc);
      $b = (-$c * $m) + $h + ($k * $m);
      $d = ($m * $m) + 1;

      $x1 = (-$rt + $b) / $d;
      $x2 = ($rt + $b) / $d;

      // y = mx + c again, using original x and y
      $y1 = $m * $x1 + $c;
      $y2 = $m * $x2 + $c;
    }

    $use_first = false;
    switch($corner) {
    case 'tr' :
      if($x1 > $cx && $y1 < $cy)
        $use_first = true;
      break;
    case 'tl' :
      if($x1 < $cx && $y1 < $cy)
        $use_first = true;
      break;
    case 'br' :
      if($x1 > $cx && $y1 > $cy)
        $use_first = true;
      break;
    case 'bl' :
      if($x1 < $cx && $y1 > $cy)
        $use_first = true;
    }
    if($use_first)
      return new SVGGraphPoint($x1, $y1);
    return new SVGGraphPoint($x2, $y2);
  }

  /**
   * Filled line label
   */
  protected function LineLabel2($x, $y, $w, $h, &$style, &$surround)
  {
    if($style['round']) {
      list($cx, $cy, $bbradius) = $this->CalcRoundLabel($x, $y, $w, $h);
      list($target, $angle, $len) = $this->GetRoundTailTarget($style, $cx, $cy,
        $bbradius);
    } else {
      list($target, $angle, $len) = $this->GetBoxTailTarget($style, $x, $y, $x + $w,
        $y + $h);
    }
    if(is_null($target))
      return NULL;

    if($style['round']) {
      $t_width = max(1, min($style['tail_width'], $bbradius));
      $l_angle = asin($t_width * 0.5 / $bbradius);
      $p1 = new SVGGraphPoint($cx + $bbradius * cos($angle - $l_angle), 
        $cy + $bbradius * sin($angle - $l_angle));
      $p2 = new SVGGraphPoint($cx + $bbradius * cos($angle + $l_angle),
        $cy + $bbradius * sin($angle + $l_angle));
    } else {

      $h2 = $h * 0.5; $w2 = $w * 0.5;
      $cx = $x + $w2; $cy = $y + $h2;
      $t_width = max(1, min((float)$style['tail_width'], $w - 1, $h - 1));
      $sin = sin($angle);
      $cos = cos($angle);
      $xo = $yo = 0;
      if(abs($sin) == 1) {
        $py = $cy + $h2 * $sin;
        $px = $cx;
        $xo = $t_width * 0.5;
      } elseif(abs($cos) == 1) {
        $px = $cx + $w2 * $cos;
        $py = $cy;
        $yo = $t_width * 0.5;
      } else {
        $h1 = abs($w2 * tan($angle));
        if($h1 >= $h2) {
          $h1 = ($sin < 0 ? -$h2 : $h2);
          $w1 = $h1 * tan(M_PI * 0.5 - $angle);
        } else {
          $w1 = ($cos < 0 ? -$w2 : $w2);
          $h1 = $w1 / tan(M_PI * 0.5 - $angle);
        }
        $px = $cx + $w1;
        $py = $cy + $h1;
        $xo = $t_width * 0.5 * $sin;
        $yo = $t_width * -0.5 * $cos;
      }

      $p1 = new SVGGraphPoint($px + $xo, $py + $yo);
      $p2 = new SVGGraphPoint($px - $xo, $py - $yo);
      $l1 = $px - $target[0];
      $l2 = $py - $target[1];
      $len = sqrt($l1 * $l1 + $l2 * $l2);
    }

    $surround['fill'] = $this->graph->ParseColour($style['fill']);
    $surround['d'] = "M{$p1}L{$p2}" .
      $this->GetTailEnding($target[0], $target[1], $angle, $t_width, $len, $style) . "z";
    return "path";
  }

  /**
   * Line and box label style
   */
  protected function BoxLineLabel($x, $y, $w, $h, &$style, &$surround)
  {
    $x1 = $x; $y1 = $y;
    $x2 = $x + $w; $y2 = $y + $h;
    list($target, $angle, $len) = $this->GetBoxTailTarget($style, $x1, $y1,
      $x2, $y2);
    if(is_null($target))
      return $this->BoxLabel($x, $y, $w, $h, $style, $surround);

    $round = min((float)$style['round'], $h / 3, $w / 3);
    $t_width = max(1, min((float)$style['tail_width'], $w - 1, $h - 1));
    $cx = ($x1 + $x2) / 2;
    $cy = ($y1 + $y2) / 2;

    while($angle < 0)
      $angle += M_PI * 2.0;

    // centre points of corner arcs
    $x1c = $x1 + $round;
    $x2c = $x2 - $round;
    $y1c = $y1 + $round;
    $y2c = $y2 - $round;

    // box corners
    if($round) {
      $arc = "a{$round} {$round} 0 0 0 ";
      $c_tl = "L{$x1c} {$y1}{$arc}-{$round} {$round}";
      $c_tr = "L{$x2} {$y1c}{$arc}-{$round} -{$round}";
      $c_bl = "L{$x1} {$y2c}{$arc}{$round} {$round}";
      $c_br = "L{$x2c} {$y2}{$arc}{$round} -{$round}";
      // this gets repeated a lot
      $arc = "A{$round} {$round} 0 0 0";
    } else {
      $c_tl = "L{$x1} {$y1}";
      $c_tr = "L{$x2} {$y1}";
      $c_bl = "L{$x1} {$y2}";
      $c_br = "L{$x2} {$y2}";
    }
    $points = array();
    $rangle = M_PI * 0.5 - $angle;
    if(abs(tan($angle)) > $h / $w) {
      // top or bottom
      // $hoff = horizontal offset from centre of edge
      // $wa = width at angle
      $hoff = $h * 0.5 * tan($rangle);
      $wa = $t_width * 0.5 / cos($rangle);
      if($angle > M_PI) {
        // top
        $p1 = new SVGGraphPoint($cx - $hoff + $wa, $y1);
        if($p1->x < $x1) {
          $p1->y = $y1 + ($x1 - $p1->x) * tan($angle);
          $p1->x = $x1;
        }
        $p2 = new SVGGraphPoint($cx - $hoff - $wa, $y1);
        if($p2->x > $x2) {
          $p2->y = $y1 - ($p2->x - $x2) * tan($angle);
          $p2->x = $x2;
        }
        $start = "M{$p1}";
        $end = "L{$p2}";
        // if the line meets the side past the corner radius, there is no corner
        if($p1->y > $y1c)
          $c_tl = '';
        if($p2->y > $y1c)
          $c_tr = '';
        if($round) {
          if($c_tl != '' && $p1->x < $x1c) {
            $cross = $this->LineCrossArc($p1->x, $p1->y, $angle, $x1c, $y1c, $round, 'tl');
            $start = "M{$cross}";
            $c_tl = "{$arc} {$x1} {$y1c}";
            if($p2->x < $x1c) {
              $cross = $this->LineCrossArc($p2->x, $p2->y, $angle, $x1c, $y1c, $round, 'tl');
              $end = "L{$x1c} {$y1}{$arc} {$cross}";
            }
          }
          if($c_tr != '' && $x2c < $p2->x) {
            $cross = $this->LineCrossArc($p2->x, $p2->y, $angle, $x2c, $y1c, $round, 'tr');
            $end = "{$arc} {$cross}";
            $c_tr = "L{$x2} {$y1c}";
            if($x2c < $p1->x) {
              $cross = $this->LineCrossArc($p1->x, $p2->y, $angle, $x2c, $y1c, $round, 'tr');
              $start = "M{$cross}{$arc} {$x2c} {$y1}";
            }
          }
        }
        $points = array($start, $c_tl, $c_bl, $c_br, $c_tr, $end);
        $distance = $y1 - $target[1];
      } else {
        // bottom
        $p1 = new SVGGraphPoint($cx + $hoff + $wa, $y2);
        if($p1->x > $x2) {
          $p1->y = $y2 - ($p1->x - $x2) * tan($angle);
          $p1->x = $x2;
        }
        $p2 = new SVGGraphPoint($cx + $hoff - $wa, $y2);
        if($p2->x < $x1) {
          $p2->y = $y2 + ($x1 - $p2->x) * tan($angle);
          $p2->x = $x1;
        }
        $start = "M{$p1}";
        $end = "L{$p2}";
        if($p1->y < $y2c)
          $c_br = '';
        if($p2->y < $y2c)
          $c_bl = '';
        if($round) {
          if($c_bl != '' && $p2->x < $x1c) {
            $cross = $this->LineCrossArc($p2->x, $p2->y, $angle, $x1c, $y2c, $round, 'bl');
            $end = "{$arc} {$cross}";
            $c_bl = "L{$x1} {$y2c}";
            if($p1->x < $x1c) {
              $cross = $this->LineCrossArc($p1->x, $p1->y, $angle, $x1c, $y2c, $round, 'bl');
              $start = "M{$cross}{$arc} {$x1c} {$y2}";
            }
          }
          if($c_br != '' && $x2c < $p1->x) {
            $cross = $this->LineCrossArc($p1->x, $p1->y, $angle, $x2c, $y2c, $round, 'br');
            $start = "M{$cross}";
            $c_br = "{$arc} {$x2} {$y2c}";
            if($x2c < $p2->x) {
              $cross = $this->LineCrossArc($p2->x, $p2->y, $angle, $x2c, $y2c, $round, 'br');
              $end = "L{$x2c} {$y2}{$arc} {$cross}";
            }
          }
        }
        $points = array($start, $c_br, $c_tr, $c_tl, $c_bl, $end);
        $distance = $target[1] - $y2;
      }
    } else {
      // either side
      // $voff = vertical offset from centre of side
      // $wa = width at angle
      $voff = $w * 0.5 * tan($angle);
      $wa = $t_width * 0.5 / cos($angle);
      if($angle < M_PI * 0.5 || $angle > M_PI * 1.5) {
        // right
        $p1 = new SVGGraphPoint($x2, $cy + $voff - $wa);
        if($p1->y < $y1) {
          $p1->x = $x2 + ($y1 - $p1->y) * tan($rangle);
          $p1->y = $y1;
        }
        $p2 = new SVGGraphPoint($x2, $cy + $voff + $wa);
        if($p2->y > $y2) {
          $p2->x = $x2 - ($p2->y - $y2) * tan($rangle);
          $p2->y = $y2;
        }
        $start = "M{$p1}";
        $end = "L{$p2}";
        if($p1->x < $x2c)
          $c_tr = '';
        if($p2->x < $x2c)
          $c_br = '';
        if($round) {
          if($c_br != '' && $y2c < $p2->y) {
            $cross = $this->LineCrossArc($p2->x, $p2->y, $angle, $x2c, $y2c, $round, 'br');
            $end = "{$arc} {$cross}";
            $c_br = "L{$x2c} {$y2}";
            if($y2c < $p1->y) {
              $cross = $this->LineCrossArc($p1->x, $p1->y, $angle, $x2c, $y2c, $round, 'br');
              $start = "M{$cross}{$arc} {$x2} {$y2c}";
            }
          }
          if($c_tr != '' && $p1->y < $y1c) {
            $cross = $this->LineCrossArc($p1->x, $p1->y, $angle, $x2c, $y1c, $round, 'tr');
            $start = "M{$cross}";
            $c_tr = "{$arc} {$x2c} {$y1}";
            if($p2->y < $y1c) {
              $cross = $this->LineCrossArc($p2->x, $p2->y, $angle, $x2c, $y1c, $round, 'tr');
              $end = "L{$x2} {$y1c}{$arc} {$cross}";
            }
          }
        }
        $points = array($start, $c_tr, $c_tl, $c_bl, $c_br, $end);
        $distance = $target[0] - $x2;
      } else {
        // left
        $p1 = new SVGGraphPoint($x1, $cy - $voff - $wa);
        if($y2 < $p1->y) {
          $p1->x = $x1 + ($y2 - $p1->y) * tan($rangle);
          $p1->y = $y2;
        }
        $p2 = new SVGGraphPoint($x1, $cy - $voff + $wa);
        if($p2->y < $y1) {
          $p2->x = $x1 - ($p2->y - $y1) * tan($rangle);
          $p2->y = $y1;
        }
        $start = "M{$p1}";
        $end = "L{$p2}";
        if($p1->x > $x1c)
          $c_bl = '';
        if($p2->x > $x1c)
          $c_tl = '';
        if($round) {
          if($c_bl != '' && $y2c < $p1->y) {
            $cross = $this->LineCrossArc($p1->x, $p1->y, $angle, $x1c, $y2c, $round, 'bl');
            $start = "M{$cross}";
            $c_bl = "{$arc} {$x1c} {$y2}";
            if($y2c < $p2->y) {
              $cross = $this->LineCrossArc($p2->x, $p2->y, $angle, $x1c, $y2c, $round, 'bl');
              $end = "L{$x1} {$y2c}{$arc} {$cross}";
            }
          }
          if($c_tl != '' && $p2->y < $y1c) {
            $cross = $this->LineCrossArc($p2->x, $p2->y, $angle, $x1c, $y1c, $round, 'tl');
            $end = "{$arc} {$cross}";
            $c_tl = "L{$x1c} {$y1}";
            if($p1->y < $y1c) {
              $cross = $this->LineCrossArc($p1->x, $p1->y, $angle, $x1c, $y1c, $round, 'tl');
              $start = "M{$cross}{$arc} {$x1} {$y1c}";
            }
          }
        }
        $points = array($start, $c_bl, $c_br, $c_tr, $c_tl, $end);
        $distance = $x1 - $target[0];
      }
    }
    $box_path = implode($points);

    $surround['fill'] = $this->graph->ParseColour($style['fill']);
    $surround['d'] = $box_path .
      $this->GetTailEnding($target[0], $target[1], $angle, $t_width, $distance,
        $style) . "z";
    return "path";
  }

  /**
   * Line and circle label style
   */
  protected function CircleLineLabel($x, $y, $w, $h, &$style, &$surround)
  {
    list($cx, $cy, $bbradius) = $this->CalcRoundLabel($x, $y, $w, $h);
    list($target, $angle, $len) = $this->GetRoundTailTarget($style, $cx, $cy,
      $bbradius);
    if(is_null($target))
      return $this->CircleLabel($x, $y, $w, $h, $style, $surround);

    $t_width = max(1, min($style['tail_width'], $bbradius));
    $l_angle = asin($t_width * 0.5 / $bbradius);
    $p1x = $cx + $bbradius * cos($angle - $l_angle);
    $p1y = $cy + $bbradius * sin($angle - $l_angle);
    $p2x = $cx + $bbradius * cos($angle + $l_angle);
    $p2y = $cy + $bbradius * sin($angle + $l_angle);

    $surround['fill'] = $this->graph->ParseColour($style['fill']);
    $surround['d'] = "M{$p1x} {$p1y}A{$bbradius} {$bbradius} 0 1 0 {$p2x} {$p2y}" .
      $this->GetTailEnding($target[0], $target[1], $angle, $t_width, $len, $style) . "z";
    return "path";
  }

  /**
   * Converts a rectangle to a square with same centre point
   */
  private static function MakeSquare(&$x, &$y, &$w, &$h)
  {
    if($w == $h)
      return;
    $w2 = $w / 2;
    $h2 = $h / 2;
    if($w > $h) {
      $y -= ($w2 - $h2);
      $h += ($w - $h);
    } else {
      $x -= ($h2 - $w2);
      $w += ($h - $w);
    }
  }

  /**
   * Square label style
   */
  protected function SquareLabel($x, $y, $w, $h, &$style, &$surround)
  {
    $this->MakeSquare($x, $y, $w, $h);
    return $this->BoxLabel($x, $y, $w, $h, $style, $surround);
  }

  /**
   * Line and square label style
   */
  protected function SquareLineLabel($x, $y, $w, $h, &$style, &$surround)
  {
    $this->MakeSquare($x, $y, $w, $h);
    return $this->BoxLineLabel($x, $y, $w, $h, $style, $surround);
  }

};

/**
 * Data class for x,y coordinates
 */
class SVGGraphPoint {
  public $x = 0;
  public $y = 0;

  public function __construct($x, $y)
  {
    $this->x = $x;
    $this->y = $y;
  }

  public function __toString()
  {
    return (string)$this->x . ' ' . (string)$this->y;
  }
};

