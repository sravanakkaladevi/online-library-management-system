<?php
session_start();
error_reporting(0);
include('includes/config.php');

if (!empty($_SESSION['login']) && !empty($_SESSION['stdid'])) {
header('location:dashboard.php');
exit;
}

$loginError='';
if(isset($_POST['login']))
{
$email=$_POST['emailid'];
$password=md5($_POST['password']);
$sql ="SELECT EmailId,Password,StudentId,Status FROM tblstudents WHERE EmailId=:email and Password=:password";
$query= $dbh -> prepare($sql);
$query-> bindParam(':email', $email, PDO::PARAM_STR);
$query-> bindParam(':password', $password, PDO::PARAM_STR);
$query-> execute();
$results=$query->fetchAll(PDO::FETCH_OBJ);

if($query->rowCount() > 0)
{
 foreach ($results as $result) {
if($result->Status==1)
{
$_SESSION['stdid']=$result->StudentId;
$_SESSION['login']=$_POST['emailid'];
header('location:dashboard.php');
exit;
} else {
unset($_SESSION['stdid']);
unset($_SESSION['login']);
$loginError='Your account has been blocked. Please contact admin.';
}
}

}
else{
$loginError='Invalid email or password.';
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animated User Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        * {
          margin: 0;
          padding: 0;
          box-sizing: border-box;
          font-family: "Quicksand", sans-serif;
        }

        body {
          display: flex;
          justify-content: center;
          align-items: center;
          min-height: 100vh;
          background:
            radial-gradient(circle at top left, rgba(37, 99, 235, 0.22), transparent 32%),
            radial-gradient(circle at bottom right, rgba(14, 165, 233, 0.18), transparent 34%),
            #111;
          width: 100%;
          overflow: hidden;
        }

        .ring {
          position: relative;
          width: 500px;
          height: 500px;
          display: flex;
          justify-content: center;
          align-items: center;
        }

        .ring i {
          position: absolute;
          inset: 0;
          border: 2px solid #fff;
          transition: 0.5s;
        }

        .ring i:nth-child(1) {
          border-radius: 38% 62% 63% 37% / 41% 44% 56% 59%;
          animation: animate 6s linear infinite;
        }

        .ring i:nth-child(2) {
          border-radius: 41% 44% 56% 59%/38% 62% 63% 37%;
          animation: animate 4s linear infinite;
        }

        .ring i:nth-child(3) {
          border-radius: 41% 44% 56% 59%/38% 62% 63% 37%;
          animation: animate2 10s linear infinite;
        }

        .ring:hover i {
          border: 6px solid var(--clr);
          filter: drop-shadow(0 0 20px var(--clr));
        }

        @keyframes animate {
          0% {
            transform: rotate(0deg);
          }
          100% {
            transform: rotate(360deg);
          }
        }

        @keyframes animate2 {
          0% {
            transform: rotate(360deg);
          }
          100% {
            transform: rotate(0deg);
          }
        }

        .login {
          position: absolute;
          width: 300px;
          height: 100%;
          display: flex;
          justify-content: center;
          align-items: center;
          flex-direction: column;
          gap: 20px;
        }

        .login h2 {
          font-size: 2em;
          color: #fff;
        }

        .login .inputBx {
          position: relative;
          width: 100%;
        }

        .login .inputBx input {
          position: relative;
          width: 100%;
          padding: 12px 20px;
          background: transparent;
          border: 2px solid #fff;
          border-radius: 40px;
          font-size: 1.05em;
          color: #fff;
          box-shadow: none;
          outline: none;
        }

        .login .inputBx input[type="submit"] {
          width: 100%;
          background: linear-gradient(45deg, #2563eb, #38bdf8);
          border: none;
          cursor: pointer;
          color: #fff;
          font-weight: 700;
        }

        .login .inputBx input::placeholder {
          color: rgba(255, 255, 255, 0.75);
        }

        .login .links {
          position: relative;
          width: 100%;
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 10px;
          padding: 0 6px;
        }

        .login .links a {
          color: #fff;
          text-decoration: none;
          font-size: 13px;
        }

        .login-error {
          width: 100%;
          padding: 10px 14px;
          border-radius: 18px;
          background: rgba(239, 68, 68, 0.18);
          border: 1px solid rgba(248, 113, 113, 0.45);
          color: #fff;
          text-align: center;
          font-size: 13px;
        }

        .login-note {
          width: 100%;
          text-align: center;
          color: rgba(255,255,255,0.84);
          font-size: 13px;
          line-height: 1.7;
        }

        @media only screen and (max-width: 560px) {
          .ring {
            width: 360px;
            height: 360px;
          }

          .login {
            width: 280px;
          }
        }
    </style>
</head>
<body>
<div class="ring">
  <i style="--clr:#2563eb;"></i>
  <i style="--clr:#38bdf8;"></i>
  <i style="--clr:#7dd3fc;"></i>
  <div class="login">
    <h2>Login</h2>
<?php if($loginError!==''){ ?>
    <div class="login-error"><?php echo htmlentities($loginError);?></div>
<?php } ?>
    <form method="post" style="width:100%;">
      <div class="inputBx">
        <input type="text" name="emailid" placeholder="Email" required>
      </div>
      <div class="inputBx">
        <input type="password" name="password" placeholder="Password" required>
      </div>
      <div class="inputBx">
        <input type="submit" name="login" value="Sign in">
      </div>
    </form>
    <div class="login-note">
      User login opens the reader dashboard.<br>
      Admin uses the separate admin login page.
    </div>
    <div class="links">
      <a href="user-forgot-password.php">Forget Password</a>
      <a href="signup.php">Signup</a>
      <a href="adminlogin.php">Admin Login</a>
    </div>
  </div>
</div>
</body>
</html>
