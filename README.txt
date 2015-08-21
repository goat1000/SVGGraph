SVGGraph Library version 2.19.1
===============================

This library provides PHP classes and functions for easily creating SVG
graphs from data. As of version 2.0, SVGGraph works with PHP 5 only -
PHP 4 support has been dropped.

Here is a basic example:
 $graph = new SVGGraph(640, 480);
 $graph->colours = array('red','green','blue');
 $graph->Values(100, 200, 150);
 $graph->Links('/Tom/', '/Dick/', '/Harry/');
 $graph->Render('BarGraph');

Full documentation is available at http://www.goat1000.com/

Graph types
===========
At the moment these types of graph are supported by SVGGraph:
 
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

 StackedBarGraph - multiple data sets drawn as bars, stacked one on top of
                   another;

 GroupedBarGraph - multiple data sets drawn as bars, side-by-side;

 StackedLineGraph - multiple data sets, their values added together;

 StackedGroupedBarGraph - multiple data sets, their values added together and
                          split into groups;

 BarAndLineGraph - a grouped bar graph and multi-line graph on the same graph;

 Histogram - a bar graph that shows the range of values;

 MultiScatterGraph - scatter graph supporting multiple data sets;

 HorizontalBarGraph - a bar graph with the axes swapped;

 HorizontalStackedBarGraph - a stacked bar graph drawn horizontally;

 HorizontalGroupedBarGraph - a grouped bar graph drawn horizontally;

 RadarGraph - a radar or star graph with values drawn as lines;

 MultiRadarGraph - a radar graph supporting multiple data sets;

 CylinderGraph - a 3D bar graph with the bars cylinder shaped;

 StackedBar3DGraph - a 3D bar graph version of the stacked bar graph;

 GroupedBar3DGraph - a 3D bar graph version of the grouped bar graph;

 StackedCylinderGraph - a cylinder-bar version of the stacked bar graph;

 GroupedCylinderGraph - a cylinder-bar version of the grouped bar graph;

 DonutGraph - a pie graph with a hole in the middle;

 PolarAreaGraph - a pie graph where the area of the slice varies instead of
                  its angle;

 ExplodedPieGraph - a pie graph with slices exploded out from the centre.
 
Using SVGGraph
==============
The library consists of several class files which must be present. To use
SVGGraph, include or require the SVGGraph.php class file. The other classes
should be in the same directory as this main file to be loaded automatically.

Embedding SVG in a page
=======================
There are several ways to insert SVG graphics into a page. FireFox, Safari,
Chrome, Opera all support SVG, though Internet Explorer currently requires
the use of a plugin (supplied by Adobe).

For options 1-3, I'll assume you have a PHP script called "graph.php" which
contains the code to generate the SVG.

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

The object tag is standard, but this doesn't work in IE.

Option 4: using the svg namespace within an xhtml document

This option is more complicated, as it requires changing the doctype and
content type of the page being served. The SVG is generated as part of the
same page.
 <?php
  header('content-type: application/xhtml+xml; charset=UTF-8');
  // $graph = new SVGGraph(...);
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
doesn't work in IE.

Class Constructor
=================
The SVGGraph class constructor takes three arguments, the width and height 
of the SVG image in pixels and an optional array of settings to be passed to 
the rendering class.
 $graph = new SVGGraph($width, $height, $settings);

For more information on the $settings array, see the section below.

Data Values
===========
For simple graphs you may set the data to use by passing it into the Values
function:
 $graph->Values(1, 2, 3);

For more control over the data, and to assign labels, pass the values in as an
array:
 $data = array('first' => 1, 'second' => 2, 'third' => 3);
 $graph->Values($data);

For graphs supporting multiple datasets, pass each dataset as an array within
an outer array:
 $data = array(
  array('first' => 1, 'second' => 2, 'third' => 3),
  array('first' => 3, 'second' => 4, 'third' => 2)
 );
 $graph->Values($data);

Scatter graphs draw markers at x,y coordinates, given as the key and value in
the data array:
 $data = array(5 => 20, 6 => 30, 10 => 90, 20 => 50);
 $graph->Values($data);

This will draw the markers at (5,20), (6,30), (10,90) and (20,50). The new 
scatter_2d option in version 2.4 allows points to occupy the same x-
coordinate by passing the values as pairs of coordinates:
 $graph = new SVGGraph(200,100, array('scatter_2d' => true));
 $data = array(array(5,20), array(6,30), array(5,90), array(10,50));
 $graph->Values($data);

Note: data in this format are not supported by any of the non-scatter graph
types.

Hyperlinks
==========
The graph bars and markers may be assigned hyperlinks - each value that requires
a link should have a URL assigned to it using the Links function:
 $graph->Links('/page1.html', NULL, '/page3.html');

The NULL is used here to specify that the second bar will not be linked to
anywhere.

As with the Values function, the list of links may be passed in as an array:
 $links = array('/page1.html', NULL, '/page3.html');

Using an associative array means that NULL values may be skipped.
 $links = array('first' => '/page1.html', 'third' => '/page3.html');
 $graphs->Links($links);

Rendering
=========
To generate and display the graph, call the Render function passing in the
type of graph to be rendered:
 $graph->Render('BarGraph');

This will send the correct content type header to the browser and output the
SVG graph.

The Render function takes two optional parameters in addition to the graph
type:
 $graph->Render($type, $header, $content_type);

Passing in FALSE for $header will prevent output of the XML declaration and
doctype. Passing in FALSE for $content_type will prevent the 'image/svg+xml'
content type being set in the response header.

To generate the graph without outputting it to the browser you may use the
Fetch function instead:
 $output = $graph->Fetch('BarGraph');

This function also takes an optional $header parameter:
 $output = $graph->Fetch($type, $header);

Passing in FALSE as $header will prevent the returned output from containing
the XML declaration and doctype. The Fetch function never outputs the content
type to the response header.

Colours
=======
The colours used may be overridden from the default random set by setting the
graph's "colours" array.
 $graph->colours = array('red', 'green', '#00ffff', 'rgb(100,200,100)',
    array('red','green'));

You may use any of the standard named colours, or hex notation, or RGB notation.

Gradients
=========
The final entry in the example colours array above is an array of two colours,
which specifies a vertical gradient, the first colour (red) at the top and the
second (green) at the bottom. From version 2.1, more colours may be used and
a final 'h' or 'v' will specify horizontal or vertical gradients.

Gradients are supported by the bar graphs and for the filled area under line
graphs. Where gradients are not supported, the first colour in the array will
be used instead.

Examples:
 array('red','white','red','h');
 - a horizontal gradient, red at both sides and white in the centre

 array('red','white','blue');
 - a vertical gradient, red at the top, white in the centre, blue at the bottom

 array('red','orange','yellow','green','blue','indigo','violet','h');
 - a horizontal rainbow gradient

Settings
========
Many of the ways that things are displayed may be changed by passing in an array
of settings to the SVGGraph constructor:
 $settings = array('back_colour' => 'white');
 $graph = new Graph($w, $h, $settings);

There are now too many options for me to list them all here without wasting a 
lot of time, and probably making mistakes. For the full list of options, examples
and descriptions, please visit the website: http://www.goat1000.com/svggraph.php


Contact details
===============
For more information about this software please contact the author,
graham(at)goat1000.com or visit the website: http://www.goat1000.com/


Copyright (C) 2009-2015 Graham Breach
