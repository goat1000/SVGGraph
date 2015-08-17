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
require_once 'SVGGraphHorizontalBarGraph.php';
require_once 'SVGGraphData.php';

class HorizontalStackedBarGraph extends HorizontalBarGraph {

  protected $legend_reverse = false;
  protected $single_axis = true;

  // used to determine where the total label should go
  protected $last_position_pos = array();
  protected $last_position_neg = array();

  protected function Draw()
  {
    if($this->log_axis_y)
      throw new Exception('log_axis_y not supported by HorizontalStackedBarGraph');

    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);

    $bar_height = $this->BarHeight();
    $bspace = max(0, ($this->y_axes[$this->main_y_axis]->Unit() - $bar_height) / 2);
    $bar_style = array();
    $bar = array('height' => $bar_height);

    $bnum = 0;
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
            $this->Bar($item->value, $bar, $item->value >= 0 ? $xpos : $xneg);
            if($item->value < 0)
              $xneg += $item->value;
            else
              $xpos += $item->value;

            if($bar['width'] > 0) {
              ++$bars_shown[$j];

              $show_label = $this->AddDataLabel($j, $bnum, $bar, $item,
                $bar['x'], $bar['y'], $bar['width'], $bar['height']);
              if($this->show_tooltips)
                $this->SetTooltip($bar, $item, 0, $item->key, $item->value,
                  !$this->compat_events && $show_label);
              if($this->semantic_classes)
                $bar['class'] = "series{$j}";
              $rect = $this->Element('rect', $bar, $bar_style);
              $bars .= $this->GetLink($item, $k, $rect);
              unset($bar['id']); // clear ID for next generated value
            }
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
                $xneg);
            else
              $bar_total = $xneg;
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
    if(!is_numeric($dataset)) {
      if($dataset === 'totalpos') {
        if(isset($this->last_position_pos[$index])) {
          list($lpos, $l_w) = $this->last_position_pos[$index];
          list($hpos, $vpos) = Graph::TranslatePosition($lpos);
          if($hpos == 'or')
            return "middle outside right {$l_w} 0";
        }
        return 'outside right';
      }
      if($dataset === 'totalneg') {
        if(isset($this->last_position_neg[$index])) {
          list($lpos, $l_w) = $this->last_position_neg[$index];
          list($hpos, $vpos) = Graph::TranslatePosition($lpos);
          if($hpos == 'ol')
            return "middle outside left -{$l_w} 0";
        }
        return 'outside left';
      }
    }
    if($dataset === 'totalpos')
      return 'outside right';
    if($dataset === 'totalneg')
      return 'outside left';

    $pos = parent::DataLabelPosition($dataset, $index, $item, $x, $y, $w, $h,
      $label_w, $label_h);
    if($label_w > $w && Graph::IsPositionInside($pos))
      $pos = str_replace(array('outside left','outside right'), 'centre', $pos);

    if($item->value > 0)
      $this->last_position_pos[$index] = array($pos, $label_w);
    else
      $this->last_position_neg[$index] = array($pos, $label_w);
    return $pos;
  }

  /**
   * Returns the style options for labels
   */
  public function DataLabelStyle($dataset, $index, &$item)
  {
    $style = parent::DataLabelStyle($dataset, $index, $item);

    if($dataset === 'totalpos' || $dataset === 'totalneg') {
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
        'stroke' => 'bar_total_stroke',
        'stroke_width' => 'bar_total_stroke_width',
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

