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

$sid=$_SESSION['stdid'];
if(isset($_POST['request_return']))
{
$rid=intval($_POST['rid']);
if($rid<=0)
{
$_SESSION['error']="Invalid issued book selected.";
}
else {
$sql="SELECT id,RetrunStatus,COALESCE(ReturnRequestStatus,0) AS ReturnRequestStatus
FROM tblissuedbookdetails
WHERE id=:rid AND StudentID=:sid
LIMIT 1";
$query=$dbh->prepare($sql);
$query->bindParam(':rid',$rid,PDO::PARAM_INT);
$query->bindParam(':sid',$sid,PDO::PARAM_STR);
$query->execute();
$issuedBook=$query->fetch(PDO::FETCH_ASSOC);
if(!$issuedBook)
{
$_SESSION['error']="Issued book record not found.";
}
elseif((int)$issuedBook['RetrunStatus']===1)
{
$_SESSION['error']="This book has already been returned.";
}
elseif((int)$issuedBook['ReturnRequestStatus']===1)
{
$_SESSION['error']="A return request is already pending for this book.";
}
else {
$updateSql="UPDATE tblissuedbookdetails SET ReturnRequestStatus=1,ReturnRequestDate=NOW() WHERE id=:rid AND StudentID=:sid";
$updateQuery=$dbh->prepare($updateSql);
$updateQuery->bindParam(':rid',$rid,PDO::PARAM_INT);
$updateQuery->bindParam(':sid',$sid,PDO::PARAM_STR);
$updateQuery->execute();
$_SESSION['msg']="Return request sent to admin successfully.";
}
}
header('location:issued-books.php');
exit;
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Online Library Management System | Issued Books</title>
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
            <div class="col-md-12">
                <h4 class="header-line">My Issued Books</h4>
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
                        Issued Books
                    </div>
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover" id="dataTables-example">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Book Name</th>
                                        <th>ISBN</th>
                                        <th>Issued Date</th>
                                        <th>Return Date</th>
                                        <th>Return Status</th>
                                        <th>Fine in (USD)</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
<?php
$sql="SELECT tblbooks.BookName,tblbooks.ISBNNumber,tblissuedbookdetails.IssuesDate,tblissuedbookdetails.ReturnDate,
tblissuedbookdetails.id as rid,tblissuedbookdetails.fine,tblissuedbookdetails.RetrunStatus,
COALESCE(tblissuedbookdetails.ReturnRequestStatus,0) AS ReturnRequestStatus,tblissuedbookdetails.ReturnRequestDate
FROM tblissuedbookdetails
JOIN tblstudents ON tblstudents.StudentId=tblissuedbookdetails.StudentId
JOIN tblbooks ON tblbooks.id=tblissuedbookdetails.BookId
WHERE tblstudents.StudentId=:sid
ORDER BY tblissuedbookdetails.id DESC";
$query = $dbh->prepare($sql);
$query->bindParam(':sid', $sid, PDO::PARAM_STR);
$query->execute();
$results=$query->fetchAll(PDO::FETCH_OBJ);
$cnt=1;
if($query->rowCount() > 0)
{
foreach($results as $result)
{
$returnDateLabel=$result->ReturnDate!="" ? $result->ReturnDate : "Not Return Yet";
$returnStatusLabel="Issued";
if((int)$result->RetrunStatus===1)
{
$returnStatusLabel="Returned";
}
elseif((int)$result->ReturnRequestStatus===1)
{
$returnStatusLabel="Return Requested";
}
?>
                                    <tr class="odd gradeX">
                                        <td class="center"><?php echo htmlentities($cnt);?></td>
                                        <td class="center"><?php echo htmlentities($result->BookName);?></td>
                                        <td class="center"><?php echo htmlentities($result->ISBNNumber);?></td>
                                        <td class="center"><?php echo htmlentities($result->IssuesDate);?></td>
                                        <td class="center">
<?php if($result->ReturnDate==""){ ?>
                                            <span style="color:red;"><?php echo htmlentities($returnDateLabel);?></span>
<?php } else { ?>
                                            <?php echo htmlentities($returnDateLabel);?>
<?php } ?>
                                        </td>
                                        <td class="center">
                                            <?php echo htmlentities($returnStatusLabel);?>
<?php if((int)$result->ReturnRequestStatus===1 && $result->ReturnRequestDate!=""){ ?>
                                            <br /><small><?php echo htmlentities($result->ReturnRequestDate);?></small>
<?php } ?>
                                        </td>
                                        <td class="center"><?php echo htmlentities($result->fine!="" ? $result->fine : '0');?></td>
                                        <td class="center">
<?php if((int)$result->RetrunStatus===1){ ?>
                                            <button type="button" class="btn btn-success btn-xs" disabled>Returned</button>
<?php } elseif((int)$result->ReturnRequestStatus===1) { ?>
                                            <button type="button" class="btn btn-warning btn-xs" disabled>Pending Admin</button>
<?php } else { ?>
                                            <form method="post" style="margin:0;">
                                                <input type="hidden" name="rid" value="<?php echo htmlentities($result->rid);?>">
                                                <button type="submit" name="request_return" class="btn btn-primary btn-xs">Request Return</button>
                                            </form>
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
