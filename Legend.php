<?php
/**
 * Copyright (C) 2016-2019 Graham Breach
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

  private $graph;
  private $entry_details = [];
  private $autohide;
  private $back_colour;
  private $colour;
  private $columns;
  private $draggable;
  private $entries;
  private $entry_height;
  private $entry_width;
  private $font;
  private $font_adjust;
  private $font_size;
  private $font_weight;
  private $padding;
  private $position;
  private $round;
  private $shadow_opacity;
  private $show_empty;
  private $stroke_colour;
  private $stroke_width;
  private $text_side;
  private $title;
  private $title_colour;
  private $title_font;
  private $title_font_adjust;
  private $title_font_size;
  private $title_font_weight;
  private $type;

  public function __construct(&$graph)
  {
    $this->graph =& $graph;

    // copy options to class
    $opts = ['autohide', 'back_colour', 'colour', 'columns', 'draggable',
      'entries', 'entry_height', 'entry_width', 'font', 'font_adjust',
      'font_size', 'font_weight', 'padding', 'position', 'round',
      'shadow_opacity', 'show_empty', 'stroke_colour', 'stroke_width',
      'text_side', 'title', 'title_font_weight', 'type'];
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
    $entry = count($this->entry_details);
    $itext = $item->legend_text;
    if(!is_null($itext))
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

    // if there is no text, don't add the entry
    if($text != '')
      $this->entry_details[$entry] = new LegendEntry($item, $text, $style_info);
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
      list($w, $h) = $svg_text->measure($entry->text, $font_size, 0, $font_size);
      if($w > $max_width)
        $max_width = $w;
      if($h > $max_height)
        $max_height = $h;
      $entry->width = $w;
      $entry->height = $h;
    }

    $title = '';
    $title_width = $entries_x = 0;
    $start_y = $padding = $this->padding;

    $w = $this->entry_width;
    $x = 0;
    $entry_height = max($max_height, $this->entry_height);

    // make room for title
    if($this->title != '') {
      $title_font_size = $this->title_font_size;
      $svg_text_title = new Text($this->graph, $this->title_font,
        $this->title_font_adjust);
      list($tw, $th) = $svg_text_title->measure($this->title, $title_font_size,
        0, $title_font_size);
      $title_width = $tw + $padding * 2;
      $start_y += $th + $padding;
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
    foreach($entries as $entry) {
      // position the graph element
      $e_y = $y + ($entry_height - $this->entry_height) / 2;
      $element = $this->graph->drawLegendEntry($x, $e_y, $w,
        $this->entry_height, $entry);
      if(!empty($element)) {
        // position the text element
        $text['y'] = $y + $baseline + ($entry_height - $entry->height) / 2;
        $text_element = $svg_text->text($entry->text, $font_size, $text);
        $text_columns[$column] .= $text_element;
        $entry_columns[$column] .= $element;
        $y += $entry_height + $padding;

        ++$valid_entries;
        if(++$column_entry == $per_column) {
          $column_entry = 0;
          $y = $start_y;
          ++$column;
        }
      }
    }
    // if there's nothing to go in the legend, stop now
    if(!$valid_entries)
      return '';

    if($this->text_side == 'left') {
      $text_x_offset = $max_width + $padding;
      $entries_x_offset = $max_width + $padding * 2;
    } else {
      $text_x_offset = $w + $padding * 2;
      $entries_x_offset = $padding;
    }
    $longest_width = $padding * (2 * $columns + 1) +
      ($this->entry_width + $max_width) * $columns;
    $column_width = $padding * 2 + $this->entry_width + $max_width;
    $width = max($title_width, $longest_width);
    $height = $start_y + $per_column * ($entry_height + $padding);

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
      'fill' => $this->graph->parseColour($this->back_colour),
      'width' => $width,
      'height' => $height,
    ];
    if($this->round > 0)
      $box['rx'] = $box['ry'] = $this->round;
    if($this->stroke_width) {
      $box['stroke-width'] = $this->stroke_width;
      $box['stroke'] = $this->stroke_colour;
    }
    $rect = $this->graph->element('rect', $box);
    if($this->title != '') {
      $text['x'] = $width / 2;
      $text['y'] = $padding + $svg_text_title->baseline($title_font_size);
      $text['text-anchor'] = 'middle';
      if($this->title_font != $this->font)
        $text['font-family'] = $this->title_font;
      if($title_font_size != $font_size)
        $text['font-size'] = $title_font_size;
      if($this->title_font_weight != $this->font_weight)
        $text['font-weight'] = $this->title_font_weight;
      if($this->title_colour != $this->colour)
        $text['fill'] = $this->title_colour;
      $title = $svg_text_title->text($this->title, $title_font_size, $text);
    }

    // create group to contain whole legend
    list($left, $top) = $this->graph->parsePosition($this->position,
      $width, $height);

    $xform = new Transform;
    $xform->translate($left, $top);
    $group = [
      'font-family' => $this->font,
      'font-size' => $font_size,
      'fill' => $this->colour,
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

    if($this->autohide)
      $this->graph->javascript->autoHide($group);
    if($this->draggable)
      $this->graph->javascript->setDraggable($group);
    return $this->graph->element('g', $group, null, $rect . $title . $parts);
  }

  /**
   * Returns the list of entries in the correct order
   */
  private function getEntries()
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
      return $entries;
    }

    $entries = $this->entry_details;
    if(strpos($entry_order, 'sort') !== false) {
      usort($entries, function($a, $b) {
        if($a->text == $b->text)
          return 0;
        return $a->text > $b->text ? 1 : -1;
      });
    }

    if(strpos($entry_order, 'reverse') !== false)
      $entries = array_reverse($entries, true);

    return $entries;
  }
}

