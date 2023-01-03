<?php
/**
 * Copyright (C) 2022 Graham Breach
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

trait HorizontalBarGraphTrait {

  use BarGraphTrait;

  public function __construct($w, $h, array $settings, array $fixed_settings = [])
  {
    // backwards compatibility
    if(isset($settings['show_bar_labels']) && !isset($settings['show_data_labels']))
      $settings['show_data_labels'] = $settings['show_bar_labels'];

    $fs = ['label_centre' => !isset($settings['datetime_keys'])];
    $fs = array_merge($fs, $fixed_settings);
    parent::__construct($w, $h, $settings, $fs);
  }

  /**
   * Returns the height of a bar rectangle
   */
  protected function barWidth()
  {
    if(is_numeric($this->getOption('bar_width')) && $this->getOption('bar_width') >= 1)
      return $this->getOption('bar_width');
    $unit_h = $this->y_axes[$this->main_y_axis]->unit();
    $bh = $unit_h - $this->getOption('bar_space');
    return max(1, $bh, $this->getOption('bar_width_min'));
  }

  /**
   * Fills in the x-position and width of a bar
   * @param number $value bar value
   * @param array  &$bar  bar element array [out]
   * @param number $start bar start value
   * @return number unclamped bar position
   */
  protected function barY($value, &$bar, $start = null)
  {
    if($start)
      $value += $start;

    $startpos = $start === null ? $this->originX() : $this->gridX($start);
    if($startpos === null)
      $startpos = $this->originX();
    $pos = $this->gridX($value);
    if($pos === null) {
      $bar['width'] = 0;
    } else {
      $l1 = $this->clampHorizontal($startpos);
      $l2 = $this->clampHorizontal($pos);
      $bar['x'] = min($l1, $l2);
      $bar['width'] = abs($l1-$l2);
    }
    return $pos;
  }

  /**
   * Fills in the y and height of bar
   */
  protected function barX($item, $index, &$bar, $axis, $dataset)
  {
    $bar_y = $this->gridPosition($item, $index);
    if($bar_y === null)
      return null;

    $axis = $this->y_axes[$this->main_y_axis];
    if($axis->reversed())
      $bar['y'] = $bar_y - $this->calculated_bar_space - $this->calculated_bar_width;
    else
      $bar['y'] = $bar_y + $this->calculated_bar_space;
    $bar['height'] = $this->calculated_bar_width;
    return $bar_y;
  }

  /**
   * Returns the space before a bar
   */
  protected function barSpace($bar_width)
  {
    return max(0, ($this->y_axes[$this->main_y_axis]->unit() - $bar_width) / 2);
  }

  /**
   * Override to check minimum space requirement
   */
  protected function addDataLabel($dataset, $index, &$element, &$item,
    $x, $y, $w, $h, $content = null, $duplicate = true)
  {
    if($w < $this->getOption(['data_label_min_space', $dataset]))
      return false;
    return parent::addDataLabel($dataset, $index, $element, $item, $x, $y,
      $w, $h, $content, $duplicate);
  }

  /**
   * Returns the position for a data label
   */
  public function dataLabelPosition($dataset, $index, &$item, $x, $y, $w, $h,
    $label_w, $label_h)
  {
    list($pos, $target) = parent::dataLabelPosition($dataset, $index, $item,
      $x, $y, $w, $h, $label_w, $label_h);
    $bpos = $this->getOption('bar_label_position');
    if(!empty($bpos))
      $pos = $bpos;

    if($label_w > $w && Graph::isPositionInside($pos))
      $pos = str_replace(['left','centre','right'], 'outside right inside', $pos);

    // flip sides for negative values
    if($item !== null && $item->value < 0) {
      if(strpos($pos, 'right') !== false)
        $pos = str_replace('right', 'left', $pos);
      elseif(strpos($pos, 'left') !== false)
        $pos = str_replace('left', 'right', $pos);
    }
    return [$pos, $target];
  }

  /**
   * Returns the ordering for legend entries
   */
  public function getLegendOrder()
  {
    return 'reverse';
  }
}

