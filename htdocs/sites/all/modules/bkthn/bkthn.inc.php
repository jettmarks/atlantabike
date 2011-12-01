<?php


/**
* Generate HTML for pledge progress of the current user
* @returns HTML content
*/
function bkthn_main()
{
  //declare global variables
  global $user;

  //initialize variable
  $output = '';

  $flag_has_bikes = _check_bikes($user->uid);
  if (!$flag_has_bikes)
  {
    // Create a placeholder record for this user
    $sql = "insert bkthn_bikes (bkthn_vehicle_types_key, user_key, name, brand, model, year)";
    $sql .= "values ";
    $sql .= "(1, %d, 'Commuter', 'Unknown', 'Unknown', 1981)";
    db_query($sql, $user->uid);
    drupal_set_message(t('A default bicycle has been created for you.  ' .
    'You may want to add bikes if you use more than one or edit the one that has been created.'));
    $flag_has_bikes = TRUE;
  }

  //check if there are any pledges set up for current user
  $sql = "select count(*) as row_count ";
  $sql .= "from bkthn_pledges ";
  $sql .= "where user_key = %d ";

  //query the database
  $result = db_query($sql, $user->uid);
  //get the results
  $row = db_fetch_object($result);
  //initialize the variable
  $flag_has_pledges = FALSE;
  //check the results
  if ($row->row_count > 0)
  {
    $flag_has_pledges = TRUE;
  }

  //if there are no pledges
  if (!$flag_has_pledges)
  {
    //show the link to add pledges
    $output .= "<p>Before you can start using Bike Commute-a-Thon, " .
    "you will need to enter a pledge.  </p>";
    $output .= "<p>Click the link below.... </p>";
    $output .= "<p>" . l("Add Pledge", "bkthn/pledges/add");
  } else
  {
    $output .= _draw_progress($user->uid);
    $output .= "<p>Your current pledges:</p>";
    //start of the table
    $output .= "<table>";
    //table header
    $output .= "<tr>";
    $output .= "<th>Period</th>";
    $output .= "<th>Miles Pledged</th>";
    $output .= "<th>Miles Logged</th>";
    $output .= "<th>Pledge per Mile</th>";
    $output .= "<th>&nbsp;</th>";
    $output .= "</tr>";

    //set up the query to get all pledges for current user
    $sql = "select p.`key`, t.`key` period_key, " .
    "CONCAT( t.month_abbrev, '-', t.year) period, " .
    "p.`distance` pledge_distance, ";
    $sql .= "p.pledge_per_mile pledge_amount ";
    $sql .= "from bkthn_pledges p ";
    $sql .= " left join bkthn_periods t ";
    $sql .= " on p.period_key = t.key ";
    $sql .= "where p.user_key = %d ";

    //query the database
    $result = db_query($sql, $user->uid);

    //get the results and build each table row
    //for each record returned
    while ($row = db_fetch_object($result))
    {
      $period_key = $row->period_key;
      $sql = 'select sum(est_miles) actual_miles' .
      '   from bkthn_periods pd, bkthn_entries e ' .
      '  where pd.`key` = %d ' .
      '    and e.start_date between pd.start_date and pd.end_date ' .
      '    and e.user_key = %d ';
      $distance_result = db_query($sql, array (
        $period_key,
        $user->uid
      ));
      $distance_row = db_fetch_object($distance_result);

      $output .= "<tr>";
      $output .= "<td>" . t($row->period) . "</td>";
      $output .= "<td>" . t($row->pledge_distance) . "</td>";
      $output .= "<td>" . t($distance_row->actual_miles) . "</td>";
      $output .= "<td>" . t($row->pledge_amount) . "</td>";

      $output .= "<td>" . l("Edit", "bkthn/pledges/edit/" . $row->key ) . "</td>";
      $output .= "</tr>";
    }

    //end the table
    $output .= "</table>";

  }
  $output .= "<p>&nbsp;</p>";
  $output .= "<h2>Quick Reference</h2>" .
      "<UL><LI>Once you have pledged for the Month of May, you'll be able to " .
      "solicit sponsors for the miles you ride.  Use the 'Sponsors' menu item." .
      "<LI>The 'Ride Logs' is where you enter the miles you ride each day." .
      "<LI>If you have multiple bikes that you'd like to track separately, you " .
      "can set this up under 'Bikes'." .
      "<LI>Pledges is where you can view a summary of your pledges." .
      "<LI>This page ('Progress') is where you can track how you're doing " .
      "against where you're expected to be on today's date." .
      "</UL>";

  return $output;
} //function bkthn_main

/**
* Display the main Progress Page
* @param form_state by reference array of all post values of the form
* @param trans_type - only 'main' is used.
* @param bkthn_key - no longer used.
* @return array form
*/
function bkthn_form(& $form_state, $trans_type, $bkthn_key)
{
  $form = array ();

  $form_state['current_form'] = 'bkthn_main';
  $form['log_title'] = array (
    '#value' => t(bkthn_main())
  );

  return $form;
} //bkthn_form


/**
 * Tests to see if any bikes have been defined for this user.
 */
