<?php
/**
 * Copyright (C) 2009-2015 Graham Breach
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
  protected $line_styles = array();
  protected $fill_styles = array();

  protected function Draw()
  {
    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);
    $this->ColourSetup($this->values->ItemsCount());

    $bnum = 0;
    $cmd = 'M';
    $y_axis_pos = $this->height - $this->pad_bottom - 
      $this->y_axes[$this->main_y_axis]->Zero();
    $y_bottom = min($y_axis_pos, $this->height - $this->pad_bottom);

    $points = array();
    foreach($this->values[0] as $item) {
      $x = $this->GridPosition($item->key, $bnum);
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
    $body .= $this->Element('g', $group, NULL, $graph_line);
    $body .= $this->Guidelines(SVGG_GUIDELINE_ABOVE);
    $body .= $this->Axes();
    $body .= $this->CrossHairs();
    $body .= $this->DrawMarkers();
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
      $marker_id = $this->MarkerLabel($dataset, $index, $item, $x, $y);
      $extra = empty($marker_id) ? NULL : array('id' => $marker_id);
      $this->AddMarker($x, $y, $item, $extra, $dataset);
      $last_x = $x;
    }
    $attr['stroke'] = $stroke_colour ? $this->stroke_colour :
      $this->GetColour(null, 0, $dataset, true);
    $this->line_styles[$dataset] = $attr;
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
      $this->fill_styles[$dataset] = $fill_style;
    }

    return $graph_line;
  }

  /**
   * Return line and marker for legend
   */
  public function DrawLegendEntry($set, $x, $y, $w, $h)
  {
    if(!isset($this->line_styles[$set]))
      return '';

    $marker = parent::DrawLegendEntry($set, $x, $y, $w, $h);
    $h1 = $h/2;
    $y += $h1;
    $line = $this->line_styles[$set];
    $line['d'] = "M$x {$y}l$w 0";
    $graph_line = $this->Element('path', $line);

    if($this->ArrayOption($this->fill_under,$set)) {
      $fill = $this->fill_styles[$set];
      $fill['d'] = "M$x {$y}l$w 0 0 $h1 -$w 0z";
      $graph_line = $this->Element('path', $fill) . $graph_line;
    }
    return $graph_line . $marker;
  }

}

