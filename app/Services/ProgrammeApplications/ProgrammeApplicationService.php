<?php

declare(strict_types=1);

namespace App\Services\ProgrammeApplications;

use App\Authorization\PermissionCheckerInterface;
use App\Validation\Validator;
use DateTimeImmutable;
use DomainException;

final class ProgrammeApplicationService
{
    private const VIEW_PERMISSION = 'programme.application_view';
    private const FILTER_STATUSES = ['submitted','review','approved','rejected','enrolled','completed','withdrawn'];

    public function __construct(private readonly ProgrammeApplicationRepositoryInterface $repository, private readonly PermissionCheckerInterface $permissions, private readonly Validator $validator, private readonly string $enrolmentPrefix = 'AIMS-PRG') {}

    /** @return array{programmes:list<array<string,mixed>>,applications:list<array<string,mixed>>} */
    public function applicantDashboard(int $userId): array
    {
        return $userId > 0 ? ['programmes'=>$this->repository->publishedProgrammes(),'applications'=>$this->repository->applicationsForUser($userId)] : ['programmes'=>[],'applications'=>[]];
    }

    /** @param array<string, mixed> $input */
    public function saveDraft(int $userId, array $input): ProgrammeApplicationResult
    {
        if ($userId < 1) return ProgrammeApplicationResult::failure(401,'Authentication is required.');
        $rules=['application_public_id'=>'nullable|string|max:36','programme'=>'required|string|max:36','motivation'=>'nullable|string|max:5000','professional_background'=>'nullable|string|max:5000','highest_qualification'=>'nullable|string|max:160','current_organisation'=>'nullable|string|max:200'];
        if (!$this->validator->validate($input,$rules)) return ProgrammeApplicationResult::failure(422,'Review the highlighted application fields.',$this->validator->errors());
        $values=$this->validator->validated(); $application=$this->optionalUuid($values['application_public_id']??null); $programme=(string)($values['programme']??'');
        if (($values['application_public_id']??null)!==null && $application===null || !$this->uuid($programme)) return ProgrammeApplicationResult::failure(422,'Select a valid published programme.');
        $data=[]; foreach(['motivation','professional_background','highest_qualification','current_organisation'] as $field){$value=is_string($values[$field]??null)?trim($values[$field]):'';$data[$field]=$value===''?null:$value;}
        try { return ProgrammeApplicationResult::success('Your programme application draft was saved.',$this->repository->saveDraft($userId,$application,$programme,$data,new DateTimeImmutable('now'))); }
        catch (DomainException $e) { return ProgrammeApplicationResult::failure(409,$e->getMessage()); }
    }

    /** @param array<string, mixed> $input */
    public function submit(int $userId, array $input): ProgrammeApplicationResult
    {
        $id=is_string($input['application_public_id']??null)?trim($input['application_public_id']):''; $name=is_string($input['declaration_name']??null)?trim($input['declaration_name']):''; $accepted=filter_var($input['declaration_accepted']??false,FILTER_VALIDATE_BOOL);
        if ($userId<1 || !$this->uuid($id)) return ProgrammeApplicationResult::failure(404,'Programme application not found.');
        $application=$this->repository->applicationForUser($userId,$id); if($application===null) return ProgrammeApplicationResult::failure(404,'Programme application not found.');
        $errors=[];
        if(mb_strlen(trim((string)($application['motivation']??'')))<20)$errors['motivation'][]='Provide at least 20 characters explaining your motivation.';
        if(mb_strlen(trim((string)($application['professional_background']??'')))<20)$errors['professional_background'][]='Provide at least 20 characters about your professional background.';
        if(trim((string)($application['highest_qualification']??''))==='')$errors['highest_qualification'][]='Provide your highest qualification.';
        if(mb_strlen($name)<2 || mb_strlen($name)>200)$errors['declaration_name'][]='Enter your full declaration name.';
        if(!$accepted)$errors['declaration_accepted'][]='Accept the declaration before submitting.';
        if($errors!==[])return ProgrammeApplicationResult::failure(422,'Complete all required application information.',$errors);
        try{$reference='PRG-'.date('Y').'-'.strtoupper(bin2hex(random_bytes(6)));return ProgrammeApplicationResult::success('Your programme application was submitted for review.',$this->repository->submit($userId,$id,$reference,$name,new DateTimeImmutable('now')));}catch(DomainException $e){return ProgrammeApplicationResult::failure(409,$e->getMessage());}
    }

    public function withdraw(int $userId,string $publicId): ProgrammeApplicationResult
    {
        if($userId<1||!$this->uuid($publicId))return ProgrammeApplicationResult::failure(404,'Programme application not found.');
        try{$this->repository->withdraw($userId,$publicId,new DateTimeImmutable('now'));return ProgrammeApplicationResult::success('Your programme application was withdrawn.');}catch(DomainException $e){return ProgrammeApplicationResult::failure(409,$e->getMessage());}
    }

