<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use App\Security\Security;
use App\Services\ProgrammeApplications\ProgrammeApplicationRepositoryInterface;
use App\Services\Notifications\NotificationOutbox;
use DateTimeImmutable;
use DomainException;
use PDO;
use PDOException;

final class ProgrammeApplicationRepository extends Repository implements ProgrammeApplicationRepositoryInterface
{
    public function __construct(Connection $database) { parent::__construct($database); }

    public function publishedProgrammes(): array
    {
        return $this->connection()->query("SELECT programmes.public_id,programmes.code,programmes.name,types.name AS type_name,programmes.entry_requirements FROM programmes INNER JOIN programme_types types ON types.id=programmes.programme_type_id AND types.active=1 WHERE programmes.status='published' AND programmes.deleted_at IS NULL ORDER BY programmes.display_order,programmes.name")->fetchAll();
    }

    public function applicationsForUser(int $userId): array
    {
        $statement=$this->connection()->prepare(<<<'SQL'
            SELECT applications.public_id,applications.application_reference,applications.status,applications.motivation,
                   applications.professional_background,applications.highest_qualification,applications.current_organisation,
                   applications.decision_note,applications.submitted_at,applications.updated_at,
                   programmes.public_id AS programme_public_id,programmes.code AS programme_code,programmes.name AS programme_name,
                   types.name AS programme_type,enrolments.public_id AS enrolment_public_id,enrolments.enrolment_number,enrolments.status AS enrolment_status
            FROM programme_applications applications
            INNER JOIN programmes ON programmes.id=applications.programme_id
            INNER JOIN programme_types types ON types.id=programmes.programme_type_id
            LEFT JOIN programme_enrolments enrolments ON enrolments.programme_application_id=applications.id
            WHERE applications.user_id=:user ORDER BY applications.created_at DESC,applications.id DESC
            SQL);
        $statement->execute(['user'=>$userId]); return $statement->fetchAll();
    }

    public function applicationForUser(int $userId,string $publicId): ?array
    {
        $statement=$this->connection()->prepare(<<<'SQL'
            SELECT applications.*,programmes.public_id AS programme_public_id,programmes.code AS programme_code,
                   programmes.name AS programme_name,programmes.status AS programme_status
            FROM programme_applications applications INNER JOIN programmes ON programmes.id=applications.programme_id
            WHERE applications.public_id=:id AND applications.user_id=:user LIMIT 1
            SQL);
        $statement->execute(['id'=>$publicId,'user'=>$userId]); $row=$statement->fetch(); if(!is_array($row))return null; unset($row['id'],$row['user_id'],$row['programme_id'],$row['reviewed_by_user_id']); return $row;
    }

