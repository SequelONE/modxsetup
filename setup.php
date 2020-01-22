<?php
// MODX Setup - скачивает, распаковывает, изменяет названия файлов и начинает установку последней версии MODX Revolution
// Author: SEQUEL.ONE

$context = stream_context_create([
  'http' => [
     'header' => 'User-Agent: Awesome-Octocat-App'
   ]
]);

$url = 'https://api.github.com/repos/modxcms/revolution/tags';
$json = file_get_contents($url, false, $context);

$releases = json_decode($json, true);
//$version = $releases['0']['name'];
//$version = preg_replace('/[^0-9.]/', '', $version);
$version = '2.7.2';

$link = 'https://modx.s3.amazonaws.com/releases/'.$version.'/modx-'.$version.'-pl.zip';
$setupLocation = 'setup/index.php';
error_reporting(0);
ini_set('display_errors', 0);
set_time_limit(0);
ini_set('max_execution_time',0);
header('Content-Type: text/html; charset=utf-8');
if(extension_loaded('xdebug')){
    ini_set('xdebug.max_nesting_level', 100000);
}
class ModxInstaller{
	static public function downloadFile ($url, $path) {
		$newfname = $path;
		try {
			$file = fopen ($url, "rb");
			if ($file) {
				$newf = fopen ($newfname, "wb");
				if ($newf)
				while(!feof($file)) {
					fwrite($newf, fread($file, 1024 * 8 ), 1024 * 8 );
				}
			}			
		} catch(Exception $e) {
			$this->errors[] = array('ERROR:Download',$e->getMessage());
			return false;
		}
		if ($file) fclose($file);
		if ($newf) fclose($newf);
		return true;
	}	
	static public function removeFolder($path){
		$dir = realpath($path);
		if ( !is_dir($dir)) return;
		$it = new RecursiveDirectoryIterator($dir);
		$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
		foreach($files as $file) {
			if ($file->getFilename() === '.' || $file->getFilename() === '..') {
				continue;
			}
			if ($file->isDir()){
				rmdir($file->getRealPath());
			} else {
				unlink($file->getRealPath());
			}
		}
		rmdir($dir);
	}
	static public function copyFolder($src, $dest) {
		$path = realpath($src);
		$dest = realpath($dest);
		$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);
		foreach($objects as $name => $object)
		{			
			$startsAt = substr(dirname($name), strlen($path));
			self::mmkDir($dest.$startsAt);
			if ( $object->isDir() ) {
				self::mmkDir($dest.substr($name, strlen($path)));
			}
			if(is_writable($dest.$startsAt) and $object->isFile())
			{
				copy((string)$name, $dest.$startsAt.DIRECTORY_SEPARATOR.basename($name));
			}
		}
	}
	static public function mmkDir($folder, $perm=0777) {
		if(!is_dir($folder)) {
			mkdir($folder, $perm);
		}
	}
}
//run unzip and install
ModxInstaller::downloadFile($link ,"modx.zip");
$zip = new ZipArchive;
$res = $zip->open(dirname(__FILE__)."/modx.zip");
$zip->extractTo(dirname(__FILE__).'/temp' );
$zip->close();
unlink(dirname(__FILE__).'/modx.zip');
if ($handle = opendir(dirname(__FILE__).'/temp')) {
	while (false !== ($name = readdir($handle))) if ($name != "." && $name != "..") $dir = $name;
	closedir($handle);
}
ModxInstaller::copyFolder(dirname(__FILE__).'/temp/'.$dir, dirname(__FILE__).'/');
ModxInstaller::removeFolder(dirname(__FILE__).'/temp');
rename('ht.access','.htaccess');
rename('manager/ht.access','manager/.htaccess');
rename('core/ht.access','core/.htaccess');
unlink(basename(__FILE__));
header('Location: '.$setupLocation);
