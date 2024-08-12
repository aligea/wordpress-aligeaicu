<?php
require_once dirname(__DIR__) . '/wp-load.php';
//require_once dirname(__DIR__) . '/class-wpdb.php';

function do_authorization($privilaged_user)
{
    if (!isset($_SERVER['PHP_AUTH_USER'])) {
        header('WWW-Authenticate: Basic realm="Authentication System"');
        header('HTTP/1.0 401 Unauthorized');
        exit;
    }
    $valid_passwords = array("mario" => "carbonell");
    $valid_users = array_keys($valid_passwords);

    $username = $_SERVER['PHP_AUTH_USER'];
    $pass = md5($_SERVER['PHP_AUTH_PW']);

    $validate_user = wp_check_password($_SERVER['PHP_AUTH_PW'], wp_hash_password($_SERVER['PHP_AUTH_PW']));

    print_r($validate_user);
    die();
    print_r($privilaged_user);
    die();
    $validated = (in_array($user, $valid_users)) && ($pass == $valid_passwords[$user]);
    if (!$validated) {
        header('WWW-Authenticate: Basic realm=');
        header('HTTP/1.0 401 Unauthorized');
        die("Not authorized");
    }
}

function fetch_privilaged_user($user_login)
{
    #--- ambil wp user admin & cek authorisasi
    $userdata = WP_User::get_data_by("login", $user_login);
    if (! $userdata) {
        return false;
    }
    $privilaged_user = new WP_User();
    $privilaged_user->init($userdata);
    //do_authorization($privilaged_user);

    return $privilaged_user;
}

function insert_featured_images($scraping_image_url)
{
    $image_url = $scraping_image_url;

    $upload_dir = wp_upload_dir();
    $image_data = file_get_contents($image_url);
    $filename = basename($image_url);

    if (wp_mkdir_p($upload_dir['path'])) {
        $file = $upload_dir['path'] . '/' . $filename;
    } else {
        $file = $upload_dir['basedir'] . '/' . $filename;
    }

    file_put_contents($file, $image_data);

    $wp_filetype = wp_check_filetype($filename, null);

    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name($filename),
        'post_content' => '',
        'post_status' => 'inherit'
    );

    $attach_id = wp_insert_attachment($attachment, $file);
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $file);
    wp_update_attachment_metadata($attach_id, $attach_data);

    return $attach_id;
}

function fetch_category_id($category_name)
{
    $term_id = 0;
    if (!$category_name) {
        $category_name = "news";
    }
    $categories = get_categories();

    foreach ($categories as $key => $value) {
        if (strtolower($value->slug) == strtolower($category_name)){
            $term_id = $value->term_id;
        }
     }
    return $term_id;
}

#--- 1. ambil wp user admin & cek authorisasi
$username = 'admin';
$privilaged_user = fetch_privilaged_user($username);


#--- 2. check apa postingan tersebut sudah ada atau gak
$posttitle = $_POST['post_title'];
$postid = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_title = '" . $posttitle . "'");
if ($postid > 0) {
    //echo $postid;
    return false;
}

#--- 3. insert featured images
$featured_image_id = insert_featured_images($_POST['image_url']);

#--- 4. insert new post content
$newPostArr = array(
    'post_author'           => $privilaged_user->ID,
    'post_content'          => $_POST['post_content'],
    'post_title'            => $_POST['post_title'],
    'post_status'           => 'publish',
);
$newPostId = wp_insert_post($newPostArr);
$post_categories_id = fetch_category_id($_POST['post_category']);
set_post_thumbnail($newPostId, $featured_image_id);
wp_set_post_categories($newPostId, $post_categories_id);

#---5. set post category

echo $newPostId;
