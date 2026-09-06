<?php

declare(strict_types=1);

namespace App\Services\Cms;

use DateTimeImmutable;
interface CmsRepositoryInterface
{
 public function publicPosts(string$type):array;public function publicPost(string$type,string$slug):?array;public function publicPage(string$slug):?array;public function publicLibrary():array;public function publicMedia(string$publicId):?array;public function publicDownload(string$slug,DateTimeImmutable$now):?array;public function adminDashboard():array;
 public function savePage(int$actor,?string$id,array$data,DateTimeImmutable$now):string;public function savePost(int$actor,?string$id,array$data,DateTimeImmutable$now):string;public function saveFaq(int$actor,?string$id,array$data,DateTimeImmutable$now):string;public function createMedia(int$actor,array$data,DateTimeImmutable$now):string;public function saveDownload(int$actor,?string$id,array$data,DateTimeImmutable$now):string;
 public function updateMediaStatus(int$actor,string$publicId,string$status,DateTimeImmutable$now):void;
}
