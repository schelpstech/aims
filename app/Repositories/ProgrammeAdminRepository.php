<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use App\Security\Security;
use App\Services\ProgrammeAdmin\ProgrammeAdminRepositoryInterface;
use DomainException;
use PDO;
use PDOException;

final class ProgrammeAdminRepository extends Repository implements ProgrammeAdminRepositoryInterface
{
    public function __construct(Connection $database) { parent::__construct($database); }

    public function dashboard(): array
    {
        $db = $this->connection();
        $types = $db->query("SELECT public_id, name FROM programme_types WHERE active = 1 ORDER BY display_order, name")->fetchAll();
        $areas = $db->query("SELECT public_id, code, name, slug, description, display_order, active FROM professional_areas WHERE deleted_at IS NULL ORDER BY display_order, name")->fetchAll();
        $programmes = $db->query(<<<'SQL'
            SELECT programmes.public_id, programmes.code, programmes.name, programmes.slug, programmes.description,
                   programmes.duration, programmes.entry_requirements, programmes.delivery_mode,
                   programmes.application_fee, programmes.tuition_fee, programmes.fee_currency,
                   programmes.status, programmes.display_order, types.public_id AS type_public_id, types.name AS type_name,
                   GROUP_CONCAT(areas.public_id ORDER BY programme_areas.display_order SEPARATOR ',') AS area_public_ids,
                   GROUP_CONCAT(areas.name ORDER BY programme_areas.display_order SEPARATOR ', ') AS area_names
            FROM programmes INNER JOIN programme_types AS types ON types.id = programmes.programme_type_id
            LEFT JOIN programme_areas ON programme_areas.programme_id = programmes.id
            LEFT JOIN professional_areas AS areas ON areas.id = programme_areas.professional_area_id
            WHERE programmes.deleted_at IS NULL
            GROUP BY programmes.id, types.id ORDER BY programmes.display_order, programmes.name
            SQL)->fetchAll();
        foreach ($programmes as &$programme) $programme['areas'] = ($programme['area_public_ids'] ?? '') === '' ? [] : explode(',', (string) $programme['area_public_ids']);
        unset($programme);
        $coordinators = $db->query(<<<'SQL'
            SELECT DISTINCT people.public_id, people.full_name, people.title
            FROM people
            INNER JOIN leadership_assignments ON leadership_assignments.person_id = people.id AND leadership_assignments.active = 1
            INNER JOIN leadership_positions ON leadership_positions.id = leadership_assignments.leadership_position_id AND leadership_positions.active = 1
            INNER JOIN leadership_groups ON leadership_groups.id = leadership_positions.leadership_group_id
            WHERE leadership_groups.slug = 'programme-coordinators' AND leadership_groups.active = 1
              AND people.active = 1 AND people.deleted_at IS NULL
              AND (leadership_assignments.starts_at IS NULL OR leadership_assignments.starts_at <= CURRENT_DATE)
              AND (leadership_assignments.ends_at IS NULL OR leadership_assignments.ends_at >= CURRENT_DATE)
            ORDER BY people.full_name
            SQL)->fetchAll();
        $assignments = $db->query(<<<'SQL'
            SELECT assignments.public_id, people.full_name, people.title, assignments.role_title,
                   programmes.name AS programme_name, areas.name AS area_name, assignments.starts_at, assignments.ends_at
            FROM coordinator_assignments AS assignments
            INNER JOIN people ON people.id = assignments.person_id
            LEFT JOIN programmes ON programmes.id = assignments.programme_id
            LEFT JOIN professional_areas AS areas ON areas.id = assignments.professional_area_id
            WHERE assignments.active = 1 ORDER BY assignments.display_order, people.full_name
            SQL)->fetchAll();
        return compact('types', 'areas', 'programmes', 'coordinators', 'assignments');
    }

