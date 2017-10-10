<?php
/**
 * Copyright (C) 2010-2017 Graham Breach
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

require_once 'SVGGraphPieGraph.php';

class Pie3DGraph extends PieGraph {

  // for 100% pie the flat sides are all hidden
  protected $draw_flat_sides = false;

  // whether the gradient overlay is done in pieces or not
  protected $separate_slices = false;

  protected function Draw()
  {
    // modify pad_bottom to make PieGraph do the hard work
    $pb = $this->pad_bottom;
    $space = $this->height - $this->pad_top - $this->pad_bottom;
    if($space < $this->depth)
      $this->depth = $space / 2;
    $this->pad_bottom += $this->depth;
    $this->Calc();
    $this->pad_bottom = $pb;

    // see if flat sides are visible
    if(is_numeric($this->end_angle)) {
      $start = fmod(deg2rad($this->start_angle), M_PI * 2.0);
      $end = fmod(deg2rad($this->end_angle), M_PI * 2.0);
      if($this->reverse) {
        if(($end > M_PI * 0.5 && $end < M_PI * 1.5) ||
          $start < M_PI * 0.5 || $start > M_PI * 1.5)
          $this->draw_flat_sides = true;
      } else {
        if(($start > M_PI * 0.5 && $start < M_PI * 1.5) ||
          $end < M_PI * 0.5 || $end > M_PI * 1.5)
          $this->draw_flat_sides = true;
      }

      // might not be necessary, but it doesn't hurt
      $this->separate_slices = true;
    }
    return PieGraph::Draw();
  }

  /**
   * Returns the SVG markup to draw all slices
   */
  protected function DrawSlices($slice_list)
  {
    $edge_list = array();
    foreach($slice_list as $key => $slice) {

      $edges = $this->GetEdges($slice);
      if(!empty($edges))
        $edge_list = array_merge($edge_list, $edges);
    }

    // should not be empty - that would mean no sides visible
    if(empty($edge_list))
      return parent::DrawSlices($slice_list);

    usort($edge_list, 'SVGGraphEdgeSort');

    $edges = array();
    $overlay = ($this->separate_slices && is_array($this->depth_shade_gradient));
    foreach($edge_list as $edge) {

      $edges[] = $this->GetEdge($edge, $this->x_centre, $this->y_centre,
        $this->depth, $overlay);
    }

    return implode($edges) . parent::DrawSlices($slice_list);
  }

  /**
   * Returns the edges for the slice that face outwards
   */
  protected function GetEdges($slice)
  {
    $edges = array();

    $start = $this->draw_flat_sides ? 0 : 2;
    $end = 3;
    for($e = $start; $e <= $end; ++$e) {
      $edge = new PieSliceEdge($this, $e, $slice, $this->s_angle);
      if($edge->Visible())
        $edges[] = $edge;
    }
    return $edges;
  }

  /**
   * Returns an edge markup
   */
  protected function GetEdge($edge, $x_centre, $y_centre, $depth, $overlay)
  {
    $item = $edge->slice['item'];
    $attr = array(
      'fill' => $this->GetColour($item, $edge->slice['colour_index'], NULL,
        true, false)
    );
    if($this->show_tooltips)
      $this->SetTooltip($attr, $item, 0, $item->key, $item->value,
        !$this->compat_events);
    $content = $edge->Draw($this, $x_centre, $y_centre, $depth, $attr);

    // the gradient overlay uses a clip-path
    if($overlay && $edge->Curve()) {
      $clip_id = $this->NewID();
      $this->defs[] = $edge->GetClipPath($this, $x_centre, $y_centre, $depth,
        $clip_id);
      $content .= $this->GetEdgeOverlay($x_centre, $y_centre, $depth, $clip_id);
    }
    return $this->GetLink($item, $item->key, $content);
  }

  /**
   * Overlays the gradient on the pie sides
   */
  protected function PieExtras()
  {
    // this is only used when not drawing each segment separately
    if(!$this->separate_slices && is_array($this->depth_shade_gradient))
      return $this->GetEdgeOverlay($this->x_centre, $this->y_centre,
        $this->depth);

    return '';
  }

  /**
   * Returns the gradient overlay, optionally clipped
   */
  protected function GetEdgeOverlay($x_centre, $y_centre, $depth,
    $clip_path = NULL)
  {
    $gradient_id = $this->AddGradient($this->depth_shade_gradient);
    $start = $this->reverse ? M_PI : M_PI * 2;
    $end = $this->reverse ? M_PI * 2 : M_PI;

    if(is_null($clip_path)) {
      $slice = array(
        'angle_start' => $start,
        'angle_end' => $end,
        'radius_x' => $this->radius_x,
        'radius_y' => $this->radius_y,
        'attr' => array('fill' => "url(#{$gradient_id})"),
      );
      $edge = new PieSliceEdge($this, 2, $slice, 0.0);
      return $edge->Draw($this, $x_centre, $y_centre, $depth);
    }

    // clip a rect to the edge shape
    $rect = array(
      'x' => $x_centre - $this->radius_x,
      'y' => $y_centre - $this->radius_y,
      'width' => $this->radius_x * 2.0,
      'height' => $this->radius_y * 2.0 + $this->depth + 2.0,
      'fill' => "url(#{$gradient_id})",
      'clip-path' => "url(#{$clip_path})",
    );
    return $this->Element('rect', $rect);
  }
}

