<?php
session_start();
error_reporting(0);
include('includes/config.php');
include_once('../includes/store-helpers.php');
if(empty($_SESSION['alogin']))
    {
header('location:index.php');
exit;
}

$requestid=intval($_GET['requestid']);
if($requestid<=0)
{
$_SESSION['error']="Invalid book request selected.";
header('location:manage-book-requests.php');
exit;
}

function fetchBookRequestDetails($dbh, $requestid)
{
$sql="SELECT tblbookrequests.id,tblbookrequests.StudentId,tblbookrequests.RequestDate,tblbookrequests.Status,tblbookrequests.UserRemark,
tblbookrequests.AdminRemark,tblbookrequests.ActionDate,tblbookrequests.IssuedBookId,
tblstudents.FullName,tblstudents.EmailId,tblstudents.MobileNumber,tblstudents.Status as StudentStatus,
tblbooks.id as BookId,tblbooks.BookName,tblbooks.ISBNNumber,tblbooks.bookImage,tblbooks.bookQty,
tblauthors.AuthorName,
COALESCE(issue_counts.activeIssues,0) as activeIssues,
COALESCE(order_counts.soldQty,0) as soldQty
FROM tblbookrequests
JOIN tblstudents ON tblstudents.StudentId=tblbookrequests.StudentId
JOIN tblbooks ON tblbooks.id=tblbookrequests.BookId
LEFT JOIN tblauthors ON tblauthors.id=tblbooks.AuthorId
LEFT JOIN (
SELECT BookId,SUM(CASE WHEN RetrunStatus IS NULL OR RetrunStatus='' OR RetrunStatus=0 THEN 1 ELSE 0 END) as activeIssues
FROM tblissuedbookdetails
GROUP BY BookId
) issue_counts ON issue_counts.BookId=tblbooks.id
LEFT JOIN (
SELECT tblorderitems.BookId,SUM(tblorderitems.Quantity) as soldQty
FROM tblorderitems
INNER JOIN tblorders ON tblorders.id=tblorderitems.OrderId
WHERE tblorders.PaymentStatus='paid' AND tblorders.OrderStatus<>'cancelled'
GROUP BY tblorderitems.BookId
) order_counts ON order_counts.BookId=tblbooks.id
WHERE tblbookrequests.id=:requestid";
$query = $dbh->prepare($sql);
$query->bindParam(':requestid',$requestid,PDO::PARAM_INT);
$query->execute();
return $query->fetch(PDO::FETCH_ASSOC);
}

if(isset($_POST['approve']) || isset($_POST['reject']))
{
$adminRemark=trim($_POST['adminremark']);
try {
$dbh->beginTransaction();
$lockSql="SELECT tblbookrequests.id,tblbookrequests.StudentId,tblbookrequests.BookId,tblbookrequests.Status,
tblstudents.Status as StudentStatus,tblbooks.BookName,tblbooks.ISBNNumber,tblbooks.bookQty,
COALESCE(issue_counts.activeIssues,0) as activeIssues,
COALESCE(order_counts.soldQty,0) as soldQty
FROM tblbookrequests
JOIN tblstudents ON tblstudents.StudentId=tblbookrequests.StudentId
JOIN tblbooks ON tblbooks.id=tblbookrequests.BookId
LEFT JOIN (
SELECT BookId,SUM(CASE WHEN RetrunStatus IS NULL OR RetrunStatus='' OR RetrunStatus=0 THEN 1 ELSE 0 END) as activeIssues
FROM tblissuedbookdetails
GROUP BY BookId
) issue_counts ON issue_counts.BookId=tblbooks.id
LEFT JOIN (
SELECT tblorderitems.BookId,SUM(tblorderitems.Quantity) as soldQty
FROM tblorderitems
INNER JOIN tblorders ON tblorders.id=tblorderitems.OrderId
WHERE tblorders.PaymentStatus='paid' AND tblorders.OrderStatus<>'cancelled'
GROUP BY tblorderitems.BookId
) order_counts ON order_counts.BookId=tblbooks.id
WHERE tblbookrequests.id=:requestid
FOR UPDATE";
$lockQuery=$dbh->prepare($lockSql);
$lockQuery->bindParam(':requestid',$requestid,PDO::PARAM_INT);
$lockQuery->execute();
$request=$lockQuery->fetch(PDO::FETCH_ASSOC);

if(!$request)
{
throw new Exception("Book request not found.");
}

if((int)$request['Status']!==0)
{
throw new Exception("This request has already been processed.");
}

if(isset($_POST['approve']))
{
if((int)$request['StudentStatus']!==1)
{
throw new Exception("This student account is inactive.");
}

$activeIssueSql="SELECT id FROM tblissuedbookdetails WHERE StudentID=:studentid AND BookId=:bookid AND (RetrunStatus IS NULL OR RetrunStatus='' OR RetrunStatus=0) LIMIT 1";
$activeIssueQuery=$dbh->prepare($activeIssueSql);
$activeIssueQuery->bindParam(':studentid',$request['StudentId'],PDO::PARAM_STR);
$activeIssueQuery->bindParam(':bookid',$request['BookId'],PDO::PARAM_INT);
$activeIssueQuery->execute();
if($activeIssueQuery->fetch(PDO::FETCH_ASSOC))
{
throw new Exception("This book is already issued to the student.");
}

$availableQty=calculateAvailableBookQty($request['bookQty'], $request['activeIssues'], $request['soldQty']);
if($availableQty<=0)
{
throw new Exception("No copies are currently available for this book.");
}

$issueRemark=$adminRemark!==""?$adminRemark:"Issued from approved book request";
$issueSql="INSERT INTO tblissuedbookdetails(StudentID,BookId,remark) VALUES(:studentid,:bookid,:remark)";
$issueQuery=$dbh->prepare($issueSql);
$issueQuery->bindParam(':studentid',$request['StudentId'],PDO::PARAM_STR);
$issueQuery->bindParam(':bookid',$request['BookId'],PDO::PARAM_INT);
$issueQuery->bindParam(':remark',$issueRemark,PDO::PARAM_STR);
$issueQuery->execute();
$issuedBookId=$dbh->lastInsertId();

$updateSql="UPDATE tblbookrequests SET Status=1,AdminRemark=:adminremark,ActionDate=NOW(),IssuedBookId=:issuedbookid WHERE id=:requestid";
$updateQuery=$dbh->prepare($updateSql);
$updateQuery->bindParam(':adminremark',$issueRemark,PDO::PARAM_STR);
$updateQuery->bindParam(':issuedbookid',$issuedBookId,PDO::PARAM_INT);
$updateQuery->bindParam(':requestid',$requestid,PDO::PARAM_INT);
$updateQuery->execute();

$_SESSION['msg']="Book request approved and book issued successfully.";
}
else {
$rejectRemark=$adminRemark!==""?$adminRemark:"Request rejected by admin";
$rejectSql="UPDATE tblbookrequests SET Status=2,AdminRemark=:adminremark,ActionDate=NOW() WHERE id=:requestid";
$rejectQuery=$dbh->prepare($rejectSql);
$rejectQuery->bindParam(':adminremark',$rejectRemark,PDO::PARAM_STR);
$rejectQuery->bindParam(':requestid',$requestid,PDO::PARAM_INT);
$rejectQuery->execute();

$_SESSION['msg']="Book request rejected successfully.";
}

$dbh->commit();
}
catch (Exception $e)
{
if($dbh->inTransaction())
{
$dbh->rollBack();
}
$_SESSION['error']=$e->getMessage();
}

header('location:manage-book-requests.php');
exit;
}

