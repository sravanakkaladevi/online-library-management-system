<?php
session_start();
error_reporting(0);
include('includes/config.php');
if(empty($_SESSION['login']) || empty($_SESSION['stdid']))
  { 
unset($_SESSION['login']);
unset($_SESSION['stdid']);
header('location:index.php');
exit;
}
else{?>
<?php
$sid=$_SESSION['stdid'];
$studentName='Reader';
$studentEmail='';
$profileQuery=$dbh->prepare("SELECT FullName,EmailId FROM tblstudents WHERE StudentId=:sid LIMIT 1");
$profileQuery->bindParam(':sid',$sid,PDO::PARAM_STR);
$profileQuery->execute();
$profileRow=$profileQuery->fetch(PDO::FETCH_ASSOC);
if($profileRow)
{
$studentName=trim((string)$profileRow['FullName'])!=='' ? trim((string)$profileRow['FullName']) : 'Reader';
$studentEmail=(string)$profileRow['EmailId'];
}
$nameParts=preg_split('/\s+/', trim((string)$studentName));
$studentInitials='';
foreach($nameParts as $namePart)
{
if($namePart!=='')
{
$studentInitials.=strtoupper(substr($namePart,0,1));
}
if(strlen($studentInitials)>=2)
{
break;
}
}
if($studentInitials==='')
{
$studentInitials='R';
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Online Library Management System | User Dash Board</title>
    <!-- BOOTSTRAP CORE STYLE  -->
    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <!-- FONT AWESOME STYLE  -->
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <!-- CUSTOM STYLE  -->
    <link href="assets/css/style.css" rel="stylesheet" />
    <!-- GOOGLE FONT -->
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
    <style type="text/css">
        .dashboard-welcome {
            position: relative;
            overflow: hidden;
            margin-bottom: 28px;
            padding: 26px 28px;
            border-radius: 28px;
            background: linear-gradient(135deg, #eff6ff 0%, #ffffff 45%, #eefbf5 100%);
            border: 1px solid #d9e8f7;
            box-shadow: 0 22px 50px rgba(15, 23, 42, 0.08);
        }

        .dashboard-welcome:before,
        .dashboard-welcome:after {
            content: "";
            position: absolute;
            border-radius: 999px;
            filter: blur(8px);
            opacity: 0.55;
            animation: welcomeFloat 7s ease-in-out infinite;
        }

        .dashboard-welcome:before {
            width: 180px;
            height: 180px;
            right: -50px;
            top: -40px;
            background: rgba(37, 99, 235, 0.14);
        }

        .dashboard-welcome:after {
            width: 130px;
            height: 130px;
            left: -30px;
            bottom: -35px;
            background: rgba(16, 185, 129, 0.16);
            animation-delay: -2s;
        }

        .dashboard-welcome__content {
            position: relative;
            z-index: 1;
        }

        .dashboard-welcome__title {
            margin: 0 0 8px;
            font-size: 34px;
            font-weight: 800;
            color: #16324f;
            animation: welcomeRise 0.8s ease-out;
        }

        .dashboard-welcome__text {
            margin: 0;
            max-width: 700px;
            font-size: 16px;
            color: #4b637b;
            animation: welcomeRise 1s ease-out;
        }

        .dashboard-profile-card {
            position: relative;
            overflow: hidden;
            min-height: 233px;
            border-radius: 24px;
            padding: 26px;
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 55%, #38bdf8 100%);
            color: #fff;
            box-shadow: 0 24px 50px rgba(37, 99, 235, 0.24);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-bottom: 15px;
        }

        .dashboard-profile-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 30px 56px rgba(37, 99, 235, 0.28);
        }

        .dashboard-profile-card__avatar {
            width: 74px;
            height: 74px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.18);
            border: 1px solid rgba(255,255,255,0.25);
            font-size: 28px;
            margin-bottom: 18px;
        }

        .dashboard-profile-card__btn {
            display: inline-block;
            margin-top: 12px;
            padding: 10px 18px;
            border-radius: 999px;
            background: #fff;
            color: #1d4ed8;
            font-weight: 700;
            text-decoration: none !important;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.14);
        }

        @keyframes welcomeFloat {
            0%, 100% { transform: translateY(0px) translateX(0px); }
            50% { transform: translateY(10px) translateX(-8px); }
        }

        @keyframes welcomeRise {
            0% { opacity: 0; transform: translateY(16px); }
            100% { opacity: 1; transform: translateY(0); }
        }
    </style>

</head>
<body>
      <!------MENU SECTION START-->
<?php include('includes/header.php');?>
<!-- MENU SECTION END-->
    <div class="content-wrapper">
         <div class="container">
        <div class="row pad-botm">
            <div class="col-md-12">
                <h4 class="header-line">User DASHBOARD</h4>
                
                            </div>

        </div>

        <div class="dashboard-welcome">
            <div class="dashboard-welcome__content">
                <h2 class="dashboard-welcome__title">Welcome, <?php echo htmlentities($studentName);?></h2>
                <p class="dashboard-welcome__text">Your library dashboard is ready. Track reading, orders, requests, and open your profile quickly from here.</p>
            </div>
        </div>

             <div class="row">

