<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use App\Services\Reporting\ReportFilters;
use App\Services\Reporting\ReportingRepositoryInterface;
use PDO;

final class ReportingRepository extends Repository implements ReportingRepositoryInterface
{
    public function __construct(Connection $database){parent::__construct($database);}

    public function filters():array
    {
        $db=$this->connection();
        return [
            'membership_grades'=>$db->query("SELECT public_id,name FROM membership_grades WHERE active=1 ORDER BY display_order,name LIMIT 100")->fetchAll(),
            'programmes'=>$db->query("SELECT public_id,code,name FROM programmes WHERE deleted_at IS NULL ORDER BY name LIMIT 500")->fetchAll(),
            'professional_areas'=>$db->query("SELECT public_id,name FROM professional_areas WHERE active=1 AND deleted_at IS NULL ORDER BY display_order,name LIMIT 250")->fetchAll(),
            'events'=>$db->query("SELECT public_id,title,event_date FROM events WHERE deleted_at IS NULL ORDER BY event_date DESC,title LIMIT 500")->fetchAll(),
        ];
    }

    public function dashboard(ReportFilters $f,bool $includeFinance):array
    {
        $db=$this->connection();$memberWhere=[];$memberParams=[];
        if($f->membershipGrade){$memberWhere[]='grades.public_id=:grade';$memberParams['grade']=$f->membershipGrade;}
        if($f->professionalArea){$memberWhere[]='profiles.professional_area=(SELECT name FROM professional_areas WHERE public_id=:area LIMIT 1)';$memberParams['area']=$f->professionalArea;}
        $memberSql=$memberWhere?' WHERE '.implode(' AND ',$memberWhere):'';
        $byGrade=$this->rows($db,"SELECT grades.name label,COUNT(*) total,SUM(members.status='active') active FROM members INNER JOIN membership_grades grades ON grades.id=members.membership_grade_id LEFT JOIN member_profiles profiles ON profiles.member_id=members.id{$memberSql} GROUP BY grades.id,grades.name ORDER BY total DESC,grades.name",$memberParams);
        $summary = array_reduce($byGrade, static function (array $totals, array $grade): array {
            $totals['total_members'] += (int) ($grade['total'] ?? 0);
            $totals['active_members'] += (int) ($grade['active'] ?? 0);
            return $totals;
        }, ['total_members' => 0, 'active_members' => 0]);

        $applicationWhere=['applications.submitted_at>=:app_start','applications.submitted_at<:app_end'];$applicationParams=['app_start'=>$f->start(),'app_end'=>$f->endExclusive()];
        if($f->membershipGrade){$applicationWhere[]='grades.public_id=:app_grade';$applicationParams['app_grade']=$f->membershipGrade;}
        if($f->status&&in_array($f->status,['draft','submitted','under_review','query_raised','approved','rejected','cancelled'],true)){$applicationWhere[]='applications.status=:app_status';$applicationParams['app_status']=$f->status;}
        $membershipApplications=$this->scalar($db,'SELECT COUNT(*) FROM membership_applications applications LEFT JOIN membership_grades grades ON grades.id=applications.membership_grade_id WHERE '.implode(' AND ',$applicationWhere),$applicationParams);
        $pending=$this->scalar($db,"SELECT COUNT(*) FROM membership_applications applications LEFT JOIN membership_grades grades ON grades.id=applications.membership_grade_id WHERE applications.status IN ('submitted','under_review','query_raised')".($f->membershipGrade?' AND grades.public_id=:pending_grade':''),$f->membershipGrade?['pending_grade'=>$f->membershipGrade]:[]);
        $renewalsDue=$this->scalar($db,"SELECT COUNT(*) FROM membership_renewals renewals INNER JOIN members ON members.id=renewals.member_id INNER JOIN membership_grades grades ON grades.id=members.membership_grade_id WHERE renewals.status IN ('renewal_due','invoice_generated','payment_pending') AND renewals.due_at<:renewal_end".($f->membershipGrade?' AND grades.public_id=:renewal_grade':''),array_filter(['renewal_end'=>$f->endExclusive(),'renewal_grade'=>$f->membershipGrade],static fn($v)=>$v!==null));

        $programmeWhere=['applications.submitted_at>=:programme_start','applications.submitted_at<:programme_end'];$programmeParams=['programme_start'=>$f->start(),'programme_end'=>$f->endExclusive()];if($f->programme){$programmeWhere[]='programmes.public_id=:programme';$programmeParams['programme']=$f->programme;}if($f->status&&in_array($f->status,['draft','submitted','review','approved','rejected','enrolled','completed','withdrawn'],true)){$programmeWhere[]='applications.status=:programme_status';$programmeParams['programme_status']=$f->status;}
        $programmeApplications=$this->scalar($db,'SELECT COUNT(*) FROM programme_applications applications INNER JOIN programmes ON programmes.id=applications.programme_id WHERE '.implode(' AND ',$programmeWhere),$programmeParams);
        $enrolWhere=['enrolments.enrolled_at>=:enrol_start','enrolments.enrolled_at<:enrol_end'];$enrolParams=['enrol_start'=>$f->start(),'enrol_end'=>$f->endExclusive()];if($f->programme){$enrolWhere[]='programmes.public_id=:enrol_programme';$enrolParams['enrol_programme']=$f->programme;}if($f->status&&in_array($f->status,['enrolled','completed','withdrawn'],true)){$enrolWhere[]='enrolments.status=:enrol_status';$enrolParams['enrol_status']=$f->status;}
        $programmeEnrolments=$this->scalar($db,'SELECT COUNT(*) FROM programme_enrolments enrolments INNER JOIN programmes ON programmes.id=enrolments.programme_id WHERE '.implode(' AND ',$enrolWhere),$enrolParams);

        $eventWhere=['registrations.registered_at>=:event_start','registrations.registered_at<:event_end'];$eventParams=['event_start'=>$f->start(),'event_end'=>$f->endExclusive()];if($f->event){$eventWhere[]='events.public_id=:event';$eventParams['event']=$f->event;}if($f->status&&in_array($f->status,['registered','cancelled','attended'],true)){$eventWhere[]='registrations.status=:event_status';$eventParams['event_status']=$f->status;}
        $eventRegistrations=$this->scalar($db,'SELECT COUNT(*) FROM event_registrations registrations INNER JOIN events ON events.id=registrations.event_id WHERE '.implode(' AND ',$eventWhere),$eventParams);

        $metrics=['summary'=>['total_members'=>(int)($summary['total_members']??0),'active_members'=>(int)($summary['active_members']??0),'membership_applications'=>$membershipApplications,'pending_reviews'=>$pending,'renewals_due'=>$renewalsDue,'programme_applications'=>$programmeApplications,'programme_enrolments'=>$programmeEnrolments,'event_registrations'=>$eventRegistrations],'members_by_grade'=>$byGrade,'finance'=>null];
        if($includeFinance){$paymentWhere="payments.status='successful' AND payments.paid_at>=:paid_start AND payments.paid_at<:paid_end";$p=['paid_start'=>$f->start(),'paid_end'=>$f->endExclusive()];$payment=$this->one($db,"SELECT COUNT(*) payments,COALESCE(SUM(amount_minor),0) revenue_minor,MIN(currency) currency_min,MAX(currency) currency_max FROM payments WHERE {$paymentWhere}",$p);$revenue=$this->rows($db,"SELECT items.fee_type category,invoices.currency,COALESCE(SUM(items.line_total_minor),0) revenue_minor,COUNT(DISTINCT invoices.id) invoices FROM invoices INNER JOIN invoice_items items ON items.invoice_id=invoices.id WHERE invoices.status='paid' AND invoices.paid_at>=:invoice_start AND invoices.paid_at<:invoice_end GROUP BY items.fee_type,invoices.currency ORDER BY revenue_minor DESC LIMIT 50",['invoice_start'=>$f->start(),'invoice_end'=>$f->endExclusive()]);$metrics['finance']=['payments'=>(int)($payment['payments']??0),'revenue_minor'=>(int)($payment['revenue_minor']??0),'currency'=>(($payment['currency_min']??null)===($payment['currency_max']??null)?($payment['currency_min']??null):'MULTI'),'by_category'=>$revenue];}
        return$metrics;
    }

