<?php

/*
 * Mail a group with information
 */

require_once ('application/groups/groups.php');
$objGroup = new groups();

if($_GET['tid'] !=""){
    $tid = db::escapechars($_GET['tid']);
}
else{
    $tid = "";
}
// SEND A SET OF MESSAGES TO PEOPLE
if($_GET['action'] == 'send'){
    
    $tid = $_GET['tid'];
        
    if(!empty($tid)){

        if($objGroup->sendEmailCampaign($tid) == 'true'){
                $toggleMessage = "<p class=\"updated\">Email Campaign Sent.</p>";
        }
        else{
            
            $errorMsg = "Some errors were encountered in the processing, please try again. Some group emails could not sent.";
        }
    }
    else{
        $errorMsg = "<p class=\"confirm\">No Template ID passed - cannot continue.</p>";
        
    }
}

// REMOVE A MESSAGE TEMPLATE
if($_GET['action'] == "remove"){
    
    
}

// SAVE A TEMPLATE FOR USE
if($_POST['action'] == "save"){
    
    if(($_POST['subject'] !=="") && ($_POST['message'] !="") && ($_POST['group'] !="")){
        
        $runinsert = $objGroup->addEmailTemplate($_POST['subject'], $_POST['message'], $_POST['group'], $_SESSION['Kcampus']);
        if($runinsert == 'true'){
            $toggleMessage = "<p class=\"updated\">Your new template has been created and is ready for use</p>";
        }
        else{
            $errorMsg = "Could not store the template at this time. The fault has been logged.";
        }
        
    }
    else{
        $errorMsg = "Missing Data in Template Form, please try again";
    }
    
}



require_once ('modules/groups/mailGroup.php');
?>
