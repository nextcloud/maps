<?php

/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2019
 */

namespace OCA\Maps\Service;

use OCP\IL10N;
use OCP\ILogger;
use OCP\DB\QueryBuilder\IQueryBuilder;

class DevicesService {

    private $l10n;
    private $logger;
    private $qb;

    public function __construct (ILogger $logger, IL10N $l10n) {
        $this->l10n = $l10n;
        $this->logger = $logger;
        $this->qb = \OC::$server->getDatabaseConnection()->getQueryBuilder();
    }

    /**
     * @param string $userId
     * @param int $pruneBefore
     * @return array with devices
     */
    public function getDevicesFromDB($userId, $pruneBefore=0) {
        $deviceIds = [];
        $qb = $this->qb;
        $qb->select('id')
            ->from('maps_devices', 'd')
            ->where(
                $qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
            );
        $req = $qb->execute();

        while ($row = $req->fetch()) {
            array_push($deviceIds, intval($row['id']));
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        // get coordinates
        $pointsByDevice = [];
        foreach ($deviceIds as $deviceId) {
            $qb->select('id', 'lat', 'lng', 'timestamp', 'altitude', 'accuracy', 'battery')
                ->from('maps_device_points', 'p')
                ->where(
                    $qb->expr()->eq('device_id', $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT))
                );
            if (intval($pruneBefore) > 0) {
                $qb->andWhere(
                    $qb->expr()->gt('timestamp', $qb->createNamedParameter($pruneBefore, IQueryBuilder::PARAM_INT))
                );
            }
            $qb->orderBy('timestamp', 'ASC');
            $req = $qb->execute();

            $points = [];
            while ($row = $req->fetch()) {
                array_push($points, [
                    'id' => intval($row['id']),
                    'lat' => floatval($row['lat']),
                    'lng' => floatval($row['lng']),
                    'timestamp' => intval($row['timestamp']),
                    'altitude' => floatval($row['altitude']),
                    'accuracy' => floatval($row['accuracy']),
                    'battery' => floatval($row['battery'])
                ]);
            }
            $pointsByDevice[$deviceId] = $points;
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        // build device list
        $devices = [];
        $qb = $this->qb;
        $qb->select('id', 'user_agent', 'color')
            ->from('maps_devices', 'd')
            ->where(
                $qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
            );
        $req = $qb->execute();

        while ($row = $req->fetch()) {
            array_push($devices, [
                'id' => intval($row['id']),
                'user_agent' => $row['user_agent'],
                'color' => $row['color'],
                'points' => $pointsByDevice[intval($row['id'])]
            ]);
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();
        return $devices;
    }

    public function getOrCreateDeviceFromDB($userId, $userAgent) {
        $deviceId = null;
        $qb = $this->qb;
        $qb->select('id')
            ->from('maps_devices', 'd')
            ->where(
                $qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
            )
            ->andWhere(
                $qb->expr()->eq('user_agent', $qb->createNamedParameter($userAgent, IQueryBuilder::PARAM_STR))
            );
        $req = $qb->execute();

        while ($row = $req->fetch()) {
            $deviceId = intval($row['id']);
            break;
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        if ($deviceId === null) {
            $qb->insert('maps_devices')
                ->values([
                    'user_agent' => $qb->createNamedParameter($userAgent, IQueryBuilder::PARAM_STR),
                    'user_id' => $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)
                ]);
            $req = $qb->execute();
            $deviceId = $qb->getLastInsertId();
            $qb = $qb->resetQueryParts();
        }
        return $deviceId;
    }

    public function addPointToDB($deviceId, $lat, $lng, $ts, $altitude, $battery, $accuracy) {
        $qb = $this->qb;
        $qb->insert('maps_device_points')
            ->values([
                'device_id' => $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_STR),
                'lat' => $qb->createNamedParameter($lat, IQueryBuilder::PARAM_STR),
                'lng' => $qb->createNamedParameter($lng, IQueryBuilder::PARAM_STR),
                'timestamp' => $qb->createNamedParameter($ts, IQueryBuilder::PARAM_INT),
                'altitude' => $qb->createNamedParameter($altitude, IQueryBuilder::PARAM_STR),
                'battery' => $qb->createNamedParameter($battery, IQueryBuilder::PARAM_STR),
                'accuracy' => $qb->createNamedParameter($accuracy, IQueryBuilder::PARAM_STR)
            ]);
        $req = $qb->execute();
        $pointId = $qb->getLastInsertId();
        $qb = $qb->resetQueryParts();
        return $pointId;
    }

    public function getDeviceFromDB($id, $userId) {
        $device = null;
        $qb = $this->qb;
        $qb->select('id', 'user_agent', 'color')
            ->from('maps_devices', 'd')
            ->where(
                $qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
            );
        if ($userId !== null) {
            $qb->andWhere(
                $qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
            );
        }
        $req = $qb->execute();

        while ($row = $req->fetch()) {
            $device = [
                'id' => intval($row['id']),
                'user_agent' => $row['user_agent'],
                'color' => $row['color']
            ];
            break;
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();
        return $device;
    }

    public function editDeviceInDB($id, $color) {
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->update('maps_devices');
        $qb->set('color', $qb->createNamedParameter($color, IQueryBuilder::PARAM_STR));
        $qb->where(
            $qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
        );
        $req = $qb->execute();
        $qb = $qb->resetQueryParts();
    }

    public function deleteDeviceFromDB($id) {
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->delete('maps_devices')
            ->where(
                $qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
            );
        $req = $qb->execute();
        $qb = $qb->resetQueryParts();

        $qb->delete('maps_device_points')
            ->where(
                $qb->expr()->eq('device_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
            );
        $req = $qb->execute();
        $qb = $qb->resetQueryParts();
    }

    public function countPoints($userId, $deviceIdList, $begin, $end) {
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select($qb->createFunction('COUNT(*)'))
            ->from('maps_devices', 'd')
            ->innerJoin('bo', 'maps_device_points', 'p', $qb->expr()->eq('d.id', 'p.device_id'))
            ->where(
                $qb->expr()->eq('d.user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_INT))
            );
        if (is_array($deviceIdList) and count($deviceIdList) > 0) {
            $or = $qb->expr()->orx();
            foreach ($deviceIdList as $deviceId) {
                $or->add($qb->expr()->eq('d.id', $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT)));
            }
            $qb->andWhere($or);
        }
        else {
            return;
        }
        if ($begin !== null) {
            $qb->andWhere(
                $qb->expr()->gt('p.timestamp', $qb->createNamedParameter($begin, IQueryBuilder::PARAM_INT))
            );
        }
        if ($end !== null) {
            $qb->andWhere(
                $qb->expr()->lt('timestamp', $qb->createNamedParameter($end, IQueryBuilder::PARAM_INT))
            );
        }
        $req = $qb->execute();
        $count = 0;
        while ($row = $req->fetch()) {
            $count = intval($row['COUNT(*)']);
            break;
        }
        $qb = $qb->resetQueryParts();

        return $count;
    }

    public function exportDevices($userId, $handler, $deviceIdList, $begin, $end, $appVersion) {
    }

}
