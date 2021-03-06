<?php

/*
 * Group overview page
 */
$checkuserlevel = $objKongreg8->checkAccessLevel('Kids Church');
if($checkuserlevel == false){
    require_once ('failcodes/userlevel.html');
    exit();
}


require_once('application/kidschurch/kidschurch.php');
$objKidschurch = new kidschurch();


if(($_POST['add'] == 'true')||($_GET['add'] == 'true')){
    // Check to see if it's stage one or two on the addition process
    
    if((!empty($_POST['groupname']))&&(!empty($_POST['groupdescription']))&&(!empty($_POST['groupleader']))){
        // Check who they want as the leader from the leader search box
        
        $leaderlist = $objKidschurch->verifyGroupLeader($_POST['groupleader'], $_POST['groupname'], $_POST['groupdescription'], $_POST['campus']);
        
        $toggleMessage = "<p class=\"confirm\">Please select the correct leader from your search criteria:<br/>$leaderlist</p>";
        
    }
    elseif((!empty($_GET['groupname'])) && (!empty($_GET['groupdescription'])) && (!empty($_GET['leader']))){
        // leader selected so add the group to the system
        
        $addgroup = $objKidschurch->addGroup($_GET['groupname'], $_GET['groupdescription'], $_GET['leader'], $_GET['campus']);
        if($addgroup != 'false'){
            // Group added so reflect this
            $toggleMessage = "<p class=\"updated\">Your group has been added to the system.</p>";
            
        }
        else{
            $toggleMessage = "<p class=\"confirm\">Could not add the group at this time, this event has been logged. Sorry.</p>";
        }
        
    }
    else{
        // do not have the correct values so cannot underake actions
        $errorMsg = "<p>You are missing a group name, description or leader</p>";
    }
    
}


if($_GET['remove'] == 'true'){
    
    $groupid = $_GET['g'];
        
    if(!empty($groupid)){
    
        $groupinfo = $objKidschurch->groupView($groupid);
        $groupname = $groupinfo['groupname'];
        
        if($_GET['confirm'] == 'true'){
            
            $removegroup = $objKidschurch->groupDelete($groupid);
            if($removegroup == true){
                $toggleMessage = "<p class=\"updated\">Group removed.</a>";
            }
            else{
                $errorMsg = "Could not delete the group at the moment. The fault has been logged, please try again.";
            }
        }
        else{
            
            $toggleMessage = "<p class=\"confirm\">Are you SURE you want to remove the group $groupname ?
                            <a href=\"index.php?mid=410&g=$groupid&remove=true&confirm=true\">Yes, I agree</a>
                            ";
        }
    }
    else{
        $toggleMessage = "<p class=\"confirm\">No GROUP ID passed - cannot continue.</p>";
        
    }
}


require_once('modules/kidschurch/home.php');

?>
