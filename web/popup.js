print("<input name=\"select_delete\" type=\"submit\" 
value=\"Delete\" onclick=\"confirmDelete();\">\n");
print("</form>\n");
?>

<script>
function confirmDelete()
{
    var e = document.getElementById("movie");
    var msg = e.options[e.selectedIndex].value;
    var x;
    var r=confirm("Delete" + msg + "entry?");
    if (r==true)
    {
        //OK button pressed
    }
    else
    {
        //Cancel button pressed
    }
}
</script>
