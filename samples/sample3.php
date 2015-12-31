<?php 
//$url = "http://share.meebo.com/content/katy_perry/wallpapers/3.jpg"; 
$url = $_GET['url']; 

$url = str_replace("http:/","http://",$url); 

$allowed = array('jpg','gif','png'); 
// exit(0);
$pos = strrpos($_GET['url'], "."); 
$str = substr($_GET['url'],($pos + 1)); 
$ch = curl_init(); 
$timeout = 0; 
curl_setopt ($ch, CURLOPT_URL, $url); 
curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout); 

// Getting binary data 
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1); 

$image = curl_exec($ch); 
curl_close($ch); 
// output to browser 
$im = @imagecreatefromstring($image); 

$tw = @imagesx($im); 
if(!$tw){ 
    // Font directory + font name 
    $font = '../../fonts/Austrise.ttf'; 
    // Size of the font 
    $fontSize = 18; 
    // Height of the image 
    $height = 32; 
    // Width of the image 
    $width = 250; 
    // Text 
    $str = 'Couldn\'t get image.'; 
    $img_handle = imagecreate ($width, $height) or die ("Cannot Create image"); 
    // Set the Background Color RGB 
    $backColor = imagecolorallocate($img_handle, 255, 255, 255); 
    // Set the Text Color RGB 
    $txtColor = imagecolorallocate($img_handle, 20, 92, 137);  
    $textbox = imagettfbbox($fontSize, 0, $font, $str) or die('Error in imagettfbbox function'); 
    $x = ($width - $textbox[4])/2; 
    $y = ($height - $textbox[5])/2; 
    imagettftext($img_handle, $fontSize, 0, $x, $y, $txtColor, $font , $str) or die('Error in imagettftext function'); 
    header('Content-Type: image/jpeg'); 
    imagejpeg($img_handle,NULL,100); 
    imagedestroy($img_handle);  
}else{ 
    if($str == 'jpg' || $str == 'jpeg') 
        header("Content-type: image/jpeg"); 
    if($str == 'gif') 
        header("Content-type: image/gif"); 
    if($str == 'png') 
        header("Content-type: image/png"); 
    $th = imagesy($im); 
    $thumbWidth = 200; 
    if($tw <= $thumbWidth){ 
        $thumbWidth = $tw; 
    } 
    $thumbHeight = $th * ($thumbWidth / $tw); 
    $thumbImg = imagecreatetruecolor($thumbWidth, $thumbHeight); 
    if($str == 'gif'){ 
        $colorTransparent = imagecolortransparent($im); 
        imagefill($thumbImg, 0, 0, $colorTransparent); 
        imagecolortransparent($thumbImg, $colorTransparent); 
    } 
    if($str == 'png'){ 
        imagealphablending($thumbImg, false); 
        imagesavealpha($thumbImg,true); 
        $transparent = imagecolorallocatealpha($thumbImg, 255, 255, 255, 127); 
        imagefilledrectangle($thumbImg, 0, 0, $thumbWidth, $thumbHeight, $transparent); 
    } 
    imagecopyresampled($thumbImg, $im, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $tw, $th); 


    if($str == 'jpg' || $str == 'jpeg'){ 
        imagejpeg($thumbImg, NULL, 100); 
    } 
    if($str == 'gif'){ 
        imagegif($thumbImg); 
    } 
    if($str == 'png'){ 
        imagealphablending($thumbImg,TRUE); 
        imagepng($thumbImg, NULL, 9, PNG_ALL_FILTERS); 
    } 
         
    imagedestroy($thumbImg); 
} 