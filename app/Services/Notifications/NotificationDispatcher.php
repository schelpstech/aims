<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Logging\Logger;
use App\Security\Security;
use DateTimeImmutable;
use PDO;
use Throwable;

final class NotificationDispatcher
{
    public function __construct(private readonly PDO $database,private readonly EmailChannelInterface $email,private readonly SmsChannelInterface $sms,private readonly Logger $logger,private readonly int $maxAttempts=5){}

    public function dispatch(int $limit=50): array
    {
        $lock=Security::uuidV4();$now=new DateTimeImmutable('now');$stamp=$now->format('Y-m-d H:i:s.u');
        $this->database->beginTransaction();
        try {
            $select=$this->database->prepare("SELECT id FROM notification_outbox WHERE status IN ('pending','retry') AND available_at<=:now AND attempt_count<:max ORDER BY id LIMIT ".max(1,min(200,$limit)).' FOR UPDATE');
            $select->execute(['now'=>$stamp,'max'=>$this->maxAttempts]);$ids=array_map('intval',$select->fetchAll(PDO::FETCH_COLUMN));
            if($ids!==[]){$marks=implode(',',array_fill(0,count($ids),'?'));$update=$this->database->prepare("UPDATE notification_outbox SET status='processing',lock_token=?,locked_at=?,attempt_count=attempt_count+1 WHERE id IN ($marks)");$update->execute(array_merge([$lock,$stamp],$ids));}
            $this->database->commit();
        } catch(Throwable $e){if($this->database->inTransaction())$this->database->rollBack();throw $e;}
        $rows=[];if($ids!==[]){$query=$this->database->prepare('SELECT outbox.*,COALESCE(outbox.recipient_email,users.email) AS resolved_email FROM notification_outbox outbox LEFT JOIN users ON users.id=outbox.user_id WHERE outbox.lock_token=:lock ORDER BY outbox.id');$query->execute(['lock'=>$lock]);$rows=$query->fetchAll();}
        $sent=0;$failed=0;
        foreach($rows as $row){$errors=[];$attempt=(int)$row['attempt_count'];
            if((int)$row['send_in_app']===1){try{$insert=$this->database->prepare("INSERT IGNORE INTO member_notifications (notification_outbox_id,public_id,user_id,category,title,body,action_url,created_at) VALUES (:outbox,:public_id,:user,:category,:title,:body,:url,:created)");$insert->execute(['outbox'=>$row['id'],'public_id'=>Security::uuidV4(),'user'=>$row['user_id'],'category'=>$row['category'],'title'=>$row['title'],'body'=>$row['body'],'url'=>$row['action_url'],'created'=>$stamp]);$this->delivery($row['id'],'in_app','sent',$attempt,null,$stamp);}catch(Throwable $e){$errors[]='in_app';$this->delivery($row['id'],'in_app','failed',$attempt,'in_app_write_failed',$stamp);}}
            if((int)$row['send_email']===1){try{$ok=is_string($row['resolved_email'])&&$this->email->send($row['resolved_email'],$row['title'],$row['body'].($row['action_url']?"\n\n".$row['action_url']:''));$this->delivery($row['id'],'email',$ok?'sent':'failed',$attempt,$ok?null:'provider_rejected',$stamp);if(!$ok)$errors[]='email';}catch(Throwable $e){$errors[]='email';$this->delivery($row['id'],'email','failed',$attempt,'delivery_exception',$stamp);}}
            if((int)$row['send_sms']===1){$status=$this->sms->isAvailable()?'failed':'skipped';if($status==='failed')$errors[]='sms';$this->delivery($row['id'],'sms',$status,$attempt,$status==='skipped'?'provider_unconfigured':'delivery_failed',$stamp);}
            if($errors===[]){$this->database->prepare("UPDATE notification_outbox SET status='completed',processed_at=:now,lock_token=NULL,locked_at=NULL,last_error=NULL WHERE id=:id AND lock_token=:lock")->execute(['now'=>$stamp,'id'=>$row['id'],'lock'=>$lock]);$sent++;}
            else{$dead=$attempt >= $this->maxAttempts;$delay=min(3600,60*(2**max(0,$attempt-1)));$this->database->prepare("UPDATE notification_outbox SET status=:status,available_at=:available,lock_token=NULL,locked_at=NULL,last_error=:error WHERE id=:id AND lock_token=:lock")->execute(['status'=>$dead?'dead':'retry','available'=>$now->modify('+'.$delay.' seconds')->format('Y-m-d H:i:s.u'),'error'=>'Failed channels: '.implode(',',$errors),'id'=>$row['id'],'lock'=>$lock]);$failed++;$this->logger->error('Notification delivery failed.',['outbox_public_id'=>$row['public_id'],'channels'=>$errors]);}
        }
        return ['claimed'=>count($rows),'completed'=>$sent,'failed'=>$failed];
    }
    private function delivery(int|string $outbox,string $channel,string $status,int $attempt,?string $code,string $stamp):void{$s=$this->database->prepare('INSERT INTO notification_deliveries (notification_outbox_id,channel,status,attempt_number,error_code,attempted_at,sent_at) VALUES (:outbox,:channel,:status,:attempt,:code,:at,:sent) ON DUPLICATE KEY UPDATE status=VALUES(status),error_code=VALUES(error_code),attempted_at=VALUES(attempted_at),sent_at=VALUES(sent_at)');$s->execute(['outbox'=>$outbox,'channel'=>$channel,'status'=>$status,'attempt'=>$attempt,'code'=>$code,'at'=>$stamp,'sent'=>$status==='sent'?$stamp:null]);}
}
