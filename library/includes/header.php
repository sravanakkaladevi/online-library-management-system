<?php
include_once(__DIR__ . '/user-preferences.php');
$cartCount=0;
$currentPage=basename($_SERVER['PHP_SELF']);
$currentSort=isset($_GET['sort']) ? trim((string)$_GET['sort']) : '';
$isRecommendedView=($currentPage==='listed-books.php' && $currentSort==='recommended');
$accountName='Reader';
$accountEmail='';
$accountInitial='R';
$accountImage='';
$themeColor='#2563EB';
$themeColorSoft='rgba(37,99,235,0.16)';
if(!empty($_SESSION['login']) && !empty($_SESSION['stdid']) && isset($dbh))
{
try {
$cartCountQuery = $dbh->prepare("SELECT COALESCE(SUM(Quantity),0) as cartCount FROM tblcart WHERE StudentId=:sid");
$cartCountQuery->bindParam(':sid', $_SESSION['stdid'], PDO::PARAM_STR);
$cartCountQuery->execute();
$cartCountResult = $cartCountQuery->fetch(PDO::FETCH_ASSOC);
if($cartCountResult && isset($cartCountResult['cartCount']))
{
$cartCount=(int)$cartCountResult['cartCount'];
}

$preferences=getUserPreferences($dbh, $_SESSION['stdid']);
$themeColor=$preferences['ThemeColor'];
$themeColorSoft=hexToRgba($themeColor, 0.16);
$accountImage=$preferences['ProfileImage'];

$accountQuery = $dbh->prepare("SELECT FullName, EmailId FROM tblstudents WHERE StudentId=:sid LIMIT 1");
$accountQuery->bindParam(':sid', $_SESSION['stdid'], PDO::PARAM_STR);
$accountQuery->execute();
$accountRow = $accountQuery->fetch(PDO::FETCH_ASSOC);
if($accountRow)
{
$accountName=trim((string)$accountRow['FullName'])!=='' ? trim((string)$accountRow['FullName']) : 'Reader';
$accountEmail=(string)$accountRow['EmailId'];
$nameParts=preg_split('/\s+/', $accountName);
$initials='';
foreach($nameParts as $namePart)
{
if($namePart!=='')
{
$initials.=strtoupper(substr($namePart,0,1));
}
if(strlen($initials)>=2)
{
break;
}
}
if($initials!=='')
{
$accountInitial=$initials;
}
}
}
catch (Exception $e) {
$cartCount=0;
}
}
?>
<style type="text/css">
:root {
    --user-theme-color: <?php echo htmlentities($themeColor);?>;
    --user-theme-soft: <?php echo htmlentities($themeColorSoft);?>;
}
</style>
<div class="navbar navbar-inverse set-radius-zero" >
        <div class="container">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" >

                    <img src="assets/img/logo.png" />
                </a>

            </div>
<?php if($_SESSION['login'])
{
?> 
            <div class="right-div">
                <a href="logout.php" class="logout-btn pull-right" aria-label="Logout">
                    <span class="logout-btn__sign">
                        <svg viewBox="0 0 512 512" aria-hidden="true" focusable="false">
                            <path d="M377.9 105.9L500.7 228.7c7.2 7.2 11.3 17.1 11.3 27.3s-4.1 20.1-11.3 27.3L377.9 406.1c-6.4 6.4-15 9.9-24 9.9c-18.7 0-33.9-15.2-33.9-33.9l0-62.1-128 0c-17.7 0-32-14.3-32-32l0-64c0-17.7 14.3-32 32-32l128 0 0-62.1c0-18.7 15.2-33.9 33.9-33.9c9 0 17.6 3.6 24 9.9zM160 96L96 96c-17.7 0-32 14.3-32 32l0 256c0 17.7 14.3 32 32 32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-64 0c-53 0-96-43-96-96L0 128C0 75 43 32 96 32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32z"></path>
                        </svg>
                    </span>
                    <span class="logout-btn__text">Logout</span>
                </a>
            </div>
            <?php }?>
    </div>
    </div>
    <!-- LOGO HEADER END-->
