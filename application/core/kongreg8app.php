<?php


/**
 * kongreg8app
 * Main functions for the Kongreg8 application framework
 * Pulls data from the DB and calculates / maintains settings
 * for the whole of the Kongreg8 framework.
 * 
 * The kongreg8app function is used as the basis for extensions in other modules
 * allowing the targeting of information using $this->
 * 
 * @author rick
 */
class kongreg8app{
    
    
    /*
     * Provide information about the version number and what updates have
     * been applied to the system
     * 
     */
    
    public function version()
    {
        
        // Get the current system version from the database
        
        $sql = "SELECT * FROM settings WHERE settingName='systemVersion'";
        $result = db::returnrow($sql);
        
        return $result['settingValue'];
        
    }
    
    /*
     * System Information and Configuration Options
     * 
     */
    public function getSystemInfo()
    {
        print "Kongreg8 v" . $this->version();
        
        print "<br/>Server PHP Version: " . phpversion();
        
        print "<h2>module info</h2>" . $this->getModuleList();
        
        print help::displayHelp('about', '1');
    }
    
    /*
     * Entire Module List (maintained in DB)
     * 
     */
    public function getModuleList()
    {
        
        $sql = "SELECT * FROM kmodules ORDER BY moduleName ASC";
        $result = db::returnallrows($sql);
        $modulelist = "";
        foreach($result as $module){
            
            $modulelist .= "<span class=\"moduleName\">" . $module['moduleName'] . "</span> - version " . $module['moduleVersion'] . " -  Author " . $module['moduleAuthor'];
            $modulelist .= "<span class=\"moduleRequire\"> [ Requires Level " . $module['userlevel'] . " ]</span><br/>";
        }
        return $modulelist;
    }
    /*
     * Select what module we are loading by cleansing incomming vars
     * in partnership with the brain function
     * 
     */
    
    public function selectmodule()
    {
        // Get what I'm supposed to load as a module from the URL 
        // Or passed variables
        if($_POST['mid'] !=""){
            $module = db::escapechars(trim($_POST['mid']));
        }
        else{
            $module = db::escapechars(trim($_GET['mid']));
        }
        return $module;
    }
    
    /*
     * Get server string data
     * 
     */
    public function getServer($key = null, $default = null)
    {
        if (null === $key) {
            return $_SERVER;
        }
        else{
            return (isset($_SERVER[$key])) ? $_SERVER[$key] : $default;
        }
    }

    /*
     * Get IP Address
     * (Please note that Kongreg8 uses IPv4 format for IP addresses) 
     */
    public function getIP()
    {
        if ($this->getServer('HTTP_CLIENT_IP') != null){
            $ip = $this->getServer('HTTP_CLIENT_IP');
        }
        else if ($this->getServer('HTTP_X_FORWARDED_FOR') != null){
            $ip = $this->getServer('HTTP_X_FORWARDED_FOR');
        }
        else{
            $ip = $this->getServer('REMOTE_ADDR');
        }
        
        return $ip;
    }

    
    /*
     * 
     * Do main authentication for the system checking credentials passed from login
     * to here and then set session variables or provide error code response.
     * 
     * 
     */
    
    public function doauth($username, $password)
    {
        // Authenticate from the main log in form
        
        $username = stripslashes(db::escapechars($username));
        $password = stripslashes(db::escapechars($password));
        
        
        
        // --- IDS Detection Routine -- 
        // check how many failed authentications have taken place
        $startTime = date('H:i:s',mktime(date('H'), (date('i')-5), date('s'))); // five minutes ago
        $nowTime = date ('H:i:s'); // now

        $todayDate = date('Y-m-d'); // today
        $sql = "SELECT * FROM klog WHERE logValue LIKE '$username Failed sign in%'";
        $sql .= " AND logDate = '$todayDate' AND (logTime BETWEEN '$startTime' AND '$nowTime')";
        
        $numFailedAuths = 0;
        $numFailedAuths = db::getnumrows($sql);
        // IF less than maximum of 4 failed authentications reached do auth, else give a message.
        if($numFailedAuths < 5)
        {
            $sql = "SELECT * FROM users WHERE username = '$username'";
            $result = db::returnrow($sql);
            if($result)
            {
                $resultArray = db::returnrow($sql);
                // If there is a match set the session variables
                $md5Pass = md5($password);
                if($md5Pass == $resultArray['password'])
                {
                    $userID = $resultArray['userID'];
                    $userLevel = $resultArray['userlevel'];

                    // Set the session variables
                    $_SESSION['Kusername']= $username;
                    $_SESSION['Kpasswd'] = $md5Pass;
                    $_SESSION['Kulevel'] = $userLevel;

                    // Log the activity
                    $logType = "System Auth";
                    $IPAddress = $this->getIP();
                    $logValue = "$username Successful sign in from $IPAddress";
                    $logArea = "Auth";
                    
                    $this->logevent($logType, $logValue, $logArea);

                    
                    // return success of auth
                    $successcode = "111";
                    return $successcode;
                }
                else
                {
                    // Log the activity
                    $logType = "System Auth";
                    $IPAddress = $this->getIP();
                    $logValue = "$username Failed sign in from $IPAddress";
                    $logArea = "Auth";
                   
                    $this->logevent($logType, $logValue, $logArea);
                    
                    
                    // return failure
                    $failcode = "101";
                    return $failcode;
                }
         }
         else
             {
                 // Couldn't find the username in the database
                 // Log the activity
                 $logType = "System Auth";
                 $IPAddress = $this->getIP();
                 $logValue = "$username Failed sign in from $IPAddress";
                 $logArea = "Auth";
                 
                 $this->logevent($logType, $logValue, $logArea);
                 
                 // Return the failure code
                 $failcode = "101";
                 return $failcode;
             }
       }
       else
       {
            // Maximum failure attempts reached - return failure code
           $failcode = "102";
           return $failcode;
       }
        
    }
    
    
    
    
    
