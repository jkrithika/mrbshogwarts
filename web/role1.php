<?php

// $Id$

require "defaultincludes.inc";

// Get non-standard form variables
$area_name = get_form_var('area_name', 'string');
$error = get_form_var('error', 'string');
// the image buttons:  need to specify edit_x rather than edit etc. because
// IE6 only returns _x and _y
$edit_x = get_form_var('edit_x', 'int');
$delete_x = get_form_var('delete_x', 'int');


// Check to see whether the Edit or Delete buttons have been pressed and redirect
// as appropriate
$std_query_string = "area=$area&day=$day&month=$month&year=$year";
if (isset($edit_x))
{
  $location = $location = "edit_area_room.php?change_area=1&phase=1&$std_query_string";
  header("Location: $location");
  exit;
}
if (isset($delete_x))
{
  $location = "del.php?type=area&$std_query_string";
  header("Location: $location");
  exit;
}
  
// Check the user is authorised for this page
checkAuthorised();

// Also need to know whether they have admin rights
$user = getUserName();
$required_level = (isset($max_level) ? $max_level : 4);
$is_superadmin = (authGetUserLevel($user) == 4);
$is_areaadmin = (authGetUserLevel($user) == 3 );
$is_roomadmin = (authGetUserLevel($user) == 2);
$is_admin = (authGetUserLevel($user) >= $required_level);

print_header($day, $month, $year, isset($area) ? $area : "", isset($room) ? $room : "");

// Get the details we need for this area




echo "</div>";  // area_form

// Now the custom HTML
echo "<div id=\"custom_html\">\n";
// no htmlspecialchars() because we want the HTML!
echo (!empty($custom_html)) ? "$custom_html\n" : "";
echo "</div>\n";


