<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<!-- 
	Shows a users' stats for a particular region. 
	URL Params:
		u - the user.
    rg - the region viewing stats for.
		db - the database being used. Use 'TravelMappingDev' for in-development systems. 
-->
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<style type="text/css">
body, html {
  margin:0;
  border:0;
  padding:0;
  height:100%;
  max-height:100%;
  overflow: hidden;
  font-size:9pt;
  background-color:#EEEEFF;
}

#mapholder {
position: relative;
margin: auto;
width:1000px;
}

#map {
  height: 500px;
  overflow:hidden;
  clear: left;
}

#colorBox {
  position: relative;
  float: right;
  margin: auto;
  padding: 10px;
}

#systemsTable {
  clear: both;
}

@media screen and (max-width: 720px) {

  #mapholder {
    width:100%;
  }
}

#map * {
cursor:crosshair;
}

#body {
position: fixed;
left: 0px;
top: 80px;
bottom: 0px;
width: 100%;
overflow:auto;
padding: 20px;
}

table.nmptable {
font-size:8pt;
border: 1px solid black;
border-spacing: 0px;
margin-left: auto;
margin-right: auto;
background-color:white;
}

table.nmptable  td, th {
border: solid black;
border-width: 1px;
}

table.nmptable2 td, th {
border-width: 0px;
}

table.nmptable tr td {
text-align:right;
}

table.pthtable {
font-size:10pt;
border: 1px solid black;
border-spacing: 0px;
margin-left: auto;
margin-right: auto;
background-color:white;
}

table.pthtable  td, th {
border: solid black;
border-width: 1px;
}

table.pthtable tr td {
text-align:left;
}

table.gratable {
font-size:10pt;
border: 1px solid black;
border-spacing: 0px;
margin-left: auto;
margin-right: auto;
background-color:white;
}

table.gratable  td, th {
border: solid black;
border-width: 1px;
}

table.gratable tr td {
text-align:left;
}

table.gratable tr:hover td {
  background-color: #CCCCCC;
}

table.tablesorter th.sortable:hover {
  background-color: #CCCCFF;
}
table tr.status-active td {
  background-color: #CCFFCC;
}
table tr.status-preview td {
  background-color: #FFFFCC;
}
table tr.status-devel td {
  background-color: #FFCCCC;
}
</style>
<?php

    if (array_key_exists("u",$_GET)) {
      $user = $_GET['u'];
    }

    if (array_key_exists("rg",$_GET)) {
      $region = $_GET['rg'];
    }

    if (is_null($user) || is_null($region)) {
      header('HTTP/ 400 Missing user (u=) or region (rg=) params');
      echo "</head><body><h1>ERROR: 400 Missing user (u=) or region (rg=) params</h1></body></html>";
      exit();
    }

    $dbname = "TravelMapping";
    if (array_key_exists("db",$_GET)) {
      $dbname = $_GET['db'];
    }

    // establish connection to db: mysql_ interface is deprecated, should learn new options
    $db = mysql_connect("localhost","travmap","clinch") or die("Failed to connect to database");
    mysql_select_db($dbname, $db);


    # functions from http://stackoverflow.com/questions/834303/startswith-and-endswith-functions-in-php
    function startsWith($haystack, $needle) {
      // search backwards starting from haystack length characters from the end
      return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
    }
    function endsWith($haystack, $needle) {
      // search forward starting from end minus needle length characters
      return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== FALSE);
    }

    function colorScale($percent) {

    }
  ?>
<title><?php echo "Traveler Stats for ".$user." in ".$region; ?></title>
<script
 src="http://maps.googleapis.com/maps/api/js?sensor=false"
  type="text/javascript"></script>
