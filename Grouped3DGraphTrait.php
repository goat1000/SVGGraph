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

trait Grouped3DGraphTrait {

  use GroupedBarTrait;

  /**
   * Override AdjustAxes to change depth
   */
  protected function adjustAxes(&$x_len, &$y_len)
  {
    /**
     * The depth is roughly 1/$num - but it must also take into account the
     * bar and group spacing, which is where things get messy
     */
    $ends = $this->getAxisEnds();
    $num = $ends['k_max'][0] - $ends['k_min'][0] + 1;

    $block = $x_len / $num;
    $datasets = $this->multi_graph->getEnabledDatasets();
    $group = count($datasets);
    $a = $this->getOption('bar_space');
    $b = $this->getOption('group_space');
    $c = (($block) - $a - ($group - 1) * $b) / $group;
    $d = ($a + $c) / $block;
    $this->depth = $d;
    return parent::adjustAxes($x_len, $y_len);
  }
}

