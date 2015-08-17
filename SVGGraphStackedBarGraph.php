<?php
/**
 * Copyright (C) 2011-2015 Graham Breach
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
require_once 'SVGGraphBarGraph.php';

class StackedBarGraph extends BarGraph {

  protected $legend_reverse = true;
  protected $single_axis = true;

  // used to determine where the total label should go
  protected $last_position_pos = array();
  protected $last_position_neg = array();

  protected function Draw()
  {
    if($this->log_axis_y)
      throw new Exception('log_axis_y not supported by StackedBarGraph');

    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);
    $bar_style = array();
    $bar_width = $this->BarWidth();
    $bspace = max(0, ($this->x_axes[$this->main_x_axis]->Unit() - $bar_width) / 2);
    $bar = array('width' => $bar_width);

    $bnum = 0;
    $chunk_count = count($this->multi_graph);
    $bars_shown = array_fill(0, $chunk_count, 0);
    $this->ColourSetup($this->multi_graph->ItemsCount(-1), $chunk_count);

    $bars = '';
    foreach($this->multi_graph as $itemlist) {
      $k = $itemlist[0]->key;
      $bar_pos = $this->GridPosition($k, $bnum);

      if(!is_null($bar_pos)) {
        $bar['x'] = $bspace + $bar_pos;
        $ypos = $yneg = 0;

        // find greatest -/+ bar
        $max_neg_bar = $max_pos_bar = -1;
        for($j = 0; $j < $chunk_count; ++$j) {
          if($itemlist[$j]->value > 0)
            $max_pos_bar = $j;
          else
            $max_neg_bar = $j;
        }
        for($j = 0; $j < $chunk_count; ++$j) {
          $item = $itemlist[$j];
          $this->SetStroke($bar_style, $item, $j);
          $bar_style['fill'] = $this->GetColour($item, $bnum, $j);

          if(!is_null($item->value)) {
            $this->Bar($item->value, $bar, $item->value >= 0 ? $ypos : $yneg);
            if($item->value < 0)
              $yneg += $item->value;
            else
              $ypos += $item->value;

            if($bar['height'] > 0) {
              ++$bars_shown[$j];

              $show_label = $this->AddDataLabel($j, $bnum, $bar, $item, $bar['x'],
                $bar['y'], $bar['width'], $bar['height']);
              if($this->show_tooltips)
                $this->SetTooltip($bar, $item, $j, $item->key, $item->value,
                  !$this->compat_events && $show_label);
              if($this->semantic_classes)
                $bar['class'] = "series{$j}";
              $rect = $this->Element('rect', $bar, $bar_style);
              $bars .= $this->GetLink($item, $k, $rect);
              unset($bar['id']); // clear for next value
            }
          }
          $this->bar_styles[$j] = $bar_style;
        }
        if($this->show_bar_totals) {
          if($ypos) {
            $this->Bar($ypos, $bar);
            if(is_callable($this->bar_total_callback))
              $bar_total = call_user_func($this->bar_total_callback, $item->key,
                $ypos);
            else
              $bar_total = $ypos;
            $this->AddContentLabel('totalpos', $bnum, $bar['x'], $bar['y'],
              $bar['width'], $bar['height'], $bar_total);
          }
          if($yneg) {
            $this->Bar($yneg, $bar);
            if(is_callable($this->bar_total_callback))
              $bar_total = call_user_func($this->bar_total_callback, $item->key,
                $yneg);
            else
              $bar_total = $yneg;
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
   * Overridden to prevent drawing on other bars
   */
  public function DataLabelPosition($dataset, $index, &$item, $x, $y, $w, $h,
    $label_w, $label_h)
  {
    if(!is_numeric($dataset)) {
      // doing this supports stacked grouped bar graph totals too
      list($d) = explode('-', $dataset);
      if($d === 'totalpos') {
        if(isset($this->last_position_pos[$index])) {
          list($lpos, $l_h) = $this->last_position_pos[$index];
          list($hpos, $vpos) = Graph::TranslatePosition($lpos);
          if($vpos == 'ot')
            return "above 0 -{$l_h}";
        }
        return 'above';
      }
      if($d === 'totalneg') {
        if(isset($this->last_position_neg[$index])) {
          list($lpos, $l_h) = $this->last_position_neg[$index];
          list($hpos, $vpos) = Graph::TranslatePosition($lpos);
          if($vpos == 'ob')
            return "below 0 {$l_h}";
        }
        return 'below';
      }
    }
    $pos = parent::DataLabelPosition($dataset, $index, $item, $x, $y, $w, $h,
      $label_w, $label_h);
    if($label_h > $h && Graph::IsPositionInside($pos))
      $pos = str_replace(array('top','bottom','above','below'), 'middle', $pos);

    if($item->value > 0)
      $this->last_position_pos[$index] = array($pos, $label_h);
    else
      $this->last_position_neg[$index] = array($pos, $label_h);
    return $pos;
  }

  /**
   * Returns the style options for bar labels (and totals)
   */
  public function DataLabelStyle($dataset, $index, &$item)
  {
    $style = parent::DataLabelStyle($dataset, $index, $item);

    if(strpos($dataset, 'total') === 0) {

      // total settings can override label settings
      $opts = array(
        'font' => 'bar_total_font',
        'font_size' => 'bar_total_font_size',
        'font_weight' => 'bar_total_font_weight',
        'colour' => 'bar_total_colour',
        'space' => 'bar_total_space',
        'type' => 'bar_total_type',
        'font_adjust' => 'bar_total_font_adjust',
        'back_colour' => 'bar_total_back_colour',
        'angle' => 'bar_total_angle',
        'round' => 'bar_total_round',
        'stroke' => 'bar_total_outline_colour',
        'stroke_width' => 'bar_total_outline_thickness',
        'fill' => 'bar_total_fill',
        'tail_width' => 'bar_total_tail_width',
        'tail_length' => 'bar_total_tail_length',
        'shadow_opacity' => 'bar_total_shadow_opacity',
        'pad_x' => 'bar_total_padding_x',
        'pad_y' => 'bar_total_padding_y',
      );

      // special case
      $opt = 'bar_total_padding';
      if(isset($this->settings[$opt]) && !empty($this->settings[$opt])) {
        $style['pad_x'] = $this->settings[$opt];
        $style['pad_y'] = $this->settings[$opt];
      }
      foreach($opts as $key => $opt) {
        if(isset($this->settings[$opt]) && !empty($this->settings[$opt]))
          $style[$key] = $this->settings[$opt];
      }
    }
    return $style;
  }

  /**
   * Returns the maximum (stacked) value
   */
  protected function GetMaxValue()
  {
    return $this->multi_graph->GetMaxSumValue();
  }

  /**
   * Returns the minimum (stacked) value
   */
  protected function GetMinValue()
  {
    return $this->multi_graph->GetMinSumValue();
  }
}

