<?php

declare(strict_types=1);

use App\Authorization\PermissionCheckerInterface;
use App\Services\ProgrammeApplications\ProgrammeApplicationRepositoryInterface;
use App\Services\ProgrammeApplications\ProgrammeApplicationService;
use App\Validation\Validator;

require dirname(__DIR__).'/vendor/autoload.php';

$assert=static function(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);};

try{
    $applicationId='10000000-0000-4000-8000-000000000001';$programmeId='20000000-0000-4000-8000-000000000001';
    $repository=new class($applicationId,$programmeId) implements ProgrammeApplicationRepositoryInterface{
        public int $writes=0;public string $lastAction='';
        public function __construct(private string $applicationId,private string $programmeId){}
        public function publishedProgrammes():array{return [['public_id'=>$this->programmeId,'code'=>'PRO-1','name'=>'Professional Programme']];}
        public function applicationsForUser(int $userId):array{return [];}
        public function applicationForUser(int $userId,string $publicId):?array{return $userId===7&&$publicId===$this->applicationId?['public_id'=>$publicId,'status'=>'draft','motivation'=>'A sufficiently detailed motivation statement.','professional_background'=>'A sufficiently detailed professional background.','highest_qualification'=>'Professional Diploma']:null;}
        public function saveDraft(int $userId,?string $publicId,string $programmePublicId,array $data,DateTimeImmutable $now):array{$this->writes++;$this->lastAction='draft';return ['public_id'=>$publicId??$this->applicationId,'status'=>'draft'];}
        public function submit(int $userId,string $publicId,string $reference,string $declarationName,DateTimeImmutable $now):array{$this->writes++;$this->lastAction='submit';return ['public_id'=>$publicId,'status'=>'submitted','application_reference'=>$reference];}
        public function withdraw(int $userId,string $publicId,DateTimeImmutable $now):void{$this->writes++;$this->lastAction='withdraw';}
        public function search(array $filters):array{return ['items'=>[]];}
        public function administrativeApplication(string $publicId):?array{return ['public_id'=>$publicId,'status'=>'review'];}
        public function review(int $actor,string $publicId,?string $note,DateTimeImmutable $now):array{$this->writes++;$this->lastAction='review';return ['status'=>'review'];}
        public function approve(int $actor,string $publicId,?string $note,DateTimeImmutable $now):array{$this->writes++;$this->lastAction='approve';return ['status'=>'approved'];}
        public function reject(int $actor,string $publicId,string $note,DateTimeImmutable $now):array{$this->writes++;$this->lastAction='reject';return ['status'=>'rejected'];}
        public function enrol(int $actor,string $publicId,string $numberPrefix,DateTimeImmutable $now):array{$this->writes++;$this->lastAction='enrol';return ['idempotent'=>false,'enrolment'=>['status'=>'enrolled']];}
        public function complete(int $actor,string $publicId,DateTimeImmutable $now):array{$this->writes++;$this->lastAction='complete';return ['status'=>'completed'];}
        public function withdrawEnrolment(int $actor,string $publicId,string $note,DateTimeImmutable $now):array{$this->writes++;$this->lastAction='withdraw_enrolment';return ['status'=>'withdrawn'];}
    };
    $denied=new class implements PermissionCheckerInterface{public function allows(int $userId,string $permission):bool{return false;}};
    $service=new ProgrammeApplicationService($repository,$denied,new Validator());
    $assert($service->applicantDashboard(7)['programmes'][0]['code']==='PRO-1','Published programme eligibility catalogue was not loaded.');
    $unauthenticated=$service->saveDraft(0,[]);$assert($unauthenticated->status===401&&$repository->writes===0,'Unauthenticated draft write was not rejected.');
    $invalid=$service->saveDraft(7,['programme'=>'invalid']);$assert($invalid->status===422&&$repository->writes===0,'Invalid programme identifier reached persistence.');
    $draft=$service->saveDraft(7,['programme'=>$programmeId,'motivation'=>'Draft']);$assert($draft->successful&&$repository->lastAction==='draft','Valid programme draft was not saved.');
    $badSubmission=$service->submit(7,$applicationId==='x'?[]:['application_public_id'=>$applicationId,'declaration_name'=>'Applicant Name']);$assert($badSubmission->status===422,'Submission without declaration acceptance was allowed.');
    $submitted=$service->submit(7,['application_public_id'=>$applicationId,'declaration_name'=>'Applicant Name','declaration_accepted'=>'1']);$assert($submitted->successful&&$repository->lastAction==='submit','Complete application was not submitted.');
    $adminDenied=$service->approve(99,$applicationId,null);$assert($adminDenied->status===403,'Administrative approval did not enforce RBAC.');
    $allowed=new class implements PermissionCheckerInterface{public function allows(int $userId,string $permission):bool{return $userId===10&&in_array($permission,['programme.application_view','programme.application_review','programme.application_approve','programme.application_reject','programme.enrol'],true);}};
    $service=new ProgrammeApplicationService($repository,$allowed,new Validator(),'AIMS-PRG');
    $assert($service->review(10,$applicationId,'Reviewed')->successful&&$repository->lastAction==='review','Authorized review failed.');
    $assert($service->approve(10,$applicationId,null)->successful&&$repository->lastAction==='approve','Authorized approval failed.');
    $assert($service->enrol(10,$applicationId)->successful&&$repository->lastAction==='enrol','Authorized enrolment failed.');
    $assert($service->complete(10,$applicationId)->successful&&$repository->lastAction==='complete','Authorized completion failed.');
    $invalidWithdrawal=$service->withdrawEnrolment(10,$applicationId,'');$assert($invalidWithdrawal->status===422,'Enrolment withdrawal without a reason was allowed.');
    $reviewOnly=new class implements PermissionCheckerInterface{public function allows(int $userId,string $permission):bool{return $userId===11&&$permission==='programme.application_review';}};
    $service=new ProgrammeApplicationService($repository,$reviewOnly,new Validator());
    $assert($service->review(11,$applicationId,'Reviewed')->successful,'Programme reviewer could not perform the permitted review action.');
    $writesBefore=$repository->writes;
    $assert($service->approve(11,$applicationId,null)->status===403&&$service->enrol(11,$applicationId)->status===403&&$repository->writes===$writesBefore,'Review-only authority escalated into approval or enrolment.');

    $migration=(string)file_get_contents(dirname(__DIR__).'/database/migrations/20260904_210000_create_programme_application_enrolment_tables.php');
    foreach(['CREATE TABLE programme_applications','CREATE TABLE programme_enrolments','uq_programme_applications_user_programme','uq_programme_enrolments_application','ENGINE=InnoDB',"'draft','submitted','review','approved','rejected','enrolled','completed','withdrawn'"] as $needle)$assert(str_contains($migration,$needle),"Stage 10 migration is missing {$needle}.");
    $repositoryCode=(string)file_get_contents(dirname(__DIR__).'/app/Repositories/ProgrammeApplicationRepository.php');
    foreach(['FOR UPDATE','programme_application.approved','programme_enrolment.created','idempotent','transaction(function'] as $needle)$assert(str_contains($repositoryCode,$needle),"Transactional or audit protection is missing {$needle}.");
    $assert(str_contains($repositoryCode,'user_id=:user'),'Applicant ownership is not enforced in repository queries.');
    $routes=(string)file_get_contents(dirname(__DIR__).'/routes/web.php');foreach(['/account/programme-applications','/admin/programme-applications','application_view','application_review','application_approve','application_reject',"programmePermissions['enrol']"] as $needle)$assert(str_contains($routes,$needle),"Stage 10 route protection is missing {$needle}.");
    $scope=strtolower($migration.$repositoryCode);foreach(['student_results','result_scores','academic_grades','transcripts'] as $forbidden)$assert(!str_contains($scope,$forbidden),"Out-of-scope academic feature detected: {$forbidden}.");
    echo "Programme application and enrolment checks passed: applicant validation, ownership, RBAC, workflow, transactions, idempotency, audit hooks, and academic-scope exclusion.\n";
}catch(Throwable $exception){fwrite(STDERR,'Programme application/enrolment check failed: '.$exception->getMessage().PHP_EOL);exit(1);}