/**
 * The PieSliceEdge class calculates and draws the 3D slice edges
 */
class PieSliceEdge {

  public $x;
  public $y;
  public $slice;

  // types: 0 => start, 1 => end, 2 => curve,
  // 3 => second curve (if it exists), -1 = no edge
  protected $type;
  protected $a1;
  protected $a2;

  /**
   * $slice is the slice details array
   * $s_angle is the start angle in radians
   */
  public function __construct(&$graph, $type, $slice, $s_angle)
  {
    $this->type = $type;
    $this->slice = $slice;

    $start_angle = $slice['angle_start'] + $s_angle;
    $end_angle = $slice['angle_end'] + $s_angle;

    if(isset($slice['single_slice']) && $slice['single_slice'] &&
      !is_numeric($graph->end_angle)) {
      // if end_angle is not set, then single_slice is full pie
      $start_angle = 0.0;
      $end_angle = M_PI;
    } elseif($graph->reverse) {
      // apply reverse now to save thinking about it later
      $s = M_PI * 4.0 - $end_angle;
      $e = M_PI * 4.0 - $start_angle;
      $start_angle = $s;
      $end_angle = $e;
    }

    $this->a1 = fmod($start_angle, M_PI * 2.0);
    $this->a2 = fmod($end_angle, M_PI * 2.0);
    if($this->a2 < $this->a1)
      $this->a2 += M_PI * 2.0;

    // truncate curves to visible area
    if($type == 2) {
      if($this->a1 < M_PI && $this->a2 > M_PI)
        $this->a2 = M_PI;
      elseif($this->a1 > M_PI && $this->a2 > M_PI * 2.0)
        $this->a1 = M_PI * 2.0;
    }
    if($type == 3) {
      // type 3 edges are for pie slices that show at both sides
      if($this->a1 < M_PI && $this->a2 > M_PI * 2.0)
        $this->a1 = M_PI * 2.0;
      else
        $this->type = -1;
    }

    if($type == 0 || $type == 1) {
      $angle = $type == 1 ? $this->a2 : $this->a1;
      $this->x = 2000.0 * cos($angle);
      $this->y = 2000.0 * sin($angle);
    } else {
      // if the edge crosses the bottom use full distance
      if(($this->a1 < M_PI * 0.5 && $this->a2 > M_PI * 0.5) ||
        ($this->a2 > M_PI * 2.5)) {
        $this->x = 0;
        $this->y = 2000.0;
      } else {
        $s1 = 2000.0 * sin($this->a1);
        $s2 = 2000.0 * sin($this->a2);
        if($s1 > $s2) {
          $this->y = $s1;
          $this->x = 2000.0 * cos($this->a1);
        } else {
          $this->y = $s2;
          $this->x = 2000.0 * cos($this->a2);
        }
      }
    }
  }

