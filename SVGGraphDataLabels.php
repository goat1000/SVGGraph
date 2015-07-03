<?php
/**
 * Copyright (C) 2015 Graham Breach
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
   * Returns all the labels as a string
   */
  public function GetLabels()
  {
    $labels = '';
    $filters = $this->data_label_filter;
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
          $labels .= $this->DrawLabel($dataset, $i, $label);
          if(++$count >= $this->max_labels)
            break;
        }
      }
    }
    $group = array();
    if($this->semantic_classes)
      $group['class'] = 'data-labels';
    $labels = $this->graph->Element('g', $group, NULL, $labels);
    return $labels;
  }

  /**
   * Draws a label
   */
  protected function DrawLabel($dataset, $index, &$gobject)
  {
    if(is_null($gobject['item']) && empty($gobject['content']))
      return '';

    if(is_null($gobject['item'])) {
      // convert to string - numbers will confuse TextSize()
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
    if($content == '')
      return '';

    $style = $this->graph->DataLabelStyle($dataset, $index, $gobject['item']);
    if(!is_null($gobject['item']))
      $this->ItemStyles($style, $gobject['item']);

    $type = $style['type'];
    $font_size = max(4, (float)$style['font_size']);
    $space = (float)$style['space'];
    if($type == 'box' || $type == 'bubble') {
      $label_pad_x = $style['pad_x'];
      $label_pad_y = $style['pad_y'];
    } else {
      $label_pad_x = $label_pad_y = 0;
    }

    // reasonable approximation of the baseline position
    $text_baseline = $font_size * 0.85;

    // get size of label
    list($tw, $th) = Graph::TextSize($content, $font_size, $style['font_adjust'],
      $this->encoding, $style['angle'], $font_size);

    $label_w = $tw + $label_pad_x * 2;
    $label_h = $th + $label_pad_y * 2;
    $label_wp = $label_w + $space * 2;
    $label_hp = $label_h + $space * 2;

    $pos = NULL;
    // try to get position from item
    if(!is_null($gobject['item']))
      $pos = $gobject['item']->Data('data_label_position');

    // find out from graph class where this label should go
    if(is_null($pos))
      $pos = $this->graph->DataLabelPosition($dataset, $index, $gobject['item'],
        $gobject['x'], $gobject['y'], $gobject['width'], $gobject['height'],
        $label_wp, $label_hp);

    // convert position string to an actual location
    list($x, $y, $anchor, $hpos, $vpos) = $res = Graph::RelativePosition($pos,
      $gobject['y'], $gobject['x'],
      $gobject['y'] + $gobject['height'], $gobject['x'] + $gobject['width'],
      $label_w, $label_h, $space, true);

    // if the position is outside, use the alternative colours
    $colour = $style['colour'];
    $back_colour = $style['back_colour'];
    if(strpos($hpos . $vpos, 'o') !== FALSE) {
      if(!empty($style['altcolour']))
        $colour = $style['altcolour'];
      if(!empty($style['back_altcolour']))
        $back_colour = $style['back_altcolour'];
    }

    $text = array(
      'font-family' => $style['font'],
      'font-size' => $font_size,
      'fill' => $colour,
    );

    $label_markup = '';

    // rotation
    if($style['angle'] != 0) {

      // need text size pre-rotation
      list($tbw, $tbh) = Graph::TextSize($content, $font_size,
        $style['font_adjust'], $this->encoding, 0, $font_size);

      if($anchor == 'middle') {
        $text['x'] = $x;
      } elseif($anchor == 'start') {
        $text['x'] = $x + $label_pad_x + ($tw - $tbw) / 2;
      } else {
        $text['x'] = $x - $label_pad_x - ($tw - $tbw) / 2; 
      }
      $text['y'] = $y + $label_h / 2 - $tbh / 2 + $text_baseline;

    } else {

      if($anchor == 'start') {
        $text['x'] = $x + $label_pad_x;
      } elseif($anchor == 'end') {
        $text['x'] = $x - $label_pad_x;
      } else {
        $text['x'] = $x;
      }
      $text['y'] = $y + $label_pad_y + $text_baseline;
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

      /** DEBUG: text position and rotation point 
      $label_markup .= $this->graph->Element('circle',
        array('cx' => $text['x'], 'cy' => $text['y'], 'r' => 2, 'fill' => '#f0f'));
      $label_markup .= $this->graph->Element('circle',
        array('cx' => $rx, 'cy' => $ry, 'r' => 2));
      **/
    }

    if($anchor != 'start')
      $text['text-anchor'] = $anchor;
    if(!empty($style['font_weight']) && $style['font_weight'] != 'normal')
      $text['font-weight'] = $style['font_weight'];

    $surround = array();
    $element = null;

    if($type == 'box') {
      $element = $this->BoxLabel($x, $y, $label_w, $label_h, $style, $surround);
    } elseif($type == 'bubble') {

      $style['tail_direction'] = $this->graph->DataLabelTailDirection($dataset,
        $index, $hpos, $vpos);
      $element = $this->BubbleLabel($x, $y, $label_w, $label_h, $style, $surround);

    } elseif($type == 'line') {

      $style['tail_direction'] = $this->graph->DataLabelTailDirection($dataset,
        $index, $hpos, $vpos);
      $element = $this->LineLabel($x, $y, $label_w, $label_h, $style, $surround);
    }

    // if there is a box or bubble, draw it
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

    if(!empty($back_colour)) {
      $outline = array(
        'stroke-width' => '3px',
        'stroke' => $back_colour,
        'stroke-linejoin' => 'round',
      );
      $t1 = array_merge($outline, $text);
      $label_markup .= $this->graph->Text($content, $font_size, $t1);
    }
    $label_markup .= $this->graph->Text($content, $font_size, $text);

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
   * Individual label styles from the structured data item
   */
  protected function ItemStyles(&$style, &$item)
  {
    $options = array(
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
      'shadow_opacity' => 'data_label_shadow_opacity',
    );

    // overwrite any style options that the item has set
    $v = $item->Data('data_label_padding');
    if(!is_null($v))
      $style['pad_x'] = $style['pad_y'] = $v;
    foreach($options as $s => $k) {
      $v = $item->Data($k);
      if(!is_null($v))
        $style[$s] = $v;
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

    // make sure line is long enough to not look like part of text
    $llen = max($style['font_size'], $style['tail_length']);

    // start at edge of text bounding box
    $w2a = $w2;
    $h2a = $w2 * tan($a);
    if(abs($h2a) > $h2) {
      $h2a = $h2;
      $w2a = $h2 / tan($a);
    }
    if(($a < M_PI && $h2a < 0) || ($a > M_PI && $h2a > 0)) {
      $h2a = -$h2a;
      $w2a = -$w2a;
    }
     
    $x1 = $x + $w2 + $w2a;
    $y1 = $y + $h2 + $h2a;
    $x2 = $llen * cos($a);
    $y2 = $llen * sin($a);
    $surround['d'] = "M{$x1} {$y1}l{$x2} {$y2}";
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
};