// BOTTOM ECTION: ROOMS IN THE SELECTED AREA
// Only display the bottom section if the user is an admin or
// else if there are some areas that can be displayed
if (($is_areaadmin) ||($is_superadmin) ||  ($n_displayable_areas > 0))
{
  echo "<h2>\n";
  echo get_vocab("rooms");
 
  echo "</h2>\n";

  echo "<div id=\"room_form\">\n";
  if (isset($room))
  {
    $res = sql_query("SELECT role_name,capability FROM $tbl_users");
    if (! $res)
    {
      trigger_error(sql_error(), E_USER_WARNING);
      fatal_error(FALSE, get_vocab("fatal_db_error"));
    }
    if (sql_count($res) == 0)
    {
      echo "<p>" . get_vocab("norooms") . "</p>\n";
    }
    else
    {
       // Get the information about the fields in the room table
      $fields = sql_field_info($tbl_roles);
    
      // Build an array with the room info and also see if there are going
      // to be any rooms to display (in other words rooms if you are not an
      // admin whether any rooms are enabled)
      $rooms = array();
      $n_displayable_rooms = 0;
      for ($i = 0; ($row = sql_row_keyed($res, $i)); $i++)
      {
        $rooms[] = $row;
        if ($is_superadmin )
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
        echo "<div id=\"room_info\" class=\"datatable_container\">\n";
        // Build the table.    We deal with the name and disabled columns
        // first because they are not necessarily the first two columns in
        // the table (eg if you are running PostgreSQL and have upgraded your
        // database)
        echo "<table id=\"rooms_table\" class=\"admin_table display\">\n";
        
        // The header
        echo "<thead>\n";
        echo "<tr>\n";

        echo "<th>" . get_vocab("role_name") . "</th>\n";
        if ($is_areaadmin || $is_superadmin || $is_roomadmin)
        {
        // Don't show ordinary users the disabled status:  they are only going to see enabled rooms
          echo "<th>" . get_vocab("enabled") . "</th>\n";
        }
        // ignore these columns, either because we don't want to display them,
        // or because we have already displayed them in the header column
        $ignore = array('role_id', 'role_name', 'custom_html');
        foreach($fields as $field)
        {
          if (!in_array($field['name'], $ignore))
          {
            switch ($field['name'])
            {
              // the standard MRBS fields
              case 'description':
              case 'capacity':
			$text = get_vocab($field['name']);
			break;
             // case 'Room_admin_email':sql_query("SELECT email FROM $tbl_users WHERE area_id=$area ORDER BY sort_key");
                
              //  break;
              // any user defined fields
              default:
                $text = get_loc_field_name($tbl_roles, $field['name']);
                break;
            }
            // We don't use htmlspecialchars() here because the column names are
            // trusted and some of them may deliberately contain HTML entities (eg &nbsp;)
            echo "<th>$text</th>\n";
          }
        }
        
        if ($is_superadmin || $is_areaadmin || $is_roomadmin)
        {
          echo "<th>&nbsp;</th>\n";
        }
        
        echo "</tr>\n";
        echo "</thead>\n";
        
        // The body
        echo "<tbody>\n";
        $row_class = "odd";
        foreach ($rooms as $r)
        {
          // Don't show ordinary users disabled rooms
          if ($is_superadmin)
          {
            $row_class = ($row_class == "even") ? "odd" : "even";
            echo "<tr class=\"$row_class\">\n";

            $html_name = htmlspecialchars($r['role_name']);
            // We insert an invisible span containing the sort key so that the rooms will
            // be sorted properly
            echo "<td><div>" .
                 //"<span>" . htmlspecialchars($r['sort_key']) . "</span>" .
                 //"<a title=\"$html_name\" href=\"edit_area_room.php?change_room=1&amp;phase=1&amp;room=" . $r['id'] . "\">$html_name</a>" .
                 "</div></td>\n";
            if ($is_superadmin || $is_areaadmin || $is_roomadmin)
            {
              // Don't show ordinary users the disabled status:  they are only going to see enabled rooms
              echo "<td class=\"boolean\"><div>" . ((!$r['disabled']) ? "<img src=\"images/check.png\" alt=\"check mark\" width=\"16\" height=\"16\">" : "&nbsp;") . "</div></td>\n";
            }
            foreach($fields as $field)
            {
              if (!in_array($field['name'], $ignore))
              {
                switch ($field['name'])
                {
                  // the standard MRBS fields
                  //case 'description':
                  //case 'room_admin_email':
                    //echo "<td><div>" . htmlspecialchars($r[$field['name']]) . "</div></td>\n";
                    //break;
             //     case 'capacity':
               //     echo "<td class=\"int\"><div>" . $r[$field['name']] . "</div></td>\n";
                 //   break;
                  // any user defined fields
                  default:
                    if (($field['nature'] == 'boolean') || 
                        (($field['nature'] == 'integer') && isset($field['length']) && ($field['length'] <= 2)) )
                    {
                      // booleans: represent by a checkmark
                      echo "<td class=\"boolean\"><div>";
                      echo (!empty($r[$field['name']])) ? "<img src=\"images/check.png\" alt=\"check mark\" width=\"16\" height=\"16\">" : "&nbsp;";
                      echo "</div></td>\n";
                    }
                    elseif (($field['nature'] == 'integer') && isset($field['length']) && ($field['length'] > 2))
                    {
                      // integer values
                      echo "<td class=\"int\"><div>" . $r[$field['name']] . "</div></td>\n";
                    }
                    else
                    {
                      // strings
                      $value = $r[$field['name']];
                      $html = "<td title=\"" . htmlspecialchars($value) . "\"><div>";
                      // Truncate before conversion, otherwise you could chop off in the middle of an entity
                      $html .= htmlspecialchars(utf8_substr($value, 0, $max_content_length));
                      $html .= (utf8_strlen($value) > $max_content_length) ? " ..." : "";
                      $html .= "</div></td>\n";
                      echo $html;
                    }
                    break;
                }  // switch
              }  // if
            }  // foreach
            
            // Give admins a delete link
            if ($is_admin )
            {
              // Delete link
              echo "<td><div>\n";
              echo "<a href=\"del.php?type=room&amp;area=$area&amp;room=" . $r['id'] . "\">\n";
              echo "<img src=\"images/delete.png\" width=\"16\" height=\"16\" 
                         alt=\"" . get_vocab("delete") . "\"
                         title=\"" . get_vocab("delete") . "\">\n";
              echo "</a>\n";
              echo "</div></td>\n";
            }
            
            echo "</tr>\n";
          }
        }

        echo "</tbody>\n";
        echo "</table>\n";
        echo "</div>\n";
        
      }
    }
  }
  else
  {
    echo get_vocab("noarea");
  }

  // Give admins a form for adding rooms to the area - provided 
  // there's an area selected
  if (($is_admin ) && $areas_defined && !empty($area))
  {
  ?>
   
  <?php
  }
  echo "</div>\n";
}

output_trailer();

