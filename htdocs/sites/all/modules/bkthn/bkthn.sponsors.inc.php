<?php


/**
* Generate HTML for all sponsors and sponsorships of the current user
* @returns HTML page
*/
function bkthn_sponsors_main()
{
  //declare global variables
  global $user;

  //initialize variable
  $output = '';

  //check if there are any sponsorships
  //made by current user
  $sql = "select count(*) as row_count ";
  $sql .= "from bkthn_sponsorships ";
  $sql .= "where sponsor_key = %d ";

  //query the database
  $result = db_query($sql, $user->uid);
  //get the results
  $row = db_fetch_object($result);
  //initialize the variable
  $flag_sponsors_riders = FALSE;
  //check the results
  if ($row->row_count > 0)
  {
    $flag_sponsors_riders = TRUE;
  }

  //check if there are any sponsorships
  //set up for current user by others
  $sql = "select count(*) as row_count ";
  $sql .= "from bkthn_sponsorships ";
  $sql .= "where rider_key = %d ";

  //query the database
  $result = db_query($sql, $user->uid);
  //get the results
  $row = db_fetch_object($result);
  //initialize the variable
  $flag_has_sponsorships = FALSE;
  //check the results
  if ($row->row_count > 0)
  {
    $flag_has_sponsorships = TRUE;
  }

  //if there are people who have sponsored this user
  if ($flag_has_sponsorships)
  {
    $output .= "<p>People who are sponsoring you:</p>";
    //start of the table
    $output .= "<table>";
    //table header
    $output .= "<tr>";
    $output .= "<th>Sponsor</th>";
    $output .= "<th>Pledge per Mile</th>";
    $output .= "<th>Projected Donation</th>";
    $output .= "<th>Note</th>";
    $output .= "</tr>";

    //set up the query to get all sponsorships for this user
    $sql = "select u.name sponsor_name, s.sponsor_key, " .
        "s.pledge_per_mile pledge_amount, s.note ";
    $sql .= "from bkthn_sponsorships s, users u ";
    // $sql .= "from bkthn_sponsorships s, drupal_users u ";
    $sql .= "where s.rider_key = %d " .
        "      and u.uid = s.sponsor_key";

    //query the database
    $result = db_query($sql, $user->uid);

    //get the results and build each table row
    //for each record returned
    while ($row = db_fetch_object($result))
    {
      // Retrieve Pledged mile total for rider.
      $sql = 'select distance' .
      '   from bkthn_pledges p' .
      '  where p.`period_key` = %d ' .
      '    and p.user_key = %d ';
      $distance_result = db_query($sql, array (
        5,
        $user->uid
      ));
      $distance_row = db_fetch_object($distance_result);

      // Calculate the projected donation based on distance and pledge per mile
      $dollar_total = $distance_row->distance * $row->pledge_amount;
//      setlocale(LC_MONETARY, 'en_US');
//      $dollar_formatted = money_format('%i', $dollar_total);
      $dollar_formatted = '$' . number_format($dollar_total, 2);

      $output .= "<tr>";
      $output .= "<td>" . t($row->sponsor_name) . "</td>";
      $output .= "<td>" . t('$' . number_format($row->pledge_amount, 2)) . "</td>";
      $output .= "<td>" . t( $dollar_formatted) . "</td>";
      $output .= "<td>" . t($row->note) . "</td>";
      $output .= "</tr>";
    }

    //end the table
    $output .= "</table>";
  }

  //if there are sponsorships made by this user
  if ($flag_sponsors_riders)
  {
    $output .= "<p>Riders you are sponsoring:</p>";
    //start of the table
    $output .= "<table>";
    //table header
    $output .= "<tr>";
    $output .= "<th>Rider</th>";
    $output .= "<th>Pledge per Mile</th>";
    $output .= "<th>Projected Donation</th>";
    $output .= "<th>Note</th>";
    $output .= "<th>&nbsp;</th>";
    $output .= "<th>&nbsp;</th>";
    $output .= "</tr>";

    //set up the query to get all sponsorships for this user
    $sql = "select s.`key`, u.name rider_name, s.rider_key, " .
        "s.pledge_per_mile pledge_amount, s.note ";
    $sql .= "from bkthn_sponsorships s, users u ";
    // $sql .= "from bkthn_sponsorships s, drupal_users u ";
    $sql .= "where s.sponsor_key = %d " .
        "      and u.uid = s.rider_key";

    //query the database
    $result = db_query($sql, $user->uid);

    //get the results and build each table row
    //for each record returned
    while ($row = db_fetch_object($result))
    {
      // Retrieve Pledged mile total for rider.
      $rider_key = $row->rider_key;
      $sql = 'select distance' .
      '   from bkthn_pledges p' .
      '  where p.`period_key` = %d ' .
      '    and p.user_key = %d ';
      $distance_result = db_query($sql, array (
        5,
        $rider_key
      ));
      $distance_row = db_fetch_object($distance_result);

      // Calculate the projected donation based on distance and pledge per mile
      $dollar_total = $distance_row->distance * $row->pledge_amount;
//      setlocale(LC_MONETARY, 'en_US');
//      $dollar_formatted = money_format('%i', $dollar_total);
      $dollar_formatted = '$' . number_format($dollar_total, 2);

      $output .= "<tr>";
      $output .= "<td>" . t($row->rider_name) . "</td>";
      $output .= "<td>" . t('$' . number_format($row->pledge_amount, 2)) . "</td>";
      $output .= "<td>" . t( $dollar_formatted) . "</td>";
      $output .= "<td>" . t( $row->note) . "</td>";
      $output .= "<td>" . l("Edit", "bkthn/sponsorship/edit/" . $row->key) . "</td>";
      $output .= "<td>" . l("Delete", "bkthn/sponsorship/delete/" . $row->key) . "</td>";
      $output .= "</tr>";
    }

    //end the table
    $output .= "</table>";
  }

  // Permit adding a Sponsorship at all times
  $output .= "<p>" . l("Sponsor a Rider", "bkthn/sponsorship/add") . "</p>";

  $output .= "<p>" . t("When asking your sponsor to support your ride, ask " .
      "them if they have an account on this website.  If not, and they are " .
      "unable to join, you can 'sponsor yourself' and make a note indicating " .
      "who has sponsored you for a specific amount per mile. ") . "</p>";

  $output .= "<p>&nbsp;</p>";

  return $output;
} //function bkthn_sponsors_main