    /*
     * 
     * Maintain authentication of the system throughout page loads
     * re-check the session variables against the system in case of
     * injection or manipulation
     * 
     */
    
    
    public function maintainauth()
    {
        
        // Maintain Authentication using session variables and a connection to the DB
        
        if(($_SESSION['Kusername'] == "") || ($_SESSION['Kpasswd'] == "") || ($_SESSION['Kulevel'] == ""))
        {
            if(($_POST['username'] !== "") && ($_POST['passwd'] !== "") && ($_POST['mid'] == "100"))
            {
                // Authenticating against scripts so allow through this check script
                return 'authing';
            }
            else
            {
                // Log in form required
                return 'noauth';    
            }
        }
        else
        {
            /*
            *
            * Should be authenticated ok but always check the authentication
            * in case SESSION vars are being tampered with
            *
            */
            $Kusername = strip_tags(stripslashes($_SESSION['Kusername']));
            $sql = "SELECT * FROM users WHERE username = '".$Kusername."'";
            $result = db::returnrow($sql);
            if($result){
                    // If there is a match set the session variables
                    if($_SESSION['Kpasswd'] == $result['password']){
                            $_SESSION['Kusername'] = $Kusername;
                            $_SESSION['Kpasswd'] = $_SESSION['Kpasswd'];
                            $_SESSION['Kulevel'] = $result['userlevel'];
                            $_SESSION['Kcampus'] = $result['campus'];
                            
                            return 'auth';
                    }
                    else{
                            // Stored data doesn't match that passed to it
                            // Kill the session variables and give an error message
                            $_SESSION['Kusername'] = "";
                            $_SESSION['Kpasswd'] = "";
                            $_SESSION['Kulevel'] = "";
                            $_SESSION['Kcampus'] = "";
                            session_destroy();
                            
                            return 'noauth';
                    }
            }
            else{
                    // Couldn't get the username - need to authenticate again because something is wrong
                    
                    return 'noauth';
            }
         }
        }
        
        
        
        
        
        
        /*
         * LOG OUT OF THE SYSTEM
         * Kill all session variables
         * 
         */
        public function logout()
        {
            // Kill the session variables and give an error message
            $_SESSION['Kusername'] = "";
            $_SESSION['Kpasswd'] = "";
            $_SESSION['Kulevel'] = "";
            $_SESSION['Kcampus'] = "";
            session_destroy();
        }
        
        
        /*
         * CHECK WHAT CAMPUS INFORMATION YOU SHOULD SEE
         * This is used to limit administrators to a specific campus
         * information set, or to allow access to all.
         */
        public function checkCampus(){
            if($_SESSION['Kcampus'] == 'all'){
                return true;
            }
            else{
                if($_SESSION['Kcampus'] != ""){
                    return intval($_SESSION['Kcampus']);
                }
                else{
                    return false;
                }
            }
        }
        
        
        /*
         * 
         * Log events in the system, passed parameters from each module
         * 
         */
        
        public function logevent($logType, $logValue, $logArea)
        {
            $theTime = date('H:i:s');
            $theDate = date('y-m-d');
            $sql = "INSERT INTO klog
                    SET
                    logTime='$theTime',
                    logDate='$theDate',
                    logType='" . db::escapechars($logType) . "',
                    logValue='" . db::escapechars($logValue) . "',
                    logArea='" . db::escapechars($logArea) . "'
                    ";
            
            $result = db::execute($sql);
        }
        
        
        /*
         * 
         * Log errors in the system, passed parameters from each module
         * 
         */
        
        public function logerror($logType, $logValue, $logArea)
        {
            $theTime = date('H:i:s');
            $theDate = date('y-m-d');
            $sql = "INSERT INTO kerrorlog
                    SET
                    logTime='$theTime',
                    logDate='$theDate',
                    logType='" . db::escapechars($logType) . "',
                    logValue='" . db::escapechars($logValue) . "',
                    logArea='" . db::escapechars($logArea) . "'
                    ";
            
            $result = db::execute($sql);
        }
        
