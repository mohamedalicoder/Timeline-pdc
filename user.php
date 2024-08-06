<?php

session_start();
$dbhost         = 'localhost';
$dbuser         = 'root';
$dbpass         = '';
$dbname             = 'pcds';

$conn =mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: ". mysqli_connect_error($conn));
}

    $email = trim(htmlspecialchars(htmlentities($_POST['email'])));
    $password = trim(htmlspecialchars(htmlentities($_POST['password'])));

    $sql = "INSERT INTO  `user` (`email`,`password`) VALUES ('$email','$password')";

    mysqli_query($conn, $sql) or die("Error: ". mysqli_error($conn));

   if( mysqli_affected_rows($conn) === 1){
    $_SESSION["success"] = "login successfully";
    
   };






  
  header("Location: ./timeline.php");

























?>