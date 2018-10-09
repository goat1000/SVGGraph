<?php
/**
 * Copyright (C) 2011-2018 Graham Breach
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

require_once 'SVGGraphRadarGraph.php';
require_once 'SVGGraphMultiGraph.php';

/**
 * MultiRadarGraph - multiple radar graphs on one plot
 */
class MultiRadarGraph extends RadarGraph {

  protected function Draw()
  {
    $body = $this->Grid() . $this->UnderShapes();

    $plots = '';
    $y_axis = $this->y_axes[$this->main_y_axis];
    $chunk_count = count($this->multi_graph);
    $this->ColourSetup($this->multi_graph->ItemsCount(-1), $chunk_count);
    for($i = 0; $i < $chunk_count; ++$i) {
      $bnum = 0;
      $cmd = 'M';
      $path = '';
      $attr = array('fill' => 'none');
      $fill = $this->GetOption(array('fill_under', $i));
      $dash = $this->GetOption(array('line_dash', $i));
      $stroke_width = $this->GetOption(array('line_stroke_width', $i));
      $fill_style = null;
      if($fill) {
        $attr['fill'] = $this->GetColour(null, 0, $i);
        $fill_style = array('fill' => $attr['fill']);
        $opacity = $this->GetOption(array('fill_opacity', $i));
        if($opacity < 1.0) {
          $attr['fill-opacity'] = $opacity;
          $fill_style['fill-opacity'] = $opacity;
        }
      }
      if(!empty($dash))
        $attr['stroke-dasharray'] = $dash;
      $attr['stroke-width'] = $stroke_width <= 0 ? 1 : $stroke_width;

      $marker_points = array();
      foreach($this->multi_graph[$i] as $item) {
        $point_pos = $this->GridPosition($item, $bnum);
        if(!is_null($item->value) && !is_null($point_pos)) {
          $val = $y_axis->Position($item->value);
          if(!is_null($val)) {
            $angle = $this->arad + $point_pos / $this->g_height;
            $x = $this->xc + ($val * sin($angle));
            $y = $this->yc + ($val * cos($angle));

            $path .= "$cmd$x $y ";

            // no need to repeat same L command
            $cmd = $cmd == 'M' ? 'L' : '';
            $marker_points[$bnum] = compact('x', 'y', 'item');
          }
        }
        ++$bnum;
      }

      if($path != '') {
        $attr['stroke'] = $this->GetColour(null, 0, $i, true);
        $path .= "z";
        $this->curr_line_style = $attr;
        $this->curr_fill_style = $fill_style;
        foreach($marker_points as $bnum => $m) {
          $marker_id = $this->MarkerLabel($i, $bnum, $m['item'], $m['x'], $m['y']);
          $extra = empty($marker_id) ? NULL : array('id' => $marker_id);
          $this->AddMarker($m['x'], $m['y'], $m['item'], $extra, $i);
        }
        $attr['d'] = $path;
        if($this->semantic_classes)
          $attr['class'] = "series{$i}";
        $plots .= $this->Element('path', $attr);
      }
    }

    $group = array();
    $this->ClipGrid($group);
    if($this->semantic_classes)
      $group['class'] = "series";
    $body .= $this->Element('g', $group, NULL, $plots);
    $body .= $this->OverShapes();
    $body .= $this->Axes();
    $body .= $this->CrossHairs();
    $body .= $this->DrawMarkers();
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
        $this->datetime_keys, $this->require_integer_keys);
  }
}