    public function saveDraft(int $userId,?string $publicId,string $programmePublicId,array $data,DateTimeImmutable $now): array
    {
        try{return $this->database->transaction(function(PDO $db)use($userId,$publicId,$programmePublicId,$data,$now):array{
            $user=$db->prepare("SELECT id FROM users WHERE id=:id AND status='active' AND email_verified_at IS NOT NULL LIMIT 1 FOR UPDATE");$user->execute(['id'=>$userId]);if($user->fetchColumn()===false)throw new DomainException('Only an active, verified account may apply.');
            $programme=$db->prepare("SELECT id FROM programmes WHERE public_id=:id AND status='published' AND deleted_at IS NULL LIMIT 1");$programme->execute(['id'=>$programmePublicId]);$programmeId=$programme->fetchColumn();if($programmeId===false)throw new DomainException('The selected programme is not accepting applications.');
            if($publicId!==null){$application=$db->prepare("SELECT id,status,programme_id FROM programme_applications WHERE public_id=:id AND user_id=:user LIMIT 1 FOR UPDATE");$application->execute(['id'=>$publicId,'user'=>$userId]);$row=$application->fetch();if(!is_array($row)||$row['status']!=='draft'||(int)$row['programme_id']!==(int)$programmeId)throw new DomainException('This programme application cannot be edited.');$applicationId=(int)$row['id'];}
            else{$existing=$db->prepare('SELECT id,public_id,status FROM programme_applications WHERE user_id=:user AND programme_id=:programme LIMIT 1 FOR UPDATE');$existing->execute(['user'=>$userId,'programme'=>$programmeId]);$row=$existing->fetch();if(is_array($row)){if($row['status']!=='draft')throw new DomainException('You already have an application for this programme.');$applicationId=(int)$row['id'];$publicId=(string)$row['public_id'];}else{$publicId=Security::uuidV4();$insert=$db->prepare("INSERT INTO programme_applications (public_id,user_id,programme_id,status,created_at,updated_at) VALUES (:public_id,:user,:programme,'draft',:created_at,:updated_at)");$insert->execute(['public_id'=>$publicId,'user'=>$userId,'programme'=>$programmeId,'created_at'=>$this->date($now),'updated_at'=>$this->date($now)]);$applicationId=(int)$db->lastInsertId();}}
            $update=$db->prepare('UPDATE programme_applications SET motivation=:motivation,professional_background=:background,highest_qualification=:qualification,current_organisation=:organisation,updated_at=:updated_at WHERE id=:id');$update->execute(['motivation'=>$data['motivation'],'background'=>$data['professional_background'],'qualification'=>$data['highest_qualification'],'organisation'=>$data['current_organisation'],'updated_at'=>$this->date($now),'id'=>$applicationId]);
            $this->audit($db,$userId,'programme_application.draft_saved','programme_application',(string)$publicId,['programme_public_id'=>$programmePublicId],$now);return $this->owned($db,$userId,(string)$publicId)??throw new DomainException('Programme application unavailable.');
        });}catch(PDOException $e){if($e->getCode()==='23000')throw new DomainException('You already have an application for this programme.');throw $e;}
    }

    public function submit(int $userId,string $publicId,string $reference,string $declarationName,DateTimeImmutable $now): array
    {
        return $this->database->transaction(function(PDO $db)use($userId,$publicId,$reference,$declarationName,$now):array{
            $row=$this->lockOwned($db,$userId,$publicId);
            if(in_array($row['status'],['submitted','review','approved','enrolled','completed'],true))return $this->owned($db,$userId,$publicId)??[];
            if($row['status']!=='draft')throw new DomainException('This programme application cannot be submitted.');
            foreach(['motivation'=>20,'professional_background'=>20] as $field=>$minimum)if(mb_strlen(trim((string)$row[$field]))<$minimum)throw new DomainException('Complete all required application information before submission.');
            if(trim((string)$row['highest_qualification'])==='')throw new DomainException('Complete all required application information before submission.');
            $statement=$db->prepare("UPDATE programme_applications SET application_reference=:reference,status='submitted',declaration_name=:declaration,declaration_accepted_at=:accepted,submitted_at=:submitted,updated_at=:updated WHERE id=:id AND status='draft'");$stamp=$this->date($now);$statement->execute(['reference'=>$reference,'declaration'=>$declarationName,'accepted'=>$stamp,'submitted'=>$stamp,'updated'=>$stamp,'id'=>$row['id']]);if($statement->rowCount()!==1)throw new DomainException('The application changed before submission completed.');
            $this->audit($db,$userId,'programme_application.submitted','programme_application',$publicId,['from'=>'draft','to'=>'submitted','reference'=>$reference],$now);NotificationOutbox::enqueue($db,'programme.application_submitted',$publicId,$userId,null,'programmes','Programme application submitted','Your programme application has been received for review.','/portal/programmes',['in_app','email'],$now);return $this->owned($db,$userId,$publicId)??[];
        });
    }

    public function withdraw(int $userId,string $publicId,DateTimeImmutable $now): void
    {
        $this->database->transaction(function(PDO $db)use($userId,$publicId,$now):void{$row=$this->lockOwned($db,$userId,$publicId);if($row['status']==='withdrawn')return;if(!in_array($row['status'],['draft','submitted','review'],true))throw new DomainException('This programme application can no longer be withdrawn by the applicant.');$from=(string)$row['status'];$db->prepare("UPDATE programme_applications SET status='withdrawn',withdrawn_at=:now,updated_at=:now WHERE id=:id")->execute(['now'=>$this->date($now),'id'=>$row['id']]);$this->audit($db,$userId,'programme_application.withdrawn','programme_application',$publicId,['from'=>$from,'to'=>'withdrawn'],$now);});
    }

