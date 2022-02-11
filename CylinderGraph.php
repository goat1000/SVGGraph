<?php
/**
 * Copyright (C) 2012-2022 Graham Breach
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

class CylinderGraph extends Bar3DGraph {

  public function __construct($w, $h, array $settings, array $fixed_settings = [])
  {
    $this->bar_class = 'Goat1000\\SVGGraph\\Bar3DCylinder';
    parent::__construct($w, $h, $settings, $fixed_settings);
  }

  /**
   * Set the bar width and space
   */
  protected function setBarWidth($width, $space)
  {
    parent::setBarWidth($width, $space);

    // translation for cylinders added to 3D bar offset
    list($sx, $sy) = $this->project(0, 0, $width);
    $this->tx += ($width + $sx) / 2;
    $this->ty += $sy / 2;
  }
}