    /** @param array<string,mixed> $filters */ public function administrativeApplications(int $actor,array $filters): ProgrammeApplicationResult
    {
        if(!$this->allowed($actor,self::VIEW_PERMISSION))return ProgrammeApplicationResult::failure(403,'You are not authorized to view programme applications.');
        $status=is_string($filters['status']??null)?trim($filters['status']):''; $search=is_string($filters['search']??null)?trim($filters['search']):'';
        if($status!==''&&!in_array($status,self::FILTER_STATUSES,true))return ProgrammeApplicationResult::failure(422,'The application status filter is invalid.');
        if(mb_strlen($search)>100)return ProgrammeApplicationResult::failure(422,'Search text must not exceed 100 characters.');
        return ProgrammeApplicationResult::success('Programme applications loaded.',$this->repository->search(['status'=>$status,'search'=>$search]));
    }

    public function administrativeApplication(int $actor,string $publicId): ProgrammeApplicationResult
    {
        if(!$this->allowed($actor,self::VIEW_PERMISSION))return ProgrammeApplicationResult::failure(403,'You are not authorized to view programme applications.'); if(!$this->uuid($publicId))return ProgrammeApplicationResult::failure(404,'Programme application not found.');
        $application=$this->repository->administrativeApplication($publicId);return $application?ProgrammeApplicationResult::success('Programme application loaded.',$application):ProgrammeApplicationResult::failure(404,'Programme application not found.');
    }

    public function review(int $actor,string $id,?string $note): ProgrammeApplicationResult { return $this->adminAction($actor,'programme.application_review',$id,$note,false,fn($now)=>$this->repository->review($actor,$id,$note,$now),'Application moved to review.'); }
    public function approve(int $actor,string $id,?string $note): ProgrammeApplicationResult { return $this->adminAction($actor,'programme.application_approve',$id,$note,false,fn($now)=>$this->repository->approve($actor,$id,$note,$now),'Programme application approved. Approval does not enrol the applicant.'); }
    public function reject(int $actor,string $id,?string $note): ProgrammeApplicationResult { return $this->adminAction($actor,'programme.application_reject',$id,$note,true,fn($now)=>$this->repository->reject($actor,$id,(string)$note,$now),'Programme application rejected and retained.'); }
    public function enrol(int $actor,string $id): ProgrammeApplicationResult
    {
        $prefix=strtoupper(trim($this->enrolmentPrefix)); if(preg_match('/^[A-Z0-9-]{2,20}$/D',$prefix)!==1)return ProgrammeApplicationResult::failure(500,'Programme enrolment number configuration is invalid.');
        return $this->adminAction($actor,'programme.enrol',$id,null,false,fn($now)=>$this->repository->enrol($actor,$id,$prefix,$now),'Applicant enrolled.');
    }
    public function complete(int $actor,string $id): ProgrammeApplicationResult { return $this->adminAction($actor,'programme.enrol',$id,null,false,fn($now)=>$this->repository->complete($actor,$id,$now),'Programme enrolment marked completed.'); }
    public function withdrawEnrolment(int $actor,string $id,?string $note): ProgrammeApplicationResult { return $this->adminAction($actor,'programme.enrol',$id,$note,true,fn($now)=>$this->repository->withdrawEnrolment($actor,$id,(string)$note,$now),'Programme enrolment withdrawn.'); }

    /** @param callable(DateTimeImmutable):array<string,mixed> $action */
    private function adminAction(int $actor,string $permission,string $id,?string $note,bool $required,callable $action,string $message): ProgrammeApplicationResult
    {
        if(!$this->allowed($actor,$permission))return ProgrammeApplicationResult::failure(403,'You are not authorized to perform this programme application action.'); if(!$this->uuid($id))return ProgrammeApplicationResult::failure(404,'Programme application not found.');
        $note=is_string($note)?trim($note):null; if($note==='')$note=null; if(($required&&$note===null)||($note!==null&&(mb_strlen($note)<3||mb_strlen($note)>5000)))return ProgrammeApplicationResult::failure(422,'A note between 3 and 5000 characters is required.');
        try{return ProgrammeApplicationResult::success($message,$action(new DateTimeImmutable('now')));}catch(DomainException $e){return ProgrammeApplicationResult::failure(409,$e->getMessage());}
    }
    private function allowed(int $actor,string $permission):bool{return $actor>0&&$this->permissions->allows($actor,$permission);}
    private function optionalUuid(mixed $id):?string{return is_string($id)&&trim($id)!==''&&$this->uuid(trim($id))?trim($id):null;}
    private function uuid(string $id):bool{return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di',$id)===1;}
}
