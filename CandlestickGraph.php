<?php
/**
 * Copyright (C) 2021-2022 Graham Breach
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

class CandlestickGraph extends BarGraph {

  private $min_value = null;
  private $max_value = null;

  public function __construct($w, $h, array $settings, array $fixed_settings = [])
  {
    $fs = [
      'label_centre' => !isset($settings['datetime_keys']),
      'require_structured' => ['open', 'high', 'low'],
    ];
    $fs = array_merge($fs, $fixed_settings);
    parent::__construct($w, $h, $settings, $fs);
  }

  /**
   * Check that all the values are in the right order
   */
  protected function checkValues()
  {
    parent::checkValues();

    $fields = ['low', 'high', 'open'];
    foreach($this->values[0] as $item) {
      $val = $item->value;
      if($val === null)
        continue;

      foreach($fields as $f) {
        if(!is_numeric($item->$f))
          throw new \Exception("Data error: field '$f' is not numeric (key:'{$item->key}', value:'{$item->$f}')");
      }

      $wb = $item->low;
      $wt = $item->high;
      $o = $item->open;
      $b = min($val, $o);
      $t = max($val, $o);
      if($wb > $b || $wt < $t) {

        $wb = new Number($wb);
        $b = new Number($b);
        $wt = new Number($wt);
        $t = new Number($t);
        throw new \Exception('Data problem: ' . $wb . '--[' . $b . ' ' . $t . ']--' . $wt);
      }
    }
  }

  /**
   * Returns the maximum bar end
   */
  public function getMaxValue()
  {
    if($this->max_value !== null)
      return $this->max_value;
    $max = null;
    $dataset = $this->getOption(['dataset', 0], 0);
    foreach($this->values[$dataset] as $item) {
      if($item->value === null)
        continue;
      if($max === null || $item->high > $max)
        $max = $item->high;
    }
    return ($this->max_value = $max);
  }

  /**
   * Returns the minimum bar end
   */
  public function getMinValue()
  {
    if($this->min_value !== null)
      return $this->min_value;
    $min = null;
    $dataset = $this->getOption(['dataset', 0], 0);
    foreach($this->values[$dataset] as $item) {
      if($item->value === null)
        continue;
      if($min === null || $item->low < $min)
        $min = $item->low;
    }
    return ($this->min_value = $min);
  }

  /**
   * Sets up the colours used for the graph
   */
  protected function setup()
  {
    $dataset = $this->getOption(['dataset', 0], 0);

    // use two datasets for colours
    $this->colourSetup($this->values->itemsCount($dataset), 2);
  }

  /**
   * Returns an array with x, y, width and height set
   */
  protected function barDimensions($item, $index, $start, $axis, $dataset)
  {
    $bar = [];
    $bar_x = $this->barX($item, $index, $bar, $axis, $dataset);
    if($bar_x === null)
      return [];

    $start = $item->value;
    $value = $item->open - $start;
    $y_pos = $this->barY($value, $bar, $start, $axis);
    if($y_pos === null)
      return [];
    return $bar;
  }

  /**
   * Returns the SVG code for a bar
   */
  protected function drawBar(DataItem $item, $index, $start = 0, $axis = null,
    $dataset = 0, $options = [])
  {
    if($item->value === null)
      return '';

    // negative bars are a different colour
    $negative = $item->value < $item->open;
    if($negative)
      ++$dataset;

    $bar = $this->barDimensions($item, $index, $start, $axis, $dataset);
    if(empty($bar))
      return '';

    if($bar['height'] < 1)
      $bar['height'] = 1;
    $this->setStroke($bar, $item, $index, $dataset);
    $bar['fill'] = $this->getColour($item, $index, $dataset);

    if($this->getOption('semantic_classes'))
      $bar['class'] = 'series' . $dataset;

    $label_shown = $this->addDataLabel($dataset, $index, $bar, $item,
      $bar['x'], $bar['y'], $bar['width'], $bar['height']);

    if($this->getOption('show_tooltips'))
      $this->setTooltip($bar, $item, $dataset, $item->key, $item->value,
        $label_shown);
    if($this->getOption('show_context_menu'))
      $this->setContextMenu($bar, $dataset, $item, $label_shown);

    $round = max($this->getItemOption('bar_round', $dataset, $item), 0);
    if($round > 0) {
      // don't allow the round corner to be more than 1/2 bar width or height
      $bar['rx'] = $bar['ry'] = min($round, $bar['width'] / 2, $bar['height'] / 2);
    }

    // wick lines
    $lx = $bar['x'] + ($bar['width'] / 2);
    $ly1 = $this->gridY($item->high);
    $ly2 = $bar['y'];
    $ly3 = $ly2 + $bar['height'];
    $ly4 = $this->gridY($item->low);

    $wick_width = max(0.25, $this->getOption(['wick_stroke_width', $dataset], 1));
    $wick_dash = $this->getOption(['wick_dash', $dataset]);
    $style = [ 'stroke-width' => $wick_width ];
    if(isset($bar['stroke']))
      $style['stroke'] = $bar['stroke'];
    if(!empty($wick_dash))
      $style['stroke-dasharray'] = $wick_dash;

    $l1 = $l2 = '';
    if($ly1 != $ly2)
      $l1 = $this->element('line', array_merge($style,
        ['x1' => $lx, 'x2' => $lx, 'y1' => $ly1, 'y2' => $ly2]));
    if($ly3 != $ly4)
      $l2 = $this->element('line', array_merge($style,
        ['x1' => $lx, 'x2' => $lx, 'y1' => $ly3, 'y2' => $ly4]));

    $bar_part = $this->element('rect', $bar);
    return $this->getLink($item, $item->key, $bar_part . $l1 . $l2);
  }
}