    public function export(string $report,ReportFilters $f,int $limit):array
    {
        $limit=max(1,min(10000,$limit));$db=$this->connection();$start=$f->start();$end=$f->endExclusive();
        return match($report){
            'members'=>$this->rows($db,"SELECT members.membership_number,grades.name membership_grade,members.status,members.joined_at,members.expires_at,profiles.professional_area FROM members INNER JOIN membership_grades grades ON grades.id=members.membership_grade_id LEFT JOIN member_profiles profiles ON profiles.member_id=members.id WHERE members.joined_at>=:start AND members.joined_at<DATE(:end)".($f->membershipGrade?' AND grades.public_id=:grade':'').($f->professionalArea?' AND profiles.professional_area=(SELECT name FROM professional_areas WHERE public_id=:area LIMIT 1)':'').($f->status?' AND members.status=:status':'')." ORDER BY members.joined_at DESC,members.id DESC LIMIT {$limit}",array_filter(['start'=>$f->from,'end'=>$end,'grade'=>$f->membershipGrade,'area'=>$f->professionalArea,'status'=>$f->status],static fn($v)=>$v!==null)),
            'membership_applications'=>$this->rows($db,"SELECT applications.application_reference,grades.name membership_grade,applications.status,applications.submitted_at FROM membership_applications applications LEFT JOIN membership_grades grades ON grades.id=applications.membership_grade_id WHERE applications.submitted_at>=:start AND applications.submitted_at<:end".($f->membershipGrade?' AND grades.public_id=:grade':'').($f->status?' AND applications.status=:status':'')." ORDER BY applications.submitted_at DESC,applications.id DESC LIMIT {$limit}",array_filter(['start'=>$start,'end'=>$end,'grade'=>$f->membershipGrade,'status'=>$f->status],static fn($v)=>$v!==null)),
            'programme_applications'=>$this->rows($db,"SELECT applications.application_reference,programmes.code programme_code,programmes.name programme_name,applications.status,applications.submitted_at FROM programme_applications applications INNER JOIN programmes ON programmes.id=applications.programme_id WHERE applications.submitted_at>=:start AND applications.submitted_at<:end".($f->programme?' AND programmes.public_id=:programme':'').($f->status?' AND applications.status=:status':'')." ORDER BY applications.submitted_at DESC,applications.id DESC LIMIT {$limit}",array_filter(['start'=>$start,'end'=>$end,'programme'=>$f->programme,'status'=>$f->status],static fn($v)=>$v!==null)),
            'programme_enrolments'=>$this->rows($db,"SELECT enrolments.enrolment_number,programmes.code programme_code,programmes.name programme_name,enrolments.status,enrolments.enrolled_at,enrolments.completed_at,enrolments.withdrawn_at FROM programme_enrolments enrolments INNER JOIN programmes ON programmes.id=enrolments.programme_id WHERE enrolments.enrolled_at>=:start AND enrolments.enrolled_at<:end".($f->programme?' AND programmes.public_id=:programme':'').($f->status?' AND enrolments.status=:status':'')." ORDER BY enrolments.enrolled_at DESC,enrolments.id DESC LIMIT {$limit}",array_filter(['start'=>$start,'end'=>$end,'programme'=>$f->programme,'status'=>$f->status],static fn($v)=>$v!==null)),
            'event_registrations'=>$this->rows($db,"SELECT registrations.registration_reference,events.title event_title,events.event_date,registrations.status,registrations.registered_at FROM event_registrations registrations INNER JOIN events ON events.id=registrations.event_id WHERE registrations.registered_at>=:start AND registrations.registered_at<:end".($f->event?' AND events.public_id=:event':'').($f->status?' AND registrations.status=:status':'')." ORDER BY registrations.registered_at DESC,registrations.id DESC LIMIT {$limit}",array_filter(['start'=>$start,'end'=>$end,'event'=>$f->event,'status'=>$f->status],static fn($v)=>$v!==null)),
            'payments'=>$this->rows($db,"SELECT invoices.invoice_number,invoices.source_type category,payments.amount_minor,payments.currency,payments.status,payments.paid_at FROM payments INNER JOIN invoices ON invoices.id=payments.invoice_id WHERE payments.paid_at>=:start AND payments.paid_at<:end".($f->status?' AND payments.status=:status':'')." ORDER BY payments.paid_at DESC,payments.id DESC LIMIT {$limit}",array_filter(['start'=>$start,'end'=>$end,'status'=>$f->status],static fn($v)=>$v!==null)),
            default=>[],
        };
    }
    private function scalar(PDO$db,string$sql,array$params=[]):int{$s=$db->prepare($sql);$s->execute($params);return(int)$s->fetchColumn();}
    private function one(PDO$db,string$sql,array$params=[]):array{$s=$db->prepare($sql);$s->execute($params);$row=$s->fetch();return is_array($row)?$row:[];}
    private function rows(PDO$db,string$sql,array$params=[]):array{$s=$db->prepare($sql);$s->execute($params);return$s->fetchAll();}
}