<script src="chmviewerfunc3.js" type="text/javascript"></script>
<!-- jQuery -->
<script src="http://code.jquery.com/jquery-1.11.0.min.js"></script>
<!-- TableSorter -->
<script src="/lib/jquery.tablesorter.min.js"></script>
  <script>
  function waypointsFromSQL() {
  <?php
    $regions = array();
    if (array_key_exists("rg",$_GET)) {
      $regions = explode(',',$_GET['rg']);
    }

    // restrict to waypoints matching routes whose region is given in the "rg=" query string parameter
    // build strings needed for later queries
    $select_regions = "";
    $where_regions = "";
    $num_regions = 0;
    foreach ($regions as $region) {
      if ($num_regions == 0) {
        $select_regions = " and (routes.region='".$region."'";
        $where_regions = " where (region='".$region."'";
      }
      else {
        $select_regions = $select_regions." or routes.region='".$region."'";
        $where_regions = $where_regions." or region='".$region."'";
      }
      $num_regions = $num_regions + 1;
    }
    if ($num_regions > 0) {
      $select_regions = $select_regions.")";
      $where_regions = $where_regions.")";
    }

    // select based on system?
    $systems = array();
    if (array_key_exists("sys",$_GET)) {
      $systems = explode(',',$_GET['sys']);
    }

    // restrict to waypoints matching routes whose system is given in the "sys=" query string parameter
    $select_systems = "";
    $where_systems = "";
    $num_systems = 0;
    foreach ($systems as $system) {
      if ($num_systems == 0) {
        $select_systems = " and (routes.systemName='".$system."'";
        $where_systems = " where (routes.systemName='".$system."'";
      }
      else {
        $select_systems = $select_systems." or routes.systemName='".$system."'";
        $where_systems = $where_systems." or routes.systemName='".$system."'";
      }
      $num_systems = $num_systems + 1;
    }
    if ($num_systems > 0) {
      $select_systems = $select_systems.")";
      $where_systems = $where_systems.")";
    }

    // make sure we have selected some region or system
    if (($num_systems == 0) && ($num_regions == 0)) {
       // for now, put in a default to usai, do something better later
       $select_systems = " and (routes.systemName='usai')";
       $where_systems = " where (routes.systemName='usai')";
    }

    $sql_command = "select waypoints.pointName, waypoints.latitude, waypoints.longitude, waypoints.root, systems.tier, systems.color, systems.systemname from waypoints join routes on routes.root = waypoints.root".$select_regions.$select_systems." join systems on routes.systemname = systems.systemname and systems.active='1' order by root, waypoints.pointId;";
    echo "// SQL: ".$sql_command."\n";
    $res = mysql_query($sql_command);

    $routenum = 0;
    $pointnum = 0;
    $lastRoute = "";
    while ($row = mysql_fetch_array($res)) {
      if (!($row[3] == $lastRoute)) {
         echo "newRouteIndices[".$routenum."] = ".$pointnum.";\n";
         echo "routeTier[".$routenum."] = ".$row[4].";\n";
         echo "routeColor[".$routenum."] = '".$row[5]."';\n";
         echo "routeSystem[".$routenum."] = '".$row[6]."';\n";
         $lastRoute = $row[3];
         $routenum = $routenum + 1;
      }
      echo "waypoints[".$pointnum."] = new Waypoint(\"".$row[0]."\",".$row[1].",".$row[2]."); // Route = ".$row[3]." (".$row[5].")\n";
      $pointnum = $pointnum + 1;
    }

    // check for query string parameter for traveler clinched mapping of route
    if (array_key_exists("u",$_GET)) {
       echo "traveler = '".$_GET['u']."';\n";
       // retrieve list of segments for this region or regions
       $sql_command = "select segments.segmentId, segments.root from segments join routes on routes.root = segments.root join systems on routes.systemname = systems.systemname and systems.active='1'".$where_regions.$where_systems." order by root, segments.segmentId;";
       echo "// SQL: ".$sql_command."\n";
       $res = mysql_query($sql_command);
       $segmentIndex = 0;
       while ($row = mysql_fetch_array($res)) {
         echo "segments[".$segmentIndex."] = ".$row[0]."; // route=".$row[1]."\n";
         $segmentIndex = $segmentIndex + 1;
       }
       $sql_command = "select segments.segmentId, segments.root from segments right join clinched on segments.segmentId = clinched.segmentId join routes on routes.root = segments.root join systems on routes.systemname = systems.systemname and systems.active='1'".$where_regions.$where_systems." and clinched.traveler='".$_GET['u']."' order by root, segments.segmentId;";
       echo "// SQL: " .$sql_command."\n";
       $res = mysql_query($sql_command);
       $segmentIndex = 0;
       while ($row = mysql_fetch_array($res)) {
         echo "clinched[".$segmentIndex."] = ".$row[0]."; // route=".$row[1]."\n";
         $segmentIndex = $segmentIndex + 1;
       }
     echo "mapClinched = true;\n";
    }

    // check for custom colors query string parameters
    $customColors = array();
    if (array_key_exists("colors",$_GET)) {
       $customColors = explode(';',$_GET['colors']);
       $colorNum = 0;
       foreach ($customColors as $customColor) {
          $colorEntry = array();
          $colorEntry = explode(':',$customColor);
          echo "customColorCodes[".$colorNum."] = { name: \"".$colorEntry[0]."\", unclinched: \"".$colorEntry[1]."\", clinched: \"".$colorEntry[2]."\" };\n";
          $colorNum = $colorNum + 1;
       }
    }

  ?>
    genEdges = true;
  }
