<?php
/*****************************************************************************\
*                                                                            *
*   File name     edit_users.php                                             *
*                                                                            *
*   Description   Edit the user database                                     *
*                                                                            *
*   Notes         Automatically creates the database if it's not present.    *
*                                                                            *
*                 Designed to be easily extensible:                          *
*                 Adding more fields for each user does not require          *
*                 modifying the editor code. Only to add the fields in       *
*                 the database creation code.                                *
*                                                                            *
*                 An admin rights model is used where the level (an          *
*                 integer between 0 and $max_level) denotes rights:          *
*                      0:  no rights                                         *
*                      1:  an ordinary user                                  *
*                      2+: admins, with increasing rights.   Designed to     *
*                          allow more granularity of admin rights, for       *
*                          example by having booking admins, user admins     *
*                          snd system admins.  (System admins might be       *
*                          necessary in the future if, for example, some     *
*                          parameters currently in the config file are      *
*                          made editable from MRBS)                          *
*                                                                            *
*                 Only admins with at least user editing rights (level >=    *
*                 $min_user_editing_level) can edit other users, and they    *
*                 cannot edit users with a higher level than themselves      *
*                                                                            *
*                 To do:                                                     *
*                     - Localisability                                       *
*                                                                            *
*   History                                                                  *
*                 2003/12/29 JFL Created this file                           *
*                                                                            *
\*****************************************************************************/

// $Id$

require "defaultincludes.inc";

// Get non-standard form variables
$Action = get_form_var('Action', 'string');
$Id = get_form_var('Id', 'int');
$password0 = get_form_var('password0', 'string');
$password1 = get_form_var('password1', 'string');
$invalid_email = get_form_var('invalid_email', 'int');
$name_empty = get_form_var('name_empty', 'int');
$name_not_unique = get_form_var('name_not_unique', 'int');
$taken_name = get_form_var('taken_name', 'string');
$pwd_not_match = get_form_var('pwd_not_match', 'string');
$pwd_invalid = get_form_var('pwd_invalid', 'string');
$ajax = get_form_var('ajax', 'int');  // Set if this is an Ajax request
$datatable = get_form_var('datatable', 'int');  // Will only be set if we're using DataTables
//$std_query_string = "area=$area&day=$day&month=$month&year=$year";
$std_query_string1 = "room=$room&day=$day&month=$month&year=$year";

$user = getUserName();
$required_level = (isset($max_level) ? $max_level : 4);
$is_superadmin = (authGetUserLevel($user) >= 4);
$is_areaadmin = (authGetUserLevel($user) == 3 );
$is_roomadmin = (authGetUserLevel($user) == 2);
$is_admin = (authGetUserLevel($user) >= $required_level);
$capability = array();

// Validates that the password conforms to the password policy
// (Ideally this function should also be matched by client-side
// validation, but unfortunately JavaScript's native support for Unicode
// pattern matching is very limited.   Would need to be implemented using
// an add-in library).
function validate_password($password)
{
  global $pwd_policy;
          
  if (isset($pwd_policy))
  {
    // Set up regular expressions.  Use p{Ll} instead of [a-z] etc.
    // to make sure accented characters are included
    $pattern = array('alpha'   => '/\p{L}/',
                     'lower'   => '/\p{Ll}/',
                     'upper'   => '/\p{Lu}/',
                     'numeric' => '/\p{N}/',
                     'special' => '/[^\p{L}|\p{N}]/');
    // Check for conformance to each rule                 
    foreach($pwd_policy as $rule => $value)
    {
      switch($rule)
      {
        case 'length':
          if (utf8_strlen($password) < $pwd_policy[$rule])
          {
            return FALSE;
          }
          break;
        default:
          // turn on Unicode matching
          $pattern[$rule] .= 'u';

          $n = preg_match_all($pattern[$rule], $password, $matches);
          if (($n === FALSE) || ($n < $pwd_policy[$rule]))
          {
            return FALSE;
          }
          break;
      }
    }
  }
  
  // Everything is OK
  return TRUE;
}


// Get the type that should be used with get_form_var() for
// a field which is a member of the array returned by get_field_info()
function get_form_var_type($field)
{
  // "Level" is an exception because we've forced the value to be a string
  // so that it can be used in an associative aeeay
  if ($field['name'] == 'level')
  {
    return 'string';
  }
  switch($field['nature'])
  {
    case 'character':
      $type = 'string';
      break;
    case 'integer':
      $type = 'int';
      break;
    // We can only really deal with the types above at the moment
    default:
      $type = 'string';
      break;
  }
  return $type;
}