        /*
         * 
         * Last Log In by a user
         * 
         */
        public function lastlogin($username){
            $sql = "SELECT * FROM klog WHERE logValue LIKE '" . db::escapechars($username) . " Successful sign in%' ORDER BY logDate DESC";
            $result = db::returnrow($sql);
            $lastlogintime =  $result['logDate']." at " .$result['logTime'];
            return $lastlogintime;
        }
        
        /*
         * return the user ID for a particular user name
         * used in several functions throughout the system
         * 
         */
        public function usernametoid($username){
            $userName = db::escapechars($username);
            $sql = "SELECT * FROM users WHERE username = '$userName'";
            $result = db::returnrow($sql);
            return $result['userID'];
        }
        
        
        /*
         * return the name of the user for the ID for a particular user name
         * used in several functions throughout the system
         * 
         */
        public function useridtoname($userid){
            $userID = db::escapechars($userid);
            $sql = "SELECT * FROM users WHERE userID = '$userID'";
            $result = db::returnrow($sql);
            return $result['firstname'] . " " . $result['surname'];
        }
        
        /*
         * Function to return the member name from the memberID passed
         * Used for various functions like groups
         */
        public function memberidtoname($userid){
            $userid = db::escapechars($userid);
            $sql = "SELECT firstname, surname FROM churchmembers WHERE memberID='$userid'";
            $result = db::returnrow($sql);
            if($result){
                return $result['firstname']." ".$result['surname'];
            }
            else{
                return false;
            }
        }
        
        /*
         * Check the user level for modules and allow or deny based on the response
         * Used for each module to re-validate the ability of a user to have access
         * to a module. This allows access levels to change without a sign out/in
         * for the user if a higher-level administrator is online, and also allows 
         * the revoking of privileges should it be necessary instantaneously.
         * 
         */
        public function checkAccessLevel($modulename){
            
            $modulename = db::escapechars($modulename);
            $userid = $this->usernametoid($_SESSION['Kusername']);
            $sql = "SELECT * FROM useraccess WHERE moduleName = '$modulename' AND userID='".$userid."'";
            $result = db::returnrow($sql);
            $moduleuserlevel = $result['userlevel'];

               if($result['userID'] == $userid){
                    return true;
                }
                else{
                    return false;
                }
            
        }
        
        /*
         * Show Module User Access - A list of access to modules for each user
         * Displays access to modules
         */
        public function showModuleUserAccess()
        {
            $response = "<table class=\"memberTable\"><tr><th>UserName</th><th>Level</th><th>Modules</th><th>&nbsp;</th></tr>";
            
            $sql = "SELECT * FROM users ORDER BY surname ASC, firstname ASC";
            $result = db::returnallrows($sql);
            foreach($result as $user){
                $response .= "<tr>";
                $response .= "<td>" . $user['username'] . "</td>";
                $response .= "<td>" . $user['userlevel'] . "</td>";
                $sysmodulesql = "SELECT * FROM useraccess WHERE userID='".$user['userID']."' ORDER BY moduleName ASC";
                $sysmodule = db::returnallrows($sysmodulesql);
                $response .= "<td>";
                $firsttoggle = 0;
                foreach($sysmodule as $module){
                    if($firsttoggle == 1){
                        $response .= " , ";
                    }
                    $response .= $module['moduleName'];
                    $firsttoggle = 1;
                }
                $response .= "</td>";
                $response .= "<td><a href=\"index.php?mid=801&u=".$user['userID']."\">Edit</a></td>";
                $response .= "</tr>";
            }
            $response .= "</table>";
            return $response;
        }
        
        /*
         * Get module access form for a specific user
         * 
         * 
         */
        public function createModuleAccessForm($userid)
        {
            $response = "<form name=\"moduleprivs\" action=\"index.php\" method=\"post\">
                        <input type=\"hidden\" name=\"u\" id=\"u\" value=\"$userid\" />
                        <input type=\"hidden\" name=\"mid\" id=\"mid\" value=\"801\" />
                        <input type=\"hidden\" name=\"function\" id=\"function\" value=\"update\" />";
            
            $sql = "SELECT * FROM kmodules ORDER BY moduleName ASC";
            $result = db::returnallrows($sql);
            foreach($result as $module){
                $response .="<p><input type=\"hidden\" name=\"moduleName[]\" id=\"moduleName[]\" value=\"".$module['moduleName']."\" />
                <label>".$module['moduleName']."</label>
                <select name=\"accessVal[]\" id=\"accessVal[]\">";
                    $sql = "SELECT * FROM useraccess WHERE userID='".$userid."' AND moduleName='".$module['moduleName']."'";
                    $numrows = 0;
                    $numrows = db::getnumrows($sql);
                    if($numrows >0){
                        $response .="<option value=\"1\" default>Access</option>                  
                                    <option value=\"0\">No Access</option>";
                    }
                    else{
                        $response .="<option value=\"0\" default>No Access</option>                    
                                    <option value=\"1\">Access</option>";
                    }
                $response .="</select>
                </p>
                ";
            }
            
            $response .= "<input type=\"submit\" value=\"Save Changes\" />";
            $response .= "</form>";
            
            return $response;
        }
        
