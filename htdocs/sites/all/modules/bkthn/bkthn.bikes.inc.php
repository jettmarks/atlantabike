<?php

/**
* Generate HTML for all bikes of the current user
* @returns HTML page
*/
function bkthn_bikes_main()
{
  global $user;
  $output = '';

  $output .= "<table>";
  $output .= "<tr>";
  $output .= "<th>Name</th>";
  $output .= "<th>Brand</th>";
  $output .= "<th>Model</th>";
  $output .= "<th>Year</th>";
  $output .= "<th>&nbsp;</th>";
  $output .= "<th>&nbsp;</th>";
  $output .= "</tr>";

  $sql = "select v.`key`, ";
  $sql .= "v.user_key, v.name vehicle_name, brand, model, `year`, ";
  $sql .= "est_mpg_city, est_mpg_hwy, primary_flag ";
  $sql .= "from bkthn_bikes v  ";
  $sql .= "where user_key = %d ";
  $sql .= "order by v.`key` ";

  $result = db_query($sql, $user->uid);

  while ($row = db_fetch_object($result))
  {
    $output .= "<tr>";
    $output .= "<td>" . t($row->vehicle_name) . "</td>";
    $output .= "<td>" . t($row->brand) . "</td>";
    $output .= "<td>" . t($row->model) . "</td>";
    $output .= "<td>" . $row->year . "</td>";
    $output .= "<td>" . l("Edit", "bkthn/bikes/edit/" . $row->key) . "</td>";
    $output .= "<td>" . l("Delete", "bkthn/bikes/delete/" . $row->key) . "</td>";
    $output .= "</tr>";

  }
  $output .= "</table>";
  $output .= l("Add Bike", "bkthn/bikes/add");
  $output .= "<p></p>";
  return $output;
} //function bkthn_bikes_main

/**
* Display the bicycle add new, edit, and delete forms
* @param form_state by reference array of all post values of the form
* @param trans_type string that tells what type of transaction is being performed - add, edit, delete
* @param bkthn_bikes_key integer that identifies the current record to edit or delete. null or 0 when adding a record
* @return array form
*/
function bkthn_bikes_form(& $form_state, $trans_type, $bkthn_bikes_key)
{
  if ($trans_type == 'add')
  {
    $form_state['current_form'] = 'bkthn_bikes_add';

    $form['name'] = array (
      '#type' => 'textfield',
      '#title' => t('Nickname'),

    );

    $form['brand'] = array (
      '#type' => 'textfield',
      '#title' => t('Brand'),

    );
    $form['model'] = array (
      '#type' => 'textfield',
      '#title' => t('Model'),

    );
    $form['year'] = array (
      '#type' => 'textfield',
      '#title' => t('Year'),
      '#size' => 4,

    );
    $form['submit'] = array (
      '#type' => 'submit',
      '#value' => t('Save'),
      '#executes_submit_callback' => TRUE,

    );
    $form['cancel'] = array (
      '#type' => 'button',
      '#value' => t('Cancel'),
      '#executes_submit_callback' => TRUE,

    );

  } //add
  else
    if ($trans_type == 'edit')
    {
      $form_state['current_form'] = 'bkthn_bikes_edit';
      $form['key'] = array (
        '#type' => 'hidden',
        '#value' => $bkthn_bikes_key
      );

      $sql = "select bkthn_vehicle_types_key, user_key, name, brand, model, `year`, est_mpg_city, est_mpg_hwy, primary_flag ";
      $sql .= "from bkthn_bikes ";
      $sql .= "where `key` = %d ";

      $result = db_query($sql, $bkthn_bikes_key);
      $bikes_row = db_fetch_object($result);

      $form['title'] = array (
        '#value' => t('<p>Edit Vehicle ' . t($bikes_row->name) . "</p>"),

      );

      $form['name'] = array (
        '#type' => 'textfield',
        '#title' => t('Nickname'),
        '#default_value' => t($bikes_row->name),

      );

      $form['brand'] = array (
        '#type' => 'textfield',
        '#title' => t('Brand'),
        '#default_value' => t($bikes_row->brand),

      );
      $form['model'] = array (
        '#type' => 'textfield',
        '#title' => t('Model'),
        '#default_value' => t($bikes_row->model),

      );
      $form['year'] = array (
        '#type' => 'textfield',
        '#title' => t('Year'),
        '#size' => 4,
        '#default_value' => $bikes_row->year,

      );

      $form['submit'] = array (
        '#type' => 'submit',
        '#value' => t('Save'),

      );
      $form['delete'] = array (
        '#type' => 'button',
        '#value' => t('Delete'),
        '#executes_submit_callback' => TRUE,

      );
      $form['cancel'] = array (
        '#type' => 'button',
        '#value' => t('Cancel'),
        '#executes_submit_callback' => TRUE,

      );
    } //edit
  else
    if ($trans_type == 'delete')
    {
      $form_state['current_form'] = 'bkthn_bikes_delete';
      $form['key'] = array (
        '#type' => 'hidden',
        '#value' => $bkthn_bikes_key
      );

      $sql = "select bkthn_vehicle_types_key, user_key, name, brand, model, `year`, est_mpg_city, est_mpg_hwy, primary_flag ";
      $sql .= "from bkthn_bikes ";
      $sql .= "where `key` = %d ";

      $result = db_query($sql, $bkthn_bikes_key);
      $row = db_fetch_object($result);

      $form['title'] = array (
        '#value' => t('<p>Delete Vehicle ' . t($row->name) . "</p>"),

      );

      $form['submit'] = array (
        '#type' => 'submit',
        '#value' => t('Delete'),

      );
      $form['cancel'] = array (
        '#type' => 'button',
        '#value' => t('Cancel'),
        '#executes_submit_callback' => TRUE,

      );
    } //delete
  else
  {
    $form_state['current_form'] = 'bkthn_bikes_main';
    $form['logs_main'] = array (
      '#value' => t(bkthn_bikes_main()),

    );
  } //anything else

  return $form;
} //bkthn_bikes_form

