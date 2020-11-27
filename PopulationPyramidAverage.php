<?php
/**
 * Copyright (C) 2020 Graham Breach
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
 * Deal with the strange way population pyramids work
 */
class PopulationPyramidAverage extends Average {

  private $graph;
  private $lines = [];

  /**
   * Calculates the mean average for a dataset
   */
  protected function calculate(&$values, $dataset)
  {
    $val = parent::calculate($values, $dataset);
    if($val === null)
      return $val;

    return $dataset % 2 ? $val : -$val;
  }

  /**
   * Need to sign-correct the average value for display
   */
  protected function getTitle(&$graph, $avg, $dataset)
  {
    return parent::getTitle($graph, $dataset % 2 ? $avg : -$avg, $dataset);
  }
}
