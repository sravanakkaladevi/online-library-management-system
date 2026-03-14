<?php
session_start();
error_reporting(0);
include('includes/config.php');
if(empty($_SESSION['alogin']))
    {
header('location:index.php');
exit;
}
else{
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Online Library Management System | Manage Book Requests</title>
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
                <h4 class="header-line">Manage Book Requests</h4>
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
                        User Book Requests
                    </div>
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover" id="dataTables-example">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Student Name</th>
                                        <th>Student ID</th>
                                        <th>Book Name</th>
                                        <th>ISBN</th>
                                        <th>Requested On</th>
                                        <th>Status</th>
                                        <th>Action Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
<?php
$sql="SELECT tblbookrequests.id,tblbookrequests.RequestDate,tblbookrequests.Status,tblbookrequests.ActionDate,tblbookrequests.IssuedBookId,
tblstudents.FullName,tblstudents.StudentId,
tblbooks.BookName,tblbooks.ISBNNumber
FROM tblbookrequests
JOIN tblstudents ON tblstudents.StudentId=tblbookrequests.StudentId
JOIN tblbooks ON tblbooks.id=tblbookrequests.BookId
ORDER BY FIELD(tblbookrequests.Status,0,1,2), tblbookrequests.id DESC";
$query = $dbh -> prepare($sql);
$query->execute();
$results=$query->fetchAll(PDO::FETCH_OBJ);
$cnt=1;
if($query->rowCount() > 0)
{
foreach($results as $result)
{
$statusLabel="Pending";
if((int)$result->Status===1)
{
$statusLabel="Approved and Issued";
}
elseif((int)$result->Status===2)
{
$statusLabel="Rejected";
}
?>
                                    <tr class="odd gradeX">
                                        <td><?php echo htmlentities($cnt);?></td>
                                        <td><?php echo htmlentities($result->FullName);?></td>
                                        <td><?php echo htmlentities($result->StudentId);?></td>
                                        <td><?php echo htmlentities($result->BookName);?></td>
                                        <td><?php echo htmlentities($result->ISBNNumber);?></td>
                                        <td><?php echo htmlentities($result->RequestDate);?></td>
                                        <td><?php echo htmlentities($statusLabel);?></td>
                                        <td><?php echo htmlentities($result->ActionDate!=""?$result->ActionDate:"--");?></td>
                                        <td>
                                            <a href="update-book-request.php?requestid=<?php echo htmlentities($result->id);?>" class="btn btn-primary btn-sm">Review</a>
<?php if((int)$result->Status===1 && !empty($result->IssuedBookId)){ ?>
                                            <a href="update-issue-bookdeails.php?rid=<?php echo htmlentities($result->IssuedBookId);?>" class="btn btn-success btn-sm">Issued Record</a>
<?php } ?>
                                        </td>
                                    </tr>
<?php $cnt=$cnt+1;}} ?>
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
<?php } ?>