  /**
   * Returns TRUE if the edge faces forwards
   */
  public function Visible()
  {
    // type -1 is for non-existent edges
    if($this->type == -1)
      return false;

    // the flat edges are visible left or right
    if($this->type == 0) {
      // start on right not visible
      if($this->a1 < M_PI * 0.5 || $this->a1 > M_PI * 1.5)
        return false;
      return true;
    }

    $a2 = fmod($this->a2, M_PI * 2.0);
    if($this->type == 1) {
      // end on left not visible
      if($a2 > M_PI * 0.5 && $a2 < M_PI * 1.5)
        return false;
      return true;
    }

    // if both ends are at top and slice angle < 180, not visible
    if($this->a1 >= M_PI && $this->a2 <= M_PI * 2.0 &&
      $this->a2 - $this->a1 < M_PI * 2.0)
      return false;
    return true;
  }

  /**
   * Returns TRUE if this is a curved edge
   */
  public function Curve()
  {
    return $this->type == 2 || $this->type == 3;
  }

  /**
   * Draws the edge
   */
  public function Draw(&$graph, $x_centre, $y_centre, $depth, $attr = NULL)
  {
    if(is_null($attr))
      $attr = $this->slice['attr'];
    else
      $attr = array_merge($this->slice['attr'], $attr);
    $attr['d'] = $this->GetPath($x_centre, $y_centre, $depth);
    return $graph->Element('path', $attr);
  }

  /**
   * Returns the edge as a clipPath element
   */
  public function GetClipPath(&$graph, $x_centre, $y_centre, $depth, $clip_id)
  {
    $attr = array('id' => $clip_id);
    $path = array('d' => $this->GetPath($x_centre, $y_centre, $depth));

    return $graph->Element('clipPath', $attr, NULL,
      $graph->Element('path', $path));
  }

  /**
   * Returns the correct path
   */
  protected function GetPath($x_centre, $y_centre, $depth)
  {
    if($this->type == 0 || $this->type == 1) {
      $path = $this->GetFlatPath($this->type == 1 ? $this->a2 : $this->a1,
        $x_centre, $y_centre, $depth);
    } else {
      $path = $this->GetCurvedPath($x_centre, $y_centre, $depth);
    }
    return $path;
  }

  /**
   * Returns the path for a flat edge
   */
  protected function GetFlatPath($angle, $x_centre, $y_centre, $depth)
  {
    $x1 = $x_centre + $this->slice['radius_x'] * cos($angle);
    $y1 = $y_centre + $this->slice['radius_y'] * sin($angle) + $depth;
    return "M{$x_centre},{$y_centre} v{$depth} L{$x1},{$y1} v-{$depth}z";
  }

  /**
   * Returns the path for the curved edge
   */
  protected function GetCurvedPath($x_centre, $y_centre, $depth)
  {
    $rx = $this->slice['radius_x'];
    $ry = $this->slice['radius_y'];
    $x1 = $x_centre + $rx * cos($this->a1);
    $y1 = $y_centre + $ry * sin($this->a1);
    $x2 = $x_centre + $rx * cos($this->a2);
    $y2 = $y_centre + $ry * sin($this->a2);
    $y2d = $y2 + $depth;

    $outer = 0; // edge is never > PI
    $sweep = 1;

    $path = "M{$x1},{$y1} v{$depth} A{$rx} {$ry} 0 " .
      "$outer,$sweep {$x2},{$y2d} v-{$depth} ";
    $sweep = $sweep ? 0 : 1;
    $path .= "A{$rx} {$ry} 0 $outer,$sweep {$x1},{$y1}";
    return $path;
  }
}

function SVGGraphEdgeSort($a, $b)
{
  if($a->y == $b->y)
    return 0;
  return ($a->y < $b->y ? -1 : 1);
}