</script>
</head>
<body onload="loadmap();">
  <script type="text/javascript">
  $(document).ready(function()
    {
      $("#systemsTable").tablesorter({
        sortList: [[0,0]],
        headers: {0:{sorter:false},}
      });
    }
    );
  </script>
  <div id="header">
  <a href="/user?u=<?php echo $user?>">Back</a>
  <a href="/">Home</a>
  <a href="/hbtest">Highway Browser</a>
  <form id="userselect">
    <label>User: </label>
    <input type="text" name="u" form="userselect" value="<?php echo $user ?>">
    <label>Region: </label>
    <input type="text" name="rg" form="userselect" value="<?php echo $region ?>">
    <input type="submit">
  </form>
  <h1>Traveler Stats for <?php echo $user." in Region ".$region?>:</h1>
  </div>
  <div id="body">
    <div id="mapholder">
      <input id="showMarkers" type="checkbox" name="Show Markers" onclick="showMarkersClicked()">&nbsp;Show Markers
      <div id="controlboxinfo"></div>
      <div id="colorBox">
        
      </div>
      <div id="map"></div>
    </div>
    <table class="gratable tablesorter" id="systemsTable">
      <thead>
        <tr>
          <th colspan="7">Clinched Mileage by System</th>
        </tr>
        <tr>
          <th class="sortable">System Code</th>
          <th class="sortable">System Name</th>
          <th class="sortable">Tier</th>
          <th class="sortable">Status</th>
          <th class="sortable">Clinched Mileage</th>
          <th class="sortable">Total Mileage</th>
          <th class="sortable">Percent</th>
        </tr>
      </thead>
      <tbody>
        <?php
          $sql_command = <<<SQL
          SELECT
            sys.systemName,
            sys.tier,
            sys.level AS status,
            sys.fullName,
            r.root,
            COALESCE(ROUND(SUM(cr.mileage), 2), 0) AS clinchedMileage,
            COALESCE(ROUND(SUM(r.mileage), 2), 0) AS totalMileage,
            COALESCE(ROUND(SUM(cr.mileage) / SUM(r.mileage) * 100, 3), 0) AS percentage
          FROM systems as sys
          INNER JOIN routes AS r
            ON r.systemName = sys.systemName
          LEFT JOIN clinchedRoutes AS cr
            ON cr.route = r.root AND cr.traveler = 'xxxxxxxxxxxxxxxxx'
          WHERE r.region = 'yyyyyyyyyyyyyyyyy'
          GROUP BY r.systemName
          ORDER BY sys.tier, sys.systemName;
SQL;

        $sql_command = str_replace("xxxxxxxxxxxxxxxxx", $user, $sql_command);
        $sql_command = str_replace("yyyyyyyyyyyyyyyyy", $region, $sql_command);
        echo "<!--".$sql_command."-->";

        $res = mysql_query($sql_command);
        while ($row = mysql_fetch_array($res)) {
          echo "<tr onClick=\"window.document.location='/user/system.php?u=".$user."&sys=".$row['systemName']."&rg=".$region."'\" class=\"status-".$row['status']."\">";
          echo "<td>".$row['systemName']."</td>";
          echo "<td>".$row['fullName']."</td>";
          echo "<td>Tier ".$row['tier']."</td>";
          echo "<td>".$row['status']."</td>";
          echo "<td>".$row['clinchedMileage']."</td>";
          echo "<td>".$row['totalMileage']."</td>";
          echo "<td>".$row['percentage']."%</td></tr>";
        }
        ?>
      </tbody>
    </table>
    </div>
  </div>
</body>
</html>