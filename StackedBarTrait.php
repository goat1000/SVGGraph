<?php
/**
 * Copyright (C) 2019-2020 Graham Breach
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

trait StackedBarTrait {

  use MultiGraphTrait;

  protected $bar_visibility = [];

  // used to determine where the total label should go
  protected $last_position_pos = [];
  protected $last_position_neg = [];

  /**
   * Draws the bars
   */
  protected function drawBars()
  {
    $this->barSetup();

    $chunk_count = count($this->multi_graph);
    $datasets = $this->multi_graph->getEnabledDatasets();
    $bars = '';
    $legend_entries = [];
    foreach($this->multi_graph as $bnum => $itemlist) {
      $item = $itemlist[0];

      // sort the values from bottom to top, assigning position
      $yplus = $yminus = 0;
      $chunk_values = [];
      for($j = 0; $j < $chunk_count; ++$j) {
        if(!in_array($j, $datasets))
          continue;
        $item = $itemlist[$j];
        if($item->value !== null) {
          if($item->value < 0) {
            array_unshift($chunk_values, [$j, $item, $yminus]);
            $yminus += $item->value;
          } else {
            $chunk_values[] = [$j, $item, $yplus];
            $yplus += $item->value;
          }
        }
      }

      $bar_count = count($chunk_values);
      $b = 0;
      foreach($chunk_values as $chunk) {
        list($j, $item, $start) = $chunk;

        $top = (++$b == $bar_count);
        $this->setBarVisibility($j, $item, $top);

        $legend_entries[$j][$bnum] = $item;
        $bars .= $this->drawBar($item, $bnum, $start, null, $j, ['top' => $top]);
      }

      $this->barTotals($item, $bnum, $yplus, $yminus, $j);
    }

    // assign legend entries in order of datasets
    foreach($legend_entries as $j => $dataset)
      foreach($dataset as $bnum => $item)
        $this->setBarLegendEntry($j, $bnum, $item);

    return $bars;
  }

  /**
   * Sets whether a bar is visible or not
   */
  protected function setBarVisibility($dataset, DataItem $item, $top)
  {
    $this->bar_visibility[$dataset][$item->key] = ($item->value != 0);
  }

  /**
   * Displays the bar totals
   */
  public function barTotals(DataItem $item, $bnum, $yplus, $yminus, $dataset)
  {
    $bar_x = $this->gridPosition($item, $bnum);
    if($this->show_bar_totals && $bar_x !== null) {
      if($yplus) {
        $bar = $this->barDimensions($item, $bnum, 0, null, $dataset);
        $this->barY($yplus, $bar);
        if(is_callable($this->bar_total_callback)) {
          $total = call_user_func($this->bar_total_callback, $item->key, $yplus);
        } else {
          $total = new Number($yplus);
          $total = $total->format();
        }
        $this->addContentLabel('totalpos-' . $dataset, $bnum,
          $bar['x'], $bar['y'], $bar['width'], $bar['height'], $total);
      }
      if($yminus) {
        $bar = $this->barDimensions($item, $bnum, 0, null, $dataset);
        $this->barY($yminus, $bar);
        if(is_callable($this->bar_total_callback)) {
          $total = call_user_func($this->bar_total_callback, $item->key, $yminus);
        } else {
          $total = new Number($yminus);
          $total = $total->format();
        }
        $this->addContentLabel('totalneg-' . $dataset, $bnum,
          $bar['x'], $bar['y'], $bar['width'], $bar['height'], $total);
      }
    }
  }

  /**
   * Overridden to prevent drawing on other bars
   */
  public function dataLabelPosition($dataset, $index, &$item, $x, $y, $w, $h,
    $label_w, $label_h)
  {
    list($pos, $target) = parent::dataLabelPosition($dataset, $index, $item,
      $x, $y, $w, $h, $label_w, $label_h);
    if(!is_numeric($dataset)) {
      // doing this supports stacked grouped bar graph totals too
      list($d) = explode('-', $dataset);
      if($d === 'totalpos') {
        if(isset($this->last_position_pos[$index])) {
          list($lpos, $l_h) = $this->last_position_pos[$index];
          list($hpos, $vpos) = Graph::translatePosition($lpos);
          if($vpos == 'ot') {
            $num_offset = new Number(-$l_h);
            return ['above 0 ' . $num_offset, $target];
          }
        }
        return ['above', $target];
      }
      if($d === 'totalneg') {
        if(isset($this->last_position_neg[$index])) {
          list($lpos, $l_h) = $this->last_position_neg[$index];
          list($hpos, $vpos) = Graph::translatePosition($lpos);
          if($vpos == 'ob') {
            $num_offset = new Number($l_h);
            return ['below 0 ' . $num_offset, $target];
          }
        }
        return ['below', $target];
      }
    }
    if($label_h > $h && Graph::isPositionInside($pos))
      $pos = str_replace(['top','bottom','above','below'], 'middle', $pos);

    if($item->value > 0)
      $this->last_position_pos[$index] = [$pos, $label_h];
    else
      $this->last_position_neg[$index] = [$pos, $label_h];
    return [$pos, $target];
  }

  /**
   * Returns the style options for bar labels (and totals)
   */
  public function dataLabelStyle($dataset, $index, &$item)
  {
    $style = parent::dataLabelStyle($dataset, $index, $item);

    if(strpos($dataset, 'total') === 0) {

      // total settings can override label settings
      $simple = [
        'font', 'font_size', 'font_weight', 'space', 'type', 'fill',
        'font_adjust', 'angle', 'round', 'shadow_opacity',
        'tail_width', 'tail_length',
      ];
      foreach($simple as $opt) {
        $val = $this->getOption('bar_total_' . $opt);
        if(!empty($val))
          $style[$opt] = $val;
      }

      $colour = new Colour($this, $this->getOption('bar_total_colour'));
      $back_colour = new Colour($this, $this->getOption('bar_total_back_colour'));
      if(!$colour->isNone())
        $style['colour'] = $colour;
      if(!$back_colour->isNone())
        $style['back_colour'] = $back_colour;
      $stroke = $this->getOption('bar_total_outline_colour');
      $stroke_width = $this->getOption('bar_total_outline_thickness');
      $pad_x = $this->getOption('bar_total_padding_x', 'bar_total_padding');
      $pad_y = $this->getOption('bar_total_padding_y', 'bar_total_padding');

      if(!empty($stroke))
        $style['stroke'] = $stroke;
      if(!empty($stroke_width))
        $style['stroke_width'] = $stroke_width;
      if(!empty($pad_x))
        $style['pad_x'] = $pad_x;
      if(!empty($pad_y))
        $style['pad_y'] = $pad_y;
    }
    return $style;
  }

  /**
   * Returns the maximum (stacked) value
   */
  public function getMaxValue()
  {
    return $this->multi_graph->getMaxSumValue();
  }

  /**
   * Returns the minimum (stacked) value
   */
  public function getMinValue()
  {
    return $this->multi_graph->getMinSumValue();
  }

  /**
   * Returns TRUE if the item is visible on the graph
   */
  public function isVisible($item, $dataset = 0)
  {
    return isset($this->bar_visibility[$dataset][$item->key]) &&
      $this->bar_visibility[$dataset][$item->key];
  }

  /**
   * Returns the ordering for legend entries
   */
  public function getLegendOrder()
  {
    return 'reverse';
  }
}

