<?php
/**
 * Copyright (C) 2012-2018 Graham Breach
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

require_once 'SVGGraphLineGraph.php';
require_once 'SVGGraphDisplayAxisRadar.php';
require_once 'SVGGraphDisplayAxisRotated.php';

/**
 * RadarGraph - a line graph that goes around in circles
 */
class RadarGraph extends LineGraph {

  protected $xc;
  protected $yc;
  protected $radius;
  protected $arad;
  private $pad_v_axis_label;

  // in the case of radar graphs, $label_centre means we want an axis that
  // ends at N points + 1
  protected $label_centre = true;
  protected $require_integer_keys = false;
  protected $single_axis = true;

  protected function Draw()
  {
    $body = $this->Grid() . $this->UnderShapes();

    $dash = $this->GetOption(array('line_dash', 0));
    $stroke_width = $this->GetOption(array('line_stroke_width', 0));
    $fill_under = $this->GetOption(array('fill_under', 0));
    $attr = array(
      'stroke' => $this->GetOption(array('stroke_colour', 0)),
      'fill' => 'none',
      'stroke-width' => ($stroke_width <= 0 ? 1 : $stroke_width),
    );
    if(!empty($dash))
      $attr['stroke-dasharray'] = $dash;
    $this->ColourSetup($this->values->ItemsCount());

    $bnum = 0;
    $cmd = 'M';

    $path = '';
    if($fill_under) {
      $attr['fill'] = $this->GetColour(null, 0);
      $this->curr_fill_style = array(
        'fill' => $attr['fill'],
        'stroke' => $attr['fill']
      );

      $fill_opacity = $this->GetOption(array('fill_opacity', 0));
      if($fill_opacity < 1.0) {
        $attr['fill-opacity'] = $fill_opacity;
        $this->curr_fill_style['fill-opacity'] = $fill_opacity;
      }
    }

    $y_axis = $this->y_axes[$this->main_y_axis];
    $marker_points = array();
    foreach($this->values[0] as $item) {
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

    $path .= "z";

    $this->curr_line_style = $attr;
    foreach($marker_points as $bnum => $m) {
      $marker_id = $this->MarkerLabel(0, $bnum, $m['item'], $m['x'], $m['y']);
      $extra = empty($marker_id) ? NULL : array('id' => $marker_id);
      $this->AddMarker($m['x'], $m['y'], $m['item'], $extra);
    }
    $attr['d'] = $path;
    $group = array();

    $this->ClipGrid($group);
    if($this->semantic_classes) {
      $group['class'] = 'series';
      $attr['class'] = "series0";
    }

    $body .= $this->Element('g', $group, NULL, $this->Element('path', $attr));
    $body .= $this->OverShapes();
    $body .= $this->Axes();
    $body .= $this->CrossHairs();
    $body .= $this->DrawMarkers();
    return $body;
  }

  /**
   * Finds the grid position for radar graphs, returns NULL if not on graph
   */
  protected function GridPosition($item, $ikey)
  {
    $gkey = $this->values->AssociativeKeys() ? $ikey : $item->key;
    $axis = $this->x_axes[$this->main_x_axis];
    $offset = $axis->Position($gkey);
    if($offset >= 0 && $offset < $this->g_width)
      return $this->reverse ? -$offset : $offset;
    return NULL;
  }

  /**
   * Returns the $x value as a grid position
   */
  public function GridX($x, $axis_no = NULL)
  {
    $p = $this->UnitsX($x, $axis_no);
    return $p;
  }

  /**
   * Returns the $y value as a grid position
   */
  public function GridY($y, $axis_no = NULL)
  {
    $p = $this->UnitsY($y, $axis_no);
    return $p;
  }

  /**
   * Returns a DisplayAxisRadar for the round axis
   */
  protected function GetDisplayAxis($axis, $axis_no, $orientation, $type)
  {
    $var = "main_{$type}_axis";
    $main = ($axis_no == $this->{$var});
    if($orientation == 'h')
      return new DisplayAxisRadar($this, $axis, $axis_no, $orientation, $type,
        $main, $this->xc, $this->yc, $this->radius);

    return new DisplayAxisRotated($this, $axis, $axis_no, $orientation, $type,
      $main, $this->arad);
  }

  /**
   * Returns the location of an axis
   */
  protected function GetAxisLocation($orientation, $axis_no)
  {
    return array($this->xc, $this->yc);
  }

  /**
   * Convert X, Y in grid space to radial position
   */
  public function TransformCoords($x, $y)
  {
    $angle = $x / $this->g_height;
    if($this->grid_straight) {
      // this complicates things...

      // get all the grid points, div and subdiv
      $points = array_merge($this->GetGridPointsX(0), $this->GetSubDivsX(0));
      $grid_angles = array();
      foreach($points as $point) {
        $grid_angles[] = $point->position / $this->radius;
      }
      sort($grid_angles);

      // find angle between (sub)divisions
      $div_angle = $grid_angles[1] - $grid_angles[0];

      // use trig to find length of Y
      $a = fmod($angle, $div_angle);
      $t = ($div_angle / 2) - $a;

      $y2 = $y * cos($div_angle / 2);
      $y = $y2 / cos($t);
    }
    $angle += $this->arad;
    $new_x = $this->xc + ($y * sin($angle));
    $new_y = $this->yc + ($y * cos($angle));

    return array($new_x, $new_y);
  }

  /**
   * Find the bounding box of the axis text for given axis lengths
   */
  protected function FindAxisBBox($length_x, $length_y, $x_axes, $y_axes)
  {
    $this->xc = $length_x / 2;
    $this->yc = $length_y / 2;
    $diameter = min($length_x, $length_y);
    $length_y = $diameter / 2;
    $length_x = 2 * M_PI * $length_y;
    $this->radius = $length_y;
    foreach($x_axes as $a)
      $a->SetLength($length_x);
    foreach($y_axes as $a)
      $a->SetLength($length_y);

    $min_x = array($this->width);
    $min_y = array($this->height);
    $max_x = array(0); $max_y = array(0);

    $display_axis = $this->GetDisplayAxis($x_axes[0], 0, 'h', 'x');
    $axis_m = $display_axis->Measure();
    $min_x[] = $axis_m['x'];
    $max_x[] = $axis_m['x'] + $axis_m['width'];
    $min_y[] = $axis_m['y'];
    $max_y[] = $axis_m['y'] + $axis_m['height'];
    $display_axis = $this->GetDisplayAxis($y_axes[0], 0, 'v', 'y');
    $axis_m = $display_axis->Measure();
    $min_x[] = $axis_m['x'] + $this->xc;
    $max_x[] = $axis_m['x'] + $axis_m['width'] + $this->xc;
    $min_y[] = $axis_m['y'] + $this->yc;
    $max_y[] = $axis_m['y'] + $axis_m['height'] + $this->yc;

    $min_x = min($min_x);
    $min_y = min($min_y);
    $max_x = max($max_x);
    $max_y = max($max_y);

    $bbox = compact('min_x', 'min_y', 'max_x', 'max_y');
    $this->radius = null;
    return $bbox;
  }

  /**
   * Draws concentric Y grid lines
   */
  public function YGrid(&$y_points)
  {
    $path = '';

    if($this->grid_straight) {
      $grid_angles = array();
      $points = array_merge($this->GetGridPointsX(0), $this->GetSubDivsX(0));
      foreach($points as $point) {
        $new_x = $point->position - $this->pad_left;
        $grid_angles[] = $this->arad + $new_x / $this->radius;
      }
      // put the grid angles in order
      sort($grid_angles);
      foreach($y_points as $y) {
        $y = $y->position;
        $x1 = $this->xc + $y * sin($this->arad);
        $y1 = $this->yc + $y * cos($this->arad);
        $path .= "M$x1 {$y1}L";
        foreach($grid_angles as $a) {
          $x1 = $this->xc + $y * sin($a);
          $y1 = $this->yc + $y * cos($a);
          $path .= "$x1 $y1 ";
        }
        $path .= "z";
      }
    } else {
      foreach($y_points as $y) {
        $y = $y->position;
        $p1 = $this->xc - $y;
        $p2 = $this->xc + $y;
        $path .= "M$p1 {$this->yc}A $y $y 0 1 1 $p2 {$this->yc}";
        $path .= "M$p2 {$this->yc}A $y $y 0 1 1 $p1 {$this->yc}";
      }
    }
    return $path;
  }

  /**
   * Draws radiating X grid lines
   */
  protected function XGrid(&$x_points)
  {
    $path = '';
    foreach($x_points as $x) {
      $x = $x->position - $this->pad_left;
      $angle = $this->arad + $x / $this->radius;
      $p1 = $this->radius * sin($angle);
      $p2 = $this->radius * cos($angle);
      $path .= "M{$this->xc} {$this->yc}l$p1 $p2";
    }
    return $path;
  }

  /**
   * Draws the grid behind the graph
   */
  protected function Grid()
  {
    $this->CalcAxes();
    $this->CalcGrid();
    if(!$this->show_grid || (!$this->show_grid_h && !$this->show_grid_v))
      return '';

    $xc = $this->xc;
    $yc = $this->yc;
    $r = $this->radius;

    $back = $subpath = '';
    $back_colour = $this->ParseColour($this->grid_back_colour, null, false,
      false, true);
    $y_points = $this->GetGridPointsY(0);
    $x_points = $this->GetGridPointsX(0);
    $y_subdivs = $this->GetSubDivsY(0);
    $x_subdivs = $this->GetSubDivsX(0);
    if(!empty($back_colour) && $back_colour != 'none') {
      // use the YGrid function to get the path
      $points = array(new GridPoint($r, '', 0));
      $bpath = array(
        'd' => $this->YGrid($points),
        'fill' => $back_colour
      );
      if($this->grid_back_opacity != 1)
        $bpath['fill-opacity'] = $this->grid_back_opacity;
      $back = $this->Element('path', $bpath);
    }
    if($this->grid_back_stripe) {
      // use array of colours if available, otherwise stripe a single colour
      $colours = is_array($this->grid_back_stripe_colour) ?
        $this->grid_back_stripe_colour :
        array(NULL, $this->grid_back_stripe_colour);
      $c = 0;
      $num_colours = count($colours);
      $num_points = count($y_points);
      while($c < $num_points - 1) {
        if(!is_null($colours[$c % $num_colours])) {
          $s_points = array($y_points[$c], $y_points[$c + 1]);
          $bpath = array(
            'fill' => $this->ParseColour($colours[$c % $num_colours]),
            'd' => $this->YGrid($s_points),
            'fill-rule' => 'evenodd',
          );
          if($this->grid_back_stripe_opacity != 1)
            $bpath['fill-opacity'] = $this->grid_back_stripe_opacity;
          $back .= $this->Element('path', $bpath);
        }
        ++$c;
      }
    }
    if($this->show_grid_subdivisions) {
      $subpath_h = $this->show_grid_h ? $this->YGrid($y_subdivs) : '';
      $subpath_v = $this->show_grid_v ? $this->XGrid($x_subdivs) : '';
      if($subpath_h != '' || $subpath_v != '') {
        $colour_h = $this->GetOption('grid_subdivision_colour_h',
          'grid_subdivision_colour', 'grid_colour_h', 'grid_colour');
        $colour_v = $this->GetOption('grid_subdivision_colour_v',
          'grid_subdivision_colour', 'grid_colour_v', 'grid_colour');
        $dash_h = $this->GetOption('grid_subdivision_dash_h',
          'grid_subdivision_dash', 'grid_dash_h', 'grid_dash');
        $dash_v = $this->GetOption('grid_subdivision_dash_v',
          'grid_subdivision_dash', 'grid_dash_v', 'grid_dash');

        if($dash_h == $dash_v && $colour_h == $colour_v) {
          $subpath = $this->GridLines($subpath_h . $subpath_v, $colour_h,
            $dash_h, 'none');
        } else {
          $subpath = $this->GridLines($subpath_h, $colour_h, $dash_h, 'none') .
            $this->GridLines($subpath_v, $colour_v, $dash_v, 'none');
        }
      }
    }

    $path_v = $this->show_grid_h ? $this->YGrid($y_points) : '';
    $path_h = $this->show_grid_v ? $this->XGrid($x_points) : '';

    $colour_h = $this->GetOption('grid_colour_h', 'grid_colour');
    $colour_v = $this->GetOption('grid_colour_v', 'grid_colour');
    $dash_h = $this->GetOption('grid_dash_h', 'grid_dash');
    $dash_v = $this->GetOption('grid_dash_v', 'grid_dash');

    if($dash_h == $dash_v && $colour_h == $colour_v) {
      $path = $this->GridLines($path_v . $path_h, $colour_h, $dash_h, 'none');
    } else {
      $path = $this->GridLines($path_h, $colour_h, $dash_h, 'none') .
        $this->GridLines($path_v, $colour_v, $dash_v, 'none');
    }

    return $back . $subpath . $path;
  }

  /**
   * Sets the grid size as circumference x radius
   */
  protected function SetGridDimensions()
  {
    if(is_null($this->radius)) {
      $w = $this->width - $this->pad_left - $this->pad_right;
      $h = $this->height - $this->pad_top - $this->pad_bottom;
      $this->xc = $this->pad_left + $w / 2;
      $this->yc = $this->pad_top + $h / 2;
      $this->radius = min($w, $h) / 2;
    }
    $this->g_height = $this->radius;
    $this->g_width = 2 * M_PI * $this->radius;
  }

  /**
   * Calculate the extra details for radar axes
   */
  protected function CalcAxes()
  {
    $this->arad = (90 + $this->start_angle) * M_PI / 180;
    $this->axis_right = false;
    parent::CalcAxes();
  }

  /**
   * Returns what would be the vertical axis label
   */
  protected function VLabel(&$attribs)
  {
    if(empty($this->label_v))
      return '';

    $svg_text = new SVGGraphText($this);
    $c = cos($this->arad);
    $s = sin($this->arad);
    $a = $this->arad + ($s * $c > 0 ? - M_PI_2 : M_PI_2);
    $offset = max($this->division_size * (int)$this->show_divisions,
      $this->subdivision_size * (int)$this->show_subdivisions) +
      $this->pad_v_axis_label + $this->label_space;
    $offset += ($c < 0 ? ($svg_text->Lines($this->label_v) - 1) : 1) *
      $this->label_font_size;

    $x2 = $offset * sin($a);
    $y2 = $offset * cos($a);
    $p = $this->radius / 2;
    $x = $this->xc + $p * sin($this->arad) + $x2;
    $y = $this->yc + $p * cos($this->arad) + $y2;
    $a = $s < 0 ? 180 - $this->start_angle : -$this->start_angle;
    $pos = array(
      'x' => $x,
      'y' => $y,
      'transform' => "rotate($a,$x,$y)",
    );
    return $svg_text->Text($this->label_v, $this->label_font_size,
      array_merge($attribs, $pos));
  }

  /**
   * Returns the grid points for a Y-axis
   */
  protected function GetGridPointsY($axis)
  {
    $points = $this->y_axes[$axis]->GetGridPoints(0);
    foreach($points as $k => $p)
      $points[$k]->position = -$p->position;
    return $points;
  }

  /**
   * Returns the subdivisions for a Y-axis
   */
  protected function GetSubDivsY($axis)
  {
    $points = $this->y_axes[$axis]->GetGridSubdivisions(
      $this->minimum_subdivision,
      $this->GetOption(array('minimum_units_y', $axis)), 0, 
      $this->GetOption(array('subdivision_v', $axis)));
    foreach($points as $k => $p)
      $points[$k]->position = -$p->position;
    return $points;
  }

  /**
   * Calculates guidelines
   */
  protected function CalcGuidelines($g = null)
  {
    // in the case of radar graphs, prevents junk guidelines being drawn
  }
}