<?php if($_SESSION['login'])
{
?>    
<section class="menu-section">
        <div class="container">
            <div class="row ">
                <div class="col-md-12">
                    <div class="navbar-collapse collapse ">
                        <ul id="menu-top" class="nav navbar-nav navbar-right">
                            <li><a href="dashboard.php" class="<?php if($currentPage==='dashboard.php'){ echo 'menu-top-active'; } ?>"><i class="fa fa-th-large nav-icon"></i>DASHBOARD</a></li>
                            <li><a href="listed-books.php" class="<?php if($currentPage==='listed-books.php' && !$isRecommendedView){ echo 'menu-top-active'; } ?>"><i class="fa fa-book nav-icon"></i>Listed Books</a></li>
                            <li><a href="listed-books.php?sort=recommended" class="<?php if($isRecommendedView){ echo 'menu-top-active'; } ?>"><i class="fa fa-magic nav-icon"></i>Recommended</a></li>
                            <li><a href="issued-books.php" class="<?php if($currentPage==='issued-books.php'){ echo 'menu-top-active'; } ?>"><i class="fa fa-bookmark nav-icon"></i>Issued Books</a></li>
                            <li><a href="book-requests.php" class="<?php if($currentPage==='book-requests.php'){ echo 'menu-top-active'; } ?>"><i class="fa fa-envelope-open nav-icon"></i>Book Requests</a></li>
                            <li><a href="cart.php" class="<?php if($currentPage==='cart.php'){ echo 'menu-top-active'; } ?>"><i class="fa fa-shopping-cart nav-icon"></i>Cart<?php if($cartCount>0){ echo " (".$cartCount.")"; } ?></a></li>
                             <li>
                                <a href="#" class="dropdown-toggle account-menu-toggle" id="userAccountMenu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <span class="account-avatar<?php if($accountImage!==''){ echo ' account-avatar--image'; } ?>">
                                        <?php if($accountImage!==''){ ?>
                                            <img src="<?php echo htmlentities($accountImage);?>" alt="<?php echo htmlentities($accountName);?>">
                                        <?php } else { ?>
                                            <?php echo htmlentities($accountInitial);?>
                                        <?php } ?>
                                    </span>
                                    <span>Account</span> <i class="fa fa-angle-down"></i>
                                </a>
                                <ul class="dropdown-menu account-dropdown-menu" role="menu" aria-labelledby="userAccountMenu">
                                    <li class="account-dropdown-card" role="presentation">
                                        <div class="account-dropdown-card__avatar<?php if($accountImage!==''){ echo ' account-dropdown-card__avatar--image'; } ?>">
                                            <?php if($accountImage!==''){ ?>
                                                <img src="<?php echo htmlentities($accountImage);?>" alt="<?php echo htmlentities($accountName);?>">
                                            <?php } else { ?>
                                                <?php echo htmlentities($accountInitial);?>
                                            <?php } ?>
                                        </div>
                                        <div class="account-dropdown-card__meta">
                                            <strong><?php echo htmlentities($accountName);?></strong>
                                            <span><?php echo htmlentities($accountEmail);?></span>
                                        </div>
                                    </li>
                                    <li role="presentation"><a role="menuitem" tabindex="-1" href="my-profile.php"><i class="fa fa-user nav-icon"></i>My Profile</a></li>
                                    <li role="presentation"><a role="menuitem" tabindex="-1" href="my-orders.php"><i class="fa fa-truck nav-icon"></i>My Orders</a></li>
                                    <li role="presentation"><a role="menuitem" tabindex="-1" href="change-password.php"><i class="fa fa-lock nav-icon"></i>Change Password</a></li>
                                </ul>
                            </li>

                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </section>
    <?php } else { ?>
        <section class="menu-section">
        <div class="container">
            <div class="row ">
                <div class="col-md-12">
                    <div class="navbar-collapse collapse ">
                        <ul id="menu-top" class="nav navbar-nav navbar-right">                        
                          
      <li><a href="index.php"><i class="fa fa-home nav-icon"></i>Home</a></li>
      <li><a href="index.php#ulogin"><i class="fa fa-sign-in nav-icon"></i>User Login</a></li>
                            <li><a href="signup.php"><i class="fa fa-user-plus nav-icon"></i>User Signup</a></li>
                         
                            <li><a href="adminlogin.php"><i class="fa fa-shield nav-icon"></i>Admin Login</a></li>

                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <?php } ?>
