SVGGraph Library version 3.19
=============================

This library provides PHP classes and functions for easily creating SVG
graphs from data. Version 3.0 of SVGGraph requires at least PHP 5.4 - if
you must use an earlier version of PHP then use the SVGGraph 2.x version.

Here is a basic example:
 $graph = new Goat1000\SVGGraph\SVGGraph(640, 480);
 $graph->colours(['red', 'green', 'blue']);
 $graph->values(['Tom' => 100, 'Dick' => 200, 'Harry' => 150]);
 $graph->render('BarGraph');

Full documentation is available at http://www.goat1000.com/

Graph types
===========
At the moment these types of graph are supported by SVGGraph:

 EmptyGraph      - en empty document that can be used to add shapes or labels;

 BarGraph        - vertical bars, optionally hyperlinked;

 LineGraph       - a line joining the data points, with optionally hyperlinked
                   markers at the data points;

 PieGraph        - a pie chart, with optionally hyperlinked slices and option
                   to fade labels in/out when the pointer enters/leaves a
                   slice;

 Bar3DGraph      - a 3D-looking version of the BarGraph type;

 Pie3DGraph      - a 3D-looking version of the PieGraph type;

 ScatterGraph    - markers drawn at arbitrary horizontal and vertical points;

 MultiLineGraph  - multiple data sets drawn as lines on one graph;

 SteppedLineGraph - a line graph with its lines drawin in horizonal and vertical
                    steps;

 MultiSteppedLineGraph - a MultiLineGraph, but using stepped lines;

 StackedBarGraph - multiple data sets drawn as bars, stacked one on top of
                   another;

 GroupedBarGraph - multiple data sets drawn as bars, side-by-side;

 StackedLineGraph - multiple data sets, their values added together;

 StackedGroupedBarGraph - multiple data sets, their values added together and
                          split into groups;

 BarAndLineGraph - a grouped bar graph and multi-line graph on the same graph;

 StackedBarAndLineGraph - a stacked bar graph and multi-line graph on the same
                          graph;

 Histogram - a bar graph that shows the range of values;

 ParetoChart - a bar and line graph showing sorted and summed values;

 MultiScatterGraph - scatter graph supporting multiple data sets;

 HorizontalBarGraph - a bar graph with the axes swapped;

 HorizontalStackedBarGraph - a stacked bar graph drawn horizontally;

 HorizontalGroupedBarGraph - a grouped bar graph drawn horizontally;

 HorizontalBar3DGraph - a 3D bar graph with the axes swapped;

 HorizontalStackedBar3DGraph - a stacked 3D bar graph drawn horizontally;

 HorizontalGroupedBar3DGraph - a grouped 3D bar graph drawn horizontally;

 RadarGraph - a radar or star graph with values drawn as lines;

 MultiRadarGraph - a radar graph supporting multiple data sets;

 CylinderGraph - a 3D bar graph with the bars cylinder shaped;

 StackedBar3DGraph - a 3D bar graph version of the stacked bar graph;

 GroupedBar3DGraph - a 3D bar graph version of the grouped bar graph;

 StackedGroupedBar3DGraph - a 3D bar graph version of the stacked grouped bar
                            graph;

 StackedCylinderGraph - a cylinder-bar version of the stacked bar graph;

 GroupedCylinderGraph - a cylinder-bar version of the grouped bar graph;

 StackedGroupedCylinderGraph - a cylinder-bar version of the stacked grouped
                               bar graph;

 DonutGraph - a pie graph with a hole in the middle;

 SemiDonutGraph - half of a donut graph;

 Donut3DGraph - a 3D version of the donut graph;

 SemiDonut3DGraph - a 3D version of the semi-donut graph;

 PolarAreaGraph - a pie graph where the area of the slice varies instead of
                  its angle;

 PolarArea3DGraph - a 3D version of the polar area graph.

 ExplodedPieGraph - a pie graph with slices exploded out from the centre.

 ExplodedPie3DGraph - a 3D version of the exploded pie graph.

 ExplodedDonutGraph - a donut graph with its slices exploded;

 ExplodedSemiDonutGraph - a semi-donut graph with its slices exploded;

 ExplodedDonut3DGraph - a 3D version of the exploded donut graph;

 ExplodedSemiDonut3DGraph - a 3D version of the exploded semi-donut graph;

 ArrayGraph - a graph containing other graphs.

There are also these graphs that are really hard to describe:

 FloatingBarGraph; HorizontalFloatingBarGraph; BubbleGraph;
 BoxAndWhiskerGraph; PopulationPyramid; CandlestickGraph; GanttChart.

Using SVGGraph
==============
The library consists of a directory of class files and a subdirectory of font
metrics. An autoloader will load classes on demand when they are required.
SVGGraph includes an autoloader script if you don't have one - include the
"autoloader.php" file to use it.

Embedding SVG in a page
=======================
There are several ways to insert SVG graphics into a page. At time of writing,
all modern browsers support SVG natively, so the Adobe plugin is not required.

For options 1-3, I'll assume you have a PHP script called "graph.php" which
contains the SVGGraph code to generate the SVG document.

Option 1: the embed tag
 <embed src="graph.php" type="image/svg+xml" width="600" height="400"
  pluginspage="http://www.adobe.com/svg/viewer/install/" />

This method works in all browsers, though the embed tag is not part of the HTML
standard.

Option 2: the iframe tag
 <iframe src="graph.php" type="image/svg+xml" width="600" height="400"></iframe>

This method also works in all browsers, and the iframe tag is standard.

