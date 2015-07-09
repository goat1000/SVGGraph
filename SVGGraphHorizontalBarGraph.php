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

require_once 'SVGGraphGridGraph.php';

class HorizontalBarGraph extends GridGraph {

  protected $flip_axes = true;
  protected $label_centre = true;
  protected $legend_reverse = true;
  protected $bar_styles = array();

  public function __construct($w, $h, $settings = NULL)
  {
    // backwards compatibility
    if(isset($settings['show_bar_labels']))
      $settings['show_data_labels'] = $settings['show_bar_labels'];

    parent::__construct($w, $h, $settings);
  }

  protected function Draw()
  {
    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);
    $bar_height = $this->BarHeight();
    $bspace = max(0, ($this->y_axes[$this->main_y_axis]->Unit() - $bar_height) / 2);
    $this->ColourSetup($this->values->ItemsCount());

    $bnum = 0;
    $series = '';
    foreach($this->values[0] as $item) {
      $bar = array('height' => $bar_height);
      $bar_pos = $this->GridPosition($item->key, $bnum);
      if($this->legend_show_empty || $item->value != 0) {
        $bar_style = array('fill' => $this->GetColour($item, $bnum));
        $this->SetStroke($bar_style, $item);
      } else {
        $bar_style = NULL;
      }

      if(!is_null($item->value) && !is_null($bar_pos)) {
        $bar['y'] = $bar_pos - $bspace - $bar_height;
        $this->Bar($item->value, $bar);

        if($bar['width'] > 0) {
          $show_label = $this->AddDataLabel(0, $bnum, $bar, $item,
            $bar['x'], $bar['y'], $bar['width'], $bar['height']);
          if($this->show_tooltips)
            $this->SetTooltip($bar, $item, 0, $item->key, $item->value,
              !$this->compat_events && $show_label);
          if($this->semantic_classes)
            $bar['class'] = "series0";
          $rect = $this->Element('rect', $bar, $bar_style);
          $series .= $this->GetLink($item, $item->key, $rect);
        }
      }
      $this->bar_styles[] = $bar_style;
      ++$bnum;
    }

    if($this->semantic_classes)
      $series = $this->Element('g', array('class' => 'series'), NULL, $series);
    $body .= $series . $this->Guidelines(SVGG_GUIDELINE_ABOVE) . $this->Axes();
    return $body;
  }

  /**
   * Returns the height of a bar rectangle
   */
  protected function BarHeight()
  {
    if(is_numeric($this->bar_width) && $this->bar_width >= 1)
      return $this->bar_width;
    $unit_h = $this->y_axes[$this->main_y_axis]->Unit();
    return $this->bar_space >= $unit_h ? '1' : $unit_h - $this->bar_space;
  }

  /**
   * Fills in the x-position and width of a bar
   * @param number $value bar value
   * @param array  &$bar  bar element array [out]
   * @param number $start bar start value
   * @return number unclamped bar position
   */
  protected function Bar($value, &$bar, $start = null)
  {
    if($start)
      $value += $start;

    $startpos = is_null($start) ? $this->OriginX() : $this->GridX($start);
    if(is_null($startpos))
      $startpos = $this->OriginX();
    $pos = $this->GridX($value);
    if(is_null($pos)) {
      $bar['width'] = 0;
    } else {
      $l1 = $this->ClampHorizontal($startpos);
      $l2 = $this->ClampHorizontal($pos);
      $bar['x'] = min($l1, $l2);
      $bar['width'] = abs($l1-$l2);
    }
    return $pos;
  }

  /**
   * Override to check minimum space requirement
   */
  protected function AddDataLabel($dataset, $index, &$element, &$item,
    $x, $y, $w, $h, $content = NULL, $duplicate = TRUE)
  {
    if($w < $this->ArrayOption($this->data_label_min_space, $dataset))
      return false;
    return parent::AddDataLabel($dataset, $index, $element, $item, $x, $y,
      $w, $h, $content, $duplicate);
  }

  /**
   * Returns the position for a data label
   */
  public function DataLabelPosition($dataset, $index, &$item, $x, $y, $w, $h,
    $label_w, $label_h)
  {
    $pos = parent::DataLabelPosition($dataset, $index, $item, $x, $y, $w, $h,
      $label_w, $label_h);
    $bpos = $this->bar_label_position;
    if(!empty($bpos))
      $pos = $bpos;

    if($label_w > $w && Graph::IsPositionInside($pos))
      $pos = str_replace(array('left','centre','right'), 'outside right inside', $pos);

    // flip sides for negative values
    if($item->value < 0) {
      if(strpos($pos, 'right') !== FALSE)
        $pos = str_replace('right', 'left', $pos);
      elseif(strpos($pos, 'left') !== FALSE)
        $pos = str_replace('left', 'right', $pos);
    }
    return $pos;
  }

  /**
   * Returns the style options for bar labels
   */
  public function DataLabelStyle($dataset, $index, &$item)
  {
    $style = parent::DataLabelStyle($dataset, $index, $item);

    // bar label settings can override global settings
    $opts = array(
      'font' => 'bar_label_font',
      'font_size' => 'bar_label_font_size',
      'font_weight' => 'bar_label_font_weight',
      'font_adjust' => 'bar_label_font_adjust',
      'colour' => 'bar_label_colour',
      'altcolour' => 'bar_label_colour_above',
      'space' => 'bar_label_space',
    );
    foreach($opts as $key => $opt)
      if(isset($this->settings[$opt]))
        $style[$key] = $this->settings[$opt];
    return $style;
  }

  /**
   * Return box for legend
   */
  protected function DrawLegendEntry($set, $x, $y, $w, $h)
  {
    if(!isset($this->bar_styles[$set]))
      return '';

    $bar = array('x' => $x, 'y' => $y, 'width' => $w, 'height' => $h);
    return $this->Element('rect', $bar, $this->bar_styles[$set]);
  }

}

