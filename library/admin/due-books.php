<?php
session_start();
error_reporting(0);
include('includes/config.php');
if(strlen($_SESSION['alogin'])==0)
{
header('location:index.php');
exit;
}

$loanDays=15;
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Online Library Management System | Check Due Books</title>
    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/js/dataTables/dataTables.bootstrap.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
    <style type="text/css">
        .due-summary-card {
            padding: 18px 20px;
            margin-bottom: 22px;
            border-radius: 18px;
            background: linear-gradient(135deg, rgba(255,255,255,0.96) 0%, rgba(248,250,252,0.92) 100%);
            box-shadow: 0 18px 34px rgba(15, 23, 42, 0.08);
        }

        .due-summary-card h5 {
            margin: 0 0 6px;
            font-size: 18px;
            font-weight: 800;
            color: #1f2937;
        }

        .due-summary-card p {
            margin: 0;
            color: #64748b;
        }

        .due-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .due-badge--overdue {
            background: rgba(239, 68, 68, 0.12);
            color: #b91c1c;
        }

        .due-badge--soon {
            background: rgba(245, 158, 11, 0.14);
            color: #b45309;
        }

        .due-badge--active {
            background: rgba(34, 197, 94, 0.14);
            color: #15803d;
        }
    </style>
</head>
<body>
<?php include('includes/header.php');?>
    <div class="content-wrapper">
         <div class="container">
        <div class="row pad-botm">
            <div class="col-md-12">
                <h4 class="header-line">Check Due Books</h4>
            </div>
        </div>

<?php
$summarySql="SELECT
SUM(CASE WHEN DATEDIFF(CURDATE(), DATE_ADD(DATE(IssuesDate), INTERVAL :loanDays DAY)) > 0 THEN 1 ELSE 0 END) AS overdueCount,
SUM(CASE WHEN DATEDIFF(DATE_ADD(DATE(IssuesDate), INTERVAL :loanDays DAY), CURDATE()) BETWEEN 0 AND 2 THEN 1 ELSE 0 END) AS dueSoonCount,
COUNT(*) AS activeCount
FROM tblissuedbookdetails
WHERE (RetrunStatus IS NULL OR RetrunStatus='' OR RetrunStatus=0)";
$summaryQuery=$dbh->prepare($summarySql);
$summaryQuery->bindParam(':loanDays',$loanDays,PDO::PARAM_INT);
$summaryQuery->execute();
$summaryData=$summaryQuery->fetch(PDO::FETCH_ASSOC);
?>

        <div class="row">
            <div class="col-md-12">
                <div class="due-summary-card">
                    <h5>Due tracking is based on a <?php echo (int)$loanDays; ?> day issue period.</h5>
                    <p>
                        Active Issues: <?php echo (int)$summaryData['activeCount']; ?> |
                        Due Soon: <?php echo (int)$summaryData['dueSoonCount']; ?> |
                        Overdue: <?php echo (int)$summaryData['overdueCount']; ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        Active Issued Books With Due Dates
                    </div>
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover" id="dataTables-example">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Student ID</th>
                                        <th>Student Name</th>
                                        <th>Book Name</th>
                                        <th>Issued Date</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th>Days Left</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
<?php
$sql = "SELECT
tblstudents.StudentId,
tblstudents.FullName,
tblbooks.BookName,
tblissuedbookdetails.IssuesDate,
tblissuedbookdetails.id AS rid,
DATE_ADD(DATE(tblissuedbookdetails.IssuesDate), INTERVAL :loanDays DAY) AS DueDate,
DATEDIFF(DATE_ADD(DATE(tblissuedbookdetails.IssuesDate), INTERVAL :loanDays DAY), CURDATE()) AS DaysLeft
FROM tblissuedbookdetails
JOIN tblstudents ON tblstudents.StudentId=tblissuedbookdetails.StudentId
JOIN tblbooks ON tblbooks.id=tblissuedbookdetails.BookId
WHERE (tblissuedbookdetails.RetrunStatus IS NULL OR tblissuedbookdetails.RetrunStatus='' OR tblissuedbookdetails.RetrunStatus=0)
ORDER BY DaysLeft ASC, tblissuedbookdetails.IssuesDate ASC";
$query = $dbh->prepare($sql);
$query->bindParam(':loanDays',$loanDays,PDO::PARAM_INT);
$query->execute();
$results=$query->fetchAll(PDO::FETCH_OBJ);
$cnt=1;
if($query->rowCount() > 0)
{
foreach($results as $result)
{
    $daysLeft=(int)$result->DaysLeft;
    $statusClass='due-badge due-badge--active';
    $statusText='On Time';
    $daysLabel=$daysLeft.' days left';

    if($daysLeft < 0)
    {
        $statusClass='due-badge due-badge--overdue';
        $statusText='Overdue';
        $daysLabel=abs($daysLeft).' days overdue';
    }
    elseif($daysLeft <= 2)
    {
        $statusClass='due-badge due-badge--soon';
        $statusText='Due Soon';
    }
?>
                                    <tr class="odd gradeX">
                                        <td class="center"><?php echo htmlentities($cnt);?></td>
                                        <td class="center"><?php echo htmlentities($result->StudentId);?></td>
                                        <td class="center"><?php echo htmlentities($result->FullName);?></td>
                                        <td class="center"><?php echo htmlentities($result->BookName);?></td>
                                        <td class="center"><?php echo htmlentities($result->IssuesDate);?></td>
                                        <td class="center"><?php echo htmlentities($result->DueDate);?></td>
                                        <td class="center"><span class="<?php echo $statusClass; ?>"><?php echo htmlentities($statusText);?></span></td>
                                        <td class="center"><?php echo htmlentities($daysLabel);?></td>
                                        <td class="center">
                                            <a href="update-issue-bookdeails.php?rid=<?php echo htmlentities($result->rid);?>" class="btn btn-primary btn-sm">Open Record</a>
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
