<?php


/**
* Generate HTML for all travel log entires of the current user
* @param bkthn_key integer that filters out log entries based on parent bkthn
* @returns HTML page
*/
function bkthn_logs_main($bkthn_key, $user_key)
{

  $output = '';
  $output .= "<table>";
  $output .= "<tr>";
  $output .= "<th>Date</th>";
  $output .= "<th>Miles</th>";
  $output .= "<th>Duration</th>";
  $output .= "<th>&nbsp;</th>";
  $output .= "<th>&nbsp;</th>";
  $output .= "</tr>";

  $sql = "select `key`, start_date, est_miles, est_time ";
  $sql .= "from bkthn_entries ";
  $sql .= "where user_key = %d ";
  $sql .= "order by start_date desc, `key` desc "; // Most recent at the top

  // $result = db_query($sql, $bkthn_key);
  $result = db_query($sql, $user_key);

  while ($row = db_fetch_object($result))
  {
    $output .= "<tr>";
    $output .= "<td>" . t($row->start_date) . "</td>";
    $output .= "<td>" . $row->est_miles . "</td>";
    $output .= "<td>" . $row->est_time . "</td>";

    $output .= "<td>" . l("Edit", "bkthn/logs/" . $bkthn_key . "/edit/" . $row->key) . "</td>";
    $output .= "<td>" . l("Delete", "bkthn/logs/" . $bkthn_key . "/delete/" . $row->key) . "</td>";
    $output .= "</tr>";
  }

  $output .= "<tr>";
  $output .= "<td>" . l("Add Log Entry", "bkthn/logs/" . $bkthn_key . "/add") . "</td>";
  $output .= "</tr>";
  $output .= "</table>";
  return $output;

} //function bkthn_logs_main

