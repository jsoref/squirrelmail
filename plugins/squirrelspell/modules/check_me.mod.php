<?php

 /**
    CHECK_ME.MOD.PHP
    -----------------
    This module is the main workhorse of SquirrelSpell. It submits
    the message to the spell-checker, parses the output, and loads
    the interface window.
								**/
// Declaring globals for E_ALL.
global $sqspell_text, $SQSPELL_APP, $sqspell_use_app, $attachment_dir,
       $username, $SQSPELL_EREG, $color; 

 // Now we explode the lines for three reasons:
 // 1) So we can ignore lines starting with ">" (reply's)
 // 2) So we can stop processing when we get to "--" on a single line,
 //    which means that the signature is starting
 // 3) So we can add an extra space at the beginning of each line. This way
 //    ispell/aspell don't treat these as command characters.
 $sqspell_raw_lines = explode("\n", $sqspell_text);
 for ($i=0; $i<sizeof($sqspell_raw_lines); $i++){
   if (trim($sqspell_raw_lines[$i]) == "--") break;
   if(substr($sqspell_raw_lines[$i], 0, 1) != ">") 
    $sqspell_new_lines[$i] = " " . $sqspell_raw_lines[$i];
    else $sqspell_new_lines[$i] = "";
 }
 $sqspell_new_text=implode("\n", $sqspell_new_lines);

 // Define the command used to spellcheck the document.
 $sqspell_command=$SQSPELL_APP[$sqspell_use_app];
 // For the simplicity's sake we'll put all text into a file
 // in attachment_dir directory, then cat it and pipe it to sqspell_command.
 // There are other ways to do it, including popen(), but it's unidirectional
 // and no fun at all.
 // NOTE: This will probably change in future releases of squirrelspell
 // for privacy reasons.
 //
 $floc = "$attachment_dir/$username" . "_sqspell_data.txt";
 $fp=fopen($floc, "w");
 fwrite($fp, $sqspell_new_text);
 fclose($fp);
 exec("cat $floc | $sqspell_command", $sqspell_output);
 unlink($floc);

 // Load the user dictionary.
 $words=sqspell_getLang(sqspell_getWords(), $sqspell_use_app);
 // define some variables.
 $current_line=0;
 $missed_words=Array();
 $misses = Array();
 $locations = Array();
 $errors=0;
 // Now we process the output of sqspell_command (ispell or aspell
 // in ispell compatibility mode, whichever).
 for ($i=0; $i<sizeof($sqspell_output); $i++){
  switch (substr($sqspell_output[$i], 0, 1)){
  case "":
    // Ispell adds empty lines when an end of line is reached
    $current_line++;
    break;

  case "&":
    // This means there's a misspelled word and a few suggestions.
    list($left, $right) = explode(": ", $sqspell_output[$i]);
    $tmparray = explode(" ", $left);
    $sqspell_word=$tmparray[1];
    // Check if the word is in user dictionary.
    if (!$SQSPELL_EREG("\n$sqspell_word\n", $words)){ 
     $sqspell_symb=intval($tmparray[3])-1;
     if (!$misses[$sqspell_word]) {
	$misses[$sqspell_word] = $right;
	$missed_words[$errors] = $sqspell_word;
	$errors++;
     }
     if ($locations[$sqspell_word]) $locations[$sqspell_word] .= ", ";
     $locations[$sqspell_word] .= "$current_line:$sqspell_symb";
    }
    break;

  case "#":
    // This means a misspelled word and no suggestions.
    $tmparray = explode(" ", $sqspell_output[$i]);
    $sqspell_word=$tmparray[1];
    // Check if the word is in user dictionary.
    if (!$SQSPELL_EREG("\n$sqspell_word\n", $words)){
     $sqspell_symb=intval($tmparray[2])-1;
     if (!$misses[$sqspell_word]) {
	$misses[$sqspell_word] = "_NONE";
	$missed_words[$errors] = $sqspell_word;
	$errors++;
     }
     if ($locations[$sqspell_word]) $locations[$sqspell_word] .= ", ";
     $locations[$sqspell_word] .= "$current_line:$sqspell_symb";
    }
    break;
  }
 }

 if ($errors){ 
  // So, there are errors
  // This is the only place where the generic GUI-wrapper is not
  // called, but generated right here. This is due to the complexity
  // of the output.
  ?>
  <html>
  <head>
   <title>SquirrelSpell Results</title>
   <script type="text/javascript">
     // Load the spelling errors into JavaScript arrays
     <!--
     <?php
     $sqspell_lines = explode("\n", $sqspell_text);
     echo "// All lines of the message
     var sqspell_lines=new Array();\n";
     for ($i=0; $i<sizeof($sqspell_lines); $i++){
       echo "sqspell_lines[$i] = \"" . chop(addslashes($sqspell_lines[$i])) . "\";\n";
     }

     echo "\n\n";
     echo "// Misses are all misspelled words
     var misses=new Array();\n";
     for ($i=0; $i<sizeof($missed_words); $i++){
       echo "misses[$i] = \"" . $missed_words[$i] . "\";\n";
     }

     echo "\n\n";
     echo "// Suggestions are (guess what!) suggestions for misspellings
     var suggestions = new Array();\n";
     $i=0;
     while (list($word, $value) = each($misses)){
       if ($value=="_NONE") $value="";
       echo "suggestions[$i] = \"$value\";\n";
       $i++;
     }

     echo "\n\n";
     echo "// Locations are where those misspellings are located, line:symbol
     var locations= new Array();\n";
     $i=0;
     while (list($word, $value) = each($locations)){
       echo "locations[$i] = \"$value\";\n";
       $i++;
     }
     ?>
     // Why isn't there a booger fairy?
     //-->
   </script>
   <script src="js/check_me.js" type="text/javascript"></script>
   </head>
  <?php
  printf('<body bgcolor="%s" text="%s" link="%s" alink="%s" vlink="%s" onload="populateSqspellForm()">', $color[4], $color[8], $color[7], $color[7], $color[7]);
   ?>
   <table width="100%" border="0" cellpadding="2">
   <tr><td bgcolor="<?php echo $color[9] ?>" align="center"><b>Found <?php 
   	echo $errors ?> errors</b></td></tr>
   <tr><td><hr></td></tr>
   <tr><td>
   <form method="post">
   <input type="hidden" name="MOD" value="forget_me_not">
   <input type="hidden" name="words" value="">
   <input type="hidden" name="sqspell_use_app" value="<?php echo $sqspell_use_app ?>">
   <table border="0" width="100%">
    <tr align="center">
     <td colspan="4">
      <span style="background-color:<?php echo $color[9] ?>">Line with an error:</span><br>
      <textarea name="sqspell_line_area" cols="50" rows="3" wrap="hard" onfocus="this.blur()"></textarea>
     </td>
    </tr>
    <tr valign="middle">
     <td align="right" width="25%">
      <span style="background-color: <?php echo $color[9] ?>">Error:</span> 
     </td>
     <td align="left" width="25%">
      <input name="sqspell_error" size="10" value="" onfocus="this.blur()">
     </td>
     <td align="right" width="25%">
      <span style="background-color: <?php echo $color[9] ?>">Suggestions:</span> 
     </td>
     <td align="left" width="25%">
      <select name="sqspell_suggestion" onchange="if (this.options[this.selectedIndex].value != '_NONE') document.forms[0].sqspell_oruse.value=this.options[this.selectedIndex].value">
       <option>Suggestions</option>
      </select>
     </td>
    </tr>
    <tr>
     <td align="right">
      <span style="background-color: <?php echo $color[9] ?>">Change to:</span> 
     </td>
     <td align="left">
      <input name="sqspell_oruse" size="15" value=""
        onfocus="if(!this.value) this.value=document.forms[0].sqspell_error.value">
     </td>
     <td align="right">
      <span style="background-color: <?php echo $color[9] ?>">Occurs times:</span> 
     </td>
     <td align="left">
      <input name="sqspell_likethis" size=3 value=""
	onfocus="this.blur()">
     </td>
    </tr>
   </td></tr>
   <tr><td colspan="4"><hr></td></tr>
    <tr>
     <td colspan="4">
      <table border="0" cellpadding="0" cellspacing="3" width="100%">
       <tr align="center" bgcolor="<?php echo $color[9] ?>">
        <td>
         <a href="javascript:sqspellChange()"
	  title="Change this word">Change</a>
        </td>
        <td>
         <a href="javascript:sqspellChangeAll()"
	  title="Change ALL occurances of this word">Change All</a>
        </td>
        <td>
         <a href="javascript:sqspellIgnore()"
	  title="Ignore this word">Ignore</a>
        </td>
        <td>
         <a href="javascript:sqspellIgnoreAll()"
	  title="Ignore ALL occurances of this word">Ignore All</a>
        </td>
	<td>
	 <a href="javascript:sqspellRemember()" 
	  title="Add this word to your personal dictionary">Add to Dic</a>
	</td>
       </tr>
      </table>
     </td>
    </tr>
    <tr><td colspan="4"><hr></td></tr>
    <tr>
     <td colspan="4" align="center" bgcolor="<?php echo $color[9] ?>">
      <input type="button" value="  Close and Commit  " onclick="if (confirm('The spellcheck is not finished. Really close and commit changes?')) sqspellCommitChanges()"> 
      <input type="button" value="  Close and Cancel  " onclick="if (confirm('The spellcheck is not finished. Really close and discard changes?')) self.close()">
     </td>
    </tr>
   </table>
   </form>
   </td></tr>
  </table>
  </body>
  </html>
  <?php
 } else {
   // AREN'T YOU SUCH A KNOW-IT-ALL!
   $msg="<form onsubmit=\"return false\"><div align=\"center\"><input type=\"submit\" value=\"  Close  \" onclick=\"self.close()\"></div></form>";
   sqspell_makeWindow(null, "No errors found", null, $msg);
 }
?>