function _check_bikes($user_id)
{
  //check if there are any bikes for current user
  $sql = "select count(*) as row_count ";
  $sql .= "from bkthn_bikes ";
  $sql .= "where user_key = %d ";

  $result = db_query($sql, $user_id);
  $row = db_fetch_object($result);
  return ($row->row_count > 0);
}


/**
 * Summarizes the participation level across all pledges and invites others
 * to participate.
 */
function _print_summary()
{
  // Find the number of pledges for this period.
  $sql = "select count(*) as pledge_count, ";
  $sql .= "sum(distance) as total_miles ";
  $sql .= "from bkthn_pledges ";
  $sql .= "where period_key = %d";

  $result = db_query($sql, 5);
  $row = db_fetch_object($result);

  $cyclist_count = ($row->pledge_count > 8) ? $row->pledge_count : "";

  $message = "Planning to ride this month?  ";
  $message .= "Join the other " . $cyclist_count . " cyclists who have ";
  $message .= "pledged to ride a combined " . $row->total_miles;
  $message .= " miles the month of May.";
  return $message;
}

/**
 * Draws a progress graph for the given UserID and returns the HTML pointing
 * to this newly created image.
 *
 * To avoid different users stepping on each other's image, the userID is
 * appended to name of the image file.
 */
function _draw_progress($user_id)
{
  // Calculate graph values to display
  $now = getdate();
  // $days_in_month = cal_days_in_month(CAL_GREGORIAN, $now[mon], $now[year]);
  $days_in_month = 31;
  $day_within_month = $now[mday];
  $sql = 'select distance, start_date, end_date ';
  $sql .= 'from bkthn_pledges pl, bkthn_periods pd' .
  '  where current_date between pd.start_date and pd.end_date ' .
  '    and pd.`key` = pl.period_key ' .
  '    and user_key = %d ';
  $result = db_query($sql, $user_id);
  $distance_row = db_fetch_object($result);
  if (!$distance_row)
  {
  	drupal_set_message(t("No pledges for the current month."));
  	return "";
  }

  $pledge = $distance_row->distance;

  $period_start_date = $distance_row->start_date;
  $period_end_date = $distance_row->end_date;
  $sql = 'select sum(est_miles) distance_over_period ' .
  '   from bkthn_entries ' .
  '  where start_date between "' . $period_start_date .
  '"                      and "' . $period_end_date .
  '"   and user_key = %d ';
  $result = db_query($sql, $user_id);
  $row = db_fetch_object($result);
  $actual = $row->distance_over_period;

  $pledge_progress = round(100 * $day_within_month / $days_in_month);
  $mileage_progress = round(100 * $actual / $pledge);

  //Creating the Image
  $width = 400;
  $height = 60;
  $im = imagecreate($width, $height);

  //Defining colors
  $black = imagecolorallocate($im, 0, 0, 0);
  $white = imagecolorallocate($im, 255, 255, 255);
  $blue = imagecolorallocate($im, 0, 0, 240);
  $red = imagecolorallocate($im, 240, 0, 0);
  $green = imagecolorallocate($im, 0, 240, 0);

  //Drawing white background and black outline
  imagefilledrectangle($im, 0, 0, $width -1, $height -1, $white);
  imagerectangle($im, 0, 0, $width -1, $height -1, $black);

  //Drawing Horizontal Bars
  $actual_bar_color = ($mileage_progress > $pledge_progress) ? $green : $red;
  // 0->100% <=> 80->380px
  imagefilledrectangle($im, 80, 5, 80 + $mileage_progress * 3, 27, $actual_bar_color);
  imagefilledrectangle($im, 80, 32, 80 + $pledge_progress * 3, 54, $blue);

  //Printing out Horizontal Strings in front of each bar
  imagestring($im, 4, 12, 10, "Actual", $black);
  $x_percent_text = ($mileage_progress > 50) ? 100 : 250;
  imagestring($im, 4, $x_percent_text, 10, $mileage_progress . "%", $black);
  imagestring($im, 4, 12, 40, "Planned", $black);
  $x_percent_text = ($pledge_progress > 50) ? 100 : 250;
  $bar_text_color = ($pledge_progress > 50) ? $white : $black;
  imagestring($im, 4, $x_percent_text, 40, $pledge_progress . "%", $bar_text_color);

  //Creating the image output in png format
  $filename = 'images/bkthn/progress-' . $user_id . '.png';
  imagepng($im, $filename);

  //freeing up any memory associated with the image
  imagedestroy($im);

  // Building a message for how much distance remains to meet pledge
  if ($mileage_progress > 100)
  {
  	$progress_message = "Well Done!  You have met your pledge goal.";
  }
  else
  {
  	$remaining_distance = $pledge - $actual;
  	$remaining_days = $days_in_month - $day_within_month + 1;
  	$average_remaining = round($remaining_distance / $remaining_days, 1);
  	$progress_message = "To complete your pledge, you should ride " .
  	  $average_remaining . " miles per day over the next " . $remaining_days .
      " remaining days.";
  }

  return ('<img src="/' . $filename . '" ><p>' . $progress_message );
}
?>