    public function search(array $filters): array
    {
        $where=["applications.status<>'draft'"];$params=[];if(($filters['status']??'')!==''){$where[]='applications.status=:status';$params['status']=$filters['status'];}if(($filters['search']??'')!==''){$where[]='(applications.application_reference LIKE :search OR users.email LIKE :search OR programmes.name LIKE :search OR programmes.code LIKE :search)';$params['search']='%'.$filters['search'].'%';}
        $sql="SELECT applications.public_id,applications.application_reference,applications.status,applications.submitted_at,applications.updated_at,users.email,programmes.code AS programme_code,programmes.name AS programme_name,enrolments.enrolment_number FROM programme_applications applications INNER JOIN users ON users.id=applications.user_id INNER JOIN programmes ON programmes.id=applications.programme_id LEFT JOIN programme_enrolments enrolments ON enrolments.programme_application_id=applications.id WHERE ".implode(' AND ',$where).' ORDER BY COALESCE(applications.submitted_at,applications.updated_at) DESC,applications.id DESC';$statement=$this->connection()->prepare($sql);$statement->execute($params);return ['items'=>$statement->fetchAll()];
    }

    public function administrativeApplication(string $publicId): ?array
    {
        return $this->adminFind($this->connection(),$publicId);
    }

    public function review(int $actor,string $publicId,?string $note,DateTimeImmutable $now): array
    {
        return $this->transition($actor,$publicId,['submitted'],'review','programme_application.review_started',$note,$now);
    }
    public function approve(int $actor,string $publicId,?string $note,DateTimeImmutable $now): array
    {
        return $this->database->transaction(function(PDO $db)use($actor,$publicId,$note,$now):array{$row=$this->lockAdmin($db,$publicId);if(in_array($row['status'],['approved','enrolled','completed'],true))return $this->adminFind($db,$publicId)??[];if($row['status']!=='review')throw new DomainException('Only an application under review can be approved.');$stamp=$this->date($now);$db->prepare("UPDATE programme_applications SET status='approved',decision_note=:note,approved_at=:now,reviewed_by_user_id=:actor,updated_at=:now WHERE id=:id AND status='review'")->execute(['note'=>$note,'now'=>$stamp,'actor'=>$actor,'id'=>$row['id']]);$this->audit($db,$actor,'programme_application.approved','programme_application',$publicId,['from'=>'review','to'=>'approved'],$now);return $this->adminFind($db,$publicId)??[];});
    }
    public function reject(int $actor,string $publicId,string $note,DateTimeImmutable $now): array
    {
        return $this->database->transaction(function(PDO $db)use($actor,$publicId,$note,$now):array{$row=$this->lockAdmin($db,$publicId);if($row['status']==='rejected')return $this->adminFind($db,$publicId)??[];if(!in_array($row['status'],['submitted','review'],true))throw new DomainException('This application cannot be rejected in its current state.');$from=(string)$row['status'];$stamp=$this->date($now);$db->prepare("UPDATE programme_applications SET status='rejected',decision_note=:note,rejected_at=:now,reviewed_at=COALESCE(reviewed_at,:now),reviewed_by_user_id=:actor,updated_at=:now WHERE id=:id")->execute(['note'=>$note,'now'=>$stamp,'actor'=>$actor,'id'=>$row['id']]);$this->audit($db,$actor,'programme_application.rejected','programme_application',$publicId,['from'=>$from,'to'=>'rejected'],$now);return $this->adminFind($db,$publicId)??[];});
    }

