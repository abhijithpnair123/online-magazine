<?php
// 1. Start the session to gain access to the session variables.
session_start();

// 2. Unset all of the session variables. This clears the user's login data.
$_SESSION = array();

// 3. Destroy the session itself.
session_destroy();

// 4. Redirect the user back to the login page.
header("Location: index.php");
exit(); // Ensure no further code is executed after the redirect.
?>