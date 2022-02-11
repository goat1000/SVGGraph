<?php
/**
 * Copyright (C) 2009-2022 Graham Breach
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

class HorizontalBar3DGraph extends HorizontalThreeDGraph {

  use HorizontalBarGraphTrait {
    barGroup as traitBarGroup;
    setBarWidth as traitSetBarWidth;
  }

  protected $tx;
  protected $ty;
  protected $bar_class = 'Goat1000\\SVGGraph\\Bar3D';
  protected $bar_drawer;

  public function __construct($w, $h, array $settings, array $fixed_settings = [])
  {
    $fs = ['label_centre' => !isset($settings['datetime_keys'])];
    $fs = array_merge($fs, $fixed_settings);
    parent::__construct($w, $h, $settings, $fs);

    $this->bar_drawer = new $this->bar_class($this);
  }

  /**
   * Returns the SVG code for a 3D bar
   */
  protected function bar3D($item, &$bar, $top, $index, $dataset = null,
    $start = null, $axis = null)
  {
    $pos = $this->barX($item, $index, $tmp_bar, $axis, $dataset);
    if($pos === null || $pos > $this->height - $this->pad_bottom)
      return '';

    return $this->bar_drawer->draw($bar['x'], $bar['y'],
      $bar['width'], $bar['height'], true, $top,
      $this->getColour($item, $index, $dataset, false, false));
  }

  /**
   * Set the bar width and space, create the top
   */
  protected function setBarWidth($width, $space)
  {
    $this->traitSetBarWidth($width, $space);
    $this->bar_drawer->setDepth($width);
    list($this->tx, $this->ty) = $this->project(0, 0, $space);
  }

  /**
   * Add the translation to the bar group
   */
  protected function barGroup()
  {
    $group = $this->traitBarGroup();
    if($this->tx || $this->ty) {
      $xform = new Transform;
      $xform->translate($this->tx, $this->ty);
      $group['transform'] = $xform;
    }
    return $group;
  }

  /**
   * Returns the SVG code for a bar
   */
  protected function drawBar(DataItem $item, $index, $start = 0, $axis = null,
    $dataset = 0, $options = [])
  {
    return $this->drawBar3D($item, $index, $start, $axis, $dataset, $options);
  }
}
