<?php
/**
 * Copyright (C) 2013-2022 Graham Breach
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

class PopulationPyramid extends HorizontalStackedBarGraph {

  protected $neg_datasets = [];

  protected function draw()
  {
    if($this->getOption('log_axis_y'))
      throw new \Exception('log_axis_y not supported by PopulationPyramid');

    $body = $this->grid() . $this->underShapes();

    $bar_height = $this->barWidth();
    $bar = ['height' => $bar_height];

    $bnum = 0;
    $bspace = max(0, ($this->y_axes[$this->main_y_axis]->unit() - $bar_height) / 2);
    $b_start = $this->height - $this->pad_bottom - ($this->getOption('bar_space') / 2);
    $chunk_count = count($this->multi_graph);
    $bars_shown = array_fill(0, $chunk_count, 0);
    $bars = '';
    $datasets = $this->multi_graph->getEnabledDatasets();

    foreach($this->multi_graph as $itemlist) {
      $item = $itemlist[0];
      $k = $item->key;
      $bar_pos = $this->gridPosition($item, $bnum);
      if($bar_pos !== null) {
        $bar['y'] = $bar_pos - $bspace - $bar_height;
        $xpos = $xneg = 0;

        // find greatest -/+ bar
        $max_neg_bar = $max_pos_bar = -1;
        for($j = 0, $enabled = 0; $j < $chunk_count; ++$j) {
          if(!in_array($j, $datasets))
            continue;

          $item = $itemlist[$j];
          $value = $enabled % 2 ? $item->value : -$item->value;
          if($value > 0)
            $max_pos_bar = $j;
          else
            $max_neg_bar = $j;
          ++$enabled;
        }
        for($j = 0, $enabled = 0; $j < $chunk_count; ++$j) {
          if(!in_array($j, $datasets))
            continue;

          $item = $itemlist[$j];
          if($enabled % 2) {
            $value = $item->value;
          } else {
            $value = -$item->value;
            $this->neg_datasets[] = $j;
          }
          ++$enabled;
          $bar_style = ['fill' => $this->getColour($item, $bnum, $j)];
          $this->setStroke($bar_style, $item, $bnum, $j);

          // store whether the bar can be seen or not
          $this->setBarVisibility($j, $item, false);
          $this->setBarLegendEntry($j, $bnum, $item);

          $this->barY($value, $bar, $value >= 0 ? $xpos : $xneg);
          if($value < 0)
            $xneg += $value;
          else
            $xpos += $value;

          if($bar['width'] > 0) {
            ++$bars_shown[$j];

            $round = max($this->getItemOption('bar_round', $j, $item), 0);
            if($round > 0) {
              $bar['rx'] = $bar['ry'] = min($round, $bar['width'] / 2,
                $bar['height'] / 2);
            }

            $show_label = $this->addDataLabel($j, $bnum, $bar, $item,
              $bar['x'], $bar['y'], $bar['width'], $bar['height']);
            if($this->getOption('show_tooltips'))
              $this->setTooltip($bar, $item, $j, $item->key, $item->value, $show_label);
            if($this->getOption('show_context_menu'))
              $this->setContextMenu($bar, $j, $item, $show_label);
            if($this->getOption('semantic_classes'))
              $bar['class'] = 'series' . $j;
            $rect = $this->element('rect', $bar, $bar_style);
            $bars .= $this->getLink($item, $k, $rect);
            unset($bar['id']);
          }
        }
        if($this->getOption('show_bar_totals')) {
          if($xpos) {
            $this->barY($xpos, $bar);
            if(is_callable($this->getOption('bar_total_callback'))) {
              $bar_total = call_user_func($this->getOption('bar_total_callback'), $item->key,
                $xpos);
            } else {
              $bar_total = new Number($xpos);
              $bar_total = $bar_total->format();
            }
            $this->addContentLabel('totalpos', $bnum, $bar['x'], $bar['y'],
              $bar['width'], $bar['height'], $bar_total);
          }
          if($xneg) {
            $this->barY($xneg, $bar);
            if(is_callable($this->getOption('bar_total_callback'))) {
              $bar_total = call_user_func($this->getOption('bar_total_callback'), $item->key,
                -$xneg);
            } else {
              $bar_total = new Number(-$xneg);
              $bar_total = $bar_total->format();
            }
            $this->addContentLabel('totalneg', $bnum, $bar['x'], $bar['y'],
              $bar['width'], $bar['height'], $bar_total);
          }
        }
      }
      ++$bnum;
    }

    $group = [];
    if($this->getOption('semantic_classes'))
      $group['class'] = 'series';
    $shadow_id = $this->defs->getShadow();
    if($shadow_id !== null)
      $group['filter'] = 'url(#' . $shadow_id . ')';
    if(!empty($group))
      $bars = $this->element('g', $group, null, $bars);
    $body .= $bars;
    $body .= $this->overShapes();
    $body .= $this->axes();
    return $body;
  }

  /**
   * Overridden to prevent drawing on other bars
   */
  public function dataLabelPosition($dataset, $index, &$item, $x, $y, $w, $h,
    $label_w, $label_h)
  {
    if(in_array($dataset, $this->neg_datasets, true)) {
      // pass in an item with negative value for positions on left
      $ineg = $item;
      $ineg->value = -$item->value;
      return parent::dataLabelPosition($dataset, $index, $ineg, $x, $y, $w, $h,
        $label_w, $label_h);
    } else {
      return parent::dataLabelPosition($dataset, $index, $item, $x, $y, $w, $h,
        $label_w, $label_h);
    }
  }

  /**
   * Returns the maximum (stacked) value
   */
  public function getMaxValue()
  {
    $sums = [ [], [] ];
    $datasets = $this->multi_graph->getEnabledDatasets();
    if(count($datasets) < 2)
      return $this->multi_graph->getMaxValue();

    $i = 0;
    foreach($datasets as $d) {
      $dir = $i % 2;
      foreach($this->values[$d] as $item) {
        if($item->value === null)
          continue;
        if(isset($sums[$dir][$item->key]))
          $sums[$dir][$item->key] += $item->value;
        else
          $sums[$dir][$item->key] = $item->value;
      }
      ++$i;
    }
    if(!count($sums[0]))
      return null;
    return max(max($sums[0]), max($sums[1]));
  }

  /**
   * Returns the minimum (stacked) value
   */
  public function getMinValue()
  {
    $sums = [ [], [] ];
    $datasets = $this->multi_graph->getEnabledDatasets();
    if(count($datasets) < 2)
      return $this->multi_graph->getMinValue();

    $i = 0;
    foreach($datasets as $d) {
      $dir = $i % 2;
      foreach($this->values[$d] as $item) {
        if($item->value === null)
          continue;
        if(!is_numeric($item->value))
          throw new \Exception('Non-numeric value');
        if(isset($sums[$dir][$item->key]))
          $sums[$dir][$item->key] += $item->value;
        else
          $sums[$dir][$item->key] = $item->value;
      }
      ++$i;
    }
    if(!count($sums[0]))
      return null;
    return min(min($sums[0]), min($sums[1]));
  }

  /**
   * Returns the X and Y axis class instances as a list
   */
  protected function getAxes($ends, &$x_len, &$y_len)
  {
    // always assoc, no units
    $this->setOption('units_x', null);
    $this->setOption('units_before_x', null);

    // if fixed grid spacing is specified, make the min spacing 1 pixel
    if(is_numeric($this->getOption('grid_division_v')))
      $this->setOption('minimum_grid_spacing_v', 1);
    if(is_numeric($this->getOption('grid_division_h')))
      $this->setOption('minimum_grid_spacing_h', 1);

    $max_h = $ends['v_max'][0];
    $min_h = $ends['v_min'][0];
    $max_v = $ends['k_max'][0];
    $min_v = $ends['k_min'][0];
    $x_min_unit = $this->getOption(['minimum_units_y', 0]);
    $x_fit = false;
    $y_min_unit = 1;
    $y_fit = true;
    $x_units_after = (string)$this->getOption(['units_y', 0]);
    $y_units_after = '';
    $x_units_before = (string)$this->getOption(['units_before_y', 0]);
    $y_units_before = '';
    $x_decimal_digits = $this->getOption(['decimal_digits_y', 0],
      'decimal_digits');
    $y_decimal_digits = $this->getOption(['decimal_digits_x', 0],
      'decimal_digits');
    $x_text_callback = $this->getOption(['axis_text_callback_x', 0],
      'axis_text_callback');
    $y_text_callback = $this->getOption(['axis_text_callback_y', 0],
      'axis_text_callback');

    $grid_division_h = $this->getOption(['grid_division_h', 0]);
    $grid_division_v = $this->getOption(['grid_division_v', 0]);

    // sanitise grid divisions
    if(is_numeric($grid_division_v) && $grid_division_v <= 0)
      $grid_division_v = null;
    if(is_numeric($grid_division_h) && $grid_division_h <= 0)
      $grid_division_h = null;
    $this->setOption('grid_division_v', $grid_division_v);
    $this->setOption('grid_division_h', $grid_division_h);

    if(!is_numeric($max_h) || !is_numeric($min_h) ||
      !is_numeric($max_v) || !is_numeric($min_v))
      throw new \Exception('Non-numeric min/max');

    if($min_h == $max_h) {
      if($x_min_unit > 0) {
        $inc = $x_min_unit;
      } else {
        $fallback = $this->getOption('axis_fallback_max');
        $inc = $fallback > 0 ? $fallback : 1;
      }
      $max_h += $inc;
    }

    if(!is_numeric($grid_division_h)) {
      $x_min_space = $this->getOption(['minimum_grid_spacing_h', 0],
        'minimum_grid_spacing');
      $x_axis = new AxisDoubleEnded($x_len, $max_h, $min_h, $x_min_unit,
        $x_min_space, $x_fit, $x_units_before, $x_units_after,
        $x_decimal_digits, $x_text_callback);
    } else {
      $x_axis = new AxisFixedDoubleEnded($x_len, $max_h, $min_h,
        $grid_division_h, $x_units_before, $x_units_after,
        $x_decimal_digits, $x_text_callback);
    }

    $min_space = $this->getOption(['minimum_grid_spacing_v', 0],
      'minimum_grid_spacing');
    $grid_division = $this->getOption(['grid_division_v', 0]);
    if(is_numeric($grid_division)) {
      if($grid_division <= 0)
        throw new \Exception('Invalid grid division');
      $this->setOption('minimum_grid_spacing_v', 1);
      $min_space = 1;
    }

    $levels = $this->getOption(['axis_levels_h', 0]);
    $ticks = $this->getOption('axis_ticks_x');

    $y_axis_factory = new AxisFactory($this->getOption('datetime_keys'), $this->settings,
      true, true, true);
    $y_axis = $y_axis_factory->get($y_len, $min_v, $max_v, $y_min_unit,
      $min_space, $grid_division, $y_units_before, $y_units_after,
      $y_decimal_digits, $y_text_callback, $this->values, false, 0, $levels,
      $ticks);

    return [ [$x_axis], [$y_axis] ];
  }

  /**
   * Override the function to pass in the class to use
   */
  protected function calcAverages($cls = 'Goat1000\SVGGraph\PopulationPyramidAverage')
  {
    return parent::calcAverages('Goat1000\SVGGraph\PopulationPyramidAverage');
  }

}

