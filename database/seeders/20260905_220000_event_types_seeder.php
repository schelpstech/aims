<?php

declare(strict_types=1);

use App\Database\Seeder;

return new class implements Seeder {
    public function run(\PDO $database): void
    {
        $statement=$database->prepare('INSERT INTO event_types (public_id,name,slug,display_order,active) VALUES (:public_id,:name,:slug,:display_order,1) ON DUPLICATE KEY UPDATE name=VALUES(name),slug=VALUES(slug),display_order=VALUES(display_order),active=1');
        $types=[
            ['9a8a52e0-f79b-4a71-8ac5-b44504540701','Induction','induction'],['9a8a52e0-f79b-4a71-8ac5-b44504540702','Investiture','investiture'],
            ['9a8a52e0-f79b-4a71-8ac5-b44504540703','Conference','conference'],['9a8a52e0-f79b-4a71-8ac5-b44504540704','Workshop','workshop'],
            ['9a8a52e0-f79b-4a71-8ac5-b44504540705','Webinar','webinar'],['9a8a52e0-f79b-4a71-8ac5-b44504540706','AGM','agm'],
            ['9a8a52e0-f79b-4a71-8ac5-b44504540707','Training','training'],['9a8a52e0-f79b-4a71-8ac5-b44504540708','Awards','awards'],
            ['9a8a52e0-f79b-4a71-8ac5-b44504540709','Other','other'],
        ];foreach($types as $index=>[$id,$name,$slug])$statement->execute(['public_id'=>$id,'name'=>$name,'slug'=>$slug,'display_order'=>($index+1)*10]);
    }
};