function output_row(&$row)
{
  global $ajax, $json_data;
  global $level, $min_user_editing_level, $user;
  global $selectedarea, $selectedroom;
  global $fields, $ignore_columns, $select_options,$select_option;
  
  $values = array();
  
  // First column, which is the name
  $html_name = htmlspecialchars($row['name']);
  // You can only edit a user if you have sufficient admin rights, or else if that user is yourself
  if (($level >= $min_user_editing_level) || (strcasecmp($row['name'], $user) == 0))
  {
    $link = htmlspecialchars(this_page()) . "?Action=Edit&amp;Id=" . $row['id'];
    $values[] = "<a title=\"$html_name\" href=\"$link\">$html_name</a>";
  }
  else
  {
    $values[] = "<span class=\"normal\" title=\"$html_name\">$html_name</span>";
  }
    
  // Other columns
  foreach ($fields as $field)
  {
    $key = $field['name'];
    
    
    if (!in_array($key, $ignore_columns))
    {
      $col_value = $row[$key];
      switch($key)
      {
        // special treatment for some fields
        case 'level':
          // the level field contains a code and we want to display a string
          // (but we put the code in a span for sorting)
          $values[] = "<span title=\"$col_value\"></span>" .
                      "<div class=\"string\">" . get_vocab("level_$col_value") . "</div>";
			
          break;
        case 'email':
          // we don't want to truncate the email address
          $values[] = "<div class=\"string\">" . htmlspecialchars($col_value) . "</div>";
			     
     break;
        case 'Area Name':
          // the level field contains a code and we want to display a string
          // (but we put the code in a span for sorting)
          $values[] = "<div class=\"string\">" . htmlspecialchars($col_value) . "</div>";
			//echo $field -> $key;
	 //echo $values[];
          break;
	
        case 'Room Names':
          // the level field contains a code and we want to display a string
          // (but we put the code in a span for sorting)

         // $values[] = $col_value . "\">" 
                        //$col_value. "</div>";

$values[] = //"<span title=\"$col_value.\"></span>" ."<div class=\"string\">" . get_vocab("$col_value") . "</div>";
"<div class=\"string\">" . htmlspecialchars($col_value) . "</div>";
		 //mrbsAddSelectedArea($name, &$error);
	
	break;
        default:
          // Where there's an associative array of options, display
          // the value rather than the key
          if (isset($select_options["users.$key"]) &&
              is_assoc($select_options["users.$key"]))
          {
            if (isset($select_options["users.$key"][$row[$key]]))
            {
              $col_value = $select_options["users.$key"][$row[$key]];
            }
            else
            {
              $col_value = '';
            }
            $values[] = "<div class=\"string\">" . htmlspecialchars($col_value) . "</div>";
          }
          elseif (($field['nature'] == 'boolean') || 
              (($field['nature'] == 'integer') && isset($field['length']) && ($field['length'] <= 2)) )
          {
            // booleans: represent by a checkmark
            $values[] = (!empty($col_value)) ? "<img src=\"images/check.png\" alt=\"check mark\" width=\"16\" height=\"16\">" : "&nbsp;";
          }
          elseif (($field['nature'] == 'integer') && isset($field['length']) && ($field['length'] > 2))
          {
            // integer values
            $values[] = $col_value;
          }
          else
          {
             // strings
            $values[] = "<div class=\"string\" title=\"" . htmlspecialchars($col_value) . "\">" .
                        htmlspecialchars($col_value) . "</div>";
          }
          break;
      }  // end switch
    }
  }  // end foreach

/*foreach ($fields as $field)
  {
    $key = $field['name'];
    
    
    if (!in_array($key, $ignore_columns))
    {
      $col_value = $row[$key];
      switch($key) {
     		case 'Room Names':
          // the level field contains a code and we want to display a string
          // (but we put the code in a span for sorting)

          $values[] = "<div class=\"string\" title=\"" . htmlspecialchars($col_value) . "\">" .
                        htmlspecialchars($col_value) . "</div>";

//"<span title=\"$col_value\"></span>" ."<div class=\"string\">" . get_vocab("$col_value") . "</div>";
//"<div class=\"string\">" . htmlspecialchars($col_value) . "</div>";
		 //mrbsAddSelectedArea($name, &$error);
	
	break;
	}
    }
}
*/
  if ($ajax)
  {
    $json_data['aaData'][] = $values;
  }
  else
  {
    echo "<tr>\n<td>\n";
    echo implode("</td>\n<td>", $values);
    echo "</td>\n</tr>\n";
  }
}

// Set up for Ajax.   We need to know whether we're capable of dealing with Ajax
// requests, which will only be if (a) the browser is using DataTables and (b)
// we can do JSON encoding.    We also need to initialise the JSON data array.
$ajax_capable = $datatable && function_exists('json_encode');

if ($ajax)
{
  $json_data['aaData'] = array();
}

// Get the information about the fields in the users table
$fields = sql_field_info($tbl_users);

$nusers = sql_query1("SELECT COUNT(*) FROM $tbl_users");

/*---------------------------------------------------------------------------*\
|                         Authenticate the current user                         |
\*---------------------------------------------------------------------------*/

$initial_user_creation = 0;

if ($nusers > 0)
{
  $user = getUserName();
  $level = authGetUserLevel($user);
  // Check the user is authorised for this page
  checkAuthorised();
}
else 
// We've just created the table.   Assume the person doing this IS an administrator
// and then send them through to the screen to add the first user (which we'll force
// to be an admin)
{
  $initial_user_creation = 1;
  if (!isset($Action))   // second time through it will be set to "Update"
  {
    $Action = "Add";
    $Id = -1;
  }
  $level = $max_level;
  $user = "";           // to avoid an undefined variable notice
}


/*---------------------------------------------------------------------------*\
|             Edit a given entry - 1st phase: Get the user input.             |
\*---------------------------------------------------------------------------*/

