<?php
/**
 * Copyright (C) 2016-2023 Graham Breach
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
 * Draws legend
 */
class Legend {

  protected $graph;
  protected $entry_details = [];
  protected $autohide;
  protected $autohide_opacity;
  protected $back_colour;
  protected $colour;
  protected $columns;
  protected $draggable;
  protected $entries;
  protected $entry_height;
  protected $entry_width;
  protected $font;
  protected $font_adjust;
  protected $font_size;
  protected $font_weight;
  protected $position;
  protected $round;
  protected $shadow_opacity;
  protected $show_empty;
  protected $stroke_colour;
  protected $stroke_width;
  protected $text_side;
  protected $line_spacing;
  protected $title;
  protected $title_colour;
  protected $title_font;
  protected $title_font_adjust;
  protected $title_font_size;
  protected $title_font_weight;
  protected $title_line_spacing;
  protected $title_link;
  protected $type;
  protected $unique_fields;

  public function __construct(&$graph)
  {
    $this->graph =& $graph;

    // copy options to class
    $opts = ['autohide', 'autohide_opacity', 'back_colour', 'colour', 'columns',
      'draggable', 'entries', 'entry_height', 'entry_width', 'font',
      'font_adjust', 'font_size', 'font_weight', 'position', 'round',
      'shadow_opacity', 'show_empty', 'stroke_colour', 'stroke_width',
      'text_side', 'title', 'title_link', 'title_font_weight', 'type',
      'unique_fields'];
    foreach($opts as $opt) {
      $this->{$opt} = $graph->getOption('legend_' . $opt);
    }

    // slightly more complicated options
    $this->title_colour = $graph->getOption('legend_title_colour',
      'legend_colour');
    $this->title_font = $graph->getOption('legend_title_font', 'legend_font');
    $this->title_font_adjust = $graph->getOption('legend_title_font_adjust',
      'legend_font_adjust');
    $this->title_font_size = $graph->getOption('legend_title_font_size',
      'legend_font_size');
    $this->line_spacing = $graph->getOption('legend_line_spacing');
    if($this->line_spacing === null || $this->line_spacing < 1)
      $this->line_spacing = $this->font_size;
    $this->title_line_spacing = $graph->getOption('legend_title_line_spacing');
    if($this->title_line_spacing === null || $this->title_line_spacing < 1)
      $this->title_line_spacing = $this->title_font_size;
  }

  /**
   * Sets the style information for an entry
   */
  public function setEntry($dataset, $index, $item, $style_info)
  {
    // ignore entry if empty and legend_show_empty not enabled
    if(!$this->show_empty && !$this->graph->isVisible($item, $dataset))
      return;

    // find the text first
    $text = '';
    $link = null;
    $entry = count($this->entry_details);
    $itext = $item->legend_text;
    if($itext !== null)
      $text = $itext;

    if($text == '') {
      // no text from structured data
      if($this->type == 'none')
        return;

      if($this->type == 'dataset') {
        // one entry per dataset
        $entry = $dataset;
        if(!isset($this->entry_details[$entry]) &&
          isset($this->entries[$dataset]))
          $text = $this->entries[$dataset];
      } else { // $this->type == 'all'
        // one entry per data item
        if(isset($this->entries[$index]))
          $text = $this->entries[$index];
      }
    }

    // split out link
    if(is_array($text))
      list($text, $link) = $text;

    if($this->unique_fields) {
      // prevent adding multiple entries with the same text
      foreach($this->entry_details as $e) {
        if($e->text == $text)
          return;
      }
    }

    // if there is no text, don't add the entry
    if($text != '')
      $this->entry_details[$entry] = new LegendEntry($item, $text, $link,
        $style_info);
  }

