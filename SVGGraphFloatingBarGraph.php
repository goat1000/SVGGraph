<?php
/**
 * Copyright (C) 2013-2016 Graham Breach
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

require_once 'SVGGraphBarGraph.php';

class FloatingBarGraph extends BarGraph {

  protected $require_structured = array('end');
  private $min_value = null;
  private $max_value = null;

  protected function Draw()
  {
    $body = $this->Grid() . $this->UnderShapes();

    $bar_width = $this->BarWidth();
    $bar = array('width' => $bar_width);

    $bspace = max(0, ($this->x_axes[$this->main_x_axis]->Unit() - $bar_width) / 2);    
    $bnum = 0;
    $this->ColourSetup($this->values->ItemsCount());
    $series = '';

    foreach($this->values[0] as $item) {
      $bar_pos = $this->GridPosition($item, $bnum);

      if(!is_null($item->value) && !is_null($bar_pos)) {
        $bar['x'] = $bspace + $bar_pos;

        $end = $item->Data('end');
        $start = $item->value;
        $value = $end - $start;
        $this->Bar($value, $bar, $start);

        if($bar['height'] > 0) {
          $bar_style = array('fill' => $this->GetColour($item, $bnum));
          $this->SetStroke($bar_style, $item);
          $this->SetLegendEntry(0, $bnum, $item, $bar_style);
          $show_label = $this->AddDataLabel(0, $bnum, $bar, $item,
            $bar['x'], $bar['y'], $bar['width'], $bar['height']);

          if($this->show_tooltips)
            $this->SetTooltip($bar, $item, 0, $item->key, $value,
              !$this->compat_events && $show_label);
          if($this->semantic_classes)
            $bar['class'] = "series0";
          $rect = $this->Element('rect', $bar, $bar_style);
          $series .= $this->GetLink($item, $item->key, $rect);
          unset($bar['id']); // clear for next value
        }
      }
      ++$bnum;
    }

    if($this->semantic_classes)
      $series = $this->Element('g', array('class' => 'series'), NULL, $series);
    $body .= $series;
    $body .= $this->OverShapes();
    $body .= $this->Axes();
    return $body;
  }

  /**
   * Returns the maximum bar end
   */
  protected function GetMaxValue()
  {
    if(!is_null($this->max_value))
      return $this->max_value;
    $max = null;
    foreach($this->values[0] as $item) {
      $s = $item->value;
      $e = $item->Data('end');
      if(is_null($s) || is_null($e))
        continue;
      $m = max($s, $e);
      if(is_null($max) || $m > $max)
        $max = $m;
    }
    return ($this->max_value = $max);
  }

  /**
   * Returns the minimum bar end
   */
  protected function GetMinValue()
  {
    if(!is_null($this->min_value))
      return $this->min_value;
    $min = null;
    foreach($this->values[0] as $item) {
      $s = $item->value;
      $e = $item->Data('end');
      if(is_null($s) || is_null($e))
        continue;
      $m = min($s, $e);
      if(is_null($min) || $m < $min)
        $min = $m;
    }
    return ($this->min_value = $min);
  }

}

