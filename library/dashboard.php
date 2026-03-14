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
             
             <div class="row">


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
 $sid=$_SESSION['stdid'];
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