if($is_superadmin || $is_areaadmin) {

if (isset($Action) && ( ($Action == "Edit") or ($Action == "Add") ))
{
  
  if ($Id >= 0) /* -1 for new users, or >=0 for existing ones */
  {
    // If it's an existing user then get the data from the database
    $result = sql_query("select * from $tbl_users where id=$Id");
    $data = sql_row_keyed($result, 0);
    sql_free($result);
  }
  if (($Id == -1) || (!$data)) 
  {
    // Otherwise try and get the data from the query string, and if it's
    // not there set the default to be blank.  (The data will be in the 
    // query string if there was an error on validating the data after it
    // had been submitted.   We want to preserve the user's original values
    // so that they don't have to re-type them).
    foreach ($fields as $field)
    {
      $type = get_form_var_type($field);
      $value = get_form_var($field['name'], $type);
      $data[$field['name']] = (isset($value)) ? $value : "";
    }
  }

  /* First make sure the user is authorized */
  if (!$initial_user_creation && !auth_can_edit_user($user, $data['name']))
  {
    showAccessDenied(0, 0, 0, "", "");
    exit();
  }

  print_header(0, 0, 0, 0, "");
  
  if ($initial_user_creation == 1)
  {
    print "<h3>" . get_vocab("no_users_initial") . "</h3>\n";
    print "<p>" . get_vocab("no_users_create_first_admin") . "</p>\n";
  }
  
  print "<div id=\"form_container\">";
  print "<form id=\"form_edit_users\" method=\"post\" action=\"" . htmlspecialchars(this_page()) . "\">\n";
    ?>
        <fieldset class="admin">
        <legend><?php echo (($Action == "Edit") ? get_vocab("edit_user") : get_vocab("add_new_user"));?></legend>
        <div id="edit_users_input_container">
          <?php
          // Find out how many admins are left in the table - it's disastrous if the last one is deleted,
          // or admin rights are removed!
          if ($Action == "Edit")
          {
            $n_admins = sql_query1("select count(*) from $tbl_users where level < $max_level");
            $editing_last_admin = ($n_admins <= 1) && ($data['level'] < $max_level);
          }
          else
          {
            $editing_last_admin = FALSE;
          }
          
          foreach ($fields as $field)
          {
            $key = $field['name'];
            $params = array('label' => get_loc_field_name($tbl_users, $key) . ':',
                            'name'  => VAR_PREFIX . $key,
                            'value' => $data[$key]);
            if (isset($maxlength["users.$key"]))
            {
              $params['maxlength'] = $maxlength["users.$key"];
            }
            // First of all output the input for the field
            // The ID field cannot change; The password field must not be shown.
            switch($key)
            {
              case 'id':
                echo "<input type=\"hidden\" name=\"Id\" value=\"$Id\">\n";
                break;
              case 'password_hash':
                echo "<input type=\"hidden\" name=\"" . $params['name'] ."\" value=\"". htmlspecialchars($params['value']) . "\">\n";
                break;
              default:
                echo "<div>\n";
                switch($key)
                {
                  case 'level':
                    // Work out whether the level select input should be disabled (NB you can't make a <select> readonly)
                    // We don't want the user to be able to change the level if (a) it's the first user being created or
                    // (b) it's the last admin left or (c) they don't have admin rights
                    $params['disabled'] = $initial_user_creation || $editing_last_admin || ($level < $min_user_editing_level);
                    // Only display options up to and including one's own level (you can't upgrade yourself).
                    // If you're not some kind of admin then the select will also be disabled.
                    // (Note - disabling individual options doesn't work in older browsers, eg IE6)
                    $params['options'] = array();     
                    for ($i=0; $i<$level; $i++)
                    {
                      $params['options'][$i] = get_vocab("level_$i");
                      // Work out which option should be selected by default:
                      //   if we're editing an existing entry, then it should be the current value;
                      //   if we're adding the very first entry, then it should be an admin;
                      //   if we're adding a subsequent entry, then it should be an ordinary user;
                      if ( (($Action == "Edit") && ($i == $data[$key])) ||
                           (($Action == "Add") && $initial_user_creation && ($i == $max_level)) ||
                           (($Action == "Add") && !$initial_user_creation && ($i == 1)) )
                      {
                        $params['value'] = $i;
                      }
                    }
                    $params['force_assoc'] = TRUE;
                    generate_select($params);
                    break;
                  case 'name':
                    // you cannot change a username (even your own) unless you have user editing rights
                    $params['disabled'] = ($level < $min_user_editing_level);
                    $params['mandatory'] = TRUE;
                    generate_input($params);
                    break;
                  case 'email':
                    $params['type'] = 'email';
                    $params['attributes'] = 'multiple';
                    generate_input($params);
                    break;
		   case 'role_name':
                    // you cannot change a username (even your own) unless you have user editing rights
                    //$params['disabled'] = ($level < $min_user_editing_level);
                    $params['mandatory'] = TRUE;
                    generate_input($params);
                    break;
                  default:    
                    // Output a checkbox if it's a boolean or integer <= 2 bytes (which we will
                    // assume are intended to be booleans)
                    if (($field['nature'] == 'boolean') || 
                        (($field['nature'] == 'integer') && isset($field['length']) && ($field['length'] <= 2)) )
                    {
                      generate_checkbox($params);
                    }
                    // Output a textarea if it's a character string longer than the limit for a
                    // text input
                   /* elseif (($field['nature'] == 'character') && isset($field['length']) && ($field['length'] > $text_input_max))
                    {
                      $params['attributes'] = array('rows="8"', 'cols="40"');
                      generate_textarea($params);   
                    }
                    // Otherwise output a text input
                    else
                    {
                      $params['field'] = "users.$key";
                      generate_input($params);
                    }*/
                    break;
                } // end switch
                echo "</div>\n";
            } // end switch
            
            
            // Then output any error messages associated with the field
            // except for the password field which is a special case
            switch($key)
            {
              case 'email':
                if (!empty($invalid_email))
                {
                  echo "<p class=\"error\">" . get_vocab('invalid_email') . "</p>\n";
                }
                break;
              case 'name':
                if (!empty($name_not_unique))
                {
                  echo "<p class=\"error\">'" . htmlspecialchars($taken_name) . "' " . get_vocab('name_not_unique') . "<p>\n";
                }
                if (!empty($name_empty))
                {
                  echo "<p class=\"error\">" . get_vocab('name_empty') . "<p>\n";
                }
                break;
            }
                     
          } // end foreach


//AREA LIST
if (isset($area))
{
  $res = sql_query("SELECT area_name, custom_html FROM $tbl_area WHERE id=$area LIMIT 1");
  if (! $res)
  {
    trigger_error(sql_error(), E_USER_WARNING);
    fatal_error(FALSE, get_vocab("fatal_db_error"));
  }
  if (sql_count($res) == 1)
  {
    $row = sql_row_keyed($res, 0);
    $area_name = $row['area_name'];
    $custom_html = $row['custom_html'];
  }
  sql_free($res);
}

if($is_superadmin) {
echo "<div id=\"area_form\">\n\n";
$sql = "SELECT id, area_name, disabled
          FROM $tbl_area
      ORDER BY disabled, area_name";
$res = sql_query($sql);
//$sql_insert_temp = 
$areas_defined = $res && (sql_count($res) > 0);
//if($is_superadmin) {
	if (!$areas_defined) {
  echo "<p>" . get_vocab("noareas") . "</p>\n";
	}
else
{
  // Build an array with the area info and also see if there are going
  // to be any areas to display (in other words rooms if you are not an
  // admin whether any areas are enabled)
  $areas = array();
  $n_displayable_areas = 0;
  for ($i = 0; ($row = sql_row_keyed($res, $i)); $i++)
  {
    $areas[] = $row;
    if ($is_superadmin || !$row['disabled'])
    {
      $n_displayable_areas++;
    }
  }

  if ($n_displayable_areas == 0)
  {
    echo "<p>" . get_vocab("noareas_enabled") . "</p>\n";
  }
  else
  {
    // If there are some areas displayable, then show the area form
    echo "<form id=\"areaChangeForm\" method=\"get\" action=\"" . htmlspecialchars(this_page()) . "\">\n";
    echo "<fieldset>\n";
    //echo "<fieldset>\n";
   // echo "<legend></legend>\n";
  
    // The area selector
    echo "<label id=\"area_label\" for=\"area_select\">" . get_vocab("Area List") . ":</label>\n";
    echo "<select class=\"area_select\" id=\"area_select\" name=\"area[]\" multiple = \"multiple\">";
//onchange=\"this.form.submit()\    
if ($is_superadmin)
    {
      if ($areas[0]['disabled'])
      {
        $done_change = TRUE;
        echo "<optgroup label=\"" . get_vocab("disabled") . "\">\n";
      }
      else
      {
        $done_change = FALSE;
        echo "<optgroup label=\"" . get_vocab("enabled") . "\">\n";
      }
    }
    foreach ($areas as $a)
    {
      if ($is_superadmin || !$a['disabled'])

      {
        if ($is_superadmin && !$done_change && $a['disabled'])
        {
          echo "</optgroup>\n";
          echo "<optgroup label=\"" . get_vocab("disabled") . "\">\n";
          $done_change = TRUE;
        }
        $selected = ($a['id'] == $area) ? "selected=\"selected\"" : "";
        echo "<option $selected value=\"".$a['area_name']. "\">" . htmlspecialchars($a['area_name']) . "</option>";
      }
    }


    if ($is_superadmin)
    {
      echo "</optgroup>\n";
    }


    echo "</select>\n";
}
    echo "</fieldset>\n";
    echo "</form>\n";
  }
}


if (isset($room))
{
  $res = sql_query("SELECT room_name, custom_html FROM $tbl_room WHERE id=$room LIMIT 1");
  if (! $res)
  {
    trigger_error(sql_error(), E_USER_WARNING);
    fatal_error(FALSE, get_vocab("fatal_db_error"));
  }
  if (sql_count($res) == 1)
  {
    $row = sql_row_keyed($res, 0);
    $room_name = $row['room_name'];
    $custom_html = $row['custom_html'];
  }
  sql_free($res);
}


$sql = "SELECT id, room_name, disabled
          FROM $tbl_room
      ORDER BY disabled, room_name";
//$res = sql_query("SELECT * FROM $tbl_room WHERE area_id=$area ORDER BY sort_key");
$res = sql_query($sql);
$rooms_defined = $res && (sql_count($res) > 0);
if (! $res)
    {
      trigger_error(sql_error(), E_USER_WARNING);
      fatal_error(FALSE, get_vocab("fatal_db_error"));
    }

if (!$rooms_defined)
{
  echo "<p>" . get_vocab("norooms") . "</p>\n";
}
else
{
$fields = sql_field_info($tbl_room);
  // Build an array with the area info and also see if there are going
  // to be any areas to display (in other words rooms if you are not an
  // admin whether any areas are enabled)
  $rooms = array();
  $n_displayable_rooms = 0;
  for ($i = 0; ($row = sql_row_keyed($res, $i)); $i++)
  {
    $rooms[] = $row;
    if ($is_areaadmin || !$row['disabled'])
    {
      $n_displayable_rooms++;
    }
  }

  if ($n_displayable_rooms == 0)
  {
    echo "<p>" . get_vocab("norooms_enabled") . "</p>\n";
  }
  else
  {
    // If there are some rooms displayable, then show the room form
    echo "<form id=\"roomChangeForm\" method=\"get\" action=\"" . htmlspecialchars(this_page()) . "\">\n";
    echo"<fieldset>";
     
    // The rooms selector
    echo "<label id=\"room_label\" for=\"room_select\">" . get_vocab("Room list") . ":</label>\n";
    echo "<select class=\"room_select\" id=\"room_select\" name=\"room[]\" multiple = \"multiple\">";
//onchange=\"this.form.submit()\    
if ($is_areaadmin)
    {
      if ($rooms[0]['disabled'])
      {
        $done_change = TRUE;
        echo "<optgroup label=\"" . get_vocab("disabled") . "\">\n";
      }
      else
      {
        $done_change = FALSE;
        echo "<optgroup label=\"" . get_vocab("enabled") . "\">\n";
      }
    }
    foreach ($rooms as $r)
    {
      if ($is_areaadmin || !$r['disabled'])
      {
        if ($is_areaadmin && !$done_change && $r['disabled'])
        {
          echo "</optgroup>\n";
          echo "<optgroup label=\"" . get_vocab("disabled") . "\">\n";
          $done_change = TRUE;
        }
        $selected = ($r['area_id'] == $area) ? "selected=\"selected\"" : "";
        echo "<option $selected value=\"". $r['room_name']. "\">" . htmlspecialchars($r['room_name']) . "</option>";
        
      }
    }


    if ($is_areaadmin)
    {
      echo "</optgroup>\n";
    }


    echo "</select>\n";


   echo "</fieldset>";
    echo "</form>\n";
  }
}
//}
if (isset($work))
{
  $res = sql_query("SELECT capabilities, custom_html FROM $tbl_work WHERE id=$work LIMIT 1");
  if (! $res)
  {
    trigger_error(sql_error(), E_USER_WARNING);
    fatal_error(FALSE, get_vocab("fatal_db_error"));
  }
  if (sql_count($res) == 1)
  {
    $row = sql_row_keyed($res, 0);
    $capabilities = $row['capabilities'];
    $custom_html = $row['custom_html'];
  }
  sql_free($res);
}
//Functionality
/*if($is_superadmin) {
echo "<h2>" . get_vocab("Functionalities") . "</h2>\n";
if (!empty($error))
{
  echo "<p class=\"error\">" . get_vocab($error) . "</p>\n";
}
}*/

/*if ($is_superadmin)
{
  // New area form
  ?>
  <form id="add_capability" class="form_admin" action="addcapability.php" method="post">
    <fieldset>
    <legend><?php echo get_vocab("Add Functionality") ?></legend>
        
      <input type="hidden" name="type" value="work">

      <div>
        <label for="capabilities"><?php echo get_vocab("name") ?>:</label>
        <input type="text" id="capabilities" name="name" maxlength="<?php echo $maxlength['work.capabilities'] ?>">
      </div>
          
      <div>
        <input type="submit" class="submit" value="<?php echo get_vocab("Add Capabilities") ?>">
      </div>

    </fieldset>
  </form>
*/

/*if ($is_superadmin)
{
  // New area form
  ?>
<form id="add_capability" class="form_admin" action="role.php" method="post">
    <fieldset>
    <legend><?php echo get_vocab("Roles") ?></legend>
        
      <input type="hidden" name="type" value="work">

      <div>
        <label for="capabilities"><?php echo get_vocab("name") ?>:</label>
        <input type="text" id="save" name="name" maxlength="<?php echo $maxlength['roles.capability'] ?>">
      </div>
          
      <div>
        <input type="submit" class="submit" value="<?php echo get_vocab("Save") ?>">
      </div>

    </fieldset>
  </form>
  <?php
}*/
echo "</div>";  // capability form

echo "<div id=\"capabilities_form\">\n";
echo "<fieldset>\n";
$sql = "SELECT cid, capabilities
          FROM $tbl_work
      ORDER BY capabilities";
$res = sql_query($sql);
$options_defined = $res && (sql_count($res) > 0);
if (!$options_defined)
{
  echo "<p>" . get_vocab("no values") . "</p>\n";
}
else
{
  // Build an array with the area info and also see if there are going
  // to be any areas to display (in other words rooms if you are not an
  // admin whether any areas are enabled)

  $works = array();
  $n_displayable_options = 0;
  echo "<label id=\"capability_label\" for=\"capability_select\">" . get_vocab("Capabilities") . ":</label>\n";
  echo "<select class=\"room_select\" id=\"room_select\" name=\"capability[]\" multiple = \"multiple\">";
  echo "<fieldset>\n";
  //echo "<option $selected value=\"". $c['cid']. "\">" . htmlspecialchars($c['capabilities']) . "</option>";
  for ($i = 0; ($works = sql_row_keyed($res, $i)); $i++)
  {
    $works[] = $row;
    if ($is_admin || !$row['disabled'])
    {
      $n_displayable_options++;
    }
   /* $params = array(  'label' => $row['works'],
                      'name'          => 'works',
                      'options'       => $work,
                      'force_assoc'   => TRUE,
                      'value'         => $row['works'],
                      'disabled'      => $disabled
                      );
   generate_checkbox($params);*/
   echo "<option $selected value=\"". $works['capabilities']. "\">" . htmlspecialchars($works['capabilities']) . "</option>";
  }
//echo "<fieldset>\n";
  if ($n_displayable_options == 0)
  {
    echo "<p>" . get_vocab("not_enabled") . "</p>\n";
  }
  else
  {
    // If there are some areas displayable, then show the area form
    echo "<form id=\"capabilitiesChangeForm\" method=\"get\" action=\"" . htmlspecialchars(this_page()) . "\">\n";
   // echo "<fieldset>\n";
    //echo "<legend></legend>\n";
  
      
if ($is_admin)
    {
      if ($works[0]['disabled'])
      {
        $done_change = TRUE;
        echo "<optgroup label=\"" . get_vocab("disabled") . "\">\n";
	echo "<div>";
      }
      else
      {
        $done_change = FALSE;
        echo "<optgroup label=\"" . get_vocab("enabled") . "\">\n";
      }
    }
    foreach ($works as $c)
    {
      if ($is_admin || !$c['disabled'])
      {
        if ($is_admin && !$done_change && $c['disabled'])
        {
          echo "</optgroup>\n";
          echo "<optgroup label=\"" . get_vocab("disabled") . "\">\n";
          $done_change = TRUE;
        }
        $selected = ($c['cid'] == $work) ? "selected=\"selected\"" : "";
	/*$params = array(  'label' => $row['works'],
                      'name'          => 'new_works',
                      'options'       => $work,
                      'force_assoc'   => TRUE,
                      'value'         => $row['works'],
                      'disabled'      => $disabled,
                      'create_hidden' => FALSE);
    	generate_checkbox_group($params);*/
        echo "<option $selected value=\"". $c['cid']. "\">" . htmlspecialchars($c['capabilities']) . "</option>";
      }
    }


    if ($is_admin)
    {
      echo "</optgroup>\n";
    }


    echo "</select>\n";
	//echo "</fieldset>\n";
    echo "</fieldset>\n";
    echo "</form>\n";
    //echo "</fieldset>\n";
  }
}
 


// Now the custom HTML
echo "<div id=\"custom_html\">\n";
// no htmlspecialchars() because we want the HTML!
echo (!empty($custom_html)) ? "$custom_html\n" : "";
echo "</div>\n";




//output_trailer();

	if($level>3){
	//$parameter = "add entry";
	//if(in_array($parameter, $capability, TRUE)){
          print "<div><p>" . get_vocab("password_twice") . "...</p></div>\n";

          for ($i=0; $i<2; $i++)
          {
            print "<div>\n";
            print "<label for=\"password$i\">" . get_vocab("users.password") . ":</label>\n";
            print "<input type=\"password\" id=\"password$i\" name=\"password$i\" value=\"\">\n";
            print "</div>\n";
          }
          
          // Now do any password error messages
          if (!empty($pwd_not_match))
          {
            echo "<p class=\"error\">" . get_vocab("passwords_not_eq") . "</p>\n";
          }
          if (!empty($pwd_invalid))
          {
            echo "<p class=\"error\">" . get_vocab("password_invalid") . "</p>\n";
            if (isset($pwd_policy))
            {
              echo "<ul class=\"error\">\n";
              foreach ($pwd_policy as $rule => $value)
              {
                echo "<li>$value " . get_vocab("policy_" . $rule) . "</li>\n";
              }
              echo "</ul>\n";
            }
          }
      }    
          if ($editing_last_admin)
          {
            echo "<p><em>(" . get_vocab("warning_last_admin") . ")</em></p>\n";
          }
          ?>

          <input type="hidden" name="Action" value="Update">    
          <input class="submit default_action" type="submit" value="<?php echo(get_vocab("Save")); ?>">
          
        </div>
      </fieldset>
      </form>






      <?php
      /* Administrators get the right to delete users, but only those at the same level as them or lower */
      if (($Id >= 0) && ($level >= $min_user_editing_level) && ($level >= $data['level'])) 
      {
        echo "<form id=\"form_delete_users\" method=\"post\" action=\"" . htmlspecialchars(this_page()) . "\">\n";
        echo "<div>\n";
        echo "<input type=\"hidden\" name=\"Action\" value=\"Delete\">\n";
        echo "<input type=\"hidden\" name=\"Id\" value=\"$Id\">\n";
        echo "<input class=\"submit\" type=\"submit\" " . 
              (($editing_last_admin) ? "disabled=\"disabled\"" : "") .
              "value=\"" . get_vocab("delete_user") . "\">\n";
        echo "</div>\n";
        echo "</form>\n";
      }
      // otherwise (ie when adding, or else editing when not an admin) give them a cancel button
      // which takes them back to the user list and does nothing
      else
      {
        echo "<form id=\"form_delete_users\" method=\"post\" action=\"" . htmlspecialchars(this_page()) . "\">\n";
        echo "<div>\n";
        echo "<input class=\"submit\" type=\"submit\" value=\"" . get_vocab("back") . "\">\n";
        echo "</div>\n";
        echo "</form>\n";
      }
	
?>
      </div>
<?php


  // Print footer and exit
  output_trailer();
  exit;
//}
//}

/*---------------------------------------------------------------------------*\
|             Edit a given entry - 2nd phase: Update the database.            |
\*---------------------------------------------------------------------------*/

if (isset($Action) && ($Action == "Update"))
{
  // If you haven't got the rights to do this, then exit
  $my_id = sql_query1("SELECT id FROM $tbl_users WHERE name='".sql_escape($user)."' LIMIT 1");
  if (($level < $min_user_editing_level) && ($Id != $my_id ))
  {
    Header("Location: edit_users.php");
    exit;
  }
  
  // otherwise go ahead and update the database
  else
  {
    $values = array();
    $q_string = ($Id >= 0) ? "Action=Edit" : "Action=Add";
    foreach ($fields as $field)
    {
	//echo "adding...................................................................................................";
      $fieldname = $field['name'];
	echo "fieldname: ".$fieldname;
      $type = get_form_var_type($field);
      if ($fieldname == 'id')
      {
        // id: don't need to do anything except add the id to the query string;
        // the field itself is auto-incremented
        $q_string .= "&Id=$Id";
        continue; 
      }
      if($fieldname == "Area Name"){
	$values[$fieldname] = "";
        //echo "fieldname: ".$fieldname;
	//echo "trying to get area selected option:\n";
	foreach($_POST['area'] as $selected_options) {
		//echo "area selected_options: ".$selected_options."\n";
		$values[$fieldname] = $values[$fieldname].$selected_options.",";
		//echo "In Area name\n";
	}
	continue;
      }

	if($fieldname == "Room Names"){
	//echo "trying to get selected room option:\n";
	$values[$fieldname] = "";
      //  echo $field-> $values;
        //echo "room selected_option: ".$selected_option."\n";
	foreach($_POST['room'] as $selected_option) {
                
		//echo "room selected_options: ".$selected_option."\n";
		$values[$fieldname] = $values[$fieldname].$selected_option.",";
		//echo "Hola\n";
	}
	continue;
        }

	//if($fieldname == "role_name"){
	//$values[$fieldname] = "";
    
	
	//}
	//continue;
        //}

	if($fieldname == "capability"){
	//echo "trying to get selected room option:\n";
	$values[$fieldname] = "";
      //  echo $field-> $values;
        //echo "room selected_option: ".$selected_option."\n";
	foreach($_POST['capability'] as $selected_option) {
                
	echo "selected_options: ".$selected_option."\n";
		$values[$fieldname] = $values[$fieldname].$selected_option.",";
		//echo "Hola\n";
	}
	continue;
        }
      

      // first, get all the other form variables and put them into an array, $values, which 
      // we will use for entering into the database assuming we pass validation
      $values[$fieldname] = get_form_var(VAR_PREFIX. $fieldname, $type);
      // Truncate the field to the maximum length as a precaution.1111111111
      if (isset($maxlength["users.$fieldname"]))
      {
        $values[$fieldname] = utf8_substr($values[$fieldname], 0, $maxlength["users.$fieldname"]);
      }
      // we will also put the data into a query string which we will use for passing
      // back to this page if we fail validation.   This will enable us to reload the
      // form with the original data so that the user doesn't have to
      // re-enter it.  (Instead of passing the data in a query string we
      // could pass them as session variables, but at the moment MRBS does
      // not rely on PHP sessions).
      switch ($fieldname)
      {
        // some of the fields get special treatment
        case 'name':
          // name: convert it to lower case
          $q_string .= "&$fieldname=" . urlencode($values[$fieldname]);
          $values[$fieldname] = utf8_strtolower($values[$fieldname]);
          break;
        case 'password_hash':
          // password: if the password field is blank it means
          // that the user doesn't want to change the password
          // so don't do anything; otherwise calculate the hash.
          // Note: we don't put the password in the query string
          // for security reasons.
          if (!empty($password0))
          {
            if (PasswordCompat\binary\check())
            {
              $hash = password_hash($password0, PASSWORD_DEFAULT);
            }
            else
            {
              $hash = md5($password0);
            }
            $values[$fieldname] = $hash;
          }
          break;
        case 'level':
          // level:  set a safe default (lowest level of access)
          // if there is no value set
          $q_string .= "&$fieldname=" . $values[$fieldname];
          if (!isset($values[$fieldname]))
          {
            $values[$fieldname] = 0;
          }
          // Check that we are not trying to upgrade our level.    This shouldn't be possible
          // but someone might have spoofed the input in the edit form
          if ($values[$fieldname] > $level)
          {
            Header("Location: edit_users.php");
            exit;
          }
          break;
        case "Area Name":
          //$q_string .= "&$fieldname=" . urlencode($values[$fieldname]);
          $values[$fieldname] = utf8_strtolower($values[$fieldname]);
          break;
        case "Room Names":
          $q_string .= "&$fieldname=" . urlencode($values[$fieldname]);
          $values[$fieldname] = utf8_strtolower($values[$fieldname]);
          break;
	case "role_name":
          $q_string .= "&$fieldname=" . urlencode($values[$fieldname]);
          $values[$fieldname] = utf8_strtolower($values[$fieldname]);
          break;
	case "capability":
          $q_string .= "&$fieldname=" . urlencode($values[$fieldname]);
          $values[$fieldname] = utf8_strtolower($values[$fieldname]);
          break;
        default:
          $q_string .= "&$fieldname=" . urlencode($values[$fieldname]);
          break;
      }
	/*echo "values = ";
	foreach ($values as $name=>$value) {
		echo $value;
	}*/
    }

    // Now do some form validation
    $valid_data = TRUE;
    foreach ($values as $fieldname => $value)
    { echo "$fieldname , $value done <br>";
      switch ($fieldname)
      {
        case 'name':
          // check that the name is not empty
          if (empty($value))
          {
            $valid_data = FALSE;
            $q_string .= "&name_empty=1";
          }
          // Check that the name is unique.
          // If it's a new user, then to check to see if there are any rows with that name.
          // If it's an update, then check to see if there are any rows with that name, except
          // for that user.
          $query = "SELECT id FROM $tbl_users WHERE name='" . sql_escape($value) . "'";
          if ($Id >= 0)
          {
            $query .= " AND id!='$Id'";
          }
          $query .= " LIMIT 1";  // we only want to know if there is at least one instance of the name
          $result = sql_query($query);
          if (sql_count($result) > 0)
          {
            $valid_data = FALSE;
            $q_string .= "&name_not_unique=1";
            $q_string .= "&taken_name=$value";
          }
          break;
        case 'password':
          // check that the two passwords match
          if ($password0 != $password1)
          {
            $valid_data = FALSE;
            $q_string .= "&pwd_not_match=1";
          }
          // check that the password conforms to the password policy
          // if it's a new user (Id < 0), or else it's an existing user
          // trying to change their password
          if (($Id <0) || !empty($password0))
          {
            if (!validate_password($password0))
            {
              $valid_data = FALSE;
              $q_string .= "&pwd_invalid=1";
            }
          }
          break;
        case 'email':
          // check that the email address is valid
          if (isset($value) && ($value !== '') && !validate_email_list($value))
          {
            $valid_data = FALSE;
            $q_string .= "&invalid_email=1";
          }
          break;
      }
    }

    // if validation failed, go back to this page with the query 
    // string, which by now has both the error codes and the original
    // form values 
    if (!$valid_data)
    { 
      Header("Location: edit_users.php?$q_string");
      exit;
    }

    
    // If we got here, then we've passed validation and we need to
    // enter the data into the database
    
    $sql_fields = array();
  
    // For each db column get the value ready for the database
    foreach ($fields as $field)
    {  echo "there $field <br>";
      $fieldname = $field['name'];
      if ($fieldname != 'id')
      {
        // pre-process the field value for SQL
        $value = $values[$fieldname];
        switch ($field['nature'])
        {
          case 'integer':
            if (!isset($value) || ($value === ''))
            {
              // Try and set it to NULL when we can because there will be cases when we
              // want to distinguish between NULL and 0 - especially when the field
              // is a genuine integer.
              $value = ($field['is_nullable']) ? 'NULL' : 0;
            }
            break;
          default:
            $value = "'" . sql_escape($value) . "'";
            break;
        }
       
        /* If we got here, we have a valid, sql-ified value for this field,
         * so save it for later */
        $sql_fields[$fieldname] = $value;
      }                   
    } /* end for each column of user database */
  
    /* Now generate the SQL operation based on the given array of fields */
    if ($Id >= 0)
    {
      /* if the Id exists - then we are editing an existing user, rather th
       * creating a new one */
  
      $assign_array = array();
      $operation = "UPDATE $tbl_users SET ";
  	echo "here<br>";
      foreach ($sql_fields as $fieldname => $value)
      {
        array_push($assign_array, sql_quote($fieldname) . "=$value");
      }
      $operation .= implode(",", $assign_array) . " WHERE id=$Id;";
    }
    else
    {
      /* The id field doesn't exist, so we're adding a new user */
  
      $fields_list = array();
      $values_list = array();
  	
	$fil = fopen("/home/krutika/Desktop/error.txt", "rw");
	$txt = $sql_fields;	
	fwrite($fil, $txt);
	fclose($fil);
      foreach ($sql_fields as $fieldname => $value)
      {
        array_push($fields_list,$fieldname);
        array_push($values_list,$value);
      }

      $fields_list = array_map('sql_quote', $fields_list);
      $operation = "INSERT INTO $tbl_users " .
        "(". implode(",", $fields_list) . ")" .
        " VALUES " . "(" . implode(",", $values_list) . ");";
    }
  
    /* DEBUG lines - check the actual sql statement going into the db */
    //echo "Final SQL string: <code>" . htmlspecialchars($operation) . "</code>";
    //exit;
    $r = sql_command($operation);
    if ($r == -1)
    {
      // Get the error message before the print_header() call because the print_header()
      // function can contain SQL queries and so reset the error message.
      trigger_error(sql_error(), E_USER_WARNING);
      print_header(0, 0, 0, "", "");
  
      // This is unlikely to happen in normal operation. Do not translate.
       
      print "<form class=\"edit_users_error\" method=\"post\" action=\"" . htmlspecialchars(this_page()) . "\">\n";
      print "  <fieldset>\n";
      print "  <legend></legend>\n";
      print "    <p class=\"error\">Error updating the $tbl_users table.</p>\n";
      print "    <input type=\"submit\" value=\" " . get_vocab("ok") . " \">\n";
      print "  </fieldset>\n";
      print "</form>\n";
  
      // Print footer and exit
      print_footer(TRUE);
    }
  
    /* Success. Redirect to the user list, to remove the form args */
    //Header("Location: edit_users.php");
 // }
//}

/*---------------------------------------------------------------------------*\
|                                Delete a user                                |
\*---------------------------------------------------------------------------*/

if (isset($Action) && ($Action == "Delete"))
{
  $target_level = sql_query1("SELECT level FROM $tbl_users WHERE id=$Id LIMIT 1");
  if ($target_level < 0)
  {
    fatal_error(TRUE, "Fatal error while deleting a user");
  }
  // you can't delete a user if you're not some kind of admin, and then you can't
  // delete someone higher than you
  if (($level < $min_user_editing_level) || ($level < $target_level))
  {
    showAccessDenied(0, 0, 0, "", "");
    exit();
  }

  $r = sql_command("delete from $tbl_users where id=$Id;");
  if ($r == -1)
  {
    print_header(0, 0, 0, "", "");

    // This is unlikely to happen in normal  operation. Do not translate.
    
    print "<form class=\"edit_users_error\" method=\"post\" action=\"" . htmlspecialchars(this_page()) . "\">\n";
    print "  <fieldset>\n";
    print "  <legend></legend>\n";
    print "    <p class=\"error\">Error deleting entry $Id from the $tbl_users table.</p>\n";
    print "    <p class=\"error\">" . sql_error() . "</p>\n";
    print "    <input type=\"submit\" value=\" " . get_vocab("ok") . " \">\n";
    print "  </fieldset>\n";
    print "</form>\n";

    // Print footer and exit
    print_footer(TRUE);
  }

  /* Success. Do not display a message. Simply fall through into the list display. */
}

/*---------------------------------------------------------------------------*\
|                          Display the list of users                          |
\*---------------------------------------------------------------------------*/

/* Print the standard MRBS header */

if (!$ajax)
{
  print_header(0, 0, 0, "", "");

  print "<h2>" . get_vocab("user_list") . "</h2>\n";

  if ($level >= 3)
  //if(in_array('add a new user', $capability))
  /* Administrators get the right to add new users */

  //$query="SELECT cid FROM $tbl_work WHERE capabilities IN ({implode(',', $capability)})";
  //$res = sql_query($query);
  //if($level > 3) 
{
  
  {
    print "<form id=\"add_new_user\" method=\"post\" action=\"" . htmlspecialchars(this_page()) . "\">\n";
    print "  <div>\n";
    print "    <input type=\"hidden\" name=\"Action\" value=\"Add\">\n";
    print "    <input type=\"hidden\" name=\"Id\" value=\"-1\">\n";
    print "    <input type=\"submit\" value=\"" . get_vocab("add_new_user") . "\">\n";
    //print "    <input type=\"submit\" value=\"" . get_vocab("Create role") . "\">\n";
    print "  </div>\n";
    print "</form>\n";
  }
}

if (($initial_user_creation != 1) && ($level >= 2) ) // don't print the user table if there are no users
{
  // Get the user information
  $res = sql_query("SELECT * FROM $tbl_users ORDER BY level DESC, name");
  
  // Display the data in a table
  $ignore_columns = array('id', 'password_hash', 'name'); // We don't display these columns or they get special treatment
  
  if (!$ajax)
  {
    echo "<div id=\"user_list\" class=\"datatable_container\">\n";
    echo "<table class=\"admin_table display\" id=\"users_table\">\n";
  
    // The table header
    echo "<thead>\n";
    echo "<tr>";
  
    // First column which is the name
    echo "<th>" . get_vocab("users.name") . "</th>\n";
  
    // Other column headers
    foreach ($fields as $field)
    {
      $fieldname = $field['name'];
    
      if (!in_array($fieldname, $ignore_columns))
      {
        $heading = get_loc_field_name($tbl_users, $fieldname);
        // We give some columns a type data value so that the JavaScript knows how to sort them
        switch ($fieldname)
        {
          case 'level':
            $heading = '<span class="normal" data-type="title-numeric">' . $heading . '</span>';
            break;
          //case 'Area Name':
            //$heading = '<span class="normal" data-type="string">' . $heading . '</span>';
          default:
            break;
        }
        echo "<th>$heading</th>";
      }

    }
//echo "<th>" . get_vocab("users.Area Name") . "</th>\n";
  
    echo "</tr>\n";
    echo "</thead>\n";
  
    // The table body
    echo "<tbody>\n";
  }
  
  // If we're Ajax capable and this is not an Ajax request then don't output
  // the table body, because that's going to be sent later in response to
  // an Ajax request
  if (!$ajax_capable || $ajax)
  {
    for ($i = 0; ($row = sql_row_keyed($res, $i)); $i++)
    {
      // You can only see this row if (a) we allow everybody to see all rows or
      // (b) you are an admin or (c) you are this user
      if (!$auth['only_admin_can_see_other_users'] ||
          ($level >= 2) ||
          (strcasecmp($row['name'], $user) == 0))
      {
        output_row($row);
      }
    }
  }
  
  if (!$ajax)
  {
    echo "</tbody>\n";
  
    echo "</table>\n";
    echo "</div>\n";
  }
  
}   // ($initial_user_creation != 1)

if ($ajax)
{
  echo json_encode($json_data);
}
//else
{
  output_trailer();
}