        /*
         * Update User module access privs
         * for each of the incomming modules
         */
        public function updateModuleAccess($userid, $moduleName, $accessVal)
        {
            $errorFlag = 0;
            // Loop through all incomming objects
            for($i=0; $i<count($moduleName); $i++){
                if($moduleName[$i] != ""){
                    // Check the type of access
                    if($accessVal[$i] == '0'){
                        // Remove access from the module
                        $sql = "DELETE FROM useraccess WHERE userID='".db::escapechars($userid)."' AND moduleName='".db::escapechars($moduleName[$i])."'";
                        $rundelete = db::execute($sql);
                        if(!$rundelete){
                            $errorFlag = 1;
                        }
                    }
                    else{
                        // Access granted to module - check if access already granted
                        if($accessVal[$i] == '1'){
                            $sql = "SELECT * FROM useraccess WHERE userID='".db::escapechars($userid)."' AND moduleName='".db::escapechars($moduleName[$i])."'";
                            $numrows = 0;
                            $numrows = db::getnumrows($sql);
                            // if no existing acces, run the insert
                            if($numrows == 0){
                                $insertsql = "INSERT INTO useraccess SET userID='".$userid."', moduleName='".$moduleName[$i]."'";
                                $runinsert = db::execute($insertsql);
                                if(!$runinsert){
                                    $errorFlag = 1;
                                }
                            }
                        }
                    }
                } 
            }
            // return to called area
            if($errorFlag == 0){
                return true;
            }
            else{
                return false;
            }
        }
        
        
        /*
        * Generate Date and Time field input boxes for any forms requiring them
        * across the whole appication 
        */
        public function generateTimeDateInput(){

            print "Date: ";
            // Create the Day input field
            print "<select name=\"dateDay\" id=\"dateDate\">";
            print "<option value=\"--\">--</option>";
                for($i=1;$i<=31;$i++){
                    print "<option value=\"$i\">$i</option>";
                }
            print "</select>";

            // Create the Month input field
            print "<select name=\"dateMonth\" id=\"dateMonth\">";
                print "<option value=\"--\">--</option>";
                print "<option value=\"01\">January</option>";
                print "<option value=\"02\">February</option>";
                print "<option value=\"03\">March</option>";
                print "<option value=\"04\">April</option>";
                print "<option value=\"05\">May</option>";
                print "<option value=\"06\">June</option>";
                print "<option value=\"07\">July</option>";
                print "<option value=\"08\">August</option>";
                print "<option value=\"09\">September</option>";
                print "<option value=\"10\">October</option>";
                print "<option value=\"11\">November</option>";
                print "<option value=\"12\">December</option>";
            print "</select>";

            // Create the Day input field
            print "<select name=\"dateYear\" id=\"dateYear\">";
                print "<option value=\"--\">--</option>";
                $theyear = date('Y');
                for($i=1;$i<10;$i++){
                    print "<option value=\"$theyear\">$theyear</option>";
                    $theyear++;
                }
            print "</select>";

            print "<br/>";
            print "Time: ";
            // Create the Hour input field
            print "<select name=\"dateHour\" id=\"dateHour\">";
                print "<option value=\"--\">--</option>";
                for($i=1;$i<=24;$i++){
                    if($i < 10){
                        print "<option value=\"0$i\">0$i</option>";
                    }
                    else{
                        print "<option value=\"$i\">$i</option>";
                    }
                }
            print "</select>";

            // Create the Minute input field
            print "<select name=\"dateMinute\" id=\"dateMinute\">";
            print "<option value=\"--\">--</option>";
                for($i=0;$i<=59;$i++){
                    if($i < 10){
                        print "<option value=\"0$i\">0$i</option>";
                    }
                    else{
                        print "<option value=\"$i\">$i</option>";
                    }
                }
            print "</select>";

            print "<br/>";
        }
    
        
        
        /*
         * 
         * Phone Home to check for new updates of the software
         * 
         */
        public function checkForUpdates()
        {
            
            // Run a call back to the PizzaBoxSoftware site to check for current versions 
            $xml = simplexml_load_file("http://www.pizzaboxsoftware.co.uk/kongreg8os2/updates/current.xml");

            print $xml->getName() . "<br />";

            foreach($xml->children() as $child)
            {
                print $child->getName() . ": " . $child . "<br/>";
                if($child->getName() == "version")
                {
                    $myversion = $child;
                }
            }
            return $myversion;
            
            
        }
        