/**
* Display the Sponsorship add new, edit, and delete forms
* @param form_state by reference array of all post values of the form
* @param trans_type string that tells what type of transaction is being performed - add, edit, delete
* @param bkthn_key integer that identifies the current record to edit or delete. null or 0 when adding a record
* @return array form
*/
function bkthn_sponsors_form(& $form_state, $trans_type, $bkthn_sponsors_key)
{
  global $user;
  $form = array ();

  if ($trans_type == 'add')
  {
    $form['title'] = array (
      '#value' => t('<p>Add New Sponsorship</p>'),

    );
    $form_state['current_form'] = 'bkthn_sponsorship_add';

    $options[-1] = t('<select rider>');
    // $sql = 'select uid, name from drupal_users u, bkthn_pledges p ' .
    $sql = 'select uid, name from users u, bkthn_pledges p ' .
    'where u.uid = p.user_key' .
    '  and p.period_key = 5';

    $result = db_query($sql);
    while ($row = db_fetch_object($result))
    {
      $options[$row->uid] = $row->name;
    }
    $form['rider_key'] = array (
      '#type' => 'select',
      '#title' => t('Rider you are sponsoring'),
      '#options' => $options
    );
    $form['pledgeAmount'] = array (
      '#type' => 'textfield',
      '#title' => t('Pledge per Mile')
    );
    $form['note'] = array (
      '#type' => 'textfield',
      '#title' => t('Note')
    );

    $form['submit'] = array (
      '#type' => 'submit',
      '#value' => t('Save')
    );
    $form['cancel'] = array (
      '#type' => 'button',
      '#value' => t('Cancel'),
      '#executes_submit_callback' => TRUE
    );
  } //add new travel log * @param form_state by reference array of all post values of the form
  else
    if ($trans_type == 'delete')
    {
      $form_state['current_form'] = 'bkthn_sponsorship_delete';
      $form['key'] = array (
        '#type' => 'hidden',
        '#value' => $bkthn_sponsors_key
      );

      $sql = "select u.name rider_name ";
      // $sql .= "from bkthn_sponsorships s, drupal_users u ";
      $sql .= "from bkthn_sponsorships s, users u ";
      $sql .= "where s.key = %d " .
          "      and u.uid = s.rider_key ";

      $result = db_query($sql, $bkthn_sponsors_key);
      $bkthn_row = db_fetch_object($result);

      $form['title'] = array (
        '#value' => t('<p>Delete Sponsorship for rider ' .
          t($bkthn_row->rider_name). '</p>')
      );
      $form['submit'] = array (
        '#type' => 'submit',
        '#value' => t('Delete')
      );
      $form['cancel'] = array (
        '#type' => 'button',
        '#value' => t('Cancel'),
        '#executes_submit_callback' => TRUE
      );
    } //delete
  else
    if ($trans_type == 'edit')
    {
      $form_state['current_form'] = 'bkthn_sponsorships_edit';
      $form['key'] = array (
        '#type' => 'hidden',
        '#value' => $bkthn_sponsors_key
      );

      $sql = "select pledge_per_mile, u.name rider_name, s.rider_key, s.note ";
      // $sql .= "from bkthn_sponsorships s, drupal_users u ";
      $sql .= "from bkthn_sponsorships s, users u ";
      $sql .= "where s.key = %d " .
          "      and u.uid = s.rider_key";

      $result = db_query($sql, $bkthn_sponsors_key);
      $bkthn_row = db_fetch_object($result);

      $form['rider_key'] = array (
        '#type' => 'hidden',
        '#value' => $bkthn_row->rider_key
      );

      $form['title'] = array (
        '#value' => t('<p>Pledge for Rider '
         . $bkthn_row->rider_name . ' </p>')
      );
      $form['pledge_per_mile'] = array (
        '#type' => 'textfield',
        '#title' => t('Pledge per Mile'),
        '#default_value' => $bkthn_row->pledge_per_mile
      );
      $form['note'] = array (
        '#type' => 'textfield',
        '#title' => t('Note'),
        '#default_value' => $bkthn_row->note
      );

      $form['submit'] = array (
        '#type' => 'submit',
        '#value' => t('Save')
      );
      $form['cancel'] = array (
        '#type' => 'button',
        '#value' => t('Cancel'),
        '#executes_submit_callback' => TRUE
      );
    } //edit
  else
  {
    $form_state['current_form'] = 'bkthn_sponsors_main';
    $form['log_title'] = array (
      '#value' => t(bkthn_sponsors_main()),
    );
  } //anything else

  return $form;
} //bkthn_sponsors_form

