<?php
session_start();
error_reporting(0);
include('includes/config.php');
include_once('includes/user-preferences.php');
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
$preferences=getUserPreferences($dbh, $sid);
$dashboardThemeColor=$preferences['ThemeColor'];
$dashboardThemeSoft=hexToRgba($dashboardThemeColor, 0.20);
$dashboardThemeGlow=hexToRgba($dashboardThemeColor, 0.35);
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
        :root {
            --dashboard-theme: <?php echo htmlentities($dashboardThemeColor);?>;
            --dashboard-theme-soft: <?php echo htmlentities($dashboardThemeSoft);?>;
            --dashboard-theme-glow: <?php echo htmlentities($dashboardThemeGlow);?>;
        }

        body.dashboard-page {
            background:
                linear-gradient(135deg, rgba(8, 15, 28, 0.28) 0%, rgba(15, 23, 42, 0.18) 38%, rgba(8, 15, 28, 0.26) 100%),
                url('assets/img/library-hero.jpg') center center / cover fixed no-repeat;
        }

        body.dashboard-page .content-wrapper {
            background: transparent;
        }

        body.dashboard-page .container {
            position: relative;
            z-index: 1;
        }

        .dashboard-shell {
            position: relative;
            padding: 22px;
            border-radius: 40px;
            background: linear-gradient(135deg, rgba(255,255,255,0.18) 0%, rgba(248,250,252,0.14) 45%, rgba(255,255,255,0.16) 100%);
            border: 3px solid var(--dashboard-theme);
            box-shadow:
                0 0 0 6px var(--dashboard-theme-soft),
                0 0 36px var(--dashboard-theme-glow),
                0 28px 60px rgba(15, 23, 42, 0.10);
            backdrop-filter: blur(8px);
        }

        .dashboard-hero {
            position: relative;
            overflow: hidden;
            min-height: 360px;
            margin-bottom: 0;
            padding: 34px 32px 26px;
            border-radius: 28px;
            background:
                linear-gradient(115deg, rgba(255, 255, 255, 0.76) 0%, rgba(248, 250, 252, 0.52) 34%, rgba(239, 246, 255, 0.30) 100%);
            border: 1px solid rgba(255,255,255,0.42);
            box-shadow:
                inset 0 0 0 1px rgba(255,255,255,0.14),
                0 30px 60px rgba(15, 23, 42, 0.16);
            backdrop-filter: blur(3px);
        }

        .dashboard-hero:before,
        .dashboard-hero:after {
            content: "";
            position: absolute;
            border-radius: 999px;
            filter: blur(12px);
            opacity: 0.65;
            animation: welcomeFloat 9s ease-in-out infinite;
        }

        .dashboard-hero:before {
            width: 220px;
            height: 220px;
            right: -40px;
            top: -55px;
            background: rgba(56, 189, 248, 0.20);
        }

        .dashboard-hero:after {
            width: 170px;
            height: 170px;
            left: -20px;
            bottom: -45px;
            background: rgba(16, 185, 129, 0.18);
            animation-delay: -2s;
        }

        .dashboard-hero__content {
            position: relative;
            z-index: 1;
            max-width: 620px;
        }

        .dashboard-kicker {
            display: inline-block;
            margin-bottom: 16px;
            color: var(--dashboard-theme);
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .dashboard-hero__title {
            margin: 0 0 14px;
            font-size: 54px;
            line-height: 1.03;
            font-weight: 800;
            color: #162033;
            animation: welcomeRise 0.8s ease-out;
        }

        .dashboard-hero__text {
            margin: 0;
            max-width: 560px;
            font-size: 21px;
            line-height: 1.7;
            color: #42526b;
            animation: welcomeRise 1s ease-out;
        }

        .dashboard-hero__stats {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-top: 46px;
        }

        .dashboard-hero-stat {
            display: block;
            padding: 22px 24px 18px;
            border-left: 1px solid rgba(148, 163, 184, 0.22);
            background: rgba(255, 255, 255, 0.26);
            backdrop-filter: blur(8px);
            transform-origin: top center;
            animation: statDropSpin 0.9s cubic-bezier(0.22, 1, 0.36, 1) both;
            text-decoration: none !important;
            transition: transform 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
        }

        .dashboard-hero-stat:hover,
        .dashboard-hero-stat:focus {
            transform: translateY(-4px);
            background: rgba(255, 255, 255, 0.36);
            box-shadow: 0 18px 34px rgba(15, 23, 42, 0.10);
        }

        .dashboard-hero-stat:nth-child(1) {
            animation-delay: 0.08s;
        }

        .dashboard-hero-stat:nth-child(2) {
            animation-delay: 0.18s;
        }

        .dashboard-hero-stat:nth-child(3) {
            animation-delay: 0.28s;
        }

        .dashboard-hero-stat:nth-child(4) {
            animation-delay: 0.38s;
        }

        .dashboard-hero-stat__value {
            display: block;
            margin-bottom: 6px;
            font-size: 28px;
            line-height: 1;
            font-weight: 800;
            color: #111827;
            text-shadow: 0 1px 0 rgba(255,255,255,0.2);
        }

        .dashboard-hero-stat__label {
            color: #334155;
            font-size: 15px;
            line-height: 1.6;
        }

        @keyframes welcomeFloat {
            0%, 100% { transform: translateY(0px) translateX(0px); }
            50% { transform: translateY(10px) translateX(-8px); }
        }

        @keyframes welcomeRise {
            0% { opacity: 0; transform: translateY(16px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        @keyframes statDropSpin {
            0% {
                opacity: 0;
                transform: translateY(-42px) rotateX(70deg) rotateZ(-6deg) scale(0.92);
            }
            60% {
                opacity: 1;
                transform: translateY(10px) rotateX(-10deg) rotateZ(2deg) scale(1.02);
            }
            100% {
                opacity: 1;
                transform: translateY(0) rotateX(0deg) rotateZ(0deg) scale(1);
            }
        }

        @media (max-width: 991px) {
            .dashboard-shell {
                padding: 16px;
                border-radius: 28px;
            }

            .dashboard-hero {
                min-height: auto;
                padding: 26px 24px 24px;
            }

            .dashboard-hero__title {
                font-size: 40px;
            }

            .dashboard-hero__text {
                font-size: 18px;
            }

            .dashboard-hero__stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

        }

        @media (max-width: 767px) {
            .dashboard-hero__title {
                font-size: 32px;
            }

            .dashboard-hero__text {
                font-size: 16px;
            }

            .dashboard-hero__stats,
            .dashboard-hero__stats {
                grid-template-columns: 1fr;
            }
        }
    </style>

</head>
<body class="dashboard-page">
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

<?php 
$sql ="SELECT id from tblbooks ";
$query = $dbh -> prepare($sql);
$query->execute();
$results=$query->fetchAll(PDO::FETCH_OBJ);
$listdbooks=$query->rowCount();

$rsts=0;
$sql2 ="SELECT id from tblissuedbookdetails where StudentID=:sid and (RetrunStatus=:rsts || RetrunStatus is null || RetrunStatus='')";
$query2 = $dbh -> prepare($sql2);
$query2->bindParam(':sid',$sid,PDO::PARAM_STR);
$query2->bindParam(':rsts',$rsts,PDO::PARAM_STR);
$query2->execute();
$results2=$query2->fetchAll(PDO::FETCH_OBJ);
$returnedbooks=$query2->rowCount();

$ret =$dbh -> prepare("SELECT id from tblissuedbookdetails where StudentID=:sid");
$ret->bindParam(':sid',$sid,PDO::PARAM_STR);
$ret->execute();
$results22=$ret->fetchAll(PDO::FETCH_OBJ);
$totalissuedbook=$ret->rowCount();

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

        <div class="dashboard-shell">
        <div class="dashboard-hero">
            <div class="dashboard-hero__content">
                <span class="dashboard-kicker">Our Track Record</span>
                <h2 class="dashboard-hero__title">A smarter reading hub for every visit.</h2>
                <p class="dashboard-hero__text">Track issued books, orders, returns, cart activity, and pending requests from one clean dashboard built for daily library use.</p>
            </div>
            <div class="dashboard-hero__stats">
                <a href="listed-books.php" class="dashboard-hero-stat">
                    <span class="dashboard-hero-stat__value js-count-up" data-target="<?php echo (int)$listdbooks; ?>" data-suffix="+">0+</span>
                    <span class="dashboard-hero-stat__label">Books available in the catalog</span>
                </a>
                <a href="issued-books.php" class="dashboard-hero-stat">
                    <span class="dashboard-hero-stat__value js-count-up" data-target="<?php echo (int)$totalissuedbook; ?>">0</span>
                    <span class="dashboard-hero-stat__label">Books you have already issued</span>
                </a>
                <a href="my-orders.php" class="dashboard-hero-stat">
                    <span class="dashboard-hero-stat__value js-count-up" data-target="<?php echo (int)$orderCount; ?>">0</span>
                    <span class="dashboard-hero-stat__label">Orders placed from your account</span>
                </a>
                <a href="book-requests.php" class="dashboard-hero-stat">
                    <span class="dashboard-hero-stat__value js-count-up" data-target="<?php echo (int)$pendingRequestCount; ?>">0</span>
                    <span class="dashboard-hero-stat__label">Requests waiting for approval</span>
                </a>
            </div>
        </div>

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
    <script type="text/javascript">
    (function () {
        var counters=document.querySelectorAll('.js-count-up');
        var duration=1400;

        function animateCounter(counter, index) {
            var target=parseInt(counter.getAttribute('data-target'), 10) || 0;
            var suffix=counter.getAttribute('data-suffix') || '';
            var startTime=null;

            function step(timestamp) {
                if (!startTime) {
                    startTime=timestamp;
                }

                var progress=Math.min((timestamp - startTime) / duration, 1);
                var eased=1 - Math.pow(1 - progress, 3);
                var current=Math.round(target * eased);
                counter.textContent=current + suffix;

                if (progress < 1) {
                    window.requestAnimationFrame(step);
                } else {
                    counter.textContent=target + suffix;
                }
            }

            window.setTimeout(function () {
                window.requestAnimationFrame(step);
            }, index * 120);
        }

        for (var i=0; i<counters.length; i++) {
            animateCounter(counters[i], i);
        }
    })();
    </script>
</body>
</html>
<?php } ?>