/**
* Processes the form validate hook
* @param form array of the current form to be processed
* @param form_state by reference array of all post values of the form
* @return array form
*/
function bkthn_bikes_form_validate($form, & $form_state)
{
  //don't validate if it's a cancel
  if ($form_state['values']['op'] == 'Save')
  {
    $name = trim(check_plain($form_state['values']['name']));
    if ($name == '')
    {
      form_set_error('Validation', t('Please name your ride'));
    }
    //    if ($form_state['values']['bkthn_vehicle_types_key'] == 0) {
    //      form_set_error('Validation', t('Please select the type of ride'));
    //    }
  }
} //function bkthn_bikes_form_validate

/**
* Processes the form submit hook
* @param form array of the current form to be processed
* @param form_state by reference array of all post values of the form
* @return array form
*/
function bkthn_bikes_form_submit($form, & $form_state)
{
  global $user;

  //drupal_set_message(t('Test'),'status');
  if ($form_state['values']['op'] == 'Save')
  {
    if ($form_state['current_form'] == 'bkthn_bikes_add')
    {
      try
      {
        $sql = "insert into bkthn_bikes ";
        $sql .= "(bkthn_vehicle_types_key, user_key, name, brand, model, year, est_mpg_city, est_mpg_hwy, primary_flag) ";
        $sql .= "values ";
        $sql .= "(%d,%d,'%s','%s','%s',%d,%d,%d,%d) ";

        if (!db_query($sql //	                   , $form_state['values']['bkthn_vehicle_types_key']
        , 1, $user->uid, trim($form_state['values']['name']), trim($form_state['values']['brand']), trim($form_state['values']['model']), $form_state['values']['year'], $form_state['values']['est_mpg_city'], null, 0))
        {
          throw new Exception('Could not update record!');
        }
        //set the status

        drupal_set_message(t('Your record has been saved.'), 'status');
        $form_state['rebuild'] = FALSE;
        $form_state['redirect'] = url("bkthn/bikes");
      } //try
      catch (Exception $e)
      {
        //set the error
        drupal_set_message(t('Your new record could not be saved.  There was an error.'), 'error');
      } //catch
    } //Add
    else
      if ($form_state['current_form'] == 'bkthn_bikes_edit')
      {
        try
        {
          $sql = "update bkthn_bikes ";
          $sql .= "set ";
          $sql .= "bkthn_vehicle_types_key = %d, ";
          $sql .= "user_key = %d, ";
          $sql .= "name = '%s', ";
          $sql .= "brand = '%s',  ";
          $sql .= "model = '%s',  ";
          $sql .= "year = %d, ";
          $sql .= "est_mpg_city = %d, ";
          $sql .= "est_mpg_hwy = %d, ";
          $sql .= "primary_flag = %d ";
          $sql .= "where `key` = %d ";

          if (!db_query($sql, $form_state['values']['bkthn_vehicle_types_key'], $user->uid, trim($form_state['values']['name']), trim($form_state['values']['brand']), trim($form_state['values']['model']), $form_state['values']['year'], $form_state['values']['est_mpg_city'], null //est_mpg_hwy
          , 0 //primary_flag
          , $form_state['values']['key']))
          {
            throw new Exception('Could not update record!');
          }
          //set the status

          drupal_set_message(t('Your record has been saved.'), 'status');
          $form_state['rebuild'] = FALSE;
          $form_state['redirect'] = url("bkthn/bikes");
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
      if ($form_state['current_form'] == 'bkthn_bikes_edit')
      {
        $form_state['rebuild'] = FALSE;
        $form_state['redirect'] = url("bkthn/bikes/delete/" . $form_state['values']['key']);
      } //bkthn_bikes_delete
      else
      {
        try
        {
          $sql = "delete from bkthn_bikes ";
          $sql .= "where `key` = %d ";

          if (!db_query($sql, $form_state['values']['key']))
          {
            throw new Exception('Could not delete record!');
          }
          //set the status

          drupal_set_message(t('Your record has been deleted.'), 'status');
          $form_state['rebuild'] = FALSE;
          $form_state['redirect'] = url("bkthn/bikes");
        } //try
        catch (Exception $e)
        {
          //set the error
          drupal_set_message(t('Your new record could not be saved.  There was an error.'), 'error');
        } //catch
      } //bkthn_bikes_delete
    } //Delete
  else
    if ($form_state['values']['op'] == 'Cancel')
    {
      drupal_set_message(t('Cancelled.'), 'status');
      $form_state['rebuild'] = FALSE;
      $form_state['redirect'] = url("bkthn/bikes");
    } //cancel
} //bikes_form_submit
?>