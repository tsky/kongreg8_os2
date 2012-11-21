<?php

/*
 * Create a register (current or historic) for Kids Church
 */
?>
<div class="helpbox" id="helpbox">
    <a href="#" onClick="hidereminder('helpbox');">close help</a>
    <?php print $objHelp->displayHelp('Kids Church','3'); ?>
</div>
<div class="contentBox">
    <h1>Kids Church Register</h1>
    <h2>Current / Historic Register Generator</h2>
    <a href="index.php?mid=460">&lt;&lt; Back to Registration page</a>
        <span class="helpclass"><a href="#" onClick="showreminder('helpbox');"><img src="images/icons/information.png" alt="Show Help" title="Show Help" border="0" /></a></span>

    <?php 
        if(!empty($toggleMessage)){
            print $toggleMessage;
        }
        
        if($_POST['stage'] == 1){
            if($_POST['date'] !=""){
                $searchDate = db::escapechars($_POST['date']);
                $objKidschurch->displayHistoricRegister($searchDate);
            }
        }
        else{
            ?>
        <form name="historicRegister" action="index.php" method="post">
            <label for="date">Date : </label>
            <input type="text" name="date" id="date" />
            <label for="submit"></label>
            <input type="submit" name="submit" id="submit" value="Search Registers " />
            <input type="hidden" name="mid" id="mid" value="460" />
            <input type="hidden" name="function" id="function" value="historic" />
            <input type="hidden" name="stage" id="stage" value="1" />
        </form>
            <?php
        }
        
    ?>

</div>