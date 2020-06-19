<?
// read information from the TM database about the
// routes for the HB showroutes Route Stats table:
// waypoints and traveled info for connections
//
// Author: Jim Teresco, Travel Mapping Project, June 2020
//
$params = json_decode($_POST['params'], true);

// $params has 2 fields:
// roots - array of TM chopped route roots (e.g., ny.i090)
// traveler - user whose stats are to be included

// note that roots are in connected-route order for a connected route with
// multiple chopped routes, will be a single route for a chopped route
// or a connected route with just one chopped route

// need to buffer and clean output since tmphpfuncs generates
// some output that breaks the JSON output
ob_start();
require "./tmphpfuncs.php";
ob_end_clean();

// initialize the array of responses
$response = array();

$roots = $params['roots'];

// total number of users

// gather info about each chopped route
$response['pointNames'] = array();
$response['latitudes'] = array();
$response['longitudes'] = array();
$response['driverCounts'] = array();
$response['segmentIds'] = array();
$response['clinched'] = array();
foreach ($roots as $root) {
    $rootPointNames = array();
    $rootLatitudes = array();
    $rootLongitudes = array();
    $rootDriverCounts = array();
    $rootSegmentIds = array();
    $rootClinched = array();
    $sql_command = <<<SQL
        SELECT pointName, latitude, longitude, driverCount, segmentId
        FROM waypoints
        LEFT JOIN (
            SELECT
              waypoints.pointId,
              sum(!ISNULL(clinched.traveler)) as driverCount,
              segments.segmentId
            FROM segments
            LEFT JOIN clinched ON segments.segmentId = clinched.segmentId
            LEFT JOIN waypoints ON segments.waypoint1 = waypoints.pointId
            WHERE segments.root = '$root'
            GROUP BY segments.segmentId
        ) as pointStats on pointStats.pointId = waypoints.pointId
        WHERE root = '$root';
SQL;
    $result = tmdb_query($sql_command);
    while ($row = $result->fetch_assoc()) {
        array_push($rootPointNames, $row['pointName']);
        array_push($rootLatitudes, $row['latitude']);
        array_push($rootLongitudes, $row['longitude']);
        array_push($rootDriverCounts, $row['driverCount']);
        array_push($rootSegmentIds, $row['segmentId']);
	// an additional query to see if the traveler has clinched this segment
	array_push($rootClinched,
	    tm_count_rows("clinched", "WHERE traveler='".$params['traveler']."' AND segmentId='".$row['segmentId']."'"));
    }
    $result->free();

    array_push($response['pointNames'], $rootPointNames);
    array_push($response['latitudes'], $rootLatitudes);
    array_push($response['longitudes'], $rootLongitudes);
    array_push($response['driverCounts'], $rootDriverCounts);
    array_push($response['segmentIds'], $rootSegmentIds);
    array_push($response['clinched'], $rootClinched);
}
$tmdb->close();
echo json_encode($response);
?>
