<?php
$password = "password"; // <- put the password that you want here
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

echo $hashedPassword;



// $inputPassword = "MySecurePassword123";
// $hashedPasswordFromDB = '$2y$10$kS0PZ9i/...'; // from your DB

// if (password_verify($inputPassword, $hashedPasswordFromDB)) {
//     echo "Password is correct!";
// } else {
//     echo "Invalid password.";
// }