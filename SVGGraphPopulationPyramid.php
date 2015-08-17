<?php
/**
 * Copyright (C) 2013-2015 Graham Breach
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

require_once 'SVGGraphMultiGraph.php';
require_once 'SVGGraphHorizontalStackedBarGraph.php';
require_once 'SVGGraphAxisDoubleEnded.php';
require_once 'SVGGraphAxisFixedDoubleEnded.php';
require_once 'SVGGraphData.php';

class PopulationPyramid extends HorizontalStackedBarGraph {

  protected $legend_reverse = false;
  protected $neg_datasets = array();

  protected function Draw()
  {
    if($this->log_axis_y)
      throw new Exception('log_axis_y not supported by PopulationPyramid');

    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);

    $bar_height = $this->BarHeight();
    $bar_style = array();
    $bar = array('height' => $bar_height);

    $bnum = 0;
    $bspace = max(0, ($this->y_axes[$this->main_y_axis]->Unit() - $bar_height) / 2);
    $b_start = $this->height - $this->pad_bottom - ($this->bar_space / 2);
    $chunk_count = count($this->multi_graph);
    $bars_shown = array_fill(0, $chunk_count, 0);
    $this->ColourSetup($this->multi_graph->ItemsCount(-1), $chunk_count);
    $bars = '';

    foreach($this->multi_graph as $itemlist) {
      $k = $itemlist[0]->key;
      $bar_pos = $this->GridPosition($k, $bnum);
      if(!is_null($bar_pos)) {
        $bar['y'] = $bar_pos - $bspace - $bar_height;
        $xpos = $xneg = 0;

        // find greatest -/+ bar
        $max_neg_bar = $max_pos_bar = -1;
        for($j = 0; $j < $chunk_count; ++$j) {
          $item = $itemlist[$j];
          $value = $j % 2 ? $item->value : -$item->value;
          if($value > 0)
            $max_pos_bar = $j;
          else
            $max_neg_bar = $j;
        }
        for($j = 0; $j < $chunk_count; ++$j) {
          $item = $itemlist[$j];
          if($j % 2) {
            $value = $item->value;
          } else {
            $value = -$item->value;
            $this->neg_datasets[] = $j;
          }
          $bar_style['fill'] = $this->GetColour($item, $bnum, $j);
          $this->SetStroke($bar_style, $item, $j);
          $this->Bar($value, $bar, $value >= 0 ? $xpos : $xneg);
          if($value < 0)
            $xneg += $value;
          else
            $xpos += $value;

          if($bar['width'] > 0) {
            ++$bars_shown[$j];

            $show_label = $this->AddDataLabel($j, $bnum, $bar, $item,
              $bar['x'], $bar['y'], $bar['width'], $bar['height']);
            if($this->show_tooltips)
              $this->SetTooltip($bar, $item, $j, $item->key, $item->value,
                !$this->compat_events && $show_label);
            if($this->semantic_classes)
              $bar['class'] = "series{$j}";
            $rect = $this->Element('rect', $bar, $bar_style);
            $bars .= $this->GetLink($item, $k, $rect);
            unset($bar['id']);
          }
          $this->bar_styles[$j] = $bar_style;
        }
        if($this->show_bar_totals) {
          if($xpos) {
            $this->Bar($xpos, $bar);
            if(is_callable($this->bar_total_callback))
              $bar_total = call_user_func($this->bar_total_callback, $item->key,
                $xpos);
            else
              $bar_total = $xpos;
            $this->AddContentLabel('totalpos', $bnum, $bar['x'], $bar['y'],
              $bar['width'], $bar['height'], $bar_total);
          }
          if($xneg) {
            $this->Bar($xneg, $bar);
            if(is_callable($this->bar_total_callback))
              $bar_total = call_user_func($this->bar_total_callback, $item->key,
                -$xneg);
            else
              $bar_total = -$xneg;
            $this->AddContentLabel('totalneg', $bnum, $bar['x'], $bar['y'],
              $bar['width'], $bar['height'], $bar_total);
          }
        }
      }
      ++$bnum;
    }
    if(!$this->legend_show_empty) {
      foreach($bars_shown as $j => $bar) {
        if(!$bar)
          $this->bar_styles[$j] = NULL;
      }
    }

    if($this->semantic_classes)
      $bars = $this->Element('g', array('class' => 'series'), NULL, $bars);
    $body .= $bars;
    $body .= $this->Guidelines(SVGG_GUIDELINE_ABOVE) . $this->Axes();
    return $body;
  }

  /**
   * Overridden to prevent drawing on other bars
   */
  public function DataLabelPosition($dataset, $index, &$item, $x, $y, $w, $h,
    $label_w, $label_h)
  {
    if(in_array($dataset, $this->neg_datasets, true)) {
      // pass in an item with negative value for positions on left
      $ineg = $item;
      $ineg->value = -$item->value;
      $pos = parent::DataLabelPosition($dataset, $index, $ineg, $x, $y, $w, $h,
        $label_w, $label_h);
    } else {
      $pos = parent::DataLabelPosition($dataset, $index, $item, $x, $y, $w, $h,
        $label_w, $label_h);
    }

    return $pos;
  }

  /**
   * construct multigraph
   */
  public function Values($values)
  {
    parent::Values($values);
    if(!$this->values->error)
      $this->multi_graph = new MultiGraph($this->values, $this->force_assoc,
        $this->require_integer_keys);
  }

  /**
   * Returns the maximum (stacked) value
   */
  protected function GetMaxValue()
  {
    $sums = array(array(), array());
    $sets = count($this->values);
    if($sets < 2)
      return $this->multi_graph->GetMaxValue();
    for($i = 0; $i < $sets; ++$i) {
      $dir = $i % 2;
      foreach($this->values[$i] as $item) {
        if(isset($sums[$dir][$item->key]))
          $sums[$dir][$item->key] += $item->value;
        else
          $sums[$dir][$item->key] = $item->value;
      }
    }
    if(!count($sums[0]))
      return NULL;
    return max(max($sums[0]), max($sums[1]));
  }

  /**
   * Returns the minimum (stacked) value
   */
  protected function GetMinValue()
  {
    $sums = array(array(), array());
    $sets = count($this->values);
    if($sets < 2)
      return $this->multi_graph->GetMinValue();
    for($i = 0; $i < $sets; ++$i) {
      $dir = $i % 2;
      foreach($this->values[$i] as $item) {
        if(isset($sums[$dir][$item->key]))
          $sums[$dir][$item->key] += $item->value;
        else
          $sums[$dir][$item->key] = $item->value;
      }
    }
    if(!count($sums[0]))
      return NULL;
    return min(min($sums[0]), min($sums[1]));
  }

  /**
   * Returns the X and Y axis class instances as a list
   */
  protected function GetAxes($ends, &$x_len, &$y_len)
  {
    // always assoc, no units
    $this->units_x = $this->units_before_x = null;

    // if fixed grid spacing is specified, make the min spacing 1 pixel
    if(is_numeric($this->grid_division_v))
      $this->minimum_grid_spacing_v = 1;
    if(is_numeric($this->grid_division_h))
      $this->minimum_grid_spacing_h = 1;

    $max_h = $ends['v_max'][0];
    $min_h = $ends['v_min'][0];
    $max_v = $ends['k_max'][0];
    $min_v = $ends['k_min'][0];
    $x_min_unit = $this->ArrayOption($this->minimum_units_y, 0);
    $x_fit = false;
    $y_min_unit = 1;
    $y_fit = true;
    $x_units_after = (string)$this->ArrayOption($this->units_y, 0);
    $y_units_after = (string)$this->ArrayOption($this->units_x, 0);
    $x_units_before = (string)$this->ArrayOption($this->units_before_y, 0);
    $y_units_before = (string)$this->ArrayOption($this->units_before_x, 0);
    $x_decimal_digits = $this->GetFirst(
      $this->ArrayOption($this->decimal_digits_y, 0),
      $this->decimal_digits);
    $y_decimal_digits = $this->GetFirst(
      $this->ArrayOption($this->decimal_digits_x, 0),
      $this->decimal_digits);
    $x_text_callback = $this->GetFirst(
      $this->ArrayOption($this->axis_text_callback_x, 0),
      $this->axis_text_callback);
    $y_text_callback = $this->GetFirst(
      $this->ArrayOption($this->axis_text_callback_y, 0),
      $this->axis_text_callback);

    $this->grid_division_h = $this->ArrayOption($this->grid_division_h, 0);
    $this->grid_division_v = $this->ArrayOption($this->grid_division_v, 0);

    // sanitise grid divisions
    if(is_numeric($this->grid_division_v) && $this->grid_division_v <= 0)
      $this->grid_division_v = null;
    if(is_numeric($this->grid_division_h) && $this->grid_division_h <= 0)
      $this->grid_division_h = null;

    if(!is_numeric($max_h) || !is_numeric($min_h) ||
      !is_numeric($max_v) || !is_numeric($min_v))
      throw new Exception('Non-numeric min/max');

    if(!is_numeric($this->grid_division_h))
      $x_axis = new AxisDoubleEnded($x_len, $max_h, $min_h, $x_min_unit, $x_fit,
        $x_units_before, $x_units_after, $x_decimal_digits, $x_text_callback);
    else
      $x_axis = new AxisFixedDoubleEnded($x_len, $max_h, $min_h, 
        $this->grid_division_h, $x_units_before, $x_units_after,
        $x_decimal_digits, $x_text_callback);

    if(!is_numeric($this->grid_division_v))
      $y_axis = new Axis($y_len, $max_v, $min_v, $y_min_unit, $y_fit,
        $y_units_before, $y_units_after, $y_decimal_digits, $y_text_callback);
    else
      $y_axis = new AxisFixed($y_len, $max_v, $min_v, $this->grid_division_v,
        $y_units_before, $y_units_after, $y_decimal_digits, $y_text_callback);

    $y_axis->Reverse(); // because axis starts at bottom
    return array(array($x_axis), array($y_axis));
  }
}

