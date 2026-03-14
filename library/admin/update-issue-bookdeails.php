<?php
session_start();
error_reporting(0);
include('includes/config.php');
if(strlen($_SESSION['alogin'])==0)
{
header('location:index.php');
exit;
}

$rid=intval($_GET['rid']);
if($rid<=0)
{
$_SESSION['error']="Invalid issued book selected.";
header('location:manage-issued-books.php');
exit;
}

if(isset($_POST['return']))
{
$fine=max(0, intval($_POST['fine']));
$checkSql="SELECT id,RetrunStatus FROM tblissuedbookdetails WHERE id=:rid LIMIT 1";
$checkQuery=$dbh->prepare($checkSql);
$checkQuery->bindParam(':rid',$rid,PDO::PARAM_INT);
$checkQuery->execute();
$issuedBook=$checkQuery->fetch(PDO::FETCH_ASSOC);
if(!$issuedBook)
{
$_SESSION['error']="Issued book record not found.";
header('location:manage-issued-books.php');
exit;
}

if((int)$issuedBook['RetrunStatus']===1)
{
$_SESSION['error']="This book has already been returned.";
header('location:manage-issued-books.php');
exit;
}

$updateSql="UPDATE tblissuedbookdetails
SET fine=:fine,
RetrunStatus=1,
ReturnDate=NOW(),
ReturnRequestStatus=2,
ReturnProcessedDate=NOW()
WHERE id=:rid";
$updateQuery=$dbh->prepare($updateSql);
$updateQuery->bindParam(':fine',$fine,PDO::PARAM_INT);
$updateQuery->bindParam(':rid',$rid,PDO::PARAM_INT);
$updateQuery->execute();

$_SESSION['msg']="Book returned successfully.";
header('location:manage-issued-books.php');
exit;
}

$sql = "SELECT tblstudents.StudentId,tblstudents.FullName,tblstudents.EmailId,tblstudents.MobileNumber,
tblbooks.BookName,tblbooks.ISBNNumber,tblissuedbookdetails.IssuesDate,tblissuedbookdetails.ReturnDate,
tblissuedbookdetails.id as rid,tblissuedbookdetails.fine,tblissuedbookdetails.RetrunStatus,
COALESCE(tblissuedbookdetails.ReturnRequestStatus,0) AS ReturnRequestStatus,
tblissuedbookdetails.ReturnRequestDate,tblissuedbookdetails.ReturnProcessedDate,
tblbooks.id as bid,tblbooks.bookImage
FROM tblissuedbookdetails
JOIN tblstudents ON tblstudents.StudentId=tblissuedbookdetails.StudentId
JOIN tblbooks ON tblbooks.id=tblissuedbookdetails.BookId
WHERE tblissuedbookdetails.id=:rid";
$query = $dbh->prepare($sql);
$query->bindParam(':rid',$rid,PDO::PARAM_INT);
$query->execute();
$issuedBook=$query->fetch(PDO::FETCH_ASSOC);
if(!$issuedBook)
{
$_SESSION['error']="Issued book record not found.";
header('location:manage-issued-books.php');
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
    <title>Online Library Management System | Issued Book Details</title>
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
                <h4 class="header-line">Issued Book Details</h4>
            </div>
        </div>

        <div class="row">
            <div class="col-md-10 col-sm-6 col-xs-12 col-md-offset-1">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        Issued Book Details
                    </div>
                    <div class="panel-body">
                        <form role="form" method="post">
                            <h4>Student Details</h4>
                            <hr />
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Student ID :</label>
                                    <?php echo htmlentities($issuedBook['StudentId']);?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Student Name :</label>
                                    <?php echo htmlentities($issuedBook['FullName']);?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Student Email Id :</label>
                                    <?php echo htmlentities($issuedBook['EmailId']);?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Student Contact No :</label>
                                    <?php echo htmlentities($issuedBook['MobileNumber']);?>
                                </div>
                            </div>

                            <h4>Book Details</h4>
                            <hr />
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Book Image :</label>
                                    <img src="bookimg/<?php echo htmlentities($issuedBook['bookImage']); ?>" width="120">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Book Name :</label>
                                    <?php echo htmlentities($issuedBook['BookName']);?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>ISBN :</label>
                                    <?php echo htmlentities($issuedBook['ISBNNumber']);?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Book Issued Date :</label>
                                    <?php echo htmlentities($issuedBook['IssuesDate']);?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Return Request Status :</label>
<?php if((int)$issuedBook['ReturnRequestStatus']===1){ ?>
                                    <span style="color:#b8860b;">User requested return</span>
<?php } elseif((int)$issuedBook['RetrunStatus']===1) { ?>
                                    <span style="color:green;">Returned</span>
<?php } else { ?>
                                    <span>Not requested yet</span>
<?php } ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Return Request Date :</label>
                                    <?php echo htmlentities($issuedBook['ReturnRequestDate']!="" ? $issuedBook['ReturnRequestDate'] : '--');?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Book Returned Date :</label>
                                    <?php echo htmlentities($issuedBook['ReturnDate']!="" ? $issuedBook['ReturnDate'] : 'Not Return Yet');?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Return Processed Date :</label>
                                    <?php echo htmlentities($issuedBook['ReturnProcessedDate']!="" ? $issuedBook['ReturnProcessedDate'] : '--');?>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>Fine (in USD) :</label>
<?php if((int)$issuedBook['RetrunStatus']===1){ ?>
                                    <?php echo htmlentities($issuedBook['fine']!="" ? $issuedBook['fine'] : '0');?>
<?php } else { ?>
                                    <input class="form-control" type="number" min="0" name="fine" id="fine" value="<?php echo htmlentities($issuedBook['fine']!="" ? $issuedBook['fine'] : '0');?>" required />
<?php } ?>
                                </div>
                            </div>
<?php if((int)$issuedBook['RetrunStatus']!==1){ ?>
                            <button type="submit" name="return" id="submit" class="btn btn-info">Return Book</button>
<?php } else { ?>
                            <button type="button" class="btn btn-success" disabled>Book Returned</button>
<?php } ?>
                            <a href="manage-issued-books.php" class="btn btn-default">Back</a>
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