        /*
         * Get error overview for the system - any major errors that have occurred in the last 7 days
         * 
         * 
         */
        public function getErrorOverview()
        {
            // Today and last week variables for SQL
            $today = date('Y-m-d');
            $lastweek = date('Y-m-d', strtotime("-1 week"));
                
            $sql = "SELECT * FROM kerrorlog WHERE logDate >= $lastweek AND logDate <= $today";
            $numitems = db::getnumrows($sql);
            print "<p>There have been $numitems errors caught in the last 7 days by the application framework</p>";
            return;
        }
        
        
        /*
         * Change password function
         * 
         */
        public function changePassword($oldpass, $newpass)
        {
            $oldpass = md5($oldpass);
            $newpass = md5($newpass);
            
            if($oldpass == $_SESSION['Kpasswd']){
                
                $sql = "UPDATE users SET password='".$newpass."' WHERE username='".$_SESSION['Kusername']."' LIMIT 1";
                $result = db::execute($sql);
                if($result){
                    $_SESSION['Kpasswd'] = $newpass;
                    // Log the activity
                        $logType = "Account Update";
                        $logValue = $_SESSION['Kusername']." changed their password";
                        $logArea = "Account";				
                        $this->logevent($logType, $logValue, $logArea);
                    return true;
                }
                else{
                    // Log the failure
                        $logType = "Update Fail";
                        $logValue = $_SESSION['Kusername']." failed updating password ".db::escapechars($sql);
                        $logArea = "Account";				
                        $this->logerror($logType, $logValue, $logArea);
                    return false;
                }
            }
            else{
                return false;
            }
            
        }
        
        
        /*
         * 
         * Function to check for htaccess files in correct locations
         * Creates the files if they do not exist and warns the user
         * Used to secure folders from direct access by web users
         */
        public function checkhtaccess()
        {
            $appPath = "application/.htaccess";
            $modPath = "modules/.htaccess";
            
            if(!file_exists($appPath)){
                print "<strong>htaccess file missing from application path - creating now</strong> ";
                $fh = fopen($appPath, 'w') or print("can't create file");
                $accessText = "order allow,deny\nallow from 127.0.0.1\ndeny from all\n";
                fwrite($fh, $accessText);
                fclose($fh);
                print "<br/>Written";
            }
            else{
                print "htaccess application file exists<br/>";
            }
            if(!file_exists($modPath)){
                print "<strong>htaccess file missing from module path - creating now</strong> ";
                $fh = fopen($modPath, 'w') or print("can't create file");
                $accessText = "order allow,deny\nallow from 127.0.0.1\ndeny from all\n";
                fwrite($fh, $accessText);
                fclose($fh);
                print "<br/>Written";
            }
            else{
                print "htaccess module file exists<br/>";
            }
            return;
        }
        
        
     /*
     * Function to allow the downloading of a file
     * 
     */
    public function downloadExport($filename,$checksum)
    {
        
        $filename = db::escapechars($filename);
        $checksum = db::escapechars($checksum);
        $filepath = "application/export/files/";
        $myfile = $filepath . $filename;
        if(md5($myfile) == $checksum){
            if(file_exists($myfile)){
                $fsize = filesize($myfile);
                header("Content-disposition: filename=$filename"); 
                header('Content-type: application/octet-stream'); 
                header("Content-length: $fsize");
                $fd = fopen ($myfile, "r");
                
                while(!feof($fd)) {
                    $buffer = fread($fd, 2048);
                    echo $buffer;
                } 
                
                return true;
            }
            else{
                return "File not found";
            }
        }
        else{
            print "Incorrect link";
            return false;
        }
        
    }
    
     /*
     * Function to allow the downloading of a backup file
     * 
     */
    public function downloadBackup($filename,$checksum)
    {
        
        $filename = db::escapechars($filename);
        $checksum = db::escapechars($checksum);
        $filepath = "application/backup/files/";
        $myfile = $filepath . $filename;
        if(md5($myfile) == $checksum){
            if(file_exists($myfile)){
                $fsize = filesize($myfile);
                header("Content-disposition: filename=$filename"); 
                header('Content-type: application/octet-stream'); 
                header("Content-length: $fsize");
                $fd = fopen ($myfile, "r");
                
                while(!feof($fd)) {
                    $buffer = fread($fd, 2048);
                    echo $buffer;
                } 
                
                return true;
            }
            else{
                return "File not found";
            }
        }
        else{
            print "Incorrect link";
            return false;
        }
        
    }
    
    
    /*
     * User Control Mechanism
     * View System Users
     */
    public function displayUsers()
    {
        $sql = "SELECT * FROM users ORDER BY userlevel DESC";
        $result = db::returnallrows($sql);
        if(db::getnumrows($sql)>0){
            $output = "<table class=\"firewalltable\"><tr><th>Username</th><th>Surname</th><th>Firstname</th><th>User Level</th><th>Action</th></tr>";
            foreach($result as $user){
                
                $output .= "<tr>";
                $output .= "<td>".$user['username']."</td>";
                $output .= "<td>".$user['surname']."</td>";
                $output .= "<td>".$user['firstname']."</td>";
                $output .= "<td>".$user['userlevel']."</td>";
                $output .= "<td><a href=\"index.php?mid=950&action=edit&u=".$user['userID']."\" class=\"runbutton\">Edit</a>
                            <a href=\"index.php?mid=950&action=remove&u=".$user['userID']."\" class=\"delbutton\">Delete</a>
                            </td>";
                $output .= "</tr>";
                
            }
            $output .= "</table>";
        }
        else{
            $output = "<p>There are no users to list</p>";
        }
        return $output;
    }
    