/**
* Display the travel log add new, edit, and delete forms
* @param form_state by reference array of all post values of the form
* @param trans_type string that tells what type of transaction is being performed - add, edit, delete
* @param bkthn_key integer that identifies the current travel log parent for listing all log entries
* @param bkthn_logs_key integer that identifies the current record to edit or delete. null or 0 when adding a record
* @return array form
*/
function bkthn_logs_form(& $form_state, $trans_type, $bkthn_key, $bkthn_logs_key)
{
  global $user;
  $debug_flag = FALSE;
  if ($debug_flag)
  {
    $debug = "trans_type= " . $trans_type . "<br>";
    $debug .= "bkthn_key= " . $bkthn_key . "<br>";
    $debug .= "bkthn_logs_key= " . $bkthn_logs_key . "<br>";
    $debug .= "user->uid= " . $user->uid . "<br>";

    $form['debug'] = array (
      '#value' => t('<p>' . $debug . "</p>")
    );
  }
  if ($trans_type == 'add')
  {
    $form_state['current_form'] = 'bkthn_logs_add';
    $form['bkthn_key'] = array (
      '#type' => 'hidden',
      '#value' => $bkthn_key
    );
    $form['title'] = array (
      '#value' => t('<p>Add Log Entry</p>')
    );

    $sql = 'select `key`, `name` from bkthn_bikes ' .
    'where user_key = %d';
    $result = db_query($sql, $user->uid);
    while ($row = db_fetch_object($result))
    {
      $options[$row->key] = $row->name;
    }

    $form['bike_key'] = array (
      '#type' => 'select',
      '#title' => t('Bicycle'),
      '#options' => $options
    );
    $form['start_date'] = array (
      '#type' => 'date',
      '#title' => t('Start Date')
    );
    $form['est_miles'] = array (
      '#type' => 'textfield',
      '#title' => t('Miles'),
      '#default_value' => '0',
      '#size' => 10
    );
    $form['est_time'] = array (
      '#type' => 'textfield',
      '#title' => t('Travel Time (HH:MI)'),
      '#default_value' => '00:00',
      '#size' => 10
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

  } //add
  else
    if ($trans_type == 'edit')
    {
      $form_state['current_form'] = 'bkthn_logs_edit';
      $form['key'] = array (
        '#type' => 'hidden',
        '#value' => $bkthn_logs_key
      );
      $form['bkthn_key'] = array (
        '#type' => 'hidden',
        '#value' => $bkthn_key
      );

      $sql = "select start_date, start_time, end_date, end_time, est_miles, est_time, bike_key ";
      $sql .= "from bkthn_entries ";
      $sql .= "where `key` = %d ";

      $result = db_query($sql, $bkthn_logs_key);
      $row = db_fetch_object($result);

      $form['title'] = array (
        '#value' => t('<p>Edit ' . t($row->start_date) . " Log Entry </p>")
      );

      $sql = 'select `key`, `name` from bkthn_bikes ' .
      'where user_key = %d';
      $result = db_query($sql, $user->uid);
      while ($bike_row = db_fetch_object($result))
      {
        $options[$bike_row->key] = $bike_row->name;
      }

      $form['bike_key'] = array (
        '#type' => 'select',
        '#title' => t('Bicycle'),
        '#default_value' => $row->bike_key,
        '#options' => $options
      );

      $date = explode('-', $row->start_date);
      $form['start_date'] = array (
        '#type' => 'date',
        '#title' => t('Start Date'),
        '#default_value' => array (
          'year' => $date[0],
          'month' => intval($date[1]),
          'day' => intval($date[2])
        )
      );
      $form['est_miles'] = array (
        '#type' => 'textfield',
        '#title' => t('Miles'),
        '#default_value' => '0',
        '#default_value' => $row->est_miles,
        '#size' => 10
      );
      unset ($time);
      $time = explode(':', $row->est_time);
      $form['est_time'] = array (
        '#type' => 'textfield',
        '#title' => t('Travel Time'),
        '#default_value' => $time[0] . ':' . $time[1],
        '#size' => 10
      );
      $form['submit'] = array (
        '#type' => 'submit',
        '#value' => t('Save')
      );
      $form['delete'] = array (
        '#type' => 'button',
        '#value' => t('Delete'),
        '#executes_submit_callback' => TRUE
      );
      $form['cancel'] = array (
        '#type' => 'button',
        '#value' => t('Cancel'),
        '#executes_submit_callback' => TRUE
      );
    } //edit
  else
    if ($trans_type == 'delete')
    {
      $form_state['current_form'] = 'bkthn_logs_delete';
      $form['key'] = array (
        '#type' => 'hidden',
        '#value' => $bkthn_logs_key
      );
      $form['bkthn_key'] = array (
        '#type' => 'hidden',
        '#value' => $bkthn_key
      );
      $sql = "select `name`, description, start_date, start_time, end_date, " .
      "end_time, est_miles, est_time ";
      $sql .= "from bkthn_entries ";
      $sql .= "where `key` = %d ";

      $result = db_query($sql, $bkthn_logs_key);
      $row = db_fetch_object($result);

      $form['title'] = array (
        '#value' => t('<p>Delete ' . t($row->name) . " Log Entry </p>")
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
  {
    $form_state['current_form'] = 'bkthn_logs_main';

    $form['logs_main'] = array (
      '#value' => t(bkthn_logs_main($bkthn_key, $user->uid))
    );
    $form['cancel'] = array (
      '#type' => 'button',
      '#value' => t('Cancel'),
      '#executes_submit_callback' => TRUE
    );
  } //anything else

  return $form;
} //bkthn_logs_form

/**
* Processes the form submit hook
* @param form array of the current form to be processed
* @param form_state by reference array of all post values of the form
* @return array form
*/
function bkthn_logs_form_submit($form, & $form_state)
{
  global $user;

  if ($form_state['values']['op'] == 'Save')
  {
    if ($form_state['current_form'] == 'bkthn_logs_add')
    {
      $est_time = explode(':', $form_state['values']['est_time']);
      $start_time = explode(':', $form_state['values']['start_time']);
      $end_time = explode(':', $form_state['values']['end_time']);
      try
      {
        $sql = "insert into bkthn_entries ";
        $sql .= "(name, description, ";
        $sql .= "bkthn_key, start_date, end_date, ";
        $sql .= "start_time, end_time, est_miles, est_time, bike_key, user_key ) ";
        $sql .= "values ";
        $sql .= "('%s','%s',%d, '%s', '%s', '%s', '%s' , %f, '%s', %d, %d ) ";

        if (!db_query($sql, trim($form_state['values']['name']), trim($form_state['values']['description']), $form_state['values']['bkthn_key'], $form_state['values']['start_date']['year'] .
          '-' . $form_state['values']['start_date']['month'] .
          '-' . $form_state['values']['start_date']['day'], $form_state['values']['end_date']['year'] .
          '-' . $form_state['values']['end_date']['month'] .
          '-' . $form_state['values']['end_date']['day'], $start_time[0] . ':' . $start_time[1], $end_time[0] . ':' . $end_time[1], $form_state['values']['est_miles'], $est_time[0] . ':' . $est_time[1], $form_state['values']['bike_key'], $user->uid))
        {
          throw new Exception('Could not update record!');
        }
        //set the status

        drupal_set_message(t('Your record has been saved.'), 'status');
        $form_state['rebuild'] = FALSE;
        $form_state['redirect'] = url("bkthn/logs/" . $form_state['values']['bkthn_key']);
      } //try
      catch (Exception $e)
      {
        //set the error
        drupal_set_message(t('Your new record could not be saved.  There was an error.'), 'error');
      } //catch
    } //Add
    else
      if ($form_state['current_form'] == 'bkthn_logs_edit')
      {
        $est_time = explode(':', $form_state['values']['est_time']);
        $start_time = explode(':', $form_state['values']['start_time']);
        $end_time = explode(':', $form_state['values']['end_time']);
        try
        {
          $sql = "update bkthn_entries ";
          $sql .= "set ";
          $sql .= "name = '%s', ";
          $sql .= "description = '%s', ";
          $sql .= "start_date = '%s', ";
          $sql .= "end_date = '%s', ";
          $sql .= "start_time = '%s', ";
          $sql .= "end_time = '%s', ";
          $sql .= "est_miles = %f, ";
          $sql .= "est_time = '%s' ";
          $sql .= "where `key` = %d ";

          if (!db_query($sql, trim($form_state['values']['name']), trim($form_state['values']['description']), $form_state['values']['start_date']['year'] .
            '-' . $form_state['values']['start_date']['month'] .
            '-' . $form_state['values']['start_date']['day'], $form_state['values']['end_date']['year'] .
            '-' . $form_state['values']['end_date']['month'] .
            '-' . $form_state['values']['end_date']['day'], $start_time[0] . ':' . $start_time[1], $end_time[0] . ':' . $end_time[1], $form_state['values']['est_miles'], $est_time[0] . ':' . $est_time[1], $form_state['values']['key']))
          {
            throw new Exception('Could not update record!');
          }
          //set the status

          drupal_set_message(t('Your record has been saved.'), 'status');
          $form_state['rebuild'] = FALSE;
          $form_state['redirect'] = url("bkthn/logs/" . $form_state['values']['bkthn_key']);
        } //try
        catch (Exception $e)
        {
          //set the error
          drupal_set_message(t('Your new record could not be saved.  There was an error.'), 'error');
        } //catch
      } //edit
  } //Save
  else
    if ($form_state['values']['op'] == 'Delete')
    {
      if ($form_state['current_form'] == 'bkthn_logs_edit')
      {
        $form_state['rebuild'] = FALSE;
        $form_state['redirect'] = url("bkthn/logs/delete/" . $form_state['values']['key']);
      } //bkthn_vehicles_delete
      else
      {
        try
        {
          $sql = "delete from bkthn_entries ";
          $sql .= "where `key` = %d ";

          if (!db_query($sql, $form_state['values']['key']))
          {
            throw new Exception('Could not delete record!');
          }
          //set the status

          drupal_set_message(t('Your record has been deleted.'), 'status');
          $form_state['rebuild'] = FALSE;
          $form_state['redirect'] = url("bkthn/logs/" . $form_state['values']['bkthn_key']);
        } //try
        catch (Exception $e)
        {
          //set the error
          drupal_set_message(t('Your new record could not be saved.  There was an error.'), 'error');
        } //catch
      } //bkthn_delete
    } //Delete
  else
    if ($form_state['values']['op'] == 'Cancel')
    {
      drupal_set_message(t('Cancelled.'), 'status');
      $form_state['rebuild'] = FALSE;
      $form_state['redirect'] = url("bkthn/logs/" . $form_state['values']['bkthn_key']);
    } //cancel
} //bkthn_logs_form_submit

/**
* Processes the form submit hook
* @param form array of the current form to be processed
* @param form_state by reference array of all post values of the form
* @return array form
*/
function bkthn_logs_form_validate($from, & $form_state)
{

  //don't validate if it's a cancel and not a delete
  if ($form_state['values']['op'] == 'Save')
  {
    //    $name = trim(check_plain($form_state['values']['name']));
    //    if ($name=='') {
    //      form_set_error('Validation', t('Please enter a name'));
    //    }
    if (!preg_match("/^[0-9]{2}\:[0-9]{2}$/", $form_state['values']['est_time']))
    {
      form_set_error('', t('Estimated Travel Time is in HH:MI'));
    }
    unset ($time);
    $time = explode(':', $form_state['values']['est_time']);
    if (intval($time[0]) > 99)
    {
      form_set_error('Validation', t('Est Travel Time Hour should be less than 99'));
    }
    if (intval($time[1]) > 59)
    {
      form_set_error('Validation', t('Est Travel Time Minutes should be less than 60'));
    }
  }
} //bkthn_logs_form_validate
?>
