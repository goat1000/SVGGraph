<?php
/**
 * Copyright (C) 2019-2022 Graham Breach
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

trait GroupedBarTrait {

  use MultiGraphTrait;

  protected $group_bar_spacing;
  protected $dataset_offsets = [];

  /**
   * Draws the bars
   */
  protected function drawBars()
  {
    $this->barSetup();
    $dataset_count = count($this->multi_graph);
    $datasets = $this->multi_graph->getEnabledDatasets();

    // bars must be drawn from left to right, since they might overlap
    $bars = '';
    $legend_entries = [];
    foreach($this->multi_graph as $bnum => $itemlist) {
      $item = $itemlist[0];
      $bar_pos = $this->gridPosition($item, $bnum);
      if($bar_pos !== null) {
        for($j = 0; $j < $dataset_count; ++$j) {
          if(!in_array($j, $datasets))
            continue;
          $item = $itemlist[$j];
          $bars .= $this->drawBar($item, $bnum, 0, $this->datasetYAxis($j), $j);
          $legend_entries[$j][$bnum] = $item;
        }
      }
    }

    // legend entries are added in order of dataset
    foreach($legend_entries as $j => $dataset)
      foreach($dataset as $bnum => $item)
        $this->setBarLegendEntry($j, $bnum, $item);

    return $bars;
  }

  /**
   * Sets up bar details
   */
  protected function barSetup()
  {
    parent::barSetup();
    $datasets = $this->multi_graph->getEnabledDatasets();
    $dataset_count = count($datasets);

    list($chunk_width, $bspace, $chunk_unit_width) =
      $this->barPosition($this->getOption('bar_width'), $this->getOption('bar_width_min'),
        $this->x_axes[$this->main_x_axis]->unit(), $dataset_count,
        $this->getOption('bar_space'), $this->getOption('group_space'));
    $this->group_bar_spacing = $chunk_unit_width;
    $this->setBarWidth($chunk_width, $bspace);

    $offset = 0;
    foreach($datasets as $d) {
      $this->dataset_offsets[$d] = $offset;
      ++$offset;
    }
  }

  /**
   * Fills in the x and width of bar
   */
  protected function barX($item, $index, &$bar, $axis, $dataset)
  {
    $bar_x = $this->gridPosition($item, $index);
    if($bar_x === null)
      return null;

    $d_offset = $this->dataset_offsets[$dataset];
    $bar['x'] = $bar_x + $this->calculated_bar_space +
        ($d_offset * $this->group_bar_spacing);
    $bar['width'] = $this->calculated_bar_width;
    return $bar_x;
  }

  /**
   * Calculates the bar width, gap to first bar, gap between bars
   * returns an array containing all three
   */
  static function barPosition($bar_width, $bar_width_min, $unit_width,
    $group_size, $bar_space, $group_space)
  {
    if(is_numeric($bar_width) && $bar_width >= 1) {
      return self::barPositionFixed($bar_width, $unit_width,
        $group_size, $group_space);
    } else {
      // bar width dependent on space
      $gap_count = $group_size - 1;
      $gap = $gap_count > 0 ? $group_space : 0;

      $bar_width = $bar_space >= $unit_width ? '1' : $unit_width - $bar_space;
      if($gap_count > 0 && $gap * $gap_count > $bar_width - $group_size)
        $gap = ($bar_width - $group_size) / $gap_count;
      $bar_width = ($bar_width - ($gap * ($group_size - 1)))
        / $group_size;

      if($bar_width < $bar_width_min)
        return self::barPositionFixed($bar_width_min, $unit_width,
          $group_size, $group_space);
      $spacing = $bar_width + $gap;
      $offset = $bar_space / 2;
    }
    return [$bar_width, $offset, $spacing];
  }

  /**
   * Calculate bar width, gaps, using fixed bar width
   */
  static function barPositionFixed($bar_width, $unit_width, $group_size,
    $group_space)
  {
    $gap = $group_size > 1 ? $group_space : 0;
    if($group_size > 1 && ($bar_width + $gap) * $group_size > $unit_width) {

      // bars don't fit with group_space option, so they must overlap
      // (and make sure the bars are at least 1 pixel apart)
      $spacing = max(1, ($unit_width - $bar_width) / ($group_size - 1));
      $offset = 0;
    } else {
      // space the bars group_space apart, centred in unit space
      $spacing = $bar_width + $gap;
      $offset = max(0, ($unit_width - ($spacing * $group_size)) / 2);
    }
    return [$bar_width, $offset, $spacing];
  }
}