$request=fetchBookRequestDetails($dbh,$requestid);
if(!$request)
{
$_SESSION['error']="Book request not found.";
header('location:manage-book-requests.php');
exit;
}

$availableQty=calculateAvailableBookQty($request['bookQty'], $request['activeIssues'], $request['soldQty']);
$statusLabel="Pending";
if((int)$request['Status']===1)
{
$statusLabel="Approved and Issued";
}
elseif((int)$request['Status']===2)
{
$statusLabel="Rejected";
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Online Library Management System | Review Book Request</title>
    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
</head>
<body>
<?php include('includes/header.php');?>
    <div class="content-wrapper">
         <div class="container">
        <div class="row pad-botm">
            <div class="col-md-12">
                <h4 class="header-line">Review Book Request</h4>
            </div>
        </div>
        <div class="row">
            <div class="col-md-10 col-sm-6 col-xs-12 col-md-offset-1">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        Book Request Details
                    </div>
                    <div class="panel-body">
                        <form role="form" method="post">
                            <h4>Student Details</h4>
                            <hr />
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Student ID :</label>
                                    <?php echo htmlentities($request['StudentId']);?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Student Name :</label>
                                    <?php echo htmlentities($request['FullName']);?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Student Email :</label>
                                    <?php echo htmlentities($request['EmailId']);?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Student Contact :</label>
                                    <?php echo htmlentities($request['MobileNumber']);?>
                                </div>
                            </div>

                            <h4>Book Details</h4>
                            <hr />
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Book Image :</label>
                                    <img src="bookimg/<?php echo htmlentities($request['bookImage']);?>" width="120">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Book Name :</label>
                                    <?php echo htmlentities($request['BookName']);?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Author :</label>
                                    <?php echo htmlentities($request['AuthorName']);?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>ISBN :</label>
                                    <?php echo htmlentities($request['ISBNNumber']);?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Book Quantity :</label>
                                    <?php echo htmlentities($request['bookQty']);?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Currently Reading :</label>
                                    <?php echo htmlentities($request['activeIssues']);?> people
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Sold Copies :</label>
                                    <?php echo htmlentities($request['soldQty']);?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Available Quantity :</label>
                                    <?php echo htmlentities($availableQty);?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Requested On :</label>
                                    <?php echo htmlentities($request['RequestDate']);?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Request Status :</label>
                                    <?php echo htmlentities($statusLabel);?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Action Date :</label>
                                    <?php echo htmlentities($request['ActionDate']!=""?$request['ActionDate']:"--");?>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>Admin Remark :</label>
<?php if((int)$request['Status']===0){ ?>
                                    <textarea class="form-control" name="adminremark" rows="4" placeholder="Optional note for approval or rejection"></textarea>
<?php } else { ?>
                                    <div><?php echo htmlentities($request['AdminRemark']!=""?$request['AdminRemark']:"--");?></div>
<?php } ?>
                                </div>
                            </div>

<?php if((int)$request['Status']===0){ ?>
                            <button type="submit" name="approve" class="btn btn-success">Approve and Issue Book</button>
                            <button type="submit" name="reject" class="btn btn-danger">Reject Request</button>
<?php } elseif((int)$request['Status']===1 && !empty($request['IssuedBookId'])) { ?>
                            <a href="update-issue-bookdeails.php?rid=<?php echo htmlentities($request['IssuedBookId']);?>" class="btn btn-primary">View Issued Book Record</a>
<?php } ?>
                            <a href="manage-book-requests.php" class="btn btn-default">Back</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
<?php include('includes/footer.php');?>
    <script src="assets/js/jquery-1.10.2.js"></script>
    <script src="assets/js/bootstrap.js"></script>
    <script src="assets/js/custom.js"></script>
</body>
</html>
