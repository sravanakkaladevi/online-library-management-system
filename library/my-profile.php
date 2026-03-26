<?php
session_start();
include('includes/config.php');
include_once('includes/user-preferences.php');
error_reporting(0);
if (empty($_SESSION['login']) || empty($_SESSION['stdid'])) {
    unset($_SESSION['login']);
    unset($_SESSION['stdid']);
    header('location:index.php');
    exit;
} else {
    $sid = $_SESSION['stdid'];
    $profileMessage = '';
    $profileError = '';
    $themeOptions = getDefaultUserThemeOptions();
    $preferences = getUserPreferences($dbh, $sid);

    if (isset($_POST['update'])) {
        $fname = trim((string)$_POST['fullanme']);
        $mobileno = trim((string)$_POST['mobileno']);
        $selectedTheme = isset($_POST['theme_color']) ? (string)$_POST['theme_color'] : $preferences['ThemeColor'];
        $customThemeColor = isset($_POST['custom_theme_color']) ? (string)$_POST['custom_theme_color'] : $preferences['ThemeColor'];
        $themeColor = ($selectedTheme === 'custom') ? $customThemeColor : $selectedTheme;
        $profileImagePath = $preferences['ProfileImage'];

        $uploadResult = handleUserProfileUpload(isset($_FILES['profile_image']) ? $_FILES['profile_image'] : null, $sid);
        if (!$uploadResult['success']) {
            $profileError = $uploadResult['message'];
        } elseif (!empty($uploadResult['path'])) {
            $profileImagePath = $uploadResult['path'];
        }

        if ($profileError === '') {
            $sql = "update tblstudents set FullName=:fname,MobileNumber=:mobileno where StudentId=:sid";
            $query = $dbh->prepare($sql);
            $query->bindParam(':sid', $sid, PDO::PARAM_STR);
            $query->bindParam(':fname', $fname, PDO::PARAM_STR);
            $query->bindParam(':mobileno', $mobileno, PDO::PARAM_STR);
            $query->execute();

            saveUserPreferences($dbh, $sid, $themeColor, $profileImagePath);
            $preferences = getUserPreferences($dbh, $sid);
            $profileMessage = 'Your profile has been updated.';
        }
    }
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Online Library Management System | My Profile</title>
    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
    <style type="text/css">
        .profile-settings-card {
            border-radius: 22px;
            border: 1px solid #dbe8f5;
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .profile-settings-card .panel-heading {
            background: linear-gradient(135deg, <?php echo htmlentities($preferences['ThemeColor']);?> 0%, #0f172a 180%);
            color: #fff;
            border-bottom: none;
            padding: 16px 22px;
            font-weight: 700;
        }

        .profile-preview {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 24px;
            padding: 20px;
            border-radius: 18px;
            background: <?php echo htmlentities(hexToRgba($preferences['ThemeColor'], 0.10));?>;
        }

        .profile-preview__avatar {
            width: 78px;
            height: 78px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, <?php echo htmlentities($preferences['ThemeColor']);?> 0%, #0ea5e9 140%);
            color: #fff;
            font-size: 28px;
            font-weight: 800;
            flex: 0 0 auto;
        }

        .profile-preview__avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-theme-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-top: 10px;
        }

        .profile-theme-option {
            position: relative;
            display: block;
            border: 1px solid #dbe8f5;
            border-radius: 16px;
            padding: 12px;
            cursor: pointer;
            background: #fff;
        }

        .profile-theme-option input {
            position: absolute;
            opacity: 0;
        }

        .profile-theme-option__swatch {
            width: 100%;
            height: 34px;
            border-radius: 999px;
            margin-bottom: 8px;
        }

        .profile-theme-option--active {
            border-color: <?php echo htmlentities($preferences['ThemeColor']);?>;
            box-shadow: 0 12px 28px <?php echo htmlentities(hexToRgba($preferences['ThemeColor'], 0.18));?>;
        }

        .profile-note {
            margin-top: 8px;
            color: #64748b;
            font-size: 13px;
        }
    </style>
</head>
<body>
<?php include('includes/header.php');?>
    <div class="content-wrapper">
        <div class="container">
            <div class="row pad-botm">
                <div class="col-md-12">
                    <h4 class="header-line">My Profile</h4>
                </div>
            </div>
            <div class="row">
                <div class="col-md-9 col-md-offset-1">
                    <div class="panel panel-danger profile-settings-card">
                        <div class="panel-heading">My Profile Settings</div>
                        <div class="panel-body">
                            <?php if ($profileMessage !== '') { ?>
                                <div class="alert alert-success"><?php echo htmlentities($profileMessage);?></div>
                            <?php } ?>
                            <?php if ($profileError !== '') { ?>
                                <div class="alert alert-danger"><?php echo htmlentities($profileError);?></div>
                            <?php } ?>
                            <form name="signup" method="post" enctype="multipart/form-data">
<?php
$sql = "SELECT StudentId,FullName,EmailId,MobileNumber,RegDate,UpdationDate,Status from tblstudents where StudentId=:sid";
$query = $dbh->prepare($sql);
$query->bindParam(':sid', $sid, PDO::PARAM_STR);
$query->execute();
$results = $query->fetchAll(PDO::FETCH_OBJ);
if ($query->rowCount() > 0) {
    foreach ($results as $result) {
        $previewName = trim((string)$result->FullName);
        $nameParts = preg_split('/\s+/', $previewName);
        $initials = '';
        foreach ($nameParts as $namePart) {
            if ($namePart !== '') {
                $initials .= strtoupper(substr($namePart, 0, 1));
            }
            if (strlen($initials) >= 2) {
                break;
            }
        }
        if ($initials === '') {
            $initials = 'R';
        }
?>
                                <div class="profile-preview">
                                    <div class="profile-preview__avatar">
                                        <?php if (!empty($preferences['ProfileImage'])) { ?>
                                            <img src="<?php echo htmlentities($preferences['ProfileImage']);?>" alt="<?php echo htmlentities($result->FullName);?>">
                                        <?php } else { ?>
                                            <?php echo htmlentities($initials);?>
                                        <?php } ?>
                                    </div>
                                    <div>
                                        <h4 style="margin:0 0 4px;"><?php echo htmlentities($result->FullName);?></h4>
                                        <p style="margin:0;"><?php echo htmlentities($result->EmailId);?></p>
                                        <p class="profile-note">Set a unique profile picture and a custom theme color for this user account.</p>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Student ID :</label>
                                    <?php echo htmlentities($result->StudentId);?>
                                </div>

                                <div class="form-group">
                                    <label>Reg Date :</label>
                                    <?php echo htmlentities($result->RegDate);?>
                                </div>
                                <?php if ($result->UpdationDate != "") { ?>
                                <div class="form-group">
                                    <label>Last Updation Date :</label>
                                    <?php echo htmlentities($result->UpdationDate);?>
                                </div>
                                <?php } ?>

                                <div class="form-group">
                                    <label>Profile Status :</label>
                                    <?php if ($result->Status == 1) { ?>
                                        <span style="color: green">Active</span>
                                    <?php } else { ?>
                                        <span style="color: red">Blocked</span>
                                    <?php } ?>
                                </div>

                                <div class="form-group">
                                    <label>Enter Full Name</label>
                                    <input class="form-control" type="text" name="fullanme" value="<?php echo htmlentities($result->FullName);?>" autocomplete="off" required />
                                </div>

                                <div class="form-group">
                                    <label>Mobile Number :</label>
                                    <input class="form-control" type="text" name="mobileno" maxlength="10" value="<?php echo htmlentities($result->MobileNumber);?>" autocomplete="off" required />
                                </div>

                                <div class="form-group">
                                    <label>Enter Email</label>
                                    <input class="form-control" type="email" name="email" id="emailid" value="<?php echo htmlentities($result->EmailId);?>" autocomplete="off" required readonly />
                                </div>

                                <div class="form-group" id="profile-picture">
                                    <label>Profile Picture</label>
                                    <input class="form-control" type="file" name="profile_image" accept=".jpg,.jpeg,.png,.gif,.webp" />
                                    <p class="profile-note">Upload JPG, PNG, GIF, or WEBP up to 2MB.</p>
                                </div>

                                <div class="form-group" id="theme-color">
                                    <label>Select Theme Color</label>
                                    <div class="profile-theme-grid">
                                        <?php foreach ($themeOptions as $themeValue => $themeLabel) { ?>
                                        <label class="profile-theme-option<?php if (strtoupper($preferences['ThemeColor']) === strtoupper($themeValue)) { echo ' profile-theme-option--active'; } ?>">
                                            <input type="radio" name="theme_color" value="<?php echo htmlentities($themeValue);?>" <?php if (strtoupper($preferences['ThemeColor']) === strtoupper($themeValue)) { echo 'checked'; } ?>>
                                            <span class="profile-theme-option__swatch" style="background: linear-gradient(135deg, <?php echo htmlentities($themeValue);?> 0%, #0f172a 180%);"></span>
                                            <span><?php echo htmlentities($themeLabel);?></span>
                                        </label>
                                        <?php } ?>
                                        <label class="profile-theme-option<?php if (!array_key_exists(strtoupper($preferences['ThemeColor']), $themeOptions)) { echo ' profile-theme-option--active'; } ?>">
                                            <input type="radio" name="theme_color" value="custom" <?php if (!array_key_exists(strtoupper($preferences['ThemeColor']), $themeOptions)) { echo 'checked'; } ?>>
                                            <span class="profile-theme-option__swatch" style="background: linear-gradient(135deg, <?php echo htmlentities($preferences['ThemeColor']);?> 0%, #0f172a 180%);"></span>
                                            <span>Custom Color</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Custom Theme Color</label>
                                    <input class="form-control" type="color" name="custom_theme_color" value="<?php echo htmlentities($preferences['ThemeColor']);?>" />
                                    <p class="profile-note">Choose any color if you want a fully personalized theme for this user.</p>
                                </div>
<?php
    }
}
?>
                                <button type="submit" name="update" class="btn btn-primary" id="submit">Update Now</button>
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
<?php } ?>