Option 3: the object tag
 <object data="graph.php" width="600" height="100" type="image/svg+xml" />

The object tag is standard, but this doesn't work in old versions of IE.

Option 4a: using the svg namespace within an xhtml document

This option is more complicated, as it requires changing the doctype and
content type of the page being served. The SVG is generated as part of the
same page.
 <?php
  header('content-type: application/xhtml+xml; charset=UTF-8');
  // $graph = new Goat1000\SVGGraph\SVGGraph(...);
  // $graph setup here!
 ?>
 <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1 plus MathML 2.0 plus SVG 1.1//EN"
  "http://www.w3.org/2002/04/xhtml-math-svg/xhtml-math-svg.dtd">
 <html xmlns="http://www.w3.org/1999/xhtml"
  xmlns:svg="http://www.w3.org/2000/svg"
  xmlns:xlink="http://www.w3.org/1999/xlink" xml:lang="en">
 <head>
  <meta http-equiv="Content-Type" content="application/xhtml+xml; charset=UTF-8" />
  <title>SVGGraph example</title>
 </head>
 <body>
  <h1>Example of SVG in XHTML</h1>
  <div>
  <?php echo $graph->Fetch('BarGraph', false); ?>
  </div>
 </body>
 </html>

This method allows you more control over how you use the SVG, though again it
doesn't work in older IE.

Option 4b: using SVG in HTML5

HTML5 is much more relaxed about containing non-HTML code, so you can insert
the SVG code without too much hassle.
 <?php
  // $graph = new Goat1000\SVGGraph\SVGGraph(...);
  // $graph setup here!
 ?>
 <!DOCTYPE html>
 <html>
 <head>
  <title>SVGGraph in HTML5</title>
 </head>
 <body>
  <h1>Example of SVG in HTMLi5</h1>
  <div>
  <?php echo $graph->Fetch('BarGraph', false); ?>
  </div>
 </body>
 </html>

This works in all modern browsers.

Option 5: using the img tag

I don't recommend this method, since it prevents tooltips and other graph
options from working. Browser support can be patchy too.

Class Constructor
=================
The SVGGraph class constructor takes three arguments, the width and height
of the SVG image in pixels and an optional array of settings to be passed to
the rendering class. The full namespace is required to create the instance.
 $graph = new Goat1000\SVGGraph\SVGGraph($width, $height, $settings);

For more information on the $settings array, see the section below.

Data Values
===========
For simple graphs you may set the data to use by passing it into the Values
function:
 $graph->values(1, 2, 3);

For more control over the data, and to assign labels, pass the values in as an
array:
 $data = array('first' => 1, 'second' => 2, 'third' => 3);
 $graph->values($data);

For graphs supporting multiple datasets, pass each dataset as an array within
an outer array:
 $data = array(
  array('first' => 1, 'second' => 2, 'third' => 3),
  array('first' => 3, 'second' => 4, 'third' => 2)
 );
 $graph->values($data);

Scatter graphs draw markers at x,y coordinates, given as the key and value in
the data array:
 $data = array(5 => 20, 6 => 30, 10 => 90, 20 => 50);
 $graph->values($data);

This will draw the markers at (5,20), (6,30), (10,90) and (20,50). To draw
markers using the same X value you must use structured data - please visit the
website for details and examples.

Note: data in this format are not supported by any of the non-scatter graph
types.

Hyperlinks
==========
The graph bars and markers may be assigned hyperlinks - each value that requires
a link should have a URL assigned to it using the Links function:
 $graph->links('/page1.html', NULL, '/page3.html');

The NULL is used here to specify that the second bar will not be linked to
anywhere.

As with the Values function, the list of links may be passed in as an array:
 $links = array('/page1.html', NULL, '/page3.html');

Using an associative array means that NULL values may be skipped.
 $links = array('first' => '/page1.html', 'third' => '/page3.html');
 $graphs->links($links);

Rendering
=========
To generate and display the graph, call the Render function passing in the
type of graph to be rendered:
 $graph->render('BarGraph');

This will send the correct content type header to the browser and output the
SVG graph.

The Render function takes two optional parameters in addition to the graph
type:
 $graph->render($type, $header, $content_type);

Passing in FALSE for $header will prevent output of the XML declaration and
doctype. Passing in FALSE for $content_type will prevent the 'image/svg+xml'
content type being set in the response header.

To generate the graph without outputting it to the browser you may use the
Fetch function instead:
 $output = $graph->fetch('BarGraph');

This function also takes an optional $header parameter:
 $output = $graph->fetch($type, $header);

Passing in FALSE as $header will prevent the returned output from containing
the XML declaration and doctype. The Fetch function never outputs the content
type to the response header.

Colours
=======
SVGGraph has several functions for setting the functions to use. The simplest
assigns an array of colours to be used in turn.
 $colours = array('red', 'green', '#00ffff', 'rgb(100,200,100)',
    array('red','green'));
 $graph->colours($colours);

You may use any of the standard named colours, or hex notation, or RGB notation.

SVGGraph also supports gradients and patterns, described in detail on the
website.

Settings
========
Many of the ways that things are displayed may be changed by passing in an array
of settings to the SVGGraph constructor:
 $settings = array('back_colour' => 'white');
 $graph = new Graph($w, $h, $settings);

There are literally hundreds of options available, though not all of them are
relevant for all graph types. For the full list of options, examples and
descriptions, please visit the website: http://www.goat1000.com/svggraph.php


Contact details
===============
For more information about this software please contact the author,
graham(at)goat1000.com or visit the website: http://www.goat1000.com/


Copyright (C) 2009-2023 Graham Breach
