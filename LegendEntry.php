<?php
/**
 * Copyright (C) 2016-2021 Graham Breach
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
 * A class to hold the details of an entry in the legend
 */
class LegendEntry {

  public $item = null;
  public $text = null;
  public $link = null;
  public $style = null;
  public $width = 0;
  public $height = 0;

  public function __construct($item, $text, $link, $style)
  {
    $this->item = $item;
    $this->text = $text;
    $this->link = $link;
    $this->style = $style;
  }
}