  /**
   * Draws the legend
   */
  public function draw()
  {
    $entries = $this->getEntries();
    $entry_count = count($entries);
    if($entry_count < 1)
      return '';

    $encoding = $this->graph->encoding;

    // find the largest width / height
    $font_size = $this->font_size;
    $max_width = $max_height = 0;
    $svg_text = new Text($this->graph, $this->font, $this->font_adjust);
    $baseline = $svg_text->baseline($font_size);
    foreach($this->entry_details as $entry) {
      list($w, $h) = $svg_text->measure($entry->text, $font_size, 0, $this->line_spacing);
      if($w > $max_width)
        $max_width = $w;
      if($h > $max_height)
        $max_height = $h;
      $entry->width = $w;
      $entry->height = $h;
    }

    $title = '';
    $title_width = $entries_x = 0;
    $padding_y = $this->graph->getOption('legend_padding_y', 'legend_padding', 1);
    $padding_x = $this->graph->getOption('legend_padding_x', 'legend_padding', 1);
    $start_y = $padding_y;

    $w = $this->entry_width;
    $x = 0;
    $entry_height = max($max_height, $this->entry_height);

    // make room for title
    if($this->title != '') {
      $title_font_size = $this->title_font_size;
      $svg_text_title = new Text($this->graph, $this->title_font,
        $this->title_font_adjust);
      list($tw, $th) = $svg_text_title->measure($this->title, $title_font_size,
        0, $this->title_line_spacing);
      $title_width = $tw + $padding_x * 2;
      $start_y += $th + $this->graph->getOption('legend_title_spacing',
        'legend_padding', 1);
    }

    $columns = max(1, min(ceil($this->columns), $entry_count));
    $per_column = ceil($entry_count / $columns);
    $columns = ceil($entry_count / $per_column);
    $column = 0;

    $text = ['x' => 0];

    $column_entry = 0;
    $y = $start_y;
    $text_columns = array_fill(0, $columns, '');
    $entry_columns = array_fill(0, $columns, '');
    $valid_entries = 0;
    $spacing = $this->graph->getOption('legend_spacing', 'legend_padding', 1);
    $entry_space = $this->graph->getOption('legend_entry_spacing',
      'legend_padding', 1);
    $col_space = $this->graph->getOption('legend_column_spacing',
      'legend_padding', 1);

    foreach($entries as $entry) {
      $y = $start_y + $column_entry * ($entry_height + $spacing);

      // position the graph element
      $e_y = $y + ($entry_height - $this->entry_height) / 2;
      $element = $this->graph->drawLegendEntry($x, $e_y, $w,
        $this->entry_height, $entry);
      if(!empty($element)) {
        // position the text element
        $text['y'] = $y + $baseline + ($entry_height - $entry->height) / 2;
        $text_element = $svg_text->text($entry->text, $this->line_spacing, $text);
        if($entry->link !== null)
          $text_element = $this->graph->getLink($entry, 0, $text_element);
        $text_columns[$column] .= $text_element;
        $entry_columns[$column] .= $element;

        ++$valid_entries;
        if(++$column_entry == $per_column) {
          $column_entry = 0;
          ++$column;
        }
      }
    }
    // if there's nothing to go in the legend, stop now
    if(!$valid_entries)
      return '';

    if($this->text_side == 'left') {
      $text_x_offset = $max_width + $padding_x;
      $entries_x_offset = $max_width + $padding_x + $entry_space;
    } else {
      $text_x_offset = $w + $padding_x + $entry_space;
      $entries_x_offset = $padding_x;
    }
    $longest_width = $padding_x * 2 + ($entry_space * $columns) +
      $col_space * ($columns - 1) +
      ($this->entry_width + $max_width) * $columns;
    $column_width = $col_space + $this->entry_width + $entry_space + $max_width;
    $width = max($title_width, $longest_width);
    $height = $start_y + $per_column * ($entry_height + $spacing) - $spacing
      + $padding_y;

    // centre the entries if the title makes the box bigger
    if($width > $longest_width) {
      $offset = ($width - $longest_width) / 2;
      $entries_x_offset += $offset;
      $text_x_offset += $offset;
    }

    $xform = new Transform;
    $xform->translate($text_x_offset, 0);
    $text_group = ['transform' => $xform];
    if($this->text_side == 'left')
      $text_group['text-anchor'] = 'end';
    $xform = new Transform;
    $xform->translate($entries_x_offset, 0);
    $entries_group = ['transform' => $xform];

    $parts = '';
    foreach($entry_columns as $col) {
      $parts .= $this->graph->element('g', $entries_group, null, $col);
      $entries_x_offset += $column_width;
      $xform = new Transform;
      $xform->translate($entries_x_offset, 0);
      $entries_group['transform'] = $xform;
    }
    foreach($text_columns as $col) {
      $parts .= $this->graph->element('g', $text_group, null, $col);
      $text_x_offset += $column_width;
      $xform = new Transform;
      $xform->translate($text_x_offset, 0);
      $text_group['transform'] = $xform;
    }

    // create box and title
    $box = [
      'fill' => new Colour($this->graph, $this->back_colour),
      'width' => $width,
      'height' => $height,
    ];
    if($this->round > 0)
      $box['rx'] = $box['ry'] = $this->round;
    if($this->stroke_width) {
      $box['stroke-width'] = $this->stroke_width;
      $box['stroke'] = new Colour($this->graph, $this->stroke_colour);
    }
    $rect = $this->graph->element('rect', $box);
    if($this->title != '') {
      $text['x'] = $width / 2;
      $text['y'] = $padding_y + $svg_text_title->baseline($title_font_size);
      $text['text-anchor'] = 'middle';
      if($this->title_font != $this->font)
        $text['font-family'] = $this->title_font;
      if($title_font_size != $font_size)
        $text['font-size'] = $title_font_size;
      if($this->title_font_weight != $this->font_weight)
        $text['font-weight'] = $this->title_font_weight;
      if($this->title_colour != $this->colour)
        $text['fill'] = new Colour($this->graph, $this->title_colour);
      $title = $svg_text_title->text($this->title, $this->title_line_spacing, $text);
      if(!empty($this->title_link)) {
        $item = new \stdClass;
        $item->link = $this->title_link;
        $title = $this->graph->getLink($item, 0, $title);
      }
    }

    // find position for legend
    if(is_array($this->position)) {
      list($px, $py) = $this->position;
      $vx = Coords::parseValue($px);
      $vy = Coords::parseValue($py);
      $c = new Coords($this->graph);
      list($tx, $ty) = $c->transformCoords($px, $py);

      // handle relative positions
      switch($vx['value']) {
      case 'c' : $left = $tx - ($width / 2); break;
      case 'r' : $left = $tx - $width; break;
      default : $left = $tx;
      }
      switch($vy['value']) {
      case 'c' : $top = $ty - ($height / 2); break;
      case 'b' : $top = $ty - $height; break;
      default: $top = $ty;
      }
    } else {
      list($left, $top) = $this->graph->parsePosition($this->position,
        $width, $height);
    }

    // create group to contain whole legend
    $xform = new Transform;
    $xform->translate($left, $top);
    $group = [
      'font-family' => $this->font,
      'font-size' => $font_size,
      'fill' => new Colour($this->graph, $this->colour),
      'transform' => $xform,
    ];
    if($this->font_weight != 'normal')
      $group['font-weight'] = $this->font_weight;

    // add shadow if not completely transparent
    if($this->shadow_opacity > 0) {
      $box['x'] = $box['y'] = 2 + ($this->stroke_width / 2);
      $box['fill'] = '#000';
      $box['opacity'] = $this->shadow_opacity;
      unset($box['stroke'], $box['stroke-width']);
      $rect = $this->graph->element('rect', $box) . $rect;
    }

    if($this->autohide) {
      $o0 = $this->autohide_opacity;
      $o1 = 1;
      if($o0 < 0)
      {
        $o1 = new Number(-$o0);
        $o0 = 1;
        $group['opacity'] = $o1;
      }
      $this->graph->getJavascript()->autoHide($group, $o0, $o1);
    }
    if($this->draggable)
      $this->graph->getJavascript()->setDraggable($group);
    return $this->graph->element('g', $group, null, $rect . $title . $parts);
  }