    /*
     * User Control Mechanism
     * Add a system user
     */
    public function addUser($username, $password, $firstname, $surname, $userlevel, $campus, $emailaddress)
    {
        $username = db::escapechars($username);
        $firstname = db::escapechars($firstname);
        $surname = db::escapechars($surname);
        $userlevel = db::escapechars($userlevel);
        $password = md5(db::escapechars($password));
        $campus = db::escapechars($campus);
        $emailaddress = db::escapechars($emailaddress);
        
        // Verify there isn't a duplicate username
        $sql = "SELECT * FROM users WHERE username='$username'";
        $exists = 0;
        $exists = db::getnumrows($sql);
        if($exists == 0){
            $sql = "INSERT INTO users SET 
                    username='$username',
                    firstname='$firstname',
                    surname='$surname',
                    password='$password',
                    userlevel='$userlevel',
                    emailaddress='$emailaddress',
                    campus='$campus'
                    ";
            $result = db::execute($sql);
            if($result){
                // Insert all the privs for the user in the useraccess table
                $lastid = db::getlastid();
                $modulesql = "SELECT * FROM kmodules";
                $item = db::returnallrows($modulesql);
                // for each module in the system, enter access if your level is greater than or equal to default levels
                foreach($item as $module){
                    if($module['userlevel'] >= $userlevel){
                        $sql = "INSERT INTO useraccess SET userID='".$lastid."', moduleName='".$module['moduleName']."'";
                        $privinsert = db::execute($sql);
                    }
                }
                
                return true;
            }
            else{
                return false;
            }
        }
        else{
            return 'username already exists';
        }
    }
    
    /*
     * Remove a user from the system
     * 
     */
    public function removeUser($userid){
        $userid = db::escapechars($userid);
        
        if($userid !=""){
            $sql = "DELETE FROM users WHERE userID='$userid' LIMIT 1";
            $result = db::execute($sql);
            if($result){
                $sql = "DELETE FROM useraccess WHERE userID='$userid'";
                $result = db::execute($sql);
                return true;
            }
            else{
                return false;
            }
        }
        else{
            return false;
        }
    }
    
    
    /*
     * Create Edit a User Form
     * 
     */
    public function editUserForm($userID)
    {
        $sql = "SELECT * FROM users WHERE userID='".db::escapechars($userID)."'";
        $userinfo = db::returnrow($sql);
        
        $formoutput = "
        <form name=\"adduser\" action=\"index.php\" method=\"post\" >
            <input type=\"hidden\" name=\"mid\" id=\"mid\" value=\"950\" />
            <input type=\"hidden\" name=\"action\" id=\"action\" value=\"edit\" />
            <input type=\"hidden\" name=\"save\" id=\"save\" value=\"true\" />
            <input type=\"hidden\" name=\"u\" id=\"u\" value=\"".$userinfo['userID']."\" />
            <label for=\"uname\">Username:</label>
            <input type=\"text\" name=\"uname\" id=\"uname\" value=\"".$userinfo['username']."\" />
            <label for=\"fname\">First name:</label>
            <input type=\"text\" name=\"fname\" id=\"fname\" value=\"".$userinfo['firstname']."\" />
            <label for=\"sname\">Surname:</label>
            <input type=\"text\" name=\"sname\" id=\"sname\" value=\"".$userinfo['surname']."\" />
            <label for=\"pword1\">Password:</label>
            <input type=\"password\" name=\"pword1\" id=\"pword1\" />
            <label for=\"pword2\">Verify:</label>
            <input type=\"password\" name=\"pword2\" id=\"pword2\" />
            <label for=\"userlevel\">User level:</label>
            <select name=\"userlevel\">
                <option default value=\"".$userinfo['userlevel']."\">Current (".$userinfo['userlevel'].")</option>
                <option value=\"1\">1 (lowest)</option>
                <option value=\"2\">2</option>
                <option value=\"3\">3</option>
                <option value=\"4\">4</option>
                <option value=\"5\">5 (sys admin)</option>
            </select>
            <label for=\"email\">Email Address:</label>
            <input type=\"text\" name=\"email\" id=\"email\" value=\"".$userinfo['emailaddress']."\" />
            <label for=\"campus\">Campus: </label>
            <select name=\"campus\" id=\"campus\">
                <option value=\"".$userinfo['campus']."\">Current (".$userinfo['campus'].")</option>
                <option value=\"all\">All</option>";
                 
        $formoutput .= memberControl::getCampusList();
            
        $formoutput .="</select>
            <label for=\"submit\">&nbsp;</label>
            <input type=\"submit\" name=\"submit\" id=\"submit\" value=\"Save\" />
        </form>";
        
        return $formoutput;
    }
    
