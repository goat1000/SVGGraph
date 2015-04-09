<?php
/**
 * Copyright (C) 2012-2015 Graham Breach
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

require_once 'SVGGraphPointGraph.php';
require_once 'SVGGraphMultiGraph.php';
require_once 'SVGGraphMultiLineGraph.php';

/**
 * StackedLineGraph - multiple joined lines with values added together
 */
class StackedLineGraph extends MultiLineGraph {

  protected $legend_reverse = true;
  protected $single_axis = true;

  protected function Draw()
  {
    if($this->log_axis_y)
      throw new Exception('log_axis_y not supported by StackedLineGraph');

    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);

    $plots = array();
    $chunk_count = count($this->multi_graph);
    $this->ColourSetup($this->multi_graph->ItemsCount(-1), $chunk_count);
    $stack = array();
    for($i = 0; $i < $chunk_count; ++$i) {
      $bnum = 0;
      $cmd = 'M';
      $path = $fillpath = '';
      $attr = array('fill' => 'none');
      $fill = $this->ArrayOption($this->fill_under, $i);
      $dash = $this->ArrayOption($this->line_dash, $i);
      $stroke_width = 
        $this->ArrayOption($this->line_stroke_width, $i);
      if(!empty($dash))
        $attr['stroke-dasharray'] = $dash;
      $attr['stroke-width'] = $stroke_width <= 0 ? 1 : $stroke_width;

      $bottom = array();
      $point_count = 0;
      foreach($this->multi_graph[$i] as $item) {
        $x = $this->GridPosition($item->key, $bnum);
        // key might not be an integer, so convert to string for $stack
        $strkey = "{$item->key}";
        if(!isset($stack[$strkey]))
          $stack[$strkey] = 0;
        if(!is_null($x)) {
          $bottom["$x"] = $stack[$strkey];
          $y = $this->GridY($stack[$strkey] + $item->value);
          $stack[$strkey] += $item->value;

          $path .= "$cmd$x $y ";
          if($fill && empty($fillpath))
            $fillpath = "M$x {$y}L";
          else
            $fillpath .= "$x $y ";

          // no need to repeat same L command
          $cmd = $cmd == 'M' ? 'L' : '';
          if(!is_null($item->value)) {
            $marker_id = $this->MarkerLabel($i, $bnum, $item, $x, $y);
            $extra = empty($marker_id) ? NULL : array('id' => $marker_id);
            $this->AddMarker($x, $y, $item, $extra, $i);
            ++$point_count;
          }
        }
        ++$bnum;
      }

      if($point_count > 0) {
        $attr['d'] = $path;
        $attr['stroke'] = $this->GetColour(null, 0, $i, true);
        if($this->semantic_classes)
          $attr['class'] = "series{$i}";
        $graph_line = $this->Element('path', $attr);
        $fill_style = null;

        if($fill) {
          // complete the fill area with the previous stack total
          $cmd = 'L';
          $opacity = $this->ArrayOption($this->fill_opacity, $i);
          $bpoints = array_reverse($bottom, TRUE);
          foreach($bpoints as $x => $pos) {
            $y = $this->GridY($pos);
            $fillpath .= "$x $y ";
          }
          $fillpath .= 'z';
          $fill_style = array(
            'fill' => $this->GetColour(null, 0, $i),
            'd' => $fillpath,
            'stroke' => $attr['fill'],
          );
          if($opacity < 1)
            $fill_style['opacity'] = $opacity;
          if($this->semantic_classes)
            $fill_style['class'] = "series{$i}";
          $graph_line = $this->Element('path', $fill_style) . $graph_line;
        }

        $plots[] = $graph_line;
        unset($attr['d'], $attr['class'], $fill_style['class']);
        $this->line_styles[] = $attr;
        $this->fill_styles[] = $fill_style;
      }
    }

    $group = array();
    $this->ClipGrid($group);

    $plots = array_reverse($plots);
    $all_plots = '';
    if($this->semantic_classes) {
      foreach($plots as $p)
        $all_plots .= $this->Element('g', array('class' => 'series'), NULL, $p);
    } else {
      $all_plots = implode($plots);
    }
    $body .= $this->Element('g', $group, NULL, $all_plots);
    $body .= $this->Guidelines(SVGG_GUIDELINE_ABOVE);
    $body .= $this->Axes();
    $body .= $this->CrossHairs();
    $body .= $this->DrawMarkers();
    return $body;
  }


  /**
   * Returns the maximum value
   */
  protected function GetMaxValue()
  {
    return $this->multi_graph->GetMaxSumValue();
  }

  /**
   * Returns the minimum value
   */
  protected function GetMinValue()
  {
    return $this->multi_graph->GetMinSumValue();
  }

}