    public function enrol(int $actor,string $publicId,string $numberPrefix,DateTimeImmutable $now): array
    {
        return $this->database->transaction(function(PDO $db)use($actor,$publicId,$numberPrefix,$now):array{$row=$this->lockAdmin($db,$publicId);$existing=$db->prepare('SELECT enrolment_number,status FROM programme_enrolments WHERE programme_application_id=:id LIMIT 1 FOR UPDATE');$existing->execute(['id'=>$row['id']]);$enrolment=$existing->fetch();if(is_array($enrolment))return ['idempotent'=>true,'enrolment'=>$enrolment,'application'=>$this->adminFind($db,$publicId)];if($row['status']!=='approved')throw new DomainException('Only an approved application can be enrolled.');
            $member=$db->prepare('SELECT id FROM members WHERE user_id=:user AND status=\'active\' LIMIT 1');$member->execute(['user'=>$row['user_id']]);$memberId=$member->fetchColumn();$memberId=$memberId===false?null:(int)$memberId;$number='';
            for($attempt=0;$attempt<5;$attempt++){$number=$numberPrefix.'-'.$now->format('Y').'-'.strtoupper(bin2hex(random_bytes(5)));try{$insert=$db->prepare("INSERT INTO programme_enrolments (public_id,programme_application_id,user_id,member_id,programme_id,enrolment_number,status,enrolled_by_user_id,enrolled_at,created_at,updated_at) VALUES (:public_id,:application,:user,:member,:programme,:number,'enrolled',:actor,:now,:now,:now)");$insert->execute(['public_id'=>Security::uuidV4(),'application'=>$row['id'],'user'=>$row['user_id'],'member'=>$memberId,'programme'=>$row['programme_id'],'number'=>$number,'actor'=>$actor,'now'=>$this->date($now)]);break;}catch(PDOException $e){if($e->getCode()!=='23000'||$attempt===4)throw $e;$number='';}}
            if($number==='')throw new DomainException('A unique enrolment number could not be generated.');$db->prepare("UPDATE programme_applications SET status='enrolled',updated_at=:now WHERE id=:id AND status='approved'")->execute(['now'=>$this->date($now),'id'=>$row['id']]);$this->audit($db,$actor,'programme_enrolment.created','programme_enrolment',$publicId,['from'=>'approved','to'=>'enrolled','enrolment_number'=>$number],$now);return ['idempotent'=>false,'enrolment'=>['enrolment_number'=>$number,'status'=>'enrolled'],'application'=>$this->adminFind($db,$publicId)];});
    }

    public function complete(int $actor,string $publicId,DateTimeImmutable $now): array { return $this->enrolmentTransition($actor,$publicId,'completed',null,$now); }
    public function withdrawEnrolment(int $actor,string $publicId,string $note,DateTimeImmutable $now): array { return $this->enrolmentTransition($actor,$publicId,'withdrawn',$note,$now); }

    private function enrolmentTransition(int $actor,string $publicId,string $to,?string $note,DateTimeImmutable $now): array
    {
        return $this->database->transaction(function(PDO $db)use($actor,$publicId,$to,$note,$now):array{$row=$this->lockAdmin($db,$publicId);$enrolment=$db->prepare('SELECT id,status FROM programme_enrolments WHERE programme_application_id=:id LIMIT 1 FOR UPDATE');$enrolment->execute(['id'=>$row['id']]);$en=$enrolment->fetch();if(!is_array($en))throw new DomainException('No programme enrolment exists for this application.');if($en['status']===$to)return $this->adminFind($db,$publicId)??[];if($en['status']!=='enrolled')throw new DomainException('This enrolment is already in a final state.');$field=$to==='completed'?'completed_at':'withdrawn_at';$stamp=$this->date($now);$db->prepare("UPDATE programme_enrolments SET status=:status,{$field}=:now,updated_at=:now WHERE id=:id AND status='enrolled'")->execute(['status'=>$to,'now'=>$stamp,'id'=>$en['id']]);$db->prepare('UPDATE programme_applications SET status=:status,decision_note=COALESCE(:note,decision_note),withdrawn_at=CASE WHEN :status=\'withdrawn\' THEN :now ELSE withdrawn_at END,updated_at=:now WHERE id=:id')->execute(['status'=>$to,'note'=>$note,'now'=>$stamp,'id'=>$row['id']]);$this->audit($db,$actor,'programme_enrolment.'.$to,'programme_enrolment',$publicId,['from'=>'enrolled','to'=>$to],$now);return $this->adminFind($db,$publicId)??[];});
    }

