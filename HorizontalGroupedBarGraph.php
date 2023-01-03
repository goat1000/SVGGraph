<?php
/**
 * Copyright (C) 2011-2022 Graham Breach
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

class HorizontalGroupedBarGraph extends HorizontalBarGraph {

  use GroupedBarTrait;

  public function __construct($w, $h, $settings, $fixed_settings = [])
  {
    $fixed = [ 'single_axis' => true ];
    $fixed_settings = array_merge($fixed, $fixed_settings);
    parent::__construct($w, $h, $settings, $fixed_settings);
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
      $this->barPosition($this->getOption('bar_width'),
        $this->getOption('bar_width_min'),
        $this->y_axes[$this->main_y_axis]->unit(), $dataset_count,
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
   * Returns an array with x, y, width and height set
   */
  protected function barDimensions($item, $index, $start, $axis, $dataset)
  {
    $bar_y = $this->gridPosition($item, $index);
    if($bar_y === null)
      return [];

    $d_offset = $this->dataset_offsets[$dataset];
    $bar = [
      'y' => $bar_y - $this->calculated_bar_space -
        ($d_offset * $this->group_bar_spacing) - $this->calculated_bar_width,
      'height' => $this->calculated_bar_width,
    ];

    $this->barY($item->value, $bar, $start, $axis);
    return $bar;
  }
}