    public function saveProgramme(int $actor, ?string $publicId, array $data): string
    {
        try {
            return $this->database->transaction(function (PDO $db) use ($actor, $publicId, $data): string {
                $typeId = $this->idFor($db, 'programme_types', (string) $data['programme_type'], 'active = 1');
                if ($typeId === null) throw new DomainException('The selected programme type is unavailable.');
                $id = null; $created = $publicId === null;
                if ($publicId !== null) $id = $this->idFor($db, 'programmes', $publicId, 'deleted_at IS NULL', true);
                $publicId ??= Security::uuidV4();
                $parameters = [
                    'public_id' => $publicId, 'type_id' => $typeId, 'code' => $data['code'], 'name' => $data['name'], 'slug' => $data['slug'],
                    'description' => $data['description'], 'duration' => $data['duration'], 'requirements' => $data['entry_requirements'],
                    'delivery_mode' => $data['delivery_mode'], 'application_fee' => $data['application_fee'], 'tuition_fee' => $data['tuition_fee'],
                    'currency' => $data['fee_currency'], 'status' => $data['status'], 'display_order' => $data['display_order'],
                ];
                if ($created) {
                    $statement = $db->prepare("INSERT INTO programmes (public_id, programme_type_id, code, name, slug, description, duration, entry_requirements, delivery_mode, application_fee, tuition_fee, fee_currency, status, display_order) VALUES (:public_id,:type_id,:code,:name,:slug,:description,:duration,:requirements,:delivery_mode,:application_fee,:tuition_fee,:currency,:status,:display_order)");
                    $statement->execute($parameters); $id = (int) $db->lastInsertId();
                } else {
                    $parameters['id'] = $id;
                    $statement = $db->prepare("UPDATE programmes SET programme_type_id=:type_id,code=:code,name=:name,slug=:slug,description=:description,duration=:duration,entry_requirements=:requirements,delivery_mode=:delivery_mode,application_fee=:application_fee,tuition_fee=:tuition_fee,fee_currency=:currency,status=:status,display_order=:display_order WHERE id=:id");
                    unset($parameters['public_id']); $statement->execute($parameters);
                }
                $db->prepare('DELETE FROM programme_areas WHERE programme_id = :id')->execute(['id' => $id]);
                foreach ($data['areas'] as $order => $areaPublicId) {
                    $areaId = $this->idFor($db, 'professional_areas', (string) $areaPublicId, 'active = 1 AND deleted_at IS NULL');
                    if ($areaId === null) throw new DomainException('A selected professional area is unavailable.');
                    $link = $db->prepare('INSERT INTO programme_areas (programme_id, professional_area_id, is_primary, display_order) VALUES (:programme,:area,:primary_area,:display_order)');
                    $link->execute(['programme' => $id, 'area' => $areaId, 'primary_area' => $order === 0 ? 1 : 0, 'display_order' => $order]);
                }
                if ($data['status'] === 'archived') {
                    $db->prepare('UPDATE coordinator_assignments SET active=0 WHERE programme_id=:id')->execute(['id'=>$id]);
                }
                $this->audit($db, $actor, $created ? 'programme.created' : 'programme.updated', 'programme', $publicId);
                return $publicId;
            });
        } catch (PDOException $e) { if ($e->getCode() === '23000') throw new DomainException('Programme code or name conflicts with an existing record.'); throw $e; }
    }

    public function archiveProgramme(int $actor, string $publicId): void
    {
        $this->database->transaction(function (PDO $db) use ($actor, $publicId): void {
            $statement = $db->prepare("UPDATE programmes SET status='archived' WHERE public_id=:id AND deleted_at IS NULL AND status <> 'archived'");
            $statement->execute(['id' => $publicId]); if ($statement->rowCount() !== 1) throw new DomainException('Programme not found.');
            $db->prepare('UPDATE coordinator_assignments SET active=0 WHERE programme_id=(SELECT id FROM programmes WHERE public_id=:id)')->execute(['id' => $publicId]);
            $this->audit($db, $actor, 'programme.archived', 'programme', $publicId);
        });
    }

    public function saveArea(int $actor, ?string $publicId, array $data): string
    {
        try {
            return $this->database->transaction(function (PDO $db) use ($actor, $publicId, $data): string {
                $created = $publicId === null; $id = $created ? null : $this->idFor($db, 'professional_areas', (string) $publicId, 'deleted_at IS NULL', true); $publicId ??= Security::uuidV4();
                $params = ['public_id'=>$publicId,'code'=>$data['code'],'name'=>$data['name'],'slug'=>$data['slug'],'description'=>$data['description'],'display_order'=>$data['display_order'],'active'=>$data['active']];
                if ($created) $db->prepare('INSERT INTO professional_areas (public_id,code,name,slug,description,display_order,active) VALUES (:public_id,:code,:name,:slug,:description,:display_order,:active)')->execute($params);
                else { $params['id']=$id; unset($params['public_id']); $db->prepare('UPDATE professional_areas SET code=:code,name=:name,slug=:slug,description=:description,display_order=:display_order,active=:active WHERE id=:id')->execute($params); }
                $this->audit($db, $actor, $created ? 'professional_area.created' : 'professional_area.updated', 'professional_area', $publicId); return $publicId;
            });
        } catch (PDOException $e) { if ($e->getCode() === '23000') throw new DomainException('Area code or name conflicts with an existing record.'); throw $e; }
    }

