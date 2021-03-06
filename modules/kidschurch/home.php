<?php

/*
 * Group home overview and create facility
 */

if(!empty($errorMsg)){
    print "<div class=\"errors\">$errorMsg</div>";
}
    
?>
<div class="helpbox" id="helpbox">
    <a href="#" onClick="hidereminder('helpbox');">close help</a>
    <?php print $objHelp->displayHelp('Kids Church','2'); ?>
</div>
<div class="contentBoxLeft">
    <h1>Kids Church Groups Overview</h1>
        <span class="helpclass"><a href="#" onClick="showreminder('helpbox');"><img src="images/icons/information.png" alt="Show Help" title="Show Help" border="0" /></a></span>

    <?php 
        if(!empty($toggleMessage)){
            print $toggleMessage;
        }
        $grouplist = $objKidschurch->viewGroups($groupid); 
        if($grouplist != "false"){
            print $grouplist;
        }
        else{
            print "<p>No groups available at present</p>";
        }
    ?>
</div>
<div class="contentBoxRight">
    <h2>Add a new Group</h2>
    <form name="addgroup" action="index.php" method="post" >
        <input type="hidden" name="mid" id="mid" value="400" />
        <input type="hidden" name="add" id="add" value="true" />
        <label for="groupname">Group Name</label>
        <input type="text" name="groupname" id="groupname" />
        <label for="groupdescription">Description</label>
        <textarea id="groupdescription" name="groupdescription"></textarea>
        <label for="groupleader">Group Leader</label>
        <input type="text" name="groupleader" id="groupleader" />
        
        <?php
        if($_SESSION['Kcampus'] == "all"){
            ?>
            <label for="campus">Campus</label>
            <?php  
            $dropdown = $objKongreg8->viewCampusDropdown();
            print $dropdown;
        }
        else{
            ?>
            <input type="hidden" name="campus" id="campus" value="<?php print $_SESSION['Kcampus']; ?>" />
            <?php
        }
        ?>
        <label for="submit">&nbsp;</label>
        <input type="submit" value="Create" />
    </form>
</div>