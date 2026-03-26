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
                <a href="logout.php" class="btn btn-danger pull-right">LOG ME OUT</a>
            </div>
            <?php }?>
        </div>
    </div>
    <!-- LOGO HEADER END-->
<?php
$cartCount=0;
$currentPage=basename($_SERVER['PHP_SELF']);
$currentSort=isset($_GET['sort']) ? trim((string)$_GET['sort']) : '';
$isRecommendedView=($currentPage==='listed-books.php' && $currentSort==='recommended');
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
}
catch (Exception $e) {
$cartCount=0;
}
}
if($_SESSION['login'])
{
?>    
<section class="menu-section">
        <div class="container">
            <div class="row ">
                <div class="col-md-12">
                    <div class="navbar-collapse collapse ">
                        <ul id="menu-top" class="nav navbar-nav navbar-right">
                            <li><a href="dashboard.php" class="<?php if($currentPage==='dashboard.php'){ echo 'menu-top-active'; } ?>">DASHBOARD</a></li>
                            <li><a href="listed-books.php" class="<?php if($currentPage==='listed-books.php' && !$isRecommendedView){ echo 'menu-top-active'; } ?>">Listed Books</a></li>
                            <li><a href="listed-books.php?sort=recommended" class="<?php if($isRecommendedView){ echo 'menu-top-active'; } ?>">Recommended</a></li>
                            <li><a href="issued-books.php" class="<?php if($currentPage==='issued-books.php'){ echo 'menu-top-active'; } ?>">Issued Books</a></li>
                            <li><a href="book-requests.php" class="<?php if($currentPage==='book-requests.php'){ echo 'menu-top-active'; } ?>">Book Requests</a></li>
                            <li><a href="cart.php" class="<?php if($currentPage==='cart.php'){ echo 'menu-top-active'; } ?>">Cart<?php if($cartCount>0){ echo " (".$cartCount.")"; } ?></a></li>
                             <li>
                                <a href="#" class="dropdown-toggle" id="ddlmenuItem" data-toggle="dropdown"> Account <i class="fa fa-angle-down"></i></a>
                                <ul class="dropdown-menu" role="menu" aria-labelledby="ddlmenuItem">
                                    <li role="presentation"><a role="menuitem" tabindex="-1" href="my-profile.php">My Profile</a></li>
                                    <li role="presentation"><a role="menuitem" tabindex="-1" href="my-orders.php">My Orders</a></li>
                                     <li role="presentation"><a role="menuitem" tabindex="-1" href="change-password.php">Change Password</a></li>
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
                          
      <li><a href="index.php">Home</a></li>
      <li><a href="index.php#ulogin">User Login</a></li>
                            <li><a href="signup.php">User Signup</a></li>
                         
                            <li><a href="adminlogin.php">Admin Login</a></li>

                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <?php } ?>
