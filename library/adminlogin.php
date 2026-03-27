<?php
session_start();
error_reporting(0);
include('includes/config.php');

if(!empty($_SESSION['alogin'])){
header('location:admin/dashboard.php');
exit;
}

$loginError='';
if(isset($_POST['login']))
{
$username=trim($_POST['username']);
$password=md5($_POST['password']);
$sql ="SELECT UserName,Password FROM admin WHERE UserName=:username and Password=:password";
$query= $dbh -> prepare($sql);
$query-> bindParam(':username', $username, PDO::PARAM_STR);
$query-> bindParam(':password', $password, PDO::PARAM_STR);
$query-> execute();
if($query->rowCount() > 0)
{
$_SESSION['alogin']=$username;
header('location:admin/dashboard.php');
exit;
}
else
{
$loginError='Invalid admin username or password.';
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animated Admin Login</title>
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
          background: #111;
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
          font-size: 1.2em;
          color: #fff;
          box-shadow: none;
          outline: none;
        }

        .login .inputBx input[type="submit"] {
          width: 100%;
          background: #0078ff;
          background: linear-gradient(45deg, #ff357a, #fff172);
          border: none;
          cursor: pointer;
          color: #111;
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
          padding: 0 6px;
          gap: 10px;
        }

        .login .links a {
          color: #fff;
          text-decoration: none;
          font-size: 13px;
        }

        .admin-note {
          width: 100%;
          color: rgba(255, 255, 255, 0.88);
          font-size: 13px;
          line-height: 1.7;
          text-align: center;
        }

        .admin-note strong {
          color: #fff;
        }

        .login-error {
          width: 100%;
          padding: 10px 14px;
          border-radius: 18px;
          background: rgba(255, 0, 87, 0.18);
          border: 1px solid rgba(255, 0, 87, 0.45);
          color: #fff;
          text-align: center;
          font-size: 13px;
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
    <i style="--clr:#00ff0a;"></i>
    <i style="--clr:#ff0057;"></i>
    <i style="--clr:#fffd44;"></i>
    <div class="login">
      <h2>Login</h2>
<?php if($loginError!==''){ ?>
      <div class="login-error"><?php echo htmlentities($loginError);?></div>
<?php } ?>
      <form method="post" style="width:100%;">
        <div class="inputBx">
          <input type="text" name="username" placeholder="Username" required>
        </div>
        <div class="inputBx">
          <input type="password" name="password" placeholder="Password" required>
        </div>
        <div class="inputBx">
          <input type="submit" name="login" value="Sign in">
        </div>
      </form>
      <div class="admin-note">
        <strong>Admin only</strong><br>
        Username: admin<br>
        Password: Test@123
      </div>
      <div class="links">
        <a href="index.php">User Login</a>
        <a href="signup.php">Signup</a>
      </div>
    </div>
  </div>
</body>
</html>