<div class="col-md-4 col-sm-4 col-xs-12">
<div class="dashboard-profile-card">
    <div class="dashboard-profile-card__avatar"><?php echo htmlentities($studentInitials);?></div>
    <h3 style="margin-top:0;"><?php echo htmlentities($studentName);?></h3>
    <p style="opacity:0.92;"><?php echo htmlentities($studentEmail);?></p>
    <p>Open your profile to update account details whenever you need.</p>
    <a href="my-profile.php" class="dashboard-profile-card__btn"><i class="fa fa-user"></i> Open Profile</a>
</div>
</div>

<a href="listed-books.php">
<div class="col-md-4 col-sm-4 col-xs-6">
 <div class="alert alert-success back-widget-set text-center">
 <i class="fa fa-book fa-5x"></i>
<?php 
$sql ="SELECT id from tblbooks ";
$query = $dbh -> prepare($sql);
$query->execute();
$results=$query->fetchAll(PDO::FETCH_OBJ);
$listdbooks=$query->rowCount();
?>
<h3><?php echo htmlentities($listdbooks);?></h3>
Books Listed
</div></div></a>
             
               <div class="col-md-4 col-sm-4 col-xs-6">
                      <div class="alert alert-warning back-widget-set text-center">
                            <i class="fa fa-recycle fa-5x"></i>
<?php 
$rsts=0;
$sql2 ="SELECT id from tblissuedbookdetails where StudentID=:sid and (RetrunStatus=:rsts || RetrunStatus is null || RetrunStatus='')";
$query2 = $dbh -> prepare($sql2);
$query2->bindParam(':sid',$sid,PDO::PARAM_STR);
$query2->bindParam(':rsts',$rsts,PDO::PARAM_STR);
$query2->execute();
$results2=$query2->fetchAll(PDO::FETCH_OBJ);
$returnedbooks=$query2->rowCount();
?>

                            <h3><?php echo htmlentities($returnedbooks);?></h3>
                          Books Not Returned Yet
                        </div>
                    </div>

<?php 

$ret =$dbh -> prepare("SELECT id from tblissuedbookdetails where StudentID=:sid");
$ret->bindParam(':sid',$sid,PDO::PARAM_STR);
$ret->execute();
$results22=$ret->fetchAll(PDO::FETCH_OBJ);
$totalissuedbook=$ret->rowCount();
?>


<a href="issued-books.php">
<div class="col-md-4 col-sm-4 col-xs-6">
 <div class="alert alert-success back-widget-set text-center">
 <i class="fa fa-book fa-5x"></i>
      <h3><?php echo htmlentities($totalissuedbook);?></h3>
Total Issued Books
</div></div></a>





        </div>    
        <div class="row">
<?php
$cartQuery=$dbh->prepare("SELECT COALESCE(SUM(Quantity),0) AS cartItems FROM tblcart WHERE StudentId=:sid");
$cartQuery->bindParam(':sid',$sid,PDO::PARAM_STR);
$cartQuery->execute();
$cartData=$cartQuery->fetch(PDO::FETCH_ASSOC);
$cartItems=$cartData ? (int)$cartData['cartItems'] : 0;

$orderQuery=$dbh->prepare("SELECT id FROM tblorders WHERE StudentId=:sid");
$orderQuery->bindParam(':sid',$sid,PDO::PARAM_STR);
$orderQuery->execute();
$orderCount=$orderQuery->rowCount();

$requestQuery=$dbh->prepare("SELECT id FROM tblbookrequests WHERE StudentId=:sid AND Status=0");
$requestQuery->bindParam(':sid',$sid,PDO::PARAM_STR);
$requestQuery->execute();
$pendingRequestCount=$requestQuery->rowCount();
?>
<a href="cart.php">
<div class="col-md-4 col-sm-4 col-xs-6">
 <div class="alert alert-info back-widget-set text-center">
 <i class="fa fa-shopping-cart fa-5x"></i>
      <h3><?php echo htmlentities($cartItems);?></h3>
Cart Items
</div></div></a>

<a href="my-orders.php">
<div class="col-md-4 col-sm-4 col-xs-6">
 <div class="alert alert-success back-widget-set text-center">
 <i class="fa fa-credit-card fa-5x"></i>
      <h3><?php echo htmlentities($orderCount);?></h3>
My Orders
</div></div></a>

<a href="book-requests.php">
<div class="col-md-4 col-sm-4 col-xs-6">
 <div class="alert alert-warning back-widget-set text-center">
 <i class="fa fa-clock-o fa-5x"></i>
      <h3><?php echo htmlentities($pendingRequestCount);?></h3>
Pending Requests
</div></div></a>
        </div>
    </div>
    </div>
     <!-- CONTENT-WRAPPER SECTION END-->
<?php include('includes/footer.php');?>
      <!-- FOOTER SECTION END-->
    <!-- JAVASCRIPT FILES PLACED AT THE BOTTOM TO REDUCE THE LOADING TIME  -->
    <!-- CORE JQUERY  -->
    <script src="assets/js/jquery-1.10.2.js"></script>
    <!-- BOOTSTRAP SCRIPTS  -->
    <script src="assets/js/bootstrap.js"></script>
      <!-- CUSTOM SCRIPTS  -->
    <script src="assets/js/custom.js"></script>
</body>
</html>
<?php } ?>
