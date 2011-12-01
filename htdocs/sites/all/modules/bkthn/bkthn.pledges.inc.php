<?php


/**
* Generate HTML for all travel logs of the current user
* @returns HTML page
*/
function bkthn_pledges_main()
{
  //declare global variables
  global $user;

  //initialize variable
  $output = '';

  //check if there are any pledges
  //set up for current user
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
    $output .= "<p>Before you can start using Bike Commute-a-Thon, you will need to enter a pledge.  </p>";
    $output .= "<p>Click the link below.... </p>";
  }
  //nothing left to check show the listing
  else
  {
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

    //set up the query to get all travel logs
    //for current user
    $sql = "select p.`key`, t.`key` period_key, CONCAT( t.month_abbrev, '-', t.year) period, " .
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

      $output .= "<td>" . l("Edit", "bkthn/pledges/edit/" . $row->key) . "</td>";
      $output .= "</tr>";
    }

    //end the table
    $output .= "</table>";
  }

  // Permit adding a pledge at all times
  $output .= "<p>" . l("Add Pledge", "bkthn/pledges/add") . "</p>";

  $output .= "<p>&nbsp;</p>";

  return $output;
} //function bkthn_pledges_main

/**
* Display the travel log add new, edit, and delete forms
* @param form_state by reference array of all post values of the form
* @param trans_type string that tells what type of transaction is being performed - add, edit, delete
* @param bkthn_key integer that identifies the current record to edit or delete. null or 0 when adding a record
* @return array form
*/
function bkthn_pledges_form(& $form_state, $trans_type, $bkthn_pledges_key)
{
  global $user;
  $form = array ();

  if ($trans_type == 'add')
  {
    $form['title'] = array (
      '#value' => t('<p>Add New Pledge</p>'),

    );
    $form_state['current_form'] = 'bkthn_pledges_add';

    $options[] = t('<select period>');
    $sql = 'select `key`, CONCAT(month_abbrev, "-", `year`) period from bkthn_periods ' .
    'where end_date > NOW() order by start_date';
    $result = db_query($sql);
    while ($row = db_fetch_object($result))
    {
      $options[$row->key] = $row->period;
    }
    $form['period_key'] = array (
      '#type' => 'select',
      '#title' => t('Month'),
      '#options' => $options
    );

    $form['distance'] = array (
      '#type' => 'textfield',
      '#title' => t('Distance'),
      '#description' => t('Total miles for the entire month'),
      '#size' => 16
    );
    $form['pledgeHelp'] = array (
      '#type' => 'item',
      '#title' => t('Pledge Amount per Mile'),
      '#value' => t('To setup a dollar amount for each mile of your pledge, ' .
          'Use the "Sponsors" menu item and enter "Sponsorhips". ' .
          'Sponsors should identify themselves either by ' .
            'logging in, or if you want to enter their sponsorship for them, ' .
            'you can note on the sponsorship who is your sponsor.'),

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
      $form_state['current_form'] = 'bkthn_delete';
      $form['key'] = array (
        '#type' => 'hidden',
        '#value' => $bkthn_pledges_key
      );

      $sql = "select `name`, description, bkthn_bikes_key, bkthn_types_key ";
      $sql .= "from bkthn ";
      $sql .= "where `key` = %d ";

      $result = db_query($sql, $bkthn_pledges_key);
      $bkthn_row = db_fetch_object($result);

      $form['title'] = array (
        '#value' => t('<p>Delete ' . t($bkthn_row->name) . ' Travel Log</p>')
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
      $form_state['current_form'] = 'bkthn_pledges_edit';
      $form['key'] = array (
        '#type' => 'hidden',
        '#value' => $bkthn_pledges_key
      );

      $sql = "select distance, pledge_per_mile, pd.month, pd.year ";
      $sql .= "from bkthn_pledges pl, bkthn_periods pd ";
      $sql .= "where pl.`key` = %d " .
          " and pl.period_key = pd.`key`";

      $result = db_query($sql, $bkthn_pledges_key);
      $bkthn_row = db_fetch_object($result);

      $form['title'] = array (
        '#value' => t('<p>Month: ' .
          $bkthn_row->month . ' ' .
          $bkthn_row->year . '</p>')
      );
      $form['distance'] = array (
        '#type' => 'textfield',
        '#title' => t('Distance'),
        '#description' => t('in Miles'),
        '#default_value' => $bkthn_row->distance
      );
      $form['pledge_per_mile'] = array (
        '#type' => 'item',
        '#title' => t('Pledge per Mile'),
        '#description' => t('Sum of all sponsorships for this pledge.  ' .
            'Edit this value by changing the Sponsorships.'),
        '#value' => $bkthn_row->pledge_per_mile
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
      $form['help'] = array (
        '#type' => 'item',
        '#title' => t('To Edit Pledge per Mile'),
        '#value' => t('Click "Cancel" and then select the "Sponsors" menu item ' .
            'and enter ' .
            'sponsorships.  Sponsors should identify themselves either by ' .
            'logging in, or if you want to enter their sponsorship, you can ' .
            'note on the sponsorship who is your sponsor.')
      );
    } //edit
  else
  {
    $form_state['current_form'] = 'bkthn_pledges_main';
    $form['log_title'] = array (
      '#value' => t(bkthn_pledges_main()),


    );
  } //anything else

  return $form;
} //bkthn_pledges_form

/**
* Processes the form validate hook
* @param form array of the current form to be processed
* @param form_state by reference array of all post values of the form
* @return array form
*/
function bkthn_pledges_form_validate($form, & $form_state)
{
  //don't validate if it's a cancel or delete
  if ($form_state['values']['op'] == 'Save')
  {
    $name = trim(check_plain($form_state['values']['distance']));
    if ($name == '')
    {
      form_set_error('Validation', t('Are you really planning to ride no miles this month?'));
    }
  }
}
/**
* Processes the form submit hook
* @param form array of the current form to be processed
* @param form_state by reference array of all post values of the form
* @return array form
*/
function bkthn_pledges_form_submit($form, & $form_state)
{
  global $user;
  if ($form_state['values']['op'] == 'Save')
  {
    if ($form_state['current_form'] == 'bkthn_pledges_add')
    {
      try
      {
        $sql = "insert into bkthn_pledges ";
        $sql .= "(user_key, period_key, distance, pledge_per_mile) ";
        $sql .= "values ";
        $sql .= "(%d,%d,'%f','%f') ";

        if (!db_query($sql, $user->uid, $form_state['values']['period_key'], trim($form_state['values']['distance']), trim($form_state['values']['pledgeAmount']) //	                   , $form_state['values']['bkthn_types_key']
        //	                   , $form_state['values']['bkthn_bikes_key'])
        ))
        {
          throw new Exception('Could not update record!');
        }
        //set the status

        drupal_set_message(t('Your record has been saved.'), 'status');
        $form_state['rebuild'] = FALSE;
        $form_state['redirect'] = url("bkthn/pledge");
      } //try
      catch (Exception $e)
      {
        //set the error
        drupal_set_message(t('Your new record could not be saved.  There was an error.'), 'error');
      } //catch
    } //Add
    else
      if ($form_state['current_form'] == 'bkthn_pledges_edit')
      {
        try
        {
          $sql = "update bkthn_pledges ";
          $sql .= "set ";
          $sql .= "distance = '%f', ";
          $sql .= "pledge_per_mile = '%f' ";
          $sql .= "where `key` = %d ";

          if (!db_query($sql, trim($form_state['values']['distance']), $form_state['values']['pledge_per_mile'], $form_state['values']['key']))
          {
            throw new Exception('Could not update record!');
          }
          //set the status

          drupal_set_message(t('Your record has been saved.'), 'status');
          $form_state['rebuild'] = FALSE;
          $form_state['redirect'] = url("bkthn/pledges");
        } //try
        catch (Exception $e)
        {
          //set the error
          drupal_set_message(t('Your new record could not be saved.  There was an error.'), 'error');
        } //catch
      } //bkthn_pledges_edit
  } //Save
  else
    if ($form_state['values']['op'] == 'Delete')
    {
      if ($form_state['current_form'] == 'bkthn_pledges_edit')
      {
        $form_state['rebuild'] = FALSE;
        $form_state['redirect'] = url("bkthn/pledges/delete/" . $form_state['values']['key']);
      } //bkthn_pledges_bikes_edit
      else
      {
        try
        {
          $sql = "delete from bkthn ";
          $sql .= "where `key` = %d ";
          if (!db_query($sql, $form_state['values']['key']))
          {
            throw new Exception('Could not delete record!');
          }
          //set the status

          drupal_set_message(t('Your record has been deleted.'), 'status');
          $form_state['rebuild'] = FALSE;
          $form_state['redirect'] = url("bkthn/" . $form_state['values']['bkthn_key']);
        } //try
        catch (Exception $e)
        {
          //set the error
          drupal_set_message(t('Your new record could not be saved.  There was an error.'), 'error');
        } //catch
      } //bkthn_pledges_delete
    } //delete
  //everything else
  else
    if ($form_state['values']['op'] == 'Cancel')
    {
      drupal_set_message(t('Cancelled.'), 'status');
      $form_state['rebuild'] = FALSE;
      $form_state['redirect'] = url("bkthn/pledges");
    } //cancel
} //bkthn_pledges_form_submit
?>