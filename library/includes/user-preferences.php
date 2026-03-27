<?php
function ensureUserPreferencesTable($dbh)
{
    static $tableReady = false;
    if ($tableReady || !isset($dbh)) {
        return;
    }

    $sql = "CREATE TABLE IF NOT EXISTS tbluserpreferences (
        id INT(11) NOT NULL AUTO_INCREMENT,
        StudentId VARCHAR(100) NOT NULL,
        ThemeColor VARCHAR(7) DEFAULT '#2563eb',
        ProfileImage VARCHAR(255) DEFAULT NULL,
        CreatedAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        UpdatedAt TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_student_pref (StudentId)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $dbh->exec($sql);
    $tableReady = true;
}

function getDefaultUserThemeOptions()
{
    return array(
        '#2563EB' => 'Ocean Blue',
        '#EF4444' => 'Coral Red',
        '#10B981' => 'Emerald Green',
        '#F59E0B' => 'Amber Gold',
        '#8B5CF6' => 'Royal Violet',
        '#0F766E' => 'Deep Teal'
    );
}

function sanitizeThemeColor($color)
{
    $color = strtoupper(trim((string)$color));
    if (preg_match('/^#[0-9A-F]{6}$/', $color)) {
        return $color;
    }

    return '#2563EB';
}

function hexToRgba($hex, $alpha)
{
    $hex = ltrim(sanitizeThemeColor($hex), '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    return 'rgba(' . $r . ',' . $g . ',' . $b . ',' . $alpha . ')';
}

function normalizeUserProfileImagePath($path)
{
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }

    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#^\./+#', '', $path);
    $path = ltrim($path, '/');

    $profilePos = stripos($path, 'assets/img/profiles/');
    if ($profilePos !== false) {
        $path = substr($path, $profilePos);
    }

    if (stripos($path, 'library/') === 0) {
        $path = substr($path, strlen('library/'));
    }

    return $path;
}

function resolveUserProfileImagePath($path)
{
    $relativePath = normalizeUserProfileImagePath($path);
    if ($relativePath === '') {
        return '';
    }

    $profilePrefix = 'assets/img/profiles/';
    if (stripos($relativePath, $profilePrefix) !== 0) {
        $profilePos = stripos($relativePath, $profilePrefix);
        if ($profilePos === false) {
            return '';
        }

        $relativePath = substr($relativePath, $profilePos);
    }

    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($absolutePath)) {
        return '';
    }

    return $relativePath;
}

function getUserPreferences($dbh, $studentId)
{
    ensureUserPreferencesTable($dbh);

    $preferences = array(
        'ThemeColor' => '#2563EB',
        'ProfileImage' => ''
    );

    $query = $dbh->prepare("SELECT ThemeColor, ProfileImage FROM tbluserpreferences WHERE StudentId=:sid LIMIT 1");
    $query->bindParam(':sid', $studentId, PDO::PARAM_STR);
    $query->execute();
    $row = $query->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        if (!empty($row['ThemeColor'])) {
            $preferences['ThemeColor'] = sanitizeThemeColor($row['ThemeColor']);
        }
        if (!empty($row['ProfileImage'])) {
            $preferences['ProfileImage'] = resolveUserProfileImagePath($row['ProfileImage']);
        }
    }

    return $preferences;
}

function saveUserPreferences($dbh, $studentId, $themeColor, $profileImage)
{
    ensureUserPreferencesTable($dbh);

    $themeColor = sanitizeThemeColor($themeColor);
    $profileImage = resolveUserProfileImagePath($profileImage);
    if ($profileImage === '') {
        $profileImage = null;
    }

    $sql = "INSERT INTO tbluserpreferences (StudentId, ThemeColor, ProfileImage)
            VALUES (:sid, :themeColor, :profileImage)
            ON DUPLICATE KEY UPDATE ThemeColor=VALUES(ThemeColor), ProfileImage=VALUES(ProfileImage)";
    $query = $dbh->prepare($sql);
    $query->bindParam(':sid', $studentId, PDO::PARAM_STR);
    $query->bindParam(':themeColor', $themeColor, PDO::PARAM_STR);
    $query->bindParam(':profileImage', $profileImage, PDO::PARAM_STR);
    $query->execute();
}

function handleUserProfileUpload($file, $studentId)
{
    if (!isset($file) || !isset($file['error']) || (int)$file['error'] === 4) {
        return array('success' => true, 'path' => null, 'message' => '');
    }

    if ((int)$file['error'] !== 0) {
        return array('success' => false, 'path' => null, 'message' => 'Profile image upload failed.');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return array('success' => false, 'path' => null, 'message' => 'Invalid image upload.');
    }

    $allowedTypes = array(
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    );
    $allowedExtensions = array(
        'jpg' => 'jpg',
        'jpeg' => 'jpg',
        'png' => 'png',
        'gif' => 'gif',
        'webp' => 'webp'
    );

    $mimeType = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? (string)finfo_file($finfo, $file['tmp_name']) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
    }

    $originalExtension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $extension = '';
    if ($mimeType !== '' && isset($allowedTypes[$mimeType])) {
        $extension = $allowedTypes[$mimeType];
    } elseif (isset($allowedExtensions[$originalExtension])) {
        $extension = $allowedExtensions[$originalExtension];
    }

    if ($extension === '') {
        return array('success' => false, 'path' => null, 'message' => 'Use JPG, JPEG, PNG, GIF, or WEBP for the profile picture.');
    }

    if (!empty($file['size']) && (int)$file['size'] > 2 * 1024 * 1024) {
        return array('success' => false, 'path' => null, 'message' => 'Profile picture must be under 2MB.');
    }

    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'profiles';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$studentId) . '_' . substr(md5(uniqid((string)$studentId, true)), 0, 12) . '.' . $extension;
    $absolutePath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
        return array('success' => false, 'path' => null, 'message' => 'Could not save the uploaded profile picture.');
    }

    return array(
        'success' => true,
        'path' => resolveUserProfileImagePath('assets/img/profiles/' . $fileName),
        'message' => ''
    );
}
?>
