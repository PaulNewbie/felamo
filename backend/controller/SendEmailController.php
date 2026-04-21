<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Manila');

// include(__DIR__ . '/../db/db.php');

require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


class SendEmailController
{
    // public function __construct()
    // {
    //     $this->connect();
    // }

    public function test()
    {
        echo "login";
    }

    public function SendCode($email, $code, $firstName, $lastName)
    {
        $mail = new PHPMailer(true);

        $mail->isSMTP();

        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'ugabane0516@gmail.com';
        $mail->Password = 'owwj dmzb hypq lsfu';
        $mail->Port = 465;
        $mail->SMTPSecure = 'ssl';

        $mail->setFrom('ugabane0516@gmail.com', 'Felamo');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Felamo Verification Code';
        $mail->Body = '
        <html>
        <head>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background-color: #f1f1f1;
                    padding: 20px;
                }
                .container {
                    background-color: #fff;
                    border-radius: 5px;
                    padding: 20px;
                    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
                }
                h1 {
                    color: #333;
                }
                p {
                    color: #777;
                    margin-bottom: 10px;
                }
                .verification-code {
                    font-size: 24px;
                    font-weight: bold;
                    color: #007bff;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>Felamo Verification Code</h1>
                <p>Hi ' . $firstName . ' ' . $lastName . ',</p>
                <p>Your verification code is:</p>
                <p class="verification-code">' . $code . '</p>
            </div>
        </body>
        </html>';

        if ($mail->send()) {
            echo "200";
        } else {
            echo "400";
        }
    }

    public function SendForgotPasswordCode($email, $code, $firstName, $lastName)
    {
        $mail = new PHPMailer(true);

        $mail->isSMTP();

        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'ugabane0516@gmail.com';
        $mail->Password = 'owwj dmzb hypq lsfu';
        $mail->Port = 465;
        $mail->SMTPSecure = 'ssl';

        $mail->setFrom('ugabane0516@gmail.com', 'Felamo');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Felamo Login Using OTP';
        $mail->Body = '
        <html>
        <head>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background-color: #f1f1f1;
                    padding: 20px;
                }
                .container {
                    background-color: #fff;
                    border-radius: 5px;
                    padding: 20px;
                    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
                }
                h1 {
                    color: #333;
                }
                p {
                    color: #777;
                    margin-bottom: 10px;
                }
                .verification-code {
                    font-size: 24px;
                    font-weight: bold;
                    color: #007bff;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>Felamo Login using OTP</h1>
                <p>Hi ' . $firstName . ' ' . $lastName . ',</p>
                <p>Your One Time Password is:</p>
                <p class="verification-code">' . $code . '</p>
            </div>
        </body>
        </html>';

        if ($mail->send()) {
            return "200";
        } else {
            return "400";
        }
    }
}