/**
* Processes the form validate hook
* @param form array of the current form to be processed
* @param form_state by reference array of all post values of the form
* @return array form
*/
function bkthn_sponsors_form_validate($form, & $form_state)
{
  //don't validate if it's a cancel or delete
  if ($form_state['values']['op'] == 'Save')
  {
    $name = trim(check_plain($form_state['values']['rider_key']));
    if ($name == '')
    {
      form_set_error('Validation', t('Choose the rider whose pledge you plan to sponsor.'));
    }
  }
}
/**
 * Takes a rider's pledge and adds all the sponsorhip amounts to the total
 * that is recorded for the whole pledge.
 *
 * Pledge Period is hard-coded for the Month of May 2010 at this time.
 *
 * @param rider_key for the rider's record.
 * @return none
 */
function bkthn_update_pledge_from_sponsorships($rider_key)
{
  $sql = "update bkthn_pledges set pledge_per_mile = (" .
      "    select SUM(pledge_per_mile) FROM bkthn_sponsorships" .
      "     where rider_key = %d)" .
      " where user_key = %d" .
      "   and period_key = 5";

  if (!db_query($sql, $rider_key, $rider_key))
  {
    throw new Exception('Could not update pledge from sponsorship records');
  }
}



/**
* Processes the form submit hook
* @param form array of the current form to be processed
* @param form_state by reference array of all post values of the form
* @return array form
*/
function bkthn_sponsors_form_submit($form, & $form_state)
{
  global $user;
  if ($form_state['values']['op'] == 'Save')
  {
    if ($form_state['current_form'] == 'bkthn_sponsorship_add')
    {
      try
      {
        $sql = "insert into bkthn_sponsorships ";
        $sql .= "(sponsor_key, rider_key, period_key, pledge_per_mile, note) ";
        $sql .= "values ";
        $sql .= "(%d,%d,%d,'%f','%s') ";

        if (!db_query($sql,
            $user->uid,
            $form_state['values']['rider_key'],
            5,
            trim($form_state['values']['pledgeAmount']),
            trim($form_state['values']['note'])
        ))
        {
          throw new Exception('Could not update record!');
        }

        // Add newest sponsorship to the pledge total
        bkthn_update_pledge_from_sponsorships(
          $form_state['values']['rider_key']);

        //set the status

        drupal_set_message(t('Your record has been saved.'), 'status');
        $form_state['rebuild'] = FALSE;
        $form_state['redirect'] = url("bkthn/sponsors");
      } //try
      catch (Exception $e)
      {
        //set the error
        drupal_set_message(t('Your new record could not be saved.  There was an error.'), 'error');
      } //catch
    } //Add
    else
      if ($form_state['current_form'] == 'bkthn_sponsorships_edit')
      {
        try
        {
          $sql = "update bkthn_sponsorships ";
          $sql .= "set ";
          $sql .= "pledge_per_mile = '%f', ";
          $sql .= "note = '%s' ";
          $sql .= "where `key` = %d ";

          if (!db_query($sql, $form_state['values']['pledge_per_mile'],
                              $form_state['values']['note'],
                              $form_state['values']['key']))
          {
            throw new Exception('Could not update record!');
          }

	        // Amend pledge total
	        bkthn_update_pledge_from_sponsorships(
          $form_state['values']['rider_key']);

          //set the status

          drupal_set_message(t('Your record has been saved.'), 'status');
          $form_state['rebuild'] = FALSE;
          $form_state['redirect'] = url("bkthn/sponsors");
        } //try
        catch (Exception $e)
        {
          //set the error
          drupal_set_message(t('Your new record could not be saved.  There was an error.'), 'error');
        } //catch
      } //bkthn_sponsorships_edit
  } //Save
  else
    if ($form_state['values']['op'] == 'Delete')
    {
      if ($form_state['current_form'] == 'bkthn_sponsorships_edit')
      {
        $form_state['rebuild'] = FALSE;
        $form_state['redirect'] = url("bkthn/sponsorships/delete/" . $form_state['values']['key']);
      } //bkthn_sponsors_bikes_edit
      else
      {
        // Retrieve Rider's key so we can adjust their records after the delete.
        $sql = "select rider_key from bkthn_sponsorships " .
            "where `key` = %d";
        $result = db_query($sql, $form_state['values']['key']);
        $row = db_fetch_object($result);
        $rider_key = $row->rider_key;

        try
        {
          $sql = "delete from bkthn_sponsorships ";
          $sql .= "where `key` = %d ";
          if (!db_query($sql, $form_state['values']['key']))
          {
            throw new Exception('Could not delete record!');
          }

          // Amend pledge total
          bkthn_update_pledge_from_sponsorships($rider_key);

          //set the status

          drupal_set_message(t('Your record has been deleted.'), 'status');
          $form_state['rebuild'] = FALSE;
          $form_state['redirect'] = url("bkthn/sponsors");
        } //try
        catch (Exception $e)
        {
          //set the error
          drupal_set_message(t('Your new record could not be saved.  There was an error.'), 'error');
        } //catch
      } //bkthn_sponsorships_delete
    } //delete
  //everything else
  else
    if ($form_state['values']['op'] == 'Cancel')
    {
      drupal_set_message(t('Cancelled.'), 'status');
      $form_state['rebuild'] = FALSE;
      $form_state['redirect'] = url("bkthn/sponsors");
    } //cancel
} //bkthn_sponsors_form_submit
?>
