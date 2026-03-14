<?php
session_start();
error_reporting(0);
include('includes/config.php');
if(strlen($_SESSION['alogin'])==0)
{
header('location:index.php');
exit;
}

if(isset($_GET['inid']))
{
$id=intval($_GET['inid']);
$status=0;
$sql = "UPDATE tblstudents SET Status=:status WHERE id=:id";
$query = $dbh->prepare($sql);
$query->bindParam(':id',$id,PDO::PARAM_INT);
$query->bindParam(':status',$status,PDO::PARAM_INT);
$query->execute();
$_SESSION['msg']="Student blocked successfully.";
header('location:reg-students.php');
exit;
}

if(isset($_GET['id']))
{
$id=intval($_GET['id']);
$status=1;
$sql = "UPDATE tblstudents SET Status=:status WHERE id=:id";
$query = $dbh->prepare($sql);
$query->bindParam(':id',$id,PDO::PARAM_INT);
$query->bindParam(':status',$status,PDO::PARAM_INT);
$query->execute();
$_SESSION['msg']="Student activated successfully.";
header('location:reg-students.php');
exit;
}

if(isset($_GET['del']))
{
$id=intval($_GET['del']);
$studentSql="SELECT id,StudentId,FullName FROM tblstudents WHERE id=:id LIMIT 1";
$studentQuery=$dbh->prepare($studentSql);
$studentQuery->bindParam(':id',$id,PDO::PARAM_INT);
$studentQuery->execute();
$student=$studentQuery->fetch(PDO::FETCH_ASSOC);
if(!$student)
{
$_SESSION['error']="Student not found.";
header('location:reg-students.php');
exit;
}

$referenceSql="SELECT
(SELECT COUNT(*) FROM tblissuedbookdetails WHERE StudentID=:issuedstudentid) AS issuedCount,
(SELECT COUNT(*) FROM tblorders WHERE StudentId=:orderstudentid) AS orderCount";
$referenceQuery=$dbh->prepare($referenceSql);
$studentIdValue=$student['StudentId'];
$referenceQuery->bindValue(':issuedstudentid',$studentIdValue,PDO::PARAM_STR);
$referenceQuery->bindValue(':orderstudentid',$studentIdValue,PDO::PARAM_STR);
$referenceQuery->execute();
$references=$referenceQuery->fetch(PDO::FETCH_ASSOC);

if((int)$references['issuedCount']>0 || (int)$references['orderCount']>0)
{
$_SESSION['error']="Cannot delete " . $student['FullName'] . " because issued-book or order history exists.";
header('location:reg-students.php');
exit;
}

$dbh->beginTransaction();
try {
$deleteRequestsSql="DELETE FROM tblbookrequests WHERE StudentId=:studentid";
$deleteRequestsQuery=$dbh->prepare($deleteRequestsSql);
$deleteRequestsQuery->bindValue(':studentid',$studentIdValue,PDO::PARAM_STR);
$deleteRequestsQuery->execute();

$deleteCartSql="DELETE FROM tblcart WHERE StudentId=:studentid";
$deleteCartQuery=$dbh->prepare($deleteCartSql);
$deleteCartQuery->bindValue(':studentid',$studentIdValue,PDO::PARAM_STR);
$deleteCartQuery->execute();

$deleteStudentSql="DELETE FROM tblstudents WHERE id=:id";
$deleteStudentQuery=$dbh->prepare($deleteStudentSql);
$deleteStudentQuery->bindParam(':id',$id,PDO::PARAM_INT);
$deleteStudentQuery->execute();

$dbh->commit();
$_SESSION['msg']="Student deleted successfully.";
}
catch (Exception $e)
{
if($dbh->inTransaction())
{
$dbh->rollBack();
}
$_SESSION['error']="Unable to delete student right now.";
}

header('location:reg-students.php');
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
    <title>Online Library Management System | Manage Reg Students</title>
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
                <h4 class="header-line">Manage Registered Students</h4>
            </div>
        </div>

        <div class="row">
<?php if($_SESSION['error']!=""){ ?>
            <div class="col-md-6">
                <div class="alert alert-danger">
                    <strong>Error :</strong>
                    <?php echo htmlentities($_SESSION['error']);?>
                    <?php echo htmlentities($_SESSION['error']="");?>
                </div>
            </div>
<?php } ?>
<?php if($_SESSION['msg']!=""){ ?>
            <div class="col-md-6">
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
                        Registered Students
                    </div>
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover" id="dataTables-example">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Student ID</th>
                                        <th>Student Name</th>
                                        <th>Email id</th>
                                        <th>Mobile Number</th>
                                        <th>Reg Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
<?php
$sql = "SELECT * FROM tblstudents ORDER BY id DESC";
$query = $dbh->prepare($sql);
$query->execute();
$results=$query->fetchAll(PDO::FETCH_OBJ);
$cnt=1;
if($query->rowCount() > 0)
{
foreach($results as $result)
{
?>
                                    <tr class="odd gradeX">
                                        <td class="center"><?php echo htmlentities($cnt);?></td>
                                        <td class="center"><?php echo htmlentities($result->StudentId);?></td>
                                        <td class="center"><?php echo htmlentities($result->FullName);?></td>
                                        <td class="center"><?php echo htmlentities($result->EmailId);?></td>
                                        <td class="center"><?php echo htmlentities($result->MobileNumber);?></td>
                                        <td class="center"><?php echo htmlentities($result->RegDate);?></td>
                                        <td class="center"><?php echo $result->Status==1 ? 'Active' : 'Blocked'; ?></td>
                                        <td class="center">
<?php if($result->Status==1){ ?>
                                            <a href="reg-students.php?inid=<?php echo htmlentities($result->id);?>" onclick="return confirm('Are you sure you want to block this student?');" class="btn btn-danger btn-xs">Inactive</a>
<?php } else { ?>
                                            <a href="reg-students.php?id=<?php echo htmlentities($result->id);?>" onclick="return confirm('Are you sure you want to activate this student?');" class="btn btn-primary btn-xs">Active</a>
<?php } ?>
                                            <a href="student-history.php?stdid=<?php echo htmlentities($result->StudentId);?>" class="btn btn-success btn-xs">Details</a>
                                            <a href="reg-students.php?del=<?php echo htmlentities($result->id);?>" onclick="return confirm('Are you sure you want to delete this student?');" class="btn btn-danger btn-xs">Delete</a>
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
