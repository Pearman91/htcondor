<?php   

define("TASK_URL", "task-details.php?platform=%s&task=%s&runid=%s");

include "Dashboard.php";
$dash = new Dashboard();
$dash->print_header("Condor Build and Test Dashboard");
$dash->connect_to_db();

# get args
$runid = (int)$_REQUEST["runid"];
$sha1 = $_REQUEST["sha1"];

// If we get a SHA1 and not a run id, look up the run id
if(!$runid and $sha1) {
  $sql = "SELECT runid FROM Run WHERE project_version='$sha1'";
  $result = $dash->db_query($sql);
  $runid = $result[0]["runid"];
}

?>

<script type='text/javascript' src='jquery-1.6.2.min.js'></script>

<script type="text/javascript">
  var toggle = 1;

  $(document).ready(function(){
      $("#toggle").click(function(){
	  if(toggle == 1) {
	    $(".time").show();
	    $(".status").hide();
	    $("#toggle").text("Show task status");
	    toggle = 0;
	  }
	  else {
	    $(".time").hide();
	    $(".status").show();
	    $("#toggle").text("Show task times");
	    toggle = 1;	    
	  }
	});
  });
</script>

<style type="text/css">
<!--
div.status {
  
}
div.time {
  display:none;
}
p.toggle {
  cursor: pointer;
}
-->
</style>

</head>
<body>


<?php

echo "<h2>Results for build ID $runid</h2>\n";


//
// Get the platforms, and prepare some data structures
//
$platforms = $dash->get_platform_list_for_runid($runid);

$build_headers = Array();
$test_headers = Array();
foreach ($platforms as $platform) {
  $build_headers[$platform] = Array("passed" => 0, "pending" => 0, "failed" => 0);
  $test_headers[$platform] = Array("passed" => 0, "pending" => 0, "failed" => 0);
}

//
// Get build task info   
//
$sql = "SELECT
          platform,
          name,
          result,
          host,
          TIME_TO_SEC(TIMEDIFF(Finish, Start)) as length
       FROM
          Task
       WHERE 
          runid = $runid 
       ORDER BY
          start ASC";

$results = $dash->db_query($sql);

foreach ($results as $row) {
  if(!array_key_exists($row["name"], $build_tasks)) {
    $build_tasks[$row["name"]] = Array();
  }
  $build_tasks[$row["name"]][$row["platform"]] = Array($row["result"], $row["length"], $row["host"]);

  $result = map_result($row["result"]);
  $build_headers[$row["platform"]][$result] += 1;
}


//
// Get test task info
//
$test_runids = $dash->get_test_runids_for_build($runid);

$sql = "SELECT 
          platform,
          Task.name,
          result,
          host,
          TIME_TO_SEC(TIMEDIFF(Finish, Start)) as length
        FROM
          Task,
          Method_nmi
        WHERE
          Task.runid = Method_nmi.runid AND
          Method_nmi.input_runid = $runid
        ORDER BY
          Task.start ASC";

$results = $dash->db_query($sql);

foreach ($results as $row) {
  if(!array_key_exists($row["name"], $test_tasks)) {
    $test_tasks[$row["name"]] = Array();
  }

  $test_tasks[$row["name"]][$row["platform"]] = Array($row["result"], $row["length"], $row["host"]);

  $result = map_result($row["result"]);
  $test_headers[$row["platform"]][$result] += 1;
}

print "<p><a id='toggle'>Show task times</a>\n";

print "<table border='0' cellspacing='0'>\n";
print "<tr>\n";
print "   <th>Build Tasks</th>\n";


foreach ($platforms AS $platform) {
  $display = preg_replace("/nmi:/", "", $platform);
   
  if(preg_match("/^x86_64_/", $display)) {
    $display = preg_replace("/x86_64_/", "x86_64<br>", $display);
  }
  elseif(preg_match("/ia64_/", $display)) {
    $display = preg_replace("/ia64_/", "x86<br>", $display);
  }
  else {
    $display = preg_replace("/x86_/", "x86<br>", $display);
  }

  $display = "<font style='font-size:75%'>$display</font>";

  print "<td align='center'>$display</td>\n";
}


//
// Print build hosts
//
print "<tr>\n";
print "  <td>Hosts</td>\n";
foreach ($platforms as $platform) {
  // Lookup the file location now
  $loc_query = "SELECT filepath,gid FROM Run WHERE runid='$runid'";
  $loc_query_results = $dash->db_query($loc_query);
  foreach ($loc_query_results as $loc_row) {
    $filepath = $loc_row["filepath"];
    $mygid = $loc_row["gid"];
  }

  $class = "";
  if($build_headers[$platform]["failed"] > 0) {
    $class = "failed";
  }
  elseif($build_headers[$platform]["pending"] > 0) {
    $class = "pending";
  }
  elseif($build_headers[$platform]["passed"] > 0) {
    $class = "passed";
  }

  if(array_key_exists("remote_task", $build_tasks)) {
    if(array_key_exists($platform, $build_tasks["remote_task"])) {
      $host = preg_replace("/\.batlab\.org/", "", $build_tasks["remote_task"][$platform][2]);

      $summary = "<table>\n";
      $summary .= "<tr><td class='left passed'>Passed:</td><td class='passed'>" . $build_headers[$platform]["passed"] . "</td></tr>";
      $summary .= "<tr><td class='left pending'>Pending:</td><td class='pending'>" . $build_headers[$platform]["pending"] . "</td></tr>";
      $summary .= "<tr><td class='left failed'>Failed:</td><td class='failed'>" . $build_headers[$platform]["failed"] . "</td></tr>";
      $summary .= "</table>\n";

      print "<td class='$class'><span class='link'><a href='$filepath/$mygid/userdir/$platform/'>$host<span>$summary</span></a></span></td>";
      continue;
    }
  }
  print "<td class='$class'>&nbsp;</td>";
}