    /** @param list<string> $from */ private function transition(int $actor,string $publicId,array $from,string $to,string $action,?string $note,DateTimeImmutable $now):array
    {
        return $this->database->transaction(function(PDO $db)use($actor,$publicId,$from,$to,$action,$note,$now):array{$row=$this->lockAdmin($db,$publicId);if($row['status']===$to)return $this->adminFind($db,$publicId)??[];if(!in_array($row['status'],$from,true))throw new DomainException('This application cannot move to the requested state.');$old=(string)$row['status'];$stamp=$this->date($now);$db->prepare('UPDATE programme_applications SET status=:status,review_note=:note,reviewed_at=:now,reviewed_by_user_id=:actor,updated_at=:now WHERE id=:id')->execute(['status'=>$to,'note'=>$note,'now'=>$stamp,'actor'=>$actor,'id'=>$row['id']]);$this->audit($db,$actor,$action,'programme_application',$publicId,['from'=>$old,'to'=>$to],$now);return $this->adminFind($db,$publicId)??[];});
    }
    private function lockOwned(PDO $db,int $user,string $publicId):array{$statement=$db->prepare('SELECT * FROM programme_applications WHERE public_id=:id AND user_id=:user LIMIT 1 FOR UPDATE');$statement->execute(['id'=>$publicId,'user'=>$user]);$row=$statement->fetch();if(!is_array($row))throw new DomainException('Programme application not found.');return $row;}
    private function lockAdmin(PDO $db,string $publicId):array{$statement=$db->prepare('SELECT * FROM programme_applications WHERE public_id=:id LIMIT 1 FOR UPDATE');$statement->execute(['id'=>$publicId]);$row=$statement->fetch();if(!is_array($row))throw new DomainException('Programme application not found.');return $row;}
    private function owned(PDO $db,int $user,string $publicId):?array{$statement=$db->prepare('SELECT applications.*,programmes.public_id AS programme_public_id,programmes.code AS programme_code,programmes.name AS programme_name FROM programme_applications applications INNER JOIN programmes ON programmes.id=applications.programme_id WHERE applications.public_id=:id AND applications.user_id=:user LIMIT 1');$statement->execute(['id'=>$publicId,'user'=>$user]);$row=$statement->fetch();if(!is_array($row))return null;unset($row['id'],$row['user_id'],$row['programme_id'],$row['reviewed_by_user_id']);return $row;}
    private function adminFind(PDO $db,string $publicId):?array{$statement=$db->prepare("SELECT applications.*,users.email,programmes.public_id AS programme_public_id,programmes.code AS programme_code,programmes.name AS programme_name,types.name AS programme_type,enrolments.public_id AS enrolment_public_id,enrolments.enrolment_number,enrolments.status AS enrolment_status,enrolments.enrolled_at,enrolments.completed_at FROM programme_applications applications INNER JOIN users ON users.id=applications.user_id INNER JOIN programmes ON programmes.id=applications.programme_id INNER JOIN programme_types types ON types.id=programmes.programme_type_id LEFT JOIN programme_enrolments enrolments ON enrolments.programme_application_id=applications.id WHERE applications.public_id=:id AND applications.status<>'draft' LIMIT 1");$statement->execute(['id'=>$publicId]);$row=$statement->fetch();if(!is_array($row))return null;unset($row['id'],$row['user_id'],$row['programme_id'],$row['reviewed_by_user_id']);return $row;}
    /** @param array<string,mixed> $values */ private function audit(PDO $db,int $actor,string $action,string $type,string $id,array $values,DateTimeImmutable $now):void{$db->prepare("INSERT INTO audit_logs (actor_user_id,action,auditable_type,auditable_id,description,new_values,request_id,created_at) VALUES (:actor,:action,:type,:id,'A professional programme workflow action was completed.',:values,:request_id,:created_at)")->execute(['actor'=>$actor,'action'=>$action,'type'=>$type,'id'=>$id,'values'=>json_encode($values,JSON_THROW_ON_ERROR),'request_id'=>bin2hex(random_bytes(16)),'created_at'=>$this->date($now)]);}
    private function date(DateTimeImmutable $date):string{return $date->format('Y-m-d H:i:s.u');}
}
