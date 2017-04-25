<?php

// $Id$

require "defaultincludes.inc";

// Get non-standard form variables

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
/*if (isset($work))
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
*/
if($is_superadmin) {
echo "<h2>" . get_vocab("Roles") . "</h2>\n";
if (!empty($error))
{
  echo "<p class=\"error\">" . get_vocab($error) . "</p>\n";
}


}

if ($is_superadmin)
{
  // New area form
  ?>
  <form id="add_role" class="form_admin" action="addcapability.php" method="post">
    <fieldset>
    <legend><?php echo get_vocab("Add Functionality") ?></legend>
        
      <input type="hidden" name="type" value="role">

      <div>
        <label for="roles"><?php echo get_vocab("name") ?>:</label>
        <input type="text" id="roles" name="name" maxlength="<?php echo $maxlength['role.capabilities'] ?>">
      </div>
          
      <div>
        <input type="submit" class="submit" value="<?php echo get_vocab("Add Roles") ?>">
      </div>

    </fieldset>
  </form>
  <?php
}
echo "</div>";  // area_form

echo "<div id=\"capabilities_form\">\n";
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
  //echo "<option $selected value=\"". $c['cid']. "\">" . htmlspecialchars($c['capabilities']) . "</option>";
  for ($i = 0; ($works = sql_row_keyed($res, $i)); $i++)
  {
    $works[] = $row;
    if ($is_admin || !$row['disabled'])
    {
      $n_displayable_options++;
    }
    $params = array(  'label' => $row['works'],
                      'name'          => 'works',
                      'options'       => $work,
                      'force_assoc'   => TRUE,
                      'value'         => $row['works'],
                      'disabled'      => $disabled
                      );
   generate_checkbox($params);
   echo "<option $selected value=\"". $works['capabilities']. "\">" . htmlspecialchars($works['capabilities']) . "</option>";
  }

  if ($n_displayable_options == 0)
  {
    echo "<p>" . get_vocab("not_enabled") . "</p>\n";
  }
  else
  {
    // If there are some areas displayable, then show the area form
    echo "<form id=\"capabilitiesChangeForm\" method=\"get\" action=\"" . htmlspecialchars(this_page()) . "\">\n";
    echo "<fieldset>\n";
    echo "<legend></legend>\n";
  
      
if ($is_admin)
    {
      if ($works[0]['disabled'])
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
       /* $selected = ($c['cid'] == $work) ? "selected=\"selected\"" : "";
	$params = array(  'label' => $row['works'],
                      'name'          => 'new_works',
                      'options'       => $work,
                      'force_assoc'   => TRUE,
                      'value'         => $row['works'],
                      'disabled'      => $disabled,
                      'create_hidden' => FALSE);
    	generate_checkbox_group($params);
        echo "<option $selected value=\"". $c['cid']. "\">" . htmlspecialchars($c['capabilities']) . "</option>";
      }
    }


    if ($is_admin)
    {
      echo "</optgroup>\n";
    }


    echo "</select>\n";

    echo "</fieldset>\n";
    echo "</form>\n";
  }
}
 


// Now the custom HTML
echo "<div id=\"custom_html\">\n";
// no htmlspecialchars() because we want the HTML!
echo (!empty($custom_html)) ? "$custom_html\n" : "";
echo "</div>\n";




output_trailer();