    public function archiveArea(int $actor, string $publicId): void
    {
        $this->database->transaction(function (PDO $db) use ($actor, $publicId): void {
            $areaId = $this->idFor($db, 'professional_areas', $publicId, 'deleted_at IS NULL', true);
            $linked = $db->prepare('SELECT COUNT(*) FROM programme_areas INNER JOIN programmes ON programmes.id=programme_areas.programme_id AND programmes.deleted_at IS NULL WHERE programme_areas.professional_area_id=:id'); $linked->execute(['id'=>$areaId]);
            if ((int) $linked->fetchColumn() > 0) throw new DomainException('This area is linked to a programme and cannot be archived.');
            $db->prepare('UPDATE professional_areas SET active=0,deleted_at=CURRENT_TIMESTAMP(6) WHERE id=:id')->execute(['id'=>$areaId]);
            $db->prepare('UPDATE coordinator_assignments SET active=0 WHERE professional_area_id=:id')->execute(['id'=>$areaId]);
            $this->audit($db, $actor, 'professional_area.archived', 'professional_area', $publicId);
        });
    }

    public function assignCoordinator(int $actor, array $data): void
    {
        $this->database->transaction(function (PDO $db) use ($actor, $data): void {
            $eligible = $db->prepare("SELECT people.id FROM people INNER JOIN leadership_assignments la ON la.person_id=people.id AND la.active=1 AND (la.starts_at IS NULL OR la.starts_at<=CURRENT_DATE) AND (la.ends_at IS NULL OR la.ends_at>=CURRENT_DATE) INNER JOIN leadership_positions lp ON lp.id=la.leadership_position_id AND lp.active=1 INNER JOIN leadership_groups lg ON lg.id=lp.leadership_group_id AND lg.slug='programme-coordinators' AND lg.active=1 WHERE people.public_id=:id AND people.active=1 AND people.deleted_at IS NULL LIMIT 1");
            $eligible->execute(['id'=>$data['person']]); $personId=$eligible->fetchColumn(); if ($personId === false) throw new DomainException('The selected person is not an active programme coordinator.');
            $programmeId = $data['programme'] ? $this->idFor($db,'programmes',(string)$data['programme'],"status <> 'archived' AND deleted_at IS NULL") : null;
            $areaId = $data['area'] ? $this->idFor($db,'professional_areas',(string)$data['area'],'active=1 AND deleted_at IS NULL') : null;
            if (($data['programme'] && !$programmeId) || ($data['area'] && !$areaId)) throw new DomainException('The selected assignment target is unavailable.');
            $duplicate=$db->prepare('SELECT id FROM coordinator_assignments WHERE person_id=:person AND programme_id <=> :programme AND professional_area_id <=> :area AND active=1 LIMIT 1');
            $duplicate->execute(['person'=>$personId,'programme'=>$programmeId,'area'=>$areaId]);
            if ($duplicate->fetchColumn() !== false) throw new DomainException('This coordinator already has the selected active assignment.');
            $publicId=Security::uuidV4(); $db->prepare('INSERT INTO coordinator_assignments (public_id,person_id,programme_id,professional_area_id,role_title,display_order,starts_at,ends_at) VALUES (:public_id,:person,:programme,:area,:role_title,:display_order,:starts_at,:ends_at)')->execute(['public_id'=>$publicId,'person'=>$personId,'programme'=>$programmeId,'area'=>$areaId,'role_title'=>$data['role_title'],'display_order'=>$data['display_order'],'starts_at'=>$data['starts_at'],'ends_at'=>$data['ends_at']]);
            $this->audit($db,$actor,'coordinator_assignment.created','coordinator_assignment',$publicId);
        });
    }

    public function removeCoordinator(int $actor, string $publicId): void
    {
        $this->database->transaction(function (PDO $db) use ($actor, $publicId): void {
            $statement=$db->prepare('UPDATE coordinator_assignments SET active=0 WHERE public_id=:id AND active=1');
            $statement->execute(['id'=>$publicId]);
            if ($statement->rowCount() !== 1) throw new DomainException('Coordinator assignment not found.');
            $this->audit($db,$actor,'coordinator_assignment.removed','coordinator_assignment',$publicId);
        });
    }

    private function idFor(PDO $db, string $table, string $publicId, string $condition, bool $required=false): ?int
    {
        $allowed=['programme_types','programmes','professional_areas']; if (!in_array($table,$allowed,true)) throw new DomainException('Invalid catalogue lookup.');
        $statement=$db->prepare("SELECT id FROM {$table} WHERE public_id=:id AND {$condition} LIMIT 1"); $statement->execute(['id'=>$publicId]); $id=$statement->fetchColumn();
        if ($id === false) { if ($required) throw new DomainException('Catalogue record not found.'); return null; } return (int)$id;
    }

    private function audit(PDO $db, int $actor, string $action, string $type, string $publicId): void
    {
        $db->prepare("INSERT INTO audit_logs (actor_user_id,action,auditable_type,auditable_id,description,request_id,created_at) VALUES (:actor,:action,:type,:id,'A programme catalogue administrative action was completed.',:request_id,CURRENT_TIMESTAMP(6))")->execute(['actor'=>$actor,'action'=>$action,'type'=>$type,'id'=>$publicId,'request_id'=>bin2hex(random_bytes(16))]);
    }
}
