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

require_once 'SVGGraphRadarGraph.php';
require_once 'SVGGraphMultiGraph.php';

/**
 * MultiRadarGraph - multiple radar graphs on one plot
 */
class MultiRadarGraph extends RadarGraph {

  protected function Draw()
  {
    $body = $this->Grid();

    $plots = '';
    $y_axis = $this->y_axes[$this->main_y_axis];
    $chunk_count = count($this->multi_graph);
    $this->ColourSetup($this->multi_graph->ItemsCount(-1), $chunk_count);
    for($i = 0; $i < $chunk_count; ++$i) {
      $bnum = 0;
      $cmd = 'M';
      $path = '';
      $attr = array('fill' => 'none');
      $fill = $this->ArrayOption($this->fill_under, $i);
      $dash = $this->ArrayOption($this->line_dash, $i);
      $stroke_width = $this->ArrayOption($this->line_stroke_width, $i);
      $fill_style = null;
      if($fill) {
        $attr['fill'] = $this->GetColour(null, 0, $i);
        $fill_style = array('fill' => $attr['fill']);
        $opacity = $this->ArrayOption($this->fill_opacity, $i);
        if($opacity < 1.0) {
          $attr['fill-opacity'] = $opacity;
          $fill_style['fill-opacity'] = $opacity;
        }
      }
      if(!is_null($dash))
        $attr['stroke-dasharray'] = $dash;
      $attr['stroke-width'] = $stroke_width <= 0 ? 1 : $stroke_width;

      foreach($this->multi_graph[$i] as $item) {
        $point_pos = $this->GridPosition($item->key, $bnum);
        if(!is_null($item->value) && !is_null($point_pos)) {
          $val = $y_axis->Position($item->value);
          if(!is_null($val)) {
            $angle = $this->arad + $point_pos / $this->g_height;
            $x = $this->xc + ($val * sin($angle));
            $y = $this->yc + ($val * cos($angle));

            $path .= "$cmd$x $y ";

            // no need to repeat same L command
            $cmd = $cmd == 'M' ? 'L' : '';
            $marker_id = $this->MarkerLabel($i, $bnum, $item, $x, $y);
            $extra = empty($marker_id) ? NULL : array('id' => $marker_id);
            $this->AddMarker($x, $y, $item, $extra, $i);
          }
        }
        ++$bnum;
      }

      if($path != '') {
        $attr['stroke'] = $this->GetColour(null, 0, $i, true);
        $this->line_styles[] = $attr;
        $this->fill_styles[] = $fill_style;
        $path .= "z";
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
        $this->require_integer_keys);
  }
}

