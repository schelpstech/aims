<?php

declare(strict_types=1);

namespace App\Services\Cms;

use RuntimeException;
final class CmsMediaStorage
{
 private const TYPES=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf','text/plain'=>'txt','text/csv'=>'csv'];
 public function __construct(private readonly string$root,private readonly int$maxBytes){}
 public function store(array$file):array{$error=(int)($file['error']??UPLOAD_ERR_NO_FILE);if($error!==UPLOAD_ERR_OK)throw new RuntimeException('Select a valid media file.');$temporary=is_string($file['tmp_name']??null)?$file['tmp_name']:'';if($temporary===''||!is_uploaded_file($temporary))throw new RuntimeException('The upload could not be verified.');$size=filesize($temporary);if(!is_int($size)||$size<1||$size>max(1,$this->maxBytes))throw new RuntimeException('The upload exceeds the configured size limit.');$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($temporary);if(!is_string($mime)||!isset(self::TYPES[$mime]))throw new RuntimeException('This file type is not permitted. Executable and script-capable formats are rejected.');$relative=date('Y/m').'/'.bin2hex(random_bytes(24)).'.'.self::TYPES[$mime];$destination=$this->root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relative);$directory=dirname($destination);if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory))throw new RuntimeException('Media storage is unavailable.');$hash=hash_file('sha256',$temporary);if(!is_string($hash)||!move_uploaded_file($temporary,$destination))throw new RuntimeException('The media file could not be stored.');@chmod($destination,0600);$original=mb_substr(preg_replace('/[\x00-\x1F\x7F]+/u','',basename(str_replace('\\','/',(string)($file['name']??'file'))))??'file',0,255);return['original_name'=>$original?:'file.'.self::TYPES[$mime],'storage_path'=>$relative,'mime_type'=>$mime,'size_bytes'=>$size,'sha256'=>$hash];}
 public function read(string$path):string{if(preg_match('#^[0-9]{4}/[0-9]{2}/[a-f0-9]{48}\.(?:jpg|png|webp|pdf|txt|csv)$#D',$path)!==1)throw new RuntimeException('Media is unavailable.');$absolute=$this->root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$path);$data=is_file($absolute)?file_get_contents($absolute):false;if(!is_string($data))throw new RuntimeException('Media is unavailable.');return$data;}
 public function delete(string$path):void{if(preg_match('#^[0-9]{4}/[0-9]{2}/[a-f0-9]{48}\.(?:jpg|png|webp|pdf|txt|csv)$#D',$path)!==1)return;$absolute=$this->root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$path);if(is_file($absolute))@unlink($absolute);}
}
