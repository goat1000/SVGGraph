<?php
/**
 * Copyright (C) 2013-2019 Graham Breach
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

class StackedCylinderGraph extends CylinderGraph {

  use StackedBarTrait;

  public function __construct($w, $h, $settings, $fixed_settings = [])
  {
    $fixed = [ 'single_axis' => true ];
    $fixed_settings = array_merge($fixed, $fixed_settings);
    parent::__construct($w, $h, $settings, $fixed_settings);
  }

  /**
   * Sets whether a bar is visible or not
   */
  protected function setBarVisibility($dataset, DataItem $item, $top)
  {
    $this->bar_visibility[$dataset][$item->key] = ($top || $item->value != 0);
  }
}