  /**
   * Filters the entry list
   */
  protected function filterEntries($entries)
  {
    $callback = $this->graph->getOption('legend_entries_callback');
    if(!is_callable($callback))
      return $entries;

    $filtered = call_user_func($callback, $entries);
    $valid = false;
    if(is_array($filtered)) {
      $valid = true;
      foreach($filtered as $e) {
        if(!is_object($e) || get_class($e) !== 'Goat1000\\SVGGraph\\LegendEntry')
          $valid = false;
      }
    }

    if(!$valid)
      throw new \Exception('Callback did not return array of LegendEntry values.');

    return $filtered;
  }

  /**
   * Returns the list of entries in the correct order
   */
  protected function getEntries()
  {
    $entry_order = $this->graph->getOption('legend_order');
    if($entry_order === null || $entry_order == 'auto')
      $entry_order = $this->graph->getLegendOrder();

    if(is_array($entry_order)) {
      $entries = [];
      foreach($entry_order as $e) {
        if(isset($this->entry_details[$e]))
          $entries[] = $this->entry_details[$e];
      }
      return $this->filterEntries($entries);
    }

    $entries = $this->entry_details;
    if(!empty($entry_order)) {
      if(strpos($entry_order, 'sort') !== false) {
        usort($entries, function($a, $b) {
          if($a->text == $b->text)
            return 0;
          return $a->text > $b->text ? 1 : -1;
        });
      }

      if(strpos($entry_order, 'reverse') !== false)
        $entries = array_reverse($entries, true);
    }

    return $this->filterEntries($entries);
  }
}