    /*
     * Save Edited user information
     */
    public function editUser($userID, $username, $firstname, $surname, $password='', $userlevel, $email, $campus)
    {
        $userID = db::escapechars($userID);
        $username = db::escapechars($username);
        $firstname = db::escapechars($firstname);
        $surname = db::escapechars($surname);
        $password = db::escapechars($password);
        $userlevel = db::escapechars($userlevel);
        $email = db::escapechars($email);
        $campus = db::escapechars($campus);
        
        $sql = "UPDATE users SET
                username = \"".$username."\",
                firstname = \"".$firstname."\",
                surname = \"".$surname."\",
                userlevel = \"".$userlevel."\",
                emailaddress = \"".$email."\",
                campus = \"".$campus."\"";
        if($password !=""){
            $sql .= ", password=\"".md5($password)."\"";
        }
        
        $sql .= " WHERE userID=\"".$userID."\" LIMIT 1";
        
        $result = db::execute($sql);
        if($result){
            return true;
        }
        else{
            return false;
        }
                
    }
    
    /*
     * Campus Drop Down list for various areas
     * 
     */
    public function viewCampusDropdown()
    {
            $campuses = "";
            $sql = "SELECT * FROM campus ORDER BY campusName ASC";
            $result = db::returnallrows($sql);
            if(db::getNumRows($sql)>0){
                $campuses = "<select name=\"campus\" id=\"campus\">";
                foreach($result as $campus){
                    $campuses .= "<option value='".$campus['campusid']."'>".$campus['campusName']."</option>";
                }
                $campuses .= "</select>";
            }
            return $campuses;

     }
        
        
        
        
    /*
     * Settings Form to view and modify the settings
     * 
     */
    public function displaySettingsForm()
    {
        $sql = "SELECT * FROM settings";
        $result = db::returnallrows($sql);
        if(db::getnumrows($sql) >0){
            $returndata .= "<form name=\"settings\" action=\"index.php\" method=\"post\">";
            $returndata .= "<table class=\"reportsTable\">";
            foreach($result as $setting){
                $returndata .=  "<tr>";
                $returndata .= "<td>".$setting['settingName'];
                $returndata .= "<input type=\"hidden\" name=\"settingname[]\" id=\"settingname[]\" value=\"".$setting['settingName']."\">";
                $returndata .= "<td><input type=\"text\" name=\"settingvalue[]\" id=\"settingvalue[]\" value=\"".$setting['settingValue']."\"></td>";
                $returndata .= "</tr>";
            }
            $returndata .= "<tr><td><input type=\"submit\" value=\"Save\"></td></tr>";
            $returndata .= "</table>";
            $returndata .= "<input type=\"hidden\" name=\"mid\" id=\"mid\" value=\"909\">";
            $returndata .= "<input type=\"hidden\" name=\"save\" id=\"save\" value=\"true\">";
            $returndata .= "</form>";
            echo $returndata;
        }
        else{
            echo "<p>Settings not found!!</p>";
        }
        return;
    }
    
    /*
     * Settings Update from the form
     * 
     */
    public function updateSettings($settingname, $settingvalue)
    {
        // For each of the post settings, update the values
        $error = 0;
        for($x=0; $x<=count($settingname); $x++){
            $sql = "UPDATE settings SET settingValue='".db::escapechars($settingvalue[$x])."' WHERE settingName='".db::escapechars($settingname[$x])."' LIMIT 1";
            $update = db::execute($sql);
            if(!$update){
                $error = 1;
            }
        }
        if($error == 1){
            $message = $_SESSION['Kusername'].' tried to update settings but this failed from '.$this->getIP();
            $this->logerror('Settings', $message, 'Settings');
            return false;
        }
        else{
            
            return true;
        }
        
    }
    
    
    /*
     * Module Control 
     * Allows the setting of different access levels to modules
     * 
     */
    public function displayModulesForm()
    {
        $sql = "SELECT * FROM kmodules";
        $result = db::returnallrows($sql);
        if(db::getnumrows($sql) >0){
            $returndata .= "<form name=\"settings\" action=\"index.php\" method=\"post\">";
            $returndata .= "<table class=\"reportsTable\">";
            $returndata .= "<tr><th>Module</th><th>Minimum Access Level</th></tr>";
            foreach($result as $module){
                $returndata .=  "<tr>";
                $returndata .= "<td>".$module['moduleName'];
                $returndata .= "<input type=\"hidden\" name=\"modulename[]\" id=\"modulename[]\" value=\"".$module['moduleName']."\">";
                $returndata .= "<td><input type=\"text\" name=\"modulevalue[]\" id=\"modulevalue[]\" value=\"".$module['userlevel']."\"></td>";
                $returndata .= "</tr>";
            }
            $returndata .= "<tr><td><input type=\"submit\" value=\"Save\"></td></tr>";
            $returndata .= "</table>";
            $returndata .= "<input type=\"hidden\" name=\"mid\" id=\"mid\" value=\"800\">";
            $returndata .= "<input type=\"hidden\" name=\"save\" id=\"save\" value=\"true\">";
            $returndata .= "</form>";
            echo $returndata;
        }
        else{
            $this->logerror('Module Control', 'Could not find modules!', 'Module Control');
            echo "<p>Modules not found!!</p>";
        }
        return;
    }
    