// 
// Show build results
//
foreach ($build_tasks as $task_name => $results) {
  $totals = Array("passed" => 0, "pending" => 0, "failed" => 0);
  $output = "";  
  foreach ($platforms as $platform) {
    if(array_key_exists($platform, $results)) {
      $class = map_result($results[$platform][0]);
      $totals[$class] += 1;
      $link = sprintf(TASK_URL, $platform, urlencode($task_name), $runid);

      $contents = "<div class='status'>" . $results[$platform][0] . "</div>";
      $contents .= "<div class='time'>" . sec_to_min($results[$platform][1]) . "</div>";
      $output .= "  <td class=\"$class\"><a href='$link'>$contents</a></td>\n";
    }
    else {
      $output .= "<td>&nbsp;</td>\n";
    }
  }

  $class = "";
  if($totals["failed"] > 0) { $class = "failed"; }
  elseif($totals["pending"] > 0) { $class = "pending"; }

  print "<tr>\n";
  print "  <td class=\"left $class\">" . limitSize($task_name,30) . "</td>\n";
  print $output;
  print "</tr>\n";
}


//
// Show test results
//
$num_platforms = count($platforms);
print "<tr><td style='border-bottom-width:0px' colspan=" . ($num_platforms+1) . ">&nbsp;</td></tr>\n";
print "<tr><th>Test Tasks</th><th colspan=$num_platform>&nbsp</th></tr>\n";


//
// Print test hosts
//
print "<tr>\n";
print "  <td>Hosts</td>\n";
foreach ($platforms as $platform) {
  // Lookup the file location now
  $test_runid = $test_runids[$platform];
  $loc_query = "SELECT filepath,gid FROM Run WHERE runid='$test_runid'";
  $loc_query_results = $dash->db_query($loc_query);
  foreach ($loc_query_results as $loc_row) {
    $filepath = $loc_row["filepath"];
    $mygid = $loc_row["gid"];
  }

  $class = "";
  if($test_headers[$platform]["failed"] > 0) {
    $class = "failed";
  }
  elseif($test_headers[$platform]["pending"] > 0) {
    $class = "pending";
  }
  elseif($test_headers[$platform]["passed"] > 0) {
    $class = "passed";
  }

  if(array_key_exists("remote_task", $test_tasks)) {
    if(array_key_exists($platform, $test_tasks["remote_task"])) {
      $host = preg_replace("/\.batlab\.org/", "", $test_tasks["remote_task"][$platform][2]);

      $summary = "<table>\n";
      $summary .= "<tr><td class='left passed'>Passed:</td><td class='passed'>" . $test_headers[$platform]["passed"] . "</td></tr>";
      $summary .= "<tr><td class='left pending'>Pending:</td><td class='pending'>" . $test_headers[$platform]["pending"] . "</td></tr>";
      $summary .= "<tr><td class='left failed'>Failed:</td><td class='failed'>" . $test_headers[$platform]["failed"] . "</td></tr>";
      $summary .= "</table>\n";

      print "<td class='$class'><span class='link'><a href='$filepath/$mygid/userdir/$platform/'>$host<span>$summary</span></a></span></td>";
      continue;
    }
  }
  print "<td class='$class'>&nbsp;</td>";
}

foreach ($test_tasks as $task_name => $results) {  
  $totals = Array("passed" => 0, "pending" => 0, "failed" => 0);
  $output = "";
  foreach ($platforms as $platform) {
    if(array_key_exists($platform, $results)) {
      $class = map_result($results[$platform][0]);
      $totals[$class] += 1;
      $link = sprintf(TASK_URL, $platform, urlencode($task_name), $test_runids[$platform]);

      $contents = "<div class='status'>" . $results[$platform][0] . "</div>";
      $contents .= "<div class='time'>" . sec_to_min($results[$platform][1]) . "</div>";
      $output .= "  <td class=\"$class\"><a href='$link'>$contents</a></td>\n";
    }
    else {
      $output .= "<td>&nbsp;</td>\n";
    }
  }

  $class = "";
  if($totals["failed"] > 0) { $class = "failed"; }
  elseif($totals["pending"] > 0) { $class = "pending"; }

  print "<tr>\n";
  print "  <td class=\"left $class\">" . limitSize($task_name,30) . "</td>\n";
  print $output;
  print "</tr>\n";
}

print "</table>";

function limitSize($str, $cnt) {
  if (strlen($str) > $cnt) {
    $str = substr($str, 0, $cnt - 3)."...";
  }
  return ($str);
}

function sec_to_min($sec) {
  $min = floor($sec / 60);
  $sec = $sec % 60;
  if($sec < 10) {
    $sec = "0$sec";
  }
  return "$min:$sec";
}

function map_result($result) {
  if(is_null($result)) {
    return "pending";
  }
  elseif($result == 0) {
    return "passed";
  }
  else {
    return "failed";
  }
}


?>
</body>
</html>
