<?php
/**
 * Copyright (C) 2015-2022 Graham Breach
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

class Histogram extends BarGraph {

  private $scaling = 1;
  private $increment = 1;

  public function __construct($w, $h, array $settings, array $fixed_settings = [])
  {
    $fs = [
      'repeated_keys' => 'accept',
      'label_centre' => false,

      // disable datetime conversion
      'datetime_keys' => false,
    ];
    $fs = array_merge($fs, $fixed_settings);
    parent::__construct($w, $h, $settings, $fs);
  }

  /**
   * Process the values
   */
  public function values($values)
  {
    if(!empty($values)) {
      parent::values($values);
      if($this->values->error)
        return;
      $values = [];

      // find min, max, strip out nulls
      $min = $max = null;
      $dataset = $this->getOption(['dataset', 0], 0);
      foreach($this->values[$dataset] as $item) {
        if($item->value !== null) {
          if(!is_numeric($item->value)) {
            $this->values->error = 'Non-numeric value';
            return;
          }

          if($min === null || $item->value < $min)
            $min = $item->value;
          if($max === null || $item->value > $max)
            $max = $item->value;
          $values[] = $item->value;
        }
      }

      // calculate or clean up increment
      $inc = $this->getOption('increment');
      if($inc <= 0) {
        $diff = $max - $min;
        if($diff <= 0) {
          $inc = 1;
        } else {
          $scale = floor(log10($diff));
          $inc = pow(10, $scale);
          $d1 = $diff / $inc;
          if(($inc != 1 || !is_integer($diff)) && $d1 < 4) {
            if($d1 < 3)
              $inc *= 0.2;
            else
              $inc *= 0.5;
          }
        }
      }

      // need to scale if $inc not an integer
      $s = 1;
      while($inc < 1 || ($inc != floor($inc))) {
        $s *= 10;
        $inc *= 10;
      }
      if($s > 1) {
        $this->scaling = $s;
        $max *= $s;
        $min *= $s;
        $diff = $max - $min;
      }
      $this->increment = $inc;

      // prefill the map with nulls
      $map = [];
      $start = $this->interval($min);
      $end = $this->interval($max, true) + $this->increment / 2;

      Number::setup($this->getOption('precision'), $this->getOption('decimal'),
        $this->getOption('thousands'));
      for($i = $start; $i < $end; $i += $this->increment) {
        $key = (int)$i;
        $map[$key] = 0;
      }

      foreach($values as $val) {
        $val *= $this->scaling;
        $k = (int)$this->interval($val);
        if(!array_key_exists($k, $map))
          $map[$k] = 1;
        else
          $map[$k]++;
      }

      if($this->getOption('percentage')) {
        $total = count($values);
        $pmap = [];
        foreach($map as $k => $v)
          $pmap[$k] = 100 * $v / $total;
        $values = $pmap;
      } else {
        $values = $map;
      }

      // turn into structured data
      $new_values = [];
      foreach($values as $k => $v)
        $new_values[] = [$k, $v];
      $values = $new_values;

      // make structure use the same dataset number
      $structure = [ 'key' => 0, 'value' => 1 ];
      if($dataset !== 0) {
        $structure['value'] = array_fill(0, $dataset, 2);
        $structure['value'][$dataset] = 1;
      }

      $this->setOption('structure', $structure);
      $this->setOption('structured_data', true);

      // set up options to make bar graph class draw the histogram properly
      $this->setOption('minimum_units_y', 1);
      $this->setOption('subdivision_h', $this->increment); // no subdiv below bar size
      $this->setOption('grid_division_h', $this->increment); //max($increment, $this->grid_division_h));

      $amh = $this->getOption('axis_min_h');
      if(empty($amh))
        $this->setOption('axis_min_h', $start);
      if($this->scaling !== 1) {
        $this->setOption('axis_text_callback_x', function($v) {
          $s = $this->scaling;
          $p = log10($this->scaling) + 1;
          $n = new Number($v / $s);
          return $n->format(null, $p);
        });
      }
    }
    parent::values($values);
  }

  /**
   * Returns the start (or next) interval for a value
   */
  public function interval($value, $next = false)
  {
    $n = floor($value / $this->increment);
    if($next)
      ++$n;
    return $n * $this->increment;
  }

  /**
   * Sets up the colour class with corrected number of colours
   */
  protected function colourSetup($count, $datasets = null, $reverse = false)
  {
    // $count is off by 1 because the divisions are numbered
    return parent::colourSetup($count - 1, $datasets, $reverse);
  }

  /**
   * Override because of the shifted numbering
   */
  protected function gridPosition($item, $ikey)
  {
    $position = null;
    $zero = -0.01; // catch values close to 0
    $axis = $this->x_axes[$this->main_x_axis];
    $offset = $axis->position($item->key);
    $g_limit = $this->g_width - ($axis->unit() / 2);
    if($offset >= $zero && floor($offset) <= $g_limit)
      $position = $this->pad_left + $offset;

    return $position;
  }

  /**
   * Returns the width of a bar
   */
  protected function barWidth()
  {
    $bar_width = $this->getOption('bar_width');
    if(is_numeric($bar_width) && $bar_width >= 1)
      return $bar_width;
    $unit_w = $this->increment *
      $this->x_axes[$this->main_x_axis]->unit();
    return max(1, $unit_w - $this->getOption('bar_space'));
  }

  /**
   * Returns the space before a bar
   */
  protected function barSpace($bar_width)
  {
    $uwidth = $this->increment *
      $this->x_axes[$this->main_x_axis]->unit();
    return max(0, ($uwidth - $bar_width) / 2);
  }

  /**
   * Override to prevent drawing an entry past the last bar
   */
  protected function setLegendEntry($dataset, $index, $item, $style_info)
  {
    // the last entry is a blank to wangle the numbering
    if($item->key >= $this->getMaxKey())
      return;
    parent::setLegendEntry($dataset, $index, $item, $style_info);
  }

  /**
   * Override to pass in the modified Average class to use
   */
  protected function calcAverages($cls = 'Goat1000\SVGGraph\HistogramAverage')
  {
    return parent::calcAverages('Goat1000\SVGGraph\HistogramAverage');
  }

}

