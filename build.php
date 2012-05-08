#!/usr/bin/php
<?php
include("config.php");
$data = shell_exec("php ".dirname($_SERVER['SCRIPT_NAME'])."/kronos.php");// Get the output of the kronos page

if(preg_match("@Inappropriate authentication@",$data)){
  die("Auth Fail\n");
}
if(preg_match("@The server encountered an internal error and was unable to complete your request.@",$data)){
  die("Server Fail\n");
}

$datatab = explode("<h2>Punches</h2>",$data); // Split the html in half.

include('tabe.php'); // Add the Table Extraction library.

$totals = find_table($datatab[0]); // Find table before Punches
$totals_obj = new stdClass();
$sub_totals = array_pop($totals);
unset($sub_totals['PayCode']);
$totals_obj->sub_totals = $sub_totals;
$totals_obj->jobs = $totals;
$totals_obj->week1 = 0;
$totals_obj->week2 = 0;

$punches = find_table($datatab[1]); // Find table after Punches
$dates = array();
$day = 0;
$week1 = $week2 = "";
while(count($punches)){//For each "punch"
  $cur = array_shift($punches);
  if(isset($cur['Out&nbsp;Punch'])){ // Punches on the day.
    $punch = new stdClass();
    if(isset($cur['Total&nbsp;(hours:minutes)'])){ // First punch of the day.
      $date_visual = $cur['Date'];
      $punch->in = $cur['In&nbsp;Punch'];
      $punch->out = $cur['Out&nbsp;Punch'];
      $dates[$cur['Date']][] = $punch;
      $last_date = $cur['Date'];
      $day++;
    } else { // Kronos really screws with the table output when it's on the same day.
      $date_visual = "";
      $punch->in = $cur['Date'];
      $punch->out = $cur['In&nbsp;Punch'];
      $dates[$last_date][] = $punch;
    }
    $visual = "\t ".format_time(punch_min($punch))." ... ".$punch->in." to ".($punch->out=="&nbsp;"?"now":$punch->out)." \n";
    if($day <= 7){ // Seven days in a week? Who would have thought?
      $totals_obj->week1 += punch_min($punch);
      $week1 .= $date_visual . $visual;
    } else {
      $totals_obj->week2 += punch_min($punch);
      $week2 .= $date_visual . $visual;
    }
    punch_min($punch);
  } else { // Even though there's no data, we need to increment the date.
    $day++;
  }
}

if(isset($argv[1])){
  $_GET[$argv[1]] = true;
}
if(isset($_GET['json'])){
  $api_data = new stdClass();
  $api_data->totals = $totals_obj;
  $api_data->totals = $dates;
  echo json_encode($api_data);
} elseif(isset($_GET['debug'])){
  print_r($totals); // Debug
  print_r($totals_obj); // Debug
  print_r($punches); // Debug
  print_r($dates); // Debug
} else {
  echo "Pay Period Week 1\n".$week1;
  echo "Total: ".format_time($totals_obj->week1)."\n";
  echo extra_info($totals_obj->week1);
  if($week2 != ""){
    echo "Pay Period Week 2\n".$week2;
    echo "Total: ".format_time($totals_obj->week2)."\n";
    echo extra_info($totals_obj->week2);
  }
}

// Determines extra information if $weekhours is set
function extra_info($t){
  global $weekhours;
  if(isset($weekhours)){
    $remaining_time = $weekhours*60-$t;
    return "Remaining: ".format_time($remaining_time)."\n";
  }
}

// Simple time formatter
function format_time($mins){
  return str_pad(floor($mins/60),2," ",STR_PAD_LEFT)."h ".str_pad($mins%60,2," ",STR_PAD_LEFT)."m";
}

// Wrapper function for the Table Extraction library.
function find_table($data){
  $tbl = new tableExtractor;
  $tbl->source = $data; // Set the HTML Document
  $tbl->anchor = ''; // Set an anchor that is unique and occurs before the Table
  $tpl->anchorWithin = true; // To use a unique anchor within the table to be retrieved
  $d = $tbl->extractTable(); // The array
  return $d;
}

// Convert punch to total minutes.
function punch_min($punch){
  $in = render_min($punch->in);
  $out = render_min($punch->out);
  if($out == null){ // No punch out time yet.
    return 0;
  }
  return $out - $in;
}

// Convert 12-hour clock system to minute of the day.
function render_min($punch_time){
  if($punch_time == "&nbsp;"){ // Catch missing days.
    return null;
  }
  preg_match("@(.|..):(..)(AM|PM)@",$punch_time,$m);// Regex! Woot!
  if(count($m)){
    $ampm = 0;
    if($m[3] == "PM" and $m[1]!="12"){ // If the afternoon (excluding 12)
      $ampm = 60*12; // 12 hours
    }
    return $m[1]*60+$m[2]+$ampm;
  } else {
    $valid_alt_times = array("missingpunch-seeyoursupervisor");
    if(!in_array($punch_time,$valid_alt_times)){
      die("ERROR: render_min($punch_time);\n");
    }
  }
}
