<?php
/**
 * Copyright (C) 2009-2016 Graham Breach
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

/**
 * LineGraph - joined line, with axes and grid
 */
class LineGraph extends PointGraph {

  protected $require_integer_keys = false;
  protected $curr_line_style = NULL;
  protected $curr_fill_style = NULL;

  protected function Draw()
  {
    $body = $this->Grid() . $this->UnderShapes();
    $this->ColourSetup($this->values->ItemsCount());

    $bnum = 0;
    $cmd = 'M';
    $y_axis_pos = $this->height - $this->pad_bottom - 
      $this->y_axes[$this->main_y_axis]->Zero();
    $y_bottom = min($y_axis_pos, $this->height - $this->pad_bottom);

    $points = array();
    foreach($this->values[0] as $item) {
      $x = $this->GridPosition($item, $bnum);
      if(!is_null($item->value) && !is_null($x)) {
        $y = $this->GridY($item->value);
        $points[] = array($x, $y, $item, 0, $bnum);
      }
      ++$bnum;
    }

    $graph_line = $this->DrawLine(0, $points, $y_bottom, true); 

    $group = array();
    $this->ClipGrid($group);
    if($this->semantic_classes)
      $group['class'] = 'series';

    list($best_fit_above, $best_fit_below) = $this->BestFitLines();
    $body .= $best_fit_below;
    $body .= $this->Element('g', $group, NULL, $graph_line);
    $body .= $this->OverShapes();
    $body .= $this->Axes();
    $body .= $this->CrossHairs();
    $body .= $this->DrawMarkers();
    $body .= $best_fit_above;
    return $body;
  }

  /**
   * Line graphs and lines in general require at least two points
   */
  protected function CheckValues()
  {
    parent::CheckValues();

    if($this->values->ItemsCount() <= 1)
      throw new Exception('Not enough values for ' . get_class($this));
  }

  /**
   * Returns the SVG fragemnt for a line
   * $points = array of array($x, $y, $item, $dataset, $index)
   */
  public function DrawLine($dataset, $points, $y_bottom, $stroke_colour = false)
  {
    $attr = array('fill' => 'none');
    $fill = $this->ArrayOption($this->fill_under, $dataset);
    $dash = $this->ArrayOption($this->line_dash, $dataset);
    $stroke_width = $this->ArrayOption($this->line_stroke_width, $dataset);
    if(!empty($dash))
      $attr['stroke-dasharray'] = $dash;
    $attr['stroke-width'] = $stroke_width <= 0 ? 1 : $stroke_width;
    $path = $fillpath = '';
    $cmd = 'M';
    foreach($points as $point) {
      list($x, $y, $item, $dataset, $index) = $point;

      if(empty($fillpath))
        $fillpath = "M$x {$y_bottom}L";
      $path .= "$cmd$x $y ";
      $fillpath .= "$x $y ";

      // no need to repeat same L command
      $cmd = $cmd == 'M' ? 'L' : '';
      $last_x = $x;
    }
    $attr['stroke'] = $stroke_colour ? $this->stroke_colour :
      $this->GetColour(null, 0, $dataset, true);
    $this->curr_line_style = $attr;
    $attr['d'] = $path;
    if($this->semantic_classes)
      $attr['class'] = "series{$dataset}";
    $graph_line = $this->Element('path', $attr);

    if($fill) {
      $opacity = $this->ArrayOption($this->fill_opacity, $dataset);
      $fillpath .= "L{$last_x} {$y_bottom}z";
      $fill_style = array(
        'fill' => $this->GetColour(null, 0, $dataset),
        'd' => $fillpath,
        'stroke' => 'none',
      );
      if($opacity < 1)
        $fill_style['opacity'] = $opacity;
      if($this->semantic_classes)
        $fill_style['class'] = "series{$dataset}";
      $graph_line = $this->Element('path', $fill_style) . $graph_line;

      unset($fill_style['d'], $fill_style['class']);
      $this->curr_fill_style = $fill_style;
    } else {
      $this->curr_fill_style = NULL;
    }

    // add markers (and therefore legend entries too)
    foreach($points as $point) {
      list($x, $y, $item, $dataset, $index) = $point;

      $marker_id = $this->MarkerLabel($dataset, $index, $item, $x, $y);
      $extra = empty($marker_id) ? NULL : array('id' => $marker_id);
      $this->AddMarker($x, $y, $item, $extra, $dataset);
    }
    return $graph_line;
  }

  /**
   * Override to add the line info and marker at the same time
   */
  protected function SetLegendEntry($dataset, $index, $item, $style_info)
  {
    $style_info['line_style'] = $this->curr_line_style;
    $style_info['fill_style'] = $this->curr_fill_style;
    parent::SetLegendEntry($dataset, $index, $item, $style_info);
  }

  /**
   * Return line and marker for legend
   */
  public function DrawLegendEntry($x, $y, $w, $h, $entry)
  {
    if(!isset($entry->style['line_style']))
      return '';
    $marker = parent::DrawLegendEntry($x, $y, $w, $h, $entry);
    $h1 = $h/2;
    $y += $h1;
    $line = $entry->style['line_style'];
    $line['d'] = "M$x {$y}l$w 0";
    $graph_line = $this->Element('path', $line);

    if(!is_null($entry->style['fill_style'])) {
      $fill = $entry->style['fill_style'];
      $fill['d'] = "M$x {$y}l$w 0 0 $h1 -$w 0z";
      $graph_line = $this->Element('path', $fill) . $graph_line;
    }
    return $graph_line . $marker;
  }

}