    /*
     * Settings Update from the form
     * 
     */
    public function updateModules($modulename, $modulevalue)
    {
        // For each of the post settings, update the values
        $error = 0;
        for($x=0; $x<=count($modulename); $x++){
            $sql = "UPDATE kmodules SET userlevel='".db::escapechars($modulevalue[$x])."' WHERE moduleName='".db::escapechars($modulename[$x])."' LIMIT 1";
            $update = db::execute($sql);
            if(!$update){
                $error = 1;
            }
        }
        if($error == 1){
            $logmessage =  $_SESSION['Kusername'].' Could not update modules from '.$this->getIP();
            $this->logerror('Module Control', $logmessage, 'Module Control');
            echo "<p>Modules not found!!</p>";
            return false;
        }
        else{
            $logmessage =  $_SESSION['Kusername'].' Updated modules from '.$this->getIP();
            $this->logevent('Module Control', $logmessage, 'Module Control');
            return true;
        }
        
    }
    
    
    /*
     * Function to send email from the system
     * Used for group communications - sends anything in the queue
     * by a specific user
     */
    public function sendEmail($userID)
    {
        $userID = db::escapechars($userID);
        
        $sql = "SELECT * FROM emailtemp WHERE userID=$userID AND mailstatus = 'pending'";
        $result = db::returnallrows($sql);
        foreach ($result as $email){
            $to = $email['sendto'];
            $subject = $email['thesubject'];
            $plainmessage = $email['thebody'];
            
            $headers = $this->prettyEmail($plainmessage, $subject);
            $mailID = $email['mailID'];
            
            $sendmail = mail($to, $subject, '', $headers);
            if($sendmail){
                $sql2 = "UPDATE emailtemp SET mailstatus='sent' WHERE mailID='".$mailID."' LIMIT 1";
                $updatetemp = db::execute($sql2);
            }
            else{
                $errortoggle = 1;
            }
            
            
        }
        
        // verify state and return
        if($errortoggle == 1){
            $errorlog = 'Could not send email campaign correctly. Some errors logged by '.$_SESSION['Kusername'];
            $this->logerror('Email System', $errorlog , 'Email');
            return false;
        }
        else{
            $logmessage = 'Email sent by '.$_SESSION['Kusername'];
            $this->logevent('Email System', $logmessage, 'Email');
            return true;
        }
    }
        /*
         * Function to beautify the email sent from the system
         * 
         */
        
        public function prettyEmail($mymessage, $subject)
        {
            
          // SET UP SERVER INFORMATION 
            $sql = "SELECT * FROM settings WHERE settingName='commsEmail'";
            $result = db::returnrow($sql);
            $serverFrom = $result['settingValue'];
            
            $sql = "SELECT * FROM settings WHERE settingName='emailFooter'";
            $result = db::returnrow($sql);
            $serverFooter = $result['settingValue'];
            
            $sql = "SELECT * FROM settings WHERE settingName='licensedto'";
            $result = db::returnrow($sql);
            $serverApp = $result['settingValue'];
                        
                // PLAIN TEXT INFORMATION FOR EMAIL
                $bodyt = $mymessage;

                // HTML BODY FOR EMAIL
                $bodyh = "
                      <html><head><title>".$subject."</title></head>
                      <body><p style=\"font-size: 24pt; font-family: helvetica, arial, sans-serif; background-color: #336699; color: #fff; display: block; width: 100%; padding: 15px;\">" . $subject . "</p>
                            <p>".$mymessage."</p>
                            <p><strong>". $serverApp.".<br/>".$serverFooter."</p>
                            <p style=\"font-size: 18pt; font-family: helvetica, arial, sans-serif; background-color: #336699; color: #fff; display: block; width: 100%; padding: 15px;\">Kongreg8<br/>
                            <span style=\"font-size: 12pt; font-family: helvetica, arial, sans-serif;\">Church Member Database System from PizzaBoxSoftware.co.uk</span></p>
                       </body></html>";


                // Main settings for the email
                $semi_rand = md5(time());
                $mime_boundary = "MULTIPART_BOUNDARY_$semi_rand";

                $fromemailsetting = "From: ".$serverFrom;
                $replyemailsetting = "Reply-To: ".$serverFrom;
                $returnpath = "Return-Path:<".$serverFrom.">";

                // Start Email content (all in header for multi part encoding 

                $headers =  $fromemailsetting . "\n" . $replyemailsetting  . "\n" . $returnpath . "\n". 'X-Mailer: PHP/' . phpversion() . "\n";

                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: multipart/alternative; boundary=" . $mime_boundary . "\r\n";
                $headers .= "\n--$mime_boundary\n";
                $headers .= "Content-Type: text/plain; charset=iso-8859-1\r\n";
                $headers .= "Content-Transfer-Encoding: 7bit\r\n";
                $headers .= "$bodyt";
                $headers .= "\n--$mime_boundary\n";
                $headers .= "Content-Type: text/html; charset=iso-8859-1\r\n";
                $headers .= "Content-Transfer-Encoding: 7bit\r\n";
                $headers .= "$bodyh";
            
            return $headers;
        }
    
    
    
}

?>
