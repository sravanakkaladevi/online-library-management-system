<?php
session_start();
error_reporting(0);
include('includes/config.php');
include_once('includes/store-helpers.php');
if (empty($_SESSION['login']) || empty($_SESSION['stdid']))
{
unset($_SESSION['login']);
unset($_SESSION['stdid']);
header('location:index.php');
exit;
}

$sid=$_SESSION['stdid'];
$sortDate=isset($_GET['sort_date']) ? trim((string)$_GET['sort_date']) : 'new';
$orderBySql=($sortDate==='old') ? 'tblorders.CreatedDate ASC, tblorders.id ASC' : 'tblorders.CreatedDate DESC, tblorders.id DESC';
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Online Library Management System | My Online Rent Requests</title>
    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/js/dataTables/dataTables.bootstrap.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
</head>
<body>
<?php include('includes/header.php');?>
    <div class="content-wrapper">
         <div class="container">
        <div class="row pad-botm">
            <div class="col-md-8">
                <h4 class="header-line">My Online Rent Requests</h4>
            </div>
            <div class="col-md-4 text-right">
                <form method="get" class="form-inline" style="display:inline-block;">
                    <label for="sort_date" style="margin-right:8px;">Date Order</label>
                    <select name="sort_date" id="sort_date" class="form-control" onchange="this.form.submit()">
                        <option value="new" <?php if($sortDate==='new'){ echo 'selected'; } ?>>New to Old</option>
                        <option value="old" <?php if($sortDate==='old'){ echo 'selected'; } ?>>Old to New</option>
                    </select>
                </form>
            </div>
        </div>

        <div class="row">
        <?php if($_SESSION['error']!="")
        {?>
        <div class="col-md-12">
            <div class="alert alert-danger">
                <strong>Error :</strong>
                <?php echo htmlentities($_SESSION['error']);?>
                <?php echo htmlentities($_SESSION['error']="");?>
            </div>
        </div>
        <?php } ?>
        <?php if($_SESSION['msg']!="")
        {?>
        <div class="col-md-12">
            <div class="alert alert-success">
                <strong>Success :</strong>
                <?php echo htmlentities($_SESSION['msg']);?>
                <?php echo htmlentities($_SESSION['msg']="");?>
            </div>
        </div>
        <?php } ?>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        1-Year Online Rent Request History
                    </div>
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover" id="dataTables-example">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Order Number</th>
                                        <th>Book Name</th>
                                        <th>ISBN</th>
                                        <th>Amount</th>
                                        <th>Payment Status</th>
                                        <th>Access Status</th>
                                        <th>Requested On</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
<?php
$sql="SELECT tblorders.id,tblorders.OrderNumber,tblorders.TotalAmount,tblorders.PaymentStatus,tblorders.OrderStatus,tblorders.CreatedDate,
tblorders.StatusNote,tblbooks.BookName,tblbooks.ISBNNumber,tblbooks.id AS BookId
FROM tblorders
INNER JOIN tblorderitems ON tblorderitems.OrderId=tblorders.id
INNER JOIN tblbooks ON tblbooks.id=tblorderitems.BookId
WHERE tblorders.StudentId=:sid
AND tblorders.OrderType='read_online'
ORDER BY " . $orderBySql;
$query=$dbh->prepare($sql);
$query->bindParam(':sid',$sid,PDO::PARAM_STR);
$query->execute();
$results=$query->fetchAll(PDO::FETCH_OBJ);
$cnt=1;
if($query->rowCount() > 0)
{
foreach($results as $result)
{
$accessStatus='Waiting for admin approval';
if($result->OrderStatus==='cancelled')
{
$accessStatus='Cancelled';
}
elseif($result->PaymentStatus==='payment_rejected')
{
$accessStatus='Permission rejected';
}
elseif(hasStudentPaidOnlineAccessToBook($dbh, $sid, (int)$result->BookId))
{
$accessStatus='Approved for 1 year';
}
?>
                                    <tr>
                                        <td><?php echo htmlentities($cnt);?></td>
                                        <td><?php echo htmlentities($result->OrderNumber);?></td>
                                        <td><?php echo htmlentities($result->BookName);?></td>
                                        <td><?php echo htmlentities($result->ISBNNumber);?></td>
                                        <td>Rs. <?php echo htmlentities(number_format((float)$result->TotalAmount,2));?></td>
                                        <td><?php echo htmlentities(formatPaymentStatusLabel($result->PaymentStatus));?></td>
                                        <td>
                                            <?php echo htmlentities($accessStatus);?>
<?php if(trim((string)$result->StatusNote)!==''){ ?>
                                            <br /><small><?php echo htmlentities($result->StatusNote);?></small>
<?php } ?>
                                        </td>
                                        <td><?php echo htmlentities($result->CreatedDate);?></td>
                                        <td>
                                            <a href="order-details.php?orderid=<?php echo htmlentities($result->id);?>" class="btn btn-primary btn-xs">View</a>
<?php if(hasStudentPaidOnlineAccessToBook($dbh, $sid, (int)$result->BookId)){ ?>
                                            <a href="preview-book.php?bookid=<?php echo htmlentities($result->BookId);?>" class="btn btn-success btn-xs">Open Preview</a>
<?php } ?>
                                        </td>
                                    </tr>
<?php
$cnt=$cnt+1;
}}
?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
<?php include('includes/footer.php');?>
    <script src="assets/js/jquery-1.10.2.js"></script>
    <script src="assets/js/bootstrap.js"></script>
    <script src="assets/js/dataTables/jquery.dataTables.js"></script>
    <script src="assets/js/dataTables/dataTables.bootstrap.js"></script>
    <script src="assets/js/custom.js"></script>
</body>
</html>
