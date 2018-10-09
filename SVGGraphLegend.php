<?php
/**
 * Copyright (C) 2016-2018 Graham Breach
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

/**
 * Draws legend
 */
class SVGGraphLegend {

  private $graph;
  private $legend_entries = array();
  private $reverse = FALSE;
  private $entries = array();
  private $type = 'all';
  private $show_empty = false;

  public function __construct(&$graph, $reverse)
  {
    $this->graph = $graph;
    $this->legend_entries = $graph->legend_entries;
    $this->reverse = $reverse;
    $this->type = $graph->legend_type;
    $this->show_empty = $graph->legend_show_empty;
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
   * Sets the style information for an entry
   */
  public function SetEntry($dataset, $index, $item, $style_info)
  {
    // ignore entry if empty and legend_show_empty not enabled
    if(!$this->show_empty && !$this->graph->IsVisible($item, $dataset))
      return;

    // find the text first
    $text = '';
    $entry = count($this->entries);
    $itext = $item->Data('legend_text');
    if(!is_null($itext))
      $text = $itext;

    if($text == '') {
      // no text from structured data
      if($this->type == 'none')
        return;

      if($this->type == 'dataset') {
        // one entry per dataset
        $entry = $dataset;
        if(!isset($this->entries[$entry]) && 
          isset($this->legend_entries[$dataset]))
          $text = $this->legend_entries[$dataset];
      } else { // $this->type == 'all'
        // one entry per data item
        if(isset($this->legend_entries[$index]))
          $text = $this->legend_entries[$index];
      }
    }

    // if there is no text, don't add the entry
    if($text != '')
      $this->entries[$entry] = new SVGGraphLegendEntry($item, $text, $style_info);
  }

  /**
   * Draws the legend
   */
  public function Draw()
  {
    $entry_count = count($this->entries);
    if($entry_count < 1)
      return '';

    $encoding = $this->graph->encoding;

    // find the largest width / height
    $font_size = $this->legend_font_size;
    $max_width = $max_height = 0;
    $svg_text = new SVGGraphText($this->graph, $this->legend_font,
      $this->legend_font_adjust);
    $baseline = $svg_text->Baseline($font_size);
    foreach($this->entries as $entry) {
      list($w, $h) = $svg_text->Measure($entry->text, $font_size, 0, $font_size);
      if($w > $max_width)
        $max_width = $w;
      if($h > $max_height)
        $max_height = $h;
      $entry->width = $w;
      $entry->height = $h;
    }

    $title = '';
    $title_width = $entries_x = 0;
    $start_y = $padding = $this->legend_padding;

    $w = $this->legend_entry_width;
    $x = 0;
    $entry_height = max($max_height, $this->legend_entry_height);

    // make room for title
    if($this->legend_title != '') {
      $title_font = $this->graph->GetOption('legend_title_font', 'legend_font');
      $title_font_size = $this->graph->GetOption('legend_title_font_size',
        'legend_font_size');
      $title_font_adjust = $this->graph->GetOption('legend_title_font_adjust',
        'legend_font_adjust');
      $title_colour = $this->graph->GetOption('legend_title_colour',
        'legend_colour');

      $svg_text_title = new SVGGraphText($this->graph, $title_font,
        $title_font_adjust);
      list($tw, $th) = $svg_text_title->Measure($this->legend_title,
        $title_font_size, 0, $title_font_size);
      $title_width = $tw + $padding * 2;
      $start_y += $th + $padding;
    }

    $columns = max(1, min(ceil($this->legend_columns), $entry_count));
    $per_column = ceil($entry_count / $columns);
    $columns = ceil($entry_count / $per_column);
    $column = 0;

    $text = array('x' => 0);
    $entries = $this->reverse ?
      array_reverse($this->entries, true) : $this->entries;

    $column_entry = 0;
    $y = $start_y;
    $text_columns = array_fill(0, $columns, '');
    $entry_columns = array_fill(0, $columns, '');
    $valid_entries = 0;
    foreach($entries as $entry) {
      // position the graph element
      $e_y = $y + ($entry_height - $this->legend_entry_height) / 2;
      $element = $this->graph->DrawLegendEntry($x, $e_y, $w,
        $this->legend_entry_height, $entry);
      if(!empty($element)) {
        // position the text element
        $text['y'] = $y + $baseline + ($entry_height - $entry->height) / 2;
        $text_element = $svg_text->Text($entry->text, $font_size, $text);
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

    if($this->legend_text_side == 'left') {
      $text_x_offset = $max_width + $padding;
      $entries_x_offset = $max_width + $padding * 2;
    } else {
      $text_x_offset = $w + $padding * 2;
      $entries_x_offset = $padding;
    }
    $longest_width = $padding * (2 * $columns + 1) +
      ($this->legend_entry_width + $max_width) * $columns;
    $column_width = $padding * 2 + $this->legend_entry_width +
      $max_width;
    $width = max($title_width, $longest_width);
    $height = $start_y + $per_column * ($entry_height + $padding);

    // centre the entries if the title makes the box bigger
    if($width > $longest_width) {
      $offset = ($width - $longest_width) / 2;
      $entries_x_offset += $offset;
      $text_x_offset += $offset;
    }

    $text_group = array('transform' => "translate($text_x_offset,0)");
    if($this->legend_text_side == 'left')
      $text_group['text-anchor'] = 'end';
    $entries_group = array('transform' => "translate($entries_x_offset,0)");

    $parts = '';
    foreach($entry_columns as $col) {
      $parts .= $this->graph->Element('g', $entries_group, null, $col);
      $entries_x_offset += $column_width;
      $entries_group['transform'] = "translate($entries_x_offset,0)";
    }
    foreach($text_columns as $col) {
      $parts .= $this->graph->Element('g', $text_group, null, $col);
      $text_x_offset += $column_width;
      $text_group['transform'] = "translate($text_x_offset,0)";
    }

    // create box and title
    $box = array(
      'fill' => $this->graph->ParseColour($this->legend_back_colour),
      'width' => $width,
      'height' => $height,
    );
    if($this->legend_round > 0)
      $box['rx'] = $box['ry'] = $this->legend_round;
    if($this->legend_stroke_width) {
      $box['stroke-width'] = $this->legend_stroke_width;
      $box['stroke'] = $this->legend_stroke_colour;
    }
    $rect = $this->graph->Element('rect', $box);
    if($this->legend_title != '') {
      $text['x'] = $width / 2;
      $text['y'] = $padding + $svg_text_title->Baseline($title_font_size);
      $text['text-anchor'] = 'middle';
      if($title_font != $this->legend_font)
        $text['font-family'] = $title_font;
      if($title_font_size != $font_size)
        $text['font-size'] = $title_font_size;
      if($this->legend_title_font_weight != $this->legend_font_weight)
        $text['font-weight'] = $this->legend_title_font_weight;
      if($title_colour != $this->legend_colour)
        $text['fill'] = $title_colour;
      $title = $svg_text_title->Text($this->legend_title, $title_font_size, $text);
    }

    // create group to contain whole legend
    list($left, $top) = $this->graph->ParsePosition($this->legend_position,
      $width, $height);

    $group = array(
      'font-family' => $this->legend_font,
      'font-size' => $font_size,
      'fill' => $this->legend_colour,
      'transform' => "translate($left,$top)",
    );
    if($this->legend_font_weight != 'normal')
      $group['font-weight'] = $this->legend_font_weight;

    // add shadow if not completely transparent
    if($this->legend_shadow_opacity > 0) {
      $box['x'] = $box['y'] = 2 + ($this->legend_stroke_width / 2);
      $box['fill'] = '#000';
      $box['opacity'] = $this->legend_shadow_opacity;
      unset($box['stroke'], $box['stroke-width']);
      $rect = $this->graph->Element('rect', $box) . $rect;
    }

    if($this->legend_autohide)
      $this->graph->javascript->AutoHide($group);
    if($this->legend_draggable)
      $this->graph->javascript->SetDraggable($group);
    return $this->graph->Element('g', $group, NULL, $rect . $title . $parts);
  }

}

/**
 * A class to hold the details of an entry in the legend
 */
class SVGGraphLegendEntry {

  public $item = NULL;
  public $text = NULL;
  public $style = NULL;
  public $width = 0;
  public $height = 0;

  public function __construct($item, $text, $style)
  {
    $this->item = $item;
    $this->text = $text;
    $this->style = $style;
  }
}

